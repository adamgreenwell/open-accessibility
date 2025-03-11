<?php
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
		$this->version = OPEN_A11Y_VERSION;
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
			OPEN_A11Y_ASSETS_URL . 'css/open-accessibility-admin.css',
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
			OPEN_A11Y_ASSETS_URL . 'js/open-accessibility-admin.js',
			array('jquery', 'wp-color-picker'),
			$this->version,
			false
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
		include_once OPEN_A11Y_PLUGIN_DIR . 'admin/partials/admin-display.php';
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

		// Register fields - only a few examples shown for brevity

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
			'enable_readable_font' => __('Readable Font', 'open-accessibility'),
			'enable_links_underline' => __('Links Underline', 'open-accessibility'),
			'enable_hide_images' => __('Hide Images', 'open-accessibility'),
			'enable_reading_guide' => __('Reading Guide', 'open-accessibility'),
			'enable_focus_outline' => __('Focus Outline', 'open-accessibility'),
			'enable_line_height' => __('Line Height Adjustment', 'open-accessibility'),
			'enable_text_align' => __('Text Alignment Options', 'open-accessibility'),
			'enable_sitemap' => __('Sitemap', 'open-accessibility'),
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

		// Sitemap URL field
		add_settings_field(
			'sitemap_url',
			__('Sitemap URL', 'open-accessibility'),
			array($this, 'url_field_callback'),
			'open-accessibility-settings',
			'open_accessibility_features',
			array(
				'id' => 'sitemap_url',
				'description' => __('Leave empty to use default WordPress sitemap or enter a custom sitemap URL', 'open-accessibility')
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
	}

	/**
	 * General section callback
	 */
	public function general_section_callback() {
		echo '<p>' . __('Configure the general settings for the accessibility widget.', 'open-accessibility') . '</p>';
	}

	/**
	 * Design section callback
	 */
	public function design_section_callback() {
		echo '<p>' . __('Customize the appearance of the accessibility widget.', 'open-accessibility') . '</p>';
	}

	/**
	 * Position section callback
	 */
	public function position_section_callback() {
		echo '<p>' . __('Set the position of the accessibility widget on your website.', 'open-accessibility') . '</p>';
	}

	/**
	 * Features section callback
	 */
	public function features_section_callback() {
		echo '<p>' . __('Enable or disable specific accessibility features.', 'open-accessibility') . '</p>';
	}

	/**
	 * Statement section callback
	 */
	public function statement_section_callback() {
		echo '<p>' . __('Configure your accessibility statement.', 'open-accessibility') . '</p>';
	}

	/**
	 * Checkbox field callback
	 */
	public function checkbox_field_callback($args) {
		$options = get_option('open_accessibility_options');
		$id = $args['id'];
		$checked = isset($options[$id]) ? $options[$id] : false;

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
		$selected = isset($options['icon']) ? $options['icon'] : 'a11y';

		$icons = array(
			'a11y' => __('Accessibility Icon', 'open-accessibility'),
			'universal-access' => __('Universal Access', 'open-accessibility'),
			'wheelchair' => __('Wheelchair', 'open-accessibility'),
			'eye' => __('Eye', 'open-accessibility'),
			'adjust' => __('Adjust', 'open-accessibility')
		);

		echo '<div class="icon-selector">';
		foreach ($icons as $value => $label) {
			echo '<label class="icon-option">';
			echo '<input type="radio" name="open_accessibility_options[icon]" value="' . esc_attr($value) . '" ' . checked($value, $selected, false) . '>';
			echo '<span class="icon icon-' . esc_attr($value) . '" title="' . esc_attr($label) . '"></span>';
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
		echo '<p>' . __('Generate an accessibility statement for your website.', 'open-accessibility') . '</p>';
		echo '<a href="#" class="button button-primary" id="generate-statement">' . __('Generate Statement', 'open-accessibility') . '</a>';
		echo '<div id="statement-generator-container" style="display:none;">';
		// Form for statement generator
		echo '<div class="statement-form">';

		// Organization name
		echo '<div class="form-field">';
		echo '<label for="statement-org-name">' . __('Organization/Website Name', 'open-accessibility') . ':</label>';
		echo '<input type="text" id="statement-org-name" name="statement_org_name" class="regular-text">';
		echo '</div>';

		// Contact email
		echo '<div class="form-field">';
		echo '<label for="statement-contact-email">' . __('Contact Email', 'open-accessibility') . ':</label>';
		echo '<input type="email" id="statement-contact-email" name="statement_contact_email" class="regular-text">';
		echo '</div>';

		// Conformance status
		echo '<div class="form-field">';
		echo '<label for="statement-conformance">' . __('Conformance Status', 'open-accessibility') . ':</label>';
		echo '<select id="statement-conformance" name="statement_conformance">';
		echo '<option value="A">' . __('WCAG 2.1 Level A', 'open-accessibility') . '</option>';
		echo '<option value="AA" selected>' . __('WCAG 2.1 Level AA', 'open-accessibility') . '</option>';
		echo '<option value="AAA">' . __('WCAG 2.1 Level AAA', 'open-accessibility') . '</option>';
		echo '</select>';
		echo '</div>';

		// Create page
		echo '<div class="form-field">';
		echo '<label>';
		echo '<input type="checkbox" id="statement-create-page" name="statement_create_page" checked>';
		echo __('Create a new page for the statement', 'open-accessibility');
		echo '</label>';
		echo '</div>';

		// Submit button
		echo '<div class="form-field">';
		echo '<button type="button" class="button button-primary" id="create-statement">' . __('Create Statement', 'open-accessibility') . '</button>';
		echo '<button type="button" class="button" id="cancel-statement">' . __('Cancel', 'open-accessibility') . '</button>';
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
			'enable_readable_font',
			'enable_links_underline',
			'enable_hide_images',
			'enable_reading_guide',
			'enable_focus_outline',
			'enable_line_height',
			'enable_text_align',
			'enable_sitemap',
			'enable_animations_pause'
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
			$valid_icons = array('a11y', 'universal-access', 'wheelchair', 'eye', 'adjust');
			$sanitized['icon'] = in_array($input['icon'], $valid_icons) ? $input['icon'] : 'a11y';
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