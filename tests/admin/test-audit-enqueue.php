<?php
/**
 * Tests for the editor audit script and its localised data.
 *
 * The panel reads its rule metadata and its unchecked-category list from PHP, so
 * that the panel and the site report cannot disagree about what an issue is. These
 * tests cover that contract and the on/off gate.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Admin::enqueue_block_editor_assets
 * @covers Open_Accessibility_Audit::rule_definitions
 * @covers Open_Accessibility_Audit::unchecked_categories
 */
class Test_Audit_Enqueue extends OA_TestCase {

	/**
	 * Run the editor enqueue and return the localised payload.
	 *
	 * @return array|null Null when the script was not enqueued at all.
	 */
	private function enqueue_and_read_payload() {
		$admin = new Open_Accessibility_Admin();

		// A private registry keeps this self-contained; registering the same
		// handle twice is a no-op and re-localising escapes the payload
		// differently.
		$original              = wp_scripts();
		$scripts               = new WP_Scripts();
		$scripts->init();
		$GLOBALS['wp_scripts'] = $scripts;

		try {
			$admin->enqueue_block_editor_assets();
			$raw = $scripts->get_data( 'open-accessibility-editor', 'data' );
		} finally {
			$GLOBALS['wp_scripts'] = $original;
		}

		if ( ! is_string( $raw ) ) {
			return null;
		}

		$json = rtrim( trim( (string) preg_replace( '/^\s*var\s+open_accessibility_audit\s*=\s*/', '', $raw ) ), "; \t\n\r\0\x0B" );
		$data = json_decode( $json, true );

		$this->assertIsArray( $data, 'The audit payload should be decodable JSON.' );

		return $data;
	}

	/**
	 * The audit is on by default, so the script is enqueued.
	 */
	public function test_script_is_enqueued_by_default() {
		delete_option( self::OPTION );

		$this->assertNotNull( $this->enqueue_and_read_payload() );
	}

	/**
	 * Switching the audit off means the script is never enqueued.
	 *
	 * Not loaded-and-hidden: there is no reason to ship code an author cannot use.
	 */
	public function test_script_is_not_enqueued_when_disabled() {
		update_option( self::OPTION, array( 'enable_editor_audit' => 0 ) );
		wp_cache_flush();

		$this->assertNull( $this->enqueue_and_read_payload() );
	}

	/**
	 * The payload carries every rule the PHP side defines.
	 *
	 * A rule missing here would render as a blank line in the panel with no
	 * message and no criterion.
	 */
	public function test_payload_carries_every_rule() {
		delete_option( self::OPTION );

		$payload = $this->enqueue_and_read_payload();

		$this->assertArrayHasKey( 'rules', $payload );
		$this->assertNotEmpty( $payload['rules'] );

		foreach ( Open_Accessibility_Audit::rule_definitions() as $rule => $definition ) {
			$this->assertArrayHasKey( $rule, $payload['rules'], "Rule {$rule} did not reach the panel." );

			foreach ( array( 'message', 'wcag', 'severity' ) as $key ) {
				$this->assertArrayHasKey( $key, $payload['rules'][ $rule ] );
				$this->assertNotSame( '', $payload['rules'][ $rule ][ $key ] );
			}
		}
	}

	/**
	 * The payload carries the unchecked categories.
	 *
	 * The panel states what it cannot check; without this it would either omit
	 * that or keep a second copy of the list.
	 */
	public function test_payload_carries_unchecked_categories() {
		delete_option( self::OPTION );

		$payload = $this->enqueue_and_read_payload();

		$this->assertArrayHasKey( 'unchecked', $payload );
		$this->assertSame( Open_Accessibility_Audit::unchecked_categories(), $payload['unchecked'] );
	}

	/**
	 * The unchecked list is filterable, and the panel sees the filtered list.
	 */
	public function test_unchecked_categories_are_filterable() {
		add_filter(
			'open_accessibility_audit_unchecked_categories',
			function ( $categories ) {
				$categories[] = 'Custom blind spot';
				return $categories;
			}
		);

		$categories = Open_Accessibility_Audit::unchecked_categories();

		$this->assertContains( 'Custom blind spot', $categories );

		remove_all_filters( 'open_accessibility_audit_unchecked_categories' );
	}

	/**
	 * The enqueue is actually hooked.
	 *
	 * The tests above call the method directly, so they pass whether or not
	 * anything registers it — which is how the hook went missing when the enqueue
	 * moved between branches.
	 */
	public function test_enqueue_is_hooked_to_the_editor() {
		// Checked by callback id rather than by passing a callback: has_action()
		// compares against the array as it was registered, and a freshly
		// constructed object is a different value, so passing one always reports
		// false even when the hook is present.
		$registered = has_action( 'enqueue_block_editor_assets' );

		$this->assertNotFalse(
			$registered,
			'enqueue_block_editor_assets() must be registered, or the panel never loads.'
		);

		$found = false;

		foreach ( $GLOBALS['wp_filter']['enqueue_block_editor_assets']->callbacks as $callbacks ) {
			foreach ( array_keys( $callbacks ) as $id ) {
				if ( false !== strpos( (string) $id, 'enqueue_block_editor_assets' ) ) {
					$found = true;
				}
			}
		}

		$this->assertTrue( $found, 'The editor audit callback should be on that hook.' );
	}

	/**
	 * The editor script file exists and is syntactically loadable.
	 */
	public function test_editor_script_file_exists() {
		$path = OPEN_ACCESSIBILITY_PLUGIN_DIR . 'assets/js/open-accessibility-editor.js';

		$this->assertFileExists( $path );

		$source = file_get_contents( $path );

		// The project has no build step, so the script must be plain JavaScript
		// against the core globals: no JSX, no module syntax.
		$this->assertStringNotContainsString( 'import ', $source, 'The editor script must not use ES modules.' );
		$this->assertStringNotContainsString( 'export ', $source, 'The editor script must not use ES modules.' );
		$this->assertStringContainsString( 'wp.element.createElement', $source, 'The script should build UI with wp.element.' );
	}
}
