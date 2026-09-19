<?php
/**
 * Tests for the widget string override path.
 *
 * README documents an `open_accessibility_strings` filter that lets a site
 * relabel any widget control. It could not work: the widget template hardcoded
 * 54 translation calls, so the filter never reached rendered output, and the
 * i18n array localised to JavaScript was referenced zero times.
 *
 * These tests assert against rendered HTML rather than against the internals,
 * because "the filter is applied" is not the contract — "the filter changes what
 * the visitor sees" is.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Public::get_strings
 */
class Test_Widget_Strings extends OA_TestCase {

	/**
	 * Filters added during a test, so they can be removed afterwards.
	 *
	 * The WordPress test case does not roll back add_filter() calls, and an
	 * anonymous closure cannot be found again by name. Storing the callable lets
	 * each test leave the filter registry exactly as it found it.
	 *
	 * @var array[]
	 */
	private $added_filters = array();

	/**
	 * Reset per-request static state before each test.
	 *
	 * Open_Accessibility_Shortcode caches "the shortcode already rendered" in a
	 * static property so the footer widget can suppress itself on that page. It
	 * is scoped to a request, not to a test, so rendering the shortcode in one
	 * test would make the footer widget refuse to render in the next.
	 */
	public function setUp(): void {
		parent::setUp();

		$property = new ReflectionProperty( 'Open_Accessibility_Shortcode', 'shortcode_rendered' );
		$property->setAccessible( true );
		$property->setValue( null, false );
	}

	/**
	 * Remove any filters this test registered.
	 */
	public function tearDown(): void {
		foreach ( $this->added_filters as $filter ) {
			remove_filter( $filter['hook'], $filter['callback'], $filter['priority'] );
		}

		$this->added_filters = array();

		parent::tearDown();
	}

	/**
	 * Register a filter and remember it for cleanup.
	 *
	 * @param string   $hook     Filter name.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 */
	private function add_tracked_filter( $hook, $callback, $priority = 10 ) {
		add_filter( $hook, $callback, $priority );

		$this->added_filters[] = array(
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		);
	}

	/**
	 * Enable every optional widget control.
	 *
	 * Several features ship off by default — letter spacing, word spacing, and
	 * both readable fonts — and their template sections are wrapped in an
	 * enabled check. A test that filters their labels without enabling them is
	 * asserting against markup that was never rendered, which is how these tests
	 * passed vacuously the first time they were written.
	 */
	private function activate_all_optional_controls() {
		$this->activate_plugin();

		update_option(
			self::OPTION,
			array(
				'enable_letter_spacing'      => 1,
				'enable_word_spacing'        => 1,
				'enable_font_atkinson'       => 1,
				'enable_font_opendyslexic'   => 1,
			)
		);
		wp_cache_flush();
	}

	/**
	 * Render the widget and return its markup.
	 *
	 * @return string
	 */
	private function render_widget() {
		$this->activate_plugin();

		$public = new Open_Accessibility_Public();

		return $this->capture_output( array( $public, 'render_accessibility_widget' ) );
	}

	/**
	 * Keys the widget template reads out of the strings array.
	 *
	 * Parsed from the template so the assertion follows the code rather than a
	 * hand-maintained list, which is how the original drift went unnoticed.
	 *
	 * @return string[]
	 */
	private function keys_used_by_template() {
		$source = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'public/partials/widget-template.php' );

		// Matches both $oa_strings['key'] and $oa_strings['group']['key'].
		preg_match_all( "/\\\$oa_strings\[(?:'([a-z_]+)'|\s*'([a-z_]+)'\s*\]\s*\[\s*'([a-z_]+)')/", $source, $matches, PREG_SET_ORDER );

		$keys = array();
		foreach ( $matches as $match ) {
			if ( ! empty( $match[1] ) ) {
				$keys[] = $match[1];
			} elseif ( ! empty( $match[3] ) ) {
				$keys[] = $match[3];
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Every key the template reads exists in the strings array.
	 *
	 * A missing key renders as a PHP notice and an empty label, so this is the
	 * failure mode worth catching early.
	 */
	public function test_every_template_key_exists_in_the_strings_array() {
		$this->activate_plugin();

		$strings = ( new Open_Accessibility_Public() )->get_strings();
		$used    = $this->keys_used_by_template();

		$this->assertNotEmpty( $used, 'No $oa_strings keys found; the parser has drifted from the template.' );

		$missing = array();
		foreach ( $used as $key ) {
			if ( ! array_key_exists( $key, $strings ) ) {
				$missing[] = $key;
			}
		}

		$this->assertSame(
			array(),
			$missing,
			'The widget template reads strings that get_strings() does not define: ' . implode( ', ', $missing )
		);
	}

	/**
	 * The template has no hardcoded translation calls left.
	 *
	 * Any direct esc_html_e()/esc_attr_e() in the template bypasses the filter,
	 * which is the bug this issue is about.
	 */
	public function test_template_has_no_hardcoded_translation_calls() {
		$source = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'public/partials/widget-template.php' );

		// Strip comments first: the template documents this rule in prose.
		$code = preg_replace( '#(//[^\n]*|/\*.*?\*/)#s', '', $source );

		$count = preg_match_all( '/esc_(html|attr)_e\s*\(/', $code );

		$this->assertSame(
			0,
			$count,
			'widget-template.php should read labels from $oa_strings, not translate inline.'
		);
	}

	/**
	 * The filter changes what the visitor actually sees.
	 *
	 * This is the end-to-end assertion: a filter callback must alter rendered
	 * widget markup.
	 */
	public function test_strings_filter_reaches_rendered_output() {
		$this->add_tracked_filter(
			'open_accessibility_strings',
			function ( $strings ) {
				$strings['reset_title']     = 'Sentinel Reset Heading';
				$strings['grayscale_text']  = 'Sentinel Grayscale';
				$strings['toggle_open']     = 'Sentinel Toggle Label';
				return $strings;
			}
		);

		$html = $this->render_widget();

		$this->assertStringContainsString( 'Sentinel Reset Heading', $html );
		$this->assertStringContainsString( 'Sentinel Grayscale', $html );
		$this->assertStringContainsString( 'Sentinel Toggle Label', $html );

		// And the original defaults are gone from the rendered markup.
		$this->assertStringNotContainsString( 'Reset Settings', $html );
	}

	/**
	 * A filtered string is escaped on output.
	 *
	 * The filter is a public API, so its output is untrusted input.
	 */
	public function test_filtered_strings_are_escaped() {
		$this->add_tracked_filter(
			'open_accessibility_strings',
			function ( $strings ) {
				$strings['reset_title'] = '<script>alert(1)</script>';
				return $strings;
			}
		);

		$html = $this->render_widget();

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * A filtered string used inside an attribute is attribute-escaped.
	 */
	public function test_filtered_attribute_strings_are_escaped() {
		$this->add_tracked_filter(
			'open_accessibility_strings',
			function ( $strings ) {
				$strings['toggle_open'] = '" onmouseover="alert(1)';
				return $strings;
			}
		);

		$html = $this->render_widget();

		$this->assertStringNotContainsString( '" onmouseover="alert(1)', $html );
	}

	/**
	 * The panel title filter still works alongside the strings filter.
	 */
	public function test_panel_title_filter_is_still_applied() {
		$this->add_tracked_filter(
			'open_accessibility_panel_title',
			function () {
				return 'Sentinel Panel Title';
			}
		);

		$html = $this->render_widget();

		$this->assertStringContainsString( 'Sentinel Panel Title', $html );
	}

	/**
	 * The saved panel title setting still beats the default.
	 */
	public function test_saved_widget_title_overrides_the_default_label() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'widget_title' => 'Saved Panel Heading' ) );
		wp_cache_flush();

		$html = $this->render_widget();

		$this->assertStringContainsString( 'Saved Panel Heading', $html );
	}

	/**
	 * The shortcode embed renders the same filtered strings as the footer widget.
	 *
	 * The shortcode includes the same template from a different path, which is
	 * exactly where an override can silently stop working.
	 */
	public function test_shortcode_embed_honours_the_strings_filter() {
		$this->activate_plugin();

		$this->add_tracked_filter(
			'open_accessibility_strings',
			function ( $strings ) {
				$strings['reset_title'] = 'Sentinel Reset Heading';
				return $strings;
			}
		);

		$html = $this->capture_output(
			function () {
				echo do_shortcode( '[open_accessibility]' );
			}
		);

		$this->assertStringContainsString( 'Sentinel Reset Heading', $html );
	}

	/**
	 * The widget still renders its controls when no filter is registered.
	 */
	public function test_widget_renders_normally_without_a_filter() {
		$html = $this->render_widget();

		$this->assertStringContainsString( 'open-accessibility-toggle-button', $html );
		$this->assertStringContainsString( 'open-accessibility-widget-panel', $html );
		$this->assertStringContainsString( 'Reset Settings', $html );
	}

	/**
	 * Section headings honour their own `*_title` key.
	 *
	 * get_strings() exposes a title key per section. The template rendered these
	 * headings from the matching `*_text` key, which left every `*_title` filter
	 * with no visible effect: the keys existed and did nothing.
	 *
	 * @dataProvider provide_section_title_keys
	 *
	 * @param string $key Key that should control a heading.
	 */
	public function test_section_headings_use_their_title_key( $key ) {
		$this->activate_all_optional_controls();

		$sentinel = 'Sentinel ' . $key;

		$this->add_tracked_filter(
			'open_accessibility_strings',
			function ( $strings ) use ( $key, $sentinel ) {
				$strings[ $key ] = $sentinel;
				return $strings;
			}
		);

		$html = $this->render_widget();

		$this->assertStringContainsString(
			$sentinel,
			$html,
			"Filtering {$key} should change what the widget renders."
		);
	}

	/**
	 * Title keys that must drive a rendered heading.
	 *
	 * @return array[]
	 */
	public function provide_section_title_keys() {
		return array(
			'reset'            => array( 'reset_title' ),
			'grayscale'        => array( 'grayscale_title' ),
			'links underline'  => array( 'links_underline_title' ),
			'hide images'      => array( 'hide_images_title' ),
			'reading guide'    => array( 'reading_guide_title' ),
			'reading mask'     => array( 'reading_mask_title' ),
			'focus outline'    => array( 'focus_outline_title' ),
			'pause animations' => array( 'pause_animations_title' ),
			'line height'      => array( 'line_height_title' ),
			'letter spacing'   => array( 'letter_spacing_title' ),
			'word spacing'     => array( 'word_spacing_title' ),
			'text align'       => array( 'text_align_title' ),
			'contrast'         => array( 'contrast_title' ),
			'text size'        => array( 'text_size_title' ),
			'readable font'    => array( 'readable_font_title' ),
		);
	}

	/**
	 * The incremental controls keep their own directional keys.
	 *
	 * These must be relabellable independently: a site that wants "Smaller" for
	 * text size must not thereby relabel the spacing controls as well.
	 *
	 * @dataProvider provide_directional_keys
	 *
	 * @param string $key Key that should control a button label.
	 */
	public function test_directional_buttons_use_their_own_keys( $key ) {
		$this->activate_all_optional_controls();

		$sentinel = 'Sentinel ' . $key;

		$this->add_tracked_filter(
			'open_accessibility_strings',
			function ( $strings ) use ( $key, $sentinel ) {
				$strings[ $key ] = $sentinel;
				return $strings;
			}
		);

		$html = $this->render_widget();

		$this->assertStringContainsString(
			$sentinel,
			$html,
			"Filtering {$key} should change a rendered button label."
		);
	}

	/**
	 * Directional keys that must reach a rendered control.
	 *
	 * @return array[]
	 */
	public function provide_directional_keys() {
		return array(
			'text size decrease'      => array( 'text_size_decrease' ),
			'text size increase'      => array( 'text_size_increase' ),
			'letter spacing decrease' => array( 'letter_spacing_decrease' ),
			'letter spacing increase' => array( 'letter_spacing_increase' ),
			'word spacing decrease'   => array( 'word_spacing_decrease' ),
			'word spacing increase'   => array( 'word_spacing_increase' ),
			'line height decrease'    => array( 'line_height_decrease' ),
			'line height increase'    => array( 'line_height_increase' ),
		);
	}

	/**
	 * Configured panel links honour their label keys.
	 *
	 * get_strings() has always exposed statement_text, sitemap_text, help_text
	 * and feedback_text, but the panel links were built from direct __() calls,
	 * so those keys could not change the rendered link text.
	 *
	 * @dataProvider provide_link_keys
	 *
	 * @param string $key    Label key.
	 * @param string $option Option that must be set for the link to render.
	 */
	public function test_panel_links_use_their_label_keys( $key, $option ) {
		$this->activate_plugin();

		update_option( self::OPTION, array( $option => 'https://example.org/target' ) );
		wp_cache_flush();

		$sentinel = 'Sentinel ' . $key;

		$this->add_tracked_filter(
			'open_accessibility_strings',
			function ( $strings ) use ( $key, $sentinel ) {
				$strings[ $key ] = $sentinel;
				return $strings;
			}
		);

		$html = $this->render_widget();

		$this->assertStringContainsString(
			$sentinel,
			$html,
			"Filtering {$key} should change the rendered panel link."
		);
	}

	/**
	 * Link keys and the option that makes each one render.
	 *
	 * @return array[]
	 */
	public function provide_link_keys() {
		return array(
			'statement' => array( 'statement_text', 'statement_url' ),
			'sitemap'   => array( 'sitemap_text', 'sitemap_url' ),
			'help'      => array( 'help_text', 'help_url' ),
			'feedback'  => array( 'feedback_text', 'feedback_url' ),
		);
	}

	/**
	 * Level labels reach the script that overwrites them.
	 *
	 * The template's aria-label is discarded as soon as the frontend script
	 * initialises, because updateIndicator() rewrites it in English. For a
	 * filtered level label to reach assistive technology it must also be present
	 * in the data localised to that script.
	 */
	public function test_level_labels_are_localised_for_the_frontend_script() {
		$this->activate_plugin();

		$data = $this->frontend_payload();

		foreach ( array( 'text_size_level', 'letter_spacing_level', 'word_spacing_level', 'line_height_level' ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$data['i18n'],
				"{$key} must reach the frontend script or the filtered label is overwritten with English."
			);
		}
	}

	/**
	 * The data localised to the frontend script, decoded.
	 *
	 * @return array
	 */
	private function frontend_payload() {
		$this->activate_plugin();

		$public = new Open_Accessibility_Public();

		// A private registry keeps this helper self-contained: registering the
		// same handle twice is a no-op and WordPress escapes the payload
		// differently on a repeat pass.
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

		$this->assertIsString( $raw, 'The public script should localise data.' );

		$json = trim( (string) preg_replace( '/^\s*var\s+open_accessibility_data\s*=\s*/', '', $raw ) );
		$json = rtrim( $json, "; \t\n\r\0\x0B" );
		$data = json_decode( $json, true );

		$this->assertIsArray( $data, 'The localised payload should be decodable JSON.' );

		return $data;
	}

	/**
	 * The frontend payload carries the profiles the script has to apply.
	 */
	public function test_frontend_payload_carries_profiles() {
		$data = $this->frontend_payload();

		$this->assertArrayHasKey( 'profiles', $data['options'], 'The script cannot apply profiles it was not given.' );
		$this->assertArrayHasKey( 'default_profile', $data['options'] );

		$profiles = $data['options']['profiles'];

		$this->assertCount( 5, $profiles, 'All five default profiles should reach the script.' );

		foreach ( $profiles as $name => $profile ) {
			$this->assertArrayHasKey( 'label', $profile, "Profile {$name} reached the script without a label." );
			$this->assertArrayHasKey( 'state', $profile, "Profile {$name} reached the script without a state." );
			$this->assertNotEmpty( $profile['state'], "Profile {$name} reached the script with an empty state." );
		}
	}

	/**
	 * A disabled profile is withheld from the payload, not hidden in the markup.
	 *
	 * The registry is the single source of truth for which profiles exist, so
	 * disabling one should remove it from the data rather than leave the script
	 * holding a preset it must remember not to apply.
	 */
	public function test_disabled_profile_is_withheld_from_the_payload() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'enable_profile_blind' => 0 ) );
		wp_cache_flush();

		$data = $this->frontend_payload();

		$this->assertArrayNotHasKey( 'blind', $data['options']['profiles'] );
		$this->assertCount( 4, $data['options']['profiles'] );
	}

	/**
	 * The profile section renders one button per enabled profile.
	 */
	public function test_profile_section_renders_a_button_per_enabled_profile() {
		$this->activate_plugin();

		$html     = $this->render_widget();
		$profiles = Open_Accessibility_Utils::get_profiles();

		$this->assertStringContainsString( 'data-action="profile"', $html, 'The profile section did not render.' );

		foreach ( $profiles as $name => $profile ) {
			$this->assertStringContainsString(
				'data-value="' . $name . '"',
				$html,
				"Profile {$name} should render a button."
			);

			// The label sits on its own line inside the button, so match it
			// between tags rather than assuming it abuts them.
			$this->assertSame(
				1,
				preg_match( '/>\s*' . preg_quote( $profile['label'], '/' ) . '\s*</', $html ),
				"Profile {$name} should render its registry label."
			);
		}
	}

	/**
	 * A disabled profile renders nothing.
	 */
	public function test_disabled_profile_renders_no_button() {
		$this->activate_plugin();

		update_option( self::OPTION, array( 'enable_profile_blind' => 0 ) );
		wp_cache_flush();

		$html = $this->render_widget();

		$this->assertStringNotContainsString( 'data-value="blind"', $html );
		$this->assertStringContainsString( 'data-value="seizure_safe"', $html );
	}

	/**
	 * Profile buttons start unpressed.
	 *
	 * The visitor's saved state decides which is active, and the script applies
	 * that on load; server-rendered markup must not claim one is active.
	 */
	public function test_profile_buttons_render_unpressed() {
		$this->activate_plugin();

		$html = $this->render_widget();

		// One button per enabled profile, and none claiming to be pressed.
		$button_count = substr_count( $html, 'data-action="profile"' );

		$this->assertGreaterThan( 0, $button_count, 'No profile buttons rendered.' );

		preg_match_all( '/data-action="profile"[^>]*aria-pressed="([a-z]+)"/', $html, $matches );

		$this->assertCount(
			$button_count,
			$matches[1],
			'Every profile button should carry an explicit aria-pressed state.'
		);

		foreach ( $matches[1] as $pressed ) {
			$this->assertSame( 'false', $pressed, 'Profile buttons should render unpressed.' );
		}
	}

	/**
	 * The profile section heading honours its own key.
	 */
	public function test_profile_section_heading_is_filterable() {
		$this->add_tracked_filter(
			'open_accessibility_strings',
			function ( $strings ) {
				$strings['profiles_title'] = 'Sentinel Profiles Heading';
				return $strings;
			}
		);

		$html = $this->render_widget();

		$this->assertStringContainsString( 'Sentinel Profiles Heading', $html );
	}

	/**
	 * The profile section appears before the individual controls.
	 *
	 * Profiles are the fast path: a visitor who wants one should not have to
	 * scroll past fifteen individual settings to find it.
	 */
	public function test_profile_section_precedes_the_individual_controls() {
		$this->activate_plugin();

		$html = $this->render_widget();

		$profile_position = strpos( $html, 'data-action="profile"' );
		$contrast_position = strpos( $html, 'data-action="contrast"' );
		$reset_position = strpos( $html, 'open-accessibility-reset-button' );

		$this->assertNotFalse( $profile_position );
		$this->assertNotFalse( $contrast_position );
		$this->assertNotFalse( $reset_position );

		$this->assertLessThan( $contrast_position, $profile_position, 'Profiles should come before the individual controls.' );
		$this->assertGreaterThan( $reset_position, $profile_position, 'Reset should stay first.' );
	}
}
