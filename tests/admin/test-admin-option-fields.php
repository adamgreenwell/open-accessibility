<?php
/**
 * Tests for how the settings fields render stored options.
 *
 * checkbox_field_callback used to carry its own array_merge() over the defaults
 * to decide whether a toggle was checked. That workaround was removed when
 * get_options() became the single read path, so these tests pin the behaviour it
 * was protecting: a key that has never been saved must still render with its
 * declared default.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Admin::checkbox_field_callback
 * @covers Open_Accessibility_Admin::select_field_callback
 * @covers Open_Accessibility_Admin::text_field_callback
 */
class Test_Admin_Option_Fields extends OA_TestCase {

	/**
	 * Render a checkbox field and return its markup.
	 *
	 * @param string $id      Option key.
	 * @param string $label   Optional label.
	 * @return string
	 */
	private function render_checkbox( $id, $label = 'Test label' ) {
		$admin = new Open_Accessibility_Admin();

		return $this->capture_output(
			function () use ( $admin, $id, $label ) {
				$admin->checkbox_field_callback(
					array(
						'id'    => $id,
						'label' => $label,
					)
				);
			}
		);
	}

	/**
	 * Assert whether the rendered checkbox is checked.
	 *
	 * @param string $html    Rendered markup.
	 * @param bool   $checked Expected state.
	 */
	private function assertCheckboxState( $html, $checked ) {
		$has_checked = (bool) preg_match( '/\schecked(=|\s|>)/', $html );

		$this->assertSame(
			$checked,
			$has_checked,
			$checked
				? 'Expected the checkbox to be checked. Markup: ' . $html
				: 'Expected the checkbox to be unchecked. Markup: ' . $html
		);
	}

	/**
	 * Nothing saved: a default-on toggle renders checked.
	 *
	 * This is the regression the old inline merge existed to prevent.
	 */
	public function test_default_on_toggle_renders_checked_when_nothing_is_saved() {
		delete_option( self::OPTION );

		$html = $this->render_checkbox( 'enable_contrast' );

		$this->assertCheckboxState( $html, true );
	}

	/**
	 * Nothing saved: a default-off toggle renders unchecked.
	 */
	public function test_default_off_toggle_renders_unchecked_when_nothing_is_saved() {
		delete_option( self::OPTION );

		$html = $this->render_checkbox( 'enable_font_atkinson' );

		$this->assertCheckboxState( $html, false );
	}

	/**
	 * A stored 0 beats a default of 1.
	 */
	public function test_stored_zero_renders_unchecked() {
		update_option( self::OPTION, array( 'enable_contrast' => 0 ) );
		wp_cache_flush();

		$html = $this->render_checkbox( 'enable_contrast' );

		$this->assertCheckboxState( $html, false );
	}

	/**
	 * A stored 1 beats a default of 0.
	 */
	public function test_stored_one_renders_checked() {
		update_option( self::OPTION, array( 'enable_font_atkinson' => 1 ) );
		wp_cache_flush();

		$html = $this->render_checkbox( 'enable_font_atkinson' );

		$this->assertCheckboxState( $html, true );
	}

	/**
	 * A partially stored option still renders every other toggle correctly.
	 *
	 * This is the upgrade path: an install saved before a key existed must not
	 * render the newer key as unchecked just because it is absent.
	 */
	public function test_partially_stored_option_renders_other_keys_from_defaults() {
		update_option( self::OPTION, array( 'enable_contrast' => 0 ) );
		wp_cache_flush();

		$this->assertCheckboxState( $this->render_checkbox( 'enable_contrast' ), false );
		$this->assertCheckboxState( $this->render_checkbox( 'enable_reading_mask' ), true );
		$this->assertCheckboxState( $this->render_checkbox( 'enable_grayscale' ), true );
	}

	/**
	 * Every declared default renders as a control without notices.
	 *
	 * Catches a key that is read by a field callback but missing from the
	 * defaults, which would surface as a PHP notice and an unchecked box.
	 */
	public function test_every_default_renders_without_warnings() {
		delete_option( self::OPTION );

		$notices = array();
		set_error_handler(
			function ( $errno, $errstr ) use ( &$notices ) {
				$notices[] = $errstr;
				return true;
			}
		);

		try {
			foreach ( array_keys( Open_Accessibility_Utils::get_default_options() ) as $key ) {
				$this->render_checkbox( $key );
			}
		} finally {
			restore_error_handler();
		}

		$this->assertSame(
			array(),
			$notices,
			'Rendering a field for every declared key produced warnings: ' . implode( ' | ', $notices )
		);
	}

	/**
	 * The checkbox carries the option-array input name the form expects.
	 */
	public function test_checkbox_uses_the_option_array_input_name() {
		$html = $this->render_checkbox( 'enable_contrast' );

		$this->assertStringContainsString( 'name="open_accessibility_options[enable_contrast]"', $html );
	}

	/**
	 * A text field falls back to its declared default when nothing is stored.
	 */
	public function test_text_field_falls_back_to_its_default() {
		delete_option( self::OPTION );

		$admin = new Open_Accessibility_Admin();

		$html = $this->capture_output(
			function () use ( $admin ) {
				$admin->text_field_callback(
					array(
						'id'      => 'skip_to_element_id',
						'default' => 'content',
					)
				);
			}
		);

		$this->assertStringContainsString( 'value="content"', $html );
	}

	/**
	 * The panel title field reads through the merged option.
	 *
	 * widget_title is absent from an install saved before 1.4.0, so it must not
	 * emit a notice.
	 */
	public function test_panel_title_field_renders_without_a_stored_value() {
		delete_option( self::OPTION );

		$admin  = new Open_Accessibility_Admin();
		$notices = array();

		set_error_handler(
			function ( $errno, $errstr ) use ( &$notices ) {
				$notices[] = $errstr;
				return true;
			}
		);

		try {
			$html = $this->capture_output(
				function () use ( $admin ) {
					$admin->text_field_callback( array( 'id' => 'widget_title' ) );
				}
			);
		} finally {
			restore_error_handler();
		}

		$this->assertSame( array(), $notices );
		$this->assertStringContainsString( 'name="open_accessibility_options[widget_title]"', $html );
	}
}
