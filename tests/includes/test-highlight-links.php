<?php
/**
 * Tests for the highlight links control.
 *
 * Distinct from the existing Links Underline toggle, which only adds
 * text-decoration. This adds a background and an underline so links stand out
 * from surrounding text, which is what the commercial widgets offer.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Utils::get_default_options
 * @covers Open_Accessibility_Admin::sanitize_options
 * @covers Open_Accessibility_Public::get_frontend_options
 */
class Test_Highlight_Links extends OA_TestCase {

	/**
	 * The toggle has a declared default.
	 */
	public function test_default_is_declared_and_off() {
		$defaults = Open_Accessibility_Utils::get_default_options();

		$this->assertArrayHasKey( 'enable_highlight_links', $defaults );
		$this->assertSame( 0, $defaults['enable_highlight_links'], 'The control should ship disabled.' );
	}

	/**
	 * The toggle persists, and its absence persists as off.
	 */
	public function test_toggle_persists() {
		$admin = new Open_Accessibility_Admin();

		$this->assertSame( 1, $admin->sanitize_options( array( 'enable_highlight_links' => '1' ) )['enable_highlight_links'] );
		$this->assertSame( 0, $admin->sanitize_options( array() )['enable_highlight_links'] );
	}

	/**
	 * Activation seeds the key.
	 */
	public function test_activation_seeds_the_key() {
		$this->activate_plugin();

		$this->assertArrayHasKey( 'enable_highlight_links', get_option( self::OPTION ) );
	}

	/**
	 * The localised payload, decoded.
	 *
	 * @return array
	 */
	private function payload() {
		$this->activate_plugin();

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
	 * The payload carries the toggle, so the script can gate the control.
	 */
	public function test_payload_carries_the_toggle() {
		$options = $this->payload()['options'];

		$this->assertArrayHasKey( 'enable_highlight_links', $options );
	}

	/**
	 * The control renders only when enabled.
	 */
	public function test_widget_control_is_gated_on_the_option() {
		$this->activate_plugin();

		$public = new Open_Accessibility_Public();

		$off = $this->capture_output( array( $public, 'render_accessibility_widget' ) );
		$this->assertStringNotContainsString( 'data-action="highlight-links"', $off );

		update_option( self::OPTION, array( 'enable_highlight_links' => 1 ) );
		wp_cache_flush();

		$on = $this->capture_output( array( $public, 'render_accessibility_widget' ) );

		$this->assertStringContainsString( 'data-action="highlight-links"', $on );
		$this->assertStringContainsString( 'data-value="toggle"', $on );
	}

	/**
	 * The control is a toggle, so it starts unpressed and announces itself.
	 */
	public function test_control_renders_as_an_unpressed_toggle() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'enable_highlight_links' => 1 ) );
		wp_cache_flush();

		$public = new Open_Accessibility_Public();
		$html   = $this->capture_output( array( $public, 'render_accessibility_widget' ) );

		$this->assertSame(
			1,
			preg_match( '/data-action="highlight-links"[^>]*aria-pressed="false"/s', $html ),
			'The highlight toggle should render unpressed with an explicit aria-pressed.'
		);
	}

	/**
	 * Highlighting is not a preset field.
	 *
	 * No preset promises it, and including it would let a profile overwrite a
	 * visitor's choice.
	 */
	public function test_highlight_links_is_not_a_preset_field() {
		$this->assertNotContains( 'highlightLinks', Open_Accessibility_Utils::get_preset_field_names() );

		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$this->assertArrayNotHasKey( 'highlightLinks', $profile['state'], "Profile {$name} sets highlightLinks." );
		}
	}

	/**
	 * The highlight styling is not colour-only, and not a fixed dark colour.
	 *
	 * The original design for this feature specified a black bottom border, which
	 * is invisible on the black background the high contrast modes paint. The
	 * stylesheet must not hardcode a dark border, and the styling must carry more
	 * than a colour change so it survives a user who cannot distinguish the
	 * highlight colour.
	 */
	public function test_highlight_styling_is_not_a_fixed_dark_border() {
		$css = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'assets/css/open-accessibility-public.css' );

		$this->assertStringContainsString( 'open-accessibility-highlight-links', $css, 'No highlight styling found.' );

		// Assert on declarations, not prose: comments describing the old value
		// would otherwise fail this.
		$stripped = preg_replace( '#/\*.*?\*/#s', '', $css );

		$this->assertStringNotContainsString(
			'border-bottom: 2px solid #000',
			$stripped,
			'The highlight should not use a fixed black border: it is invisible on the high contrast background.'
		);
	}
}
