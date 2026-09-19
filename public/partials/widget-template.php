<?php
/**
 * Template for the accessibility widget on the frontend
 *
 * @package    Open_Accessibility
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Merged over defaults: every key the template reads below is guaranteed to
// exist, so the isset() guards are defensive rather than load-bearing.
$options = Open_Accessibility_Utils::get_options();

// Every label below comes from the filtered strings array, so themes and site
// owners can relabel any control through the `open_accessibility_strings`
// filter. Do not add a bare esc_html_e() call here: it would bypass the filter
// and reintroduce the bug this replaced.
if ( ! isset( $oa_strings ) || ! is_array( $oa_strings ) ) {
	// Fallback for any include path that does not pass strings in.
	$oa_public  = new Open_Accessibility_Public();
	$oa_strings = $oa_public->get_strings();
}

// Get icon class
$icon = isset($options['icon']) ? $options['icon'] : 'accessibility';
$icon_size = isset($options['icon_size']) ? $options['icon_size'] : 'medium';
$position = isset($options['position']) ? $options['position'] : 'left';
$icon_color = isset($options['icon_color']) ? $options['icon_color'] : '#ffffff';
$bg_color = isset($options['bg_color']) ? $options['bg_color'] : '#4054b2';

// Build widget classes
$widget_classes = array(
	'open-accessibility-widget-wrapper',
	'position-' . $position,
	'size-' . $icon_size
);

$panel_id = 'open-accessibility-widget-panel';
$panel_title_id = 'open-accessibility-widget-title';

// Panel heading: the saved override wins, otherwise the filtered default.
// Filterable so themes can adjust it without touching settings.
$panel_title = ! empty( $options['widget_title'] )
	? $options['widget_title']
	: $oa_strings['widget_title'];
$panel_title = apply_filters( 'open_accessibility_panel_title', $panel_title );

?>

<div class="<?php echo esc_attr(implode(' ', $widget_classes)); ?>" data-oa-ignore="true">
	<!-- Accessibility Widget Toggle Button -->
    <button
            aria-label="<?php echo esc_attr( $oa_strings['toggle_open'] ); ?>"
            aria-controls="<?php echo esc_attr( $panel_id ); ?>"
            aria-expanded="false"
            class="open-accessibility-toggle-button"
            style="background-color: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($icon_color); ?>;"
    >
    <span class="open-accessibility-icon">
        <?php echo wp_kses(
            Open_Accessibility_Utils::get_icon_svg($icon, $icon_color),
	        array(
		        'svg' => array(
			        'xmlns' => array(),
                    'viewbox' => array(),
			        'viewBox' => array(),
			        'width' => array(),
			        'height' => array(),
			        'aria-hidden' => array()
		        ),
		        'g' => array(
			        'transform' => array(),
			        'fill' => array()
		        ),
		        'path' => array(
			        'd' => array(),
			        'fill' => array(),
			        'stroke' => array(),
			        'id' => array(),
			        'data-color' => array()
		        ),
		        'circle' => array(
			        'cx' => array(),
			        'cy' => array(),
			        'r' => array(),
			        'fill' => array(),
			        'data-color' => array()
		        ),
		        'title' => array(),
		        'desc' => array()
	        )
        ); ?>
    </span>
	</button>

	<!-- Accessibility Widget Panel -->
	<div id="<?php echo esc_attr( $panel_id ); ?>" class="open-accessibility-widget-panel" role="region" aria-labelledby="<?php echo esc_attr( $panel_title_id ); ?>" aria-hidden="true">
		<div class="open-accessibility-widget-header">
			<h2 id="<?php echo esc_attr( $panel_title_id ); ?>"><?php echo esc_html( $panel_title ); ?></h2>
			<button class="open-accessibility-close" aria-label="<?php echo esc_attr( $oa_strings['toggle_close'] ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>

		<div class="open-accessibility-widget-content">
			<!-- Reset Button -->
			<div class="open-accessibility-widget-section">
				<h3><?php echo esc_html( $oa_strings['reset_title'] ); ?></h3>
				<button class="open-accessibility-action-button open-accessibility-reset-button">
					<?php echo esc_html( $oa_strings['reset_text'] ); ?>
				</button>
			</div>

			<!-- Accessibility Profiles. Presets come first because they are the fast
			     path: a visitor who wants one should not have to work through fifteen
			     individual controls to find it. -->
			<?php
			$oa_profiles = Open_Accessibility_Utils::get_enabled_profiles();
			?>
			<?php if ( ! empty( $oa_profiles ) ) : ?>
				<div class="open-accessibility-widget-section open-accessibility-profiles-section">
					<h3><?php echo esc_html( $oa_strings['profiles_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<?php foreach ( $oa_profiles as $oa_profile_name => $oa_profile ) : ?>
							<button
								class="open-accessibility-action-button open-accessibility-profile-button"
								data-action="profile"
								data-value="<?php echo esc_attr( $oa_profile_name ); ?>"
								aria-pressed="false"
								title="<?php echo esc_attr( $oa_profile['description'] ); ?>"
							>
								<?php echo esc_html( $oa_profile['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Saturation. The same decrease / indicator / increase shape as the
			     other incremental controls. -->
			<?php if ( ! empty( $options['enable_saturation'] ) ) : ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['saturation_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="saturation" data-value="decrease" aria-label="<?php echo esc_attr( $oa_strings['saturation_decrease'] ); ?>">
							<?php echo esc_html( $oa_strings['decrease_text'] ); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="saturation" data-max="<?php echo esc_attr( Open_Accessibility_Utils::get_max_saturation_level() ); ?>" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php echo esc_attr( $oa_strings['saturation_level'] ); ?>"></span>
						<button class="open-accessibility-action-button" data-action="saturation" data-value="increase" aria-label="<?php echo esc_attr( $oa_strings['saturation_increase'] ); ?>">
							<?php echo esc_html( $oa_strings['increase_text'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Cursor Size. A radio group rather than a toggle: the blank value is
			     the default cursor, so choosing it is how the setting is turned off. -->
			<?php if ( ! empty( $options['enable_cursor_size'] ) ) : ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['cursor_size_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<?php
						$oa_cursor_choices = array(
							''       => $oa_strings['cursor_size_default'],
							'large'  => $oa_strings['cursor_size_large'],
							'xlarge' => $oa_strings['cursor_size_xlarge'],
						);
						?>
						<?php foreach ( $oa_cursor_choices as $oa_cursor_value => $oa_cursor_label ) : ?>
							<button
								class="open-accessibility-action-button"
								data-action="cursor-size"
								data-value="<?php echo esc_attr( $oa_cursor_value ); ?>"
								aria-pressed="false"
							>
								<?php echo esc_html( $oa_cursor_label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Contrast Section -->
			<?php if (isset($options['enable_contrast']) && $options['enable_contrast']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['contrast_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="contrast" data-value="high" aria-pressed="false">
							<?php echo esc_html( $oa_strings['contrast_modes']['high'] ); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="contrast" data-value="negative" aria-pressed="false">
							<?php echo esc_html( $oa_strings['contrast_modes']['negative'] ); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="contrast" data-value="light" aria-pressed="false">
							<?php echo esc_html( $oa_strings['contrast_modes']['light'] ); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="contrast" data-value="dark" aria-pressed="false">
							<?php echo esc_html( $oa_strings['contrast_modes']['dark'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Grayscale Section -->
			<?php if (isset($options['enable_grayscale']) && $options['enable_grayscale']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['grayscale_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="grayscale" data-value="toggle" aria-pressed="false">
							<?php echo esc_html( $oa_strings['grayscale_text'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Text Size Section -->
			<?php if (isset($options['enable_text_size']) && $options['enable_text_size']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['text_size_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="text-size" data-value="decrease">
							<?php echo esc_html( $oa_strings['text_size_decrease'] ); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="text-size" data-max="5" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php echo esc_attr( $oa_strings['text_size_level'] ); ?>"></span>
						<button class="open-accessibility-action-button" data-action="text-size" data-value="increase">
							<?php echo esc_html( $oa_strings['text_size_increase'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Font Selection Section -->
			<?php
			$show_font_section = (isset($options['enable_font_atkinson']) && $options['enable_font_atkinson']) ||
							   (isset($options['enable_font_opendyslexic']) && $options['enable_font_opendyslexic']);
			if ($show_font_section):
			?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['readable_font_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="set-font" data-value="default" aria-pressed="false">
							<?php echo esc_html( $oa_strings['font_default'] ); ?>
						</button>
						<?php if (isset($options['enable_font_atkinson']) && $options['enable_font_atkinson']): ?>
							<button class="open-accessibility-action-button" data-action="set-font" data-value="atkinson" aria-pressed="false">
								<?php echo esc_html( $oa_strings['font_atkinson'] ); ?>
							</button>
						<?php endif; ?>
						<?php if (isset($options['enable_font_opendyslexic']) && $options['enable_font_opendyslexic']): ?>
							<button class="open-accessibility-action-button" data-action="set-font" data-value="opendyslexic" aria-pressed="false">
								<?php echo esc_html( $oa_strings['font_opendyslexic'] ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Letter Spacing Section -->
			<?php if (isset($options['enable_letter_spacing']) && $options['enable_letter_spacing']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['letter_spacing_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="letter-spacing" data-value="decrease" aria-label="<?php echo esc_attr( $oa_strings['letter_spacing_decrease'] ); ?>">
							<?php echo esc_html( $oa_strings['letter_spacing_decrease'] ); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="letter-spacing" data-max="3" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php echo esc_attr( $oa_strings['letter_spacing_level'] ); ?>"></span>
						<button class="open-accessibility-action-button" data-action="letter-spacing" data-value="increase" aria-label="<?php echo esc_attr( $oa_strings['letter_spacing_increase'] ); ?>">
							<?php echo esc_html( $oa_strings['letter_spacing_increase'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Word Spacing Section -->
			<?php if (isset($options['enable_word_spacing']) && $options['enable_word_spacing']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['word_spacing_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						 <button class="open-accessibility-action-button" data-action="word-spacing" data-value="decrease" aria-label="<?php echo esc_attr( $oa_strings['word_spacing_decrease'] ); ?>">
							<?php echo esc_html( $oa_strings['word_spacing_decrease'] ); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="word-spacing" data-max="3" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php echo esc_attr( $oa_strings['word_spacing_level'] ); ?>"></span>
						<button class="open-accessibility-action-button" data-action="word-spacing" data-value="increase" aria-label="<?php echo esc_attr( $oa_strings['word_spacing_increase'] ); ?>">
							<?php echo esc_html( $oa_strings['word_spacing_increase'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Links Underline Section -->
			<?php if (isset($options['enable_links_underline']) && $options['enable_links_underline']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['links_underline_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="links-underline" data-value="toggle" aria-pressed="false">
							<?php echo esc_html( $oa_strings['links_underline_text'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Highlight Links Section -->
			<?php if ( ! empty( $options['enable_highlight_links'] ) ) : ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['highlight_links_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="highlight-links" data-value="toggle" aria-pressed="false">
							<?php echo esc_html( $oa_strings['highlight_links_title'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Hide Images Section -->
			<?php if (isset($options['enable_hide_images']) && $options['enable_hide_images']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['hide_images_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="hide-images" data-value="toggle" aria-pressed="false">
							<?php echo esc_html( $oa_strings['hide_images_text'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Reading Guide Section -->
			<?php if (isset($options['enable_reading_guide']) && $options['enable_reading_guide']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['reading_guide_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="reading-guide" data-value="toggle" aria-pressed="false">
							<?php echo esc_html( $oa_strings['reading_guide_text'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Reading Mask Section -->
			<?php
			// New in 1.4.01: existing installs have no saved value for this key,
			// and an isset() gate would hide the control until they re-saved
			// their settings. Default it on, matching the other reading aids.
			$oa_enable_reading_mask = isset($options['enable_reading_mask'])
				? (bool) $options['enable_reading_mask']
				: true;
			?>
			<?php if ($oa_enable_reading_mask): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['reading_mask_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="reading-mask" data-value="toggle" aria-pressed="false">
							<?php echo esc_html( $oa_strings['reading_mask_text'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Focus Outline Section -->
			<?php if (isset($options['enable_focus_outline']) && $options['enable_focus_outline']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['focus_outline_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="focus-outline" data-value="toggle" aria-pressed="false">
							<?php echo esc_html( $oa_strings['focus_outline_text'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Line Height Section -->
			<?php if (isset($options['enable_line_height']) && $options['enable_line_height']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['line_height_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="line-height" data-value="decrease" aria-label="<?php echo esc_attr( $oa_strings['line_height_decrease'] ); ?>">
							<?php echo esc_html( $oa_strings['line_height_decrease'] ); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="line-height" data-max="3" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php echo esc_attr( $oa_strings['line_height_level'] ); ?>"></span>
						<button class="open-accessibility-action-button" data-action="line-height" data-value="increase" aria-label="<?php echo esc_attr( $oa_strings['line_height_increase'] ); ?>">
							<?php echo esc_html( $oa_strings['line_height_increase'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Text Align Section -->
			<?php if (isset($options['enable_text_align']) && $options['enable_text_align']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['text_align_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="text-align" data-value="left" aria-pressed="false">
							<?php echo esc_html( $oa_strings['text_align_left'] ); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="text-align" data-value="center" aria-pressed="false">
							<?php echo esc_html( $oa_strings['text_align_center'] ); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="text-align" data-value="right" aria-pressed="false">
							<?php echo esc_html( $oa_strings['text_align_right'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Pause Animations Section -->
			<?php if (isset($options['enable_animations_pause']) && $options['enable_animations_pause']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php echo esc_html( $oa_strings['pause_animations_title'] ); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="pause-animations" data-value="toggle" aria-pressed="false">
							<?php echo esc_html( $oa_strings['pause_animations_text'] ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Additional Links Section -->
			<?php
			// A link renders only when its URL is set, so an empty setting hides it.
			$panel_links = array(
				'statement' => array(
					'url'   => isset( $options['statement_url'] ) ? $options['statement_url'] : '',
					'label' => $oa_strings['statement_text'],
				),
				'sitemap' => array(
					'url'   => isset( $options['sitemap_url'] ) ? $options['sitemap_url'] : '',
					'label' => $oa_strings['sitemap_text'],
				),
				'help' => array(
					'url'   => isset( $options['help_url'] ) ? $options['help_url'] : '',
					'label' => $oa_strings['help_text'],
				),
				'feedback' => array(
					'url'   => isset( $options['feedback_url'] ) ? $options['feedback_url'] : '',
					'label' => $oa_strings['feedback_text'],
				),
			);

			$panel_links = apply_filters( 'open_accessibility_panel_links', $panel_links );
			$panel_links = array_filter(
				is_array( $panel_links ) ? $panel_links : array(),
				function ( $link ) {
					return is_array( $link ) && ! empty( $link['url'] ) && ! empty( $link['label'] );
				}
			);
			?>
			<?php if ( ! empty( $panel_links ) ) : ?>
				<div class="open-accessibility-widget-section open-accessibility-links-section">
					<?php foreach ( $panel_links as $link_key => $link ) : ?>
						<a href="<?php echo esc_url( $link['url'] ); ?>"
						   class="open-accessibility-link open-accessibility-link-<?php echo esc_attr( $link_key ); ?>">
							<?php echo esc_html( $link['label'] ); ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="open-accessibility-widget-footer">
			<button class="open-accessibility-hide-widget">
				<?php echo esc_html( $oa_strings['hide_widget_text'] ); ?>
			</button>
		</div>
	</div>
</div>
