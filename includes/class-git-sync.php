<?php
/**
 * One-way Git sync: fetch remote folder, Diff vs local, Pull (replace).
 *
 * Uses `git` CLI when available; falls back to GitHub/GitLab ZIP download
 * for public (or token-authenticated) HTTPS repos.
 *
 * @package RepoCodeSnippets
 */

namespace RCS;

defined( 'ABSPATH' ) || exit;

class Git_Sync {

	/**
	 * @var Storage
	 */
	private $storage;

	/**
	 * @param Storage $storage
	 */
	public function __construct( Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Whether git CLI is usable.
	 *
	 * @return bool
	 */
	public function has_git_cli() {
		if ( ! function_exists( 'exec' ) ) {
			return false;
		}
		$output = array();
		$code   = 1;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		@exec( 'git --version 2>&1', $output, $code );
		return 0 === (int) $code;
	}

	/**
	 * Fetch remote into cache and return snippets parsed from configured folder.
	 *
	 * @return array|\WP_Error Map slug => { meta, code }
	 */
	public function fetch_remote_snippets() {
		$settings = Plugin::get_settings();
		$url      = trim( (string) $settings['git_url'] );
		$folder   = trim( (string) $settings['git_folder'], "/ \t" );
		$branch   = trim( (string) $settings['git_branch'] );
		$token    = (string) $settings['git_token'];

		if ( '' === $url ) {
			return new \WP_Error( 'rcs_git_url', __( 'Git repository URL is not configured.', 'repo-code-snippets' ) );
		}
		if ( '' === $folder ) {
			$folder = 'snippets';
		}
		if ( '' === $branch ) {
			$branch = 'main';
		}

		$cache = $this->storage->git_cache_dir();
		$this->storage->ensure_directories();

		if ( $this->has_git_cli() ) {
			$result = $this->clone_or_pull( $url, $branch, $token, $cache );
		} else {
			$result = $this->download_zip( $url, $branch, $token, $cache );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$snippets_path = trailingslashit( $cache ) . 'repo/' . $folder;
		if ( ! is_dir( $snippets_path ) ) {
			return new \WP_Error(
				'rcs_git_folder',
				sprintf(
					/* translators: %s: folder path */
					__( 'Folder "%s" was not found in the repository.', 'repo-code-snippets' ),
					$folder
				)
			);
		}

		return $this->parse_folder( $snippets_path );
	}

	/**
	 * Compare local WP snippets with remote folder.
	 *
	 * @return array|\WP_Error {
	 *   only_local: string[],
	 *   only_remote: string[],
	 *   changed: array[],
	 *   identical: string[],
	 *   summary: string
	 * }
	 */
	public function diff() {
		$remote = $this->fetch_remote_snippets();
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}

		$local = $this->storage->export_map();

		$only_local  = array_values( array_diff( array_keys( $local ), array_keys( $remote ) ) );
		$only_remote = array_values( array_diff( array_keys( $remote ), array_keys( $local ) ) );
		$changed     = array();
		$identical   = array();

		foreach ( array_intersect( array_keys( $local ), array_keys( $remote ) ) as $slug ) {
			$l = $local[ $slug ];
			$r = $remote[ $slug ];

			$diffs = array();
			if ( $this->normalize_compare( $l['code'] ) !== $this->normalize_compare( $r['code'] ) ) {
				$diffs[] = 'code';
			}

			$meta_keys = array( 'name', 'type', 'status', 'scope', 'priority', 'description' );
			foreach ( $meta_keys as $key ) {
				$lv = isset( $l['meta'][ $key ] ) ? (string) $l['meta'][ $key ] : '';
				$rv = isset( $r['meta'][ $key ] ) ? (string) $r['meta'][ $key ] : '';
				if ( $lv !== $rv ) {
					$diffs[] = 'meta:' . $key;
				}
			}

			if ( empty( $diffs ) ) {
				$identical[] = $slug;
			} else {
				$changed[] = array(
					'slug'  => $slug,
					'diffs' => $diffs,
					'local' => array(
						'meta' => $l['meta'],
						'code' => $l['code'],
					),
					'remote' => array(
						'meta' => $r['meta'],
						'code' => $r['code'],
					),
				);
			}
		}

		$total = count( $only_local ) + count( $only_remote ) + count( $changed );
		if ( 0 === $total ) {
			$summary = __( 'No differences. WordPress snippets match the Git folder.', 'repo-code-snippets' );
		} else {
			$summary = sprintf(
				/* translators: 1: changed count, 2: only local, 3: only remote */
				__( '%1$d changed, %2$d only on WordPress, %3$d only in Git.', 'repo-code-snippets' ),
				count( $changed ),
				count( $only_local ),
				count( $only_remote )
			);
		}

		return array(
			'only_local'  => $only_local,
			'only_remote' => $only_remote,
			'changed'     => $changed,
			'identical'   => $identical,
			'summary'     => $summary,
			'has_diff'    => $total > 0,
		);
	}

	/**
	 * Pull remote snippets and replace local storage entirely.
	 *
	 * @return true|\WP_Error
	 */
	public function pull() {
		$remote = $this->fetch_remote_snippets();
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}

		if ( empty( $remote ) ) {
			return new \WP_Error(
				'rcs_git_empty',
				__( 'Remote folder has no snippets. Pull aborted to avoid deleting local snippets.', 'repo-code-snippets' )
			);
		}

		$list = array_values( $remote );
		return $this->storage->replace_all( $list );
	}

	/**
	 * @param string $url
	 * @param string $branch
	 * @param string $token
	 * @param string $cache
	 * @return true|\WP_Error
	 */
	private function clone_or_pull( $url, $branch, $token, $cache ) {
		$repo_dir = trailingslashit( $cache ) . 'repo';
		$auth_url = $this->auth_url( $url, $token );

		if ( is_dir( $repo_dir . '/.git' ) ) {
			$cmds = array(
				'git -C ' . escapeshellarg( $repo_dir ) . ' remote set-url origin ' . escapeshellarg( $auth_url ),
				'git -C ' . escapeshellarg( $repo_dir ) . ' fetch --depth 1 origin ' . escapeshellarg( $branch ),
				'git -C ' . escapeshellarg( $repo_dir ) . ' checkout -f FETCH_HEAD',
			);
		} else {
			$this->rrmdir( $repo_dir );
			$cmds = array(
				'git clone --depth 1 --branch ' . escapeshellarg( $branch ) . ' ' . escapeshellarg( $auth_url ) . ' ' . escapeshellarg( $repo_dir ),
			);
		}

		foreach ( $cmds as $cmd ) {
			$output = array();
			$code   = 0;
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
			exec( $cmd . ' 2>&1', $output, $code );
			if ( 0 !== (int) $code ) {
				return new \WP_Error(
					'rcs_git_cli',
					implode( "\n", $output ) ?: __( 'Git command failed.', 'repo-code-snippets' )
				);
			}
		}

		return true;
	}

	/**
	 * Download ZIP archive (GitHub / GitLab style URLs).
	 *
	 * @param string $url
	 * @param string $branch
	 * @param string $token
	 * @param string $cache
	 * @return true|\WP_Error
	 */
	private function download_zip( $url, $branch, $token, $cache ) {
		$zip_url = $this->resolve_zip_url( $url, $branch );
		if ( is_wp_error( $zip_url ) ) {
			return $zip_url;
		}

		$headers = array();
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
			$headers['Accept']        = 'application/vnd.github+json';
		}

		$response = wp_remote_get(
			$zip_url,
			array(
				'timeout' => 60,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new \WP_Error(
				'rcs_zip_http',
				sprintf(
					/* translators: %d: HTTP status */
					__( 'Could not download repository archive (HTTP %d).', 'repo-code-snippets' ),
					(int) $code
				)
			);
		}

		$zip_path = trailingslashit( $cache ) . 'repo.zip';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $zip_path, wp_remote_retrieve_body( $response ) );

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'rcs_zip', __( 'PHP ZipArchive extension is required when git CLI is unavailable.', 'repo-code-snippets' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new \WP_Error( 'rcs_zip_open', __( 'Could not open downloaded ZIP.', 'repo-code-snippets' ) );
		}

		$extract_to = trailingslashit( $cache ) . 'extract';
		$this->rrmdir( $extract_to );
		wp_mkdir_p( $extract_to );
		$zip->extractTo( $extract_to );
		$zip->close();

		// GitHub ZIPs extract to {repo}-{branch}/ — move into cache/repo.
		$entries = array_values(
			array_filter(
				scandir( $extract_to ),
				static function ( $e ) {
					return '.' !== $e && '..' !== $e;
				}
			)
		);

		if ( empty( $entries ) ) {
			return new \WP_Error( 'rcs_zip_empty', __( 'Downloaded archive was empty.', 'repo-code-snippets' ) );
		}

		$repo_dir = trailingslashit( $cache ) . 'repo';
		$this->rrmdir( $repo_dir );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		rename( trailingslashit( $extract_to ) . $entries[0], $repo_dir );
		$this->rrmdir( $extract_to );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		@unlink( $zip_path );

		return true;
	}

	/**
	 * Build ZIP download URL for common hosts.
	 *
	 * @param string $url
	 * @param string $branch
	 * @return string|\WP_Error
	 */
	private function resolve_zip_url( $url, $branch ) {
		$url = preg_replace( '/\.git$/', '', rtrim( $url, '/' ) );

		// https://github.com/owner/repo
		if ( preg_match( '#^https?://github\.com/([^/]+)/([^/]+)#i', $url, $m ) ) {
			return sprintf( 'https://codeload.github.com/%s/%s/zip/refs/heads/%s', $m[1], $m[2], rawurlencode( $branch ) );
		}

		// https://gitlab.com/owner/repo
		if ( preg_match( '#^https?://([^/]*gitlab[^/]*)/(.+?)(?:\.git)?$#i', $url, $m ) ) {
			$host = $m[1];
			$path = $m[2];
			return sprintf(
				'https://%s/%s/-/archive/%s/%s-%s.zip',
				$host,
				$path,
				rawurlencode( $branch ),
				rawurlencode( basename( $path ) ),
				rawurlencode( $branch )
			);
		}

		return new \WP_Error(
			'rcs_zip_host',
			__( 'ZIP fallback supports GitHub and GitLab HTTPS URLs. Install git CLI for other remotes.', 'repo-code-snippets' )
		);
	}

	/**
	 * Inject token into HTTPS git URL.
	 *
	 * @param string $url
	 * @param string $token
	 * @return string
	 */
	private function auth_url( $url, $token ) {
		if ( ! $token || 0 !== strpos( $url, 'http' ) ) {
			return $url;
		}
		return preg_replace( '#^https://#', 'https://x-access-token:' . rawurlencode( $token ) . '@', $url );
	}

	/**
	 * Parse snippets folder: each subdir has meta.json + code.{type}.
	 *
	 * @param string $path
	 * @return array
	 */
	public function parse_folder( $path ) {
		$map     = array();
		$entries = scandir( $path );
		if ( false === $entries ) {
			return $map;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$dir = trailingslashit( $path ) . $entry;
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$meta_file = $dir . '/meta.json';
			if ( ! is_readable( $meta_file ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$meta = json_decode( (string) file_get_contents( $meta_file ), true );
			if ( ! is_array( $meta ) ) {
				continue;
			}

			$meta = $this->storage->default_meta( $meta );
			$slug = $this->storage->sanitize_slug( ! empty( $meta['slug'] ) ? $meta['slug'] : $entry );
			$meta['slug'] = $slug;

			$type = in_array( $meta['type'], Storage::TYPES, true ) ? $meta['type'] : 'php';
			$code_file = $dir . '/code.' . $type;
			$code = '';
			if ( is_readable( $code_file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$code = (string) file_get_contents( $code_file );
				if ( 'php' === $type ) {
					$code = $this->storage->normalize_code( 'php', $code );
				}
			}

			$map[ $slug ] = array(
				'meta' => $meta,
				'code' => $code,
			);
		}

		return $map;
	}

	/**
	 * @param string $code
	 * @return string
	 */
	private function normalize_compare( $code ) {
		return str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $code ) );
	}

	/**
	 * @param string $dir
	 */
	private function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->rrmdir( $path );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $path );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $dir );
	}
}
