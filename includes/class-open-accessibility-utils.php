<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Utility functions for the Open Accessibility plugin.
 *
 * @since      1.0.0
 * @package    Open_Accessibility
 */

class Open_Accessibility_Utils {

	/**
	 * Check if a user agent is a mobile device.
	 *
	 * @since    1.0.0
	 * @return   boolean    True if mobile device, false otherwise.
	 */
	public static function is_mobile() {
		if ( function_exists( 'wp_is_mobile' ) ) {
			return wp_is_mobile();
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash($_SERVER['HTTP_USER_AGENT']) ) : '';
		$mobile_agents = array(
			'Android', 'iPhone', 'iPod', 'iPad', 'Windows Phone', 'BlackBerry', 'webOS', 'Mobile'
		);

		foreach ( $mobile_agents as $agent ) {
			if ( strpos( $user_agent, $agent ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the client IP address.
	 *
	 * @since    1.0.0
	 * @return   string    The client IP address.
	 */
	public static function get_client_ip() {
		$ip_headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);

		foreach ( $ip_headers as $header ) {
			if ( isset( $_SERVER[ $header ] ) && ! empty( $_SERVER[ $header ] ) && filter_var( wp_unslash($_SERVER[ $header ]), FILTER_VALIDATE_IP ) ) {
				return sanitize_text_field( wp_unslash($_SERVER[ $header ] ) );
			}
		}

		return '127.0.0.1';
	}

	/**
	 * Get a list of all available features.
	 *
	 * @since    1.0.0
	 * @return   array    Array of features with IDs and labels.
	 */
	public static function get_available_features() {
		return array(
			'enable_skip_to_content' => __( 'Skip to Content Link', 'open-accessibility' ),
			'enable_contrast' => __( 'Contrast Modes', 'open-accessibility' ),
			'enable_grayscale' => __( 'Grayscale', 'open-accessibility' ),
			'enable_text_size' => __( 'Text Size Adjustment', 'open-accessibility' ),
			'enable_readable_font' => __( 'Readable Font', 'open-accessibility' ),
			'enable_links_underline' => __( 'Links Underline', 'open-accessibility' ),
			'enable_hide_images' => __( 'Hide Images', 'open-accessibility' ),
			'enable_reading_guide' => __( 'Reading Guide', 'open-accessibility' ),
			'enable_focus_outline' => __( 'Focus Outline', 'open-accessibility' ),
			'enable_line_height' => __( 'Line Height Adjustment', 'open-accessibility' ),
			'enable_text_align' => __( 'Text Alignment Options', 'open-accessibility' ),
			'enable_sitemap' => __( 'Sitemap', 'open-accessibility' ),
			'enable_animations_pause' => __( 'Pause Animations', 'open-accessibility' )
		);
	}

	/**
	 * Get default plugin options.
	 *
	 * @since    1.0.0
	 * @return   array    Default plugin options.
	 */
	public static function get_default_options() {
		return array(
			// General
			'disable_widget' => 0,
			'hide_on_mobile' => 0,
			'hide_on_desktop' => 0,

			// Design
			'icon' => 'accessibility',
			'icon_size' => 'medium',
			'icon_color' => '#ffffff',
			'bg_color' => '#4054b2',

			// Position
			'position' => 'left',

			// Features - all enabled by default
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

			// Other settings
			'skip_to_element_id' => 'content',
			'sitemap_url' => '',
			'statement_url' => '',

			// Debug settings
			'enable_debug' => 0,
		);
	}

	/**
	 * Sanitize plugin options.
	 *
	 * @since    1.0.0
	 * @param    array    $input    Raw options array.
	 * @return   array    Sanitized options array.
	 */
	public static function sanitize_options( $input ) {
		$sanitized = array();
		$defaults = self::get_default_options();

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
			'enable_animations_pause',
			'enable_debug',
		);

		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
		}

		// Sanitize text inputs
		$text_fields = array(
			'skip_to_element_id'
		);

		foreach ( $text_fields as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( $input[ $key ] );
			} else {
				$sanitized[ $key ] = $defaults[ $key ];
			}
		}

		// Sanitize URLs
		$url_fields = array(
			'sitemap_url',
			'statement_url'
		);

		foreach ( $url_fields as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized[ $key ] = esc_url_raw( $input[ $key ] );
			} else {
				$sanitized[ $key ] = $defaults[ $key ];
			}
		}

		// Sanitize colors
		$color_fields = array(
			'icon_color',
			'bg_color'
		);

		foreach ( $color_fields as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_hex_color( $input[ $key ] );
			} else {
				$sanitized[ $key ] = $defaults[ $key ];
			}
		}

		// Sanitize select fields
		if ( isset( $input['icon'] ) ) {
			$valid_icons = array( 'accessibility', 'universal-access', 'wheelchair', 'eye', 'adjust' );
			$sanitized['icon'] = in_array( $input['icon'], $valid_icons ) ? $input['icon'] : $defaults['icon'];
		}

		if ( isset( $input['icon_size'] ) ) {
			$valid_sizes = array( 'small', 'medium', 'large' );
			$sanitized['icon_size'] = in_array( $input['icon_size'], $valid_sizes ) ? $input['icon_size'] : $defaults['icon_size'];
		}

		if ( isset( $input['position'] ) ) {
			$valid_positions = array( 'left', 'right', 'bottom-left', 'bottom-right' );
			$sanitized['position'] = in_array( $input['position'], $valid_positions ) ? $input['position'] : $defaults['position'];
		}

		return $sanitized;
	}

	/**
	 * Log debug messages if plugin debug mode or WP_DEBUG is enabled.
	 *
	 * @since    1.0.0
	 * @param    mixed     $message    The message to log.
	 * @param    string    $level      Log level: 'debug', 'info', 'warning', 'error'.
	 * @return   void
	 */
	public static function log($message, $level = 'debug') {
		$options = get_option('open_accessibility_options', array());
		$debug_enabled = isset($options['enable_debug']) && $options['enable_debug'];

		// Only log if plugin debug mode is enabled or WP_DEBUG is true
		if ($debug_enabled || (defined('WP_DEBUG') && WP_DEBUG === true)) {
			$log_dir = OPEN_ACCESSIBILITY_PLUGIN_DIR . 'logs';

			// Create logs directory if it doesn't exist
			if (!file_exists($log_dir)) {
				wp_mkdir_p($log_dir);

				// Create .htaccess file to prevent direct access
				$htaccess_content = "# Prevent direct access to files\n";
				$htaccess_content .= "<Files \"*\">\n";
				$htaccess_content .= "Require all denied\n";
				$htaccess_content .= "</Files>";
				file_put_contents($log_dir . '/.htaccess', $htaccess_content);

				// Create index.php file for security
				file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
			}

			$date = gmdate('Y-m-d H:i:s');
			$level_upper = strtoupper($level);

			if (is_array($message) || is_object($message)) {
				$message = print_r($message, true);
			}

			$log_message = "[$date] [$level_upper] $message" . PHP_EOL;
			$log_file = $log_dir . '/debug-' . gmdate('Y-m-d') . '.log';

			// Append to log file
			error_log($log_message, 3, $log_file);

			// Also log to PHP error log if WP_DEBUG is enabled
			if (defined('WP_DEBUG') && WP_DEBUG === true) {
				error_log("Open Accessibility: $log_message");
			}
		}
	}
}