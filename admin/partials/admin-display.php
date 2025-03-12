<?php
/**
 * Admin settings page template
 *
 * @package    Open_Accessibility
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}
?>

<div class="wrap">
	<h1><?php esc_html_e('Open Accessibility Settings', 'open-accessibility'); ?></h1>
	<div class="open-accessibility-admin-header">
		<div class="open-accessibility-admin-logo">
			<img src="<?php echo esc_url(OPEN_ACCESSIBILITY_ASSETS_URL . 'images/logo.svg'); ?>" alt="<?php esc_attr_e('Open Accessibility Logo', 'open-accessibility'); ?>">
		</div>
		<div class="open-accessibility-admin-version">
            <?php /* translators: %s: version number */ ?>
			<?php printf(esc_html__('Version %s', 'open-accessibility'), esc_html(OPEN_ACCESSIBILITY_VERSION)); ?>
		</div>
	</div>

	<div class="open-accessibility-admin-description">
		<p>
			<?php esc_html_e('Configure the Open Accessibility widget to improve the accessibility of your website. This plugin helps you comply with accessibility standards and regulations like WCAG 2.1 and ADA.', 'open-accessibility'); ?>
		</p>
	</div>

	<div class="open-accessibility-admin-nav">
		<nav class="nav-tab-wrapper">
			<a href="#tab-general" class="nav-tab nav-tab-active"><?php esc_html_e('General', 'open-accessibility'); ?></a>
			<a href="#tab-design" class="nav-tab"><?php esc_html_e('Design', 'open-accessibility'); ?></a>
			<a href="#tab-position" class="nav-tab"><?php esc_html_e('Position', 'open-accessibility'); ?></a>
			<a href="#tab-features" class="nav-tab"><?php esc_html_e('Features', 'open-accessibility'); ?></a>
			<a href="#tab-statement" class="nav-tab"><?php esc_html_e('Statement', 'open-accessibility'); ?></a>
            <a href="#tab-advanced" class="nav-tab"><?php esc_html_e('Advanced', 'open-accessibility'); ?></a>
		</nav>
	</div>

	<form method="post" action="options.php">
		<?php settings_fields('open_accessibility_options_group'); ?>

		<div class="open-accessibility-admin-content">
			<!-- General Tab -->
			<div id="tab-general" class="open-accessibility-tab active">
				<h2><?php esc_html_e('General Settings', 'open-accessibility'); ?></h2>
				<table class="form-table">
					<?php do_settings_fields('open-accessibility-settings', 'open_accessibility_general'); ?>
				</table>
			</div>

			<!-- Design Tab -->
			<div id="tab-design" class="open-accessibility-tab">
				<h2><?php esc_html_e('Widget Design', 'open-accessibility'); ?></h2>
				<table class="form-table">
					<?php do_settings_fields('open-accessibility-settings', 'open_accessibility_design'); ?>
				</table>
				<p>
					<button type="button" id="preview-widget" class="button button-secondary">
						<?php esc_html_e('Preview Widget', 'open-accessibility'); ?>
					</button>
				</p>
			</div>

			<!-- Position Tab -->
			<div id="tab-position" class="open-accessibility-tab">
				<h2><?php esc_html_e('Widget Position', 'open-accessibility'); ?></h2>
				<table class="form-table">
					<?php do_settings_fields('open-accessibility-settings', 'open_accessibility_position'); ?>
				</table>
			</div>

			<!-- Features Tab -->
			<div id="tab-features" class="open-accessibility-tab">
				<h2><?php esc_html_e('Widget Features', 'open-accessibility'); ?></h2>
				<table class="form-table">
					<?php do_settings_fields('open-accessibility-settings', 'open_accessibility_features'); ?>
				</table>
			</div>

			<!-- Statement Tab -->
			<div id="tab-statement" class="open-accessibility-tab">
				<h2><?php esc_html_e('Accessibility Statement', 'open-accessibility'); ?></h2>
				<table class="form-table">
					<?php do_settings_fields('open-accessibility-settings', 'open_accessibility_statement'); ?>
				</table>
			</div>

            <!-- Advanced Tab -->
            <div id="tab-advanced" class="open-accessibility-tab">
                <h2><?php esc_html_e('Advanced Settings', 'open-accessibility'); ?></h2>

                <div class="open-accessibility-debug-controls">
                    <p>
						<?php
						$options = get_option('open_accessibility_options', array());
						$debug_enabled = isset($options['enable_debug']) && $options['enable_debug'];

						if ($debug_enabled):
							?>
                            <span class="open-accessibility-debug-status enabled"><?php esc_html_e('Debug mode is enabled', 'open-accessibility'); ?></span>
						<?php else: ?>
                            <span class="open-accessibility-debug-status disabled"><?php esc_html_e('Debug mode is disabled', 'open-accessibility'); ?></span>
						<?php endif; ?>
                    </p>

                    <p>
                        <button type="button" id="open-accessibility-view-logs" class="button button-secondary">
							<?php esc_html_e('View Debug Logs', 'open-accessibility'); ?>
                        </button>

                        <button type="button" id="open-accessibility-clear-logs" class="button">
							<?php esc_html_e('Clear Debug Logs', 'open-accessibility'); ?>
                        </button>
                    </p>
                </div>

                <div id="open-accessibility-log-viewer" style="display: none;">
                    <div class="open-accessibility-log-content">
                        <pre class="open-accessibility-log-entries"></pre>
                    </div>
                </div>

                <hr>

                <h3><?php esc_html_e('Advanced Settings', 'open-accessibility'); ?></h3>
                <table class="form-table">
					<?php do_settings_fields('open-accessibility-settings', 'open_accessibility_advanced'); ?>
                </table>
            </div>
		</div>

		<?php submit_button(); ?>
	</form>

	<div class="open-accessibility-admin-footer">
        <div class="open-accessibility-admin-resources">
            <h3><?php esc_html_e('Resources', 'open-accessibility'); ?></h3>
            <ul>
                <li><a href="https://www.w3.org/WAI/standards-guidelines/wcag/" target="_blank"><?php esc_html_e('WCAG Guidelines', 'open-accessibility'); ?></a></li>
                <li><a href="https://www.w3.org/WAI/fundamentals/accessibility-intro/" target="_blank"><?php esc_html_e('Introduction to Web Accessibility', 'open-accessibility'); ?></a></li>
                <li><a href="https://www.ada.gov/" target="_blank"><?php esc_html_e('Americans with Disabilities Act (ADA)', 'open-accessibility'); ?></a></li>
            </ul>
        </div>

        <div class="open-accessibility-admin-disclaimer">
            <p><strong><?php esc_html_e('Disclaimer:', 'open-accessibility'); ?></strong> <?php esc_html_e('While this plugin helps improve your website\'s accessibility, it does not guarantee full compliance with all accessibility standards and regulations. Regular accessibility audits and testing with real users are recommended.', 'open-accessibility'); ?></p>
        </div>
    </div>
</div>

	<!-- Tab Navigation Script -->
	<script>
        jQuery(document).ready(function($) {
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();

                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');

                // Show selected tab content
                const target = $(this).attr('href');
                $('.open-accessibility-tab').removeClass('active');
                $(target).addClass('active');
            });
        });
	</script>