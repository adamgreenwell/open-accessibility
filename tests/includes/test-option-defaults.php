<?php
/**
 * Tests for the plugin's option defaults contract.
 *
 * The defect these guard against: the plugin had two competing defaults arrays
 * (the activation seeder and Open_Accessibility_Utils::get_default_options())
 * plus per-call fallbacks at each read site. They disagreed in both directions,
 * so a key could be read by the frontend but never seeded, or seeded but absent
 * from the defaults map and therefore unrepairable by saving the settings form.
 *
 * These tests read the plugin's own source to discover which keys are consumed,
 * rather than checking against a hand-written list. A hand-written list would
 * fail to notice the exact problem these tests exist to catch.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Utils::get_default_options
 * @covers Open_Accessibility_Utils::get_options
 */
class Test_Option_Defaults extends OA_TestCase {

	/**
	 * Read a file from the plugin.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	private function read_plugin_file( $relative ) {
		$path = OPEN_ACCESSIBILITY_PLUGIN_DIR . $relative;

		$this->assertFileExists( $path, "Expected plugin file {$relative} to exist." );

		return file_get_contents( $path );
	}

	/**
	 * Option keys the plugin reads via Open_Accessibility_Public::get_option().
	 *
	 * @return string[]
	 */
	private function keys_read_by_frontend_payload() {
		$source = $this->read_plugin_file( 'public/class-open-accessibility-public.php' );

		preg_match_all( '/get_option\(\s*\'([a-z_]+)\'/', $source, $matches );

		// The first argument of the plugin's own option is the option name, not a key.
		return array_values( array_unique( array_diff( $matches[1], array( 'open_accessibility_options' ) ) ) );
	}

	/**
	 * Option keys the widget template reads directly from the options array.
	 *
	 * @return string[]
	 */
	private function keys_read_by_widget_template() {
		$source = $this->read_plugin_file( 'public/partials/widget-template.php' );

		preg_match_all( '/\$options\[\'([a-z_]+)\'\]/', $source, $matches );

		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * Every option key the frontend consumes must have a declared default.
	 *
	 * Without this, a missing key falls back to whatever each individual read
	 * site happened to hardcode, which is how the two arrays drifted apart.
	 */
	public function test_every_frontend_consumed_key_has_a_default() {
		$defaults = Open_Accessibility_Utils::get_default_options();
		$consumed = $this->keys_read_by_frontend_payload();

		$this->assertNotEmpty( $consumed, 'Source parsing found no keys; the parser has drifted from the code.' );

		$missing = array_values( array_diff( $consumed, array_keys( $defaults ) ) );

		$this->assertSame(
			array(),
			$missing,
			'These keys are read by the frontend but have no default: ' . implode( ', ', $missing )
		);
	}

	/**
	 * Every option key the widget template reads must have a declared default.
	 */
	public function test_every_widget_template_key_has_a_default() {
		$defaults = Open_Accessibility_Utils::get_default_options();
		$consumed = $this->keys_read_by_widget_template();

		$this->assertNotEmpty( $consumed, 'Source parsing found no keys; the parser has drifted from the code.' );

		$missing = array_values( array_diff( $consumed, array_keys( $defaults ) ) );

		$this->assertSame(
			array(),
			$missing,
			'These keys are read by the widget template but have no default: ' . implode( ', ', $missing )
		);
	}

	/**
	 * Activating the plugin must seed every key the defaults declare.
	 *
	 * A fresh install should not depend on a later settings save to become
	 * complete; that is what left a live install missing enable_reading_mask.
	 */
	public function test_activation_seeds_every_declared_default() {
		$this->activate_plugin();

		$stored = get_option( self::OPTION );

		$this->assertIsArray( $stored, 'Activation should create the options array.' );

		$missing = array_values( array_diff( array_keys( Open_Accessibility_Utils::get_default_options() ), array_keys( $stored ) ) );

		$this->assertSame(
			array(),
			$missing,
			'Activation did not seed: ' . implode( ', ', $missing )
		);
	}

	/**
	 * Activation must not seed keys the defaults do not declare.
	 *
	 * Guards the other direction of the drift: keys that were seeded but absent
	 * from the defaults map could never be restored by saving the settings form.
	 */
	public function test_activation_seeds_nothing_beyond_the_declared_defaults() {
		$this->activate_plugin();

		$stored  = get_option( self::OPTION );
		$extra   = array_values( array_diff( array_keys( $stored ), array_keys( Open_Accessibility_Utils::get_default_options() ) ) );

		$this->assertSame(
			array(),
			$extra,
			'Activation seeded keys that the defaults do not declare: ' . implode( ', ', $extra )
		);
	}

	/**
	 * The seeder and the defaults map must agree exactly.
	 *
	 * This is the core assertion: one source of truth, and the seeder derived
	 * from it rather than maintained alongside it.
	 */
	public function test_seeded_options_match_declared_defaults_exactly() {
		$this->activate_plugin();

		$stored   = get_option( self::OPTION );
		$defaults = Open_Accessibility_Utils::get_default_options();

		$this->assertSame(
			array_keys( $defaults ),
			array_keys( $stored ),
			'Seeded keys should be exactly the declared defaults, in the same order.'
		);

		$this->assertSame(
			$defaults,
			$stored,
			'Seeded values should equal the declared defaults.'
		);
	}

	/**
	 * Every declared default is a key something actually reads.
	 *
	 * Prevents the opposite failure: a default nobody consumes lingering as dead
	 * configuration, which is how enable_sitemap survived after its UI was lost.
	 */
	public function test_declared_defaults_are_all_consumed() {
		$consumed = array_merge(
			$this->keys_read_by_frontend_payload(),
			$this->keys_read_by_widget_template()
		);

		// Read by other parts of the plugin rather than the frontend payload.
		$consumed_elsewhere = array(
			'disable_widget',        // Open_Accessibility_Shortcode::render().
			'hide_on_mobile',        // Open_Accessibility_Public::render_accessibility_widget().
			'hide_on_desktop',       // Open_Accessibility_Public::render_accessibility_widget().
			'enable_analytics',      // Open_Accessibility_Ajax::log_usage().
			'enable_debug',          // Open_Accessibility_Utils::log().
			'statement_url',         // Open_Accessibility_Statement_Generator::create_statement_page().
			'default_profile',       // Open_Accessibility_Utils::get_enabled_profiles().
			'cursor_size',           // frontend payload; gates a CSS class.
			'enable_cursor_size',    // widget control gate.
			'enable_editor_audit',   // gates enqueue_block_editor_assets().
			'saturation_level',      // frontend payload; drives the inline filter.
			'enable_saturation',     // widget control gate.
		);

		// Profile toggles are read by iterating the profile registry rather than
		// by naming each key, so they are discovered from the registry instead of
		// being listed by hand. A profile whose option nobody reads still fails.
		foreach ( Open_Accessibility_Utils::get_profiles() as $profile ) {
			$consumed_elsewhere[] = $profile['option'];
		}

		$consumed = array_values( array_unique( array_merge( $consumed, $consumed_elsewhere ) ) );

		$unused = array_values( array_diff( array_keys( Open_Accessibility_Utils::get_default_options() ), $consumed ) );

		$this->assertSame(
			array(),
			$unused,
			'These defaults are declared but nothing reads them: ' . implode( ', ', $unused )
		);
	}
}
