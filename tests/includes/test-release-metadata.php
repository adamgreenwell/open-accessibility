<?php
/**
 * Tests for release metadata and the WordPress.org readme.
 *
 * These are invariants that no other test can catch, because they are not about
 * behaviour: a version bumped in one file and not another, or a readme section
 * rename that quietly drops a required heading. The plugin ships to a directory
 * that rejects a malformed readme, and `bin/check-release.sh` enforces the
 * version parity, but only when someone remembers to run it.
 *
 * @package Open_Accessibility
 */

/**
 * @coversNothing
 */
class Test_Release_Metadata extends OA_TestCase {

	/**
	 * Read a plugin file.
	 *
	 * @param string $relative Path relative to the plugin directory.
	 * @return string
	 */
	private function plugin_file( $relative ) {
		$path = OPEN_ACCESSIBILITY_PLUGIN_DIR . $relative;

		$this->assertFileExists( $path, "{$relative} should exist." );

		return file_get_contents( $path );
	}

	/**
	 * The version is the same in the header, the constant, and the readme.
	 *
	 * All three are read by something: WordPress shows the header, the plugin
	 * uses the constant for asset cache busting, and the directory uses the
	 * stable tag to decide what to serve.
	 */
	public function test_version_matches_across_header_constant_and_readme() {
		$plugin = $this->plugin_file( 'open-accessibility.php' );
		$readme = $this->plugin_file( 'README.txt' );

		preg_match( '/^[ \t]*\*[ \t]*Version:[ \t]*(\S+)/m', $plugin, $header );
		preg_match( '/^Stable tag:[ \t]*(\S+)/m', $readme, $stable );

		$this->assertNotEmpty( $header, 'The plugin header should declare a Version.' );
		$this->assertNotEmpty( $stable, 'README.txt should declare a Stable tag.' );

		$this->assertSame(
			OPEN_ACCESSIBILITY_VERSION,
			$header[1],
			'The plugin header version and OPEN_ACCESSIBILITY_VERSION have diverged.'
		);

		$this->assertSame(
			OPEN_ACCESSIBILITY_VERSION,
			$stable[1],
			'The README.txt stable tag and the plugin version have diverged.'
		);
	}

	/**
	 * The readme has the sections the plugin directory requires.
	 *
	 * A missing or renamed heading is not a PHP error and breaks nothing at
	 * runtime, which is exactly why it needs a test.
	 */
	public function test_readme_has_the_required_sections() {
		$readme = $this->plugin_file( 'README.txt' );

		preg_match_all( '/^==\s*(.+?)\s*==\s*$/m', $readme, $matches );

		$sections = array_map( 'trim', $matches[1] );

		foreach ( array( 'Description', 'Installation', 'Frequently Asked Questions', 'Changelog', 'Upgrade Notice' ) as $required ) {
			$this->assertContains(
				$required,
				$sections,
				"README.txt is missing its '{$required}' section."
			);
		}
	}

	/**
	 * Upgrade Notice covers every release still in the changelog's recent past.
	 *
	 * It previously stopped at 1.1.0, so users on 1.2.x through 1.4.x got no
	 * upgrade prompt at all. Every version from 1.2 onwards is asserted, since
	 * that is when the gap started.
	 */
	public function test_upgrade_notice_covers_recent_releases() {
		$readme = $this->plugin_file( 'README.txt' );

		$position = strpos( $readme, '== Upgrade Notice ==' );

		$this->assertNotFalse( $position, 'README.txt should have an Upgrade Notice section.' );

		$section = substr( $readme, $position );

		preg_match_all( '/^=\s*([0-9][^=]*?)\s*=\s*$/m', $section, $matches );

		$notices = array_map( 'trim', $matches[1] );

		$this->assertContains(
			OPEN_ACCESSIBILITY_VERSION,
			$notices,
			'The current version should have an upgrade notice.'
		);

		// Every release from 1.2 up to the current one should be covered.
		preg_match_all( '/^=\s*([0-9][^=]*?)\s*=\s*$/m', $readme, $all );

		$changelog = array_map( 'trim', $all[1] );

		$expected = array_filter(
			$changelog,
			function ( $version ) {
				return version_compare( $version, '1.2', '>=' );
			}
		);

		foreach ( $expected as $version ) {
			$this->assertContains(
				$version,
				$notices,
				"Upgrade Notice has no entry for {$version}, so users on it get no prompt."
			);
		}
	}

	/**
	 * Both readmes describe the features this roadmap added.
	 *
	 * Documentation that lags the product is a support burden; documentation
	 * that runs ahead of it is worse. This asserts the features exist in the
	 * docs, and the next test asserts the docs make no claim the code cannot
	 * support.
	 */
	public function test_both_readmes_document_the_roadmap_features() {
		$files = array(
			'README.txt' => $this->plugin_file( 'README.txt' ),
			'README.md'  => $this->plugin_file( 'README.md' ),
		);

		$terms = array( 'profile', 'cursor', 'saturation', 'highlight links', 'audit', 'report', 'statement' );

		foreach ( $files as $name => $body ) {
			foreach ( $terms as $term ) {
				$this->assertStringContainsStringIgnoringCase(
					$term,
					$body,
					"{$name} never mentions '{$term}', so a roadmap feature is undocumented."
				);
			}
		}
	}

	/**
	 * Neither readme claims the plugin makes a site compliant.
	 *
	 * The description used to call it "a comprehensive accessibility solution
	 * that helps your website comply with WCAG 2.1 standards" and list "WCAG 2.1
	 * Compliant" as a feature. The audit checks seven rules and cannot see
	 * colour contrast, focus order, keyboard traps or ARIA at all — so that was
	 * a claim the product could not support, and the exact kind this category
	 * has been penalised for making.
	 */
	public function test_both_readmes_decline_to_claim_compliance() {
		$files = array(
			'README.txt' => $this->plugin_file( 'README.txt' ),
			'README.md'  => $this->plugin_file( 'README.md' ),
		);

		foreach ( $files as $name => $body ) {
			$this->assertDoesNotMatchRegularExpression(
				'/WCAG 2\.\d Compliant|helps your website comply with WCAG/i',
				$body,
				"{$name} claims compliance the plugin cannot support."
			);

			$this->assertStringContainsStringIgnoringCase(
				'does not make your site compliant',
				$body,
				"{$name} should say plainly that the plugin does not make a site compliant."
			);
		}
	}

	/**
	 * The unchecked categories are named in the documentation, not just in the UI.
	 *
	 * A reader deciding whether to trust this plugin should not have to install
	 * it to find out what it does not do.
	 */
	public function test_documentation_names_the_unchecked_categories() {
		$readme = $this->plugin_file( 'README.txt' );

		foreach ( Open_Accessibility_Audit::unchecked_categories() as $category ) {
			$this->assertStringContainsString(
				$category,
				$readme,
				"README.txt does not mention that '{$category}' is unchecked."
			);
		}
	}

	/**
	 * Every documented rule is a rule the audit actually has.
	 *
	 * Guards against the opposite drift: documentation describing checks that
	 * were removed or renamed.
	 */
	public function test_documented_rules_match_the_audit() {
		$readme = $this->plugin_file( 'README.txt' );

		foreach ( Open_Accessibility_Audit::rule_definitions() as $rule => $definition ) {
			$this->assertStringContainsString(
				'WCAG ' . $definition['wcag'],
				$readme,
				"README.txt never cites WCAG {$definition['wcag']} for the '{$rule}' rule."
			);
		}
	}
}
