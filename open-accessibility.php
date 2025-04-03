<?php
/**
 * Plugin Name: Open Accessibility
 * Plugin URI: https://github.com/adamgreenwell/open-accessibility
 * Description: An open-source accessibility solution for WordPress websites, providing tools to improve usability and ensure WCAG compliance.
 * Version: 1.1.3
 * Requires at least: 4.1
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

define( 'OPEN_ACCESSIBILITY_VERSION', '1.1.3' );
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
 */
function open_accessibility_activate() {
	if (!get_option('open_accessibility_options')) {
		$default_options = array(
			'icon' => 'accessibility',
			'icon_size' => 'medium',
			'icon_color' => '#ffffff',
			'bg_color' => '#4054b2',
			'position' => 'left',
			'enable_skip_to_content' => 1,
			'enable_contrast' => 1,
			'enable_grayscale' => 1,
			'enable_text_size' => 1,
			'enable_readable_font' => 1,
			'enable_links_underline' => 1,
			'enable_hide_images' => 1,
			'enable_reading_guide' => 1,
			'enable_focus_outline' => 1,
			'enable_line_height' => 1,
			'enable_text_align' => 1,
			'enable_sitemap' => 1,
			'enable_animations_pause' => 1,
			'disable_widget' => 0,
			'hide_on_mobile' => 0,
			'hide_on_desktop' => 0,
			'skip_to_element_id' => 'content',
		);
		update_option('open_accessibility_options', $default_options);
	}
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