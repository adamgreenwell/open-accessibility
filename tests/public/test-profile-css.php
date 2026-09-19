<?php
/**
 * Tests for the profile button styling.
 *
 * These assert structure in the stylesheet, not rendered pixels: PHPUnit cannot
 * see layout. What it can enforce are the properties that are easy to lose in a
 * later edit — that the active state is distinguishable by more than colour, and
 * that the indicator's active colour is not invisible against the panel.
 *
 * @package Open_Accessibility
 */

/**
 * @coversNothing
 */
class Test_Profile_Css extends OA_TestCase {

	/**
	 * Read the frontend stylesheet.
	 *
	 * @return string
	 */
	private function css() {
		$path = OPEN_ACCESSIBILITY_PLUGIN_DIR . 'assets/css/open-accessibility-public.css';

		$this->assertFileExists( $path );

		return file_get_contents( $path );
	}

	/**
	 * Extract a rule block by selector.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Selector to find.
	 * @return string The declarations, or '' when the selector is absent.
	 */
	private function rule( $css, $selector ) {
		$pattern = '/' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/';

		return preg_match( $pattern, $css, $m ) ? $m[1] : '';
	}

	/**
	 * The profile button group has its own styling.
	 */
	public function test_profile_buttons_are_styled() {
		$css = $this->css();

		$this->assertStringContainsString( '.open-accessibility-profile-button', $css );
		$this->assertStringContainsString( '.open-accessibility-profiles-section', $css );
	}

	/**
	 * The active state is not signalled by colour alone.
	 *
	 * Colour-only state fails WCAG 1.4.1 and is invisible to anyone who cannot
	 * distinguish the active fill from the inactive one — which includes users of
	 * the high contrast modes this plugin itself provides.
	 */
	public function test_active_action_button_is_not_colour_only() {
		$declarations = $this->rule( $this->css(), '.open-accessibility-action-button.active' );

		$this->assertNotSame( '', $declarations, 'The active action-button rule is missing.' );

		$colour_only = array( 'background-color', 'border-color', 'color' );

		// Strip comments first. The rule documents why it carries an inset ring,
		// and an earlier version of this test counted that prose as a
		// declaration — so the assertion passed even with the ring removed, which
		// is the opposite of what it exists to check.
		$properties_only = preg_replace( '#/\*.*?\*/#s', '', $declarations );

		$found_structural_cue = false;

		foreach ( explode( ';', $properties_only ) as $declaration ) {
			$property = trim( strtok( $declaration, ':' ) );

			if ( '' === $property || in_array( $property, $colour_only, true ) ) {
				continue;
			}

			$found_structural_cue = true;
		}

		$this->assertTrue(
			$found_structural_cue,
			'The active state relies only on background/colour. Add a non-colour cue such as an outline, border width or inset ring.'
		);
	}

	/**
	 * The indicator's active dot uses a token, not a hardcoded colour.
	 *
	 * It previously resolved to the border token, which is a dark grey in the dark
	 * panel theme — roughly 1.4:1 against the panel, so the active dot was
	 * effectively invisible.
	 */
	public function test_indicator_active_dot_contrasts_with_the_panel() {
		$declarations = $this->rule( $this->css(), '.open-accessibility-indicator-dot.active' );

		$this->assertNotSame( '', $declarations, 'The active indicator-dot rule is missing.' );

		// Strip comments first: this rule documents the token it moved away from,
		// and matching prose rather than declarations would fail on a correct
		// stylesheet.
		$properties = preg_replace( '#/\*.*?\*/#s', '', $declarations );

		$this->assertStringNotContainsString(
			'var(--oa-action-button-border',
			$properties,
			'The active dot should not reuse the action-button border token: it is a dark grey on the dark panel.'
		);

		$this->assertStringContainsString(
			'var(--oa-panel-text',
			$properties,
			'The active dot should use a token that contrasts with the panel in every theme.'
		);
	}

	/**
	 * Every rule the plugin adds for profiles lives inside its own namespace.
	 */
	public function test_profile_rules_are_namespaced() {
		$css = $this->css();

		preg_match_all( '/\.(open-accessibility-profile[a-z-]*)/', $css, $matches );

		$this->assertNotEmpty( $matches[1], 'No profile selectors found.' );

		foreach ( array_unique( $matches[1] ) as $selector ) {
			$this->assertStringStartsWith( 'open-accessibility-', $selector );
		}
	}

	/**
	 * Contrast modes re-point the panel tokens.
	 *
	 * Those modes repaint the panel with hardcoded colours rather than updating
	 * the tokens, so anything token-driven kept its light-mode value. The level
	 * indicators rendered #333 on a black panel — about 1.7:1 — in the modes
	 * meant to improve visibility.
	 */
	public function test_contrast_modes_override_the_panel_tokens() {
		$css = $this->css();

		foreach ( array( 'high-contrast', 'negative-contrast' ) as $mode ) {
			// A selector may appear in more than one block, so gather them all
			// rather than assuming the first one carries the declaration.
			$pattern = '/' . preg_quote( 'body.open-accessibility-' . $mode . ' .open-accessibility-widget-wrapper', '/' ) . '\s*\{([^}]*)\}/';

			preg_match_all( $pattern, $css, $matches );

			$this->assertNotEmpty( $matches[1], "No wrapper rule for {$mode}." );

			$found = false;

			foreach ( $matches[1] as $block ) {
				if ( false !== strpos( $block, '--oa-panel-text' ) ) {
					$found = true;
				}
			}

			$this->assertTrue(
				$found,
				"The {$mode} wrapper should re-point --oa-panel-text at the colour that mode paints."
			);
		}
	}
}
