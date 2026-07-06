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
	 * Supported attributes:
	 * - direction: which way the panel opens relative to the toggle button.
	 *   Accepts 'auto' (default), 'up', or 'down'. With 'auto' the frontend
	 *   script picks the direction with the most viewport space.
	 * - align: which edge of the toggle button the panel aligns to.
	 *   Accepts 'auto' (default), 'left' (panel extends right), or 'right'
	 *   (panel extends left).
	 *
	 * @since    1.2.75
	 * @since    1.3.02    Added `direction` and `align` attributes.
	 * @param    array|string    $atts    Shortcode attributes.
	 * @return   string    The shortcode HTML or empty string if widget is disabled.
	 */
	public static function render( $atts = array() ) {
		// Prevent multiple widget instances on the same page
		if ( self::$shortcode_rendered ) {
			return '';
		}

		$options = get_option( 'open_accessibility_options', array() );

		if ( ! empty( $options['disable_widget'] ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'direction' => 'auto',
				'align'     => 'auto',
			),
			$atts,
			'open_accessibility'
		);

		$direction = strtolower( trim( (string) $atts['direction'] ) );
		$align     = strtolower( trim( (string) $atts['align'] ) );

		if ( ! in_array( $direction, array( 'auto', 'up', 'down' ), true ) ) {
			$direction = 'auto';
		}

		if ( ! in_array( $align, array( 'auto', 'left', 'right' ), true ) ) {
			$align = 'auto';
		}

		self::$shortcode_rendered = true;

		ob_start();
		include OPEN_ACCESSIBILITY_PLUGIN_DIR . 'public/partials/widget-template.php';
		$html = ob_get_clean();

		// Explicit values also get a class so the placement works without JS;
		// 'auto' is resolved by the frontend script when the panel opens.
		$classes = array( 'open-accessibility-shortcode' );

		if ( 'auto' !== $direction ) {
			$classes[] = 'oa-direction-' . $direction;
		}

		if ( 'auto' !== $align ) {
			$classes[] = 'oa-align-' . $align;
		}

		return '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"'
			. ' data-oa-direction="' . esc_attr( $direction ) . '"'
			. ' data-oa-align="' . esc_attr( $align ) . '">'
			. $html . '</div>';
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
