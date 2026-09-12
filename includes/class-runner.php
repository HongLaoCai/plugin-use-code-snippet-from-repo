<?php
/**
 * Execute / enqueue active snippets by scope.
 *
 * CSS/JS/HTML are printed inline so snippet files can stay non-public
 * (storage dir uses Deny from all).
 *
 * @package RepoCodeSnippets
 */

namespace RCS;

defined( 'ABSPATH' ) || exit;

class Runner {

	/**
	 * @var Storage
	 */
	private $storage;

	/**
	 * @var Error_Handler
	 */
	private $errors;

	/**
	 * @param Storage       $storage
	 * @param Error_Handler $errors
	 */
	public function __construct( Storage $storage, Error_Handler $errors ) {
		$this->storage = $storage;
		$this->errors  = $errors;
	}

	/**
	 * Kick off execution for the current request context.
	 */
	public function run_active_snippets() {
		$is_admin = is_admin();

		foreach ( $this->storage->all() as $snippet ) {
			$meta = $snippet['meta'];
			if ( 'active' !== $meta['status'] ) {
				continue;
			}

			if ( ! $this->scope_matches( $meta['scope'], $is_admin ) ) {
				continue;
			}

			$type = $meta['type'];
			if ( 'php' === $type ) {
				$this->run_php( $snippet );
			} elseif ( 'css' === $type ) {
				$this->print_css( $snippet, $is_admin );
			} elseif ( 'js' === $type ) {
				$this->print_js( $snippet, $is_admin );
			} elseif ( 'html' === $type ) {
				$this->print_html( $snippet, $is_admin );
			}
		}
	}

	/**
	 * @param string $scope
	 * @param bool   $is_admin
	 * @return bool
	 */
	private function scope_matches( $scope, $is_admin ) {
		if ( 'everywhere' === $scope ) {
			return true;
		}
		if ( 'admin' === $scope ) {
			return $is_admin;
		}
		if ( 'frontend' === $scope ) {
			return ! $is_admin;
		}
		return false;
	}

	/**
	 * @param array $snippet
	 */
	private function run_php( array $snippet ) {
		$slug = $snippet['meta']['slug'];
		$file = $snippet['code_file'];
		if ( ! is_readable( $file ) ) {
			return;
		}

		$this->errors->push( $slug );
		try {
			include $file;
		} catch ( \Throwable $e ) {
			$this->storage->deactivate_with_error(
				$slug,
				sprintf(
					'%s in %s on line %d',
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				)
			);
		}
		$this->errors->pop();
	}

	/**
	 * @param array $snippet
	 * @param bool  $is_admin
	 */
	private function print_css( array $snippet, $is_admin ) {
		$hook = $is_admin ? 'admin_head' : 'wp_head';
		$code = $snippet['code'];
		$prio = (int) $snippet['meta']['priority'];
		$slug = $snippet['meta']['slug'];

		add_action(
			$hook,
			static function () use ( $code, $slug ) {
				echo "\n<style id=\"rcs-css-" . esc_attr( $slug ) . "\">\n";
				// Trusted admin-authored CSS.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $code;
				echo "\n</style>\n";
			},
			$prio
		);
	}

	/**
	 * @param array $snippet
	 * @param bool  $is_admin
	 */
	private function print_js( array $snippet, $is_admin ) {
		$hook = $is_admin ? 'admin_footer' : 'wp_footer';
		$code = $snippet['code'];
		$prio = (int) $snippet['meta']['priority'];
		$slug = $snippet['meta']['slug'];

		add_action(
			$hook,
			static function () use ( $code, $slug ) {
				echo "\n<script id=\"rcs-js-" . esc_attr( $slug ) . "\">\n";
				// Trusted admin-authored JS.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $code;
				echo "\n</script>\n";
			},
			$prio
		);
	}

	/**
	 * @param array $snippet
	 * @param bool  $is_admin
	 */
	private function print_html( array $snippet, $is_admin ) {
		$hook   = $is_admin ? 'admin_footer' : 'wp_footer';
		$code   = $snippet['code'];
		$prio   = (int) $snippet['meta']['priority'];
		$slug   = $snippet['meta']['slug'];
		$errors = $this->errors;

		add_action(
			$hook,
			function () use ( $code, $slug, $errors ) {
				$errors->push( $slug );
				// Trusted admin-authored HTML.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $code;
				$errors->pop();
			},
			$prio
		);
	}
}
