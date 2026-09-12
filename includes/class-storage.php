<?php
/**
 * File-based snippet storage.
 *
 * Layout (wp-content/repo-code-snippets/snippets/{slug}/):
 *   meta.json  — name, type, status, scope, priority, description, error
 *   code.php|css|js|html
 *
 * @package RepoCodeSnippets
 */

namespace RCS;

defined( 'ABSPATH' ) || exit;

class Storage {

	const TYPES  = array( 'php', 'css', 'js', 'html' );
	const SCOPES = array( 'everywhere', 'frontend', 'admin' );

	/**
	 * Root storage directory under wp-content.
	 *
	 * @return string
	 */
	public function root_dir() {
		return trailingslashit( WP_CONTENT_DIR ) . 'repo-code-snippets';
	}

	/**
	 * Directory that holds snippet folders.
	 *
	 * @return string
	 */
	public function snippets_dir() {
		return $this->root_dir() . '/snippets';
	}

	/**
	 * Git clone cache directory.
	 *
	 * @return string
	 */
	public function git_cache_dir() {
		return $this->root_dir() . '/.git-cache';
	}

	/**
	 * Create storage directories + protect with index.php / .htaccess.
	 */
	public function ensure_directories() {
		$dirs = array(
			$this->root_dir(),
			$this->snippets_dir(),
			$this->git_cache_dir(),
		);

		foreach ( $dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			$this->protect_directory( $dir );
		}
	}

	/**
	 * @param string $dir Absolute path.
	 */
	private function protect_directory( $dir ) {
		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n" );
		}
	}

	/**
	 * @param string $slug
	 * @return string
	 */
	public function snippet_dir( $slug ) {
		return $this->snippets_dir() . '/' . $this->sanitize_slug( $slug );
	}

	/**
	 * @param string $slug
	 * @return string
	 */
	public function sanitize_slug( $slug ) {
		$slug = sanitize_title( $slug );
		return $slug ? $slug : 'snippet-' . wp_generate_password( 6, false, false );
	}

	/**
	 * Default meta for a new snippet.
	 *
	 * @param array $overrides
	 * @return array
	 */
	public function default_meta( array $overrides = array() ) {
		$defaults = array(
			'name'        => '',
			'slug'        => '',
			'type'        => 'php',
			'status'      => 'inactive',
			'scope'       => 'everywhere',
			'priority'    => 10,
			'description' => '',
			'error'       => '',
			'updated_at'  => gmdate( 'c' ),
			'created_at'  => gmdate( 'c' ),
		);
		return array_merge( $defaults, $overrides );
	}

	/**
	 * List all snippets (meta + code).
	 *
	 * @return array[]
	 */
	public function all() {
		$dir = $this->snippets_dir();
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$items = array();
		$entries = scandir( $dir );
		if ( false === $entries ) {
			return array();
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry || 'index.php' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( ! is_dir( $path ) ) {
				continue;
			}
			$snippet = $this->get( $entry );
			if ( $snippet ) {
				$items[] = $snippet;
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				$pa = (int) $a['meta']['priority'];
				$pb = (int) $b['meta']['priority'];
				if ( $pa === $pb ) {
					return strcasecmp( $a['meta']['name'], $b['meta']['name'] );
				}
				return $pa <=> $pb;
			}
		);

		return $items;
	}

	/**
	 * @param string $slug
	 * @return array|null { meta, code, path }
	 */
	public function get( $slug ) {
		$slug = $this->sanitize_slug( $slug );
		$dir  = $this->snippet_dir( $slug );
		$meta_file = $dir . '/meta.json';

		if ( ! is_readable( $meta_file ) ) {
			return null;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = file_get_contents( $meta_file );
		$meta = json_decode( $raw, true );
		if ( ! is_array( $meta ) ) {
			return null;
		}

		$meta = $this->default_meta( $meta );
		$meta['slug'] = $slug;

		$type = in_array( $meta['type'], self::TYPES, true ) ? $meta['type'] : 'php';
		$code_file = $dir . '/code.' . $type;
		$code = '';
		if ( is_readable( $code_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$code = (string) file_get_contents( $code_file );
			if ( 'php' === $type ) {
				$code = $this->normalize_code( 'php', $code );
			}
		}

		return array(
			'meta' => $meta,
			'code' => $code,
			'path' => $dir,
			'code_file' => $code_file,
		);
	}

	/**
	 * Save snippet to disk.
	 *
	 * @param array  $meta
	 * @param string $code
	 * @param string|null $old_slug When renaming.
	 * @return array|\WP_Error
	 */
	public function save( array $meta, $code, $old_slug = null ) {
		$meta = $this->default_meta( $meta );

		if ( empty( $meta['name'] ) ) {
			return new \WP_Error( 'rcs_name', __( 'Snippet name is required.', 'repo-code-snippets' ) );
		}

		if ( ! in_array( $meta['type'], self::TYPES, true ) ) {
			return new \WP_Error( 'rcs_type', __( 'Invalid snippet type.', 'repo-code-snippets' ) );
		}

		if ( ! in_array( $meta['scope'], self::SCOPES, true ) ) {
			$meta['scope'] = 'everywhere';
		}

		if ( ! in_array( $meta['status'], array( 'active', 'inactive' ), true ) ) {
			$meta['status'] = 'inactive';
		}

		$slug = ! empty( $meta['slug'] ) ? $meta['slug'] : $meta['name'];
		$slug = $this->sanitize_slug( $slug );
		$meta['slug'] = $slug;
		$meta['updated_at'] = gmdate( 'c' );
		if ( empty( $meta['created_at'] ) ) {
			$meta['created_at'] = gmdate( 'c' );
		}

		// Rename: move old folder if slug changed.
		if ( $old_slug && $this->sanitize_slug( $old_slug ) !== $slug ) {
			$old_dir = $this->snippet_dir( $old_slug );
			$new_dir = $this->snippet_dir( $slug );
			if ( is_dir( $old_dir ) && ! is_dir( $new_dir ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				rename( $old_dir, $new_dir );
			} elseif ( is_dir( $new_dir ) && $this->sanitize_slug( $old_slug ) !== $slug ) {
				return new \WP_Error( 'rcs_exists', __( 'A snippet with this slug already exists.', 'repo-code-snippets' ) );
			}
		}

		$dir = $this->snippet_dir( $slug );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		// Remove stale code.* files when type changes.
		foreach ( self::TYPES as $type ) {
			$file = $dir . '/code.' . $type;
			if ( $type !== $meta['type'] && file_exists( $file ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				unlink( $file );
			}
		}

		$code_file = $dir . '/code.' . $meta['type'];
		$meta_file = $dir . '/meta.json';

		$code_body = $this->normalize_code( $meta['type'], $code );

		if ( 'php' === $meta['type'] && 'active' === $meta['status'] ) {
			$check = $this->validate_php_syntax( $code_body );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}

		// PHP files always start with an opening tag so include() executes them.
		$code_to_write = ( 'php' === $meta['type'] ) ? "<?php\n" . $code_body : $code_body;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$ok_code = false !== file_put_contents( $code_file, $code_to_write );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$ok_meta = false !== file_put_contents(
			$meta_file,
			wp_json_encode( $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		if ( ! $ok_code || ! $ok_meta ) {
			return new \WP_Error( 'rcs_write', __( 'Could not write snippet files.', 'repo-code-snippets' ) );
		}

		return $this->get( $slug );
	}

	/**
	 * Strip opening PHP tag; keep raw CSS/JS/HTML.
	 *
	 * @param string $type
	 * @param string $code
	 * @return string
	 */
	public function normalize_code( $type, $code ) {
		$code = (string) $code;
		if ( 'php' === $type ) {
			$code = preg_replace( '/^\s*<\?php\s*/i', '', $code );
		}
		return $code;
	}

	/**
	 * PHP syntax check without relying on php-fpm / PHP_BINARY.
	 *
	 * Uses token_get_all( …, TOKEN_PARSE ) in-process. Falls back to
	 * `php -l` only when a real CLI binary is found (never php-fpm).
	 *
	 * @param string $code Without opening tag.
	 * @return true|\WP_Error
	 */
	public function validate_php_syntax( $code ) {
		$source = "<?php\n" . $code;

		if ( defined( 'TOKEN_PARSE' ) ) {
			try {
				// TOKEN_PARSE throws ParseError on invalid syntax (PHP 7+).
				token_get_all( $source, TOKEN_PARSE );
				return true;
			} catch ( \ParseError $e ) {
				return new \WP_Error(
					'rcs_syntax',
					sprintf(
						/* translators: 1: message, 2: line */
						__( 'PHP syntax error: %1$s on line %2$d', 'repo-code-snippets' ),
						$e->getMessage(),
						$e->getLine()
					)
				);
			} catch ( \Throwable $e ) {
				return new \WP_Error( 'rcs_syntax', $e->getMessage() );
			}
		}

		// Older PHP / no TOKEN_PARSE: optional CLI lint.
		$php = $this->find_php_cli();
		if ( ! $php ) {
			return true;
		}

		$tmp = wp_tempnam( 'rcs-snippet' );
		if ( ! $tmp ) {
			return true;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $tmp, $source );

		$output   = array();
		$code_ret = 0;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		exec( escapeshellarg( $php ) . ' -l ' . escapeshellarg( $tmp ) . ' 2>&1', $output, $code_ret );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		unlink( $tmp );

		if ( 0 !== (int) $code_ret ) {
			$message = implode( "\n", $output );
			return new \WP_Error( 'rcs_syntax', $message ? $message : __( 'PHP syntax error.', 'repo-code-snippets' ) );
		}

		return true;
	}

	/**
	 * Locate a PHP CLI binary suitable for `php -l` (not php-fpm).
	 *
	 * @return string|null
	 */
	private function find_php_cli() {
		$candidates = array();

		if ( defined( 'PHP_BINARY' ) && PHP_BINARY ) {
			$candidates[] = PHP_BINARY;
		}

		$candidates = array_merge(
			$candidates,
			array(
				'/usr/bin/php',
				'/usr/local/bin/php',
				'/opt/homebrew/bin/php',
			)
		);

		foreach ( $candidates as $bin ) {
			if ( ! $bin || ! is_executable( $bin ) ) {
				continue;
			}
			$base = strtolower( basename( $bin ) );
			// Local/dev and many hosts set PHP_BINARY to php-fpm — unusable for -l.
			if ( false !== strpos( $base, 'php-fpm' ) || false !== strpos( $base, 'fpm' ) ) {
				continue;
			}
			return $bin;
		}

		return null;
	}

	/**
	 * @param string $slug
	 * @return bool|\WP_Error
	 */
	public function delete( $slug ) {
		$dir = $this->snippet_dir( $slug );
		if ( ! is_dir( $dir ) ) {
			return new \WP_Error( 'rcs_missing', __( 'Snippet not found.', 'repo-code-snippets' ) );
		}
		return $this->rrmdir( $dir );
	}

	/**
	 * Toggle active/inactive.
	 *
	 * @param string $slug
	 * @param string $status active|inactive
	 * @return array|\WP_Error
	 */
	public function set_status( $slug, $status ) {
		$snippet = $this->get( $slug );
		if ( ! $snippet ) {
			return new \WP_Error( 'rcs_missing', __( 'Snippet not found.', 'repo-code-snippets' ) );
		}

		$meta = $snippet['meta'];
		$meta['status'] = ( 'active' === $status ) ? 'active' : 'inactive';
		if ( 'active' === $meta['status'] ) {
			$meta['error'] = '';
		}

		return $this->save( $meta, $snippet['code'] );
	}

	/**
	 * Record crash and deactivate.
	 *
	 * @param string $slug
	 * @param string $error_message
	 * @return void
	 */
	public function deactivate_with_error( $slug, $error_message ) {
		$snippet = $this->get( $slug );
		if ( ! $snippet ) {
			return;
		}

		$meta           = $snippet['meta'];
		$meta['status'] = 'inactive';
		$meta['error']  = $error_message;
		$this->save( $meta, $snippet['code'] );
	}

	/**
	 * Replace entire snippets tree from an associative map.
	 * Used by Git Pull.
	 *
	 * @param array $incoming List of { meta, code }
	 * @return true|\WP_Error
	 */
	public function replace_all( array $incoming ) {
		$existing = $this->all();
		foreach ( $existing as $item ) {
			$this->delete( $item['meta']['slug'] );
		}

		foreach ( $incoming as $item ) {
			$meta = isset( $item['meta'] ) ? $item['meta'] : array();
			$code = isset( $item['code'] ) ? $item['code'] : '';
			$result = $this->save( $meta, $code );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Export current snippets as portable map (for Diff / Git folder format).
	 *
	 * @return array
	 */
	public function export_map() {
		$map = array();
		foreach ( $this->all() as $item ) {
			$slug = $item['meta']['slug'];
			$map[ $slug ] = array(
				'meta' => $item['meta'],
				'code' => $item['code'],
			);
		}
		return $map;
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir
	 * @return bool
	 */
	private function rrmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}
		$items = scandir( $dir );
		if ( false === $items ) {
			return false;
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
		return rmdir( $dir );
	}
}
