<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The widget class for Open Accessibility.
 *
 * Handles the functionality of the accessibility widget.
 *
 * @since      1.0.0
 * @package    Open_Accessibility
 */

class Open_Accessibility_Widget {

	/**
	 * Widget options
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $options    The options for this widget.
	 */
	private $options;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    array    $options    Widget options.
	 */
	public function __construct($options = array()) {
		$this->options = $options;
	}

	/**
	 * Render the widget
	 *
	 * @since    1.0.0
	 * @return   string    The widget HTML.
	 */
	public function render() {
		ob_start();
		include_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'public/partials/widget-template.php';
		return ob_get_clean();
	}

	/**
	 * Get the widget options
	 *
	 * @since    1.0.0
	 * @return   array    The widget options.
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Set a widget option
	 *
	 * @since    1.0.0
	 * @param    string    $key      Option key.
	 * @param    mixed     $value    Option value.
	 */
	public function set_option($key, $value) {
		$this->options[$key] = $value;
	}

	/**
	 * Get a widget option
	 *
	 * @since    1.0.0
	 * @param    string    $key       Option key.
	 * @param    mixed     $default   Default value if option not found.
	 * @return   mixed     Option value or default if not found.
	 */
	public function get_option($key, $default = false) {
		return isset($this->options[$key]) ? $this->options[$key] : $default;
	}
}