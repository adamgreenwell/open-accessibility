<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The site-wide audit report screen.
 *
 * Part of the admin layer: it owns the capability checks and nonces that the
 * scanner deliberately does not, and it never scans anything itself. All the
 * work is behind Open_Accessibility_Scanner so the report and the scheduled
 * batches cannot drift apart.
 *
 * @since      1.4.2
 * @package    Open_Accessibility
 */

class Open_Accessibility_Report {

	/**
	 * Capability required to view the report and run a scan.
	 *
	 * @since    1.4.2
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Menu slug for the report screen.
	 *
	 * @since    1.4.2
	 */
	const PAGE_SLUG = 'open-accessibility-report';

	/**
	 * Nonce action shared by the report's AJAX handlers.
	 *
	 * @since    1.4.2
	 */
	const NONCE_ACTION = 'open_accessibility_report';

	/**
	 * Rows shown per page of the report.
	 *
	 * @since    1.4.2
	 */
	const PER_PAGE = 20;

	/**
	 * Register the report hooks.
	 *
	 * @since    1.4.2
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_report_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'wp_ajax_open_accessibility_start_scan', array( __CLASS__, 'ajax_start_scan' ) );
		add_action( 'wp_ajax_open_accessibility_scan_progress', array( __CLASS__, 'ajax_scan_progress' ) );
		add_action( 'wp_ajax_open_accessibility_rescan_post', array( __CLASS__, 'ajax_rescan_post' ) );
	}

	/**
	 * Add the report as a submenu of the Accessibility menu.
	 *
	 * @since    1.4.2
	 */
	public static function add_report_page() {
		add_submenu_page(
			'open-accessibility-settings',
			__( 'Accessibility Report', 'open-accessibility' ),
			__( 'Report', 'open-accessibility' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'display_report_page' )
		);
	}

	/**
	 * Load the report styles and script, on the report screen only.
	 *
	 * @since    1.4.2
	 * @param    string    $hook Current admin page.
	 */
	public static function enqueue_assets( $hook ) {
		// Submenu hooks are the parent slug with the child slug appended.
		if ( false === strpos( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'open-accessibility-report',
			OPEN_ACCESSIBILITY_ASSETS_URL . 'css/open-accessibility-report.css',
			array(),
			OPEN_ACCESSIBILITY_VERSION,
			'all'
		);

		wp_enqueue_script(
			'open-accessibility-report',
			OPEN_ACCESSIBILITY_ASSETS_URL . 'js/open-accessibility-report.js',
			array(),
			OPEN_ACCESSIBILITY_VERSION,
			true
		);

		wp_set_script_translations(
			'open-accessibility-report',
			'open-accessibility',
			OPEN_ACCESSIBILITY_PLUGIN_DIR . 'languages'
		);

		wp_localize_script(
			'open-accessibility-report',
			'open_accessibility_report',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'pageSlug' => self::PAGE_SLUG,
				'i18n'     => array(
					'starting'      => __( 'Starting scan...', 'open-accessibility' ),
					'scanning'      => __( 'Scanning...', 'open-accessibility' ),
					'rescanning'    => __( 'Rescanning...', 'open-accessibility' ),
					'complete'      => __( 'Scan complete.', 'open-accessibility' ),
					'failed'        => __( 'The scan could not be started. Please try again.', 'open-accessibility' ),
					/* translators: 1: posts scanned, 2: total posts. */
					'progress'      => __( '%1$s of %2$s posts scanned', 'open-accessibility' ),
					'cancel'        => __( 'Cancel', 'open-accessibility' ),
					'rescan'        => __( 'Rescan', 'open-accessibility' ),
					'confirmRescan' => __( 'Rescan this post? Its stored result will be replaced.', 'open-accessibility' ),
				),
			)
		);
	}

	/**
	 * Render the report screen.
	 *
	 * @since    1.4.2
	 */
	public static function display_report_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view this report.', 'open-accessibility' ) );
		}

		$progress = Open_Accessibility_Scanner::get_progress();
		$totals   = Open_Accessibility_Scanner::get_totals();
		$page     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$rows     = Open_Accessibility_Scanner::get_report( self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );

		include OPEN_ACCESSIBILITY_PLUGIN_DIR . 'admin/partials/report-display.php';
	}

	/**
	 * Whether a scan has ever finished.
	 *
	 * Distinguishes "nothing found" from "nothing looked at", which need
	 * different messages: an empty report before the first scan is not a clean
	 * bill of health.
	 *
	 * @since    1.4.2
	 * @param    array      $progress Scan progress.
	 * @param    array|null $totals   Totals already fetched, if the caller has
	 *                                them. Counting totals walks every scanned
	 *                                post, so the report passes its own.
	 * @return   bool
	 */
	public static function has_scanned( $progress, $totals = null ) {
		if ( ! empty( $progress['finished'] ) ) {
			return true;
		}

		if ( null === $totals ) {
			$totals = Open_Accessibility_Scanner::get_totals();
		}

		return ! empty( $totals['posts_scanned'] );
	}

	/**
	 * Begin a site scan.
	 *
	 * The first batch runs inside this request rather than being handed to
	 * WP-Cron. That is deliberate: spawn_cron() returns early when DOING_AJAX is
	 * set, so a cron-driven scan would sit untouched for as long as the browser
	 * kept polling — which is exactly when the user is watching it. Driving each
	 * batch from the poll instead keeps the visible and actual progress the same
	 * number.
	 *
	 * @since    1.4.2
	 */
	public static function ajax_start_scan() {
		self::verify_request();

		$progress = Open_Accessibility_Scanner::get_progress();

		// Resume rather than restart: a scan already in flight is the one the
		// user is asking about, and starting over would discard its progress.
		if ( ! empty( $progress['running'] ) ) {
			wp_send_json_success( self::scan_state( $progress ) );
		}

		$force = isset( $_POST['force'] ) && 'true' === sanitize_text_field( wp_unslash( $_POST['force'] ) );

		$progress = array(
			'cursor'   => 0,
			'total'    => Open_Accessibility_Scanner::count_scannable_posts(),
			'scanned'  => 0,
			'running'  => true,
			'finished' => false,
			'force'    => $force,
		);

		wp_send_json_success( self::scan_state( self::advance_scan( $progress ) ) );
	}

	/**
	 * Carry the scan forward by one batch and report where it got to.
	 *
	 * @since    1.4.2
	 * @param    array     $progress Current progress.
	 * @return   array    Updated progress.
	 */
	private static function advance_scan( $progress ) {
		$batch = Open_Accessibility_Scanner::scan_batch(
			(int) $progress['cursor'],
			Open_Accessibility_Scanner::BATCH_SIZE,
			! empty( $progress['force'] )
		);

		$progress['cursor']   = $batch['next'];
		$progress['scanned']  = (int) $progress['scanned'] + $batch['scanned'];
		$progress['running']  = ! $batch['complete'];
		$progress['finished'] = $batch['complete'];

		Open_Accessibility_Scanner::set_progress( $progress );

		return $progress;
	}

	/**
	 * The scan's state plus the figures the report shows.
	 *
	 * @since    1.4.2
	 * @param    array     $progress Current progress.
	 * @return   array
	 */
	private static function scan_state( $progress ) {
		return array(
			'progress' => $progress,
			'totals'   => Open_Accessibility_Scanner::get_totals(),
		);
	}

	/**
	 * Take the next step of a running scan, and report progress.
	 *
	 * Safe to call when nothing is running: it reports the finished state and
	 * does no work.
	 *
	 * @since    1.4.2
	 */
	public static function ajax_scan_progress() {
		self::verify_request();

		$progress = Open_Accessibility_Scanner::get_progress();

		if ( ! empty( $progress['running'] ) ) {
			$progress = self::advance_scan( $progress );
		}

		wp_send_json_success( self::scan_state( $progress ) );
	}

	/**
	 * Rescan a single post.
	 *
	 * @since    1.4.2
	 */
	public static function ajax_rescan_post() {
		self::verify_request();

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'That post could not be found.', 'open-accessibility' ) ) );
		}

		$result = Open_Accessibility_Scanner::scan_post( $post_id, true );

		if ( null === $result ) {
			wp_send_json_error( array( 'message' => __( 'That post could not be scanned.', 'open-accessibility' ) ) );
		}

		wp_send_json_success(
			array(
				'count'  => Open_Accessibility_Scanner::count_findings( $result ),
				'totals' => Open_Accessibility_Scanner::get_totals(),
				'rows'   => self::report_rows_for_response(),
			)
		);
	}

	/**
	 * The first page of report rows, shaped for the script.
	 *
	 * @since    1.4.2
	 * @return   array
	 */
	private static function report_rows_for_response() {
		$rows = array();

		foreach ( Open_Accessibility_Scanner::get_report( self::PER_PAGE, 0 ) as $row ) {
			$post = get_post( $row['post_id'] );

			if ( ! $post ) {
				continue;
			}

			$rows[] = array(
				'post_id'  => $row['post_id'],
				'title'    => get_the_title( $post ),
				'editUrl'  => get_edit_post_link( $row['post_id'], 'raw' ),
				'viewUrl'  => get_permalink( $row['post_id'] ),
				'count'    => $row['count'],
				'summary'  => $row['summary'],
				'findings' => self::describe_findings( $row['findings'] ),
			);
		}

		return $rows;
	}

	/**
	 * Turn stored findings into display strings.
	 *
	 * The stored result holds rule ids and block paths, not sentences. Resolving
	 * them here rather than at scan time means a reworded message shows up
	 * without every post needing to be scanned again.
	 *
	 * @since    1.4.2
	 * @param    array     $findings Stored findings.
	 * @return   array
	 */
	public static function describe_findings( $findings ) {
		$definitions = Open_Accessibility_Audit::rule_definitions();
		$described   = array();

		foreach ( (array) $findings as $finding ) {
			if ( ! is_array( $finding ) || ! isset( $finding['rule'] ) ) {
				continue;
			}

			$rule = $finding['rule'];

			$described[] = array(
				'rule'     => $rule,
				'message'  => isset( $definitions[ $rule ]['message'] ) ? $definitions[ $rule ]['message'] : $rule,
				'wcag'     => isset( $definitions[ $rule ]['wcag'] ) ? $definitions[ $rule ]['wcag'] : '',
				'severity' => isset( $finding['severity'] ) ? $finding['severity'] : 'warning',
				'block'    => isset( $finding['block'] ) ? $finding['block'] : '',
			);
		}

		return $described;
	}

	/**
	 * Scan progress as a whole percentage.
	 *
	 * Clamped to 0-100 because the total is counted before the scan runs and a
	 * post published mid-scan can push the scanned count past it.
	 *
	 * @since    1.4.2
	 * @param    array     $progress Scan progress.
	 * @return   int
	 */
	public static function percent( $progress ) {
		$total = isset( $progress['total'] ) ? (int) $progress['total'] : 0;

		if ( $total < 1 ) {
			return 0;
		}

		$scanned = isset( $progress['scanned'] ) ? (int) $progress['scanned'] : 0;

		return (int) max( 0, min( 100, round( ( $scanned / $total ) * 100 ) ) );
	}

	/**
	 * A severity label for display.
	 *
	 * @since    1.4.2
	 * @param    string    $severity Severity slug.
	 * @return   string
	 */
	public static function severity_label( $severity ) {
		$labels = array(
			'error'   => __( 'Error', 'open-accessibility' ),
			'warning' => __( 'Warning', 'open-accessibility' ),
			'review'  => __( 'Needs review', 'open-accessibility' ),
		);

		return isset( $labels[ $severity ] ) ? $labels[ $severity ] : $severity;
	}

	/**
	 * Reject a request that fails its nonce or capability check.
	 *
	 * Both are checked on every handler. The nonce proves the request came from
	 * this screen; the capability check is the one that actually matters, since
	 * a nonce is not an authorisation.
	 *
	 * @since    1.4.2
	 */
	private static function verify_request() {
		if ( ! check_ajax_referer( self::NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'open-accessibility' ) ), 403 );
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'open-accessibility' ) ), 403 );
		}
	}
}
