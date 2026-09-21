<?php
/**
 * Tests for the accessibility statement generator.
 *
 * The point of this class is that it does not claim anything it cannot support,
 * so most of what is asserted here is what the statement refuses to say: no
 * conformance claim when nothing has been assessed, no limitations list when no
 * checks have run, and no silence about the categories the audit cannot examine.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Statement_Generator
 */
class Test_Statement_Generator extends OA_TestCase {

	/**
	 * The generator must be callable from anywhere.
	 *
	 * It used to sniff $_POST['action'] and return null unless it was reached
	 * through one specific AJAX request, and check a nonce that the controller
	 * already checks. That made it unusable from a test, a CLI command or a cron
	 * job, and it silently produced an empty statement anywhere else.
	 */
	public function test_generate_statement_needs_no_request_context() {
		$_POST = array();
		$_GET  = array();

		$statement = Open_Accessibility_Statement_Generator::generate_statement( array() );

		$this->assertIsString( $statement, 'The generator must return a string, not null.' );
		$this->assertNotSame( '', trim( $statement ) );
		$this->assertStringContainsString( 'Accessibility Statement', $statement );
	}

	/**
	 * A statement produced with no input at all makes no conformance claim.
	 */
	public function test_defaults_to_not_assessed_on_wcag_2_2() {
		$statement = Open_Accessibility_Statement_Generator::generate_statement( array() );

		$this->assertStringContainsString( 'WCAG 2.2', $statement, 'WCAG 2.2 should be the default standard.' );
		$this->assertStringContainsString( 'has not been assessed', $statement );
		$this->assertStringContainsString( 'No conformance claim is made', $statement );

		$this->assertStringNotContainsString(
			'partially conformant with',
			$statement,
			'Nothing was assessed, so no conformance may be asserted.'
		);
	}

	/**
	 * A chosen version and level both appear, and the claim is scoped to them.
	 */
	public function test_a_chosen_standard_and_level_are_stated() {
		$statement = Open_Accessibility_Statement_Generator::generate_statement(
			array(
				'standard'    => '2.1',
				'conformance' => 'AA',
			)
		);

		$this->assertStringContainsString( 'partially conformant with WCAG 2.1 level AA', $statement );
	}

	/**
	 * Values outside the offered set are refused rather than passed through.
	 *
	 * A statement is a public claim; it must not be possible to make it assert a
	 * version or a level that does not exist.
	 */
	public function test_unknown_standard_and_level_fall_back_to_safe_values() {
		$statement = Open_Accessibility_Statement_Generator::generate_statement(
			array(
				'standard'    => '9.9',
				'conformance' => 'AAAA',
			)
		);

		$this->assertStringContainsString( 'WCAG 2.2', $statement );
		$this->assertStringContainsString( 'has not been assessed', $statement );
		$this->assertStringNotContainsString( '9.9', $statement );
		$this->assertStringNotContainsString( 'AAAA', $statement );
	}

	/**
	 * A conformance claim made without any assessment says so.
	 *
	 * "Partially conformant" is still a claim, so it matters that the statement
	 * does not let it stand unqualified when the plugin has measured nothing.
	 */
	public function test_a_claim_without_report_data_is_flagged() {
		$statement = Open_Accessibility_Statement_Generator::generate_statement(
			array( 'conformance' => 'AA' )
		);

		$this->assertStringContainsString( 'partially conformant', $statement );
		$this->assertStringContainsString(
			'has not been verified',
			$statement,
			'A conformance claim with no assessment behind it must be qualified.'
		);
	}

	/**
	 * With no checks run, the limitations section says so rather than inventing.
	 */
	public function test_limitations_say_nothing_has_been_checked() {
		$statement = Open_Accessibility_Statement_Generator::generate_statement( array() );

		$this->assertStringContainsString( 'No automated checks have been run', $statement );
	}

	/**
	 * Limitations cite what the site report actually found.
	 *
	 * This is the difference between a statement and boilerplate: a checkable
	 * count rather than a paragraph that would be true of any site.
	 */
	public function test_limitations_cite_real_report_findings() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":7} -->'
					. '<figure class="wp-block-image"><img src="https://example.org/p.jpg" class="wp-image-7"/></figure>'
					. '<!-- /wp:image -->',
			)
		);

		Open_Accessibility_Scanner::scan_post( $post_id, true );

		$limitations = Open_Accessibility_Statement_Generator::known_limitations();

		$this->assertArrayHasKey( 'image_missing_alt', $limitations['checked'] );
		$this->assertSame( 1, $limitations['checked']['image_missing_alt']['count'] );
		$this->assertSame( 1, $limitations['posts'] );

		$statement = Open_Accessibility_Statement_Generator::generate_statement( array() );

		$this->assertStringNotContainsString( 'No automated checks have been run', $statement );
		$this->assertStringContainsString( 'This image has no alt text', $statement );
		$this->assertStringContainsString( 'WCAG 1.1.1', $statement );
	}

	/**
	 * Categories the audit cannot check are named in the statement.
	 *
	 * A list of findings reads as a complete account unless the statement says
	 * what was never looked at.
	 */
	public function test_statement_names_the_unchecked_categories() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":7} -->'
					. '<figure class="wp-block-image"><img src="https://example.org/p.jpg" class="wp-image-7"/></figure>'
					. '<!-- /wp:image -->',
			)
		);

		Open_Accessibility_Scanner::scan_post( $post_id, true );

		$statement = Open_Accessibility_Statement_Generator::generate_statement( array() );

		foreach ( Open_Accessibility_Audit::unchecked_categories() as $category ) {
			$this->assertStringContainsString(
				esc_html( $category ),
				$statement,
				'Every category the audit cannot check should be named in the statement.'
			);
		}
	}

	/**
	 * Citing report data can be turned off.
	 */
	public function test_report_citation_can_be_disabled() {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:image {"id":7} -->'
					. '<figure class="wp-block-image"><img src="https://example.org/p.jpg" class="wp-image-7"/></figure>'
					. '<!-- /wp:image -->',
			)
		);

		Open_Accessibility_Scanner::scan_post( $post_id, true );

		$statement = Open_Accessibility_Statement_Generator::generate_statement(
			array( 'include_report' => false )
		);

		$this->assertStringNotContainsString( 'This image has no alt text', $statement );
		$this->assertStringContainsString( 'does not cite automated check results', $statement );
	}

	/**
	 * Scope falls back to the site URL, and uses the text when given one.
	 */
	public function test_scope_uses_supplied_text_or_the_site_url() {
		$fallback = Open_Accessibility_Statement_Generator::generate_statement( array() );

		$this->assertStringContainsString( 'This statement applies to the website at', $fallback );
		$this->assertStringContainsString( esc_html( site_url() ), $fallback );

		$explicit = Open_Accessibility_Statement_Generator::generate_statement(
			array( 'scope' => 'The shop and checkout only.' )
		);

		$this->assertStringContainsString( 'The shop and checkout only.', $explicit );
	}

	/**
	 * The assessment method and its date are stated.
	 */
	public function test_assessment_method_and_date_are_stated() {
		$statement = Open_Accessibility_Statement_Generator::generate_statement(
			array(
				'assessment'      => 'third_party',
				'assessment_date' => '2026-03-01',
			)
		);

		$this->assertStringContainsString( 'Third-party evaluation', $statement );
		$this->assertStringContainsString( '2026-03-01', $statement );
	}

	/**
	 * The feedback route reuses the settings the widget panel already uses.
	 */
	public function test_feedback_section_reuses_configured_links() {
		$post_id = self::factory()->post->create(
			array( 'post_content' => '<!-- wp:image {"id":7} -->'
				. '<figure class="wp-block-image"><img src="https://example.org/p.jpg" class="wp-image-7"/></figure>'
				. '<!-- /wp:image -->' )
		);

		Open_Accessibility_Scanner::scan_post( $post_id, true );

		$options                  = Open_Accessibility_Utils::get_options();
		$options['feedback_url']  = 'https://example.org/feedback';
		$options['help_url']      = 'https://example.org/help';
		update_option( self::OPTION, $options );

		$statement = Open_Accessibility_Statement_Generator::generate_statement(
			array( 'contact_email' => 'access@example.org' )
		);

		$this->assertStringContainsString( 'access@example.org', $statement );
		$this->assertStringContainsString( 'https://example.org/feedback', $statement );
		$this->assertStringContainsString( 'https://example.org/help', $statement );
	}

	/**
	 * Nothing supplied by the user can inject markup.
	 */
	public function test_user_supplied_values_are_escaped() {
		$statement = Open_Accessibility_Statement_Generator::generate_statement(
			array(
				'org_name' => 'Bad <script>alert(1)</script> & Co',
				'scope'    => 'Scope with <script>alert(2)</script> markup',
			)
		);

		$this->assertStringNotContainsString( '<script>', $statement );
		$this->assertStringNotContainsString( 'alert(1)', $statement );
		$this->assertStringNotContainsString( 'alert(2)', $statement );
	}

	/**
	 * Creating a page stores its URL, and deleting the page clears it.
	 *
	 * The URL is written into the plugin options so the settings field and the
	 * widget's panel link can point at it. Nothing undid that when the page was
	 * deleted, so both kept pointing at a 404.
	 */
	public function test_deleting_the_statement_page_clears_the_stored_url() {
		$page_id = Open_Accessibility_Statement_Generator::create_statement_page( array() );

		$this->assertIsInt( $page_id );
		$this->assertGreaterThan( 0, $page_id );

		$options = Open_Accessibility_Utils::get_options();

		$this->assertSame(
			get_permalink( $page_id ),
			$options['statement_url'],
			'Creating the page should record its URL.'
		);

		wp_delete_post( $page_id, true );

		$after = Open_Accessibility_Utils::get_options();

		$this->assertSame(
			'',
			$after['statement_url'],
			'Deleting the statement page should clear the stored URL.'
		);
	}

	/**
	 * Deleting an unrelated page leaves the statement URL alone.
	 *
	 * The cleanup hook runs for every deletion, so it has to be sure it is
	 * looking at the right post.
	 */
	public function test_deleting_another_page_does_not_clear_the_statement_url() {
		$statement_page = Open_Accessibility_Statement_Generator::create_statement_page( array() );
		$other_page     = self::factory()->post->create(
			array( 'post_type' => 'page', 'post_title' => 'Unrelated' )
		);

		wp_delete_post( $other_page, true );

		$options = Open_Accessibility_Utils::get_options();

		$this->assertSame(
			get_permalink( $statement_page ),
			$options['statement_url'],
			'An unrelated deletion must not clear the statement URL.'
		);
	}

	/**
	 * The generated page's content is the generated statement.
	 */
	public function test_created_page_contains_the_statement() {
		$page_id = Open_Accessibility_Statement_Generator::create_statement_page(
			array( 'conformance' => 'AA', 'standard' => '2.2' )
		);

		$page = get_post( $page_id );

		$this->assertNotNull( $page );
		$this->assertStringContainsString( 'partially conformant with WCAG 2.2 level AA', $page->post_content );
		$this->assertStringContainsString( 'Known Limitations', $page->post_content );
	}
}
