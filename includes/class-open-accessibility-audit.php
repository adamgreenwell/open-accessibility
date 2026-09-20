<?php
/**
 * Content audit rules for the editor panel and the site report.
 *
 * What this checks, and — just as deliberately — what it does not:
 *
 * The rules work on block data. That covers the structural problems an overlay
 * cannot fix and which are therefore the reason this feature exists: missing or
 * unhelpful image alt text, broken heading sequences, vague link text, unlabelled
 * controls, tables without headers.
 *
 * It cannot see colour contrast, focus order, keyboard traps or ARIA correctness,
 * because those need a rendered page and a real engine. It also cannot see inside
 * synced patterns, whose content lives in a separate post. Rather than quietly
 * returning a clean result for content it never looked at, scan_content() returns
 * an `unchecked` list and names the synced patterns it skipped. A clean audit is
 * only meaningful next to a statement of what was not examined.
 *
 * This class is deliberately free of request context: no $_POST, no current user,
 * no AJAX. The Phase 4 site report scans stored content with the same rules, and
 * both need to be callable from a test or a scheduled job.
 *
 * @since      1.4.2
 * @package    Open_Accessibility
 */

class Open_Accessibility_Audit {

	/**
	 * Rule identifiers.
	 *
	 * Stable strings rather than numbers: the panel, the report and any theme
	 * filtering findings all key off these.
	 */
	const RULE_IMAGE_MISSING_ALT  = 'image_missing_alt';
	const RULE_IMAGE_UNHELPFUL_ALT = 'image_alt_unhelpful';
	const RULE_HEADING_SKIPPED    = 'heading_skipped_level';
	const RULE_HEADING_EMPTY      = 'heading_empty';
	const RULE_LINK_UNHELPFUL     = 'link_text_unhelpful';
	const RULE_BUTTON_NO_LABEL    = 'button_missing_label';
	const RULE_TABLE_NO_HEADERS   = 'table_missing_headers';

	/**
	 * Link text that tells a screen reader user nothing about the destination.
	 *
	 * Compared case-insensitively against the whole link text, so a link reading
	 * "read more about accessibility" is not caught — that one is fine.
	 *
	 * @var string[]
	 */
	private static $vague_link_text = array(
		'click here',
		'here',
		'read more',
		'more',
		'learn more',
		'more info',
		'more information',
		'link',
		'this link',
		'details',
		'continue',
		'download',
		'view',
		'see more',
		'website',
		'this',
	);

	/**
	 * Phrases that describe an image as an image.
	 *
	 * A screen reader already announces the element as an image, so "image of a
	 * cat" costs the listener three words and adds nothing.
	 *
	 * @var string[]
	 */
	private static $redundant_alt_prefixes = array(
		'image of ',
		'picture of ',
		'photo of ',
		'photograph of ',
		'graphic of ',
		'icon of ',
		'a picture of ',
		'a photo of ',
		'an image of ',
	);

	/**
	 * WCAG 2.2 criteria each rule maps to.
	 *
	 * @var array
	 */
	private static $criteria = array(
		self::RULE_IMAGE_MISSING_ALT   => '1.1.1',
		self::RULE_IMAGE_UNHELPFUL_ALT => '1.1.1',
		self::RULE_HEADING_SKIPPED     => '1.3.1',
		self::RULE_HEADING_EMPTY       => '1.3.1',
		self::RULE_LINK_UNHELPFUL      => '2.4.4',
		self::RULE_BUTTON_NO_LABEL     => '4.1.2',
		self::RULE_TABLE_NO_HEADERS    => '1.3.1',
	);

	/**
	 * How each rule is phrased in the UI.
	 *
	 * @var array
	 */
	private static $messages = array(
		self::RULE_IMAGE_MISSING_ALT   => 'This image has no alt text. Describe what it shows, or mark it decorative.',
		self::RULE_IMAGE_UNHELPFUL_ALT => 'This image alt text does not describe the image, so it does not help anyone using a screen reader.',
		self::RULE_HEADING_SKIPPED     => 'This heading skips a level, which makes the page outline harder to follow.',
		self::RULE_HEADING_EMPTY       => 'This heading is empty.',
		self::RULE_LINK_UNHELPFUL      => 'This link text does not say where the link goes.',
		self::RULE_BUTTON_NO_LABEL     => 'This button has no label, so it cannot be identified.',
		self::RULE_TABLE_NO_HEADERS    => 'This table has no header cells. Add a header row, or confirm it is only for layout.',
	);

	/**
	 * Severity per rule.
	 *
	 * `review` is used where the markup may well be correct — an empty alt on a
	 * decorative image, a layout table — and only the author can say.
	 *
	 * @var array
	 */
	private static $severity = array(
		self::RULE_IMAGE_MISSING_ALT   => 'error',
		self::RULE_IMAGE_UNHELPFUL_ALT => 'warning',
		self::RULE_HEADING_SKIPPED     => 'warning',
		self::RULE_HEADING_EMPTY       => 'error',
		self::RULE_LINK_UNHELPFUL      => 'warning',
		self::RULE_BUTTON_NO_LABEL     => 'error',
		self::RULE_TABLE_NO_HEADERS    => 'review',
	);

	/**
	 * Scan serialised post content.
	 *
	 * @since    1.4.2
	 * @param    string    $content    Post content, as stored.
	 * @return   array    {
	 *     @type array $findings  List of findings.
	 *     @type array $unscanned Content that could not be examined.
	 *     @type array $unchecked Categories this audit cannot check at all.
	 *     @type array $summary   Counts by severity.
	 * }
	 */
	public static function scan_content( $content ) {
		$findings  = array();
		$unscanned = array();

		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return self::build_result( $findings, $unscanned );
		}

		$blocks = parse_blocks( $content );
		$state  = array( 'last_heading_level' => 0, 'path' => array() );

		self::walk( $blocks, $state, $findings, $unscanned );

		return self::build_result( $findings, $unscanned );
	}

	/**
	 * Walk a block tree, collecting findings.
	 *
	 * Recursive because core/image inside core/columns inside core/group is
	 * ordinary content, and a single-level loop would miss most of it.
	 *
	 * @since    1.4.2
	 * @param    array    $blocks       Blocks to examine.
	 * @param    array    $state        Scan state, passed by reference for heading order.
	 * @param    array    $findings     Findings, appended to.
	 * @param    array    $unscanned    Unscanned notes, appended to.
	 * @param    string   $path         Human-readable position, for messages.
	 */
	private static function walk( $blocks, &$state, &$findings, &$unscanned, $path = '' ) {
		if ( ! is_array( $blocks ) ) {
			return;
		}

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name = isset( $block['blockName'] ) ? $block['blockName'] : null;

			if ( null === $name ) {
				// Freeform HTML. Nothing to assert about it structurally.
				continue;
			}

			// Synced patterns and reusable blocks keep their content in a separate
			// post, so it is genuinely not here. Say so rather than reporting a
			// clean result for content that was never examined.
			if ( 'core/block' === $name ) {
				$unscanned[] = array(
					'type'    => 'synced_pattern',
					'ref'     => isset( $block['attrs']['ref'] ) ? (int) $block['attrs']['ref'] : 0,
					'message' => 'A synced pattern is used here. Its content is stored separately and was not checked.',
				);

				continue;
			}

			$block_path = '' === $path ? $name : $path . ' > ' . $name;

			self::check_block( $block, $name, $block_path, $state, $findings );

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $state, $findings, $unscanned, $block_path );
			}
		}
	}

	/**
	 * Apply the rules that concern a single block.
	 *
	 * @since    1.4.2
	 * @param    array    $block      Parsed block.
	 * @param    string   $name       Block name.
	 * @param    string   $block_path Position, for messages.
	 * @param    array    $state      Scan state.
	 * @param    array    $findings   Findings, appended to.
	 */
	private static function check_block( $block, $name, $block_path, &$state, &$findings ) {
		$html = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';

		switch ( $name ) {
			case 'core/image':
				self::check_image( $html, $block_path, $findings );
				break;

			case 'core/heading':
				self::check_heading( $html, $block_path, $state, $findings );
				break;

			case 'core/paragraph':
			case 'core/list-item':
			case 'core/quote':
			case 'core/verse':
				self::check_links( $html, $block_path, $findings );
				break;

			case 'core/button':
				self::check_button( $html, $block_path, $findings );
				break;

			case 'core/table':
				self::check_table( $html, $block_path, $findings );
				break;

			// A gallery saved before inner blocks, as 5.2-era installs have, holds
			// its images directly in the markup rather than as core/image children,
			// so those alt attributes were never examined.
			case 'core/gallery':
				self::check_image( $html, $block_path, $findings );
				break;
		}
	}

	/**
	 * Image alt text.
	 *
	 * The alt value is not in the block attributes — core/image saves it to the
	 * rendered <img> through a source of `attribute` — so it has to be read from
	 * the markup.
	 *
	 * @since    1.4.2
	 * @param    string    $html       Block markup.
	 * @param    string    $block_path Position.
	 * @param    array     $findings   Findings, appended to.
	 */
	private static function check_image( $html, $block_path, &$findings ) {
		$tags = self::collect_tags( $html, 'IMG' );

		foreach ( $tags as $tag ) {
			$alt = $tag['attributes']['alt'];

			if ( null === $alt ) {
				self::add_finding( $findings, self::RULE_IMAGE_MISSING_ALT, $block_path );
				continue;
			}

			// An empty alt is the correct, deliberate markup for a decorative
			// image. Reporting it would mark correct content as broken, which is
			// the most likely false positive in this whole feature.
			if ( '' === trim( $alt ) ) {
				continue;
			}

			if ( self::is_unhelpful_alt( $alt ) ) {
				self::add_finding( $findings, self::RULE_IMAGE_UNHELPFUL_ALT, $block_path );
			}
		}
	}

	/**
	 * Whether alt text fails to describe anything.
	 *
	 * @since    1.4.2
	 * @param    string    $alt Alt text.
	 * @return   bool
	 */
	private static function is_unhelpful_alt( $alt ) {
		$alt = trim( $alt );

		// A filename.
		if ( preg_match( '/\.(jpe?g|png|gif|webp|avif|svg|bmp|tiff?)$/i', $alt ) ) {
			return true;
		}

		if ( preg_match( '/^(img|image|dsc|dscn|photo|pic|picture|screenshot|untitled|untitled[-_ ]?\d*)[-_ ]?\d*$/i', $alt ) ) {
			return true;
		}

		$lower = strtolower( $alt );

		foreach ( self::$redundant_alt_prefixes as $prefix ) {
			if ( 0 === strpos( $lower, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Heading emptiness and level order.
	 *
	 * @since    1.4.2
	 * @param    string    $html       Block markup.
	 * @param    string    $block_path Position.
	 * @param    array     $state      Scan state.
	 * @param    array     $findings   Findings, appended to.
	 */
	private static function check_heading( $html, $block_path, &$state, &$findings ) {
		if ( ! preg_match( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches ) ) {
			return;
		}

		$level = (int) $matches[1];
		$text  = trim( wp_strip_all_tags( $matches[2] ) );

		if ( '' === $text ) {
			self::add_finding( $findings, self::RULE_HEADING_EMPTY, $block_path );
		}

		$last = (int) $state['last_heading_level'];

		// Only a jump *downwards* by more than one level is a problem, and only
		// once a heading has been seen. A leading h2 is correct: the page title
		// is normally the h1 and comes from the theme.
		if ( $last > 0 && $level > $last + 1 ) {
			self::add_finding( $findings, self::RULE_HEADING_SKIPPED, $block_path );
		}

		$state['last_heading_level'] = $level;
	}

	/**
	 * Link text that does not say where it goes.
	 *
	 * @since    1.4.2
	 * @param    string    $html       Block markup.
	 * @param    string    $block_path Position.
	 * @param    array     $findings   Findings, appended to.
	 */
	private static function check_links( $html, $block_path, &$findings ) {
		foreach ( self::collect_tags( $html, 'A' ) as $tag ) {
			// A link wrapping an image is described by that image's alt text, and
			// its text content is legitimately empty.
			if ( ! empty( $tag['has_image'] ) ) {
				continue;
			}

			$text = trim( $tag['text'] );

			if ( '' === $text ) {
				// Nothing to judge: an icon link with an aria-label is fine, and
				// one without is a different problem this audit does not claim to
				// detect.
				continue;
			}

			if ( self::is_vague_link_text( $text ) ) {
				self::add_finding( $findings, self::RULE_LINK_UNHELPFUL, $block_path );
			}
		}
	}

	/**
	 * Whether link text says nothing about the destination.
	 *
	 * @since    1.4.2
	 * @param    string    $text Link text.
	 * @return   bool
	 */
	private static function is_vague_link_text( $text ) {
		$normalised = strtolower( trim( preg_replace( '/\s+/', ' ', $text ) ) );

		if ( in_array( $normalised, self::$vague_link_text, true ) ) {
			return true;
		}

		// Text that is only a URL. Not helpful read aloud, and usually a paste.
		if ( preg_match( '#^https?://\S+$#i', $normalised ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Buttons with no accessible name.
	 *
	 * @since    1.4.2
	 * @param    string    $html       Block markup.
	 * @param    string    $block_path Position.
	 * @param    array     $findings   Findings, appended to.
	 */
	private static function check_button( $html, $block_path, &$findings ) {
		foreach ( self::collect_tags( $html, 'A' ) as $tag ) {
			if ( '' !== trim( $tag['text'] ) ) {
				continue;
			}

			// An icon-only button with an image or an explicit label is labelled.
			if ( ! empty( $tag['has_image'] ) || '' !== trim( (string) $tag['attributes']['aria-label'] ) ) {
				continue;
			}

			self::add_finding( $findings, self::RULE_BUTTON_NO_LABEL, $block_path );
		}
	}

	/**
	 * Tables that look like data but have no header cells.
	 *
	 * Only reported when the table is big enough to be plausibly tabular. A
	 * two-cell table is far more likely to be layout, and core/table cannot
	 * express that distinction, so the finding is worded as a question.
	 *
	 * @since    1.4.2
	 * @param    string    $html       Block markup.
	 * @param    string    $block_path Position.
	 * @param    array     $findings   Findings, appended to.
	 */
	private static function check_table( $html, $block_path, &$findings ) {
		if ( false === stripos( $html, '<table' ) ) {
			return;
		}

		if ( false !== stripos( $html, '<th' ) ) {
			return;
		}

		$rows = preg_match_all( '/<tr\b/i', $html );
		$cols = 0;

		if ( preg_match( '/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $first_row ) ) {
			$cols = preg_match_all( '/<t[dh]\b/i', $first_row[1] );
		}

		// Two rows and two columns is the floor for "plausibly a data table". A
		// single-row table is more likely layout, and core/table cannot express
		// the difference, so the finding is worded as a question the author
		// answers rather than an assertion the audit makes.
		if ( $rows >= 2 && $cols >= 2 ) {
			self::add_finding( $findings, self::RULE_TABLE_NO_HEADERS, $block_path );
		}
	}

	/**
	 * Collect elements of a tag type, with their attributes and text.
	 *
	 * Uses WP_HTML_Tag_Processor where available rather than regex or DOMDocument:
	 * it needs no ext-dom, and it does not mangle HTML5 markup the way DOMDocument
	 * does. Falls back to a conservative regex on WordPress older than 6.2, which
	 * the plugin still supports.
	 *
	 * @since    1.4.2
	 * @param    string    $html Tag name to collect.
	 * @param    string    $tag  Element name, upper case.
	 * @return   array    List of array( attributes, text, has_image ).
	 */
	private static function collect_tags( $html, $tag ) {
		if ( class_exists( 'WP_HTML_Tag_Processor' ) ) {
			$collected = self::collect_tags_with_processor( $html, $tag );

			if ( null !== $collected ) {
				return $collected;
			}
		}

		return self::collect_tags_with_regex( $html, $tag );
	}

	/**
	 * Collect elements using WP_HTML_Tag_Processor.
	 *
	 * @since    1.4.2
	 * @param    string    $html Block markup.
	 * @param    string    $tag  Element name, upper case.
	 * @return   array|null Null when the processor cannot handle this input.
	 */
	private static function collect_tags_with_processor( $html, $tag ) {
		$processor = new WP_HTML_Tag_Processor( $html );
		$collected = array();
		$open      = null;

		$void = in_array( $tag, array( 'IMG', 'INPUT', 'BR', 'HR' ), true );

		// Closing tags are skipped by default and get_tag() returns canonical
		// names, so link boundaries have to be tracked with both the closer query
		// and is_tag_closer(). Checking for a '/A' tag name never matches, which
		// silently collected only the first link in each block.
		while ( $processor->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
			$name    = $processor->get_tag();
			$closing = $processor->is_tag_closer();

			if ( $tag === $name ) {
				if ( $void ) {
					if ( ! $closing ) {
						$collected[] = array(
							'attributes' => self::processor_attributes( $processor ),
							'text'       => '',
							'has_image'  => false,
						);
					}

					continue;
				}

				if ( $closing ) {
					if ( null !== $open ) {
						$collected[] = $open;
						$open        = null;
					}

					continue;
				}

				if ( null === $open ) {
					$open = array(
						'attributes' => self::processor_attributes( $processor ),
						'text'       => '',
						'has_image'  => false,
					);
				}

				continue;
			}

			// A nested image inside an open anchor means the link is described by
			// that image rather than by its own text.
			if ( null !== $open && 'IMG' === $name && ! $closing ) {
				$open['has_image'] = true;
			}
		}

		// An unclosed tag still has attributes worth judging.
		if ( null !== $open ) {
			$collected[] = $open;
		}

		// Text content is needed for links, and the tag processor does not expose
		// it directly in a form that survives nested markup, so take it from the
		// markup between the tags.
		if ( 'A' === $tag ) {
			$collected = self::attach_link_text( $html, $collected );
		}

		return $collected;
	}

	/**
	 * Read an element's attributes through the tag processor.
	 *
	 * @since    1.4.2
	 * @param    WP_HTML_Tag_Processor $processor Active processor.
	 * @return   array Attribute name => value, with null for absent attributes.
	 */
	private static function processor_attributes( $processor ) {
		$attributes = array();

		foreach ( array( 'alt', 'href', 'aria-label' ) as $name ) {
			$attributes[ $name ] = $processor->get_attribute( $name );
		}

		return $attributes;
	}

	/**
	 * Attach each anchor's text content.
	 *
	 * @since    1.4.2
	 * @param    string    $html      Block markup.
	 * @param    array     $collected Collected anchors.
	 * @return   array
	 */
	private static function attach_link_text( $html, $collected ) {
		preg_match_all( '#<a\b[^>]*>(.*?)</a>#is', $html, $matches );

		foreach ( $collected as $index => $anchor ) {
			if ( isset( $matches[1][ $index ] ) ) {
				$collected[ $index ]['text'] = wp_strip_all_tags( $matches[1][ $index ] );
			}
		}

		return $collected;
	}

	/**
	 * Conservative fallback for WordPress older than 6.2.
	 *
	 * Regex on HTML is a last resort and is confined to this method. It handles
	 * the flat, well-formed markup the editor writes; anything more complex is
	 * better missed than misreported.
	 *
	 * @since    1.4.2
	 * @param    string    $html Block markup.
	 * @param    string    $tag  Element name, upper case.
	 * @return   array
	 */
	private static function collect_tags_with_regex( $html, $tag ) {
		$collected = array();
		$void      = in_array( $tag, array( 'IMG', 'INPUT', 'BR', 'HR' ), true );

		if ( $void ) {
			preg_match_all( '#<' . strtolower( $tag ) . '\b([^>]*)>#i', $html, $matches, PREG_SET_ORDER );

			foreach ( $matches as $match ) {
				$collected[] = array(
					'attributes' => self::regex_attributes( $match[1] ),
					'text'       => '',
					'has_image'  => false,
				);
			}

			return $collected;
		}

		preg_match_all( '#<' . strtolower( $tag ) . '\b([^>]*)>(.*?)</' . strtolower( $tag ) . '>#is', $html, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$collected[] = array(
				'attributes' => self::regex_attributes( $match[1] ),
				'text'       => wp_strip_all_tags( $match[2] ),
				'has_image'  => false !== stripos( $match[2], '<img' ),
			);
		}

		return $collected;
	}

	/**
	 * Parse attributes out of a raw attribute string.
	 *
	 * @since    1.4.2
	 * @param    string    $raw Attribute string.
	 * @return   array
	 */
	private static function regex_attributes( $raw ) {
		$attributes = array( 'alt' => null, 'href' => null, 'aria-label' => null );

		foreach ( array_keys( $attributes ) as $name ) {
			$pattern = '/\b' . preg_quote( $name, '/' ) . '\s*=\s*("([^"]*)"|\'([^\']*)\')/i';

			if ( preg_match( $pattern, $raw, $match ) ) {
				$attributes[ $name ] = isset( $match[2] ) && '' !== $match[2] ? $match[2] : ( $match[3] ?? '' );
			}
		}

		return $attributes;
	}

	/**
	 * Record a finding.
	 *
	 * @since    1.4.2
	 * @param    array     $findings   Findings, appended to.
	 * @param    string    $rule       Rule identifier.
	 * @param    string    $block_path Position in the block tree.
	 */
	private static function add_finding( &$findings, $rule, $block_path ) {
		$findings[] = array(
			'rule'     => $rule,
			'message'  => self::$messages[ $rule ],
			'wcag'     => self::$criteria[ $rule ],
			'severity' => self::$severity[ $rule ],
			'block'    => $block_path,
		);
	}

	/**
	 * The rules, as data, for the editor panel.
	 *
	 * The panel applies these to the live block tree, so they have to be in
	 * JavaScript. Delivering them from here rather than declaring them again in
	 * the script keeps one definition of what a rule is, which is the difference
	 * between the panel and the Phase 4 report agreeing and drifting.
	 *
	 * The detection logic is not data and is not delivered: the panel has its own
	 * implementation over editor blocks, and the tests in this repository cover
	 * the PHP one that the report uses.
	 *
	 * @since    1.4.2
	 * @return   array    Rule id => message, wcag, severity.
	 */
	public static function rule_definitions() {
		$definitions = array();

		foreach ( self::$messages as $rule => $message ) {
			$definitions[ $rule ] = array(
				'message'  => $message,
				'wcag'     => self::$criteria[ $rule ],
				'severity' => self::$severity[ $rule ],
			);
		}

		return $definitions;
	}

	/**
	 * The categories this audit cannot check.
	 *
	 * Exposed separately so the editor panel can name what it does not examine
	 * without keeping its own copy of the list, which is how the two would drift.
	 *
	 * @since    1.4.2
	 * @return   string[]
	 */
	public static function unchecked_categories() {
		$result = self::build_result( array(), array() );

		return $result['unchecked'];
	}

	/**
	 * Assemble the result, including what was not checked.
	 *
	 * @since    1.4.2
	 * @param    array    $findings  Findings.
	 * @param    array    $unscanned Content that could not be examined.
	 * @return   array
	 */
	private static function build_result( $findings, $unscanned ) {
		$summary = array( 'error' => 0, 'warning' => 0, 'review' => 0 );

		foreach ( $findings as $finding ) {
			if ( isset( $summary[ $finding['severity'] ] ) ) {
				$summary[ $finding['severity'] ]++;
			}
		}

		return array(
			'findings'  => $findings,
			'unscanned' => $unscanned,
			'summary'   => $summary,
			/**
			 * Filter the categories the audit states it cannot check.
			 *
			 * The list is deliberately visible in the UI. An audit that reports
			 * findings without saying what it never looked at overstates itself,
			 * which is the failure mode this whole feature is positioned against.
			 *
			 * @since 1.4.2
			 * @param string[] $unchecked Category descriptions.
			 */
			'unchecked' => apply_filters(
				'open_accessibility_audit_unchecked_categories',
				array(
					'Colour contrast',
					'Focus order and focus visibility',
					'Keyboard traps',
					'ARIA roles and states',
					'Anything inside third-party blocks whose output is not in the block data',
				)
			),
		);
	}
}
