<?php
/**
 * Tests for Open_Accessibility_Utils::get_options().
 *
 * This accessor is the single read path for plugin options. Its contract is that
 * callers can rely on every declared default being present, which is what lets
 * read sites drop the bespoke fallbacks they used to carry.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Utils::get_options
 */
class Test_Get_Options extends OA_TestCase {

	/**
	 * With nothing stored, the accessor returns the declared defaults.
	 */
	public function test_returns_defaults_when_option_is_absent() {
		delete_option( self::OPTION );

		$this->assertSame(
			Open_Accessibility_Utils::get_default_options(),
			Open_Accessibility_Utils::get_options()
		);
	}

	/**
	 * A partially stored option is completed by the defaults.
	 *
	 * This is the upgrade path that matters: an install saved before a key
	 * existed must still yield that key.
	 */
	public function test_completes_a_partial_stored_option() {
		update_option( self::OPTION, array( 'icon_size' => 'large' ) );
		wp_cache_flush();

		$options = Open_Accessibility_Utils::get_options();

		// The stored value wins.
		$this->assertSame( 'large', $options['icon_size'] );

		// Everything else is backfilled.
		$this->assertSame(
			array(),
			array_values( array_diff( array_keys( Open_Accessibility_Utils::get_default_options() ), array_keys( $options ) ) ),
			'Every declared default should be present in the merged result.'
		);
	}

	/**
	 * Stored values take precedence over defaults.
	 */
	public function test_stored_values_override_defaults() {
		$stored = array(
			'disable_widget' => 1,
			'widget_title'   => 'Custom Heading',
			'icon_color'     => '#123456',
			'bg_color'       => '#abcdef',
			'position'       => 'bottom-right',
		);

		update_option( self::OPTION, $stored );
		wp_cache_flush();

		$options = Open_Accessibility_Utils::get_options();

		foreach ( $stored as $key => $value ) {
			$this->assertSame( $value, $options[ $key ], "Stored value for {$key} should win over the default." );
		}
	}

	/**
	 * A non-array stored value degrades to the defaults rather than fataling.
	 *
	 * A corrupted or third-party-written option should not break the frontend.
	 */
	public function test_non_array_stored_value_falls_back_to_defaults() {
		update_option( self::OPTION, 'not-an-array' );
		wp_cache_flush();

		$this->assertSame(
			Open_Accessibility_Utils::get_default_options(),
			Open_Accessibility_Utils::get_options()
		);
	}

	/**
	 * An empty stored array yields the defaults.
	 */
	public function test_empty_stored_array_yields_defaults() {
		update_option( self::OPTION, array() );
		wp_cache_flush();

		$this->assertSame(
			Open_Accessibility_Utils::get_default_options(),
			Open_Accessibility_Utils::get_options()
		);
	}

	/**
	 * The accessor does not write anything back.
	 *
	 * Reading options on every page load must not turn into a write, which would
	 * make the option dirty and defeat the autoload fast path.
	 */
	public function test_accessor_is_read_only() {
		delete_option( self::OPTION );

		Open_Accessibility_Utils::get_options();

		$this->assertFalse(
			get_option( self::OPTION ),
			'get_options() should not create the option.'
		);
	}
}
