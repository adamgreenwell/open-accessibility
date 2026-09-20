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
		$this->assertSame( 3, $response['data']['totals']['posts_with_issues'] );
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
