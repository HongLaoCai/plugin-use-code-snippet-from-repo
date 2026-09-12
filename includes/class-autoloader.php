<?php
/**
 * Simple PSR-4-ish autoloader for the RCS namespace.
 *
 * @package RepoCodeSnippets
 */

namespace RCS;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	/**
	 * Register spl_autoload.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative = substr( $class, strlen( __NAMESPACE__ ) + 1 );
		$relative = strtolower( str_replace( '_', '-', $relative ) );
		$parts    = explode( '\\', $relative );
		$class_file = 'class-' . array_pop( $parts ) . '.php';
		$path       = RCS_PATH . 'includes/';

		if ( ! empty( $parts ) ) {
			$path .= implode( '/', $parts ) . '/';
		}

		$file = $path . $class_file;

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
