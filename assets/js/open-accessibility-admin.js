/**
 * Admin JavaScript for Open Accessibility plugin
 */
(function($) {
    'use strict';

    // Initialize color pickers
    function initColorPickers() {
        $('.color-picker').wpColorPicker();
    }

    // Statement generator
    function initStatementGenerator() {
        const $generateBtn = $('#generate-statement');
        const $generatorContainer = $('#statement-generator-container');
        const $createBtn = $('#create-statement');
        const $cancelBtn = $('#cancel-statement');

        // Show the statement generator form
        $generateBtn.on('click', function(e) {
            e.preventDefault();
            $generatorContainer.slideDown();
            $(this).hide();
        });

        // Hide the statement generator form
        $cancelBtn.on('click', function() {
            $generatorContainer.slideUp();
            $generateBtn.show();
        });

        // Create statement
        $createBtn.on('click', function() {
            const $btn = $(this);
            const $form = $btn.closest('.statement-form');

            // Get form data
            const orgName = $form.find('#statement-org-name').val();
            const contactEmail = $form.find('#statement-contact-email').val();
            const conformanceLevel = $form.find('#statement-conformance').val();
            const createPage = $form.find('#statement-create-page').is(':checked');

            // Validate email
            if (contactEmail && !isValidEmail(contactEmail)) {
                alert(open_accessibility_admin.i18n.invalid_email);
                return;
            }

            // Disable button and show loading state
            $btn.prop('disabled', true).text(open_accessibility_admin.i18n.generating);

            // Send AJAX request
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'open_accessibility_generate_statement',
                    nonce: open_accessibility_admin.nonce,
                    org_name: orgName,
                    contact_email: contactEmail,
                    conformance_level: conformanceLevel,
                    create_page: createPage.toString()
                },
                success: function(response) {
                    if (response.success) {
                        if (createPage && response.data.page_url) {
                            // Update statement URL field
                            $('input[name="open_accessibility_options[statement_url]"]').val(response.data.page_url);

                            // Show success message
                            showNotice('success', response.data.message);

                            // Optionally offer to view the new page
                            if (confirm(open_accessibility_admin.i18n.view_statement)) {
                                window.open(response.data.page_url, '_blank');
                            }
                        } else if (response.data.statement) {
                            // Show statement in modal or new window
                            showStatementPreview(response.data.statement);
                        }

                        // Reset form
                        $generatorContainer.slideUp();
                        $generateBtn.show();
                    } else {
                        showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    showNotice('error', open_accessibility_admin.i18n.ajax_error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(open_accessibility_admin.i18n.create_statement);
                }
            });
        });
    }

    // Show statement preview
    function showStatementPreview(statement) {
        // Create modal if it doesn't exist
        if ($('#statement-preview-modal').length === 0) {
            $('body').append(`
                <div id="statement-preview-modal" class="statement-preview-modal">
                    <div class="statement-preview-content">
                        <span class="statement-preview-close">&times;</span>
                        <div class="statement-preview-body"></div>
                        <div class="statement-preview-actions">
                            <button class="button statement-preview-copy">${open_accessibility_admin.i18n.copy_statement}</button>
                            <button class="button button-primary statement-preview-close-btn">${open_accessibility_admin.i18n.close}</button>
                        </div>
                    </div>
                </div>
            `);

            // Close modal events
            $('.statement-preview-close, .statement-preview-close-btn').on('click', function() {
                $('#statement-preview-modal').hide();
            });

            // Copy statement
            $('.statement-preview-copy').on('click', function() {
                const statementText = $('.statement-preview-body').html();
                copyToClipboard(statementText);
                showNotice('success', open_accessibility_admin.i18n.copied_statement);
            });

            // Close when clicking outside
            $(window).on('click', function(event) {
                if ($(event.target).is('#statement-preview-modal')) {
                    $('#statement-preview-modal').hide();
                }
            });
        }

        // Update content and show modal
        $('.statement-preview-body').html(statement);
        $('#statement-preview-modal').show();
    }

    // Copy to clipboard helper
    function copyToClipboard(html) {
        const textarea = document.createElement('textarea');
        textarea.value = html;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }

    // Show admin notice
    function showNotice(type, message) {
        const $notice = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">${open_accessibility_admin.i18n.dismiss_notice}</span>
                </button>
            </div>
        `);

        // Add notice at the top of the form
        $('.wrap h1').after($notice);

        // Initialize dismiss button
        $notice.find('.notice-dismiss').on('click', function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });

        // Auto dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Validate email helper
    function isValidEmail(email) {
        const regex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/;
        return regex.test(email);
    }

    // Toggle settings sections based on dependencies
    function handleDependencies() {
        // Skip to content field depends on the skip to content option
        const $skipToContent = $('input[name="open_accessibility_options[enable_skip_to_content]"]');
        const $skipToContentField = $('#skip_to_element_id').closest('tr');

        function toggleSkipToContentField() {
            if ($skipToContent.is(':checked')) {
                $skipToContentField.show();
            } else {
                $skipToContentField.hide();
            }
        }

        $skipToContent.on('change', toggleSkipToContentField);
        toggleSkipToContentField();

        // Sitemap URL field depends on sitemap option
        const $sitemapOption = $('input[name="open_accessibility_options[enable_sitemap]"]');
        const $sitemapField = $('#sitemap_url').closest('tr');

        function toggleSitemapField() {
            if ($sitemapOption.is(':checked')) {
                $sitemapField.show();
            } else {
                $sitemapField.hide();
            }
        }

        $sitemapOption.on('change', toggleSitemapField);
        toggleSitemapField();
    }

    // Preview widget
    function initWidgetPreview() {
        const $previewBtn = $('#preview-widget');

        $previewBtn.on('click', function(e) {
            e.preventDefault();

            // Get current settings
            const iconColor = $('.color-picker[name="open_accessibility_options[icon_color]"]').val() || '#ffffff';
            const bgColor = $('.color-picker[name="open_accessibility_options[bg_color]"]').val() || '#4054b2';
            const icon = $('input[name="open_accessibility_options[icon]"]:checked').val() || 'a11y';
            const position = $('input[name="open_accessibility_options[position]"]:checked').val() || 'left';
            const size = $('select[name="open_accessibility_options[icon_size]"]').val() || 'medium';

            // Create preview HTML
            const previewHtml = `
                <div class="open-a11y-widget-preview position-${position} size-${size}">
                    <div class="preview-toggle-button" style="background-color: ${bgColor}; color: ${iconColor};">
                        <span class="preview-icon icon-${icon}"></span>
                    </div>
                    <div class="preview-panel">
                        <div class="preview-header" style="background-color: ${bgColor}; color: ${iconColor};">
                            <h2>${open_accessibility_admin.i18n.accessibility_options}</h2>
                        </div>
                        <div class="preview-content">
                            <p>${open_accessibility_admin.i18n.preview_note}</p>
                        </div>
                    </div>
                </div>
            `;

            // Show preview in modal
            if ($('#widget-preview-modal').length === 0) {
                $('body').append(`
                    <div id="widget-preview-modal" class="widget-preview-modal">
                        <div class="widget-preview-content">
                            <span class="widget-preview-close">&times;</span>
                            <h2>${open_accessibility_admin.i18n.widget_preview}</h2>
                            <div class="widget-preview-body"></div>
                            <div class="widget-preview-actions">
                                <button class="button button-primary widget-preview-close-btn">${open_accessibility_admin.i18n.close}</button>
                            </div>
                        </div>
                    </div>
                `);

                // Close modal events
                $('.widget-preview-close, .widget-preview-close-btn').on('click', function() {
                    $('#widget-preview-modal').hide();
                });

                // Close when clicking outside
                $(window).on('click', function(event) {
                    if ($(event.target).is('#widget-preview-modal')) {
                        $('#widget-preview-modal').hide();
                    }
                });
            }

            // Update content and show modal
            $('.widget-preview-body').html(previewHtml);
            $('#widget-preview-modal').show();
        });
    }

    // Document ready
    $(function() {
        initColorPickers();
        initStatementGenerator();
        handleDependencies();
        initWidgetPreview();
    });

})(jQuery);