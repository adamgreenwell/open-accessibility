<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Open_Accessibility
 */

class Open_Accessibility_i18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * Note: Since WordPress 4.6+, translations are automatically loaded for plugins
	 * hosted on WordPress.org. This method is kept for backward compatibility with
	 * older WordPress versions.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'open-accessibility',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}