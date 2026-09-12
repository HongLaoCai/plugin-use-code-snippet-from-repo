<?php
/**
 * Main plugin bootstrap.
 *
 * @package RepoCodeSnippets
 */

namespace RCS;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var Storage
	 */
	public $storage;

	/**
	 * @var Runner
	 */
	public $runner;

	/**
	 * @var Error_Handler
	 */
	public $error_handler;

	/**
	 * @var Git_Sync
	 */
	public $git;

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Activation: ensure storage dirs exist.
	 */
	public static function activate() {
		$storage = new Storage();
		$storage->ensure_directories();

		if ( ! get_option( 'rcs_settings' ) ) {
			update_option(
				'rcs_settings',
				array(
					'git_url'        => '',
					'git_folder'     => 'snippets',
					'git_branch'     => 'main',
					'git_token'      => '',
					'safe_mode_key'  => wp_generate_password( 32, false, false ),
					'auto_disable'   => 1,
				)
			);
		}
	}

	/**
	 * Deactivation hook (snippets stay on disk).
	 */
	public static function deactivate() {
		// Intentionally empty — files remain for recovery.
	}

	/**
	 * Boot services.
	 */
	public function boot() {
		$this->storage       = new Storage();
		$this->error_handler = new Error_Handler( $this->storage );
		$this->runner        = new Runner( $this->storage, $this->error_handler );
		$this->git           = new Git_Sync( $this->storage );

		$this->storage->ensure_directories();
		$this->error_handler->register();

		if ( ! $this->is_safe_mode() ) {
			$this->runner->run_active_snippets();
		}

		if ( is_admin() ) {
			$admin = new Admin\Admin( $this->storage, $this->git );
			$admin->register();
		}
	}

	/**
	 * Whether all snippet execution is suppressed.
	 *
	 * @return bool
	 */
	public function is_safe_mode() {
		if ( REPO_CODE_SNIPPETS_SAFE_MODE ) {
			return true;
		}

		$settings = get_option( 'rcs_settings', array() );
		$key      = isset( $settings['safe_mode_key'] ) ? $settings['safe_mode_key'] : '';

		if ( empty( $key ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['rcs_safe_mode'] ) && hash_equals( $key, (string) wp_unslash( $_GET['rcs_safe_mode'] ) ) ) {
			if ( ! headers_sent() ) {
				setcookie( 'rcs_safe_mode', $key, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
			}
			return true;
		}

		if ( isset( $_COOKIE['rcs_safe_mode'] ) && hash_equals( $key, (string) wp_unslash( $_COOKIE['rcs_safe_mode'] ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'git_url'       => '',
			'git_folder'    => 'snippets',
			'git_branch'    => 'main',
			'git_token'     => '',
			'safe_mode_key' => '',
			'auto_disable'  => 1,
		);
		$settings = get_option( 'rcs_settings', array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
	}
}
