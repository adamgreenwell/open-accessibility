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
    const DEFAULT_TARGET_CONFIG = {
        roots: [
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
        groups: {
            readable_text: [
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
            headings: [
                'h1',
                'h2',
                'h3',
                'h4',
                'h5',
                'h6'
            ],
            links: [
                'a[href]'
            ],
            media: [
                'img',
                'picture',
                'video',
                'audio',
                'iframe',
                'embed',
                'object',
                'svg',
                'canvas'
            ],
            interactive: [
                'a[href]',
                'button',
                'input',
                'select',
                'textarea',
                'summary',
                '[tabindex]:not([tabindex="-1"])'
            ]
        },
        layout_containers: [
            '[data-oa-relax-layout]'
        ],
        excluded: [
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

    let targetResolver = null;

    function isDebugStorageEnabled() {
        try {
            return typeof localStorage !== 'undefined' &&
                localStorage.getItem('openAccessibilityDebug') === '1';
        } catch (error) {
            return false;
        }
    }

    const debugEnabled = Boolean(
        typeof open_accessibility_data !== 'undefined' &&
        open_accessibility_data &&
        open_accessibility_data.options &&
        open_accessibility_data.options.debug
    ) || isDebugStorageEnabled();

    function normalizeSelectorList(value, fallback) {
        const fallbackSelectors = Array.isArray(fallback) ? fallback : [];

        if (!Array.isArray(value)) {
            return fallbackSelectors.slice();
        }

        const selectors = value
            .filter((selector) => typeof selector === 'string' && selector.trim().length > 0)
            .map((selector) => selector.trim());

        return selectors.length ? Array.from(new Set(selectors)) : fallbackSelectors.slice();
    }

    function normalizeTargetConfig(config) {
        const source = config && typeof config === 'object' ? config : {};
        const sourceGroups = source.groups && typeof source.groups === 'object' ? source.groups : {};
        const normalizedGroups = {};

        Object.keys(DEFAULT_TARGET_CONFIG.groups).forEach((groupName) => {
            normalizedGroups[groupName] = normalizeSelectorList(
                sourceGroups[groupName],
                DEFAULT_TARGET_CONFIG.groups[groupName]
            );
        });

        Object.keys(sourceGroups).forEach((groupName) => {
            if (!Object.prototype.hasOwnProperty.call(normalizedGroups, groupName)) {
                normalizedGroups[groupName] = normalizeSelectorList(sourceGroups[groupName], []);
            }
        });

        return {
            roots: normalizeSelectorList(source.roots, DEFAULT_TARGET_CONFIG.roots),
            groups: normalizedGroups,
            layoutContainers: normalizeSelectorList(source.layout_containers, DEFAULT_TARGET_CONFIG.layout_containers),
            excluded: normalizeSelectorList(source.excluded, DEFAULT_TARGET_CONFIG.excluded)
        };
    }

    const targetConfig = normalizeTargetConfig(
        typeof open_accessibility_data !== 'undefined' &&
        open_accessibility_data &&
        open_accessibility_data.options &&
        open_accessibility_data.options.target_config
            ? open_accessibility_data.options.target_config
            : {}
    );

    function exposeOpenAccessibilityApi() {
        const api = window.OpenAccessibility || {};

        api.refresh = function() {
            targetResolver.refresh();
            applyState();
        };
        api.getState = function() {
            return $.extend(true, {}, accessibilityState);
        };
        api.setState = function(partialState) {
            if (!partialState || typeof partialState !== 'object') {
                return;
            }

            accessibilityState = $.extend({}, accessibilityState, partialState);
            saveState();
            applyState();
        };
        api.getTargets = function(groupName) {
            return targetResolver.getTargets(groupName);
        };
        api.debug = function() {
            return {
                state: $.extend(true, {}, accessibilityState),
                targets: targetResolver.getSummary()
            };
        };

        window.OpenAccessibility = api;
    }

    function dispatchReadyEvent() {
        dispatchOpenAccessibilityEvent('openAccessibility:ready', window.OpenAccessibility.debug());
    }

    // Initialize
    function initAccessibility() {
        // Load saved state
        loadState();

        // Detect if widget is embedded via shortcode (inline positioning)
        isShortcodeEmbed = $('.open-accessibility-widget-wrapper').closest('.open-accessibility-shortcode').length > 0;

        targetResolver = createTargetResolver(targetConfig);
        exposeOpenAccessibilityApi();

        // Create reading guide element
        if ($('.open-accessibility-reading-guide').length === 0) {
            $('body').append('<div class="open-accessibility-reading-guide"></div>');
        }

        // Apply saved state
        applyState();
        dispatchReadyEvent();

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

    function logDebug() {
        if (!debugEnabled || !window.console || !window.console.log) {
            return;
        }

        window.console.log.apply(window.console, arguments);
    }

    function dispatchOpenAccessibilityEvent(name, detail) {
        document.dispatchEvent(new CustomEvent(name, {
            detail: detail || {}
        }));
    }

    function createTargetResolver(config) {
        const invalidSelectors = [];
        let roots = [];
        let targets = {};
        let layoutContainers = [];
        let excludedCount = 0;

        function queryAll(selector, root, context) {
            if (!selector) {
                return [];
            }

            try {
                return Array.from((root || document).querySelectorAll(selector));
            } catch (error) {
                invalidSelectors.push({ selector, context, message: error.message });
                return [];
            }
        }

        function queryAllSelectors(selectors, root, context) {
            const collected = [];

            selectors.forEach((selector) => {
                collected.push.apply(collected, queryAll(selector, root, context));
            });

            return collected;
        }

        function matches(element, selector, context) {
            if (!selector || !(element instanceof Element)) {
                return false;
            }

            try {
                return element.matches(selector);
            } catch (error) {
                invalidSelectors.push({ selector, context, message: error.message });
                return false;
            }
        }

        function matchesAny(element, selectors, context) {
            return selectors.some((selector) => matches(element, selector, context));
        }

        function closest(element, selector, context) {
            if (!selector || !(element instanceof Element)) {
                return null;
            }

            try {
                return element.closest(selector);
            } catch (error) {
                invalidSelectors.push({ selector, context, message: error.message });
                return null;
            }
        }

        function closestAny(element, selectors, context) {
            let match = null;

            selectors.some((selector) => {
                match = closest(element, selector, context);
                return Boolean(match);
            });

            return match;
        }

        function isExcluded(element, options) {
            const settings = options || {};

            if (!(element instanceof Element)) {
                return true;
            }

            if (!document.body.contains(element)) {
                return true;
            }

            if (closest(element, '.open-accessibility-widget-wrapper, .open-accessibility-reading-guide, .open-accessibility-skip-to-content-link, .open-accessibility-skip-to-content-backdrop', 'built-in exclusions')) {
                return true;
            }

            if (matchesAny(element, config.excluded, 'excluded') || closestAny(element, config.excluded, 'excluded')) {
                excludedCount++;
                return true;
            }

            if (settings.preserveLayout && closest(element, '[data-oa-preserve-layout]', 'preserve layout')) {
                excludedCount++;
                return true;
            }

            return false;
        }

        function uniqueElements(elements, options) {
            const seen = new Set();
            const unique = [];

            elements.forEach((element) => {
                if (seen.has(element) || isExcluded(element, options)) {
                    return;
                }

                seen.add(element);
                unique.push(element);
            });

            return unique;
        }

        function collectRoots() {
            const configuredRoots = queryAllSelectors(config.roots, document, 'roots')
                .concat(queryAll('[data-oa-root]', document, 'data roots'));

            roots = uniqueElements(configuredRoots);

            if (!roots.length) {
                roots = [document.body];
            }
        }

        function collectGroup(groupName) {
            const selectors = config.groups[groupName] || [];
            const dataSelector = `[data-oa-target~="${groupName}"]`;
            const collected = [];

            roots.forEach((root) => {
                if (matchesAny(root, selectors, groupName)) {
                    collected.push(root);
                }

                collected.push.apply(collected, queryAllSelectors(selectors, root, groupName));
                collected.push.apply(collected, queryAll(dataSelector, root, `${groupName} data target`));
            });

            targets[groupName] = uniqueElements(collected);
        }

        function collectLayoutContainers() {
            const selectors = config.layoutContainers.concat(['[data-oa-relax-layout]']);
            const collected = [];

            roots.forEach((root) => {
                if (matchesAny(root, selectors, 'layout containers')) {
                    collected.push(root);
                }

                collected.push.apply(collected, queryAllSelectors(selectors, root, 'layout containers'));
            });

            layoutContainers = uniqueElements(collected, { preserveLayout: true });
        }

        function refresh() {
            invalidSelectors.length = 0;
            excludedCount = 0;
            targets = {};

            collectRoots();
            Object.keys(config.groups).forEach(collectGroup);
            collectLayoutContainers();

            dispatchOpenAccessibilityEvent('openAccessibility:targetsRefreshed', getSummary());
            logDebug('Open Accessibility targets', getSummary());
        }

        function getTargets(groupName) {
            if (groupName === 'layout_containers') {
                return layoutContainers.slice();
            }

            return targets[groupName] ? targets[groupName].slice() : [];
        }

        function getSummary() {
            const groupCounts = {};

            Object.keys(targets).forEach((groupName) => {
                groupCounts[groupName] = targets[groupName].length;
            });

            return {
                roots: roots.length,
                groups: groupCounts,
                layoutContainers: layoutContainers.length,
                invalidSelectors: invalidSelectors.slice(),
                excludedCount
            };
        }

        refresh();

        return {
            refresh,
            getTargets,
            getSummary
        };
    }

    function getTypographyTargets() {
        const contentTargets = targetResolver ? targetResolver.getTargets('readable_text') : [];
        const headingTargets = targetResolver ? targetResolver.getTargets('headings') : [];
        const readableTargets = mergeUniqueElements(contentTargets, headingTargets);

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

    function captureOriginalStyle(element, datasetKey, propertyName) {
        if (element.dataset[`${datasetKey}Captured`] === '1') {
            return;
        }

        element.dataset[`${datasetKey}Captured`] = '1';
        element.dataset[datasetKey] = element.style[propertyName] || '';
    }

    function restoreOriginalStyle(element, datasetKey, propertyName) {
        if (element.dataset[datasetKey]) {
            element.style[propertyName] = element.dataset[datasetKey];
        } else {
            element.style[propertyName] = '';
        }
    }

    function clearManagedStyles(selector, restoreCallback) {
        document.querySelectorAll(selector).forEach((element) => {
            restoreCallback(element);
        });
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

    function hasTextAffectingControls() {
        const selectedFont = accessibilityState.selectedFont || 'default';

        return accessibilityState.textSize > 0 ||
            accessibilityState.lineHeightLevel > 0 ||
            accessibilityState.letterSpacingLevel > 0 ||
            accessibilityState.wordSpacingLevel > 0 ||
            selectedFont !== 'default';
    }

    function captureOriginalLayoutStyles(element) {
        if (element.dataset.oaLayoutReliefCaptured === '1') {
            return;
        }

        element.dataset.oaLayoutReliefCaptured = '1';
        element.dataset.oaOriginalHeight = element.style.height || '';
        element.dataset.oaOriginalMaxHeight = element.style.maxHeight || '';
        element.dataset.oaOriginalOverflow = element.style.overflow || '';
        element.dataset.oaOriginalOverflowX = element.style.overflowX || '';
        element.dataset.oaOriginalOverflowY = element.style.overflowY || '';
        element.dataset.oaOriginalDisplay = element.style.display || '';
        element.dataset.oaOriginalWebkitLineClamp = element.style.webkitLineClamp || '';
        element.dataset.oaOriginalLineClamp = element.style.lineClamp || '';
    }

    function restoreLayoutProperty(element, propertyName, originalValueKey) {
        if (element.dataset[originalValueKey]) {
            element.style[propertyName] = element.dataset[originalValueKey];
        } else {
            element.style[propertyName] = '';
        }
    }

    function restoreLayoutRelief() {
        document.querySelectorAll('[data-oa-layout-relief-managed="1"]').forEach((element) => {
            restoreLayoutProperty(element, 'height', 'oaOriginalHeight');
            restoreLayoutProperty(element, 'maxHeight', 'oaOriginalMaxHeight');
            restoreLayoutProperty(element, 'overflow', 'oaOriginalOverflow');
            restoreLayoutProperty(element, 'overflowX', 'oaOriginalOverflowX');
            restoreLayoutProperty(element, 'overflowY', 'oaOriginalOverflowY');
            restoreLayoutProperty(element, 'display', 'oaOriginalDisplay');
            restoreLayoutProperty(element, 'webkitLineClamp', 'oaOriginalWebkitLineClamp');
            restoreLayoutProperty(element, 'lineClamp', 'oaOriginalLineClamp');
            delete element.dataset.oaLayoutReliefManaged;
            delete element.dataset.oaLayoutReliefCaptured;
            delete element.dataset.oaOriginalHeight;
            delete element.dataset.oaOriginalMaxHeight;
            delete element.dataset.oaOriginalOverflow;
            delete element.dataset.oaOriginalOverflowX;
            delete element.dataset.oaOriginalOverflowY;
            delete element.dataset.oaOriginalDisplay;
            delete element.dataset.oaOriginalWebkitLineClamp;
            delete element.dataset.oaOriginalLineClamp;
        });
    }

    function applyLayoutRelief() {
        targetResolver.getTargets('layout_containers').forEach((element) => {
            const computedStyle = window.getComputedStyle(element);

            captureOriginalLayoutStyles(element);
            element.dataset.oaLayoutReliefManaged = '1';
            element.style.height = 'auto';
            element.style.maxHeight = 'none';
            element.style.overflow = 'visible';
            element.style.overflowX = 'visible';
            element.style.overflowY = 'visible';
            element.style.webkitLineClamp = 'unset';
            element.style.lineClamp = 'unset';

            if (computedStyle.display === '-webkit-box') {
                element.style.display = 'block';
            }
        });
    }

    function syncLayoutRelief() {
        restoreLayoutRelief();

        if (hasTextAffectingControls()) {
            applyLayoutRelief();
        }
    }

    function getReadableFontTargets() {
        if (!targetResolver) {
            return [];
        }

        return mergeUniqueElements(
            targetResolver.getTargets('readable_text'),
            targetResolver.getTargets('headings')
        );
    }

    function getReadableFontFamily(fontValue) {
        if (fontValue === 'atkinson') {
            return "'Atkinson Hyperlegible', sans-serif";
        }

        if (fontValue === 'opendyslexic') {
            return "'OpenDyslexic', sans-serif";
        }

        return '';
    }

    function restoreReadableFontTarget(element) {
        restoreOriginalStyle(element, 'oaReadableFontOriginal', 'fontFamily');
        delete element.dataset.oaReadableFontManaged;
        delete element.dataset.oaReadableFontOriginal;
        delete element.dataset.oaReadableFontOriginalCaptured;
    }

    function applyReadableFontTargets(fontValue) {
        const fontFamily = getReadableFontFamily(fontValue);

        clearManagedStyles('[data-oa-readable-font-managed="1"]', restoreReadableFontTarget);

        if (!fontFamily) {
            return;
        }

        getReadableFontTargets().forEach((element) => {
            captureOriginalStyle(element, 'oaReadableFontOriginal', 'fontFamily');
            element.dataset.oaReadableFontManaged = '1';
            element.style.fontFamily = fontFamily;
        });
    }

    function restoreLinksUnderlineTarget(element) {
        restoreOriginalStyle(element, 'oaLinksUnderlineOriginal', 'textDecoration');
        delete element.dataset.oaLinksUnderlineManaged;
        delete element.dataset.oaLinksUnderlineOriginal;
        delete element.dataset.oaLinksUnderlineOriginalCaptured;
    }

    function applyLinksUnderlineTargets() {
        clearManagedStyles('[data-oa-links-underline-managed="1"]', restoreLinksUnderlineTarget);

        if (!accessibilityState.linksUnderline || !targetResolver) {
            return;
        }

        targetResolver.getTargets('links').forEach((element) => {
            captureOriginalStyle(element, 'oaLinksUnderlineOriginal', 'textDecoration');
            element.dataset.oaLinksUnderlineManaged = '1';
            element.style.textDecoration = 'underline';
        });
    }

    function restoreHideImagesTarget(element) {
        restoreOriginalStyle(element, 'oaHideImagesOriginal', 'visibility');
        delete element.dataset.oaHideImagesManaged;
        delete element.dataset.oaHideImagesOriginal;
        delete element.dataset.oaHideImagesOriginalCaptured;
    }

    function applyHideImagesTargets() {
        clearManagedStyles('[data-oa-hide-images-managed="1"]', restoreHideImagesTarget);

        if (!accessibilityState.hideImages || !targetResolver) {
            return;
        }

        targetResolver.getTargets('media').forEach((element) => {
            if (!['IMG', 'PICTURE', 'SVG'].includes(element.tagName)) {
                return;
            }

            captureOriginalStyle(element, 'oaHideImagesOriginal', 'visibility');
            element.dataset.oaHideImagesManaged = '1';
            element.style.visibility = 'hidden';
        });
    }

    function restoreGrayscaleTarget(element) {
        restoreOriginalStyle(element, 'oaGrayscaleOriginal', 'filter');
        delete element.dataset.oaGrayscaleManaged;
        delete element.dataset.oaGrayscaleOriginal;
        delete element.dataset.oaGrayscaleOriginalCaptured;
    }

    function getGrayscaleTargets() {
        if (!targetResolver) {
            return [];
        }

        return mergeUniqueElements(
            targetResolver.getTargets('readable_text'),
            targetResolver.getTargets('headings'),
            targetResolver.getTargets('links'),
            targetResolver.getTargets('media')
        );
    }

    function applyGrayscaleTargets() {
        const $button = $('.open-accessibility-toggle-button');
        const $panel = $('.open-accessibility-widget-panel');

        clearManagedStyles('[data-oa-grayscale-managed="1"]', restoreGrayscaleTarget);
        $button.toggleClass('widget-grayscale', accessibilityState.grayscale);
        $panel.toggleClass('widget-grayscale', accessibilityState.grayscale);

        if (!accessibilityState.grayscale) {
            return;
        }

        getGrayscaleTargets().forEach((element) => {
            captureOriginalStyle(element, 'oaGrayscaleOriginal', 'filter');
            element.dataset.oaGrayscaleManaged = '1';
            element.style.filter = 'grayscale(100%)';
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
            syncLayoutRelief();
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

        syncLayoutRelief();
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
        applyGrayscaleTargets();
        syncActionButtonStates();
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
        // Remove previous legacy font classes
        $('body').removeClass('open-accessibility-font-atkinson open-accessibility-font-opendyslexic');
        // Remove active class from all font buttons
        $('.open-accessibility-action-button[data-action="set-font"]').removeClass('active');

        // If the same font is clicked again, revert to default
        if (accessibilityState.selectedFont === fontValue) {
            accessibilityState.selectedFont = 'default';
            $('.open-accessibility-action-button[data-action="set-font"][data-value="default"]').addClass('active'); // Activate default button
        } else {
            accessibilityState.selectedFont = fontValue;
             // Add active class to the clicked button
            $(`.open-accessibility-action-button[data-action="set-font"][data-value="${fontValue}"]`).addClass('active');
        }

        // Ensure default button is active if state is default
        if (accessibilityState.selectedFont === 'default') {
             $('.open-accessibility-action-button[data-action="set-font"][data-value="default"]').addClass('active');
        }

        applyDynamicTypographyAdjustments();
        applyReadableFontTargets(accessibilityState.selectedFont || 'default');
    }

    // Apply font from saved state (without toggle logic)
    function applyFont(fontValue) {
        // Remove previous legacy font classes
        $('body').removeClass('open-accessibility-font-atkinson open-accessibility-font-opendyslexic');
        // Remove active class from all font buttons
        $('.open-accessibility-action-button[data-action="set-font"]').removeClass('active');

        // Activate the correct button; targeted inline styles apply the font.
        if (fontValue === 'atkinson') {
            $('.open-accessibility-action-button[data-action="set-font"][data-value="atkinson"]').addClass('active');
        } else if (fontValue === 'opendyslexic') {
            $('.open-accessibility-action-button[data-action="set-font"][data-value="opendyslexic"]').addClass('active');
        } else {
            // Default font - just activate the default button
            $('.open-accessibility-action-button[data-action="set-font"][data-value="default"]').addClass('active');
        }

        applyReadableFontTargets(accessibilityState.selectedFont || 'default');
    }

    // Toggle links underline
    function toggleLinksUnderline() {
        accessibilityState.linksUnderline = !accessibilityState.linksUnderline;
        $('body').removeClass('open-accessibility-links-underline');
        applyLinksUnderlineTargets();
        syncActionButtonStates();
    }

    // Toggle hide images
    function toggleHideImages() {
        accessibilityState.hideImages = !accessibilityState.hideImages;
        $('body').removeClass('open-accessibility-hide-images');
        applyHideImagesTargets();
        syncActionButtonStates();
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
        restoreLayoutRelief();

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

        applyDynamicTypographyAdjustments();
        applyReadableFontTargets('default');
        applyGrayscaleTargets();
        applyLinksUnderlineTargets();
        applyHideImagesTargets();
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
        applyContrast(accessibilityState.contrast || '');

        // Apply grayscale
        applyGrayscaleTargets();

        // Apply selected font (directly without toggle logic)
        applyFont(accessibilityState.selectedFont || 'default');

        // Apply links underline
        $('body').removeClass('open-accessibility-links-underline');
        applyLinksUnderlineTargets();

        // Apply hide images
        $('body').removeClass('open-accessibility-hide-images');
        applyHideImagesTargets();

        // Apply reading guide
        $('.open-accessibility-reading-guide').toggle(accessibilityState.readingGuide);
        $('.open-accessibility-action-button[data-action="reading-guide"]').toggleClass('active', accessibilityState.readingGuide);

        // Apply focus outline
        $('body').toggleClass('open-accessibility-focus-outline', accessibilityState.focusOutline);
        $('.open-accessibility-action-button[data-action="focus-outline"]').toggleClass('active', accessibilityState.focusOutline);

        // Apply pause animations
        $('body').toggleClass('open-accessibility-pause-animations', accessibilityState.pauseAnimations);
        $('.open-accessibility-action-button[data-action="pause-animations"]').toggleClass('active', accessibilityState.pauseAnimations);

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
