<?php
/**
 * Plugin Name: Repo Code Snippets
 * Plugin URI:  https://github.com/hh/plugin-use-code-snippet-from-repo
 * Description: Manage PHP, JS, CSS, and HTML snippets as files, with crash auto-disable and one-way Git sync (Diff / Pull).
 * Version:     1.0.0
 * Author:      Repo Code Snippets
 * License:     GPL-2.0-or-later
 * Text Domain: repo-code-snippets
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'RCS_VERSION', '1.0.0' );
define( 'RCS_FILE', __FILE__ );
define( 'RCS_PATH', plugin_dir_path( __FILE__ ) );
define( 'RCS_URL', plugin_dir_url( __FILE__ ) );
define( 'RCS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Optional kill-switch: define( 'REPO_CODE_SNIPPETS_SAFE_MODE', true ); in wp-config.php
 */
if ( ! defined( 'REPO_CODE_SNIPPETS_SAFE_MODE' ) ) {
	define( 'REPO_CODE_SNIPPETS_SAFE_MODE', false );
}

require_once RCS_PATH . 'includes/class-autoloader.php';
RCS\Autoloader::register();

register_activation_hook( __FILE__, array( 'RCS\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RCS\\Plugin', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		RCS\Plugin::instance()->boot();
	}
);
