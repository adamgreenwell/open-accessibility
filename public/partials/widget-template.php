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

$options = get_option('open_accessibility_options', array());

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

?>

<div class="<?php echo esc_attr(implode(' ', $widget_classes)); ?>" data-oa-ignore="true">
	<!-- Accessibility Widget Toggle Button -->
    <button
            aria-label="<?php esc_attr_e('Open accessibility tools', 'open-accessibility'); ?>"
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
			<h2 id="<?php echo esc_attr( $panel_title_id ); ?>"><?php esc_html_e('Accessibility Options', 'open-accessibility'); ?></h2>
			<button class="open-accessibility-close" aria-label="<?php esc_attr_e('Close accessibility tools', 'open-accessibility'); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>

		<div class="open-accessibility-widget-content">
			<!-- Reset Button -->
			<div class="open-accessibility-widget-section">
				<h3><?php esc_html_e('Reset Settings', 'open-accessibility'); ?></h3>
				<button class="open-accessibility-action-button open-accessibility-reset-button">
					<?php esc_html_e('Reset All', 'open-accessibility'); ?>
				</button>
			</div>

			<!-- Contrast Section -->
			<?php if (isset($options['enable_contrast']) && $options['enable_contrast']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Contrast', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="contrast" data-value="high" aria-pressed="false">
							<?php esc_html_e('High Contrast', 'open-accessibility'); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="contrast" data-value="negative" aria-pressed="false">
							<?php esc_html_e('Negative Contrast', 'open-accessibility'); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="contrast" data-value="light" aria-pressed="false">
							<?php esc_html_e('Light Background', 'open-accessibility'); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="contrast" data-value="dark" aria-pressed="false">
							<?php esc_html_e('Dark Background', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Grayscale Section -->
			<?php if (isset($options['enable_grayscale']) && $options['enable_grayscale']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Grayscale', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="grayscale" data-value="toggle" aria-pressed="false">
							<?php esc_html_e('Grayscale', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Text Size Section -->
			<?php if (isset($options['enable_text_size']) && $options['enable_text_size']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Text Size', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="text-size" data-value="decrease">
							<?php esc_html_e('Decrease', 'open-accessibility'); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="text-size" data-max="5" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php esc_attr_e('Text size level', 'open-accessibility'); ?>"></span>
						<button class="open-accessibility-action-button" data-action="text-size" data-value="increase">
							<?php esc_html_e('Increase', 'open-accessibility'); ?>
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
					<h3><?php esc_html_e('Readable Font', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="set-font" data-value="default" aria-pressed="false">
							<?php esc_html_e('Default Font', 'open-accessibility'); ?>
						</button>
						<?php if (isset($options['enable_font_atkinson']) && $options['enable_font_atkinson']): ?>
							<button class="open-accessibility-action-button" data-action="set-font" data-value="atkinson" aria-pressed="false">
								<?php esc_html_e('Atkinson Hyperlegible', 'open-accessibility'); ?>
							</button>
						<?php endif; ?>
						<?php if (isset($options['enable_font_opendyslexic']) && $options['enable_font_opendyslexic']): ?>
							<button class="open-accessibility-action-button" data-action="set-font" data-value="opendyslexic" aria-pressed="false">
								<?php esc_html_e('OpenDyslexic', 'open-accessibility'); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Letter Spacing Section -->
			<?php if (isset($options['enable_letter_spacing']) && $options['enable_letter_spacing']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Letter Spacing', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="letter-spacing" data-value="decrease" aria-label="<?php esc_attr_e('Decrease letter spacing', 'open-accessibility'); ?>">
							<?php esc_html_e('Decrease', 'open-accessibility'); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="letter-spacing" data-max="3" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php esc_attr_e('Letter spacing level', 'open-accessibility'); ?>"></span>
						<button class="open-accessibility-action-button" data-action="letter-spacing" data-value="increase" aria-label="<?php esc_attr_e('Increase letter spacing', 'open-accessibility'); ?>">
							<?php esc_html_e('Increase', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Word Spacing Section -->
			<?php if (isset($options['enable_word_spacing']) && $options['enable_word_spacing']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Word Spacing', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						 <button class="open-accessibility-action-button" data-action="word-spacing" data-value="decrease" aria-label="<?php esc_attr_e('Decrease word spacing', 'open-accessibility'); ?>">
							<?php esc_html_e('Decrease', 'open-accessibility'); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="word-spacing" data-max="3" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php esc_attr_e('Word spacing level', 'open-accessibility'); ?>"></span>
						<button class="open-accessibility-action-button" data-action="word-spacing" data-value="increase" aria-label="<?php esc_attr_e('Increase word spacing', 'open-accessibility'); ?>">
							<?php esc_html_e('Increase', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Links Underline Section -->
			<?php if (isset($options['enable_links_underline']) && $options['enable_links_underline']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Links Underline', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="links-underline" data-value="toggle" aria-pressed="false">
							<?php esc_html_e('Links Underline', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Hide Images Section -->
			<?php if (isset($options['enable_hide_images']) && $options['enable_hide_images']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Hide Images', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="hide-images" data-value="toggle" aria-pressed="false">
							<?php esc_html_e('Hide Images', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Reading Guide Section -->
			<?php if (isset($options['enable_reading_guide']) && $options['enable_reading_guide']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Reading Guide', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="reading-guide" data-value="toggle" aria-pressed="false">
							<?php esc_html_e('Reading Guide', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Focus Outline Section -->
			<?php if (isset($options['enable_focus_outline']) && $options['enable_focus_outline']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Focus Outline', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="focus-outline" data-value="toggle" aria-pressed="false">
							<?php esc_html_e('Focus Outline', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Line Height Section -->
			<?php if (isset($options['enable_line_height']) && $options['enable_line_height']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Line Height', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="line-height" data-value="decrease" aria-label="<?php esc_attr_e('Decrease line height', 'open-accessibility'); ?>">
							<?php esc_html_e('Decrease', 'open-accessibility'); ?>
						</button>
						<span class="open-accessibility-indicator" data-action="line-height" data-max="3" role="status" aria-live="polite" aria-atomic="true" aria-label="<?php esc_attr_e('Line height level', 'open-accessibility'); ?>"></span>
						<button class="open-accessibility-action-button" data-action="line-height" data-value="increase" aria-label="<?php esc_attr_e('Increase line height', 'open-accessibility'); ?>">
							<?php esc_html_e('Increase', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Text Align Section -->
			<?php if (isset($options['enable_text_align']) && $options['enable_text_align']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Text Align', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="text-align" data-value="left" aria-pressed="false">
							<?php esc_html_e('Left', 'open-accessibility'); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="text-align" data-value="center" aria-pressed="false">
							<?php esc_html_e('Center', 'open-accessibility'); ?>
						</button>
						<button class="open-accessibility-action-button" data-action="text-align" data-value="right" aria-pressed="false">
							<?php esc_html_e('Right', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Pause Animations Section -->
			<?php if (isset($options['enable_animations_pause']) && $options['enable_animations_pause']): ?>
				<div class="open-accessibility-widget-section">
					<h3><?php esc_html_e('Pause Animations', 'open-accessibility'); ?></h3>
					<div class="open-accessibility-actions">
						<button class="open-accessibility-action-button" data-action="pause-animations" data-value="toggle" aria-pressed="false">
							<?php esc_html_e('Pause Animations', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Additional Links Section -->
			<div class="open-accessibility-widget-section open-accessibility-links-section">
				<?php if (isset($options['enable_sitemap']) && $options['enable_sitemap'] && !empty($options['sitemap_url'])): ?>
					<a href="<?php echo esc_url($options['sitemap_url']); ?>" class="open-accessibility-link">
						<?php esc_html_e('Sitemap', 'open-accessibility'); ?>
					</a>
				<?php endif; ?>

				<?php if (!empty($options['statement_url'])): ?>
					<a href="<?php echo esc_url($options['statement_url']); ?>" class="open-accessibility-link">
						<?php esc_html_e('Accessibility Statement', 'open-accessibility'); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="open-accessibility-widget-footer">
			<button class="open-accessibility-hide-widget">
				<?php esc_html_e('Hide Accessibility Panel', 'open-accessibility'); ?>
			</button>
		</div>
	</div>
</div>
