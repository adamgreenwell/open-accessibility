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
$icon = isset($options['icon']) ? $options['icon'] : 'a11y';
$icon_size = isset($options['icon_size']) ? $options['icon_size'] : 'medium';
$position = isset($options['position']) ? $options['position'] : 'left';
$icon_color = isset($options['icon_color']) ? $options['icon_color'] : '#ffffff';
$bg_color = isset($options['bg_color']) ? $options['bg_color'] : '#4054b2';

// Build widget classes
$widget_classes = array(
	'open-a11y-widget-wrapper',
	'position-' . $position,
	'size-' . $icon_size
);
?>

<div class="<?php echo esc_attr(implode(' ', $widget_classes)); ?>">
	<!-- Accessibility Widget Toggle Button -->
	<button
		aria-label="<?php esc_attr_e('Open accessibility tools', 'open-accessibility'); ?>"
		class="open-a11y-toggle-button"
		style="background-color: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($icon_color); ?>;"
	>
		<span class="open-a11y-icon icon-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
	</button>

	<!-- Accessibility Widget Panel -->
	<div class="open-a11y-widget-panel" aria-hidden="true">
		<div class="open-a11y-widget-header" style="background-color: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($icon_color); ?>;">
			<h2><?php esc_html_e('Accessibility Options', 'open-accessibility'); ?></h2>
			<button class="open-a11y-close" aria-label="<?php esc_attr_e('Close accessibility tools', 'open-accessibility'); ?>">
				<span aria-hidden="true">×</span>
			</button>
		</div>

		<div class="open-a11y-widget-content">
			<!-- Reset Button -->
			<div class="open-a11y-widget-section">
				<h3><?php esc_html_e('Reset Settings', 'open-accessibility'); ?></h3>
				<button class="open-a11y-action-button open-a11y-reset-button">
					<?php esc_html_e('Reset All', 'open-accessibility'); ?>
				</button>
			</div>

			<!-- Contrast Section -->
			<?php if (isset($options['enable_contrast']) && $options['enable_contrast']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Contrast', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="contrast" data-value="high">
							<?php esc_html_e('High Contrast', 'open-accessibility'); ?>
						</button>
						<button class="open-a11y-action-button" data-action="contrast" data-value="negative">
							<?php esc_html_e('Negative Contrast', 'open-accessibility'); ?>
						</button>
						<button class="open-a11y-action-button" data-action="contrast" data-value="light">
							<?php esc_html_e('Light Background', 'open-accessibility'); ?>
						</button>
						<button class="open-a11y-action-button" data-action="contrast" data-value="dark">
							<?php esc_html_e('Dark Background', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Grayscale Section -->
			<?php if (isset($options['enable_grayscale']) && $options['enable_grayscale']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Grayscale', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="grayscale" data-value="toggle">
							<?php esc_html_e('Grayscale', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Text Size Section -->
			<?php if (isset($options['enable_text_size']) && $options['enable_text_size']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Text Size', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="text-size" data-value="increase">
							<?php esc_html_e('Increase Text', 'open-accessibility'); ?>
						</button>
						<button class="open-a11y-action-button" data-action="text-size" data-value="decrease">
							<?php esc_html_e('Decrease Text', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Readable Font Section -->
			<?php if (isset($options['enable_readable_font']) && $options['enable_readable_font']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Readable Font', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="readable-font" data-value="toggle">
							<?php esc_html_e('Readable Font', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Links Underline Section -->
			<?php if (isset($options['enable_links_underline']) && $options['enable_links_underline']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Links Underline', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="links-underline" data-value="toggle">
							<?php esc_html_e('Links Underline', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Hide Images Section -->
			<?php if (isset($options['enable_hide_images']) && $options['enable_hide_images']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Hide Images', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="hide-images" data-value="toggle">
							<?php esc_html_e('Hide Images', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Reading Guide Section -->
			<?php if (isset($options['enable_reading_guide']) && $options['enable_reading_guide']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Reading Guide', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="reading-guide" data-value="toggle">
							<?php esc_html_e('Reading Guide', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Focus Outline Section -->
			<?php if (isset($options['enable_focus_outline']) && $options['enable_focus_outline']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Focus Outline', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="focus-outline" data-value="toggle">
							<?php esc_html_e('Focus Outline', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Line Height Section -->
			<?php if (isset($options['enable_line_height']) && $options['enable_line_height']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Line Height', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="line-height" data-value="toggle">
							<?php esc_html_e('Increase Line Height', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Text Align Section -->
			<?php if (isset($options['enable_text_align']) && $options['enable_text_align']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Text Align', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="text-align" data-value="left">
							<?php esc_html_e('Left', 'open-accessibility'); ?>
						</button>
						<button class="open-a11y-action-button" data-action="text-align" data-value="center">
							<?php esc_html_e('Center', 'open-accessibility'); ?>
						</button>
						<button class="open-a11y-action-button" data-action="text-align" data-value="right">
							<?php esc_html_e('Right', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Pause Animations Section -->
			<?php if (isset($options['enable_animations_pause']) && $options['enable_animations_pause']): ?>
				<div class="open-a11y-widget-section">
					<h3><?php esc_html_e('Pause Animations', 'open-accessibility'); ?></h3>
					<div class="open-a11y-actions">
						<button class="open-a11y-action-button" data-action="pause-animations" data-value="toggle">
							<?php esc_html_e('Pause Animations', 'open-accessibility'); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>

			<!-- Additional Links Section -->
			<div class="open-a11y-widget-section open-a11y-links-section">
				<?php if (isset($options['enable_sitemap']) && $options['enable_sitemap'] && !empty($options['sitemap_url'])): ?>
					<a href="<?php echo esc_url($options['sitemap_url']); ?>" class="open-a11y-link">
						<?php esc_html_e('Sitemap', 'open-accessibility'); ?>
					</a>
				<?php endif; ?>

				<?php if (!empty($options['statement_url'])): ?>
					<a href="<?php echo esc_url($options['statement_url']); ?>" class="open-a11y-link">
						<?php esc_html_e('Accessibility Statement', 'open-accessibility'); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="open-a11y-widget-footer" style="background-color: <?php echo esc_attr($bg_color); ?>; color: <?php echo esc_attr($icon_color); ?>;">
			<button class="open-a11y-hide-widget">
				<?php esc_html_e('Hide Accessibility Panel', 'open-accessibility'); ?>
			</button>
		</div>
	</div>
</div>