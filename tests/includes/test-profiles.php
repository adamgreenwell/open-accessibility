<?php
/**
 * Tests for the accessibility profile registry.
 *
 * A profile is a named preset over the widget's accessibility state. The presets
 * live in PHP rather than JavaScript so they are testable, filterable, and
 * defined once; the frontend script only applies whatever it is handed.
 *
 * The most important property tested here is completeness. A partial preset
 * silently inherits whatever was active before it, so switching from one profile
 * to another would leave traces of the first behind — the kind of bug that looks
 * like "the profile is wrong" rather than "the preset was incomplete".
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Utils::get_profiles
 * @covers Open_Accessibility_Utils::get_profile
 * @covers Open_Accessibility_Utils::get_enabled_profiles
 */
class Test_Profiles extends OA_TestCase {

	/**
	 * Every state field a frontend profile can set.
	 *
	 * Mirrors DEFAULT_ACCESSIBILITY_STATE in the frontend script but is kept
	 * here rather than parsed from it: a preset that omits a field should fail
	 * against a fixed expectation, not against whatever the script happens to
	 * declare today.
	 *
	 * @var string[]
	 */
	const STATE_FIELDS = array(
		'active',
		'contrast',
		'grayscale',
		'textSize',
		'selectedFont',
		'linksUnderline',
		'hideImages',
		'readingGuide',
		'readingMask',
		'focusOutline',
		'lineHeightLevel',
		'textAlign',
		'pauseAnimations',
		'letterSpacingLevel',
		'wordSpacingLevel',
	);

	/**
	 * Read the values of a JS array constant from the frontend script.
	 *
	 * @param string $constant Constant name, e.g. VALID_CONTRAST_MODES.
	 * @return string[] Values, with quotes stripped.
	 */
	private function js_array_constant( $constant ) {
		$source = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'assets/js/open-accessibility-public.js' );

		$pattern = '/const\s+' . preg_quote( $constant, '/' ) . '\s*=\s*\[(.*?)\];/s';

		$this->assertSame(
			1,
			preg_match( $pattern, $source, $matches ),
			"Could not find {$constant} in the frontend script; the parser has drifted."
		);

		preg_match_all( "/'([^']*)'/", $matches[1], $values );

		return $values[1];
	}

	/**
	 * Read a numeric JS constant from the frontend script.
	 *
	 * @param string $constant Constant name.
	 * @return int
	 */
	private function js_int_constant( $constant ) {
		$source = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'assets/js/open-accessibility-public.js' );

		$pattern = '/const\s+' . preg_quote( $constant, '/' ) . '\s*=\s*(\d+);/';

		$this->assertSame(
			1,
			preg_match( $pattern, $source, $matches ),
			"Could not find {$constant} in the frontend script; the parser has drifted."
		);

		return (int) $matches[1];
	}

	/**
	 * The five profiles accessiBe ships are all present.
	 *
	 * The vocabulary is deliberate: these are the names users search for.
	 */
	public function test_registry_defines_the_expected_profiles() {
		$profiles = Open_Accessibility_Utils::get_profiles();

		$this->assertSame(
			array( 'seizure_safe', 'vision_impaired', 'adhd_friendly', 'blind', 'epilepsy_safe' ),
			array_keys( $profiles ),
			'The profile registry should define the five expected profiles, in a stable order.'
		);
	}

	/**
	 * Every profile carries a label, a description and option key metadata.
	 */
	public function test_every_profile_has_the_metadata_the_ui_needs() {
		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			foreach ( array( 'label', 'description', 'option', 'state' ) as $key ) {
				$this->assertArrayHasKey( $key, $profile, "Profile {$name} is missing '{$key}'." );
			}

			$this->assertNotSame( '', $profile['label'], "Profile {$name} has an empty label." );
			$this->assertNotSame( '', $profile['description'], "Profile {$name} has an empty description." );
			$this->assertSame(
				'enable_profile_' . $name,
				$profile['option'],
				"Profile {$name} should map to the option key matching its name."
			);
		}
	}

	/**
	 * Every preset sets every state field.
	 *
	 * This is the property that stops state leaking between profiles. Without it
	 * switching profiles would leave the previous one's settings in place for any
	 * field the new preset forgot.
	 */
	public function test_every_preset_sets_every_state_field() {
		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$missing = array_values( array_diff( self::STATE_FIELDS, array_keys( $profile['state'] ) ) );
			$extra   = array_values( array_diff( array_keys( $profile['state'] ), self::STATE_FIELDS ) );

			$this->assertSame(
				array(),
				$missing,
				"Profile {$name} does not set: " . implode( ', ', $missing ) . '. A partial preset leaks state.'
			);

			$this->assertSame(
				array(),
				$extra,
				"Profile {$name} sets fields the frontend state does not have: " . implode( ', ', $extra )
			);
		}
	}

	/**
	 * Preset values are accepted by the frontend's own validators.
	 *
	 * The presets are defined in PHP and applied by JavaScript, so an invalid
	 * value would be silently dropped by normalizeAccessibilityState() and the
	 * profile would appear to do nothing. The validators are read from the script
	 * so this test follows the real whitelists rather than a copy of them.
	 */
	public function test_preset_values_pass_the_frontend_validators() {
		$contrast_modes = $this->js_array_constant( 'VALID_CONTRAST_MODES' );
		$font_values    = $this->js_array_constant( 'VALID_FONT_VALUES' );
		$align_values   = $this->js_array_constant( 'VALID_TEXT_ALIGN_VALUES' );
		$max_text_size  = $this->js_int_constant( 'MAX_TEXT_SIZE' );
		$max_spacing    = $this->js_int_constant( 'MAX_SPACING_LEVEL' );

		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$state = $profile['state'];

			$this->assertContains(
				$state['contrast'],
				$contrast_modes,
				"Profile {$name} sets contrast='{$state['contrast']}', which the frontend rejects."
			);

			$this->assertContains(
				$state['selectedFont'],
				$font_values,
				"Profile {$name} sets selectedFont='{$state['selectedFont']}', which the frontend rejects."
			);

			$this->assertContains(
				$state['textAlign'],
				$align_values,
				"Profile {$name} sets textAlign='{$state['textAlign']}', which the frontend rejects."
			);

			$this->assertGreaterThanOrEqual( 0, $state['textSize'], "Profile {$name} has a negative textSize." );
			$this->assertLessThanOrEqual(
				$max_text_size,
				$state['textSize'],
				"Profile {$name} sets textSize={$state['textSize']}, above the frontend maximum of {$max_text_size}."
			);

			foreach ( array( 'lineHeightLevel', 'letterSpacingLevel', 'wordSpacingLevel' ) as $field ) {
				$this->assertGreaterThanOrEqual( 0, $state[ $field ], "Profile {$name} has a negative {$field}." );
				$this->assertLessThanOrEqual(
					$max_spacing,
					$state[ $field ],
					"Profile {$name} sets {$field}={$state[ $field ]}, above the frontend maximum of {$max_spacing}."
				);
			}

			foreach ( array( 'active', 'grayscale', 'linksUnderline', 'hideImages', 'readingGuide', 'readingMask', 'focusOutline', 'pauseAnimations' ) as $field ) {
				$this->assertIsBool( $state[ $field ], "Profile {$name} field {$field} should be a boolean." );
			}
		}
	}

	/**
	 * A named profile can be looked up, and an unknown name yields null.
	 */
	public function test_get_profile_resolves_by_name() {
		$this->assertIsArray( Open_Accessibility_Utils::get_profile( 'seizure_safe' ) );
		$this->assertNull( Open_Accessibility_Utils::get_profile( 'not_a_profile' ) );
		$this->assertNull( Open_Accessibility_Utils::get_profile( '' ) );
	}

	/**
	 * Profiles default to offered, so the feature is visible without setup.
	 *
	 * Existing installs have no saved value for these keys, which is why the
	 * defaults matter more here than for a feature shipped at 1.0.
	 */
	public function test_profiles_are_offered_when_nothing_is_saved() {
		delete_option( self::OPTION );

		$enabled = Open_Accessibility_Utils::get_enabled_profiles();

		$this->assertCount(
			5,
			$enabled,
			'All five profiles should be offered before anything is saved.'
		);
	}

	/**
	 * A profile switched off stops being offered.
	 */
	public function test_disabled_profile_is_not_offered() {
		update_option( self::OPTION, array( 'enable_profile_blind' => 0 ) );
		wp_cache_flush();

		$enabled = Open_Accessibility_Utils::get_enabled_profiles();

		$this->assertArrayNotHasKey( 'blind', $enabled );
		$this->assertCount( 4, $enabled );
	}

	/**
	 * Profile option keys are declared defaults.
	 *
	 * Without this the admin checkbox renders unchecked on first load whatever
	 * the intent, which is the drift the Phase 0 work exists to prevent.
	 */
	public function test_profile_option_keys_are_declared_defaults() {
		$defaults = Open_Accessibility_Utils::get_default_options();

		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$this->assertArrayHasKey(
				$profile['option'],
				$defaults,
				"Profile {$name} option '{$profile['option']}' is not a declared default."
			);
		}
	}

	/**
	 * Activation seeds the profile keys.
	 */
	public function test_activation_seeds_profile_options() {
		$this->activate_plugin();

		$stored = get_option( self::OPTION );

		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$this->assertArrayHasKey( $profile['option'], $stored, "Activation did not seed {$profile['option']}." );
		}
	}

	/**
	 * The registry is filterable, so themes can add or adjust profiles.
	 */
	public function test_registry_is_filterable() {
		add_filter(
			'open_accessibility_profiles',
			function ( $profiles ) {
				$profiles['custom'] = array(
					'label'       => 'Custom',
					'description' => 'Added by a theme.',
					'option'      => 'enable_profile_custom',
					'state'       => $profiles['blind']['state'],
				);
				return $profiles;
			}
		);

		$profiles = Open_Accessibility_Utils::get_profiles();

		$this->assertArrayHasKey( 'custom', $profiles );

		remove_all_filters( 'open_accessibility_profiles' );
	}
}
