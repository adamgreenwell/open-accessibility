/**
 * Frontend JavaScript for the Open Accessibility plugin
 *
 * Handles the accessibility widget functionality
 */

(function($) {
    'use strict';

    // Store state in local storage - site-wide key
    const storageKey = 'open-accessibility-settings';
    let accessibilityState = {
        active: false,
        contrast: '',
        grayscale: false,
        textSize: 0,
        selectedFont: 'default',
        linksUnderline: false,
        hideImages: false,
        readingGuide: false,
        focusOutline: false,
        lineHeightLevel: 0,
        textAlign: '',
        pauseAnimations: false,
        letterSpacingLevel: 0,
        wordSpacingLevel: 0
    };

    const MAX_SPACING_LEVEL = 3;
    const MAX_TEXT_SIZE = 5;

    let isShortcodeEmbed = false;
    const DEFAULT_TYPOGRAPHY_TARGETS = {
        content_roots: [
            'main',
            'article',
            '[role="main"]',
            '.entry-content',
            '.post-content',
            '.page-content',
            '.wp-block-post-content',
            '.site-content',
            '.site-main',
            '#content',
            '#primary'
        ],
        text_elements: [
            'p',
            'li',
            'blockquote',
            'dd',
            'dt',
            'figcaption',
            'caption',
            'td',
            'th',
            'label'
        ],
        heading_elements: [
            'h1',
            'h2',
            'h3',
            'h4',
            'h5',
            'h6'
        ],
        excluded_selectors: [
            '.open-accessibility-widget-wrapper',
            '.open-accessibility-reading-guide',
            '.open-accessibility-skip-to-content-link',
            '.open-accessibility-skip-to-content-backdrop',
            '.open-accessibility-ignore',
            '[data-oa-ignore]',
            'aside',
            '[role="complementary"]',
            '.sidebar',
            '.sidebar-primary',
            '.sidebar-secondary',
            '.widget',
            '.widget-area',
            'nav',
            '.nav',
            '.menu',
            '.navigation',
            '.pagination',
            '.breadcrumbs',
            '.breadcrumb',
            'button',
            'input',
            'select',
            'textarea',
            'svg',
            'img',
            'video',
            'audio',
            'iframe',
            'canvas',
            'code',
            'pre',
            'kbd',
            'samp',
            '.screen-reader-text'
        ]
    };
    const TEXT_SIZE_MULTIPLIERS = [1, 1.1, 1.2, 1.3, 1.4, 1.5];
    const LINE_HEIGHT_MULTIPLIERS = [0, 1.6, 1.8, 2.0];
    const LETTER_SPACING_STEPS = [0, 0.12, 0.18, 0.24];
    const WORD_SPACING_STEPS = [0, 0.16, 0.24, 0.32];
    const typographyTargets = normalizeTypographyTargets(
        open_accessibility_data &&
        open_accessibility_data.options &&
        open_accessibility_data.options.typography_targets
            ? open_accessibility_data.options.typography_targets
            : {}
    );

    function normalizeSelectorList(value, fallback) {
        if (!Array.isArray(value)) {
            return fallback.slice();
        }

        const selectors = value
            .filter((selector) => typeof selector === 'string' && selector.trim().length > 0)
            .map((selector) => selector.trim());

        return selectors.length ? selectors : fallback.slice();
    }

    function normalizeTypographyTargets(config) {
        return {
            contentRoots: normalizeSelectorList(config.content_roots, DEFAULT_TYPOGRAPHY_TARGETS.content_roots),
            textElements: normalizeSelectorList(config.text_elements, DEFAULT_TYPOGRAPHY_TARGETS.text_elements),
            headingElements: normalizeSelectorList(config.heading_elements, DEFAULT_TYPOGRAPHY_TARGETS.heading_elements),
            excludedSelectors: normalizeSelectorList(config.excluded_selectors, DEFAULT_TYPOGRAPHY_TARGETS.excluded_selectors)
        };
    }

    // Initialize
    function initAccessibility() {
        // Load saved state
        loadState();

        // Detect if widget is embedded via shortcode (inline positioning)
        isShortcodeEmbed = $('.open-accessibility-widget-wrapper').closest('.open-accessibility-shortcode').length > 0;

        // Create reading guide element
        if ($('.open-accessibility-reading-guide').length === 0) {
            $('body').append('<div class="open-accessibility-reading-guide"></div>');
        }

        // Apply saved state
        applyState();

        // Button click handler
        $('.open-accessibility-toggle-button').on('click', toggleAccessibilityPanel);

        // Close button
        $('.open-accessibility-close').on('click', closeAccessibilityPanel);

        // Hide widget button
        $('.open-accessibility-hide-widget').on('click', hideAccessibilityWidget);

        // Action buttons
        $('.open-accessibility-action-button').on('click', function(e) {
            handleActionButton.call(this, e);
        });

        // Reading guide mouse movement
        $(document).on('mousemove', handleReadingGuide);

        // Close panel when clicking outside
        $(document).on('click', function(e) {
            const $panel = $('.open-accessibility-widget-panel');
            const $button = $('.open-accessibility-toggle-button');
            if (
                $panel.hasClass('oa-panel-is-active') &&
                !$(e.target).closest($panel).length &&
                !$(e.target).closest($button).length
            ) {
                closeAccessibilityPanel();
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // ESC key closes the panel
            if (e.key === 'Escape' && $('.open-accessibility-widget-panel').hasClass('oa-panel-is-active')) {
                closeAccessibilityPanel();
                $('.open-accessibility-toggle-button').trigger('focus');
            }
        });
        
        // Ensure widget wrapper stays in viewport on scroll
        $(window).on('scroll', function() {
            if (isShortcodeEmbed) return;

            const $widgetWrapper = $('.open-accessibility-widget-wrapper');
            const $panel = $('.open-accessibility-widget-panel');
            const isMobile = window.innerWidth < 768;

            if (!($panel.hasClass('oa-panel-is-active') && isMobile)) {
                if ($widgetWrapper.hasClass('position-left') || $widgetWrapper.hasClass('position-right')) {
                    $widgetWrapper.css({
                        'top': '50vh',
                        'transform': 'translateY(-50%)'
                    });
                } else {
                    $widgetWrapper.css({'transform': '', 'top': ''}); // Reset for corner positions
                }
            }
        });

        // Handle window resize
        $(window).on('resize', function() {
            if (isShortcodeEmbed) return;

            const $panel = $('.open-accessibility-widget-panel');
            if ($panel.hasClass('oa-panel-is-active')) {
                const $wrapper = $('.open-accessibility-widget-wrapper');
                const isMobile = window.innerWidth < 768;

                if (isMobile) {
                    if ($wrapper.hasClass('position-left') || $wrapper.hasClass('position-right')) {
                        const wrapperHeight = $wrapper.outerHeight();
                        $wrapper.css('top', `calc(50vh - ${wrapperHeight / 2}px)`);
                    }
                    $wrapper.css('transform', 'none');
                    $panel.css({
                        'position': 'fixed', 'display': 'block', 'top': '0px', 'left': '0px',
                        'width': '100vw', 'height': '100vh', 'max-height': '100vh',
                        'transform': 'none', 'z-index': '1000001'
                    });
                } else { // Became desktop
                    $wrapper.css({'transform': '', 'top': ''});
                    $panel.css({
                        'position': '', 'display': '', 'top': '', 'left': '',
                        'width': '', 'height': '', 'max-height': '', 'transform': '', 'z-index': ''
                    });
                }
            }
        });
    }

    function mergeUniqueElements() {
        const seen = new Set();
        const merged = [];

        Array.from(arguments).forEach((elements) => {
            elements.forEach((element) => {
                if (!seen.has(element)) {
                    seen.add(element);
                    merged.push(element);
                }
            });
        });

        return merged;
    }

    function getTypographyRoots() {
        const roots = [];
        const seen = new Set();

        typographyTargets.contentRoots.forEach((selector) => {
            try {
                document.querySelectorAll(selector).forEach((element) => {
                    if (!seen.has(element)) {
                        seen.add(element);
                        roots.push(element);
                    }
                });
            } catch (error) {
                console.warn('Open Accessibility: invalid typography root selector', selector, error);
            }
        });

        if (!roots.length) {
            roots.push(document.body);
        }

        return roots;
    }

    function isExcludedTypographyElement(element, excludedSelector) {
        if (!(element instanceof Element)) {
            return true;
        }

        if (
            element.closest(
                '.open-accessibility-widget-wrapper, .open-accessibility-reading-guide, .open-accessibility-skip-to-content-link, .open-accessibility-skip-to-content-backdrop'
            )
        ) {
            return true;
        }

        if (!excludedSelector) {
            return false;
        }

        try {
            return element.matches(excludedSelector) || Boolean(element.closest(excludedSelector));
        } catch (error) {
            console.warn('Open Accessibility: invalid typography exclusion selector', excludedSelector, error);
            return false;
        }
    }

    function collectTypographyTargets(selectors) {
        if (!selectors.length) {
            return [];
        }

        const selector = selectors.join(', ');
        const excludedSelector = typographyTargets.excludedSelectors.join(', ');
        const targets = [];
        const seen = new Set();

        getTypographyRoots().forEach((root) => {
            let candidates = [];

            if (root.matches && root.matches(selector)) {
                candidates.push(root);
            }

            candidates = candidates.concat(Array.from(root.querySelectorAll(selector)));

            candidates.forEach((element) => {
                if (seen.has(element) || !document.body.contains(element)) {
                    return;
                }

                if (isExcludedTypographyElement(element, excludedSelector)) {
                    return;
                }

                seen.add(element);
                targets.push(element);
            });
        });

        return targets;
    }

    function getTypographyTargets() {
        const contentTargets = collectTypographyTargets(typographyTargets.textElements);
        const readableTargets = collectTypographyTargets(
            typographyTargets.textElements.concat(typographyTargets.headingElements)
        );

        return {
            contentTargets,
            readableTargets,
            allTargets: mergeUniqueElements(contentTargets, readableTargets)
        };
    }

    function parsePixelValue(value, fallback) {
        if (typeof value !== 'string' || value === '' || value === 'normal') {
            return fallback;
        }

        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function getComputedLineHeightPx(style, fontSizePx) {
        return parsePixelValue(style.lineHeight, fontSizePx * 1.2);
    }

    function getComputedSpacingPx(value) {
        return parsePixelValue(value, 0);
    }

    function roundPx(value) {
        return Math.round(value * 1000) / 1000;
    }

    function captureOriginalInlineStyles(element) {
        if (element.dataset.oaTypographyInlineCaptured === '1') {
            return;
        }

        element.dataset.oaTypographyInlineCaptured = '1';
        element.dataset.oaOriginalFontSize = element.style.fontSize || '';
        element.dataset.oaOriginalLineHeight = element.style.lineHeight || '';
        element.dataset.oaOriginalLetterSpacing = element.style.letterSpacing || '';
        element.dataset.oaOriginalWordSpacing = element.style.wordSpacing || '';
        element.dataset.oaOriginalTextAlign = element.style.textAlign || '';
    }

    function refreshTypographyBaseline(element) {
        captureOriginalInlineStyles(element);

        const style = window.getComputedStyle(element);
        const fontSizePx = parsePixelValue(style.fontSize, 16);

        element.dataset.oaTypographyManaged = '1';
        element.dataset.oaBaseFontSize = String(fontSizePx);
        element.dataset.oaBaseLineHeight = String(getComputedLineHeightPx(style, fontSizePx));
        element.dataset.oaBaseLetterSpacing = String(getComputedSpacingPx(style.letterSpacing));
        element.dataset.oaBaseWordSpacing = String(getComputedSpacingPx(style.wordSpacing));
    }

    function restoreTypographyProperty(element, propertyName, originalValueKey) {
        if (element.dataset[originalValueKey]) {
            element.style[propertyName] = element.dataset[originalValueKey];
        } else {
            element.style[propertyName] = '';
        }
    }

    function clearDynamicTypographyStyles() {
        document.querySelectorAll('[data-oa-typography-managed="1"]').forEach((element) => {
            restoreTypographyProperty(element, 'fontSize', 'oaOriginalFontSize');
            restoreTypographyProperty(element, 'lineHeight', 'oaOriginalLineHeight');
            restoreTypographyProperty(element, 'letterSpacing', 'oaOriginalLetterSpacing');
            restoreTypographyProperty(element, 'wordSpacing', 'oaOriginalWordSpacing');
            restoreTypographyProperty(element, 'textAlign', 'oaOriginalTextAlign');
        });
    }

    function removeLegacyTypographyClasses() {
        $('body').removeClass('open-accessibility-text-align-left open-accessibility-text-align-center open-accessibility-text-align-right');

        for (let i = 1; i <= MAX_TEXT_SIZE; i++) {
            $('body').removeClass(`open-accessibility-text-size-${i}`);
        }

        for (let i = 1; i <= MAX_SPACING_LEVEL; i++) {
            $('body').removeClass(`open-accessibility-letter-spacing-${i}`);
            $('body').removeClass(`open-accessibility-word-spacing-${i}`);
            $('body').removeClass(`open-accessibility-line-height-${i}`);
        }
    }

    function applyDynamicTypographyAdjustments() {
        removeLegacyTypographyClasses();
        clearDynamicTypographyStyles();

        const typographyElements = getTypographyTargets();
        if (!typographyElements.allTargets.length) {
            return;
        }

        typographyElements.allTargets.forEach((element) => {
            refreshTypographyBaseline(element);
        });

        const textSizeMultiplier = TEXT_SIZE_MULTIPLIERS[accessibilityState.textSize] || 1;
        const lineHeightMultiplier = LINE_HEIGHT_MULTIPLIERS[accessibilityState.lineHeightLevel] || 0;
        const letterSpacingStep = LETTER_SPACING_STEPS[accessibilityState.letterSpacingLevel] || 0;
        const wordSpacingStep = WORD_SPACING_STEPS[accessibilityState.wordSpacingLevel] || 0;
        const resizedTargets = new Set(typographyElements.readableTargets);

        typographyElements.readableTargets.forEach((element) => {
            if (accessibilityState.textSize <= 0) {
                return;
            }

            const baseFontSize = parsePixelValue(element.dataset.oaBaseFontSize, 16);
            element.style.fontSize = `${roundPx(baseFontSize * textSizeMultiplier)}px`;
        });

        typographyElements.contentTargets.forEach((element) => {
            const baseFontSize = parsePixelValue(element.dataset.oaBaseFontSize, 16);
            const effectiveFontSize =
                resizedTargets.has(element) && accessibilityState.textSize > 0
                    ? baseFontSize * textSizeMultiplier
                    : baseFontSize;

            if (accessibilityState.lineHeightLevel > 0) {
                const baseLineHeight = parsePixelValue(element.dataset.oaBaseLineHeight, effectiveFontSize * 1.2);
                element.style.lineHeight = `${roundPx(Math.max(baseLineHeight, effectiveFontSize * lineHeightMultiplier))}px`;
            }

            if (accessibilityState.letterSpacingLevel > 0) {
                const baseLetterSpacing = parsePixelValue(element.dataset.oaBaseLetterSpacing, 0);
                element.style.letterSpacing = `${roundPx(baseLetterSpacing + effectiveFontSize * letterSpacingStep)}px`;
            }

            if (accessibilityState.wordSpacingLevel > 0) {
                const baseWordSpacing = parsePixelValue(element.dataset.oaBaseWordSpacing, 0);
                element.style.wordSpacing = `${roundPx(baseWordSpacing + effectiveFontSize * wordSpacingStep)}px`;
            }
        });

        if (accessibilityState.textAlign) {
            typographyElements.readableTargets.forEach((element) => {
                element.style.textAlign = accessibilityState.textAlign;
            });
        }
    }

    // Toggle widget panel
    function toggleAccessibilityPanel() {
        const $panel = $('.open-accessibility-widget-panel');
        const $toggle = $('.open-accessibility-toggle-button');
        const $wrapper = $('.open-accessibility-widget-wrapper');
        const isMobile = window.innerWidth < 768;

        $panel.toggleClass('oa-panel-is-active');
        const isActive = $panel.hasClass('oa-panel-is-active');
        $panel.attr('aria-hidden', isActive ? 'false' : 'true');
        $toggle.attr('aria-expanded', isActive ? 'true' : 'false');

        // Shortcode embed: simple show/hide, no positioning logic
        if (isShortcodeEmbed) {
            return;
        }

        if (isActive) { // Panel is OPENING
            if (isMobile) {
                if ($wrapper.hasClass('position-left') || $wrapper.hasClass('position-right')) {
                    const wrapperHeight = $wrapper.outerHeight();
                    $wrapper.css('top', `calc(50vh - ${wrapperHeight / 2}px)`);
                }
                $wrapper.css('transform', 'none');
                $panel.css({
                    'position': 'fixed', 'display': 'block', 'top': '0px', 'left': '0px',
                    'width': '100vw', 'height': '100vh', 'max-height': '100vh',
                    'transform': 'none', 'z-index': '1000001'
                });
            } else { // Desktop OPENING
                $wrapper.css({'transform': '', 'top': ''});
                $panel.css({
                    'position': '', 'display': '', 'top': '', 'left': '',
                    'width': '', 'height': '', 'max-height': '', 'transform': '', 'z-index': ''
                });
            }
        } else { // Panel is CLOSING (via toggle button click)
            if (isMobile) {
                $panel.css({ // Re-assert full-screen styles for fade-out
                    'position': 'fixed', 'display': 'block', 'top': '0px', 'left': '0px',
                    'width': '100vw', 'height': '100vh', 'max-height': '100vh',
                    'transform': 'none', 'z-index': '1000001' 
                });
                setTimeout(function() {
                    $panel.css({ // Hide and reset panel
                        'display': 'none', 'position': '', 'top': '', 'left': '', 'width': '', 
                        'height': '', 'max-height': '', 'transform': '', 'z-index': ''
                    });
                    // Reset wrapper transform/top AFTER panel is hidden
                    $wrapper.css({'transform': '', 'top': ''}); 
                }, 350); 
            } else { // Desktop CLOSING
                $wrapper.css({'transform': '', 'top': ''}); // Reset wrapper immediately for desktop
                $panel.css({
                    'position': '', 'display': '', 'top': '','left': '',
                    'width': '', 'height': '', 'max-height': '', 'transform': '', 'z-index': ''
                });
            }
        }
    }

    // Close widget panel (called by close button, ESC, click-outside)
    function closeAccessibilityPanel() {
        const $panel = $('.open-accessibility-widget-panel');
        const $toggle = $('.open-accessibility-toggle-button');
        const $wrapper = $('.open-accessibility-widget-wrapper');
        const isMobile = window.innerWidth < 768;

        $panel.removeClass('oa-panel-is-active');
        $panel.attr('aria-hidden', 'true');
        $toggle.attr('aria-expanded', 'false');

        // Shortcode embed: simple hide, no positioning logic
        if (isShortcodeEmbed) {
            return;
        }

        if (isMobile) {
            $panel.css({ // Re-assert full-screen styles for fade-out
                'position': 'fixed', 'display': 'block', 'top': '0px', 'left': '0px',
                'width': '100vw', 'height': '100vh', 'max-height': '100vh',
                'transform': 'none', 'z-index': '1000001' 
            });
            setTimeout(function() {
                $panel.css({ // Hide and reset panel
                    'display': 'none', 'position': '', 'top': '', 'left': '', 'width': '', 
                    'height': '', 'max-height': '', 'transform': '', 'z-index': ''   
                });
                // Reset wrapper transform/top AFTER panel is hidden
                $wrapper.css({'transform': '', 'top': ''}); 
            }, 350); 
        } else { // Desktop
            $wrapper.css({'transform': '', 'top': ''}); // Reset wrapper immediately for desktop
            $panel.css({
                'position': '', 'display': '', 'top': '','left': '',
                'width': '', 'height': '', 'max-height': '', 'transform': '', 'z-index': ''
            });
        }
    }

    // Hide widget (user choice)
    function hideAccessibilityWidget() {
        $('.open-accessibility-toggle-button, .open-accessibility-widget-panel').css('display', 'none');

        // Set a cookie to remember this choice temporarily
        setCookie('open_accessibility_hidden', '1', 1); // Hide for 1 day

        closeAccessibilityPanel();
    }

    // Handle action button clicks
    function handleActionButton(e) {
        e.stopPropagation();

        const $btn = $(this);
        const action = $btn.data('action');
        const value = $btn.data('value');

        switch (action) {
            case 'contrast':
                handleContrast(value);
                break;

            case 'grayscale':
                toggleGrayscale();
                break;

            case 'text-size':
                adjustTextSize(value);
                break;

            case 'set-font':
                setFont(value);
                break;

            case 'links-underline':
                toggleLinksUnderline();
                break;

            case 'hide-images':
                toggleHideImages();
                break;

            case 'reading-guide':
                toggleReadingGuide();
                break;

            case 'focus-outline':
                toggleFocusOutline();
                break;

            case 'line-height':
                adjustLineHeight(value);
                break;

            case 'text-align':
                setTextAlign(value);
                break;

            case 'pause-animations':
                togglePauseAnimations();
                break;

            case 'letter-spacing':
                adjustLetterSpacing(value);
                break;

            case 'word-spacing':
                adjustWordSpacing(value);
                break;
        }

        // Update state
        syncActionButtonStates();
        saveState();
    }

    function setButtonPressed(action, value, pressed) {
        const $button = $(`.open-accessibility-action-button[data-action="${action}"][data-value="${value}"][aria-pressed]`);
        $button.toggleClass('active', pressed);
        $button.attr('aria-pressed', pressed ? 'true' : 'false');
    }

    function syncActionButtonStates() {
        $('.open-accessibility-action-button[aria-pressed]')
            .removeClass('active')
            .attr('aria-pressed', 'false');

        if (accessibilityState.contrast) {
            setButtonPressed('contrast', accessibilityState.contrast, true);
        }

        setButtonPressed('grayscale', 'toggle', accessibilityState.grayscale);
        setButtonPressed('set-font', accessibilityState.selectedFont || 'default', true);
        setButtonPressed('links-underline', 'toggle', accessibilityState.linksUnderline);
        setButtonPressed('hide-images', 'toggle', accessibilityState.hideImages);
        setButtonPressed('reading-guide', 'toggle', accessibilityState.readingGuide);
        setButtonPressed('focus-outline', 'toggle', accessibilityState.focusOutline);

        if (accessibilityState.textAlign) {
            setButtonPressed('text-align', accessibilityState.textAlign, true);
        }

        setButtonPressed('pause-animations', 'toggle', accessibilityState.pauseAnimations);
    }

    // Handle contrast modes
    function handleContrast(mode) {
        // Remove existing contrast classes
        $('body').removeClass('open-accessibility-high-contrast open-accessibility-negative-contrast open-accessibility-light-background open-accessibility-dark-background');
        
        // Clear active state on all contrast buttons
        $('.open-accessibility-action-button[data-action="contrast"]').removeClass('active');

        // If the same mode is clicked again, deactivate it
        if (accessibilityState.contrast === mode) {
            accessibilityState.contrast = '';
            return;
        }

        // Apply new contrast mode
        switch (mode) {
            case 'high':
                $('body').addClass('open-accessibility-high-contrast');
                $('.open-accessibility-action-button[data-action="contrast"][data-value="high"]').addClass('active');
                break;

            case 'negative':
                $('body').addClass('open-accessibility-negative-contrast');
                $('.open-accessibility-action-button[data-action="contrast"][data-value="negative"]').addClass('active');
                break;

            case 'light':
                $('body').addClass('open-accessibility-light-background');
                $('.open-accessibility-action-button[data-action="contrast"][data-value="light"]').addClass('active');
                break;

            case 'dark':
                $('body').addClass('open-accessibility-dark-background');
                $('.open-accessibility-action-button[data-action="contrast"][data-value="dark"]').addClass('active');
                break;
        }

        accessibilityState.contrast = mode;
    }

    // Apply contrast from saved state (without toggle logic)
    function applyContrast(mode) {
        // Remove existing contrast classes
        $('body').removeClass('open-accessibility-high-contrast open-accessibility-negative-contrast open-accessibility-light-background open-accessibility-dark-background');
        
        // Clear active state on all contrast buttons
        $('.open-accessibility-action-button[data-action="contrast"]').removeClass('active');

        // Apply the contrast mode and activate the correct button
        switch (mode) {
            case 'high':
                $('body').addClass('open-accessibility-high-contrast');
                $('.open-accessibility-action-button[data-action="contrast"][data-value="high"]').addClass('active');
                break;

            case 'negative':
                $('body').addClass('open-accessibility-negative-contrast');
                $('.open-accessibility-action-button[data-action="contrast"][data-value="negative"]').addClass('active');
                break;

            case 'light':
                $('body').addClass('open-accessibility-light-background');
                $('.open-accessibility-action-button[data-action="contrast"][data-value="light"]').addClass('active');
                break;

            case 'dark':
                $('body').addClass('open-accessibility-dark-background');
                $('.open-accessibility-action-button[data-action="contrast"][data-value="dark"]').addClass('active');
                break;
        }
    }

    // Toggle grayscale
    function toggleGrayscale() {
        accessibilityState.grayscale = !accessibilityState.grayscale;
        const $button = $('.open-accessibility-toggle-button');
        const $panel = $('.open-accessibility-widget-panel');

        if (accessibilityState.grayscale) {
            // Apply grayscale to the main content, excluding button, panel and panel's children
            $('body *').not($button).not($panel).not($panel.find('*')).css('filter', 'grayscale(100%)');
            // Add a class to the widget button and panel for CSS targeting
            $button.addClass('widget-grayscale');
            $panel.addClass('widget-grayscale');
        } else {
            // Remove grayscale from the main content
            $('body *').not($button).not($panel).not($panel.find('*')).css('filter', '');
            // Remove the class from the widget button and panel
            $button.removeClass('widget-grayscale');
            $panel.removeClass('widget-grayscale');
        }

        // Toggle the button active state
        $('.open-accessibility-action-button[data-action="grayscale"]').toggleClass('active', accessibilityState.grayscale);
    }

    // Adjust text size
    function adjustTextSize(direction) {
        if (direction === 'increase') {
            accessibilityState.textSize = Math.min(accessibilityState.textSize + 1, MAX_TEXT_SIZE);
        } else if (direction === 'decrease') {
            accessibilityState.textSize = Math.max(accessibilityState.textSize - 1, 0);
        }

        applyDynamicTypographyAdjustments();

        // Update button states and indicator
        updateSpacingButtonStates('text-size', accessibilityState.textSize);
        updateIndicator('text-size', accessibilityState.textSize);
    }

    // Set selected font
    function setFont(fontValue) {
        // Remove previous font classes
        $('body').removeClass('open-accessibility-font-atkinson open-accessibility-font-opendyslexic');
        // Remove active class from all font buttons
        $('.open-accessibility-action-button[data-action="set-font"]').removeClass('active');

        // If the same font is clicked again, revert to default
        if (accessibilityState.selectedFont === fontValue) {
            accessibilityState.selectedFont = 'default';
            $('.open-accessibility-action-button[data-action="set-font"][data-value="default"]').addClass('active'); // Activate default button
        } else {
            accessibilityState.selectedFont = fontValue;
            if (fontValue === 'atkinson') {
                $('body').addClass('open-accessibility-font-atkinson');
            } else if (fontValue === 'opendyslexic') {
                $('body').addClass('open-accessibility-font-opendyslexic');
            }
             // Add active class to the clicked button
            $(`.open-accessibility-action-button[data-action="set-font"][data-value="${fontValue}"]`).addClass('active');
        }

        // Ensure default button is active if state is default
        if (accessibilityState.selectedFont === 'default') {
             $('.open-accessibility-action-button[data-action="set-font"][data-value="default"]').addClass('active');
        }

        applyDynamicTypographyAdjustments();
    }

    // Apply font from saved state (without toggle logic)
    function applyFont(fontValue) {
        // Remove previous font classes
        $('body').removeClass('open-accessibility-font-atkinson open-accessibility-font-opendyslexic');
        // Remove active class from all font buttons
        $('.open-accessibility-action-button[data-action="set-font"]').removeClass('active');

        // Apply the font class and activate the correct button
        if (fontValue === 'atkinson') {
            $('body').addClass('open-accessibility-font-atkinson');
            $('.open-accessibility-action-button[data-action="set-font"][data-value="atkinson"]').addClass('active');
        } else if (fontValue === 'opendyslexic') {
            $('body').addClass('open-accessibility-font-opendyslexic');
            $('.open-accessibility-action-button[data-action="set-font"][data-value="opendyslexic"]').addClass('active');
        } else {
            // Default font - just activate the default button
            $('.open-accessibility-action-button[data-action="set-font"][data-value="default"]').addClass('active');
        }
    }

    // Toggle links underline
    function toggleLinksUnderline() {
        accessibilityState.linksUnderline = !accessibilityState.linksUnderline;
        $('body').toggleClass('open-accessibility-links-underline', accessibilityState.linksUnderline);
        $('.open-accessibility-action-button[data-action="links-underline"]').toggleClass('active', accessibilityState.linksUnderline);
    }

    // Toggle hide images
    function toggleHideImages() {
        accessibilityState.hideImages = !accessibilityState.hideImages;
        $('body').toggleClass('open-accessibility-hide-images', accessibilityState.hideImages);
        $('.open-accessibility-action-button[data-action="hide-images"]').toggleClass('active', accessibilityState.hideImages);
    }

    // Toggle reading guide
    function toggleReadingGuide() {
        accessibilityState.readingGuide = !accessibilityState.readingGuide;
        $('.open-accessibility-action-button[data-action="reading-guide"]').toggleClass('active', accessibilityState.readingGuide);
        if (accessibilityState.readingGuide) {
            $('.open-accessibility-reading-guide').show();
        } else {
            $('.open-accessibility-reading-guide').hide();
        }
        saveState();
    }

    // Handle reading guide mouse movement
    function handleReadingGuide(e) {
        if (accessibilityState.readingGuide) {
            $('.open-accessibility-reading-guide').css('top', e.clientY - 15);
        }
    }

    // Toggle focus outline
    function toggleFocusOutline() {
        accessibilityState.focusOutline = !accessibilityState.focusOutline;
        $('body').toggleClass('open-accessibility-focus-outline', accessibilityState.focusOutline);
        $('.open-accessibility-action-button[data-action="focus-outline"]').toggleClass('active', accessibilityState.focusOutline);
    }


    // Set text align
    function setTextAlign(align) {
        // Clear active state on all text align buttons
        $('.open-accessibility-action-button[data-action="text-align"]').removeClass('active');

        // If the same alignment is clicked again, deactivate it
        if (accessibilityState.textAlign === align) {
            accessibilityState.textAlign = '';
            applyDynamicTypographyAdjustments();
            return;
        }

        accessibilityState.textAlign = align;
        $(`.open-accessibility-action-button[data-action="text-align"][data-value="${align}"]`).addClass('active');
        applyDynamicTypographyAdjustments();
    }

    // Toggle pause animations
    function togglePauseAnimations() {
        accessibilityState.pauseAnimations = !accessibilityState.pauseAnimations;
        $('body').toggleClass('open-accessibility-pause-animations', accessibilityState.pauseAnimations);
        $('.open-accessibility-action-button[data-action="pause-animations"]').toggleClass('active', accessibilityState.pauseAnimations);
    }

    // Adjust Letter Spacing Level
    function adjustLetterSpacing(direction) {
        const currentLevel = accessibilityState.letterSpacingLevel;
        let newLevel = currentLevel;

        if (direction === 'increase') {
            newLevel = Math.min(currentLevel + 1, MAX_SPACING_LEVEL);
        } else if (direction === 'decrease') {
            newLevel = Math.max(currentLevel - 1, 0);
        }

        if (newLevel !== currentLevel) {
            accessibilityState.letterSpacingLevel = newLevel;
            applyDynamicTypographyAdjustments();
            updateSpacingButtonStates('letter-spacing', newLevel);
            updateIndicator('letter-spacing', newLevel);
        }
    }

    // Adjust Word Spacing Level
    function adjustWordSpacing(direction) {
        const currentLevel = accessibilityState.wordSpacingLevel;
        let newLevel = currentLevel;

        if (direction === 'increase') {
            newLevel = Math.min(currentLevel + 1, MAX_SPACING_LEVEL);
        } else if (direction === 'decrease') {
            newLevel = Math.max(currentLevel - 1, 0);
        }

        if (newLevel !== currentLevel) {
            accessibilityState.wordSpacingLevel = newLevel;
            applyDynamicTypographyAdjustments();
            updateSpacingButtonStates('word-spacing', newLevel);
            updateIndicator('word-spacing', newLevel);
        }
    }

    // Adjust Line Height Level
    function adjustLineHeight(direction) {
        const currentLevel = accessibilityState.lineHeightLevel;
        let newLevel = currentLevel;

        if (direction === 'increase') {
            newLevel = Math.min(currentLevel + 1, MAX_SPACING_LEVEL);
        } else if (direction === 'decrease') {
            newLevel = Math.max(currentLevel - 1, 0);
        }

        if (newLevel !== currentLevel) {
            accessibilityState.lineHeightLevel = newLevel;
            applyDynamicTypographyAdjustments();
            updateSpacingButtonStates('line-height', newLevel);
            updateIndicator('line-height', newLevel);
        }
    }

    // Helper to update Increase/Decrease button states
    function updateSpacingButtonStates(action, level) {
        const maxLevel = action === 'text-size' ? MAX_TEXT_SIZE : MAX_SPACING_LEVEL;
        $(`.open-accessibility-action-button[data-action="${action}"][data-value="decrease"]`).prop('disabled', level <= 0);
        $(`.open-accessibility-action-button[data-action="${action}"][data-value="increase"]`).prop('disabled', level >= maxLevel);
    }

    // Update visual indicator (dots/dashes) for incremental controls
    function updateIndicator(action, currentLevel) {
        const $indicator = $(`.open-accessibility-indicator[data-action="${action}"]`);
        if ($indicator.length === 0) {
            return;
        }

        const maxLevel = parseInt($indicator.data('max'), 10);
        const totalDots = maxLevel + 1; // 0 to maxLevel inclusive
        
        // Clear existing dots
        $indicator.empty();
        
        // Create dots for each level
        for (let i = 0; i <= maxLevel; i++) {
            const $dot = $('<span></span>')
                .addClass('open-accessibility-indicator-dot')
                .addClass(i <= currentLevel ? 'active' : '');
            $indicator.append($dot);
        }
        
        // Update aria-label
        $indicator.attr('aria-label', `Level ${currentLevel} of ${maxLevel}`);
        $indicator.append($('<span></span>')
            .addClass('screen-reader-text')
            .text(`Level ${currentLevel} of ${maxLevel}`));
    }

    // Reset all settings to default
    $('.open-accessibility-reset-button').on('click', function() {
        // Remove all accessibility classes
        $('body').removeClass('open-accessibility-high-contrast open-accessibility-negative-contrast open-accessibility-light-background open-accessibility-dark-background');
        $('body').removeClass('open-accessibility-grayscale open-accessibility-links-underline');
        $('body').removeClass('open-accessibility-hide-images open-accessibility-reading-guide-active open-accessibility-focus-outline');
        $('body').removeClass('open-accessibility-pause-animations');
        $('body').removeClass('open-accessibility-font-atkinson open-accessibility-font-opendyslexic');
        removeLegacyTypographyClasses();
        clearDynamicTypographyStyles();

        // Reset all buttons active/disabled state
        $('.open-accessibility-action-button').removeClass('active').prop('disabled', false);

        // Reset state
        accessibilityState = {
            active: true,
            contrast: '',
            grayscale: false,
            textSize: 0,
            selectedFont: 'default',
            linksUnderline: false,
            hideImages: false,
            readingGuide: false,
            focusOutline: false,
            lineHeightLevel: 0,
            textAlign: '',
            pauseAnimations: false,
            letterSpacingLevel: 0,
            wordSpacingLevel: 0
        };

        // Update all indicators
        updateIndicator('text-size', 0);
        updateIndicator('letter-spacing', 0);
        updateIndicator('word-spacing', 0);
        updateIndicator('line-height', 0);

        // Explicitly hide the reading guide on reset
        $('.open-accessibility-reading-guide').hide(); 

        // Clear the grayscale filter from all elements
        $('body *').css('filter', '');
        $('.open-accessibility-toggle-button').removeClass('widget-grayscale');
        $('.open-accessibility-widget-panel').removeClass('widget-grayscale');

        applyDynamicTypographyAdjustments();
        syncActionButtonStates();

        // Save reset state
        saveState();
    });

    // Save state to local storage
    function saveState() {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(storageKey, JSON.stringify(accessibilityState));
        }
    }

    // Load state from local storage
    function loadState() {
        if (typeof localStorage !== 'undefined') {
            let savedState = localStorage.getItem(storageKey);
            
            // Migration: Look for old page-specific keys and consolidate them
            if (!savedState) {
                // Check for any page-specific keys that might exist
                for (let i = 0; i < localStorage.length; i++) {
                    const key = localStorage.key(i);
                    if (key && key.endsWith('_open_accessibility_settings')) {
                        const pageSpecificState = localStorage.getItem(key);
                        if (pageSpecificState) {
                            try {
                                const parsedState = JSON.parse(pageSpecificState);
                                // If this state shows accessibility was active, use it as our base
                                if (parsedState.active) {
                                    savedState = pageSpecificState;
                                    // Save it to the new unified key
                                    localStorage.setItem(storageKey, savedState);
                                    console.log('Open Accessibility: Migrated settings from page-specific storage');
                                }
                                // Clean up the old page-specific key
                                localStorage.removeItem(key);
                            } catch (e) {
                                console.error('Error parsing page-specific accessibility state', e);
                                localStorage.removeItem(key); // Clean up corrupted data
                            }
                        }
                    }
                }
            }
            
            if (savedState) {
                try {
                    const parsedState = JSON.parse(savedState);
                    // Migration: Convert old lineHeight boolean to lineHeightLevel
                    if (parsedState.hasOwnProperty('lineHeight') && typeof parsedState.lineHeight === 'boolean') {
                        parsedState.lineHeightLevel = parsedState.lineHeight ? 2 : 0; // Old toggle was equivalent to level 2
                        delete parsedState.lineHeight;
                    }
                    // Ensure lineHeightLevel exists
                    if (!parsedState.hasOwnProperty('lineHeightLevel')) {
                        parsedState.lineHeightLevel = 0;
                    }
                    accessibilityState = parsedState;
                } catch (e) {
                    console.error('Error parsing saved accessibility state', e);
                }
            }
        }
    }

    // Apply current state to the UI
    function applyState() {
        // Apply contrast (directly without toggle logic)
        if (accessibilityState.contrast) {
            applyContrast(accessibilityState.contrast);
        }

        // Apply grayscale
        if (accessibilityState.grayscale) {
            const $button = $('.open-accessibility-toggle-button');
            const $panel = $('.open-accessibility-widget-panel');
            // Re-apply the styles and classes managed by toggleGrayscale
            $('body *').not($button).not($panel).not($panel.find('*')).css('filter', 'grayscale(100%)');
            $button.addClass('widget-grayscale');
            $panel.addClass('widget-grayscale');
            $('.open-accessibility-action-button[data-action="grayscale"]').addClass('active');
        } else {
            const $button = $('.open-accessibility-toggle-button');
            const $panel = $('.open-accessibility-widget-panel');
            // Ensure styles/classes are removed if state is false
            $('body *').not($button).not($panel).not($panel.find('*')).css('filter', '');
            $button.removeClass('widget-grayscale');
            $panel.removeClass('widget-grayscale');
            $('.open-accessibility-action-button[data-action="grayscale"]').removeClass('active');
        }

        // Apply selected font (directly without toggle logic)
        applyFont(accessibilityState.selectedFont || 'default');

        // Apply links underline
        if (accessibilityState.linksUnderline) {
            $('body').addClass('open-accessibility-links-underline');
            $('.open-accessibility-action-button[data-action="links-underline"]').addClass('active');
        }

        // Apply hide images
        if (accessibilityState.hideImages) {
            $('body').addClass('open-accessibility-hide-images');
            $('.open-accessibility-action-button[data-action="hide-images"]').addClass('active');
        }

        // Apply reading guide
        if (accessibilityState.readingGuide) {
            const initialGuideState = accessibilityState.readingGuide;
            accessibilityState.readingGuide = !initialGuideState;
            toggleReadingGuide();
        }

        // Apply focus outline
        if (accessibilityState.focusOutline) {
            $('body').addClass('open-accessibility-focus-outline');
            $('.open-accessibility-action-button[data-action="focus-outline"]').addClass('active');
        }

        // Apply pause animations
        if (accessibilityState.pauseAnimations) {
            $('body').addClass('open-accessibility-pause-animations');
            $('.open-accessibility-action-button[data-action="pause-animations"]').addClass('active');
        }

        applyDynamicTypographyAdjustments();

        // Update button states and indicators for adaptive typography controls
        updateSpacingButtonStates('text-size', accessibilityState.textSize);
        updateIndicator('text-size', accessibilityState.textSize);
        updateSpacingButtonStates('line-height', accessibilityState.lineHeightLevel);
        updateIndicator('line-height', accessibilityState.lineHeightLevel);
        updateSpacingButtonStates('letter-spacing', accessibilityState.letterSpacingLevel);
        updateIndicator('letter-spacing', accessibilityState.letterSpacingLevel);
        updateSpacingButtonStates('word-spacing', accessibilityState.wordSpacingLevel);
        updateIndicator('word-spacing', accessibilityState.wordSpacingLevel);

        $('.open-accessibility-action-button[data-action="text-align"]').removeClass('active');
        if (accessibilityState.textAlign) {
            $(`.open-accessibility-action-button[data-action="text-align"][data-value="${accessibilityState.textAlign}"]`).addClass('active');
        }

        syncActionButtonStates();
    }

    // Helper function to set cookies
    function setCookie(name, value, days) {
        let expires = '';
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + (value || '') + expires + '; path=/; SameSite=Lax';
    }

    // Helper function to get cookies
    function getCookie(name) {
        const nameEQ = name + '=';
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // Check if widget should be hidden based on user preference
    function checkWidgetVisibility() {
        const hidden = getCookie('open_accessibility_hidden');
        if (hidden === '1') {
            $('.open-accessibility-toggle-button, .open-accessibility-widget-panel').css('display', 'none');
        } else {
            $('.open-accessibility-toggle-button').css('display', '');
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initAccessibility();
        checkWidgetVisibility();
    });

})(jQuery);
