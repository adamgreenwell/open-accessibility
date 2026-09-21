<?php
/**
 * Tests for the site-wide audit report screen.
 *
 * The report is mostly wiring: it owns the capability and nonce checks, and
 * delegates every decision to the scanner and the audit. What is worth asserting
 * here is the wiring — that the screen is registered behind the right
 * capability, that its assets load only on its own page, and that the display
 * helpers handle the shapes the scanner actually stores.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Report
 */
class Test_Report extends OA_TestCase {

	/**
	 * Start a scan the way the report does, one batch at a time.
	 *
	 * Mirrors advance_scan(): the AJAX handler is private and ends in
	 * wp_send_json_success(), which would end the test process. What matters is
	 * that stepping batches writes the same progress the browser reads back.
	 *
	 * @param bool $force Rescan posts whose content is unchanged.
	 * @return array Final progress.
	 */
	private function run_scan_in_batches( $force = true ) {
		$progress = array(
			'cursor'   => 0,
			'total'    => Open_Accessibility_Scanner::count_scannable_posts(),
			'scanned'  => 0,
			'running'  => true,
			'finished' => false,
			'force'    => $force,
		);

		$guard = 0;

		do {
			$batch = Open_Accessibility_Scanner::scan_batch(
				(int) $progress['cursor'],
				Open_Accessibility_Scanner::BATCH_SIZE,
				$force
			);

			$progress['cursor']   = $batch['next'];
			$progress['scanned'] += $batch['scanned'];
			$progress['running']  = ! $batch['complete'];
			$progress['finished'] = $batch['complete'];

			Open_Accessibility_Scanner::set_progress( $progress );
		} while ( ! $batch['complete'] && ++$guard < 50 );

		return $progress;
	}

	/**
	 * An image block whose image has no alt attribute at all.
	 *
	 * The audit reads serialised block markup, so a bare <img> is not an image
	 * block and would not be examined — which is precisely the trap this helper
	 * exists to avoid.
	 *
	 * @return string
	 */
	private static function image_without_alt() {
		return '<!-- wp:image {"id":7} -->'
			. '<figure class="wp-block-image"><img src="https://example.org/photo.jpg" class="wp-image-7"/></figure>'
			. '<!-- /wp:image -->';
	}

	/**
	 * The report registers a submenu entry behind manage_options.
	 */
	public function test_report_page_is_registered_behind_a_capability() {
		global $submenu;

		$original = $submenu;

		// add_submenu_page() appends to this global, which WordPress creates
		// while building the menu. Nothing builds the menu in a unit test.
		$submenu = array();

		// add_submenu_page() returns early when the current user lacks the
		// capability, so the test needs a user who has it.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		try {
			Open_Accessibility_Report::add_report_page();
		} catch ( Exception $e ) {
			$submenu = $original;
			throw $e;
		}

		$this->assertArrayHasKey(
			'open-accessibility-settings',
			$submenu,
			'The report should hang off the Accessibility menu.'
		);

		$slugs = wp_list_pluck( $submenu['open-accessibility-settings'], 2 );

		$this->assertContains(
			Open_Accessibility_Report::PAGE_SLUG,
			$slugs,
			'The report slug should be registered as a submenu item.'
		);

		foreach ( $submenu['open-accessibility-settings'] as $entry ) {
			if ( Open_Accessibility_Report::PAGE_SLUG === $entry[2] ) {
				$this->assertSame(
					'manage_options',
					$entry[1],
					'The report should require manage_options, not a lower capability.'
				);
			}
		}

		$submenu = $original;
	}

	/**
	 * The report hooks are registered on init.
	 */
	public function test_report_registers_its_hooks() {
		$this->assertNotFalse(
			has_action( 'admin_menu', array( 'Open_Accessibility_Report', 'add_report_page' ) ),
			'admin_menu should be hooked.'
		);

		$this->assertNotFalse(
			has_action( 'admin_enqueue_scripts', array( 'Open_Accessibility_Report', 'enqueue_assets' ) ),
			'admin_enqueue_scripts should be hooked.'
		);

		foreach ( array( 'start_scan', 'scan_progress', 'rescan_post' ) as $action ) {
			$this->assertNotFalse(
				has_action( 'wp_ajax_open_accessibility_' . $action, array( 'Open_Accessibility_Report', 'ajax_' . $action ) ),
				"wp_ajax_open_accessibility_{$action} should be hooked."
			);
		}
	}

	/**
	 * Every AJAX action is admin-only.
	 *
	 * A report action reachable by a logged-out visitor would either leak the
	 * site's content problems or let anyone queue work on the server.
	 */
	public function test_report_ajax_actions_are_not_public() {
		foreach ( array( 'start_scan', 'scan_progress', 'rescan_post' ) as $action ) {
			$this->assertFalse(
				has_action( 'wp_ajax_nopriv_open_accessibility_' . $action ),
				"open_accessibility_{$action} must not be registered for logged-out users."
			);
		}
	}

	/**
	 * The scanner never checks capabilities itself, so the report must.
	 *
	 * This is the counterpart to the scanner's own "no request context" test:
	 * between them, every entry point is covered exactly once.
	 */
	public function test_report_handlers_check_capabilities() {
		$source = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'admin/class-open-accessibility-report.php' );

		$this->assertStringContainsString(
			'current_user_can',
			$source,
			'The report must check capabilities before acting.'
		);

		$this->assertStringContainsString(
			'check_ajax_referer',
			$source,
			'The report must verify a nonce on its AJAX handlers.'
		);
	}

	/**
	 * Assets load on the report screen and nowhere else.
	 */
	public function test_assets_only_load_on_the_report_screen() {
		// A private registry keeps the assertion independent of whatever other
		// tests have enqueued on the shared one.
		$original             = wp_styles();
		$GLOBALS['wp_styles'] = new WP_Styles();

		try {
			Open_Accessibility_Report::enqueue_assets( 'toplevel_page_open-accessibility-settings' );

			$this->assertFalse(
				wp_style_is( 'open-accessibility-report', 'enqueued' ),
				'The report stylesheet should not load on the settings screen.'
			);

			Open_Accessibility_Report::enqueue_assets( 'accessibility_page_open-accessibility-report' );

			$this->assertTrue(
				wp_style_is( 'open-accessibility-report', 'enqueued' ),
				'The report stylesheet should load on the report screen.'
			);
		} finally {
			$GLOBALS['wp_styles'] = $original;
		}
	}

	/**
	 * Progress is reported as a whole percentage and clamped.
	 */
	public function test_percent_is_clamped() {
		$this->assertSame( 0, Open_Accessibility_Report::percent( array( 'total' => 0, 'scanned' => 0 ) ) );
		$this->assertSame( 50, Open_Accessibility_Report::percent( array( 'total' => 10, 'scanned' => 5 ) ) );
		$this->assertSame( 100, Open_Accessibility_Report::percent( array( 'total' => 10, 'scanned' => 10 ) ) );

		// A post published mid-scan can push the count past the total.
		$this->assertSame(
			100,
			Open_Accessibility_Report::percent( array( 'total' => 10, 'scanned' => 14 ) ),
			'Progress should never exceed 100%.'
		);
	}

	/**
	 * Before any scan, an empty report is not reported as a clean bill.
	 */
	public function test_has_scanned_distinguishes_empty_from_unchecked() {
		$this->assertFalse(
			Open_Accessibility_Report::has_scanned( array( 'finished' => false ), array( 'posts_scanned' => 0 ) ),
			'No scan and no stored results should read as not scanned.'
		);

		$this->assertTrue(
			Open_Accessibility_Report::has_scanned( array( 'finished' => true ), array( 'posts_scanned' => 0 ) ),
			'A finished scan should read as scanned even with no findings.'
		);

		$post_id = self::factory()->post->create( array( 'post_content' => self::image_without_alt() ) );
		Open_Accessibility_Scanner::scan_post( $post_id, true );

		$this->assertTrue(
			Open_Accessibility_Report::has_scanned( array( 'finished' => false ), array( 'posts_scanned' => 1 ) ),
			'Stored results should read as scanned even without a progress record.'
		);
	}

	/**
	 * Findings are described from stored rule ids, not stored sentences.
	 */
	public function test_describe_findings_resolves_rule_metadata() {
		$described = Open_Accessibility_Report::describe_findings(
			array(
				array( 'rule' => Open_Accessibility_Audit::RULE_IMAGE_MISSING_ALT, 'severity' => 'error', 'block' => '0/1' ),
			)
		);

		$this->assertCount( 1, $described );
		$this->assertSame( Open_Accessibility_Audit::RULE_IMAGE_MISSING_ALT, $described[0]['rule'] );
		$this->assertNotSame( '', $described[0]['message'], 'A known rule should resolve to its message.' );
		$this->assertNotSame( '', $described[0]['wcag'], 'A known rule should resolve to its WCAG criterion.' );
		$this->assertSame( '0/1', $described[0]['block'] );
	}

	/**
	 * An unrecognised rule is still shown rather than dropped.
	 *
	 * A result stored by an older version can name a rule this version no longer
	 * defines. Silently discarding it would under-report.
	 */
	public function test_describe_findings_keeps_unknown_rules() {
		$described = Open_Accessibility_Report::describe_findings(
			array( array( 'rule' => 'oa_rule_that_no_longer_exists', 'severity' => 'warning' ) )
		);

		$this->assertCount( 1, $described );
		$this->assertSame( 'oa_rule_that_no_longer_exists', $described[0]['message'] );
		$this->assertSame( 'warning', $described[0]['severity'] );
	}

	/**
	 * Malformed entries are skipped rather than raising notices.
	 */
	public function test_describe_findings_ignores_malformed_entries() {
		$described = Open_Accessibility_Report::describe_findings(
			array( 'not-an-array', array( 'no_rule_key' => true ) )
		);

		$this->assertSame( array(), $described );
	}

	/**
	 * Severity labels are words, not the raw slugs.
	 *
	 * The report uses these as the visible text next to each colour, so a slug
	 * leaking through would leave colour as the only real signal.
	 */
	public function test_severity_labels_are_words() {
		foreach ( array( 'error', 'warning', 'review' ) as $severity ) {
			$label = Open_Accessibility_Report::severity_label( $severity );

			$this->assertNotSame( $severity, $label, "{$severity} should have a human label." );
			$this->assertNotSame( '', $label );
		}
	}

	/**
	 * A full scan populates the totals the report header shows.
	 */
	public function test_scan_populates_report_totals() {
		self::factory()->post->create_many( 3, array( 'post_content' => self::image_without_alt() ) );
		self::factory()->post->create( array( 'post_content' => '<!-- wp:paragraph --><p>Clean content.</p><!-- /wp:paragraph -->' ) );

		$progress = $this->run_scan_in_batches();

		$this->assertTrue( $progress['finished'], 'The scan should report itself finished.' );
		$this->assertFalse( $progress['running'] );

		$totals = Open_Accessibility_Scanner::get_totals();

		$this->assertSame( 4, $totals['posts_scanned'] );
		$this->assertSame( 3, $totals['posts_with_issues'] );
		$this->assertSame( 3, $totals['error'], 'Each missing-alt image is an error.' );
	}

	/**
	 * Progress survives between batches, which is what resume depends on.
	 */
	public function test_progress_is_persisted_between_batches() {
		self::factory()->post->create_many( 3, array( 'post_content' => self::image_without_alt() ) );

		$progress = $this->run_scan_in_batches();

		$stored = Open_Accessibility_Scanner::get_progress();

		$this->assertSame( $progress['scanned'], $stored['scanned'] );
		$this->assertTrue( $stored['finished'] );
	}

	/**
	 * Render the report screen and return its markup.
	 *
	 * display_report_page() includes the partial rather than returning it, so the
	 * output is captured. This exercises the whole screen: the lookups, the
	 * pagination arithmetic and the escaping.
	 *
	 * @return string
	 */
	private function render_report() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$original_get = $_GET;

		ob_start();

		try {
			Open_Accessibility_Report::display_report_page();
		} finally {
			$html     = ob_get_clean();
			$_GET     = $original_get;
			wp_set_current_user( 0 );
		}

		return $html;
	}

	/**
	 * The screen renders, and renders something.
	 *
	 * A panel that returns nothing is the failure this test exists for: the
	 * editor panel once shipped with a component that was referenced but never
	 * defined, so it rendered an empty box and no test noticed.
	 */
	public function test_report_screen_renders() {
		self::factory()->post->create( array( 'post_content' => self::image_without_alt() ) );

		$html = $this->render_report();

		$this->assertNotSame( '', trim( $html ), 'The report screen rendered nothing at all.' );
		$this->assertStringContainsString( 'Accessibility Report', $html, 'The screen should have its heading.' );
		$this->assertStringContainsString( '<table', $html, 'The screen should render its tables.' );
		$this->assertStringContainsString(
			'What this report does not check',
			$html,
			'The report must state what it cannot check.'
		);

		foreach ( Open_Accessibility_Audit::unchecked_categories() as $category ) {
			$this->assertStringContainsString(
				esc_html( $category ),
				$html,
				'Every unchecked category should be listed on the report.'
			);
		}
	}

	/**
	 * Before any scan the report says so rather than showing a clean site.
	 */
	public function test_unscanned_report_says_nothing_has_been_checked() {
		Open_Accessibility_Scanner::clear_progress();

		$html = $this->render_report();

		$this->assertStringContainsString(
			'No scan has run yet',
			$html,
			'An empty report must not read as a clean bill of health.'
		);
	}

	/**
	 * Content is escaped on the way out.
	 */
	public function test_report_escapes_post_titles() {
		$post_id = self::factory()->post->create(
			array(
				// get_the_title() strips tags before this ever reaches the
				// report, so the escaping that can actually be observed here is
				// the character escaping. Both are asserted: tag injection is the
				// attack, and an unescaped ampersand is the same class of mistake
				// in a quieter form.
				'post_title'   => 'Bad <script>alert(1)</script> & risky "quoted" title',
				'post_content' => self::image_without_alt(),
			)
		);

		Open_Accessibility_Scanner::scan_post( $post_id, true );

		$html = $this->render_report();

		$this->assertStringNotContainsString(
			'<script>alert(1)</script>',
			$html,
			'A post title must not be able to inject markup into the report.'
		);

		$this->assertStringNotContainsString(
			' & risky ',
			$html,
			'A raw ampersand from a post title must be escaped on the way out.'
		);

		$this->assertMatchesRegularExpression(
			'/&(amp|#038);/',
			$html,
			'The title should still be shown, with its ampersand escaped.'
		);
	}

	/**
	 * A scanned post with findings appears as a row.
	 */
	public function test_report_lists_posts_with_findings() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Has a missing alt',
				'post_content' => self::image_without_alt(),
			)
		);

		Open_Accessibility_Scanner::scan_post( $post_id, true );

		$html = $this->render_report();

		$this->assertStringContainsString( 'Posts with issues', $html );
		$this->assertStringContainsString( 'Has a missing alt', $html );
		$this->assertStringContainsString(
			'oa-report__rescan',
			$html,
			'Each row should offer a rescan control.'
		);
		$this->assertStringContainsString(
			'data-post-id="' . $post_id . '"',
			$html,
			'The row should be tied to its post.'
		);
	}

	/**
	 * Content that breaks every rule, as serialised block markup.
	 *
	 * Written the way the editor saves it, because the audit reads block markup
	 * rather than raw HTML: a bare <img> is not an image block and would not be
	 * examined at all.
	 *
	 * @return string
	 */
	private static function content_breaking_every_rule() {
		return '<!-- wp:heading {"level":1} --><h1>Top</h1><!-- /wp:heading -->'
			// h1 then h3 skips a level.
			. '<!-- wp:heading {"level":3} --><h3>Skips a level</h3><!-- /wp:heading -->'
			// A heading with no text.
			. '<!-- wp:heading {"level":2} --><h2> </h2><!-- /wp:heading -->'
			// An image with no alt attribute.
			. self::image_without_alt()
			// An image whose alt is a filename.
			. '<!-- wp:image {"id":8} --><figure class="wp-block-image"><img src="https://example.org/b.jpg" alt="DSC_9999.jpg"/></figure><!-- /wp:image -->'
			// A link that says nothing about where it goes.
			. '<!-- wp:paragraph --><p><a href="https://example.org/x">click here</a></p><!-- /wp:paragraph -->'
			// A button with no label.
			. '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.org/y"></a></div>'
			. '<!-- /wp:button --></div><!-- /wp:buttons -->'
			// A table with no header cells.
			. '<!-- wp:table --><figure class="wp-block-table"><table><tbody>'
			. '<tr><td>a</td><td>b</td></tr><tr><td>c</td><td>d</td></tr>'
			. '</tbody></table></figure><!-- /wp:table -->';
	}

	/**
	 * Findings reach the screen with their severity and their WCAG criterion.
	 *
	 * The rules are exercised in isolation elsewhere; what is checked here is
	 * that a finding survives the trip from the scanner's stored result into the
	 * rendered table. The bare image is deliberate — the panel and the report
	 * have both previously run rules against markup the audit never examines.
	 */
	public function test_findings_render_with_severity_and_criterion() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Breaks every rule',
				'post_content' => self::content_breaking_every_rule(),
			)
		);

		$result = Open_Accessibility_Scanner::scan_post( $post_id, true );

		$this->assertNotNull( $result, 'The fixture should produce a stored result.' );

		$stored_rules = wp_list_pluck( $result['findings'], 'rule' );

		foreach ( Open_Accessibility_Audit::rule_definitions() as $rule => $definition ) {
			$this->assertContains(
				$rule,
				$stored_rules,
				"The fixture should trigger {$rule}; without it the report assertion below is hollow."
			);
		}

		$html = $this->render_report();

		foreach ( Open_Accessibility_Audit::rule_definitions() as $rule => $definition ) {
			$this->assertStringContainsString(
				esc_html( $definition['message'] ),
				$html,
				"The report should show the finding for {$rule}."
			);

			$this->assertStringContainsString(
				'WCAG ' . esc_html( $definition['wcag'] ),
				$html,
				"The report should show the WCAG criterion for {$rule}."
			);

			$this->assertStringContainsString(
				esc_html( Open_Accessibility_Report::severity_label( $definition['severity'] ) ),
				$html,
				"The report should name the severity of {$rule} in words."
			);
		}
	}

	/**
	 * A row's count matches the findings it stores.
	 */
	public function test_row_count_matches_stored_findings() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'   => 'Broken',
				'post_content' => self::content_breaking_every_rule(),
			)
		);

		$result = Open_Accessibility_Scanner::scan_post( $post_id, true );

		$this->assertGreaterThan( 0, count( $result['findings'] ) );

		$rows = Open_Accessibility_Scanner::get_report( 20, 0 );

		$this->assertCount( 1, $rows, 'One post has findings, so one row.' );
		$this->assertSame( $post_id, $rows[0]['post_id'] );
		$this->assertSame(
			count( $result['findings'] ),
			$rows[0]['count'],
			'The displayed count should be the stored count.'
		);

		$html = $this->render_report();

		$this->assertStringContainsString(
			'oa-report__count',
			$html,
			'The row should render its count.'
		);
	}

	/**
	 * The report paginates the rows it has, not the posts on the site.
	 *
	 * Deriving the page count from the number of posts would show pages of rows
	 * that cannot exist, because only posts with findings become rows.
	 */
	public function test_pagination_counts_rows_not_posts() {
		// More posts with findings than fit on a page.
		$per_page = Open_Accessibility_Report::PER_PAGE;

		for ( $i = 0; $i < $per_page + 2; $i++ ) {
			$id = self::factory()->post->create( array( 'post_content' => self::image_without_alt() ) );
			Open_Accessibility_Scanner::scan_post( $id, true );
		}

		// Plus posts that are clean, which are not rows.
		self::factory()->post->create_many( 5, array( 'post_content' => '<!-- wp:paragraph --><p>Fine.</p><!-- /wp:paragraph -->' ) );

		$rows = Open_Accessibility_Scanner::get_report( $per_page, 0 );

		$this->assertCount( $per_page, $rows, 'A full page should be returned.' );
		$this->assertSame(
			$per_page + 2,
			Open_Accessibility_Scanner::count_posts_with_findings(),
			'Only posts with findings should be counted as rows.'
		);

		$html = $this->render_report();

		// 22 rows at 20 per page is two pages, so pagination must appear.
		$this->assertStringContainsString(
			'tablenav-pages',
			$html,
			'More rows than fit on a page should produce pagination.'
		);
	}

	/**
	 * The rendered report announces progress rather than only drawing it.
	 *
	 * A progress bar that changes silently is invisible to a screen reader user,
	 * which in an accessibility plugin is the failure that matters most.
	 */
	public function test_report_markup_announces_state() {
		$partial = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'admin/partials/report-display.php' );

		$this->assertStringContainsString( 'aria-live="polite"', $partial );
		$this->assertStringContainsString( 'role="status"', $partial );
		$this->assertStringContainsString( '<caption', $partial, 'Each data table needs a caption.' );
		$this->assertStringContainsString( 'scope="col"', $partial, 'Header cells need a scope.' );
	}
}
