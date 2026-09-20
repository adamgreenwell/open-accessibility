<?php
/**
 * Tests for the site content scanner.
 *
 * The scanner is the part of the report that only the report needs: reading
 * posts, caching results against the content they describe, and aggregating.
 * The rules themselves are covered by Test_Audit_Rules.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Scanner
 */
class Test_Scanner extends OA_TestCase {

	/**
	 * Content with one problem: a heading level jump.
	 *
	 * @var string
	 */
	const CONTENT_WITH_ISSUE = '<!-- wp:heading {"level":2} --><h2>Section</h2><!-- /wp:heading -->'
		. '<!-- wp:heading {"level":5} --><h5>Jumped</h5><!-- /wp:heading -->';

	/**
	 * Content with nothing to report.
	 *
	 * @var string
	 */
	const CONTENT_CLEAN = '<!-- wp:paragraph --><p>Ordinary text.</p><!-- /wp:paragraph -->';

	/**
	 * Create a published post.
	 *
	 * @param string $content Post content.
	 * @return int Post ID.
	 */
	private function make_post( $content ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);
	}

	/**
	 * Scanning a post stores a result with a count.
	 */
	public function test_scan_post_stores_a_result() {
		$id = $this->make_post( self::CONTENT_WITH_ISSUE );

		$result = Open_Accessibility_Scanner::scan_post( $id );

		$this->assertIsArray( $result );
		$this->assertNotEmpty( $result['findings'] );
		$this->assertSame( 1, (int) get_post_meta( $id, Open_Accessibility_Scanner::META_COUNT, true ) );
	}

	/**
	 * A clean post stores a zero count rather than nothing.
	 *
	 * A missing count and a count of zero mean different things to the report: the
	 * first says "not scanned", the second says "scanned and fine".
	 */
	public function test_clean_post_stores_a_zero_count() {
		$id = $this->make_post( self::CONTENT_CLEAN );

		Open_Accessibility_Scanner::scan_post( $id );

		$this->assertSame( 0, (int) get_post_meta( $id, Open_Accessibility_Scanner::META_COUNT, true ) );
	}

	/**
	 * Rescanning unchanged content serves the cached result.
	 */
	public function test_unchanged_content_is_not_rescanned() {
		$id = $this->make_post( self::CONTENT_WITH_ISSUE );

		Open_Accessibility_Scanner::scan_post( $id );

		$first = get_post_meta( $id, Open_Accessibility_Scanner::META_RESULT, true );
		$first_time = $first['scanned_at'];

		// Backdate the stored timestamp; a rescan would replace it.
		$first['scanned_at'] = '2000-01-01 00:00:00';
		update_post_meta( $id, Open_Accessibility_Scanner::META_RESULT, $first );

		Open_Accessibility_Scanner::scan_post( $id );

		$second = get_post_meta( $id, Open_Accessibility_Scanner::META_RESULT, true );

		$this->assertSame(
			'2000-01-01 00:00:00',
			$second['scanned_at'],
			'An unchanged post should have served its cache, not been rescanned.'
		);
	}

	/**
	 * Changed content is rescanned.
	 *
	 * This is the property the content hash exists for: a post cannot serve a
	 * result describing content it no longer has.
	 */
	public function test_changed_content_is_rescanned() {
		$id = $this->make_post( self::CONTENT_CLEAN );

		Open_Accessibility_Scanner::scan_post( $id );
		$this->assertSame( 0, (int) get_post_meta( $id, Open_Accessibility_Scanner::META_COUNT, true ) );

		wp_update_post( array( 'ID' => $id, 'post_content' => self::CONTENT_WITH_ISSUE ) );

		// wp_update_post fires save_post, which clears the cache; scan again.
		Open_Accessibility_Scanner::scan_post( $id );

		$this->assertSame( 1, (int) get_post_meta( $id, Open_Accessibility_Scanner::META_COUNT, true ) );
	}

	/**
	 * A forced rescan ignores the cache.
	 */
	public function test_force_rescans() {
		$id = $this->make_post( self::CONTENT_WITH_ISSUE );

		Open_Accessibility_Scanner::scan_post( $id );

		$stored = get_post_meta( $id, Open_Accessibility_Scanner::META_RESULT, true );
		$stored['scanned_at'] = '2000-01-01 00:00:00';
		update_post_meta( $id, Open_Accessibility_Scanner::META_RESULT, $stored );

		Open_Accessibility_Scanner::scan_post( $id, true );

		$this->assertNotSame(
			'2000-01-01 00:00:00',
			get_post_meta( $id, Open_Accessibility_Scanner::META_RESULT, true )['scanned_at']
		);
	}

	/**
	 * Saving a post clears its cached result.
	 */
	public function test_saving_a_post_invalidates_its_result() {
		$id = $this->make_post( self::CONTENT_WITH_ISSUE );

		Open_Accessibility_Scanner::scan_post( $id );
		$this->assertNotNull( Open_Accessibility_Scanner::get_result( $id ) );

		wp_update_post( array( 'ID' => $id, 'post_title' => 'Renamed' ) );

		$this->assertNull(
			Open_Accessibility_Scanner::get_result( $id ),
			'Saving a post should drop its cached result.'
		);
	}

	/**
	 * A missing post is handled without complaint.
	 */
	public function test_missing_post_returns_null() {
		$this->assertNull( Open_Accessibility_Scanner::scan_post( 999999 ) );
	}

	/**
	 * A batch reports where it got to.
	 */
	public function test_scan_batch_reports_progress() {
		$ids = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->make_post( self::CONTENT_CLEAN );
		}

		sort( $ids );

		$batch = Open_Accessibility_Scanner::scan_batch( 0, 2 );

		$this->assertSame( 0, $batch['cursor'] );
		$this->assertSame( 2, $batch['scanned'] );
		$this->assertSame(
			$ids[1],
			$batch['next'],
			'The cursor should be the highest ID examined, so the next batch resumes after it.'
		);
		$this->assertFalse( $batch['complete'], 'A full batch is not the end.' );
	}

	/**
	 * The cursor leaves nothing behind when a post disappears mid-scan.
	 *
	 * This is the reason the position is an ID rather than an offset: deleting a
	 * post that has already been processed shifts every later row left, so an
	 * offset would step over the post that moved into the vacated slot.
	 */
	public function test_deleting_a_scanned_post_does_not_skip_the_next_one() {
		$ids = array();

		for ( $i = 0; $i < 4; $i++ ) {
			$ids[] = $this->make_post( self::CONTENT_WITH_ISSUE );
		}

		sort( $ids );

		$first = Open_Accessibility_Scanner::scan_batch( 0, 2 );
		$this->assertSame( 2, $first['scanned'] );

		// Remove one of the posts that was just scanned.
		wp_delete_post( $ids[0], true );

		$second = Open_Accessibility_Scanner::scan_batch( $first['next'], 2 );

		$this->assertSame(
			2,
			$second['scanned'],
			'The remaining posts should still be reached after a deletion behind the cursor.'
		);

		$scanned = array();

		foreach ( $ids as $id ) {
			if ( get_post_meta( $id, Open_Accessibility_Scanner::META_COUNT, true ) !== '' ) {
				$scanned[] = $id;
			}
		}

		$this->assertCount( 3, $scanned, 'Every surviving post should have been scanned exactly once.' );
	}

	/**
	 * The last batch reports completion.
	 */
	public function test_final_batch_reports_completion() {
		$this->make_post( self::CONTENT_CLEAN );

		$batch = Open_Accessibility_Scanner::scan_batch( 0, 25 );

		$this->assertTrue( $batch['complete'], 'A short batch means the end was reached.' );
	}

	/**
	 * Scanning everything covers every post.
	 */
	public function test_scan_all_covers_every_post() {
		$ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$ids[] = $this->make_post( self::CONTENT_WITH_ISSUE );
		}

		$scanned = Open_Accessibility_Scanner::scan_all();

		$this->assertGreaterThanOrEqual( 5, $scanned );

		foreach ( $ids as $id ) {
			$this->assertNotNull(
				Open_Accessibility_Scanner::get_result( $id ),
				"Post {$id} was not scanned."
			);
		}
	}

	/**
	 * The report lists posts with issues, worst first.
	 */
	public function test_report_is_ordered_by_issue_count() {
		$clean = $this->make_post( self::CONTENT_CLEAN );
		$one   = $this->make_post( self::CONTENT_WITH_ISSUE );
		$two   = $this->make_post(
			self::CONTENT_WITH_ISSUE . '<!-- wp:heading {"level":3} --><h3></h3><!-- /wp:heading -->'
		);

		Open_Accessibility_Scanner::scan_all();

		$report = Open_Accessibility_Scanner::get_report();

		$ids = array_column( $report, 'post_id' );

		$this->assertNotContains( $clean, $ids, 'A post with no findings should not be listed.' );
		$this->assertContains( $one, $ids );
		$this->assertContains( $two, $ids );
		$this->assertLessThan(
			array_search( $one, $ids, true ),
			array_search( $two, $ids, true ),
			'The post with more findings should come first.'
		);
	}

	/**
	 * Totals add up across posts.
	 */
	public function test_totals_aggregate_every_post() {
		$this->make_post( self::CONTENT_CLEAN );
		$this->make_post( self::CONTENT_WITH_ISSUE );

		Open_Accessibility_Scanner::scan_all();

		$totals = Open_Accessibility_Scanner::get_totals();

		$this->assertGreaterThanOrEqual( 2, $totals['posts_scanned'] );
		$this->assertGreaterThanOrEqual( 1, $totals['posts_with_issues'] );
		$this->assertGreaterThanOrEqual( 1, $totals['findings'] );
		$this->assertSame( $totals['warning'] + $totals['error'] + $totals['review'], $totals['findings'] );
	}

	/**
	 * The totals walk does not silently stop at an arbitrary number.
	 *
	 * An earlier version capped every walk at 500 posts, so a larger site would
	 * have reported partial totals with no indication they were partial.
	 */
	public function test_totals_walk_past_a_single_page() {
		$scanner = new ReflectionClass( 'Open_Accessibility_Scanner' );

		$this->assertTrue(
			$scanner->hasMethod( 'all_scanned_ids' ),
			'A full walk is needed so the report is not limited to one query.'
		);

		// The walk is page-based rather than capped.
		$source = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-scanner.php' );

		$this->assertStringNotContainsString(
			'get_batch( 0, 500 )',
			$source,
			'The report should page through every post rather than sampling the first 500.'
		);
	}

	/**
	 * Progress is stored in a non-autoloaded option.
	 *
	 * A transient is not durable when an external object cache is in use, and an
	 * infinite transient is autoloaded on every request site-wide.
	 */
	public function test_progress_uses_a_non_autoloaded_option() {
		Open_Accessibility_Scanner::set_progress( array( 'cursor' => 25, 'scanned' => 25 ) );

		$this->assertSame( 25, Open_Accessibility_Scanner::get_progress()['cursor'] );

		$autoload = get_option( 'open_accessibility_scan_state' );
		$this->assertIsArray( $autoload );

		// The option must not be in the autoload set.
		$alloptions = wp_load_alloptions();

		$this->assertArrayNotHasKey(
			'open_accessibility_scan_state',
			$alloptions,
			'Scan progress must not be autoloaded on every request.'
		);

		Open_Accessibility_Scanner::clear_progress();
		$this->assertSame( 0, Open_Accessibility_Scanner::get_progress()['cursor'] );
	}

	/**
	 * Each scheduled batch has distinct arguments.
	 *
	 * wp_schedule_single_event() deduplicates on the hook and its arguments, so
	 * identical arguments would be treated as a duplicate and dropped — stalling
	 * the chain after one hop with no error.
	 */
	public function test_scheduled_batches_are_distinct_events() {
		Open_Accessibility_Scanner::schedule_next_batch( 0 );
		Open_Accessibility_Scanner::schedule_next_batch( 25 );

		$this->assertNotFalse( wp_next_scheduled( 'open_accessibility_scan_batch', array( 0 ) ) );
		$this->assertNotFalse( wp_next_scheduled( 'open_accessibility_scan_batch', array( 25 ) ) );

		// The same offset twice is a duplicate and should be refused rather than
		// silently queued behind itself.
		$this->assertFalse( Open_Accessibility_Scanner::schedule_next_batch( 0 ) );

		wp_clear_scheduled_hook( 'open_accessibility_scan_batch' );
	}

	/**
	 * A scheduled batch advances progress and queues the next one.
	 */
	public function test_scheduled_batch_advances_progress() {
		$ids = array();

		for ( $i = 0; $i < 30; $i++ ) {
			$ids[] = $this->make_post( self::CONTENT_CLEAN );
		}

		Open_Accessibility_Scanner::set_progress( array( 'cursor' => 0, 'scanned' => 0 ) );
		Open_Accessibility_Scanner::run_scheduled_batch( 0 );

		$progress = Open_Accessibility_Scanner::get_progress();

		// One batch is BATCH_SIZE posts, so the cursor lands on that many posts
		// into the ordered set, not at the end of it.
		sort( $ids );

		$this->assertSame( $ids[ Open_Accessibility_Scanner::BATCH_SIZE - 1 ], $progress['cursor'] );
		$this->assertGreaterThan( 0, $progress['scanned'] );
		$this->assertTrue( $progress['running'], 'More posts remain, so the scan is still running.' );

		wp_clear_scheduled_hook( 'open_accessibility_scan_batch' );
		Open_Accessibility_Scanner::clear_progress();
	}

	/**
	 * Cache suspension is restored, not left on.
	 *
	 * wp_suspend_cache_addition() returns the *new* state rather than the previous
	 * one, unlike wp_suspend_cache_invalidation(). Restoring from its own return
	 * value would leave cache addition suspended for the rest of the request.
	 */
	public function test_cache_suspension_is_restored() {
		$this->make_post( self::CONTENT_CLEAN );

		$this->assertFalse( wp_suspend_cache_addition(), 'Precondition: not suspended.' );

		Open_Accessibility_Scanner::scan_batch( 0, 1 );

		$this->assertFalse(
			wp_suspend_cache_addition(),
			'Scanning must leave cache addition as it found it.'
		);
	}

	/**
	 * Uninstall removes the report data without touching tables it does not own.
	 */
	public function test_uninstall_removes_only_its_own_data() {
		global $wpdb;

		$id = $this->make_post( self::CONTENT_WITH_ISSUE );
		Open_Accessibility_Scanner::scan_post( $id );

		$before = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				Open_Accessibility_Scanner::META_RESULT
			)
		);
		$this->assertGreaterThan( 0, $before );

		Open_Accessibility_Scanner::uninstall();

		$after = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				Open_Accessibility_Scanner::META_RESULT
			)
		);

		$this->assertSame( 0, $after, 'Uninstall should remove the report meta it created.' );
	}

	/**
	 * The uninstall routine names no table other than the ones it owns.
	 *
	 * It runs on sites where another plugin may have created its own tables, and
	 * a broad DELETE would take those with it.
	 */
	public function test_uninstall_does_not_claim_other_plugins_tables() {
		$source = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'includes/class-open-accessibility-scanner.php' );
		$source = preg_replace( '#/\*.*?\*/#s', '', $source );

		$this->assertStringNotContainsString( 'action_scheduler', strtolower( $source ) );
		$this->assertStringNotContainsString( 'DROP TABLE', strtoupper( $source ) );
	}

	/**
	 * The uninstall entry point actually calls the scanner's cleanup.
	 *
	 * The scanner's own uninstall() can be correct while nothing ever calls it,
	 * which is exactly what happened: uninstall.php loaded only the DB class, so
	 * every cached result, the progress record and any queued batch survived an
	 * uninstall. The wiring is asserted at the source level because running
	 * uninstall.php for real means defining WP_UNINSTALL_PLUGIN, which would
	 * leak into every later test in this process and silently disable the
	 * plugin's option handling for the rest of the run.
	 */
	public function test_uninstall_entry_point_calls_the_scanner() {
		$source = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'uninstall.php' );

		$this->assertStringContainsString(
			'class-open-accessibility-scanner.php',
			$source,
			'uninstall.php must load the scanner class.'
		);

		$this->assertStringContainsString(
			'Open_Accessibility_Scanner::uninstall()',
			$source,
			'uninstall.php must call the scanner cleanup.'
		);
	}

	/**
	 * Queued batches are cleared whatever cursor they carry.
	 *
	 * wp_clear_scheduled_hook() only clears the events whose arguments it is
	 * given, or those with none. Every batch this plugin schedules carries a
	 * cursor argument, so clearing by hook name alone left the queue behind.
	 */
	public function test_clear_scheduled_batches_removes_every_cursor() {
		wp_clear_scheduled_hook( 'open_accessibility_scan_batch' );

		Open_Accessibility_Scanner::schedule_next_batch( 0 );
		Open_Accessibility_Scanner::schedule_next_batch( 25 );
		Open_Accessibility_Scanner::schedule_next_batch( 50 );

		$this->assertGreaterThanOrEqual(
			3,
			Open_Accessibility_Scanner::clear_scheduled_batches(),
			'Every queued batch should be reported as removed.'
		);

		$this->assertFalse(
			wp_next_scheduled( 'open_accessibility_scan_batch', array( 0 ) ),
			'A batch queued with cursor 0 should be gone.'
		);

		$this->assertFalse(
			wp_next_scheduled( 'open_accessibility_scan_batch', array( 25 ) ),
			'A batch queued with cursor 25 should be gone.'
		);

		$this->assertFalse(
			wp_next_scheduled( 'open_accessibility_scan_batch', array( 50 ) ),
			'A batch queued with cursor 50 should be gone.'
		);

		// Nothing left on the hook at any timestamp.
		foreach ( _get_cron_array() as $hooks ) {
			$this->assertArrayNotHasKey(
				'open_accessibility_scan_batch',
				$hooks,
				'No scan batch should remain queued.'
			);
		}
	}
}
