<?php
/**
 * Tests for the Profiles settings tab.
 *
 * The recurring failure this file guards against is a settings field that looks
 * right but never persists, because sanitize_options() rebuilds the option array
 * from scratch and discards anything it does not explicitly write.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Admin::register_settings
 * @covers Open_Accessibility_Admin::sanitize_options
 */
class Test_Admin_Profiles extends OA_TestCase {

	/**
	 * Render the default-profile select in isolation.
	 *
	 * Deliberately not the whole settings page. do_settings_fields() keeps
	 * process-global state, so the page renders once per PHP process and comes
	 * back empty afterwards — which makes any second test that renders it pass
	 * vacuously against ''. Rendering the field callback directly has no such
	 * coupling and asserts the same markup.
	 *
	 * @return string
	 */
	private function render_default_profile_select() {
		$admin = new Open_Accessibility_Admin();

		$choices = array( '' => __( 'No default', 'open-accessibility' ) );
		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$choices[ $name ] = $profile['label'];
		}

		return $this->capture_output(
			function () use ( $admin, $choices ) {
				$admin->select_field_callback(
					array(
						'id'      => 'default_profile',
						'default' => '',
						'options' => $choices,
					)
				);
			}
		);
	}

	/**
	 * Render one profile's toggle in isolation.
	 *
	 * @param string $option Option key.
	 * @return string
	 */
	private function render_profile_toggle( $option ) {
		$admin = new Open_Accessibility_Admin();

		return $this->capture_output(
			function () use ( $admin, $option ) {
				$admin->checkbox_field_callback(
					array(
						'id'    => $option,
						'label' => 'Toggle',
					)
				);
			}
		);
	}

	/**
	 * Run input through the registered sanitize callback.
	 *
	 * @param array $input Raw form input.
	 * @return array
	 */
	private function sanitize( array $input ) {
		$admin = new Open_Accessibility_Admin();

		return $admin->sanitize_options( $input );
	}

	/**
	 * The settings page template declares a Profiles tab wired to its section.
	 *
	 * Asserted against the template source rather than by rendering the page:
	 * do_settings_fields() renders once per process, so a rendered-page
	 * assertion in a suite passes vacuously wherever it runs second.
	 */
	public function test_settings_page_template_declares_the_profiles_tab() {
		$partial = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'admin/partials/admin-display.php' );

		$this->assertStringContainsString( 'href="#tab-profiles"', $partial, 'The Profiles nav anchor is missing.' );
		$this->assertStringContainsString( 'id="tab-profiles"', $partial, 'The Profiles tab container is missing.' );

		$this->assertSame(
			1,
			preg_match(
				"/do_settings_fields\(\s*'open-accessibility-settings'\s*,\s*'open_accessibility_profiles'\s*\)/",
				$partial
			),
			'The Profiles tab must render the open_accessibility_profiles section.'
		);
	}

	/**
	 * Every profile's toggle renders with the option-array input name.
	 */
	public function test_every_profile_toggle_renders() {
		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$html = $this->render_profile_toggle( $profile['option'] );

			$this->assertStringContainsString(
				'name="open_accessibility_options[' . $profile['option'] . ']"',
				$html,
				"Profile {$name} has no settings input."
			);
		}
	}

	/**
	 * The default-profile select offers every profile plus a blank choice.
	 */
	public function test_default_profile_select_offers_every_profile() {
		$html = $this->render_default_profile_select();

		$this->assertStringContainsString( 'name="open_accessibility_options[default_profile]"', $html );

		// The blank option matters: without it there is no way to turn the
		// default off once one has been chosen.
		$this->assertStringContainsString( 'value=""', $html, 'The select needs an explicit blank option.' );

		// The select is keyed by profile *name*, because that is what
		// default_profile stores; the option key is only how a profile is enabled.
		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$this->assertStringContainsString(
				'value="' . $name . '"',
				$html,
				"Profile '{$name}' is missing from the default-profile select."
			);
		}
	}

	/**
	 * Profile toggles survive a save.
	 *
	 * This is the failure mode worth testing hardest: a key missing from the
	 * checkbox whitelist is silently reset to 0 on every save, so the toggle
	 * appears to work and then quietly reverts.
	 */
	public function test_profile_toggles_persist_through_sanitize() {
		$input = array();

		foreach ( Open_Accessibility_Utils::get_profiles() as $profile ) {
			$input[ $profile['option'] ] = '1';
		}

		$sanitized = $this->sanitize( $input );

		foreach ( Open_Accessibility_Utils::get_profiles() as $name => $profile ) {
			$this->assertArrayHasKey(
				$profile['option'],
				$sanitized,
				"Profile {$name} was dropped by sanitize_options()."
			);

			$this->assertSame(
				1,
				$sanitized[ $profile['option'] ],
				"Profile {$name} did not persist as enabled."
			);
		}
	}

	/**
	 * An unchecked profile persists as off, not as missing.
	 */
	public function test_unchecked_profile_persists_as_zero() {
		$sanitized = $this->sanitize( array() );

		foreach ( Open_Accessibility_Utils::get_profiles() as $profile ) {
			$this->assertArrayHasKey( $profile['option'], $sanitized );
			$this->assertSame( 0, $sanitized[ $profile['option'] ] );
		}
	}

	/**
	 * A valid default profile is kept.
	 */
	public function test_valid_default_profile_is_accepted() {
		$sanitized = $this->sanitize( array( 'default_profile' => 'vision_impaired' ) );

		$this->assertSame( 'vision_impaired', $sanitized['default_profile'] );
	}

	/**
	 * An unknown default profile is discarded.
	 *
	 * A renamed or removed profile must not leave the option pointing at a preset
	 * that no longer exists, which would leave visitors with no default applied
	 * and no explanation.
	 */
	public function test_unknown_default_profile_is_rejected() {
		foreach ( array( 'not_a_profile', 'seizure_safe_typo', '<script>', '' ) as $candidate ) {
			$sanitized = $this->sanitize( array( 'default_profile' => $candidate ) );

			$this->assertSame(
				'',
				$sanitized['default_profile'],
				"Default profile '{$candidate}' should not be stored."
			);
		}
	}

	/**
	 * The blank default is accepted, so the feature can be turned off.
	 */
	public function test_blank_default_profile_is_accepted() {
		$sanitized = $this->sanitize( array( 'default_profile' => '' ) );

		$this->assertSame( '', $sanitized['default_profile'] );
	}

	/**
	 * The select marks the stored profile as selected.
	 */
	public function test_select_reflects_the_stored_default_profile() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'default_profile' => 'adhd_friendly' ) );
		wp_cache_flush();

		$html = $this->render_default_profile_select();

		$this->assertSame(
			1,
			preg_match( '/<option value="adhd_friendly"[^>]*selected/', $html ),
			'The stored default profile should render as selected.'
		);

		$this->assertSame(
			0,
			preg_match( '/<option value=""[^>]*selected/', $html ),
			'The blank option should not be selected when a profile is stored.'
		);
	}

	/**
	 * Profile checkboxes reflect stored values.
	 */
	public function test_profile_checkboxes_reflect_stored_values() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'enable_profile_blind' => 0 ) );
		wp_cache_flush();

		$blind = $this->render_profile_toggle( 'enable_profile_blind' );
		$this->assertStringNotContainsString( 'checked', $blind, 'A profile stored as off should render unchecked.' );

		$default_on = $this->render_profile_toggle( 'enable_profile_seizure_safe' );
		$this->assertStringContainsString( 'checked', $default_on, 'A profile left at its default should render checked.' );
	}
}
