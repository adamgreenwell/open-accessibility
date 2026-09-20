<?php
/**
 * Tests for the content audit rules.
 *
 * These are the substance of the editor audit: the panel is presentation, but
 * what it reports is what makes the feature worth having. The rules are
 * deliberately quiet about things they cannot know — an empty image alt is
 * correct markup for a decorative image, and a table without a header row may be
 * a layout table — so the tests pin both what is reported and what is not.
 *
 * @package Open_Accessibility
 */

/**
 * @covers Open_Accessibility_Audit
 */
class Test_Audit_Rules extends OA_TestCase {

	/**
	 * Scan serialised block content.
	 *
	 * @param string $content Post content.
	 * @return array
	 */
	private function scan( $content ) {
		return Open_Accessibility_Audit::scan_content( $content );
	}

	/**
	 * Rule ids reported for some content.
	 *
	 * @param string $content Post content.
	 * @return string[]
	 */
	private function rules_for( $content ) {
		return array_values( array_unique( array_column( $this->scan( $content )['findings'], 'rule' ) ) );
	}

	/**
	 * Build an image block with a given alt attribute value.
	 *
	 * The alt text saves to the rendered <img>, not to block attributes, so it
	 * has to be set in the markup the way the editor would write it.
	 *
	 * @param string|null $alt Alt value; null omits the attribute entirely.
	 * @return string
	 */
	private function image_block( $alt ) {
		$attribute = null === $alt ? '' : ' alt="' . $alt . '"';

		return '<!-- wp:image {"id":7,"sizeSlug":"large"} -->'
			. '<figure class="wp-block-image size-large">'
			. '<img src="https://example.org/photo.jpg"' . $attribute . ' class="wp-image-7"/>'
			. '</figure><!-- /wp:image -->';
	}

	/**
	 * Clean content with nothing to report.
	 */
	public function test_clean_content_produces_no_findings() {
		$content = '<!-- wp:heading --><h2>Title</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>Some readable text with <a href="https://example.org/report">the annual report</a> linked.</p><!-- /wp:paragraph -->'
			. $this->image_block( 'A cat asleep on a keyboard' );

		$this->assertSame( array(), $this->scan( $content )['findings'] );
	}

	/**
	 * An image with a usable alt attribute is fine.
	 */
	public function test_descriptive_alt_is_accepted() {
		$this->assertSame( array(), $this->rules_for( $this->image_block( 'A cat asleep on a keyboard' ) ) );
	}

	/**
	 * An image with no alt attribute at all is reported.
	 */
	public function test_missing_alt_is_reported() {
		$this->assertContains( 'image_missing_alt', $this->rules_for( $this->image_block( null ) ) );
	}

	/**
	 * An empty alt is NOT reported as an error.
	 *
	 * `alt=""` is the correct, deliberate markup for a decorative image. Flagging
	 * it would mark correct content as broken, which is the single most likely
	 * false positive in this feature.
	 */
	public function test_empty_alt_is_not_reported_as_an_error() {
		$findings = $this->scan( $this->image_block( '' ) )['findings'];

		$this->assertSame(
			array(),
			$findings,
			'An empty alt is valid markup for a decorative image and must not be reported.'
		);
	}

	/**
	 * Alt text that is really a filename is reported, because it tells a screen
	 * reader user nothing.
	 */
	public function test_filename_like_alt_is_reported() {
		foreach ( array( 'IMG_2043.jpg', 'photo-1.png', 'screenshot.png', 'DSC00123.JPG' ) as $alt ) {
			$this->assertContains(
				'image_alt_unhelpful',
				$this->rules_for( $this->image_block( $alt ) ),
				"Alt '{$alt}' reads as a filename and should be reported."
			);
		}
	}

	/**
	 * Alt text that describes the image as an image is reported.
	 */
	public function test_redundant_alt_phrasing_is_reported() {
		foreach ( array( 'image of a cat', 'Picture of a dog', 'photo of a house' ) as $alt ) {
			$this->assertContains(
				'image_alt_unhelpful',
				$this->rules_for( $this->image_block( $alt ) ),
				"Alt '{$alt}' adds nothing to what the screen reader already says."
			);
		}
	}

	/**
	 * A jump in heading level is reported.
	 */
	public function test_skipped_heading_level_is_reported() {
		$content = '<!-- wp:heading {"level":2} --><h2>Section</h2><!-- /wp:heading -->'
			. '<!-- wp:heading {"level":4} --><h4>Deeper</h4><!-- /wp:heading -->';

		$findings = $this->scan( $content )['findings'];

		$this->assertContains( 'heading_skipped_level', array_column( $findings, 'rule' ) );
	}

	/**
	 * The document title normally comes from the theme, so a first heading
	 * other than h1 is not reported.
	 *
	 * Flagging a leading h2 would fire on almost every correct post.
	 */
	public function test_a_leading_h2_is_not_reported() {
		$content = '<!-- wp:heading {"level":2} --><h2>Section</h2><!-- /wp:heading -->'
			. '<!-- wp:heading {"level":3} --><h3>Subsection</h3><!-- /wp:heading -->';

		$this->assertNotContains( 'heading_skipped_level', $this->rules_for( $content ) );
	}

	/**
	 * Headings nested inside other blocks count towards the sequence.
	 */
	public function test_heading_order_spans_nested_blocks() {
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:heading {"level":3} --><h3>Nested</h3><!-- /wp:heading -->'
			. '</div><!-- /wp:group -->'
			. '<!-- wp:heading {"level":5} --><h5>After</h5><!-- /wp:heading -->';

		$this->assertContains( 'heading_skipped_level', $this->rules_for( $content ) );
	}

	/**
	 * Non-descriptive link text is reported.
	 */
	public function test_non_descriptive_link_text_is_reported() {
		foreach ( array( 'click here', 'here', 'read more', 'learn more', 'more info' ) as $text ) {
			$content = '<!-- wp:paragraph --><p><a href="https://example.org/x">' . $text . '</a></p><!-- /wp:paragraph -->';

			$this->assertContains(
				'link_text_unhelpful',
				$this->rules_for( $content ),
				"Link text '{$text}' should be reported."
			);
		}
	}

	/**
	 * A link whose text is just its URL is reported.
	 */
	public function test_bare_url_link_text_is_reported() {
		$content = '<!-- wp:paragraph --><p><a href="https://example.org/x">https://example.org/x</a></p><!-- /wp:paragraph -->';

		$this->assertContains( 'link_text_unhelpful', $this->rules_for( $content ) );
	}

	/**
	 * Descriptive link text is left alone.
	 */
	public function test_descriptive_link_text_is_accepted() {
		$content = '<!-- wp:paragraph --><p><a href="https://example.org/x">the 2026 accessibility report</a></p><!-- /wp:paragraph -->';

		$this->assertNotContains( 'link_text_unhelpful', $this->rules_for( $content ) );
	}

	/**
	 * A button with no text is reported.
	 */
	public function test_empty_button_is_reported() {
		$content = '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="/x"></a></div><!-- /wp:button -->';

		$this->assertContains( 'button_missing_label', $this->rules_for( $content ) );
	}

	/**
	 * A button with text is accepted.
	 */
	public function test_labelled_button_is_accepted() {
		$content = '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="/x">Contact us</a></div><!-- /wp:button -->';

		$this->assertNotContains( 'button_missing_label', $this->rules_for( $content ) );
	}

	/**
	 * An empty heading is reported.
	 */
	public function test_empty_heading_is_reported() {
		$content = '<!-- wp:heading {"level":2} --><h2></h2><!-- /wp:heading -->';

		$this->assertContains( 'heading_empty', $this->rules_for( $content ) );
	}

	/**
	 * A heading containing only whitespace is reported.
	 */
	public function test_whitespace_heading_is_reported() {
		$content = '<!-- wp:heading {"level":2} --><h2>   </h2><!-- /wp:heading -->';

		$this->assertContains( 'heading_empty', $this->rules_for( $content ) );
	}

	/**
	 * A plausible data table with no header cells is reported.
	 */
	public function test_table_without_headers_is_reported() {
		$content = '<!-- wp:table --><figure class="wp-block-table"><table><tbody>'
			. '<tr><td>Region</td><td>Total</td></tr>'
			. '<tr><td>North</td><td>12</td></tr>'
			. '</tbody></table></figure><!-- /wp:table -->';

		$this->assertContains( 'table_missing_headers', $this->rules_for( $content ) );
	}

	/**
	 * A table with th cells is accepted.
	 */
	public function test_table_with_headers_is_accepted() {
		$content = '<!-- wp:table --><figure class="wp-block-table"><table><thead>'
			. '<tr><th>Region</th><th>Total</th></tr></thead><tbody>'
			. '<tr><td>North</td><td>12</td></tr>'
			. '</tbody></table></figure><!-- /wp:table -->';

		$this->assertNotContains( 'table_missing_headers', $this->rules_for( $content ) );
	}

	/**
	 * A two-cell table is not reported: it is too small to judge, and is more
	 * likely a layout table than data.
	 */
	public function test_tiny_table_is_not_reported() {
		$content = '<!-- wp:table --><figure class="wp-block-table"><table><tbody>'
			. '<tr><td>Label</td><td>Value</td></tr>'
			. '</tbody></table></figure><!-- /wp:table -->';

		$this->assertNotContains( 'table_missing_headers', $this->rules_for( $content ) );
	}

	/**
	 * Synced patterns are named as unscanned rather than silently skipped.
	 *
	 * Their content lives in a separate wp_block post, so it is genuinely not in
	 * this post's block tree. Reporting a clean result while ignoring them would
	 * be the kind of overstatement this feature exists to avoid.
	 */
	public function test_synced_patterns_are_reported_as_unscanned() {
		$content = '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->'
			. '<!-- wp:block {"ref":123} /-->';

		$result = $this->scan( $content );

		$this->assertContains( 'synced_pattern', array_column( $result['unscanned'], 'type' ) );
	}

	/**
	 * The result always names what was not checked.
	 */
	public function test_result_names_unchecked_categories() {
		$result = $this->scan( '<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->' );

		$this->assertArrayHasKey( 'unchecked', $result );
		$this->assertNotEmpty( $result['unchecked'], 'The audit must state what it cannot check.' );

		foreach ( array( 'contrast', 'focus', 'keyboard', 'aria' ) as $expected ) {
			$joined = strtolower( implode( ' ', $result['unchecked'] ) );
			$this->assertStringContainsString( $expected, $joined, "Unchecked categories should mention {$expected}." );
		}
	}

	/**
	 * Every finding carries the rule, a message and a WCAG criterion.
	 *
	 * The panel and the Phase 4 report both read these fields, so a rule that
	 * omits one would render as a blank line in the UI.
	 */
	public function test_every_finding_is_fully_described() {
		$content = $this->image_block( null )
			. '<!-- wp:heading {"level":2} --><h2>Section</h2><!-- /wp:heading -->'
			. '<!-- wp:heading {"level":5} --><h5>Jump</h5><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p><a href="/x">click here</a></p><!-- /wp:paragraph -->'
			. '<!-- wp:heading --><h2></h2><!-- /wp:heading -->'
			. '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link" href="/y"></a></div><!-- /wp:button -->';

		$findings = $this->scan( $content )['findings'];

		$this->assertNotEmpty( $findings );

		foreach ( $findings as $finding ) {
			foreach ( array( 'rule', 'message', 'wcag', 'severity' ) as $key ) {
				$this->assertArrayHasKey( $key, $finding, "A finding is missing '{$key}'." );
				$this->assertNotSame( '', $finding[ $key ], "A finding has an empty '{$key}'." );
			}

			$this->assertContains( $finding['severity'], array( 'error', 'warning', 'review' ) );
		}
	}

	/**
	 * Empty content is handled without complaint.
	 */
	public function test_empty_content_is_safe() {
		foreach ( array( '', '   ', 'plain text with no blocks' ) as $content ) {
			$result = $this->scan( $content );

			$this->assertIsArray( $result );
			$this->assertArrayHasKey( 'findings', $result );
		}
	}

	/**
	 * Malformed markup does not throw.
	 */
	public function test_malformed_markup_is_survivable() {
		$content = '<!-- wp:paragraph --><p>Unclosed <a href="/x">link<!-- /wp:paragraph -->';

		$this->assertIsArray( $this->scan( $content ) );
	}
}
