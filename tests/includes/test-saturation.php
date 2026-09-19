<?php
/**
 * Tests for the saturation control.
 *
 * Saturation reduces colour intensity without going all the way to grey, which
 * is what the existing Grayscale toggle does. It is applied per target element
 * rather than to a shared ancestor: a CSS filter on an ancestor creates a
 * containing block for position:fixed descendants, which has previously made the
 * widget unreachable (fixed in 1.1.0 and 1.4.01). Following Grayscale's
 * per-element approach avoids that class of bug entirely.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Utils::get_default_options
 * @covers Open_Accessibility_Admin::sanitize_options
 * @covers Open_Accessibility_Public::get_target_config
 */
class Test_Saturation extends OA_TestCase {

	/**
	 * The setting and its gate have declared defaults.
	 */
	public function test_defaults_are_declared() {
		$defaults = Open_Accessibility_Utils::get_default_options();

		$this->assertArrayHasKey( 'saturation_level', $defaults );
		$this->assertSame( 0, $defaults['saturation_level'], 'Saturation should start off.' );

		$this->assertArrayHasKey( 'enable_saturation', $defaults );
		$this->assertSame( 0, $defaults['enable_saturation'], 'The control should ship disabled.' );
	}

	/**
	 * The level is a bounded integer, not a free value.
	 */
	public function test_level_is_accepted_within_range() {
		$admin = new Open_Accessibility_Admin();

		foreach ( array( 0, 1, 2, 3 ) as $level ) {
			$sanitized = $admin->sanitize_options( array( 'saturation_level' => (string) $level ) );
			$this->assertSame( $level, $sanitized['saturation_level'], "Level {$level} should be accepted." );
		}
	}

	/**
	 * Out-of-range numbers are clamped, not discarded.
	 *
	 * Clamping keeps a value from an older or newer form as close as possible
	 * rather than resetting the visitor's preference to normal colour.
	 */
	public function test_out_of_range_level_is_clamped() {
		$admin = new Open_Accessibility_Admin();
		$max   = Open_Accessibility_Utils::get_max_saturation_level();

		$over = $admin->sanitize_options( array( 'saturation_level' => (string) ( $max + 10 ) ) );
		$this->assertSame( $max, $over['saturation_level'], 'A level above the maximum should clamp to it.' );

		$under = $admin->sanitize_options( array( 'saturation_level' => '-5' ) );
		$this->assertSame( 0, $under['saturation_level'], 'A negative level should clamp to zero.' );
	}

	/**
	 * A non-numeric level falls back to normal colour.
	 */
	public function test_non_numeric_level_falls_back_to_zero() {
		$admin = new Open_Accessibility_Admin();

		foreach ( array( 'lots', '<script>', '' ) as $bad ) {
			$sanitized = $admin->sanitize_options( array( 'saturation_level' => $bad ) );
			$this->assertSame( 0, $sanitized['saturation_level'], "Level '{$bad}' should fall back to 0." );
		}
	}

	/**
	 * The enable toggle persists, and its absence persists as off.
	 */
	public function test_enable_toggle_persists() {
		$admin = new Open_Accessibility_Admin();

		$this->assertSame( 1, $admin->sanitize_options( array( 'enable_saturation' => '1' ) )['enable_saturation'] );
		$this->assertSame( 0, $admin->sanitize_options( array() )['enable_saturation'] );
	}

	/**
	 * Activation seeds both keys.
	 */
	public function test_activation_seeds_both_keys() {
		$this->activate_plugin();

		$stored = get_option( self::OPTION );

		$this->assertArrayHasKey( 'saturation_level', $stored );
		$this->assertArrayHasKey( 'enable_saturation', $stored );
	}

	/**
	 * The widget wrapper is excluded from the targeting layer.
	 *
	 * This is the guard for the whole feature. Saturation is a filter, and any
	 * filter applied to an element containing the widget would make the widget's
	 * position:fixed resolve against that element instead of the viewport —
	 * the defect fixed in 1.1.0 and again in 1.4.01. The exclusion is what keeps
	 * the widget out of the target set.
	 */
	public function test_widget_wrapper_is_excluded_from_targets() {
		$public = new Open_Accessibility_Public();

		// get_target_config() derives its exclusions *from* the typography
		// targets, so it has to be given the real ones; passing an empty array
		// produces an empty exclusion list and the assertion would fail for a
		// reason unrelated to the code under test.
		$targets = new ReflectionMethod( $public, 'get_typography_targets' );
		$targets->setAccessible( true );

		$method = new ReflectionMethod( $public, 'get_target_config' );
		$method->setAccessible( true );

		$config = $method->invoke( $public, $targets->invoke( $public ) );

		$this->assertArrayHasKey( 'excluded', $config );

		$this->assertContains(
			'.open-accessibility-widget-wrapper',
			$config['excluded'],
			'The widget must never be inside the filtered target set.'
		);
	}

	/**
	 * The level maximum is available to the frontend.
	 *
	 * The script needs the bound to clamp against; declaring it only in JS would
	 * be a second copy of a value the admin also enforces.
	 */
	public function test_maximum_level_is_shared_with_the_frontend() {
		$this->assertSame( 3, Open_Accessibility_Utils::get_max_saturation_level() );
	}
}

/**
 * Rendering and payload tests for the saturation control.
 *
 * @covers Open_Accessibility_Public::get_frontend_options
 */
class Test_Saturation_Frontend extends OA_TestCase {

	/**
	 * Enable the control and return the widget markup.
	 *
	 * @return string
	 */
	private function render_enabled_widget() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'enable_saturation' => 1 ) );
		wp_cache_flush();

		$public = new Open_Accessibility_Public();

		return $this->capture_output( array( $public, 'render_accessibility_widget' ) );
	}

	/**
	 * The localised payload, decoded.
	 *
	 * @return array
	 */
	private function payload() {
		$public = new Open_Accessibility_Public();

		$original              = wp_scripts();
		$scripts               = new WP_Scripts();
		$scripts->init();
		$GLOBALS['wp_scripts'] = $scripts;

		try {
			wp_register_script( 'open-accessibility', 'https://example.org/oa.js', array( 'jquery' ), '1', true );
			$public->enqueue_scripts();
			$raw = $scripts->get_data( 'open-accessibility', 'data' );
		} finally {
			$GLOBALS['wp_scripts'] = $original;
		}

		$this->assertIsString( $raw );

		$json = rtrim( trim( (string) preg_replace( '/^\s*var\s+open_accessibility_data\s*=\s*/', '', $raw ) ), "; \t\n\r\0\x0B" );
		$data = json_decode( $json, true );

		$this->assertIsArray( $data );

		return $data;
	}

	/**
	 * No control until the site enables it.
	 */
	public function test_control_is_absent_until_enabled() {
		$this->activate_plugin();

		$public = new Open_Accessibility_Public();
		$html   = $this->capture_output( array( $public, 'render_accessibility_widget' ) );

		$this->assertStringNotContainsString( 'data-action="saturation"', $html );
	}

	/**
	 * Enabling it renders a decrement, an indicator and an increment.
	 */
	public function test_control_renders_incremental_buttons_and_indicator() {
		$html = $this->render_enabled_widget();

		$this->assertStringContainsString( 'data-action="saturation" data-value="decrease"', $html );
		$this->assertStringContainsString( 'data-action="saturation" data-value="increase"', $html );

		// The indicator carries its own bound so the script and the markup agree.
		$this->assertSame(
			1,
			preg_match(
				'/data-action="saturation"\s+data-max="' . Open_Accessibility_Utils::get_max_saturation_level() . '"/',
				$html
			),
			'The saturation indicator should declare the maximum level.'
		);
	}

	/**
	 * The indicator is a live region, so level changes are announced.
	 */
	public function test_indicator_announces_level_changes() {
		$html = $this->render_enabled_widget();

		$this->assertSame(
			1,
			preg_match(
				'/data-action="saturation"[^>]*role="status"[^>]*aria-live="polite"/s',
				$html
			),
			'The saturation indicator should be a polite live region.'
		);
	}

	/**
	 * The payload carries the level, its bound and the toggle.
	 */
	public function test_payload_carries_the_saturation_settings() {
		$this->activate_plugin();

		$options = $this->payload()['options'];

		$this->assertArrayHasKey( 'saturation_level', $options );
		$this->assertArrayHasKey( 'max_saturation_level', $options );
		$this->assertArrayHasKey( 'enable_saturation', $options );

		$this->assertSame(
			Open_Accessibility_Utils::get_max_saturation_level(),
			$options['max_saturation_level'],
			'The frontend bound should come from the same place the sanitiser uses.'
		);
	}

	/**
	 * Saturation is not a preset field.
	 *
	 * No preset promises it, and including it would let a profile overwrite a
	 * visitor's chosen level.
	 */
	public function test_saturation_is_not_a_preset_field() {
		$this->assertNotContains( 'saturationLevel', Open_Accessibility_Utils::get_preset_field_names() );

		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$this->assertArrayNotHasKey( 'saturationLevel', $profile['state'], "Profile {$name} sets saturation." );
		}
	}
}
