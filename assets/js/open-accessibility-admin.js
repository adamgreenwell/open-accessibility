/**
 * Admin JavaScript for Open Accessibility plugin
 */
(function($) {
    'use strict';

    // Initialize color pickers
    function initColorPickers() {
        $('.color-picker').wpColorPicker({
            change: function(event, ui) {
                // Update preview when color changes
                if ($(this).attr('name') === 'open_accessibility_options[icon_color]' ||
                    $(this).attr('name') === 'open_accessibility_options[bg_color]') {
                    updatePreviewColors();
                }
            }
        });
    }

    // Update the preview icon and background colors
    function updatePreviewColors() {
        const iconColor = $('.color-picker[name="open_accessibility_options[icon_color]"]').val() || '#ffffff';
        const bgColor = $('.color-picker[name="open_accessibility_options[bg_color]"]').val() || '#4054b2';

        $('.preview-toggle-button').css('background-color', bgColor);
        $('.preview-header').css('background-color', bgColor);
        $('.preview-toggle-button').css('color', iconColor);
        $('.preview-header').css('color', iconColor);

        // Update SVG colors in preview
        $('.preview-icon svg path, .preview-icon svg circle, .preview-icon svg g').attr('fill', iconColor);
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

    // Get SVG markup for the preview
    function getIconSvg(iconType, color) {
        let svg = '';
        switch (iconType) {
            case 'open-accessibility':
                // Logo SVG
                svg = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
					<g>
						<circle fill="${color}" r="5.51719" cy="12.13703" cx="32.55476"/>
						<path fill="${color}" d="m27.83315,42.78721a7.14807,7.14807 0 0 1 -1.10344,-0.99309l-7.00683,11.37865a2.75859,2.75859 0 0 0 4.69844,2.89321l6.7089,-10.90306l-3.29707,-2.3757z"/>
						<path fill="${color}" d="m46.93145,31.20995l-5.24464,-3.1459l-5.84822,-6.35249a4.87609,4.87609 0 0 0 -5.94973,-1.36275l-9.26887,4.11582a2.20687,2.20687 0 0 0 -1.07806,1.02951l-3.31031,6.62062a2.20687,2.20687 0 0 0 3.9481,1.97515l2.95611,-5.91332l3.90175,-1.73129l0,10.51907a4.95112,4.95112 0 0 0 2.0855,4.03196l8.1533,5.8758l1.97184,8.3784a2.75859,2.75859 0 1 0 5.37153,-1.26233l-2.20687,-9.37922a2.75859,2.75859 0 0 0 -1.06813,-1.60329l-5.02285,-3.64134a4.90809,4.90809 0 0 0 0.64661,-2.39998l0,-7.5122l1.68495,1.83391a2.23998,2.23998 0 0 0 0.48993,0.39834l5.51719,3.31031a2.20687,2.20687 0 0 0 2.27087,-3.78479z"/>
					</g>
				</svg>`;
                break;

            case 'universal-access':
                // Universal Access icon
                svg = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
                    <g transform="translate(0, 0)">
                        <circle data-color="color-2" cx="32" cy="8" r="6" fill="${color}"></circle>
                        <path d="M55.5,16H8.5a2.5,2.5,0,0,0,0,5L24,22V59a3,3,0,0,0,6,0l1-21h2l1,21a3,3,0,0,0,6,0V22l15.5-1a2.5,2.5,0,0,0,0-5Z" fill="${color}"></path>
                    </g>
                </svg>`;
                break;

            case 'accessible-icon-project':
                // Accessible Icon Project
                svg = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
                    <g>
                        <path stroke="null" id="accessible_icon_project"
                            d="m53.6162,29.30491c-0.69341,-0.71148 -1.6623,-1.08712 -2.65238,-1.03292l-11.98302,0.66703l6.59455,-7.51057c0.93946,-1.06986 1.20786,-2.49604 0.84465,-3.77045c-0.19167,-0.87327 -0.72051,-1.66784 -1.53297,-2.17549c-0.02495,-0.0178 -15.76321,-9.16053 -15.76321,-9.16053c-1.28514,-0.74654 -2.90451,-0.58206 -4.01354,0.40712l-7.68793,6.85776c-1.41643,1.2634 -1.54048,3.43586 -0.27699,4.85238c1.26358,1.41634 3.43613,1.54075 4.85247,0.2769l5.82851,-5.19913l4.81839,2.79807l-8.50521,9.6866c-3.52682,0.57518 -6.70008,2.20036 -9.19112,4.54355l4.4415,4.4415c2.0078,-1.82561 4.67368,-2.93992 7.59491,-2.93992c6.23089,0 11.30016,5.06936 11.30016,11.30034c0,2.92123 -1.11431,5.58694 -2.93974,7.59473l4.44123,4.4415c2.95862,-3.14518 4.77439,-7.37722 4.77439,-12.03623c0,-2.77571 -0.6444,-5.40064 -1.79019,-7.73479l4.63835,-0.25839l-1.12835,13.84056c-0.15428,1.8918 1.25455,3.55025 3.14644,3.70471c0.09472,0.00769 0.18925,0.01136 0.28289,0.01136c1.77141,0 3.27532,-1.36116 3.42164,-3.1577l1.44827,-17.77003c0.08085,-0.99017 -0.27064,-1.96666 -0.9637,-2.67796z" fill="${color}"/>
                        <path stroke="null" id="svg_3"
                            d="m47.2507,14.58258c3.17711,0 5.7524,-2.57555 5.7524,-5.75284c0,-3.17711 -2.57528,-5.75293 -5.7524,-5.75293c-3.17747,0 -5.75293,2.57582 -5.75293,5.75293c0,3.17729 2.57537,5.75284 5.75293,5.75284z" fill="${color}"/>
                        <path stroke="null" id="svg_4"
                            d="m26.9849,54.6474c-6.23098,0 -11.30034,-5.06936 -11.30034,-11.30034c0,-2.34829 0.72051,-4.53094 1.95127,-6.34l-4.48944,-4.48935c-2.33926,2.98518 -3.73771,6.74301 -3.73771,10.82935c0,9.70717 7.86904,17.57612 17.57621,17.57612c4.08652,0 7.844,-1.39846 10.82909,-3.7378l-4.48935,-4.48917c-1.80898,1.2304 -3.99163,1.95118 -6.33974,1.95118z" fill="${color}"/>
                    </g>
                </svg>`;
                break;

            case 'visually-impaired':
                // Visually Impaired icon
                svg = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
                    <g transform="translate(0, 0)">
                        <path d="M13.47,46.288l8.206-8.207A11.983,11.983,0,0,1,38.09,21.668l7.246-7.246A29.924,29.924,0,0,0,32,11c-5.135,0-18.366,1.826-30.288,18.744a3.9,3.9,0,0,0,.011,4.509A55.389,55.389,0,0,0,13.47,46.288Z" fill="${color}"></path>
                        <path d="M62.278,29.73A51.9,51.9,0,0,0,50.6,17.646l-8.262,8.262A11.983,11.983,0,0,1,25.917,42.326l-7.233,7.233A28.782,28.782,0,0,0,32,53c5.055,0,18.121-1.825,30.264-18.733A3.9,3.9,0,0,0,62.278,29.73Z" fill="${color}"></path>
                        <path data-color="color-2" d="M5,60a1,1,0,0,1-.707-1.707l54-54a1,1,0,1,1,1.414,1.414l-54,54A1,1,0,0,1,5,60Z" fill="${color}"></path>
                    </g>
                </svg>`;
                break;

            case 'service-dog':
                // Service Dog icon
                svg = `<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
                    <g transform="translate(0, 0)">
                        <path d="M40.387,21.812l-.194.317A6.038,6.038,0,0,1,35.074,25H13a10.026,10.026,0,0,1-9.895-8.636,1,1,0,0,0-1.982,0A11.892,11.892,0,0,0,9.792,29.552,7.943,7.943,0,0,0,9,33V58.41A2.593,2.593,0,0,0,11.59,61h.046A2.574,2.574,0,0,0,14.2,58.8L15.95,47.4l5.461-7.28,20.684,4.7,1.739,13.912A2.593,2.593,0,0,0,49,58.41V44.1l3.394-16.289Z" fill="${color}"></path>
                        <path d="M62.263,14.035,51.828,11.189,50.97,7.758a1,1,0,0,0-1.824-.279L41.434,20.1,52.816,25.79l1-4.79H57a6.006,6.006,0,0,0,6-6A1,1,0,0,0,62.263,14.035Z" fill="${color}"></path>
                        <path d="M35,21A17.019,17.019,0,0,1,18,4a1,1,0,0,1,2,0A15.017,15.017,0,0,0,35,19a1,1,0,0,1,0,2Z" fill="${color}" data-color="color-2"></path>
                    </g>
                </svg>`;
                break;

            case 'international':
                // International Symbol
                svg = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
                    <g>
                        <path fill="${color}" d="m28.528,10.44871c-0.02206,2.63731 -2.19905,4.75685 -4.86622,4.73781c-2.66717,-0.01904 -4.81774,-2.16948 -4.80716,-4.80687c0.01058,-2.63739 2.17831,-4.76619 4.84553,-4.7585c2.66722,0.00769 4.82711,2.14896 4.82801,4.78639"/>
                        <path fill="${color}" d="m19.69623,12.85492l2.05457,23.68158l17.51788,-0.10813l6.81251,16.3284l8.21827,-2.59524l-1.73016,-4.10913l-4.21727,1.08135l-6.05556,-14.92264l-16.3284,0l0,-3.35219l9.73216,0l0,-4.3254l-10.92164,0l-0.21627,-9.40775l0,0"/>
                        <path fill="${color}" d="m19.17205,24.87591c-25.42553,17.07942 6.54702,48.855 23.35513,24.85376l-1.85455,-6.37852c-7.47413,20.6243 -37.97369,4.02709 -21.19149,-12.86944l-0.30909,-5.60579z"/>
                    </g>
                </svg>`;
                break;

            default:
                // Default accessibility icon (universal-access is used as default)
                svg = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 64 64" aria-hidden="true">
					<g>
						<circle fill="${color}" r="5.51719" cy="12.13703" cx="32.55476"/>
						<path fill="${color}" d="m27.83315,42.78721a7.14807,7.14807 0 0 1 -1.10344,-0.99309l-7.00683,11.37865a2.75859,2.75859 0 0 0 4.69844,2.89321l6.7089,-10.90306l-3.29707,-2.3757z"/>
						<path fill="${color}" d="m46.93145,31.20995l-5.24464,-3.1459l-5.84822,-6.35249a4.87609,4.87609 0 0 0 -5.94973,-1.36275l-9.26887,4.11582a2.20687,2.20687 0 0 0 -1.07806,1.02951l-3.31031,6.62062a2.20687,2.20687 0 0 0 3.9481,1.97515l2.95611,-5.91332l3.90175,-1.73129l0,10.51907a4.95112,4.95112 0 0 0 2.0855,4.03196l8.1533,5.8758l1.97184,8.3784a2.75859,2.75859 0 1 0 5.37153,-1.26233l-2.20687,-9.37922a2.75859,2.75859 0 0 0 -1.06813,-1.60329l-5.02285,-3.64134a4.90809,4.90809 0 0 0 0.64661,-2.39998l0,-7.5122l1.68495,1.83391a2.23998,2.23998 0 0 0 0.48993,0.39834l5.51719,3.31031a2.20687,2.20687 0 0 0 2.27087,-3.78479z"/>
					</g>
				</svg>`;
                break;
        }

        return svg;
    }

    // Preview widget
    function initWidgetPreview() {
        const $previewBtn = $('#preview-widget');

        $previewBtn.on('click', function(e) {
            e.preventDefault();

            // Get current settings
            const iconColor = $('.color-picker[name="open_accessibility_options[icon_color]"]').val() || '#ffffff';
            const bgColor = $('.color-picker[name="open_accessibility_options[bg_color]"]').val() || '#4054b2';
            const icon = $('input[name="open_accessibility_options[icon]"]:checked').val() || 'accessibility';
            const position = $('input[name="open_accessibility_options[position]"]:checked').val() || 'left';
            const size = $('select[name="open_accessibility_options[icon_size]"]').val() || 'medium';

            // Create preview HTML
            const previewHtml = `
                <div class="open-accessibility-widget-preview position-${position} size-${size}">
                    <div class="preview-toggle-button" style="background-color: ${bgColor}; color: ${iconColor};">
                        <span class="preview-icon">${getIconSvg(icon, iconColor)}</span>
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

    // Debug log viewer
    function initDebugLogViewer() {
        const $viewLogsBtn = $('#open-accessibility-view-logs');
        const $clearLogsBtn = $('#open-accessibility-clear-logs');
        const $logViewer = $('#open-accessibility-log-viewer');
        const $logEntries = $('.open-accessibility-log-entries');

        // View logs
        $viewLogsBtn.on('click', function() {
            // Toggle visibility
            if ($logViewer.is(':visible')) {
                $logViewer.slideUp();
                $(this).text(open_accessibility_admin.i18n.view_logs);
                return;
            }

            $(this).text(open_accessibility_admin.i18n.hide_logs);
            $logEntries.html(open_accessibility_admin.i18n.loading);
            $logViewer.slideDown();

            // Fetch logs via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'open_accessibility_get_debug_logs',
                    nonce: open_accessibility_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.logs && response.data.logs.length > 0) {
                            $logEntries.html(response.data.logs);
                        } else {
                            $logEntries.html(open_accessibility_admin.i18n.no_logs);
                        }
                    } else {
                        $logEntries.html(response.data.message || open_accessibility_admin.i18n.error_getting_logs);
                    }
                },
                error: function() {
                    $logEntries.html(open_accessibility_admin.i18n.error_getting_logs);
                }
            });
        });

        // Clear logs
        $clearLogsBtn.on('click', function() {
            if (!confirm(open_accessibility_admin.i18n.confirm_clear_logs)) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'open_accessibility_clear_debug_logs',
                    nonce: open_accessibility_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showNotice('success', response.data.message);
                        if ($logViewer.is(':visible')) {
                            $logEntries.html(open_accessibility_admin.i18n.no_logs);
                        }
                    } else {
                        showNotice('error', response.data.message || open_accessibility_admin.i18n.error_clearing_logs);
                    }
                },
                error: function() {
                    showNotice('error', open_accessibility_admin.i18n.error_clearing_logs);
                }
            });
        });
    }

    // Document ready
    $(function() {
        initColorPickers();
        initStatementGenerator();
        handleDependencies();
        initWidgetPreview();
        initDebugLogViewer();

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

})(jQuery);