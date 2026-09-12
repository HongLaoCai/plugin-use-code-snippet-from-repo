<?php
/**
 * Admin menus, assets, and AJAX handlers.
 *
 * @package RepoCodeSnippets
 */

namespace RCS\Admin;

use RCS\Git_Sync;
use RCS\Plugin;
use RCS\Storage;

defined( 'ABSPATH' ) || exit;

class Admin {

	/**
	 * @var Storage
	 */
	private $storage;

	/**
	 * @var Git_Sync
	 */
	private $git;

	/**
	 * @param Storage  $storage
	 * @param Git_Sync $git
	 */
	public function __construct( Storage $storage, Git_Sync $git ) {
		$this->storage = $storage;
		$this->git     = $git;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_rcs_save_snippet', array( $this, 'handle_save_snippet' ) );
		add_action( 'admin_post_rcs_delete_snippet', array( $this, 'handle_delete_snippet' ) );
		add_action( 'admin_post_rcs_toggle_snippet', array( $this, 'handle_toggle_snippet' ) );
		add_action( 'admin_post_rcs_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_rcs_git_diff', array( $this, 'handle_git_diff' ) );
		add_action( 'admin_post_rcs_git_pull', array( $this, 'handle_git_pull' ) );
		add_action( 'admin_notices', array( $this, 'safe_mode_notice' ) );
	}

	/**
	 * Top-level menu.
	 */
	public function menu() {
		add_menu_page(
			__( 'Repo Snippets', 'repo-code-snippets' ),
			__( 'Repo Snippets', 'repo-code-snippets' ),
			'manage_options',
			'rcs-snippets',
			array( $this, 'render_list' ),
			'dashicons-editor-code',
			58
		);

		add_submenu_page(
			'rcs-snippets',
			__( 'All Snippets', 'repo-code-snippets' ),
			__( 'All Snippets', 'repo-code-snippets' ),
			'manage_options',
			'rcs-snippets',
			array( $this, 'render_list' )
		);

		add_submenu_page(
			'rcs-snippets',
			__( 'Add Snippet', 'repo-code-snippets' ),
			__( 'Add New', 'repo-code-snippets' ),
			'manage_options',
			'rcs-snippet-edit',
			array( $this, 'render_edit' )
		);

		add_submenu_page(
			'rcs-snippets',
			__( 'Settings', 'repo-code-snippets' ),
			__( 'Settings', 'repo-code-snippets' ),
			'manage_options',
			'rcs-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * @param string $hook
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'rcs-' ) ) {
			return;
		}

		wp_enqueue_style(
			'rcs-admin',
			RCS_URL . 'assets/admin.css',
			array(),
			RCS_VERSION
		);

		wp_enqueue_script(
			'rcs-admin',
			RCS_URL . 'assets/admin.js',
			array(),
			RCS_VERSION,
			true
		);
	}

	/**
	 * List page.
	 */
	public function render_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$snippets = $this->storage->all();
		$notice   = $this->consume_notice();
		include RCS_PATH . 'includes/admin/views/list.php';
	}

	/**
	 * Edit / create page.
	 */
	public function render_edit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug    = isset( $_GET['slug'] ) ? sanitize_title( wp_unslash( $_GET['slug'] ) ) : '';
		$snippet = $slug ? $this->storage->get( $slug ) : null;
		$meta    = $snippet ? $snippet['meta'] : $this->storage->default_meta(
			array(
				'name'   => '',
				'type'   => 'php',
				'status' => 'inactive',
				'scope'  => 'everywhere',
			)
		);
		$code    = $snippet ? $snippet['code'] : '';
		$notice  = $this->consume_notice();
		$is_new  = ! $snippet;

		include RCS_PATH . 'includes/admin/views/edit.php';
	}

	/**
	 * Settings + Git sync page.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings  = Plugin::get_settings();
		$notice    = $this->consume_notice();
		$diff      = get_transient( 'rcs_last_diff' );
		$has_git   = $this->git->has_git_cli();
		$safe_url  = '';
		if ( ! empty( $settings['safe_mode_key'] ) ) {
			$safe_url = add_query_arg( 'rcs_safe_mode', $settings['safe_mode_key'], admin_url() );
		}
		include RCS_PATH . 'includes/admin/views/settings.php';
	}

	/**
	 * Save snippet form.
	 */
	public function handle_save_snippet() {
		$this->assert_capability();
		check_admin_referer( 'rcs_save_snippet' );

		$old_slug = isset( $_POST['old_slug'] ) ? sanitize_title( wp_unslash( $_POST['old_slug'] ) ) : '';
		$meta     = array(
			'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slug'        => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			'type'        => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'php',
			'status'      => ! empty( $_POST['status'] ) ? 'active' : 'inactive',
			'scope'       => isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( $_POST['scope'] ) ) : 'everywhere',
			'priority'    => isset( $_POST['priority'] ) ? (int) $_POST['priority'] : 10,
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'error'       => '',
		);

		if ( $old_slug ) {
			$existing = $this->storage->get( $old_slug );
			if ( $existing && ! empty( $existing['meta']['created_at'] ) ) {
				$meta['created_at'] = $existing['meta']['created_at'];
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw code by design
		$code = isset( $_POST['code'] ) ? wp_unslash( $_POST['code'] ) : '';

		$result = $this->storage->save( $meta, $code, $old_slug ? $old_slug : null );
		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
			$redirect = add_query_arg(
				array(
					'page' => 'rcs-snippet-edit',
					'slug' => $old_slug,
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		$this->flash( 'success', __( 'Snippet saved.', 'repo-code-snippets' ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'rcs-snippet-edit',
					'slug' => $result['meta']['slug'],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete snippet.
	 */
	public function handle_delete_snippet() {
		$this->assert_capability();
		check_admin_referer( 'rcs_delete_snippet' );
		$slug = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$result = $this->storage->delete( $slug );
		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
		} else {
			$this->flash( 'success', __( 'Snippet deleted.', 'repo-code-snippets' ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rcs-snippets' ) );
		exit;
	}

	/**
	 * Activate / deactivate from list.
	 */
	public function handle_toggle_snippet() {
		$this->assert_capability();
		check_admin_referer( 'rcs_toggle_snippet' );
		$slug   = isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'inactive';
		$result = $this->storage->set_status( $slug, $status );
		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
		} else {
			$this->flash(
				'success',
				'active' === $status
					? __( 'Snippet activated.', 'repo-code-snippets' )
					: __( 'Snippet deactivated.', 'repo-code-snippets' )
			);
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rcs-snippets' ) );
		exit;
	}

	/**
	 * Save Git / safety settings.
	 */
	public function handle_save_settings() {
		$this->assert_capability();
		check_admin_referer( 'rcs_save_settings' );

		$current = Plugin::get_settings();
		$settings = array(
			'git_url'       => isset( $_POST['git_url'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['git_url'] ) ) ) : '',
			'git_folder'    => isset( $_POST['git_folder'] ) ? sanitize_text_field( wp_unslash( $_POST['git_folder'] ) ) : 'snippets',
			'git_branch'    => isset( $_POST['git_branch'] ) ? sanitize_text_field( wp_unslash( $_POST['git_branch'] ) ) : 'main',
			'git_token'     => isset( $_POST['git_token'] ) ? sanitize_text_field( wp_unslash( $_POST['git_token'] ) ) : '',
			'safe_mode_key' => ! empty( $current['safe_mode_key'] ) ? $current['safe_mode_key'] : wp_generate_password( 32, false, false ),
			'auto_disable'  => ! empty( $_POST['auto_disable'] ) ? 1 : 0,
		);

		// Keep previous token if field left blank (masked).
		if ( '' === $settings['git_token'] && ! empty( $current['git_token'] ) && empty( $_POST['git_token_clear'] ) ) {
			$settings['git_token'] = $current['git_token'];
		}

		if ( ! empty( $_POST['regenerate_safe_key'] ) ) {
			$settings['safe_mode_key'] = wp_generate_password( 32, false, false );
		}

		update_option( 'rcs_settings', $settings );
		$this->flash( 'success', __( 'Settings saved.', 'repo-code-snippets' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=rcs-settings' ) );
		exit;
	}

	/**
	 * Run Diff and store result in transient for display.
	 */
	public function handle_git_diff() {
		$this->assert_capability();
		check_admin_referer( 'rcs_git_diff' );

		$result = $this->git->diff();
		if ( is_wp_error( $result ) ) {
			delete_transient( 'rcs_last_diff' );
			$this->flash( 'error', $result->get_error_message() );
		} else {
			set_transient( 'rcs_last_diff', $result, HOUR_IN_SECONDS );
			$this->flash( 'success', $result['summary'] );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rcs-settings#rcs-git' ) );
		exit;
	}

	/**
	 * Pull and replace all local snippets.
	 */
	public function handle_git_pull() {
		$this->assert_capability();
		check_admin_referer( 'rcs_git_pull' );

		$result = $this->git->pull();
		if ( is_wp_error( $result ) ) {
			$this->flash( 'error', $result->get_error_message() );
		} else {
			delete_transient( 'rcs_last_diff' );
			$this->flash( 'success', __( 'Pulled from Git. Local snippets were replaced.', 'repo-code-snippets' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=rcs-settings#rcs-git' ) );
		exit;
	}

	/**
	 * Banner when Safe Mode is on.
	 */
	public function safe_mode_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! Plugin::instance()->is_safe_mode() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Repo Code Snippets Safe Mode is active — no snippets are running.', 'repo-code-snippets' );
		echo '</p></div>';
	}

	private function assert_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'repo-code-snippets' ) );
		}
	}

	/**
	 * @param string $type success|error
	 * @param string $message
	 */
	private function flash( $type, $message ) {
		set_transient(
			'rcs_admin_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 5
		);
	}

	/**
	 * @return array|null
	 */
	private function consume_notice() {
		$key = 'rcs_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );
		return is_array( $notice ) ? $notice : null;
	}
}
