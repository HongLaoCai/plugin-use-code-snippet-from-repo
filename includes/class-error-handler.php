<?php
/**
 * Fatal-error watcher: auto-deactivates the offending snippet.
 *
 * @package RepoCodeSnippets
 */

namespace RCS;

defined( 'ABSPATH' ) || exit;

class Error_Handler {

	/**
	 * @var Storage
	 */
	private $storage;

	/**
	 * Stack of currently executing snippet slugs.
	 *
	 * @var string[]
	 */
	private $stack = array();

	/**
	 * @param Storage $storage
	 */
	public function __construct( Storage $storage ) {
		$this->storage = $storage;
	}

	/**
	 * Hook shutdown handler.
	 */
	public function register() {
		register_shutdown_function( array( $this, 'on_shutdown' ) );
	}

	/**
	 * @param string $slug
	 */
	public function push( $slug ) {
		$this->stack[] = $slug;
	}

	/**
	 * Pop last slug.
	 */
	public function pop() {
		array_pop( $this->stack );
	}

	/**
	 * @return string|null
	 */
	public function current() {
		if ( empty( $this->stack ) ) {
			return null;
		}
		return end( $this->stack );
	}

	/**
	 * Deactivate snippet that was mid-execution when a fatal occurred.
	 */
	public function on_shutdown() {
		$settings = Plugin::get_settings();
		if ( empty( $settings['auto_disable'] ) ) {
			return;
		}

		$error = error_get_last();
		if ( ! $error ) {
			return;
		}

		$fatal_types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
			return;
		}

		$slug = $this->current();
		if ( ! $slug ) {
			// Fallback: blame by file path under snippets dir.
			$slug = $this->slug_from_file( isset( $error['file'] ) ? $error['file'] : '' );
		}

		if ( ! $slug ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: error message, 2: file, 3: line */
			__( '%1$s in %2$s on line %3$d', 'repo-code-snippets' ),
			$error['message'],
			isset( $error['file'] ) ? $error['file'] : '',
			isset( $error['line'] ) ? (int) $error['line'] : 0
		);

		$this->storage->deactivate_with_error( $slug, $message );

		if ( function_exists( 'error_log' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Repo Code Snippets] Auto-deactivated "' . $slug . '": ' . $message );
		}
	}

	/**
	 * @param string $file
	 * @return string|null
	 */
	private function slug_from_file( $file ) {
		if ( ! $file ) {
			return null;
		}
		$base = $this->storage->snippets_dir();
		$real_base = realpath( $base );
		$real_file = realpath( $file );
		if ( ! $real_base || ! $real_file ) {
			return null;
		}
		if ( 0 !== strpos( $real_file, $real_base ) ) {
			return null;
		}
		$rel = ltrim( substr( $real_file, strlen( $real_base ) ), DIRECTORY_SEPARATOR );
		$parts = explode( DIRECTORY_SEPARATOR, $rel );
		return isset( $parts[0] ) ? $parts[0] : null;
	}
}
