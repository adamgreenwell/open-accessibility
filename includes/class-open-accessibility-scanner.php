<?php
/**
 * Site-wide content audit scanning and storage.
 *
 * The rules themselves live in Open_Accessibility_Audit, which the editor panel
 * also uses through a JavaScript implementation of the same rules. This class is
 * the part that only the site report needs: reading posts in batches, caching
 * each result against the content it was derived from, and aggregating.
 *
 * Storage choices worth knowing before changing anything here:
 *
 * - Results are cached in post meta keyed by a hash of the content they describe.
 *   A post whose content has not changed is never rescanned, and a post whose
 *   content has changed cannot serve a stale result.
 * - Scan progress lives in a non-autoloaded option. Transients are not used for
 *   either purpose: with an external object cache they are written only to the
 *   cache and never to the database, so they are not durable, and an infinite
 *   transient is autoloaded and joins every request site-wide.
 * - The report is read with a bounded query and never filtered on a
 *   leading-wildcard meta_value comparison, which cannot use an index.
 *
 * The class takes no interest in request context: no $_POST, no current user, no
 * AJAX. It runs from cron, from the admin, and from tests alike.
 *
 * Known gaps, stated rather than silently missed: parse_blocks() understands
 * block markup but not shortcodes, and not arbitrary HTML written inside a
 * core/html block. Both are reported as unscanned by the audit rather than
 * assumed clean.
 *
 * @since      1.4.2
 * @package    Open_Accessibility
 */

class Open_Accessibility_Scanner {

	/**
	 * Post meta key holding the cached result.
	 */
	const META_RESULT = '_oa_audit_result';

	/**
	 * Post meta key holding the content hash the result was derived from.
	 */
	const META_HASH = '_oa_audit_hash';

	/**
	 * Post meta key holding the issue count, for sorting.
	 */
	const META_COUNT = '_oa_audit_count';

	/**
	 * Option holding scan progress. Deliberately not autoloaded.
	 */
	const OPTION_PROGRESS = 'open_accessibility_scan_state';

	/**
	 * How many posts to scan per batch.
	 *
	 * Small enough that a batch finishes well inside a shared host's time limit,
	 * large enough that a few thousand posts do not take hundreds of requests.
	 */
	const BATCH_SIZE = 25;

	/**
	 * Post types the report covers.
	 *
	 * Filterable because a site with custom types will almost certainly want them
	 * included, and the plugin cannot guess which ones hold public content.
	 *
	 * @since    1.4.2
	 * @return   string[]
	 */
	public static function get_post_types() {
		/**
		 * Filter the post types the content report scans.
		 *
		 * @since 1.4.2
		 * @param string[] $post_types Post type names.
		 */
		return apply_filters( 'open_accessibility_report_post_types', array( 'post', 'page' ) );
	}

	/**
	 * Scan a single post and cache the result.
	 *
	 * @since    1.4.2
	 * @param    int       $post_id    Post ID.
	 * @param    bool      $force      Rescan even when the content is unchanged.
	 * @return   array|null    The stored result, or null when the post is missing.
	 */
	public static function scan_post( $post_id, $force = false ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return null;
		}

		$hash = self::hash_content( $post->post_content );

		if ( ! $force && get_post_meta( $post->ID, self::META_HASH, true ) === $hash ) {
			$cached = get_post_meta( $post->ID, self::META_RESULT, true );

			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$result = Open_Accessibility_Audit::scan_content( $post->post_content );

		// The unchecked-category list is identical for every post, so it is not
		// stored per post; only what varies is.
		$stored = array(
			'findings'  => $result['findings'],
			'unscanned' => $result['unscanned'],
			'summary'   => $result['summary'],
			'scanned_at' => current_time( 'mysql' ),
		);

		update_post_meta( $post->ID, self::META_RESULT, $stored );
		update_post_meta( $post->ID, self::META_HASH, $hash );
		update_post_meta( $post->ID, self::META_COUNT, self::count_findings( $stored ) );

		return $stored;
	}

	/**
	 * A stable hash of the content a result describes.
	 *
	 * Hashed rather than compared directly: post content can be long, and the
	 * point is only to notice change.
	 *
	 * @since    1.4.2
	 * @param    string    $content Post content.
	 * @return   string
	 */
	public static function hash_content( $content ) {
		return md5( (string) $content );
	}

	/**
	 * Number of findings in a stored result.
	 *
	 * @since    1.4.2
	 * @param    array     $result Stored result.
	 * @return   int
	 */
	public static function count_findings( $result ) {
		if ( ! is_array( $result ) || empty( $result['findings'] ) ) {
			return 0;
		}

		return count( $result['findings'] );
	}

	/**
	 * Read a stored result for a post.
	 *
	 * @since    1.4.2
	 * @param    int       $post_id Post ID.
	 * @return   array|null
	 */
	public static function get_result( $post_id ) {
		$stored = get_post_meta( $post_id, self::META_RESULT, true );

		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Forget a post's cached result.
	 *
	 * @since    1.4.2
	 * @param    int       $post_id Post ID.
	 */
	public static function forget_post( $post_id ) {
		delete_post_meta( $post_id, self::META_RESULT );
		delete_post_meta( $post_id, self::META_HASH );
		delete_post_meta( $post_id, self::META_COUNT );
	}

	/**
	 * Count the posts the report covers.
	 *
	 * @since    1.4.2
	 * @return   int
	 */
	public static function count_scannable_posts() {
		$total = 0;

		foreach ( self::get_post_types() as $type ) {
			$counts = wp_count_posts( $type );

			foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
				if ( isset( $counts->$status ) ) {
					$total += (int) $counts->$status;
				}
			}
		}

		return $total;
	}

	/**
	 * A page of posts to scan, as ids.
	 *
	 * Ordered by ID with an offset rather than by meta value: a meta_value sort
	 * over an unindexed longtext column forces a filesort and cannot use the
	 * limit, which is the documented reason to avoid it.
	 *
	 * @since    1.4.2
	 * @param    int       $offset Offset.
	 * @param    int       $limit  Batch size.
	 * @return   int[]
	 */
	public static function get_batch( $offset, $limit = self::BATCH_SIZE ) {
		$ids = get_posts(
			array(
				'post_type'              => self::get_post_types(),
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => max( 1, (int) $limit ),
				'offset'                 => max( 0, (int) $offset ),
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		return array_map( 'intval', $ids );
	}

	/**
	 * Scan one batch, returning how far it got.
	 *
	 * @since    1.4.2
	 * @param    int       $offset Offset to start from.
	 * @param    int       $limit  Batch size.
	 * @param    bool      $force  Rescan even when content is unchanged.
	 * @return   array    {
	 *     @type int  $offset    Offset this batch started from.
	 *     @type int  $scanned   Posts examined.
	 *     @type int  $next      Offset for the next batch.
	 *     @type bool $complete  Whether the scan has reached the end.
	 * }
	 */
	public static function scan_batch( $offset, $limit = self::BATCH_SIZE, $force = false ) {
		$ids = self::get_batch( $offset, $limit );

		// Bulk work does not benefit from filling the object cache, and on a large
		// site it evicts everything else. Read the previous value first:
		// wp_suspend_cache_addition() returns the *new* state, so unlike
		// wp_suspend_cache_invalidation() it cannot be restored from its own
		// return value.
		$was_suspended = wp_suspend_cache_addition();
		wp_suspend_cache_addition( true );

		try {
			foreach ( $ids as $id ) {
				self::scan_post( $id, $force );
			}
		} finally {
			wp_suspend_cache_addition( $was_suspended );
		}

		$next = $offset + $limit;

		return array(
			'offset'   => (int) $offset,
			'scanned'  => count( $ids ),
			'next'     => $next,
			'complete' => count( $ids ) < $limit,
		);
	}

	/**
	 * Scan every covered post, in batches, in one request.
	 *
	 * Intended for small sites and for tests. Large sites should drive
	 * scan_batch() from the admin or from cron so a single request never has to
	 * finish the whole site.
	 *
	 * @since    1.4.2
	 * @param    bool      $force Rescan even when content is unchanged.
	 * @return   int    Posts scanned.
	 */
	public static function scan_all( $force = false ) {
		$offset  = 0;
		$scanned = 0;

		do {
			$batch    = self::scan_batch( $offset, self::BATCH_SIZE, $force );
			$scanned += $batch['scanned'];
			$offset   = $batch['next'];
		} while ( ! $batch['complete'] );

		return $scanned;
	}

	/**
	 * Posts with findings, worst first.
	 *
	 * Ordered in PHP rather than by a meta_value sort. The set is bounded, and a
	 * SQL sort on post meta cannot use an index, which on a large site is the
	 * difference between a report and a timeout.
	 *
	 * @since    1.4.2
	 * @param    int       $limit  How many rows to return.
	 * @param    int       $offset Offset into the ordered list.
	 * @return   array    List of array( post, result ).
	 */
	public static function get_report( $limit = 50, $offset = 0 ) {
		$ids = self::all_scanned_ids();

		$rows = array();

		foreach ( $ids as $id ) {
			$result = self::get_result( $id );

			if ( null === $result ) {
				continue;
			}

			$count = self::count_findings( $result );

			if ( $count < 1 ) {
				continue;
			}

			$rows[] = array(
				'post_id' => $id,
				'count'   => $count,
				'summary' => isset( $result['summary'] ) ? $result['summary'] : array(),
				'findings' => $result['findings'],
			);
		}

		usort(
			$rows,
			function ( $a, $b ) {
				if ( $a['count'] === $b['count'] ) {
					return $a['post_id'] <=> $b['post_id'];
				}

				return $b['count'] <=> $a['count'];
			}
		);

		$offset = max( 0, (int) $offset );

		if ( $limit < 1 ) {
			return array_slice( $rows, $offset );
		}

		return array_slice( $rows, $offset, (int) $limit );
	}

	/**
	 * Every post ID that has a cached result.
	 *
	 * Walked in batches so the set is not silently limited to whatever fits in one
	 * query. A large site pays a few queries here rather than an unbounded one.
	 *
	 * @since    1.4.2
	 * @return   int[]
	 */
	public static function all_scanned_ids() {
		$ids    = array();
		$offset = 0;

		do {
			$batch = self::get_batch( $offset, 200 );
			$ids   = array_merge( $ids, $batch );
			$offset += 200;
		} while ( count( $batch ) === 200 );

		return $ids;
	}

	/**
	 * Total findings across every scanned post.
	 *
	 * @since    1.4.2
	 * @return   array    Counts by severity, plus totals.
	 */
	public static function get_totals() {
		$totals = array(
			'posts_scanned'  => 0,
			'posts_with_issues' => 0,
			'error'          => 0,
			'warning'        => 0,
			'review'         => 0,
			'findings'       => 0,
		);

		foreach ( self::all_scanned_ids() as $id ) {
			$result = self::get_result( $id );

			if ( null === $result ) {
				continue;
			}

			$totals['posts_scanned']++;

			$count = self::count_findings( $result );

			if ( $count > 0 ) {
				$totals['posts_with_issues']++;
			}

			$totals['findings'] += $count;

			foreach ( array( 'error', 'warning', 'review' ) as $severity ) {
				if ( isset( $result['summary'][ $severity ] ) ) {
					$totals[ $severity ] += (int) $result['summary'][ $severity ];
				}
			}
		}

		return $totals;
	}

	/**
	 * Record scan progress.
	 *
	 * Stored in a non-autoloaded option rather than a transient: it has to survive
	 * a request, and it must not be loaded on every page of the site.
	 *
	 * @since    1.4.2
	 * @param    array     $state Progress state.
	 */
	public static function set_progress( $state ) {
		update_option( self::OPTION_PROGRESS, $state, false );
	}

	/**
	 * Read scan progress.
	 *
	 * @since    1.4.2
	 * @return   array
	 */
	public static function get_progress() {
		$state = get_option( self::OPTION_PROGRESS, array() );

		if ( ! is_array( $state ) ) {
			$state = array();
		}

		return array_merge(
			array(
				'offset'   => 0,
				'total'    => 0,
				'scanned'  => 0,
				'running'  => false,
				'finished' => false,
				'force'    => false,
			),
			$state
		);
	}

	/**
	 * Clear scan progress.
	 *
	 * @since    1.4.2
	 */
	public static function clear_progress() {
		delete_option( self::OPTION_PROGRESS );
	}

	/**
	 * Schedule the next batch.
	 *
	 * The arguments include the offset, which is what keeps each event distinct.
	 * wp_schedule_single_event() deduplicates on the hook and a hash of its
	 * arguments within a ten-minute window — and it resets that window when the
	 * requested time is inside it, which is the normal case for a batch scheduled
	 * seconds ahead. Identical arguments would therefore be treated as duplicates
	 * and silently dropped, stalling the chain after one hop.
	 *
	 * @since    1.4.2
	 * @param    int       $offset Offset to continue from.
	 * @return   bool    Whether the event was scheduled.
	 */
	public static function schedule_next_batch( $offset ) {
		$args = array( (int) $offset );

		if ( wp_next_scheduled( 'open_accessibility_scan_batch', $args ) ) {
			return false;
		}

		return (bool) wp_schedule_single_event( time() + 1, 'open_accessibility_scan_batch', $args );
	}

	/**
	 * Run a scheduled batch and queue the next one.
	 *
	 * @since    1.4.2
	 * @param    int       $offset Offset to continue from.
	 */
	public static function run_scheduled_batch( $offset = 0 ) {
		$progress = self::get_progress();
		$batch    = self::scan_batch( (int) $offset, self::BATCH_SIZE, ! empty( $progress['force'] ) );

		$progress['offset']  = $batch['next'];
		$progress['scanned'] = (int) $progress['scanned'] + $batch['scanned'];
		$progress['running'] = ! $batch['complete'];
		$progress['finished'] = $batch['complete'];

		self::set_progress( $progress );

		if ( ! $batch['complete'] ) {
			self::schedule_next_batch( $batch['next'] );
		}
	}

	/**
	 * Register the cron hook.
	 *
	 * @since    1.4.2
	 */
	public static function init() {
		add_action( 'open_accessibility_scan_batch', array( __CLASS__, 'run_scheduled_batch' ) );
		add_action( 'save_post', array( __CLASS__, 'invalidate_on_save' ), 10, 1 );
	}

	/**
	 * Drop a post's cached result when it is saved.
	 *
	 * The content hash would catch this on the next scan anyway, but clearing on
	 * save means the report reflects an edit immediately rather than after the
	 * next full scan.
	 *
	 * @since    1.4.2
	 * @param    int       $post_id Post ID.
	 */
	public static function invalidate_on_save( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		self::forget_post( $post_id );
	}

	/**
	 * Remove everything the report stores.
	 *
	 * Called from uninstall only. Deliberately does not touch any table it does
	 * not own — in particular not Action Scheduler's, whose tables belong to the
	 * site rather than to this plugin and may be in use by another plugin that
	 * bundles it.
	 *
	 * @since    1.4.2
	 * @return   int    Rows removed.
	 */
	public static function uninstall() {
		global $wpdb;

		self::clear_progress();

		$removed = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s, %s)",
				self::META_RESULT,
				self::META_HASH,
				self::META_COUNT
			)
		);

		// Any scheduled batches go too.
		wp_clear_scheduled_hook( 'open_accessibility_scan_batch' );

		return $removed === false ? 0 : (int) $removed;
	}
}
