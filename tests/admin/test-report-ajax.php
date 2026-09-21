<?php
/**
 * Tests for the report's AJAX handlers.
 *
 * These are the plugin's only entry points that a browser can reach directly, so
 * the guards on them are the part worth exercising rather than describing. Each
 * handler is called through WordPress's own AJAX dispatch, not invoked by name,
 * so a handler that was never hooked fails here.
 *
 * @package Open_Accessibility
 * @group   ajax
 */

/**
 * @covers Open_Accessibility_Report::ajax_start_scan
 * @covers Open_Accessibility_Report::ajax_scan_progress
 * @covers Open_Accessibility_Report::ajax_rescan_post
 * @covers Open_Accessibility_Report::verify_request
 */
class Test_Report_Ajax extends WP_Ajax_UnitTestCase {

	/**
	 * An administrator, so the capability check is not what is under test.
	 *
	 * @return int
	 */
	private function become_admin() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Dispatch an action and decode whatever it printed.
	 *
	 * wp_send_json_*() ends by calling wp_die(), which the AJAX test case turns
	 * into an exception. Nothing after the handler call would run if that were
	 * left to propagate, so it is caught here and the body returned instead.
	 *
	 * @param string $action Action name, without the wp_ajax_ prefix.
	 * @param array  $post   Request fields.
	 * @return array Decoded response body.
	 */
	private function dispatch( $action, $post = array() ) {
		$this->_last_response = '';
		$_POST                = $post;

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected: the handler finished normally.
		} catch ( WPAjaxDieStopException $e ) {
			// The handler died with no output at all.
			$this->fail( "open_accessibility_{$action} produced no response: " . $e->getMessage() );
		}

		$decoded = json_decode( $this->_last_response, true );

		$this->assertIsArray( $decoded, "open_accessibility_{$action} did not return JSON." );

		return $decoded;
	}

	/**
	 * A valid nonce for the report's own action.
	 *
	 * @return string
	 */
	private function nonce() {
		return wp_create_nonce( Open_Accessibility_Report::NONCE_ACTION );
	}

	/**
	 * Content with one missing-alt image, as the audit reads it.
	 *
	 * @return string
	 */
	private function image_without_alt() {
		return '<!-- wp:image {"id":7} -->'
			. '<figure class="wp-block-image"><img src="https://example.org/p.jpg" class="wp-image-7"/></figure>'
			. '<!-- /wp:image -->';
	}

	/**
	 * Every handler refuses a request with no nonce.
	 */
	public function test_handlers_reject_a_missing_nonce() {
		$this->become_admin();

		foreach ( array( 'start_scan', 'scan_progress', 'rescan_post' ) as $action ) {
			$response = $this->dispatch( 'open_accessibility_' . $action, array( 'post_id' => 1 ) );

			$this->assertFalse( $response['success'], "{$action} accepted a request with no nonce." );
		}
	}

	/**
	 * Every handler refuses a request with a forged nonce.
	 */
	public function test_handlers_reject_a_invalid_nonce() {
		$this->become_admin();

		foreach ( array( 'start_scan', 'scan_progress', 'rescan_post' ) as $action ) {
			$response = $this->dispatch(
				'open_accessibility_' . $action,
				array( 'nonce' => 'not-a-real-nonce', 'post_id' => 1 )
			);

			$this->assertFalse( $response['success'], "{$action} accepted a forged nonce." );
		}
	}

	/**
	 * A nonce is not authorisation: a subscriber holding a valid one is refused.
	 *
	 * This is the case the nonce check alone would let through, so it is the one
	 * that proves the capability check is really there.
	 */
	public function test_handlers_reject_a_user_without_manage_options() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$response = $this->dispatch(
			'open_accessibility_start_scan',
			array( 'nonce' => $this->nonce(), 'force' => 'true' )
		);

		$this->assertFalse( $response['success'], 'A subscriber was allowed to start a scan.' );
		$this->assertStringContainsString( 'permission', strtolower( $response['data']['message'] ) );
	}

	/**
	 * A scan started from the report actually scans.
	 */
	public function test_start_scan_scans_the_first_batch() {
		$this->become_admin();

		self::factory()->post->create_many( 2, array( 'post_content' => $this->image_without_alt() ) );

		$response = $this->dispatch(
			'open_accessibility_start_scan',
			array( 'nonce' => $this->nonce(), 'force' => 'true' )
		);

		$this->assertTrue( $response['success'] );

		$stored = Open_Accessibility_Scanner::get_progress();

		$this->assertGreaterThan( 0, $stored['scanned'], 'Starting a scan should scan something.' );
		$this->assertSame(
			2,
			Open_Accessibility_Scanner::get_totals()['posts_with_issues'],
			'Both posts should have been recorded with findings.'
		);
	}

	/**
	 * Advancing reports a finished scan once there is nothing left.
	 */
	public function test_progress_advances_until_complete() {
		$this->become_admin();

		self::factory()->post->create_many( 3, array( 'post_content' => $this->image_without_alt() ) );

		$this->dispatch(
			'open_accessibility_start_scan',
			array( 'nonce' => $this->nonce(), 'force' => 'true' )
		);

		$guard = 0;
		$response = null;

		do {
			$response = $this->dispatch(
				'open_accessibility_scan_progress',
				array( 'nonce' => $this->nonce() )
			);

			$this->assertTrue( $response['success'] );
		} while ( ! empty( $response['data']['progress']['running'] ) && ++$guard < 20 );

		$this->assertFalse(
			$response['data']['progress']['running'],
			'The scan should stop reporting itself as running.'
		);

		$this->assertTrue( $response['data']['progress']['finished'] );
		$this->assertSame(
			3,
			Open_Accessibility_Scanner::get_totals()['posts_with_issues'],
			'The scan should have recorded every post with findings.'
		);
	}

	/**
	 * Progress can be polled when nothing is running.
	 *
	 * The browser polls on load to pick up a scan left running by a previous
	 * visit, so this must be a cheap no-op rather than an error.
	 */
	public function test_progress_is_safe_to_poll_when_idle() {
		$this->become_admin();

		Open_Accessibility_Scanner::clear_progress();

		$response = $this->dispatch(
			'open_accessibility_scan_progress',
			array( 'nonce' => $this->nonce() )
		);

		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['progress']['running'] );
	}

	/**
	 * The handler responses carry what the script reads.
	 *
	 * The script polls, reads data.progress.running / .scanned / .total and
	 * data.totals, and stops when running goes false. This drives the real
	 * handlers through a whole scan and checks those fields are present and
	 * move, so the two halves cannot drift apart without a failure here.
	 */
	public function test_scan_lifecycle_exposes_the_fields_the_script_reads() {
		$this->become_admin();

		self::factory()->post->create_many( 3, array( 'post_content' => $this->image_without_alt() ) );

		$started = $this->dispatch(
			'open_accessibility_start_scan',
			array( 'nonce' => $this->nonce(), 'force' => 'true' )
		);

		$this->assertTrue( $started['success'] );

		$this->assertArrayHasKey( 'progress', $started['data'], 'The start response needs progress.' );

		foreach ( array( 'running', 'scanned', 'total', 'finished' ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$started['data']['progress'],
				"The progress payload needs '{$key}' for the script to read."
			);
		}

		$this->assertGreaterThan(
			0,
			$started['data']['progress']['total'],
			'The total drives the progress bar, so it cannot be zero here.'
		);

		// Poll to completion the way the script does.
		$response = $started;
		$guard    = 0;

		while ( ! empty( $response['data']['progress']['running'] ) && ++$guard < 20 ) {
			$response = $this->dispatch(
				'open_accessibility_scan_progress',
				array( 'nonce' => $this->nonce() )
			);
		}

		$this->assertFalse(
			$response['data']['progress']['running'],
			'Polling should reach a state where the script stops asking.'
		);

		$this->assertSame(
			3,
			Open_Accessibility_Scanner::get_totals()['posts_with_issues'],
			'Every scanned post should have been recorded with findings.'
		);
	}

	/**
	 * The script and the handler agree on the response field names.
	 *
	 * The two halves are written in different languages and cannot be checked
	 * against each other by a compiler. A rename on either side would leave the
	 * progress bar frozen with no error anywhere.
	 *
	 * The check runs in both directions: every data.* reference in the script
	 * must resolve in a real response, and the response must not carry fields
	 * the script never reads. The second half is not decoration — the payload
	 * once included rows and totals that nothing consumed, which meant walking
	 * every scanned post on every poll for no reason.
	 */
	public function test_the_script_and_the_handler_agree_on_field_names() {
		$this->become_admin();

		self::factory()->post->create( array( 'post_content' => $this->image_without_alt() ) );

		$response = $this->dispatch(
			'open_accessibility_start_scan',
			array( 'nonce' => $this->nonce(), 'force' => 'true' )
		);

		$this->assertTrue( $response['success'] );

		$script = file_get_contents( OPEN_ACCESSIBILITY_PLUGIN_DIR . 'assets/js/open-accessibility-report.js' );

		// Top-level fields read off the response, as written in the script.
		preg_match_all( '/data\.([a-zA-Z_][a-zA-Z0-9_]*)/', $script, $matches );

		$referenced = array_values( array_unique( $matches[1] ) );

		$this->assertNotEmpty( $referenced, 'The script should read fields off the response.' );

		foreach ( $referenced as $field ) {
			// 'message' is the error path, which a success response does not carry.
			if ( 'message' === $field ) {
				continue;
			}

			$this->assertArrayHasKey(
				$field,
				$response['data'],
				"The script reads data.{$field}, which the response does not contain."
			);
		}

		// And nothing beyond what the script reads, so unused work cannot creep
		// back into every poll.
		$allowed = array_merge( $referenced, array( 'message' ) );

		foreach ( array_keys( $response['data'] ) as $key ) {
			$this->assertContains(
				$key,
				$allowed,
				"The response carries data.{$key}, which the script never reads."
			);
		}

		// The nested names the script reads after `var progress = data.progress`.
		foreach ( array( 'running', 'scanned', 'total' ) as $key ) {
			$this->assertArrayHasKey(
				$key,
				$response['data']['progress'],
				"The progress payload does not contain '{$key}'."
			);
		}
	}

	/**
	 * A site larger than one batch is scanned across several polls.
	 *
	 * Every other scan test fits in a single batch, which means the cursor never
	 * actually advances between requests — the one thing the batched design
	 * exists to get right. This drives a scan that needs several round trips and
	 * checks the whole site is covered exactly once.
	 */
	public function test_a_multi_batch_scan_covers_the_site_exactly_once() {
		$this->become_admin();

		$batch = Open_Accessibility_Scanner::BATCH_SIZE;
		$total = ( $batch * 2 ) + 5;

		$ids = self::factory()->post->create_many( $total, array( 'post_content' => $this->image_without_alt() ) );

		$nonce = $this->nonce();

		$response = $this->dispatch(
			'open_accessibility_start_scan',
			array( 'nonce' => $nonce, 'force' => 'true' )
		);

		$this->assertTrue( $response['success'] );

		$polls = 0;

		while ( ! empty( $response['data']['progress']['running'] ) && ++$polls < 20 ) {
			$response = $this->dispatch( 'open_accessibility_scan_progress', array( 'nonce' => $nonce ) );
		}

		$this->assertGreaterThan(
			1,
			$polls,
			'A site this size must take more than one poll, or the test proves nothing.'
		);

		$this->assertFalse( $response['data']['progress']['running'] );
		$this->assertTrue( $response['data']['progress']['finished'] );

		$this->assertSame(
			$total,
			$response['data']['progress']['scanned'],
			'Every post should have been examined exactly once.'
		);

		$totals = Open_Accessibility_Scanner::get_totals();

		$this->assertSame( $total, $totals['posts_scanned'] );
		$this->assertSame( $total, $totals['posts_with_issues'] );
		$this->assertSame(
			$total,
			Open_Accessibility_Scanner::count_posts_with_findings(),
			'Every post should appear in the report as a row.'
		);

		// And every created post really was scanned, not just counted.
		$scanned = Open_Accessibility_Scanner::all_scanned_ids();

		sort( $ids );
		sort( $scanned );

		$this->assertSame( $ids, $scanned, 'The scanned set should be exactly the published set.' );
	}

	/**
	 * Starting a scan queues a background batch as well as running one.
	 *
	 * Without the queue, a scan stops wherever the last poll left it if the user
	 * closes the tab. Without the handoff flag it would keep running alongside
	 * the browser instead, and the two would overwrite each other.
	 */
	public function test_starting_a_scan_queues_a_background_batch() {
		$this->become_admin();

		// More posts than one batch, so the scan genuinely has work left after
		// the batch this request runs. A site small enough to finish in one batch
		// has nothing to queue.
		$batch = Open_Accessibility_Scanner::BATCH_SIZE;

		self::factory()->post->create_many( $batch + 5, array( 'post_content' => $this->image_without_alt() ) );

		wp_clear_scheduled_hook( 'open_accessibility_scan_batch' );
		Open_Accessibility_Scanner::clear_progress();

		$response = $this->dispatch(
			'open_accessibility_start_scan',
			array( 'nonce' => $this->nonce(), 'force' => 'true' )
		);

		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['progress']['running'], 'Work should remain after one batch.' );

		// A batch is queued to pick the scan up if nobody keeps polling. The
		// cursor is part of the arguments, so it is also what keeps each queued
		// batch distinct.
		$queued = wp_next_scheduled( 'open_accessibility_scan_batch', array( (int) $response['data']['progress']['cursor'] ) );

		$this->assertNotFalse(
			$queued,
			'Starting a scan with work left should queue a background batch.'
		);

		// And the browser has already taken it over, so the queued batch will
		// stand down rather than scan the same posts again.
		$this->assertFalse(
			Open_Accessibility_Scanner::get_progress()['background'],
			'A poll should take ownership of the scan from the background.'
		);

		// The queued batch is a no-op against a scan the browser now owns.
		$scanned = Open_Accessibility_Scanner::get_progress()['scanned'];

		Open_Accessibility_Scanner::run_scheduled_batch( (int) $response['data']['progress']['cursor'] );

		$this->assertSame(
			$scanned,
			Open_Accessibility_Scanner::get_progress()['scanned'],
			'The queued batch must not rescan what the browser already scanned.'
		);

		wp_clear_scheduled_hook( 'open_accessibility_scan_batch' );
		Open_Accessibility_Scanner::clear_progress();
	}

	/**
	 * Rescanning one post replaces its stored result.
	 */
	public function test_rescan_post_reports_the_new_count() {
		$this->become_admin();

		$post_id = self::factory()->post->create( array( 'post_content' => $this->image_without_alt() ) );

		// Store a stale result the rescan should overwrite.
		update_post_meta( $post_id, Open_Accessibility_Scanner::META_RESULT, array( 'findings' => array() ) );
		update_post_meta( $post_id, Open_Accessibility_Scanner::META_COUNT, 0 );

		$response = $this->dispatch(
			'open_accessibility_rescan_post',
			array( 'nonce' => $this->nonce(), 'post_id' => $post_id )
		);

		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['count'], 'The rescan should find the missing alt text.' );
	}

	/**
	 * Rescanning a post that does not exist fails cleanly.
	 */
	public function test_rescan_post_rejects_an_unknown_post() {
		$this->become_admin();

		$response = $this->dispatch(
			'open_accessibility_rescan_post',
			array( 'nonce' => $this->nonce(), 'post_id' => 999999 )
		);

		$this->assertFalse( $response['success'] );
	}

	/**
	 * Rescanning is per-post, not open to anyone who can reach the screen.
	 *
	 * The report's own capability check would let this user through, so the
	 * per-post check is the only thing that can refuse them. The message is
	 * asserted too: a capability refusal would be indistinguishable from a
	 * pass here if the test only looked at the success flag.
	 */
	public function test_rescan_post_requires_permission_on_that_post() {
		$owner = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );

		$user = get_user_by( 'id', $user_id );
		$user->add_cap( 'manage_options' );

		// current_user_can() reads the cached global user, which was built
		// before add_cap() ran, so it has to be replaced.
		wp_set_current_user( 0 );
		wp_set_current_user( $user_id );
		clean_user_cache( $user_id );

		$this->assertTrue(
			current_user_can( 'manage_options' ),
			'The setup relies on this user passing the report capability check.'
		);

		$post_id = self::factory()->post->create(
			array(
				'post_author'  => $owner,
				'post_content' => $this->image_without_alt(),
			)
		);

		$this->assertFalse(
			current_user_can( 'edit_post', $post_id ),
			'The setup relies on this user being unable to edit that post.'
		);

		$response = $this->dispatch(
			'open_accessibility_rescan_post',
			array( 'nonce' => $this->nonce(), 'post_id' => $post_id )
		);

		$this->assertFalse(
			$response['success'],
			'A user who cannot edit the post should not be able to rescan it.'
		);

		$this->assertStringNotContainsString(
			'permission',
			strtolower( $response['data']['message'] ),
			'The refusal should come from the per-post check, not the capability check.'
		);
	}
}
