<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the public-facing side.
 *
 * @package    Open_Accessibility
 */

class Open_Accessibility_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Plugin options
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $options    The options for this plugin.
	 */
	private $options;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'open-accessibility';
		$this->version = OPEN_ACCESSIBILITY_VERSION;
		$this->options = get_option('open_accessibility_options', array());
	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		// Debug paths
		error_log('Loading CSS from: ' . OPEN_ACCESSIBILITY_ASSETS_URL . 'css/open-accessibility-public.css');

		wp_enqueue_style(
			$this->plugin_name,
			OPEN_ACCESSIBILITY_ASSETS_URL . 'css/open-accessibility-public.css',
			array(),
			$this->version,
			'all'
		);

		// Skip to content link styles
		if ($this->get_option('enable_skip_to_content', true)) {
			wp_enqueue_style(
				$this->plugin_name . '-skip-link',
				OPEN_ACCESSIBILITY_ASSETS_URL . 'css/skip-link.css',
				array(),
				$this->version,
				'all'
			);
		}
	}


	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		// Debug paths
		error_log('Loading JS from: ' . OPEN_ACCESSIBILITY_ASSETS_URL . 'js/open-accessibility-public.js');

		wp_enqueue_script(
			$this->plugin_name,
			OPEN_ACCESSIBILITY_ASSETS_URL . 'js/open-accessibility-public.js',
			array('jquery'),
			$this->version,
			true
		);

		// Localize the script with necessary data
		wp_localize_script(
			$this->plugin_name,
			'open_accessibility_data',
			array(
				'ajaxurl' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('open_accessibility_nonce'),
				'options' => $this->get_frontend_options(),
				'i18n' => $this->get_strings()
			)
		);
	}

	/**
	 * Get options for frontend use
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_frontend_options() {
		return array(
			'icon' => $this->get_option('icon', 'accessibility'),
			'position' => $this->get_option('position', 'left'),
			'icon_size' => $this->get_option('icon_size', 'medium'),
			'icon_color' => $this->get_option('icon_color', '#ffffff'),
			'bg_color' => $this->get_option('bg_color', '#4054b2'),
			'statement_url' => $this->get_option('statement_url', ''),
			'sitemap_url' => $this->get_option('sitemap_url', ''),
			'enable_contrast' => $this->get_option('enable_contrast', true),
			'enable_grayscale' => $this->get_option('enable_grayscale', true),
			'enable_text_size' => $this->get_option('enable_text_size', true),
			'enable_readable_font' => $this->get_option('enable_readable_font', true),
			'enable_links_underline' => $this->get_option('enable_links_underline', true),
			'enable_hide_images' => $this->get_option('enable_hide_images', true),
			'enable_reading_guide' => $this->get_option('enable_reading_guide', true),
			'enable_focus_outline' => $this->get_option('enable_focus_outline', true),
			'enable_line_height' => $this->get_option('enable_line_height', true),
			'enable_text_align' => $this->get_option('enable_text_align', true),
			'enable_sitemap' => $this->get_option('enable_sitemap', true),
			'enable_animations_pause' => $this->get_option('enable_animations_pause', true),
			'hide_on_mobile' => $this->get_option('hide_on_mobile', false),
			'hide_on_desktop' => $this->get_option('hide_on_desktop', false),
		);
	}

	/**
	 * Get option value
	 *
	 * @since 1.0.0
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	private function get_option($key, $default = false) {
		return isset($this->options[$key]) ? $this->options[$key] : $default;
	}

	/**
	 * Get translatable strings for frontend
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_strings() {
		return array(
			'widget_title' => __('Accessibility Options', 'open-accessibility'),
			'reset_title' => __('Reset Settings', 'open-accessibility'),
			'reset_text' => __('Reset', 'open-accessibility'),
			'keyboard_nav_title' => __('Keyboard Navigation', 'open-accessibility'),
			'keyboard_nav_text' => __('Use Alt+', 'open-accessibility'),
			'contrast_title' => __('Contrast', 'open-accessibility'),
			'contrast_modes' => array(
				'high' => __('High Contrast', 'open-accessibility'),
				'negative' => __('Negative Contrast', 'open-accessibility'),
				'light' => __('Light Background', 'open-accessibility'),
				'dark' => __('Dark Background', 'open-accessibility')
			),
			'grayscale_title' => __('Grayscale', 'open-accessibility'),
			'grayscale_text' => __('Grayscale', 'open-accessibility'),
			'text_size_title' => __('Text Size', 'open-accessibility'),
			'text_size_increase' => __('Increase Text', 'open-accessibility'),
			'text_size_decrease' => __('Decrease Text', 'open-accessibility'),
			'readable_font_title' => __('Readable Font', 'open-accessibility'),
			'readable_font_text' => __('Readable Font', 'open-accessibility'),
			'links_underline_title' => __('Links Underline', 'open-accessibility'),
			'links_underline_text' => __('Links Underline', 'open-accessibility'),
			'hide_images_title' => __('Hide Images', 'open-accessibility'),
			'hide_images_text' => __('Hide Images', 'open-accessibility'),
			'reading_guide_title' => __('Reading Guide', 'open-accessibility'),
			'reading_guide_text' => __('Reading Guide', 'open-accessibility'),
			'focus_outline_title' => __('Focus Outline', 'open-accessibility'),
			'focus_outline_text' => __('Focus Outline', 'open-accessibility'),
			'line_height_title' => __('Line Height', 'open-accessibility'),
			'line_height_text' => __('Line Height', 'open-accessibility'),
			'text_align_title' => __('Text Align', 'open-accessibility'),
			'text_align_center' => __('Center', 'open-accessibility'),
			'text_align_left' => __('Left', 'open-accessibility'),
			'text_align_right' => __('Right', 'open-accessibility'),
			'pause_animations_title' => __('Pause Animations', 'open-accessibility'),
			'pause_animations_text' => __('Pause Animations', 'open-accessibility'),
			'sitemap_title' => __('Sitemap', 'open-accessibility'),
			'sitemap_text' => __('Sitemap', 'open-accessibility'),
			'statement_title' => __('Accessibility Statement', 'open-accessibility'),
			'statement_text' => __('Accessibility Statement', 'open-accessibility'),
			'dismiss_text' => __('Dismiss', 'open-accessibility'),
			'skip_to_content' => __('Skip to content', 'open-accessibility'),
		);
	}

	/**
	 * Add "Skip to content" link in the site header
	 *
	 * @since    1.0.0
	 */
	public function add_skip_to_content_link() {
		if (!$this->get_option('enable_skip_to_content', true)) {
			return;
		}

		$skip_to_element_id = $this->get_option('skip_to_element_id', 'content');
		$skip_to_element_id = apply_filters('open_accessibility_skip_to_element_id', $skip_to_element_id);

		echo '<a href="#' . esc_attr($skip_to_element_id) . '" class="open-accessibility-skip-to-content-link">' .
			esc_html__('Skip to content', 'open-accessibility') .
			'</a>';
		echo '<div class="open-accessibility-skip-to-content-backdrop"></div>';
	}

	/**
	 * Render the accessibility widget on the frontend
	 *
	 * @since    1.0.0
	 */
	public function render_accessibility_widget() {
		// Check if widget is enabled
		if ($this->get_option('disable_widget', false)) {
			return;
		}

		// Check device display settings
		$is_mobile = wp_is_mobile();
		if (($is_mobile && $this->get_option('hide_on_mobile', false)) ||
			(!$is_mobile && $this->get_option('hide_on_desktop', false))) {
			return;
		}

		include_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'public/partials/widget-template.php';
	}
}