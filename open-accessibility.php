<?php
/**
 * Plugin Name: Open Accessibility
 * Plugin URI: https://github.com/adamgreenwell/open-accessibility
 * Description: An open-source accessibility solution for WordPress websites, providing tools to improve usability and ensure WCAG compliance.
 * Version: 1.4.01
 * Requires at least: 5.2
 * Requires PHP: 7.4
 * Author: Adam Greenwell
 * Author URI: https://adamgreenwell.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: open-accessibility
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OPEN_ACCESSIBILITY_VERSION', '1.4.01' );
define( 'OPEN_ACCESSIBILITY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPEN_ACCESSIBILITY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OPEN_ACCESSIBILITY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'OPEN_ACCESSIBILITY_ASSETS_DIR', OPEN_ACCESSIBILITY_PLUGIN_DIR . 'assets/' );
define( 'OPEN_ACCESSIBILITY_ASSETS_URL', OPEN_ACCESSIBILITY_PLUGIN_URL . 'assets/' );

/**
 * The core plugin class.
 */
require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility.php';

/**
 * Set default options if none exist.
 *
 * The defaults are not repeated here. Open_Accessibility_Utils::get_default_options()
 * is the single source of truth, so a key can never be seeded without being
 * declared, or declared without being seeded.
 *
 * @since    1.0.0
 */
function open_accessibility_activate() {
	require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-utils.php';

	if ( ! get_option( 'open_accessibility_options' ) ) {
		update_option( 'open_accessibility_options', Open_Accessibility_Utils::get_default_options() );
	}

	require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/database/class-open-accessibility-db.php';
	Open_Accessibility_DB::create_tables();
}
register_activation_hook(__FILE__, 'open_accessibility_activate');

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function run_open_accessibility() {
	$plugin = new Open_Accessibility();
	$plugin->run();
}

run_open_accessibility();
