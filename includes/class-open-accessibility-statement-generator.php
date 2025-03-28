<?php
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Accessibility Statement Generator
 *
 * This class handles the generation of WCAG-compliant accessibility statements
 *
 * @since      1.0.0
 * @package    Open_Accessibility
 */

class Open_Accessibility_Statement_Generator {

	/**
	 * Generate an accessibility statement based on provided data
	 *
	 * @param array $data Statement data including:
	 *                    - org_name: Organization name
	 *                    - website_url: Website URL (defaults to site_url())
	 *                    - contact_email: Contact email
	 *                    - conformance_level: WCAG conformance level (A, AA, AAA)
	 *
	 * @return string HTML content of the accessibility statement
	 */
	public static function generate_statement($data) {

		// Make sure we're handling this specific AJAX action
		if (!isset($_POST['action']) || $_POST['action'] !== 'open_accessibility_generate_statement') {
			return;
		}

		// Check for nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce( sanitize_text_field( wp_unslash($_POST['nonce']) ), 'open_accessibility_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'open-accessibility')));
		}

		// Set defaults
		$data = wp_parse_args($data, [
			'org_name' => get_bloginfo('name'),
			'website_url' => site_url(),
			'contact_email' => get_bloginfo('admin_email'),
			'conformance_level' => 'AA',
		]);

		// Sanitize values for processing - escaping will occur at output time
		$org_name = sanitize_text_field($data['org_name']);
		$website_url = sanitize_text_field($data['website_url']);
		$contact_email = sanitize_email($data['contact_email']);
		$conformance_level = in_array($data['conformance_level'], ['A', 'AA', 'AAA']) ? $data['conformance_level'] : 'AA';

		// Get current date for the statement
		$date = date_i18n(get_option('date_format'));

		// Generate statement HTML
		$statement = '<div class="accessibility-statement">';

		// Title
		$statement .= '<h1>' . esc_html__('Accessibility Statement', 'open-accessibility') . '</h1>';

		// Introduction
		$statement .= '<p>' . sprintf(
				/* translators: %1$s: organization name */
				esc_html__('%1$s is committed to ensuring digital accessibility for people with disabilities. We are continually improving the user experience for everyone, and applying the relevant accessibility standards.', 'open-accessibility'),
				'<strong>' . esc_html($org_name) . '</strong>'
			) . '</p>';

		// Conformance status
		$statement .= '<h2>' . esc_html__('Conformance Status', 'open-accessibility') . '</h2>';
		$statement .= '<p>' . sprintf(
				/* translators: %1$s: WCAG conformance level */
				esc_html__('The Web Content Accessibility Guidelines (WCAG) defines requirements for designers and developers to improve accessibility for people with disabilities. It defines three levels of conformance: Level A, Level AA, and Level AAA. %1$s is partially conformant with WCAG 2.1 level %2$s. Partially conformant means that some parts of the content do not fully conform to the accessibility standard.', 'open-accessibility'),
				esc_html($org_name),
				esc_html($conformance_level)
			) . '</p>';

		// Feedback
		$statement .= '<h2>' . esc_html__('Feedback', 'open-accessibility') . '</h2>';
		$statement .= '<p>' . sprintf(
			/* translators: %1$s: organization name */
				esc_html__('We welcome your feedback on the accessibility of %1$s. Please let us know if you encounter accessibility barriers on %1$s:', 'open-accessibility'),
				esc_html($org_name)
			) . '</p>';

		// Contact information
		$statement .= '<ul>';
		$statement .= '<li>' . sprintf(
				/* translators: %s: contact email */
				esc_html__('Email: %s', 'open-accessibility'),
				'<a href="mailto:' . esc_attr($contact_email) . '">' . esc_html($contact_email) . '</a>'
			) . '</li>';
		$statement .= '</ul>';

		$statement .= '<p>' . esc_html__('We try to respond to feedback within 2 business days.', 'open-accessibility') . '</p>';

		// Assessment approach
		$statement .= '<h2>' . esc_html__('Assessment Approach', 'open-accessibility') . '</h2>';
		$statement .= '<p>' . sprintf(
				/* translators: %s: organization name */
				esc_html__('%s assessed the accessibility of its website by the following approaches:', 'open-accessibility'),
				esc_html($org_name)
			) . '</p>';

		$statement .= '<ul>';
		$statement .= '<li>' . esc_html__('Self-evaluation', 'open-accessibility') . '</li>';
		$statement .= '</ul>';

		// Technical specifications
		$statement .= '<h2>' . esc_html__('Technical Specifications', 'open-accessibility') . '</h2>';
		$statement .= '<p>' . esc_html__('Accessibility of this website relies on the following technologies to work with the particular combination of web browser and any assistive technologies or plugins installed on your computer:', 'open-accessibility') . '</p>';

		$statement .= '<ul>';
		$statement .= '<li>HTML</li>';
		$statement .= '<li>CSS</li>';
		$statement .= '<li>JavaScript</li>';
		$statement .= '</ul>';

		$statement .= '<p>' . esc_html__('These technologies are relied upon for conformance with the accessibility standards used.', 'open-accessibility') . '</p>';

		// Limitations and alternatives
		$statement .= '<h2>' . esc_html__('Limitations and Alternatives', 'open-accessibility') . '</h2>';
		$statement .= '<p>' . esc_html__('Despite our best efforts to ensure accessibility of the website, there may be some limitations. Below is a description of known limitations, and potential solutions. Please contact us if you observe an issue not listed below.', 'open-accessibility') . '</p>';

		$statement .= '<p>' . esc_html__('Known limitations for the website:', 'open-accessibility') . '</p>';

		$statement .= '<ul>';
		$statement .= '<li>' . esc_html__('Comments from users: User-generated content may not be fully accessible. We cannot control or fix this content since it is provided by third parties.', 'open-accessibility') . '</li>';
		$statement .= '<li>' . esc_html__('Third-party content: We do not control the accessibility of third-party content, such as social media content, maps, or videos. We try to ensure any third-party providers we select support accessibility standards.', 'open-accessibility') . '</li>';
		$statement .= '</ul>';

		// Assessment date
		$statement .= '<h2>' . esc_html__('Date', 'open-accessibility') . '</h2>';
		$statement .= '<p>' . sprintf(
				/* translators: %s: accessibility statement creation date */
				esc_html__('This statement was created on %s.', 'open-accessibility'),
				esc_html($date)
			) . '</p>';

		$statement .= '</div>';

		return $statement;
	}

	/**
	 * Create a new page containing the accessibility statement
	 *
	 * @param array $data Statement data
	 * @return int|WP_Error The page ID on success, WP_Error on failure
	 */
	public static function create_statement_page($data) {
		// Generate statement content
		$content = self::generate_statement($data);

		// Prepare page data
		$page_data = [
			'post_title'    => __('Accessibility Statement', 'open-accessibility'),
			'post_content'  => $content,
			'post_status'   => 'publish',
			'post_type'     => 'page',
		];

		// Create the page
		$page_id = wp_insert_post($page_data);

		if (!is_wp_error($page_id)) {
			// Update plugin settings with the new page URL
			$options = get_option('open_accessibility_options', []);
			$page_url = get_permalink($page_id);
			$options['statement_url'] = $page_url;
			update_option('open_accessibility_options', $options);
		}

		return $page_id;
	}
}