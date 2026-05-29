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

// Preserve options long enough to clean up related content.
$options = get_option( 'open_accessibility_options', array() );

// Include DB class to handle database cleanup
require_once plugin_dir_path( __FILE__ ) . 'includes/database/class-open-accessibility-db.php';

// Uninstall database tables
Open_Accessibility_DB::uninstall();

// Clear any scheduled hooks
wp_clear_scheduled_hook( 'open_accessibility_cleanup_data' );

// Check if there's an accessibility statement page
if ( isset( $options['statement_url'] ) && ! empty( $options['statement_url'] ) ) {
	// Try to find and delete the accessibility statement page
	$page_id = url_to_postid( $options['statement_url'] );
	if ( $page_id > 0 ) {
		wp_delete_post( $page_id, true );
	}
}

// Delete all plugin options
delete_option( 'open_accessibility_options' );
delete_option( 'open_accessibility_db_version' );
