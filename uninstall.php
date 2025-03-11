<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package    Open_Accessibility
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete all plugin options
delete_option( 'open_accessibility_options' );
delete_option( 'open_accessibility_db_version' );

// Include DB class to handle database cleanup
require_once plugin_dir_path( __FILE__ ) . 'includes/database/class-open-accessibility-db.php';

// Uninstall database tables
Open_Accessibility_DB::uninstall();

// Clear any scheduled hooks
wp_clear_scheduled_hook( 'open_accessibility_cleanup_data' );

// Check if there's an accessibility statement page
$statement_url = get_option( 'open_accessibility_options', array() );
if ( isset( $statement_url['statement_url'] ) && ! empty( $statement_url['statement_url'] ) ) {
	// Try to find and delete the accessibility statement page
	$page_id = url_to_postid( $statement_url['statement_url'] );
	if ( $page_id > 0 ) {
		wp_delete_post( $page_id, true );
	}
}