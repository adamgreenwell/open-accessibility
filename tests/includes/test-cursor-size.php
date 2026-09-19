<?php
/**
 * Tests for the cursor size control.
 *
 * Cursor size is a choice rather than a toggle, so it follows the select-field
 * path (like icon_size) rather than the feature checkbox loop — but it still
 * needs an enable toggle to gate the widget control, matching every other
 * optional control.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Utils::get_default_options
 * @covers Open_Accessibility_Admin::sanitize_options
 */
class Test_Cursor_Size extends OA_TestCase {

	/**
	 * Valid cursor sizes.
	 *
	 * @var string[]
	 */
	const VALID = array( '', 'large', 'xlarge' );

	/**
	 * The setting has a declared default.
	 */
	public function test_cursor_size_has_a_declared_default() {
		$defaults = Open_Accessibility_Utils::get_default_options();

		$this->assertArrayHasKey( 'cursor_size', $defaults );
		$this->assertContains( $defaults['cursor_size'], self::VALID );
	}

	/**
	 * The enable toggle has a declared default of off.
	 *
	 * Optional controls ship off so existing installs do not change appearance
	 * without the site owner choosing it.
	 */
	public function test_cursor_size_is_disabled_by_default() {
		$defaults = Open_Accessibility_Utils::get_default_options();

		$this->assertArrayHasKey( 'enable_cursor_size', $defaults );
		$this->assertSame( 0, $defaults['enable_cursor_size'] );
	}

	/**
	 * A valid size survives sanitising.
	 */
	public function test_valid_cursor_size_is_accepted() {
		foreach ( array( 'large', 'xlarge', '' ) as $size ) {
			$admin     = new Open_Accessibility_Admin();
			$sanitized = $admin->sanitize_options( array( 'cursor_size' => $size ) );

			$this->assertSame( $size, $sanitized['cursor_size'], "Size '{$size}' should be accepted." );
		}
	}

	/**
	 * An unknown size is rejected rather than stored.
	 */
	public function test_invalid_cursor_size_is_rejected() {
		foreach ( array( 'gigantic', 'LARGE', '<script>', '1' ) as $size ) {
			$admin     = new Open_Accessibility_Admin();
			$sanitized = $admin->sanitize_options( array( 'cursor_size' => $size ) );

			$this->assertSame(
				'',
				$sanitized['cursor_size'],
				"Size '{$size}' should be discarded, not stored."
			);
		}
	}

	/**
	 * The enable toggle persists, and its absence persists as off.
	 */
	public function test_enable_toggle_persists() {
		$admin = new Open_Accessibility_Admin();

		$on = $admin->sanitize_options( array( 'enable_cursor_size' => '1' ) );
		$this->assertSame( 1, $on['enable_cursor_size'] );

		$off = $admin->sanitize_options( array() );
		$this->assertSame( 0, $off['enable_cursor_size'] );
	}

	/**
	 * Activation seeds both keys.
	 */
	public function test_activation_seeds_both_keys() {
		$this->activate_plugin();

		$stored = get_option( self::OPTION );

		$this->assertArrayHasKey( 'cursor_size', $stored );
		$this->assertArrayHasKey( 'enable_cursor_size', $stored );
	}

	/**
	 * The admin renders a select, not a checkbox, for the size.
	 */
	public function test_admin_renders_a_select_for_the_size() {
		$admin = new Open_Accessibility_Admin();

		$html = $this->capture_output(
			function () use ( $admin ) {
				$admin->select_field_callback(
					array(
						'id'      => 'cursor_size',
						'default' => '',
						'options' => array(
							''       => 'Default cursor',
							'large'  => 'Large cursor',
							'xlarge' => 'Extra large cursor',
						),
					)
				);
			}
		);

		$this->assertStringContainsString( '<select', $html );
		$this->assertStringContainsString( 'name="open_accessibility_options[cursor_size]"', $html );

		foreach ( self::VALID as $size ) {
			$this->assertStringContainsString( 'value="' . $size . '"', $html );
		}
	}
}

/**
 * Rendering and payload tests for the cursor size control.
 *
 * @covers Open_Accessibility_Public::get_frontend_options
 */
class Test_Cursor_Size_Frontend extends OA_TestCase {

	/**
	 * Render the widget.
	 *
	 * @return string
	 */
	private function render_widget() {
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
	 * No widget control until the site enables it.
	 */
	public function test_control_is_absent_until_enabled() {
		$this->activate_plugin();

		$this->assertStringNotContainsString( 'data-action="cursor-size"', $this->render_widget() );
	}

	/**
	 * Enabling it renders the size choices.
	 */
	public function test_control_renders_a_button_per_size() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'enable_cursor_size' => 1 ) );
		wp_cache_flush();

		$html = $this->render_widget();

		foreach ( Open_Accessibility_Utils::get_cursor_sizes() as $size ) {
			$this->assertStringContainsString(
				'data-value="' . $size . '"',
				$html,
				"Cursor size '{$size}' should render a button."
			);
		}
	}

	/**
	 * The blank size renders as a labelled choice, not an empty button.
	 *
	 * The blank value is how the setting is turned off, so it needs a visible
	 * label; an unlabelled button would be a dead end for the visitor.
	 */
	public function test_default_cursor_choice_has_a_label() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'enable_cursor_size' => 1 ) );
		wp_cache_flush();

		$html = $this->render_widget();

		$this->assertSame(
			1,
			preg_match( '/data-value=""[^>]*>\s*([^<\s][^<]*?)\s*</s', $html, $m ),
			'The blank cursor size should carry a visible label.'
		);

		$this->assertNotSame( '', trim( $m[1] ) );
	}

	/**
	 * Buttons render unpressed; the saved state decides the active one.
	 */
	public function test_buttons_render_unpressed() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'enable_cursor_size' => 1 ) );
		wp_cache_flush();

		$html = $this->render_widget();

		preg_match_all( '/data-action="cursor-size"[^>]*aria-pressed="([a-z]+)"/', $html, $matches );

		$this->assertNotEmpty( $matches[1] );

		foreach ( $matches[1] as $pressed ) {
			$this->assertSame( 'false', $pressed );
		}
	}

	/**
	 * The payload carries the setting, the whitelist and the toggle.
	 */
	public function test_payload_carries_the_cursor_settings() {
		$this->activate_plugin();

		$options = $this->payload()['options'];

		$this->assertArrayHasKey( 'cursor_size', $options );
		$this->assertArrayHasKey( 'cursor_sizes', $options );
		$this->assertArrayHasKey( 'enable_cursor_size', $options );

		$this->assertSame(
			Open_Accessibility_Utils::get_cursor_sizes(),
			$options['cursor_sizes'],
			'The whitelist should come from the same place the sanitiser uses.'
		);
	}

	/**
	 * Cursor size is not a preset field.
	 *
	 * It changes the pointer rather than page content, and no profile promises
	 * it. Keeping it out of the preset list is what stops a profile resetting a
	 * visitor's cursor choice.
	 */
	public function test_cursor_size_is_not_a_preset_field() {
		$this->assertNotContains(
			'cursorSize',
			Open_Accessibility_Utils::get_preset_field_names(),
			'Cursor size should not be reset by applying a profile.'
		);

		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$this->assertArrayNotHasKey(
				'cursorSize',
				$profile['state'],
				"Profile {$name} should not set a cursor size."
			);
		}
	}

	/**
	 * The configured size reaches the frontend as a starting point.
	 *
	 * The admin setting was in the payload but nothing read it for a visitor with
	 * no stored preference, so choosing Large or Extra Large appeared to do
	 * nothing. Two review findings came from that: the value was never applied,
	 * and applying a profile cleared it because the profile path replaces the
	 * whole state.
	 *
	 * Both are the same underlying requirement — state outside the presets must
	 * survive a preset being applied — so this asserts the payload contract the
	 * script needs to honour it.
	 */
	public function test_configured_size_is_distinct_from_the_visitor_choice() {
		$this->activate_plugin();

		update_option(
			self::OPTION,
			array(
				'cursor_size'        => 'xlarge',
				'enable_cursor_size' => 1,
			)
		);
		wp_cache_flush();

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

		$json = rtrim( trim( (string) preg_replace( '/^\s*var\s+open_accessibility_data\s*=\s*/', '', $raw ) ), "; \t\n\r\0\x0B" );
		$data = json_decode( $json, true );

		$this->assertSame(
			'xlarge',
			$data['options']['cursor_size'],
			'The configured size must reach the frontend for a visitor with no preference.'
		);

		$this->assertContains(
			'xlarge',
			$data['options']['cursor_sizes'],
			'The configured value must be one the whitelist accepts, or the script discards it.'
		);
	}

	/**
	 * No preset defines a cursor size, which is what makes carrying it necessary.
	 *
	 * If a preset ever did set one, the profile path would legitimately overwrite
	 * it and the carry-forward would be wrong. This keeps that assumption honest.
	 */
	public function test_no_preset_defines_a_cursor_size() {
		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$this->assertArrayNotHasKey(
				'cursorSize',
				$profile['state'],
				"Profile {$name} now sets a cursor size, so the frontend carry-forward needs revisiting."
			);
		}
	}
}
