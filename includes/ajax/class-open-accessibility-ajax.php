<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Handle AJAX requests for the Open Accessibility plugin.
 *
 * @since      1.0.0
 * @package    Open_Accessibility
 */

class Open_Accessibility_Ajax {

	/**
	 * Initialize AJAX hooks.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		// Admin AJAX actions
		add_action( 'wp_ajax_open_accessibility_generate_statement', array( __CLASS__, 'generate_statement' ) );
		add_action( 'wp_ajax_open_accessibility_get_stats', array( __CLASS__, 'get_stats' ) );
		add_action( 'wp_ajax_open_accessibility_cleanup_data', array( __CLASS__, 'cleanup_data' ) );

		// Frontend AJAX actions
		add_action( 'wp_ajax_nopriv_open_accessibility_log_usage', array( __CLASS__, 'log_usage' ) );
		add_action( 'wp_ajax_open_accessibility_log_usage', array( __CLASS__, 'log_usage' ) );

		// Debug log handlers
		add_action('wp_ajax_open_accessibility_get_debug_logs', array(__CLASS__, 'get_debug_logs'));
		add_action('wp_ajax_open_accessibility_clear_debug_logs', array(__CLASS__, 'clear_debug_logs'));
	}

	/**
	 * Generate an accessibility statement.
	 *
	 * @since    1.0.0
	 */
	public static function generate_statement() {
		// Check for nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'open_accessibility_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'open-accessibility' ) ) );
		}

		// Check for permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'open-accessibility' ) ) );
		}

		// Get form data
		$org_name = isset( $_POST['org_name'] ) ? sanitize_text_field( wp_unslash( $_POST['org_name'] ) ) : get_bloginfo( 'name' );
		$contact_email = isset( $_POST['contact_email'] ) ? sanitize_email( wp_unslash($_POST['contact_email'] ) ) : '';
		$conformance_level = isset( $_POST['conformance_level'] ) ? sanitize_text_field( wp_unslash($_POST['conformance_level'] ) ) : 'AA';
		$create_page = isset( $_POST['create_page'] ) && sanitize_text_field( wp_unslash( $_POST['create_page'] ) ) === 'true';

		// Prepare statement data
		$statement_data = array(
			'org_name' => $org_name,
			'website_url' => site_url(),
			'contact_email' => $contact_email,
			'conformance_level' => $conformance_level
		);

		// Include statement generator class if necessary
		if ( ! class_exists( 'Open_Accessibility_Statement_Generator' ) ) {
			require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-statement-generator.php';
		}

		// Create page or return statement content
		if ( $create_page ) {
			$page_id = Open_Accessibility_Statement_Generator::create_statement_page( $statement_data );

			if ( is_wp_error( $page_id ) ) {
				wp_send_json_error( array( 'message' => $page_id->get_error_message() ) );
			} else {
				$page_url = get_permalink( $page_id );
				wp_send_json_success( array(
					'message' => __( 'Accessibility statement page created successfully.', 'open-accessibility' ),
					'page_url' => $page_url
				) );
			}
		} else {
			$statement = Open_Accessibility_Statement_Generator::generate_statement( $statement_data );
			wp_send_json_success( array( 'statement' => $statement ) );
		}
	}

	/**
	 * Get usage statistics.
	 *
	 * @since    1.0.0
	 */
	public static function get_stats() {
		// Check for nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['nonce'] ) ), 'open_accessibility_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'open-accessibility' ) ) );
		}

		// Check for permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'open-accessibility' ) ) );
		}

		// Get parameters
		$period = isset( $_POST['period'] ) ? sanitize_text_field( wp_unslash($_POST['period'] ) ) : 'month';
		$feature = isset( $_POST['feature'] ) ? sanitize_text_field( wp_unslash($_POST['feature'] ) ) : '';

		// Include DB class if necessary
		if ( ! class_exists( 'Open_Accessibility_DB' ) ) {
			require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/database/class-open-accessibility-db.php';
		}

		// Get statistics
		$stats = Open_Accessibility_DB::get_feature_usage( $period, $feature );
		$table_info = Open_Accessibility_DB::get_table_sizes();

		// Return stats
		wp_send_json_success( array(
			'stats' => $stats,
			'table_info' => $table_info
		) );
	}

	/**
	 * Clean up old usage data.
	 *
	 * @since    1.0.0
	 */
	public static function cleanup_data() {
		// Check for nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce'] ) ), 'open_accessibility_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'open-accessibility' ) ) );
		}

		// Check for permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'open-accessibility' ) ) );
		}

		// Get parameters
		$days = isset( $_POST['days'] ) ? intval( $_POST['days'] ) : 90;

		// Include DB class if necessary
		if ( ! class_exists( 'Open_Accessibility_DB' ) ) {
			require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/database/class-open-accessibility-db.php';
		}

		// Perform cleanup
		$deleted = Open_Accessibility_DB::cleanup_old_data( $days );
		$table_info = Open_Accessibility_DB::get_table_sizes();

		// Return results
		wp_send_json_success( array(
			/* translators: %d: number of records */
			'message' => sprintf( __( '%d records deleted successfully.', 'open-accessibility' ), $deleted ),
			'deleted' => $deleted,
			'table_info' => $table_info
		) );
	}

	/**
	 * Log feature usage from the frontend.
	 *
	 * @since    1.0.0
	 */
	public static function log_usage() {
		// Check for nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce'] ) ), 'open_accessibility_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'open-accessibility' ) ) );
		}

		// Get parameters
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash($_POST['session_id'] ) ) : '';
		$feature = isset( $_POST['feature'] ) ? sanitize_text_field( wp_unslash($_POST['feature'] ) ) : '';
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash($_POST['action'] ) ) : '';
		$value = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash($_POST['value'] ) ) : '';

		// Validate required fields
		if ( empty( $session_id ) || empty( $feature ) || empty( $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'open-accessibility' ) ) );
		}

		// Include necessary classes
		if ( ! class_exists( 'Open_Accessibility_DB' ) ) {
			require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/database/class-open-accessibility-db.php';
		}

		if ( ! class_exists( 'Open_Accessibility_Utils' ) ) {
			require_once OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-utils.php';
		}

		// Log the usage
		$success = Open_Accessibility_DB::log_feature_usage( $session_id, $feature, $action, $value );

		if ( $success ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to log usage.', 'open-accessibility' ) ) );
		}
	}

	/**
	 * Get debug logs.
	 *
	 * @since    1.0.0
	 */
	public static function get_debug_logs() {
		// Check for nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce']) ), 'open_accessibility_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'open-accessibility')));
		}

		// Check for permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this.', 'open-accessibility')));
		}

		$log_dir = OPEN_ACCESSIBILITY_PLUGIN_DIR . 'logs';
		$today_log = $log_dir . '/debug-' . gmdate('Y-m-d') . '.log';

		if (file_exists($today_log)) {
			$logs = file_get_contents($today_log);
			wp_send_json_success(array('logs' => esc_html($logs)));
		} else {
			wp_send_json_success(array('logs' => ''));
		}
	}

	/**
	 * Clear debug logs.
	 *
	 * @since    1.0.0
	 */
	public static function clear_debug_logs() {
		// Check for nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce']) ), 'open_accessibility_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'open-accessibility')));
		}

		// Check for permissions
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => __('You do not have permission to do this.', 'open-accessibility')));
		}

		$log_dir = OPEN_ACCESSIBILITY_PLUGIN_DIR . 'logs';

		if (is_dir($log_dir)) {
			$files = glob($log_dir . '/debug-*.log');
			$count = 0;

			foreach ($files as $file) {
				if (unlink($file)) {
					$count++;
				}
			}

			wp_send_json_success(array(
				/* translators: %d: number of log files */
				'message' => sprintf(__('%d log files cleared successfully.', 'open-accessibility'), $count)
			));
		} else {
			wp_send_json_success(array('message' => __('No log files found.', 'open-accessibility')));
		}
	}
}

// Initialize AJAX handlers
Open_Accessibility_Ajax::init();