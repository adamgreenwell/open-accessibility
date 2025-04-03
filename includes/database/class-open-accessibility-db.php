<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

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
	 *
	 * @global   wpdb      $wpdb          WordPress database abstraction object.
	 * 
	 * @uses     wpdb::insert             Direct DB call necessary for plugin table.
	 */
	public static function log_feature_usage( $session_id, $feature, $action, $value = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'open_accessibility_stats';

		$ip = Open_Accessibility_Utils::get_client_ip();
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		// Clear any related caches
		self::clear_stats_cache();

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
	 * 
	 * @global   wpdb      $wpdb         WordPress database abstraction object.
	 * @uses     wp_cache_get/set        Caching for database query results.
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

		// Define cache keys for the different queries
		$sessions_cache_key = 'open_access_stats_sessions_' . md5($date_clause . $feature_clause);
		$features_cache_key = 'open_access_stats_features_' . md5($date_clause . $feature_clause);
		$actions_cache_key = 'open_access_stats_actions_' . md5($date_clause . $feature_clause);

		// Try to get results from cache first
		$total_sessions = wp_cache_get($sessions_cache_key, 'open-accessibility');
		$feature_counts = wp_cache_get($features_cache_key, 'open-accessibility');
		$action_counts = wp_cache_get($actions_cache_key, 'open-accessibility');

		// If total_sessions not in cache, run the query and cache the result
		if (false === $total_sessions) {
			$total_sessions = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(DISTINCT session_id) 
		            FROM `{$wpdb->prefix}open_accessibility_stats` 
		            WHERE 1=1 %s %s",
					$date_clause,
					$feature_clause
				)
			);

			// Cache the result (cache for 1 hour)
			wp_cache_set($sessions_cache_key, $total_sessions, 'open-accessibility', HOUR_IN_SECONDS);
		}

		// If feature_counts not in cache, run the query and cache the result
		if (false === $feature_counts) {
			$feature_counts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT feature, COUNT(*) as count, COUNT(DISTINCT session_id) as unique_sessions
		        FROM `{$wpdb->prefix}open_accessibility_stats` 
		        WHERE 1=1 %s %s
		        GROUP BY feature
		        ORDER BY count DESC",
					$date_clause,
					$feature_clause
				),
				ARRAY_A
			);
			
			// Cache the result (cache for 1 hour)
			wp_cache_set($features_cache_key, $feature_counts, 'open-accessibility', HOUR_IN_SECONDS);
		}

		// If action_counts not in cache, run the query and cache the result
		if (false === $action_counts) {
			$action_counts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT feature, action, value, COUNT(*) as count
		        FROM `{$wpdb->prefix}open_accessibility_stats` 
		        WHERE 1=1 %s %s
		        GROUP BY feature, action, value
		        ORDER BY feature, count DESC",
					$date_clause,
					$feature_clause
				),
				ARRAY_A
			);
			
			// Cache the result (cache for 1 hour)
			wp_cache_set($actions_cache_key, $action_counts, 'open-accessibility', HOUR_IN_SECONDS);
		}

		return array(
			'total_sessions' => $total_sessions ? $total_sessions : 0,
			'feature_counts' => $feature_counts ? $feature_counts : array(),
			'action_counts' => $action_counts ? $action_counts : array(),
		);
	}

	/**
	 * Clear all stats-related caches.
	 * 
	 * @since    1.0.0
	 * @return   void
	 */
	public static function clear_stats_cache() {
		wp_cache_delete('open_access_stats_sessions_', 'open-accessibility');
		
		// Delete all stats caches by group
		if (function_exists('wp_cache_flush_group')) {
			wp_cache_flush_group('open-accessibility');
		}
	}

	/**
	 * Clear old statistics data to keep the database size manageable.
	 *
	 * @since    1.0.0
	 * @param    int       $days    Number of days to keep data for.
	 * @return   int       Number of rows deleted.
	 * 
	 * @global   wpdb      $wpdb    WordPress database abstraction object.
	 * @uses     wpdb::query       Direct DB call necessary for plugin table.
	 */
	public static function cleanup_old_data( $days = 90 ) {
		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}open_accessibility_stats WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		// Clear caches after data deletion
		self::clear_stats_cache();

		return $result !== false ? $result : 0;
	}

	/**
	 * Get the size of the database tables.
	 *
	 * @since    1.0.0
	 * @return   array    Array with table sizes.
	 * 
	 * @global   wpdb     $wpdb     WordPress database abstraction object.
	 * @uses     wpdb::get_row      Direct DB call necessary for plugin table.
	 * @uses     wpdb::get_var      Direct DB call necessary for plugin table.
	 */
	public static function get_table_sizes() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'open_accessibility_stats';
		$cache_key = 'open_access_table_sizes';
		
		// Try to get results from cache first
		$cached_result = wp_cache_get($cache_key, 'open-accessibility');
		if (false !== $cached_result) {
			return $cached_result;
		}

		// Use $wpdb->prepare() directly within get_row()
		$size = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
                table_name AS 'table',
                round(((data_length + index_length) / 1024 / 1024), 2) 'size_mb' 
            FROM information_schema.TABLES 
            WHERE table_schema = %s
            AND table_name = %s",
				DB_NAME,
				$table_name
			),
			ARRAY_A
		);

		// For table rows count
		$count_cache_key = 'open_access_table_count';
		$count = wp_cache_get($count_cache_key, 'open-accessibility');
		
		if (false === $count) {
			// For table names, we need to use $wpdb->prefix directly
			$count = $wpdb->get_var("SELECT COUNT(*) as count FROM `{$wpdb->prefix}open_accessibility_stats`");
			wp_cache_set($count_cache_key, $count, 'open-accessibility', HOUR_IN_SECONDS);
		}

		$result = array(
			'table' => $table_name,
			'size_mb' => $size ? $size['size_mb'] : 0,
			'rows' => $count ? $count : 0
		);
		
		// Cache the combined result
		wp_cache_set($cache_key, $result, 'open-accessibility', HOUR_IN_SECONDS);

		return $result;
	}

	/**
	 * Uninstall database tables and options.
	 *
	 * @since    1.0.0
	 * 
	 * @global   wpdb     $wpdb    WordPress database abstraction object.
	 * @uses     wpdb::query       Direct DB call necessary for dropping custom plugin table.
	 */
	public static function uninstall() {
		global $wpdb;

		// Clear all plugin caches
		self::clear_stats_cache();

		// Drop tables - DB schema change is necessary during uninstall
		$wpdb->query( 
			$wpdb->prepare( 'DROP TABLE IF EXISTS %s', 
				$wpdb->prefix . 'open_accessibility_stats' 
			)
		);

		// Delete options
		delete_option( 'open_accessibility_db_version' );
		delete_option( 'open_accessibility_options' );
	}
}