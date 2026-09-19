<?php
/**
 * Base test case for the Open Accessibility test suite.
 *
 * @package Open_Accessibility
 */

/**
 * Shared helpers for plugin tests.
 */
class OA_TestCase extends WP_UnitTestCase {

	/**
	 * Option name used by the plugin.
	 *
	 * @var string
	 */
	const OPTION = 'open_accessibility_options';

	/**
	 * Keys the plugin is allowed to persist, as of the current release.
	 *
	 * Kept here rather than derived from the plugin so that a test failure means
	 * "the plugin changed its contract", not "the test asked the plugin what to
	 * expect". Update deliberately when a release intentionally adds a key.
	 *
	 * @var string[]
	 */
	const KNOWN_OPTION_KEYS = array(
		// General.
		'disable_widget',
		'hide_on_mobile',
		'hide_on_desktop',
		// Design.
		'icon',
		'icon_size',
		'icon_color',
		'bg_color',
		'widget_title',
		// Position.
		'position',
		// Features.
		'enable_skip_to_content',
		'enable_contrast',
		'enable_grayscale',
		'enable_text_size',
		'enable_letter_spacing',
		'enable_word_spacing',
		'enable_font_atkinson',
		'enable_font_opendyslexic',
		'enable_links_underline',
		'enable_hide_images',
		'enable_reading_guide',
		'enable_reading_mask',
		'enable_focus_outline',
		'enable_line_height',
		'enable_text_align',
		'enable_animations_pause',
		// Links and statement.
		'sitemap_url',
		'statement_url',
		'help_url',
		'feedback_url',
		'skip_to_element_id',
		// Advanced.
		'strip_link_targets',
		'enable_analytics',
		'enable_debug',
	);

	/**
	 * Remove the plugin option before each test.
	 *
	 * The test library rolls back the database between tests, but the option is
	 * autoloaded and cached, so clear it explicitly to keep tests independent.
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( self::OPTION );
		delete_option( 'open_accessibility_db_version' );
		wp_cache_flush();
	}

	/**
	 * Simulate a fresh activation.
	 *
	 * Mirrors what WordPress does on plugin activation: run the registered
	 * activation callback against the current (empty) database.
	 */
	protected function activate_plugin() {
		open_accessibility_activate();
	}

	/**
	 * Capture the output of a callback.
	 *
	 * @param callable $callback Callback to run.
	 * @return string Captured output.
	 */
	protected function capture_output( callable $callback ) {
		ob_start();

		try {
			$callback();
		} finally {
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * Assert that a set of keys is present in an array.
	 *
	 * @param string[] $keys     Expected keys.
	 * @param array    $actual   Array to inspect.
	 * @param string   $message  Optional failure message.
	 */
	protected function assertArrayHasKeys( array $keys, array $actual, $message = '' ) {
		$missing = array_values( array_diff( $keys, array_keys( $actual ) ) );

		$this->assertSame(
			array(),
			$missing,
			$message ? $message : 'Missing expected keys: ' . implode( ', ', $missing )
		);
	}
}
