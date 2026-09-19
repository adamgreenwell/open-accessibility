<?php
/**
 * Tests for the removal of dead code.
 *
 * This code was removed because nothing read it. These tests fail if any of it
 * comes back, which is the regression that matters: dead code tends to be
 * copied rather than deleted, and each revival of an unused localisation string
 * or a stale option key makes the next drift harder to spot.
 *
 * @package Open_Accessibility
 */

/**
 * @coversNothing
 */
class Test_Removed_Dead_Code extends OA_TestCase {

	/**
	 * Read a plugin file and fail clearly if it is missing.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	private function read_plugin_file( $relative ) {
		$path = OPEN_ACCESSIBILITY_PLUGIN_DIR . $relative;

		$this->assertFileExists( $path, "Expected {$relative} to exist." );

		return file_get_contents( $path );
	}

	/**
	 * The unused widget class is gone.
	 *
	 * It was never a WP_Widget, never instantiated, and had no callers.
	 */
	public function test_unused_widget_class_is_removed() {
		$this->assertFileDoesNotExist(
			OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-widget.php',
			'class-open-accessibility-widget.php has no callers and should stay deleted.'
		);

		$this->assertFalse(
			class_exists( 'Open_Accessibility_Widget' ),
			'Nothing should reintroduce the unused widget class.'
		);
	}

	/**
	 * Look up a named function in a plugin file.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @param string $function Function name.
	 * @return bool
	 */
	private function source_defines_function( $relative, $function ) {
		$source = $this->read_plugin_file( $relative );

		return (bool) preg_match( '/function\s+' . preg_quote( $function, '/' ) . '\s*\(/', $source );
	}

	/**
	 * The unused utility helpers are gone.
	 *
	 * get_client_ip() lost its last caller when usage logging stopped storing IP
	 * addresses in 1.4.0. get_available_features() never had one. The orphaned
	 * sanitize_options() also had no callers — the registered sanitize callback
	 * is Admin::sanitize_options().
	 */
	public function test_unused_utils_methods_are_removed() {
		$this->assertFalse(
			$this->source_defines_function( 'includes/class-open-accessibility-utils.php', 'get_client_ip' ),
			'get_client_ip() has no callers; do not reintroduce it without one.'
		);

		$this->assertFalse(
			$this->source_defines_function( 'includes/class-open-accessibility-utils.php', 'get_available_features' ),
			'get_available_features() has no callers.'
		);

		$this->assertFalse(
			$this->source_defines_function( 'includes/class-open-accessibility-utils.php', 'sanitize_options' ),
			'Utils::sanitize_options() has no callers; Admin::sanitize_options() is the registered callback.'
		);

		// The accessor that replaced the per-call fallbacks must still be here.
		$this->assertTrue(
			$this->source_defines_function( 'includes/class-open-accessibility-utils.php', 'get_options' ),
			'get_options() is the single read path and must not be removed.'
		);
	}

	/**
	 * Every localisation key the admin JavaScript reads is defined in PHP.
	 *
	 * The debug log viewer read seven keys that were never defined, so its
	 * messages rendered as `undefined`. The viewer is gone; this test stops the
	 * next undefined key from shipping.
	 */
	public function test_admin_js_i18n_keys_are_all_defined_in_php() {
		$js = $this->read_plugin_file( 'assets/js/open-accessibility-admin.js' );

		preg_match_all( '/open_accessibility_admin\.i18n\.([a-z_]+)/', $js, $matches );
		$wanted = array_values( array_unique( $matches[1] ) );

		$this->assertNotEmpty( $wanted, 'No i18n keys found; the parser has drifted from the code.' );

		$php = $this->read_plugin_file( 'admin/class-open-accessibility-admin.php' );
		preg_match_all( "/'([a-z_]+)'\s*=>/", $php, $php_matches );
		$defined = array_values( array_unique( $php_matches[1] ) );

		$undefined = array_values( array_diff( $wanted, $defined ) );

		$this->assertSame(
			array(),
			$undefined,
			'These i18n keys are read by admin JS but never defined in PHP: ' . implode( ', ', $undefined )
		);
	}

	/**
	 * The debug log AJAX handlers are no longer registered.
	 *
	 * They read OPEN_ACCESSIBILITY_PLUGIN_DIR . 'logs', a directory nothing has
	 * written since 1.2.2 moved logging to the WordPress debug log, so the viewer
	 * they powered was permanently empty.
	 */
	public function test_debug_log_ajax_handlers_are_unregistered() {
		$this->assertFalse(
			has_action( 'wp_ajax_open_accessibility_get_debug_logs' ),
			'The debug log viewer was removed; its AJAX action should not be registered.'
		);

		$this->assertFalse(
			has_action( 'wp_ajax_open_accessibility_clear_debug_logs' ),
			'The debug log viewer was removed; its AJAX action should not be registered.'
		);
	}

	/**
	 * The handler methods themselves are gone, not merely unhooked.
	 */
	public function test_debug_log_handler_methods_are_removed() {
		$source = $this->read_plugin_file( 'includes/ajax/class-open-accessibility-ajax.php' );

		$this->assertStringNotContainsString( 'function get_debug_logs', $source );
		$this->assertStringNotContainsString( 'function clear_debug_logs', $source );
	}

	/**
	 * The dead sitemap dependency toggle is gone.
	 *
	 * It bound a change handler to an `enable_sitemap` checkbox that no settings
	 * field ever rendered, which left the Sitemap URL row permanently hidden on
	 * the Links tab.
	 */
	public function test_dead_sitemap_dependency_toggle_is_removed() {
		$js = $this->read_plugin_file( 'assets/js/open-accessibility-admin.js' );

		$this->assertStringNotContainsString( 'toggleSitemapField', $js );
		$this->assertStringNotContainsString( 'enable_sitemap', $js );
	}

	/**
	 * No admin CSS is left styling removed markup.
	 */
	public function test_admin_css_has_no_orphaned_log_viewer_rules() {
		$css = $this->read_plugin_file( 'assets/css/open-accessibility-admin.css' );

		$this->assertStringNotContainsString( 'open-accessibility-log-content', $css );
		$this->assertStringNotContainsString( 'open-accessibility-log-entries', $css );

		// The debug status indicator is still rendered, so its styles must stay.
		$this->assertStringContainsString( 'open-accessibility-debug-status', $css );
	}

	/**
	 * The Advanced tab still reports debug status after the viewer was removed.
	 */
	public function test_admin_display_still_shows_debug_status_without_the_viewer() {
		$partial = $this->read_plugin_file( 'admin/partials/admin-display.php' );

		$this->assertStringContainsString( 'open-accessibility-debug-status', $partial );
		$this->assertStringNotContainsString( 'open-accessibility-log-viewer', $partial );
	}
}
