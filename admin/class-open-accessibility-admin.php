<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Open_Accessibility
 */

class Open_Accessibility_Admin {

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
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		$this->plugin_name = 'open-accessibility';
		$this->version = OPEN_ACCESSIBILITY_VERSION;
		
		// Add filter to preserve tab parameter in settings redirect
		add_filter('wp_redirect', array($this, 'preserve_tab_in_redirect'), 10, 2);
	}



	/**
	 * Preserve tab parameter in settings redirect
	 *
	 * @since    1.0.0
	 * @param string $location The redirect URL
	 * @param int    $status   The redirect status code
	 * @return string Modified redirect URL
	 */
	public function preserve_tab_in_redirect($location, $status) {
		// Only modify redirects for our settings page
		if (strpos($location, 'open-accessibility-settings') !== false && 
			isset($_POST['open_accessibility_active_tab'])) {
			
			$active_tab = sanitize_text_field($_POST['open_accessibility_active_tab']);
			
			// Add the tab parameter to the redirect URL
			$location = add_query_arg('tab', $active_tab, $location);
			
		}
		
		return $location;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles($hook) {
		// Only load on plugin settings page
		if ($hook !== 'toplevel_page_open-accessibility-settings') {
			return;
		}

		wp_enqueue_style('wp-color-picker');
		wp_enqueue_style(
			$this->plugin_name,
			OPEN_ACCESSIBILITY_ASSETS_URL . 'css/open-accessibility-admin.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts($hook) {
		// Only load on plugin settings page
		if ($hook !== 'toplevel_page_open-accessibility-settings') {
			return;
		}

		wp_enqueue_script('wp-color-picker');
		wp_enqueue_script(
			$this->plugin_name,
			OPEN_ACCESSIBILITY_ASSETS_URL . 'js/open-accessibility-admin.js',
			array('jquery', 'wp-color-picker'),
			$this->version,
			false
		);

		wp_localize_script(
			$this->plugin_name,
			'open_accessibility_admin',
			array(
				'nonce' => wp_create_nonce('open_accessibility_nonce'),
				'i18n' => array(
					'invalid_email' => __('Please enter a valid email address.', 'open-accessibility'),
					'generating' => __('Generating...', 'open-accessibility'),
					'create_statement' => __('Create Statement', 'open-accessibility'),
					'view_statement' => __('Statement created successfully. Would you like to view it now?', 'open-accessibility'),
					'copy_statement' => __('Copy Statement', 'open-accessibility'),
					'close' => __('Close', 'open-accessibility'),
					'copied_statement' => __('Statement copied to clipboard.', 'open-accessibility'),
					'dismiss_notice' => __('Dismiss this notice.', 'open-accessibility'),
					'ajax_error' => __('An error occurred. Please try again.', 'open-accessibility'),
					'accessibility_options' => __('Accessibility Options', 'open-accessibility'),
					'preview_note' => __('This is a preview of the accessibility widget with your current settings.', 'open-accessibility'),
					'widget_preview' => __('Widget Preview', 'open-accessibility'),
				)
			)
		);
	}

	/**
	 * Add options page to admin menu
	 *
	 * @since    1.0.0
	 */
	public function add_options_page() {
		add_menu_page(
			__('Accessibility Settings', 'open-accessibility'),
			__('Accessibility', 'open-accessibility'),
			'manage_options',
			'open-accessibility-settings',
			array($this, 'display_options_page'),
			'dashicons-universal-access',
			100
		);
	}

	/**
	 * Display the options page content
	 *
	 * @since    1.0.0
	 */
	public function display_options_page() {
		include_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'admin/partials/admin-display.php';
	}

	/**
	 * Register settings, sections and fields
	 *
	 * @since    1.0.0
	 */
	public function register_settings() {
		register_setting(
			'open_accessibility_options_group',
			'open_accessibility_options',
			array($this, 'sanitize_options')
		);

		// General Settings
		add_settings_section(
			'open_accessibility_general',
			__('General Settings', 'open-accessibility'),
			array($this, 'general_section_callback'),
			'open-accessibility-settings'
		);

		// Widget Design
		add_settings_section(
			'open_accessibility_design',
			__('Widget Design', 'open-accessibility'),
			array($this, 'design_section_callback'),
			'open-accessibility-settings'
		);

		// Widget Position
		add_settings_section(
			'open_accessibility_position',
			__('Widget Position', 'open-accessibility'),
			array($this, 'position_section_callback'),
			'open-accessibility-settings'
		);

		// Widget Features
		add_settings_section(
			'open_accessibility_features',
			__('Widget Features', 'open-accessibility'),
			array($this, 'features_section_callback'),
			'open-accessibility-settings'
		);

		// Accessibility Statement
		add_settings_section(
			'open_accessibility_statement',
			__('Accessibility Statement', 'open-accessibility'),
			array($this, 'statement_section_callback'),
			'open-accessibility-settings'
		);

		// Add a new section for Advanced Settings
		add_settings_section(
			'open_accessibility_advanced',
			__('Advanced Settings', 'open-accessibility'),
			array($this, 'advanced_section_callback'),
			'open-accessibility-settings'
		);

		// General Settings fields
		add_settings_field(
			'disable_widget',
			__('Disable Widget', 'open-accessibility'),
			array($this, 'checkbox_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_general',
			array(
				'id' => 'disable_widget',
				'label' => __('Disable the accessibility widget completely', 'open-accessibility')
			)
		);

		add_settings_field(
			'hide_on_mobile',
			__('Hide on Mobile', 'open-accessibility'),
			array($this, 'checkbox_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_general',
			array(
				'id' => 'hide_on_mobile',
				'label' => __('Hide the accessibility widget on mobile devices', 'open-accessibility')
			)
		);

		add_settings_field(
			'hide_on_desktop',
			__('Hide on Desktop', 'open-accessibility'),
			array($this, 'checkbox_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_general',
			array(
				'id' => 'hide_on_desktop',
				'label' => __('Hide the accessibility widget on desktop devices', 'open-accessibility')
			)
		);

		// Widget design fields
		add_settings_field(
			'icon',
			__('Widget Icon', 'open-accessibility'),
			array($this, 'icon_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_design'
		);

		add_settings_field(
			'icon_size',
			__('Icon Size', 'open-accessibility'),
			array($this, 'select_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_design',
			array(
				'id' => 'icon_size',
				'options' => array(
					'small' => __('Small', 'open-accessibility'),
					'medium' => __('Medium', 'open-accessibility'),
					'large' => __('Large', 'open-accessibility')
				)
			)
		);

		add_settings_field(
			'icon_color',
			__('Icon Color', 'open-accessibility'),
			array($this, 'color_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_design',
			array(
				'id' => 'icon_color',
				'default' => '#ffffff'
			)
		);

		add_settings_field(
			'bg_color',
			__('Widget Background Color', 'open-accessibility'),
			array($this, 'color_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_design',
			array(
				'id' => 'bg_color',
				'default' => '#4054b2'
			)
		);

		// Widget position fields
		add_settings_field(
			'position',
			__('Widget Position', 'open-accessibility'),
			array($this, 'position_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_position'
		);

		// Widget features fields
		$features = array(
			'enable_skip_to_content' => __('Skip to Content Link', 'open-accessibility'),
			'enable_contrast' => __('Contrast Modes', 'open-accessibility'),
			'enable_grayscale' => __('Grayscale', 'open-accessibility'),
			'enable_text_size' => __('Text Size Adjustment', 'open-accessibility'),
			'enable_letter_spacing' => __('Letter Spacing', 'open-accessibility'),
			'enable_word_spacing' => __('Word Spacing', 'open-accessibility'),
			'enable_font_atkinson' => __('Font: Atkinson Hyperlegible', 'open-accessibility'),
			'enable_font_opendyslexic' => __('Font: OpenDyslexic', 'open-accessibility'),
			'enable_links_underline' => __('Links Underline', 'open-accessibility'),
			'enable_hide_images' => __('Hide Images', 'open-accessibility'),
			'enable_reading_guide' => __('Reading Guide', 'open-accessibility'),
			'enable_focus_outline' => __('Focus Outline', 'open-accessibility'),
			'enable_line_height' => __('Line Height Adjustment', 'open-accessibility'),
			'enable_text_align' => __('Text Alignment Options', 'open-accessibility'),
			'enable_animations_pause' => __('Pause Animations', 'open-accessibility')
		);

		foreach ($features as $id => $label) {
			add_settings_field(
				$id,
				$label,
				array($this, 'checkbox_field_callback'),
				'open-accessibility-settings',
				'open_accessibility_features',
				array(
					'id' => $id,
					/* translators: %s: feature name */
					'label' => sprintf(__('Enable %s feature', 'open-accessibility'), $label)
				)
			);
		}

		// Skip to content settings
		add_settings_field(
			'skip_to_element_id',
			__('Skip to Content Element ID', 'open-accessibility'),
			array($this, 'text_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_features',
			array(
				'id' => 'skip_to_element_id',
				'default' => 'content',
				'description' => __('HTML ID of the main content element (without the # symbol)', 'open-accessibility')
			)
		);

		// Accessibility Statement fields
		add_settings_field(
			'statement_url',
			__('Accessibility Statement URL', 'open-accessibility'),
			array($this, 'url_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_statement',
			array(
				'id' => 'statement_url',
				'description' => __('URL to your accessibility statement page', 'open-accessibility')
			)
		);

		add_settings_field(
			'generate_statement',
			__('Generate Statement', 'open-accessibility'),
			array($this, 'generate_statement_callback'),
			'open-accessibility-settings',
			'open_accessibility_statement'
		);

		// Advanced Settings field
		add_settings_field(
			'enable_debug',
			__('Enable Debug Mode', 'open-accessibility'),
			array($this, 'checkbox_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_advanced',
			array(
				'id' => 'enable_debug',
				'label' => __('Enable debug logging for troubleshooting', 'open-accessibility'),
				'description' => __('When enabled, debug information will be logged to the WordPress debug.log file. Please make sure that your WordPress install also has debugging and debug logging enabled.', 'open-accessibility')
			)
		);
	}

	/**
	 * General section callback
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html(__('Configure the general settings for the accessibility widget.', 'open-accessibility')) . '</p>';
	}

	/**
	 * Design section callback
	 */
	public function design_section_callback() {
		echo '<p>' . esc_html(__('Customize the appearance of the accessibility widget.', 'open-accessibility')) . '</p>';
	}

	/**
	 * Position section callback
	 */
	public function position_section_callback() {
		echo '<p>' . esc_html(__('Set the position of the accessibility widget on your website.', 'open-accessibility')) . '</p>';
	}

	/**
	 * Features section callback
	 */
	public function features_section_callback() {
		echo '<p>' . esc_html(__('Enable or disable specific accessibility features.', 'open-accessibility')) . '</p>';
	}

	/**
	 * Statement section callback
	 */
	public function statement_section_callback() {
		echo '<p>' . esc_html(__('Configure your accessibility statement.', 'open-accessibility')) . '</p>';
	}

	/**
	 * Advanced section callback
	 */
	public function advanced_section_callback() {
		echo '<p>' . esc_html__('Advanced settings for the accessibility widget.', 'open-accessibility') . '</p>';
	}

	/**
	 * Checkbox field callback
	 */
	public function checkbox_field_callback($args) {
		// Always merge saved options with defaults to avoid all checked/unchecked bugs
		if ( ! class_exists( 'Open_Accessibility_Utils' ) ) {
			require_once dirname( __DIR__ ) . '/includes/class-open-accessibility-utils.php';
		}
		$defaults = Open_Accessibility_Utils::get_default_options();
		$options = get_option('open_accessibility_options');
		if (!is_array($options)) {
			$options = array();
		}
		$merged = array_merge($defaults, $options);
		$id = $args['id'];
		$checked = isset($merged[$id]) ? $merged[$id] : false;

		echo '<label>';
		echo '<input type="checkbox" id="' . esc_attr($id) . '" name="open_accessibility_options[' . esc_attr($id) . ']" value="1" ' . checked(1, $checked, false) . '>';
		echo isset($args['label']) ? esc_html($args['label']) : '';
		echo '</label>';

		if (isset($args['description'])) {
			echo '<p class="description">' . esc_html($args['description']) . '</p>';
		}
	}

	/**
	 * Text field callback
	 */
	public function text_field_callback($args) {
		$options = get_option('open_accessibility_options');
		$id = $args['id'];
		$value = isset($options[$id]) ? $options[$id] : (isset($args['default']) ? $args['default'] : '');

		echo '<input type="text" id="' . esc_attr($id) . '" name="open_accessibility_options[' . esc_attr($id) . ']" value="' . esc_attr($value) . '" class="regular-text">';

		if (isset($args['description'])) {
			echo '<p class="description">' . esc_html($args['description']) . '</p>';
		}
	}

	/**
	 * URL field callback
	 */
	public function url_field_callback($args) {
		$options = get_option('open_accessibility_options');
		$id = $args['id'];
		$value = isset($options[$id]) ? $options[$id] : (isset($args['default']) ? $args['default'] : '');

		echo '<input type="url" id="' . esc_attr($id) . '" name="open_accessibility_options[' . esc_attr($id) . ']" value="' . esc_attr($value) . '" class="regular-text">';

		if (isset($args['description'])) {
			echo '<p class="description">' . esc_html($args['description']) . '</p>';
		}
	}

	/**
	 * Color field callback
	 */
	public function color_field_callback($args) {
		$options = get_option('open_accessibility_options');
		$id = $args['id'];
		$value = isset($options[$id]) ? $options[$id] : (isset($args['default']) ? $args['default'] : '#000000');

		echo '<input type="text" id="' . esc_attr($id) . '" name="open_accessibility_options[' . esc_attr($id) . ']" value="' . esc_attr($value) . '" class="color-picker">';
	}

	/**
	 * Select field callback
	 */
	public function select_field_callback($args) {
		$options = get_option('open_accessibility_options');
		$id = $args['id'];
		$selected = isset($options[$id]) ? $options[$id] : (isset($args['default']) ? $args['default'] : '');

		echo '<select id="' . esc_attr($id) . '" name="open_accessibility_options[' . esc_attr($id) . ']">';
		foreach ($args['options'] as $value => $label) {
			echo '<option value="' . esc_attr($value) . '" ' . selected($value, $selected, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';

		if (isset($args['description'])) {
			echo '<p class="description">' . esc_html($args['description']) . '</p>';
		}
	}

	/**
	 * Icon field callback
	 */
	public function icon_field_callback() {
		$options = get_option('open_accessibility_options');
		$selected = isset($options['icon']) ? $options['icon'] : 'accessibility';

		$icons = array(
			'open-accessibility' => __('Open Accessibility Logo', 'open-accessibility'),
			'universal-access' => __('Universal Access', 'open-accessibility'),
			'accessible-icon-project' => __('Accessible Icon Project', 'open-accessibility'),
			'visually-impaired' => __('Visually Impaired', 'open-accessibility'),
			'service-dog' => __('Service Dog', 'open-accessibility'),
			'international' => __('International Symbol', 'open-accessibility'),
		);

		// Preview color - use the currently saved icon color or default to white
		$preview_color = isset($options['icon_color']) ? $options['icon_color'] : '#ffffff';
		$preview_bg = isset($options['bg_color']) ? $options['bg_color'] : '#4054b2';

		echo '<div class="icon-selector">';
		foreach ($icons as $value => $label) {
			echo '<label class="icon-option">';
			echo '<input type="radio" name="open_accessibility_options[icon]" value="' . esc_attr($value) . '" ' . checked($value, $selected, false) . '>';
			echo '<span class="icon" style="background-color: ' . esc_attr($preview_bg) . ';">';

			// Inline SVG based on icon type
			switch ($value) {
				case 'open-accessibility':
					echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
						<g>
							<circle fill="' . esc_attr($preview_color) . '" r="5.51719" cy="12.13703" cx="32.55476"/>
							<path fill="' . esc_attr($preview_color) . '" d="m27.83315,42.78721a7.14807,7.14807 0 0 1 -1.10344,-0.99309l-7.00683,11.37865a2.75859,2.75859 0 0 0 4.69844,2.89321l6.7089,-10.90306l-3.29707,-2.3757z"/>
							<path fill="' . esc_attr($preview_color) . '" d="m46.93145,31.20995l-5.24464,-3.1459l-5.84822,-6.35249a4.87609,4.87609 0 0 0 -5.94973,-1.36275l-9.26887,4.11582a2.20687,2.20687 0 0 0 -1.07806,1.02951l-3.31031,6.62062a2.20687,2.20687 0 0 0 3.9481,1.97515l2.95611,-5.91332l3.90175,-1.73129l0,10.51907a4.95112,4.95112 0 0 0 2.0855,4.03196l8.1533,5.8758l1.97184,8.3784a2.75859,2.75859 0 1 0 5.37153,-1.26233l-2.20687,-9.37922a2.75859,2.75859 0 0 0 -1.06813,-1.60329l-5.02285,-3.64134a4.90809,4.90809 0 0 0 0.64661,-2.39998l0,-7.5122l1.68495,1.83391a2.23998,2.23998 0 0 0 0.48993,0.39834l5.51719,3.31031a2.20687,2.20687 0 0 0 2.27087,-3.78479z"/>
						</g>
					</svg>';
					break;

				case 'universal-access':
					echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
	                    <g transform="translate(0, 0)">
	                        <circle data-color="color-2" cx="32" cy="8" r="6" fill="' . esc_attr($preview_color) . '"></circle>
	                        <path d="M55.5,16H8.5a2.5,2.5,0,0,0,0,5L24,22V59a3,3,0,0,0,6,0l1-21h2l1,21a3,3,0,0,0,6,0V22l15.5-1a2.5,2.5,0,0,0,0-5Z" fill="' . esc_attr($preview_color) . '"></path>
	                    </g>
	                </svg>';
					break;

				case 'accessible-icon-project':
					echo '<svg width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
	                    <g>
	                        <path stroke="null" id="accessible_icon_project"
	                            d="m53.6162,29.30491c-0.69341,-0.71148 -1.6623,-1.08712 -2.65238,-1.03292l-11.98302,0.66703l6.59455,-7.51057c0.93946,-1.06986 1.20786,-2.49604 0.84465,-3.77045c-0.19167,-0.87327 -0.72051,-1.66784 -1.53297,-2.17549c-0.02495,-0.0178 -15.76321,-9.16053 -15.76321,-9.16053c-1.28514,-0.74654 -2.90451,-0.58206 -4.01354,0.40712l-7.68793,6.85776c-1.41643,1.2634 -1.54048,3.43586 -0.27699,4.85238c1.26358,1.41634 3.43613,1.54075 4.85247,0.2769l5.82851,-5.19913l4.81839,2.79807l-8.50521,9.6866c-3.52682,0.57518 -6.70008,2.20036 -9.19112,4.54355l4.4415,4.4415c2.0078,-1.82561 4.67368,-2.93992 7.59491,-2.93992c6.23089,0 11.30016,5.06936 11.30016,11.30034c0,2.92123 -1.11431,5.58694 -2.93974,7.59473l4.44123,4.4415c2.95862,-3.14518 4.77439,-7.37722 4.77439,-12.03623c0,-2.77571 -0.6444,-5.40064 -1.79019,-7.73479l4.63835,-0.25839l-1.12835,13.84056c-0.15428,1.8918 1.25455,3.55025 3.14644,3.70471c0.09472,0.00769 0.18925,0.01136 0.28289,0.01136c1.77141,0 3.27532,-1.36116 3.42164,-3.1577l1.44827,-17.77003c0.08085,-0.99017 -0.27064,-1.96666 -0.9637,-2.67796z" fill="' . esc_attr($preview_color) . '"/>
	                        <path stroke="null" id="svg_3"
	                            d="m47.2507,14.58258c3.17711,0 5.7524,-2.57555 5.7524,-5.75284c0,-3.17711 -2.57528,-5.75293 -5.7524,-5.75293c-3.17747,0 -5.75293,2.57582 -5.75293,5.75293c0,3.17729 2.57537,5.75284 5.75293,5.75284z" fill="' . esc_attr($preview_color) . '"/>
	                        <path stroke="null" id="svg_4"
	                            d="m26.9849,54.6474c-6.23098,0 -11.30034,-5.06936 -11.30034,-11.30034c0,-2.34829 0.72051,-4.53094 1.95127,-6.34l-4.48944,-4.48935c-2.33926,2.98518 -3.73771,6.74301 -3.73771,10.82935c0,9.70717 7.86904,17.57612 17.57621,17.57612c4.08652,0 7.844,-1.39846 10.82909,-3.7378l-4.48935,-4.48917c-1.80898,1.2304 -3.99163,1.95118 -6.33974,1.95118z" fill="' . esc_attr($preview_color) . '"/>
	                    </g>
	                </svg>';
					break;

				case 'visually-impaired':
					echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
	                    <g transform="translate(0, 0)">
	                        <path d="M13.47,46.288l8.206-8.207A11.983,11.983,0,0,1,38.09,21.668l7.246-7.246A29.924,29.924,0,0,0,32,11c-5.135,0-18.366,1.826-30.288,18.744a3.9,3.9,0,0,0,.011,4.509A55.389,55.389,0,0,0,13.47,46.288Z" fill="' . esc_attr($preview_color) . '"></path>
	                        <path d="M62.278,29.73A51.9,51.9,0,0,0,50.6,17.646l-8.262,8.262A11.983,11.983,0,0,1,25.917,42.326l-7.233,7.233A28.782,28.782,0,0,0,32,53c5.055,0,18.121-1.825,30.264-18.733A3.9,3.9,0,0,0,62.278,29.73Z" fill="' . esc_attr($preview_color) . '"></path>
	                        <path data-color="color-2" d="M5,60a1,1,0,0,1-.707-1.707l54-54a1,1,0,1,1,1.414,1.414l-54,54A1,1,0,0,1,5,60Z" fill="' . esc_attr($preview_color) . '"></path>
	                    </g>
	                </svg>';
					break;

				case 'service-dog':
					echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
	                    <g transform="translate(0, 0)">
	                        <path d="M40.387,21.812l-.194.317A6.038,6.038,0,0,1,35.074,25H13a10.026,10.026,0,0,1-9.895-8.636,1,1,0,0,0-1.982,0A11.892,11.892,0,0,0,9.792,29.552,7.943,7.943,0,0,0,9,33V58.41A2.593,2.593,0,0,0,11.59,61h.046A2.574,2.574,0,0,0,14.2,58.8L15.95,47.4l5.461-7.28,20.684,4.7,1.739,13.912A2.593,2.593,0,0,0,49,58.41V44.1l3.394-16.289Z" fill="' . esc_attr($preview_color) . '"></path>
	                        <path d="M62.263,14.035,51.828,11.189,50.97,7.758a1,1,0,0,0-1.824-.279L41.434,20.1,52.816,25.79l1-4.79H57a6.006,6.006,0,0,0,6-6A1,1,0,0,0,62.263,14.035Z" fill="' . esc_attr($preview_color) . '"></path>
	                        <path d="M35,21A17.019,17.019,0,0,1,18,4a1,1,0,0,1,2,0A15.017,15.017,0,0,0,35,19a1,1,0,0,1,0,2Z" fill="' . esc_attr($preview_color) . '" data-color="color-2"></path>
	                    </g>
	                </svg>';
					break;

				case 'international':
					echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
		                <g>
		                    <path fill="' . esc_attr($preview_color) . '" d="m28.528,10.44871c-0.02206,2.63731 -2.19905,4.75685 -4.86622,4.73781c-2.66717,-0.01904 -4.81774,-2.16948 -4.80716,-4.80687c0.01058,-2.63739 2.17831,-4.76619 4.84553,-4.7585c2.66722,0.00769 4.82711,2.14896 4.82801,4.78639"/>
		                    <path fill="' . esc_attr($preview_color) . '" d="m19.69623,12.85492l2.05457,23.68158l17.51788,-0.10813l6.81251,16.3284l8.21827,-2.59524l-1.73016,-4.10913l-4.21727,1.08135l-6.05556,-14.92264l-16.3284,0l0,-3.35219l9.73216,0l0,-4.3254l-10.92164,0l-0.21627,-9.40775l0,0"/>
		                    <path fill="' . esc_attr($preview_color) . '" d="m19.17205,24.87591c-25.42553,17.07942 6.54702,48.855 23.35513,24.85376l-1.85455,-6.37852c-7.47413,20.6243 -37.97369,4.02709 -21.19149,-12.86944l-0.30909,-5.60579z"/>
		                </g>
		            </svg>';
					break;

				default:
					// Default accessibility icon
					echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
	                    <g transform="translate(0, 0)">
	                        <circle data-color="color-2" cx="32" cy="8" r="6" fill="' . esc_attr($preview_color) . '"></circle>
	                        <path d="M55.5,16H8.5a2.5,2.5,0,0,0,0,5L24,22V59a3,3,0,0,0,6,0l1-21h2l1,21a3,3,0,0,0,6,0V22l15.5-1a2.5,2.5,0,0,0,0-5Z" fill="' . esc_attr($preview_color) . '"></path>
	                    </g>
	                </svg>';
					break;
			}

			echo '</span>';
			echo '<span class="icon-label">' . esc_html($label) . '</span>';
			echo '</label>';
		}
		echo '</div>';
	}

	/**
	 * Position field callback
	 */
	public function position_field_callback() {
		$options = get_option('open_accessibility_options');
		$selected = isset($options['position']) ? $options['position'] : 'left';

		$positions = array(
			'left' => __('Left', 'open-accessibility'),
			'right' => __('Right', 'open-accessibility'),
			'bottom-left' => __('Bottom Left', 'open-accessibility'),
			'bottom-right' => __('Bottom Right', 'open-accessibility')
		);

		echo '<div class="position-selector">';
		foreach ($positions as $value => $label) {
			echo '<label class="position-option">';
			echo '<input type="radio" name="open_accessibility_options[position]" value="' . esc_attr($value) . '" ' . checked($value, $selected, false) . '>';
			echo '<span class="position position-' . esc_attr($value) . '" title="' . esc_attr($label) . '"></span>';
			echo '<span class="position-label">' . esc_html($label) . '</span>';
			echo '</label>';
		}
		echo '</div>';
	}

	/**
	 * Generate statement callback
	 */
	public function generate_statement_callback() {
		echo '<p>' . esc_html(__('Generate an accessibility statement for your website.', 'open-accessibility')) . '</p>';
		echo '<a href="#" class="button button-primary" id="generate-statement">' . esc_html(__('Generate Statement', 'open-accessibility')) . '</a>';
		echo '<div id="statement-generator-container" style="display:none;">';
		// Form for statement generator
		echo '<div class="statement-form">';

		// Organization name
		echo '<div class="form-field">';
		echo '<label for="statement-org-name">' . esc_html(__('Organization/Website Name', 'open-accessibility')) . ':</label>';
		echo '<input type="text" id="statement-org-name" name="statement_org_name" class="regular-text">';
		echo '</div>';

		// Contact email
		echo '<div class="form-field">';
		echo '<label for="statement-contact-email">' . esc_html(__('Contact Email', 'open-accessibility')) . ':</label>';
		echo '<input type="email" id="statement-contact-email" name="statement_contact_email" class="regular-text">';
		echo '</div>';

		// Conformance status
		echo '<div class="form-field">';
		echo '<label for="statement-conformance">' . esc_html(__('Conformance Status', 'open-accessibility')) . ':</label>';
		echo '<select id="statement-conformance" name="statement_conformance">';
		echo '<option value="A">' . esc_html(__('WCAG 2.1 Level A', 'open-accessibility')) . '</option>';
		echo '<option value="AA" selected>' . esc_html(__('WCAG 2.1 Level AA', 'open-accessibility')) . '</option>';
		echo '<option value="AAA">' . esc_html(__('WCAG 2.1 Level AAA', 'open-accessibility')) . '</option>';
		echo '</select>';
		echo '</div>';

		// Create page
		echo '<div class="form-field">';
		echo '<label>';
		echo '<input type="checkbox" id="statement-create-page" name="statement_create_page" checked>';
		echo esc_html(__('Create a new page for the statement', 'open-accessibility'));
		echo '</label>';
		echo '</div>';

		// Submit button
		echo '<div class="form-field">';
		echo '<button type="button" class="button button-primary" id="create-statement">' . esc_html(__('Create Statement', 'open-accessibility')) . '</button>';
		echo '<button type="button" class="button" id="cancel-statement">' . esc_html(__('Cancel', 'open-accessibility')) . '</button>';
		echo '</div>';

		echo '</div>'; // .statement-form
		echo '</div>'; // #statement-generator-container
	}

	/**
	 * Sanitize options
	 */
	public function sanitize_options($input) {

		// Create a new array for sanitized values
		$sanitized = array();

		// Sanitize checkbox values
		$checkboxes = array(
			'disable_widget',
			'hide_on_mobile',
			'hide_on_desktop',
			'enable_skip_to_content',
			'enable_contrast',
			'enable_grayscale',
			'enable_text_size',
			'enable_letter_spacing',
			'enable_word_spacing',
			'enable_font_atkinson',
			'enable_font_opendyslexic',
			'enable_links_underline',
			'enable_hide_images',
			'enable_reading_guide',
			'enable_focus_outline',
			'enable_line_height',
			'enable_text_align',
			'enable_animations_pause',
			'enable_debug',
		);

		foreach ($checkboxes as $key) {
			$sanitized[$key] = isset($input[$key]) ? 1 : 0;
		}

		// Sanitize text inputs
		if (isset($input['skip_to_element_id'])) {
			$sanitized['skip_to_element_id'] = sanitize_text_field($input['skip_to_element_id']);
		}

		// Sanitize URLs
		if (isset($input['sitemap_url'])) {
			$sanitized['sitemap_url'] = esc_url_raw($input['sitemap_url']);
		}

		if (isset($input['statement_url'])) {
			$sanitized['statement_url'] = esc_url_raw($input['statement_url']);
		}

		// Sanitize colors
		if (isset($input['icon_color'])) {
			$sanitized['icon_color'] = sanitize_hex_color($input['icon_color']);
		}

		if (isset($input['bg_color'])) {
			$sanitized['bg_color'] = sanitize_hex_color($input['bg_color']);
		}

		// Sanitize select fields
		if (isset($input['icon'])) {
			$valid_icons = array('open-accessibility', 'universal-access', 'accessible-icon-project', 'visually-impaired', 'service-dog', 'international');
			$sanitized['icon'] = in_array($input['icon'], $valid_icons) ? $input['icon'] : 'accessibility';
		}

		if (isset($input['icon_size'])) {
			$valid_sizes = array('small', 'medium', 'large');
			$sanitized['icon_size'] = in_array($input['icon_size'], $valid_sizes) ? $input['icon_size'] : 'medium';
		}

		if (isset($input['position'])) {
			$valid_positions = array('left', 'right', 'bottom-left', 'bottom-right');
			$sanitized['position'] = in_array($input['position'], $valid_positions) ? $input['position'] : 'left';
		}

		return $sanitized;
	}
}