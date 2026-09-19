<?php
/**
 * PHPUnit bootstrap for the Open Accessibility test suite.
 *
 * Loads the WordPress test library, then loads the plugin the same way WordPress
 * would. Requires the test library to be present; run `composer test:setup` first.
 *
 * @package Open_Accessibility
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	// Must match the default in tests/install-wp-tests.sh. Note this is NOT
	// sys_get_temp_dir(): on macOS that resolves per-user (e.g. /var/folders/...)
	// while the installer writes to /tmp, and the two would silently diverge.
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// The test library's config file defines $table_prefix and the DB constants.
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library at {$_tests_dir}." . PHP_EOL;
	echo 'Run `composer test:setup` to install it.' . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test.
 *
 * Hooked to `muplugins_loaded` so the plugin boots at the same point it would on
 * a real install. Note this means the activation hook does NOT fire — tests that
 * need activation state must call `open_accessibility_activate()` themselves or
 * use the helpers in tests/class-oa-testcase.php.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__ ) . '/open-accessibility.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/class-oa-testcase.php';
