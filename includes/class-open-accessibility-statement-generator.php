<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Accessibility Statement Generator
 *
 * Produces an accessibility statement following the W3C's statement guidance:
 * https://www.w3.org/WAI/planning/statements/
 *
 * The thing this class exists to avoid is the unearned claim. A statement that
 * asserts conformance the site has not demonstrated is worse than no statement
 * at all — it misleads the people the statement is supposed to help, and it is
 * the specific practice regulators have acted on in this category. So every
 * sentence here is either supplied by the person filling the form or derived
 * from something the plugin actually measured, and where the plugin has not
 * measured anything the statement says so.
 *
 * The class is a pure string builder. It reads no request state, emits no
 * response and checks no nonce: those belong to the controller that has a
 * request. That keeps it callable from a test, a CLI command or a cron job.
 *
 * @since      1.4.2
 * @package    Open_Accessibility
 */

class Open_Accessibility_Statement_Generator {

	/**
	 * WCAG versions offered.
	 *
	 * 2.2 first because it is the current recommendation; 2.0 is kept for sites
	 * whose procurement or legal basis still names it.
	 *
	 * @var string[]
	 */
	const STANDARDS = array( '2.2', '2.1', '2.0' );

	/**
	 * Conformance levels offered.
	 *
	 * `none` is the "not assessed" choice and is deliberately first: for a site
	 * that has not been assessed it is the only honest answer, and offering it
	 * as the default makes it the easy one to pick rather than the exception.
	 *
	 * @var string[]
	 */
	const LEVELS = array( 'none', 'A', 'AA', 'AAA' );

	/**
	 * Assessment methods offered.
	 *
	 * @var string[]
	 */
	const METHODS = array( 'self', 'third_party', 'both', 'none' );

	/**
	 * Build an accessibility statement.
	 *
	 * @since    1.4.2
	 * @param    array    $data {
	 *     @type string $org_name         Organisation or site name.
	 *     @type string $website_url      Site URL.
	 *     @type string $contact_email    Contact address for feedback.
	 *     @type string $standard         WCAG version, one of STANDARDS.
	 *     @type string $conformance      Level, one of LEVELS. `none` means not assessed.
	 *     @type string $scope            Free text describing what is covered.
	 *     @type string $assessment       One of METHODS.
	 *     @type string $assessment_date  Date the assessment was made, as a string.
	 *     @type bool   $include_report   Whether to cite site report data.
	 * }
	 * @return   string    HTML content of the accessibility statement.
	 */
	public static function generate_statement( $data ) {
		$data = self::normalize( $data );

		$statement = '<div class="accessibility-statement">';

		$statement .= self::section_intro( $data );
		$statement .= self::section_conformance( $data );
		$statement .= self::section_scope( $data );
		$statement .= self::section_limitations( $data );
		$statement .= self::section_assessment( $data );
		$statement .= self::section_feedback( $data );
		$statement .= self::section_technical();
		$statement .= self::section_date( $data );

		$statement .= '</div>';

		/**
		 * Filter the generated accessibility statement.
		 *
		 * @since 1.4.2
		 * @param string $statement Generated HTML.
		 * @param array  $data      Normalised statement data.
		 */
		return apply_filters( 'open_accessibility_statement', $statement, $data );
	}

	/**
	 * Fill in and constrain the incoming data.
	 *
	 * Every field is constrained to something the class can reason about, so a
	 * caller cannot produce a statement that claims a version or a level that
	 * does not exist.
	 *
	 * @since    1.4.2
	 * @param    array    $data Raw data.
	 * @return   array    Normalised data.
	 */
	private static function normalize( $data ) {
		$data = wp_parse_args(
			(array) $data,
			array(
				'org_name'        => get_bloginfo( 'name' ),
				'website_url'     => site_url(),
				'contact_email'   => get_bloginfo( 'admin_email' ),
				'standard'        => '2.2',
				'conformance'     => 'none',
				'scope'           => '',
				'assessment'      => 'self',
				'assessment_date' => '',
				'include_report'  => true,
			)
		);

		$data['org_name']      = sanitize_text_field( $data['org_name'] );
		$data['website_url']   = esc_url_raw( $data['website_url'] );
		$data['contact_email'] = sanitize_email( $data['contact_email'] );
		$data['scope']         = sanitize_textarea_field( $data['scope'] );
		$data['assessment_date'] = sanitize_text_field( $data['assessment_date'] );
		$data['include_report']  = (bool) $data['include_report'];

		if ( ! in_array( $data['standard'], self::STANDARDS, true ) ) {
			$data['standard'] = '2.2';
		}

		if ( ! in_array( $data['conformance'], self::LEVELS, true ) ) {
			$data['conformance'] = 'none';
		}

		if ( ! in_array( $data['assessment'], self::METHODS, true ) ) {
			$data['assessment'] = 'self';
		}

		return $data;
	}

	/**
	 * Whether the site has been assessed by the plugin at all.
	 *
	 * @since    1.4.2
	 * @return   bool
	 */
	public static function has_report_data() {
		return Open_Accessibility_Scanner::count_posts_with_findings() > 0
			|| Open_Accessibility_Scanner::get_totals()['posts_scanned'] > 0;
	}

	/**
	 * The known limitations, as real categories with real counts.
	 *
	 * Derived from the site report rather than written as boilerplate. A generic
	 * "third-party content may not be accessible" paragraph is true of every site
	 * and therefore tells a reader nothing; "3 pages have images with no
	 * alternative text" is checkable.
	 *
	 * @since    1.4.2
	 * @return   array {
	 *     @type array $checked   Rule id => array( message, wcag, severity, count ).
	 *     @type array $unchecked Categories the audit cannot examine.
	 *     @type int   $posts     Posts with findings.
	 * }
	 */
	public static function known_limitations() {
		$totals   = Open_Accessibility_Scanner::get_totals();
		$by_rule  = array();
		$defs     = Open_Accessibility_Audit::rule_definitions();

		foreach ( Open_Accessibility_Scanner::all_scanned_ids() as $id ) {
			$result = Open_Accessibility_Scanner::get_result( $id );

			if ( null === $result || empty( $result['findings'] ) ) {
				continue;
			}

			foreach ( $result['findings'] as $finding ) {
				$rule = isset( $finding['rule'] ) ? $finding['rule'] : '';

				if ( '' === $rule ) {
					continue;
				}

				if ( ! isset( $by_rule[ $rule ] ) ) {
					$by_rule[ $rule ] = array(
						'message'  => isset( $defs[ $rule ]['message'] ) ? $defs[ $rule ]['message'] : $rule,
						'wcag'     => isset( $defs[ $rule ]['wcag'] ) ? $defs[ $rule ]['wcag'] : '',
						'severity' => isset( $finding['severity'] ) ? $finding['severity'] : 'warning',
						'count'    => 0,
					);
				}

				$by_rule[ $rule ]['count']++;
			}
		}

		// Worst first, then by count, so the statement leads with what matters.
		$order = array( 'error' => 0, 'warning' => 1, 'review' => 2 );

		uasort(
			$by_rule,
			function ( $a, $b ) use ( $order ) {
				$a_rank = isset( $order[ $a['severity'] ] ) ? $order[ $a['severity'] ] : 9;
				$b_rank = isset( $order[ $b['severity'] ] ) ? $order[ $b['severity'] ] : 9;

				if ( $a_rank === $b_rank ) {
					return $b['count'] <=> $a['count'];
				}

				return $a_rank <=> $b_rank;
			}
		);

		return array(
			'checked'   => $by_rule,
			'unchecked' => Open_Accessibility_Audit::unchecked_categories(),
			'posts'     => (int) $totals['posts_with_issues'],
		);
	}

	/**
	 * Introduction.
	 *
	 * @since    1.4.2
	 * @param    array    $data Normalised data.
	 * @return   string
	 */
	private static function section_intro( $data ) {
		return '<h1>' . esc_html__( 'Accessibility Statement', 'open-accessibility' ) . '</h1>'
			. '<p>' . sprintf(
				/* translators: %s: organisation name, wrapped in <strong>. */
				esc_html__( '%s is committed to ensuring digital accessibility for people with disabilities. We are continually improving the user experience for everyone, and applying the relevant accessibility standards.', 'open-accessibility' ),
				'<strong>' . esc_html( $data['org_name'] ) . '</strong>'
			) . '</p>';
	}

	/**
	 * Conformance status, including the honest "not assessed" answer.
	 *
	 * @since    1.4.2
	 * @param    array    $data Normalised data.
	 * @return   string
	 */
	private static function section_conformance( $data ) {
		$out = '<h2>' . esc_html__( 'Conformance Status', 'open-accessibility' ) . '</h2>';

		$out .= '<p>' . esc_html__( 'The Web Content Accessibility Guidelines (WCAG) defines requirements for designers and developers to improve accessibility for people with disabilities. It defines three levels of conformance: Level A, Level AA, and Level AAA.', 'open-accessibility' ) . '</p>';

		if ( 'none' === $data['conformance'] ) {
			$out .= '<p><strong>' . sprintf(
				/* translators: %s: WCAG version, e.g. 2.2. */
				esc_html__( 'This website has not been assessed against WCAG %s. No conformance claim is made.', 'open-accessibility' ),
				esc_html( $data['standard'] )
			) . '</strong></p>';

			$out .= '<p>' . esc_html__( 'This statement describes what is known about the site, and is published so that people can tell us where it falls short. It is not a claim that the site conforms.', 'open-accessibility' ) . '</p>';

			return $out;
		}

		$out .= '<p>' . sprintf(
			/* translators: 1: organisation name, 2: WCAG version, 3: conformance level. */
			esc_html__( '%1$s is partially conformant with WCAG %2$s level %3$s. Partially conformant means that some parts of the content do not fully conform to the accessibility standard.', 'open-accessibility' ),
			esc_html( $data['org_name'] ),
			esc_html( $data['standard'] ),
			esc_html( $data['conformance'] )
		) . '</p>';

		// "Partially conformant" is itself a claim, so it is worth saying what it
		// rests on when the plugin has measured nothing.
		if ( ! self::has_report_data() ) {
			$out .= '<p>' . esc_html__( 'This claim has not been verified by an audit run through this plugin. You can run a content report from the Accessibility report screen to see what it finds.', 'open-accessibility' ) . '</p>';
		}

		return $out;
	}

	/**
	 * Scope: what the statement covers.
	 *
	 * @since    1.4.2
	 * @param    array    $data Normalised data.
	 * @return   string
	 */
	private static function section_scope( $data ) {
		$out = '<h2>' . esc_html__( 'Scope', 'open-accessibility' ) . '</h2>';

		if ( '' !== $data['scope'] ) {
			$out .= '<p>' . esc_html( $data['scope'] ) . '</p>';
		} else {
			$out .= '<p>' . sprintf(
				/* translators: %s: site URL. */
				esc_html__( 'This statement applies to the website at %s.', 'open-accessibility' ),
				esc_html( $data['website_url'] )
			) . '</p>';
		}

		$types = Open_Accessibility_Scanner::get_post_types();
		$names = array();

		foreach ( $types as $type ) {
			$object = get_post_type_object( $type );

			if ( $object ) {
				$names[] = $object->labels->name;
			}
		}

		if ( ! empty( $names ) ) {
			$out .= '<p>' . sprintf(
				/* translators: %s: comma-separated list of content types. */
				esc_html__( 'Content covered by our accessibility checks: %s. User-generated content, such as comments and content created by visitors, is outside what we check.', 'open-accessibility' ),
				esc_html( implode( ', ', $names ) )
			) . '</p>';
		}

		return $out;
	}

	/**
	 * Known limitations, from the site report where there is one.
	 *
	 * @since    1.4.2
	 * @param    array    $data Normalised data.
	 * @return   string
	 */
	private static function section_limitations( $data ) {
		$out = '<h2>' . esc_html__( 'Known Limitations', 'open-accessibility' ) . '</h2>';

		$out .= '<p>' . esc_html__( 'Despite our best efforts, there may be some limitations. Below is what we know about, and how to tell us about anything we have missed.', 'open-accessibility' ) . '</p>';

		if ( ! $data['include_report'] ) {
			$out .= '<p>' . esc_html__( 'This statement does not cite automated check results.', 'open-accessibility' ) . '</p>';

			return $out;
		}

		$limitations = self::known_limitations();

		if ( empty( $limitations['checked'] ) ) {
			// Either nothing has been scanned, or a scan found nothing. Both
			// cases mean there is nothing specific to list, and saying so is the
			// honest answer — with the distinction preserved, because "we have
			// not looked" and "we looked and found nothing" are not the same
			// claim.
			$out .= '<p>' . (
				self::has_report_data()
					? esc_html__( 'The automated checks we have run have not found any of the issues they look for.', 'open-accessibility' )
					: esc_html__( 'No automated checks have been run, so no specific limitations can be listed here yet.', 'open-accessibility' )
			) . '</p>';
		} else {
			$out .= '<p>' . sprintf(
				/* translators: %s: number of pages. */
				esc_html__( 'Automated checks found the following on %s of our pages. Each is a known limitation of the content as it stands:', 'open-accessibility' ),
				esc_html( number_format_i18n( $limitations['posts'] ) )
			) . '</p>';

			$out .= '<ul>';

			foreach ( $limitations['checked'] as $rule ) {
				$out .= '<li>' . sprintf(
					/* translators: 1: number of occurrences, 2: what was found, 3: WCAG criterion. */
					esc_html__( '%1$s: %2$s (WCAG %3$s)', 'open-accessibility' ),
					esc_html( number_format_i18n( $rule['count'] ) ),
					esc_html( $rule['message'] ),
					esc_html( $rule['wcag'] )
				) . '</li>';
			}

			$out .= '</ul>';
		}

		// The categories no automated check can decide. Stating them is what
		// keeps the list above from reading as a complete account.
		if ( ! empty( $limitations['unchecked'] ) ) {
			$out .= '<p>' . esc_html__( 'The checks above are automated, and automated checks cannot decide everything. The following have not been checked by them and would each need a person:', 'open-accessibility' ) . '</p>';

			$out .= '<ul>';

			foreach ( $limitations['unchecked'] as $category ) {
				$out .= '<li>' . esc_html( $category ) . '</li>';
			}

			$out .= '</ul>';
		}

		$out .= '<p>' . esc_html__( 'Third-party content that we embed, such as maps, videos or social media feeds, is outside what these checks cover.', 'open-accessibility' ) . '</p>';

		return $out;
	}

	/**
	 * How the site was assessed, and when.
	 *
	 * @since    1.4.2
	 * @param    array    $data Normalised data.
	 * @return   string
	 */
	private static function section_assessment( $data ) {
		$out = '<h2>' . esc_html__( 'Assessment Approach', 'open-accessibility' ) . '</h2>';

		$methods = array(
			'self'        => __( 'Self-evaluation: the site owner assessed the website.', 'open-accessibility' ),
			'third_party' => __( 'Third-party evaluation: an external organisation assessed the website.', 'open-accessibility' ),
			'both'        => __( 'Self-evaluation and a third-party evaluation.', 'open-accessibility' ),
			'none'        => __( 'No assessment has been carried out.', 'open-accessibility' ),
		);

		$out .= '<p>' . esc_html( $methods[ $data['assessment'] ] ) . '</p>';

		if ( 'self' === $data['assessment'] || 'both' === $data['assessment'] ) {
			$out .= '<p>' . esc_html__( 'Self-evaluation can include automated checks, such as the content report this plugin produces, and manual testing that automated checks cannot replace.', 'open-accessibility' ) . '</p>';
		}

		if ( '' !== $data['assessment_date'] ) {
			$out .= '<p>' . sprintf(
				/* translators: %s: date the assessment was made. */
				esc_html__( 'The most recent assessment was made on %s.', 'open-accessibility' ),
				esc_html( $data['assessment_date'] )
			) . '</p>';
		}

		return $out;
	}

	/**
	 * How to give feedback.
	 *
	 * @since    1.4.2
	 * @param    array    $data Normalised data.
	 * @return   string
	 */
	private static function section_feedback( $data ) {
		$out = '<h2>' . esc_html__( 'Feedback', 'open-accessibility' ) . '</h2>';

		$out .= '<p>' . sprintf(
			/* translators: %s: organisation name. */
			esc_html__( 'We welcome your feedback on the accessibility of %s. Please let us know if you encounter accessibility barriers:', 'open-accessibility' ),
			esc_html( $data['org_name'] )
		) . '</p>';

		$out .= '<ul>';

		if ( '' !== $data['contact_email'] ) {
			$out .= '<li>' . sprintf(
				/* translators: %s: contact email address. */
				esc_html__( 'Email: %s', 'open-accessibility' ),
				'<!--email_off--><a href="mailto:' . esc_attr( $data['contact_email'] ) . '">' . esc_html( $data['contact_email'] ) . '</a><!--/email_off-->'
			) . '</li>';
		}

		// Reuses the settings the widget panel links already use, so a site does
		// not have to maintain the same contact details in two places.
		$options = Open_Accessibility_Utils::get_options();

		if ( ! empty( $options['feedback_url'] ) ) {
			$out .= '<li>' . sprintf(
				/* translators: %s: feedback page URL. */
				esc_html__( 'Feedback form: %s', 'open-accessibility' ),
				'<a href="' . esc_url( $options['feedback_url'] ) . '">' . esc_html( $options['feedback_url'] ) . '</a>'
			) . '</li>';
		}

		if ( ! empty( $options['help_url'] ) ) {
			$out .= '<li>' . sprintf(
				/* translators: %s: help page URL. */
				esc_html__( 'Help page: %s', 'open-accessibility' ),
				'<a href="' . esc_url( $options['help_url'] ) . '">' . esc_html( $options['help_url'] ) . '</a>'
			) . '</li>';
		}

		$out .= '</ul>';

		$out .= '<p>' . esc_html__( 'We try to respond to feedback within 2 business days.', 'open-accessibility' ) . '</p>';

		return $out;
	}

	/**
	 * Technical specifications.
	 *
	 * @since    1.4.2
	 * @return   string
	 */
	private static function section_technical() {
		return '<h2>' . esc_html__( 'Technical Specifications', 'open-accessibility' ) . '</h2>'
			. '<p>' . esc_html__( 'Accessibility of this website relies on the following technologies to work with the particular combination of web browser and any assistive technologies or plugins installed on your computer:', 'open-accessibility' ) . '</p>'
			. '<ul><li>HTML</li><li>CSS</li><li>JavaScript</li></ul>'
			. '<p>' . esc_html__( 'These technologies are relied upon for conformance with the accessibility standards used.', 'open-accessibility' ) . '</p>';
	}

	/**
	 * When the statement was created.
	 *
	 * @since    1.4.2
	 * @param    array    $data Normalised data.
	 * @return   string
	 */
	private static function section_date( $data ) {
		$date = date_i18n( get_option( 'date_format' ) );

		return '<h2>' . esc_html__( 'Date', 'open-accessibility' ) . '</h2>'
			. '<p>' . sprintf(
				/* translators: %s: statement creation date. */
				esc_html__( 'This statement was created on %s.', 'open-accessibility' ),
				esc_html( $date )
			) . '</p>';
	}

	/**
	 * Create a page containing the accessibility statement.
	 *
	 * @since    1.0.0
	 * @param    array    $data Statement data.
	 * @return   int|WP_Error The page ID on success, WP_Error on failure.
	 */
	public static function create_statement_page( $data ) {
		$content = self::generate_statement( $data );

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Accessibility Statement', 'open-accessibility' ),
				'post_content' => $content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( ! is_wp_error( $page_id ) ) {
			$options                  = Open_Accessibility_Utils::get_options();
			$options['statement_url'] = get_permalink( $page_id );
			update_option( 'open_accessibility_options', $options );
		}

		return $page_id;
	}

	/**
	 * Clear the stored statement URL when its page is deleted.
	 *
	 * create_statement_page() writes the URL into the plugin options so the
	 * settings field and the widget's panel link can point at it. Nothing undid
	 * that when the page was later deleted, so both kept pointing at a 404.
	 *
	 * Hooked to before_delete_post rather than deleted_post: the permalink is
	 * what is stored, and comparing permalinks needs the post still to exist.
	 *
	 * @since    1.4.2
	 * @param    int       $post_id Post being deleted.
	 */
	public static function clear_statement_url_on_delete( $post_id ) {
		$options = Open_Accessibility_Utils::get_options();

		if ( empty( $options['statement_url'] ) ) {
			return;
		}

		// Compare by resolved post ID rather than by string: the stored URL was
		// captured at creation time, and a permalink structure change or a
		// trailing-slash difference would make a string comparison miss.
		if ( url_to_postid( $options['statement_url'] ) !== (int) $post_id ) {
			return;
		}

		$options['statement_url'] = '';
		update_option( 'open_accessibility_options', $options );
	}
}
