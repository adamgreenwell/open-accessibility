<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The shortcode class for Open Accessibility.
 *
 * Handles the [open_accessibility] shortcode registration and rendering.
 *
 * @since      1.2.75
 * @package    Open_Accessibility
 */

class Open_Accessibility_Shortcode {

	/**
	 * Whether the shortcode has been rendered on the current page.
	 *
	 * @since    1.2.75
	 * @access   private
	 * @var      bool    $shortcode_rendered    True if the shortcode has been rendered.
	 */
	private static $shortcode_rendered = false;

	/**
	 * Initialize the shortcode by registering it with WordPress.
	 *
	 * @since    1.2.75
	 */
	public static function init() {
		add_shortcode( 'open_accessibility', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode output.
	 *
	 * @since    1.2.75
	 * @return   string    The shortcode HTML or empty string if widget is disabled.
	 */
	public static function render() {
		// Prevent multiple widget instances on the same page
		if ( self::$shortcode_rendered ) {
			return '';
		}

		$options = get_option( 'open_accessibility_options', array() );

		if ( ! empty( $options['disable_widget'] ) ) {
			return '';
		}

		self::$shortcode_rendered = true;

		ob_start();
		include OPEN_ACCESSIBILITY_PLUGIN_DIR . 'public/partials/widget-template.php';
		$html = ob_get_clean();

		return '<div class="open-accessibility-shortcode">' . $html . '</div>';
	}

	/**
	 * Check whether the shortcode has been rendered on the current page.
	 *
	 * @since    1.2.75
	 * @return   bool    True if the shortcode was rendered.
	 */
	public static function was_rendered() {
		return self::$shortcode_rendered;
	}
}
