<?php
/**
 * Database functionality for the Open Accessibility plugin.
 *
 * @since      1.0.0
 * @package    Open_Accessibility
 */

class Open_Accessibility_DB {

	/**
	 * Database version.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $db_version    The current database version.
	 */
	private static $db_version = '1.0.0';

	/**
	 * Initialize the database tables.
	 *
	 * @since    1.0.0
	 */
	public static function init() {
		self::create_tables();
	}

	/**
	 * Create the database tables needed by the plugin.
	 *
	 * @since    1.0.0
	 */
	public static function create_tables() {
		global $wpdb;

		$db_version = get_option( 'open_accessibility_db_version', '0' );

		// Only run if the database version is outdated
		if ( version_compare( $db_version, self::$db_version, '<' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Usage statistics table
			$table_name = $wpdb->prefix . 'open_accessibility_stats';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				session_id varchar(64) NOT NULL,
				feature varchar(64) NOT NULL,
				action varchar(32) NOT NULL,
				value varchar(64) NOT NULL,
				ip varchar(45) NOT NULL,
				user_agent text NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY session_id (session_id),
				KEY feature (feature),
				KEY created_at (created_at)
			) $charset_collate;";

			dbDelta( $sql );

			// Update database version option
			update_option( 'open_accessibility_db_version', self::$db_version );
		}
	}

	/**
	 * Log feature usage for analytics.
	 *
	 * @since    1.0.0
	 * @param    string    $session_id    Unique session ID.
	 * @param    string    $feature       Feature name.
	 * @param    string    $action        Action performed.
	 * @param    string    $value         Value of the action.
	 * @return   boolean   Whether the log was inserted successfully.
	 */
	public static function log_feature_usage( $session_id, $feature, $action, $value = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'open_accessibility_stats';

		$ip = Open_Accessibility_Utils::get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		$result = $wpdb->insert(
			$table_name,
			array(
				'session_id' => $session_id,
				'feature' => $feature,
				'action' => $action,
				'value' => $value,
				'ip' => $ip,
				'user_agent' => $user_agent,
				'created_at' => current_time( 'mysql' )
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s'
			)
		);

		return $result !== false;
	}

	/**
	 * Get feature usage statistics.
	 *
	 * @since    1.0.0
	 * @param    string    $period       Period to get stats for (day, week, month, year).
	 * @param    string    $feature      Specific feature to get stats for (optional).
	 * @return   array     Array of usage statistics.
	 */
	public static function get_feature_usage( $period = 'month', $feature = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'open_accessibility_stats';

		// Determine date range based on period
		$date_clause = '';
		switch ( $period ) {
			case 'day':
				$date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)';
				break;
			case 'week':
				$date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
				break;
			case 'month':
				$date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
				break;
			case 'year':
				$date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
				break;
			default:
				$date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
		}

		// Feature-specific clause
		$feature_clause = '';
		if ( ! empty( $feature ) ) {
			$feature_clause = $wpdb->prepare( 'AND feature = %s', $feature );
		}

		// Get total unique sessions
		$total_sessions = $wpdb->get_var(
			"SELECT COUNT(DISTINCT session_id) 
			FROM $table_name 
			WHERE 1=1 $date_clause $feature_clause"
		);

		// Get feature usage count
		$feature_counts = $wpdb->get_results(
			"SELECT feature, COUNT(*) as count, COUNT(DISTINCT session_id) as unique_sessions
			FROM $table_name 
			WHERE 1=1 $date_clause $feature_clause
			GROUP BY feature
			ORDER BY count DESC",
			ARRAY_A
		);

		// Get action counts for each feature
		$action_counts = $wpdb->get_results(
			"SELECT feature, action, value, COUNT(*) as count
			FROM $table_name 
			WHERE 1=1 $date_clause $feature_clause
			GROUP BY feature, action, value
			ORDER BY feature, count DESC",
			ARRAY_A
		);

		return array(
			'total_sessions' => $total_sessions ? $total_sessions : 0,
			'feature_counts' => $feature_counts ? $feature_counts : array(),
			'action_counts' => $action_counts ? $action_counts : array(),
		);
	}

	/**
	 * Clear old statistics data to keep the database size manageable.
	 *
	 * @since    1.0.0
	 * @param    int       $days    Number of days to keep data for.
	 * @return   int       Number of rows deleted.
	 */
	public static function cleanup_old_data( $days = 90 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'open_accessibility_stats';

		$query = $wpdb->prepare(
			"DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
			$days
		);

		$result = $wpdb->query( $query );

		return $result !== false ? $result : 0;
	}

	/**
	 * Get the size of the database tables.
	 *
	 * @since    1.0.0
	 * @return   array    Array with table sizes.
	 */
	public static function get_table_sizes() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'open_accessibility_stats';

		$size_query = $wpdb->prepare(
			"SELECT 
				table_name AS 'table',
				round(((data_length + index_length) / 1024 / 1024), 2) 'size_mb' 
			FROM information_schema.TABLES 
			WHERE table_schema = %s
			AND table_name = %s",
			DB_NAME,
			$table_name
		);

		$size = $wpdb->get_row( $size_query, ARRAY_A );

		$count_query = "SELECT COUNT(*) as count FROM $table_name";
		$count = $wpdb->get_var( $count_query );

		return array(
			'table' => $table_name,
			'size_mb' => $size ? $size['size_mb'] : 0,
			'rows' => $count ? $count : 0
		);
	}

	/**
	 * Uninstall database tables and options.
	 *
	 * @since    1.0.0
	 */
	public static function uninstall() {
		global $wpdb;

		// Drop tables
		$table_name = $wpdb->prefix . 'open_accessibility_stats';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

		// Delete options
		delete_option( 'open_accessibility_db_version' );
		delete_option( 'open_accessibility_options' );
	}
}