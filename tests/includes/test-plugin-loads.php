<?php
/**
 * Smoke tests: the plugin loads inside a real WordPress environment.
 *
 * These exist to prove the harness works before any behaviour is asserted. If
 * these fail, nothing else in the suite is meaningful.
 *
 * @package Open_Accessibility
 */

/**
 * @coversNothing
 */
class Test_Plugin_Loads extends OA_TestCase {

	/**
	 * The plugin's main constant is defined, which means open-accessibility.php ran.
	 */
	public function test_plugin_file_was_loaded() {
		$this->assertTrue(
			defined( 'OPEN_ACCESSIBILITY_VERSION' ),
			'The plugin entry file did not load; check the muplugins_loaded hook in tests/bootstrap.php.'
		);
	}

	/**
	 * Plugin constants point at real directories.
	 */
	public function test_plugin_constants_resolve_to_real_paths() {
		$this->assertDirectoryExists( OPEN_ACCESSIBILITY_PLUGIN_DIR );
		$this->assertDirectoryExists( OPEN_ACCESSIBILITY_ASSETS_DIR );
		$this->assertFileExists( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'open-accessibility.php' );
	}

	/**
	 * The utility class the rest of the plugin depends on is loadable.
	 */
	public function test_utils_class_is_available() {
		$this->assertTrue(
			class_exists( 'Open_Accessibility_Utils' ),
			'Open_Accessibility_Utils should be loaded by the plugin bootstrap.'
		);
	}

	/**
	 * WordPress itself is the version the plugin claims to support.
	 *
	 * Guards against someone running the suite against a stale test library.
	 */
	public function test_wordpress_test_environment_is_usable() {
		global $wp_version;

		$this->assertNotEmpty( $wp_version, 'No WordPress version detected.' );
		$this->assertTrue(
			version_compare( $wp_version, '5.2', '>=' ),
			"The test library is WordPress {$wp_version}, below the plugin's 5.2 floor."
		);
	}
}
