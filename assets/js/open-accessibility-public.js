/**
 * Frontend JavaScript for the Open Accessibility plugin
 *
 * Handles the accessibility widget functionality
 */

(function($) {
    'use strict';

    // Store state in local storage
    const storageKey = 'open_a11y_settings';
    let a11yState = {
        active: false,
        contrast: '',
        grayscale: false,
        textSize: 0,
        readableFont: false,
        linksUnderline: false,
        hideImages: false,
        readingGuide: false,
        focusOutline: false,
        lineHeight: false,
        textAlign: '',
        pauseAnimations: false
    };

    // Initialize
    function initAccessibility() {
        // Load saved state
        loadState();

        // Create reading guide element
        if ($('.open-a11y-reading-guide').length === 0) {
            $('body').append('<div class="open-a11y-reading-guide"></div>');
        }

        // Apply saved state
        applyState();

        // Button click handler
        $('.open-a11y-toggle-button').on('click', toggleAccessibilityPanel);

        // Close button
        $('.open-a11y-close').on('click', closeAccessibilityPanel);

        // Hide widget button
        $('.open-a11y-hide-widget').on('click', hideAccessibilityWidget);

        // Action buttons
        $('.open-a11y-action-button').on('click', function(e) {
            handleActionButton.call(this, e);
        });

        // Reading guide mouse movement
        $(document).on('mousemove', handleReadingGuide);

        // Close panel when clicking outside
        $(document).on('click', function(e) {
            if (
                $('.open-a11y-widget-wrapper').hasClass('active') &&
                !$(e.target).closest('.open-a11y-widget-wrapper').length &&
                !$(e.target).is('.open-a11y-action-button')
            ) {
                closeAccessibilityPanel();
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // ESC key closes the panel
            if (e.key === 'Escape' && $('.open-a11y-widget-wrapper').hasClass('active')) {
                closeAccessibilityPanel();
            }
        });
    }

    // Toggle widget panel
    function toggleAccessibilityPanel() {
        $('.open-a11y-widget-wrapper').toggleClass('active');
        const isActive = $('.open-a11y-widget-wrapper').hasClass('active');
        $('.open-a11y-widget-panel').attr('aria-hidden', !isActive);
    }

    // Close widget panel
    function closeAccessibilityPanel() {
        $('.open-a11y-widget-wrapper').removeClass('active');
        $('.open-a11y-widget-panel').attr('aria-hidden', true);
    }

    // Hide widget (user choice)
    function hideAccessibilityWidget() {
        $('.open-a11y-widget-wrapper').css('display', 'none');

        // Set a cookie to remember this choice temporarily
        setCookie('open_a11y_hidden', '1', 1); // Hide for 1 day

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

            case 'readable-font':
                toggleReadableFont();
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
                toggleLineHeight();
                break;

            case 'text-align':
                setTextAlign(value);
                break;

            case 'pause-animations':
                togglePauseAnimations();
                break;
        }

        // Update state
        saveState();
    }

    // Handle contrast modes
    function handleContrast(mode) {
        // Remove existing contrast classes
        $('body').removeClass('open-a11y-high-contrast open-a11y-negative-contrast open-a11y-light-background open-a11y-dark-background');

        // Clear active state on all contrast buttons
        $('.open-a11y-action-button[data-action="contrast"]').removeClass('active');

        // If the same mode is clicked again, deactivate it
        if (a11yState.contrast === mode) {
            a11yState.contrast = '';
            return;
        }

        // Apply new contrast mode
        switch (mode) {
            case 'high':
                $('body').addClass('open-a11y-high-contrast');
                $('.open-a11y-action-button[data-action="contrast"][data-value="high"]').addClass('active');
                break;

            case 'negative':
                $('body').addClass('open-a11y-negative-contrast');
                $('.open-a11y-action-button[data-action="contrast"][data-value="negative"]').addClass('active');
                break;

            case 'light':
                $('body').addClass('open-a11y-light-background');
                $('.open-a11y-action-button[data-action="contrast"][data-value="light"]').addClass('active');
                break;

            case 'dark':
                $('body').addClass('open-a11y-dark-background');
                $('.open-a11y-action-button[data-action="contrast"][data-value="dark"]').addClass('active');
                break;
        }

        a11yState.contrast = mode;
    }

    // Toggle grayscale
    function toggleGrayscale() {
        a11yState.grayscale = !a11yState.grayscale;
        $('body').toggleClass('open-a11y-grayscale', a11yState.grayscale);
        $('.open-a11y-action-button[data-action="grayscale"]').toggleClass('active', a11yState.grayscale);
    }

    // Adjust text size
    function adjustTextSize(direction) {
        // Remove existing text size classes
        for (let i = 1; i <= 5; i++) {
            $('body').removeClass(`open-a11y-text-size-${i}`);
        }

        if (direction === 'increase') {
            a11yState.textSize = Math.min(a11yState.textSize + 1, 5);
        } else if (direction === 'decrease') {
            a11yState.textSize = Math.max(a11yState.textSize - 1, 0);
        }

        if (a11yState.textSize > 0) {
            $('body').addClass(`open-a11y-text-size-${a11yState.textSize}`);
        }
    }

    // Toggle readable font
    function toggleReadableFont() {
        a11yState.readableFont = !a11yState.readableFont;
        $('body').toggleClass('open-a11y-readable-font', a11yState.readableFont);
        $('.open-a11y-action-button[data-action="readable-font"]').toggleClass('active', a11yState.readableFont);
    }

    // Toggle links underline
    function toggleLinksUnderline() {
        a11yState.linksUnderline = !a11yState.linksUnderline;
        $('body').toggleClass('open-a11y-links-underline', a11yState.linksUnderline);
        $('.open-a11y-action-button[data-action="links-underline"]').toggleClass('active', a11yState.linksUnderline);
    }

    // Toggle hide images
    function toggleHideImages() {
        a11yState.hideImages = !a11yState.hideImages;
        $('body').toggleClass('open-a11y-hide-images', a11yState.hideImages);
        $('.open-a11y-action-button[data-action="hide-images"]').toggleClass('active', a11yState.hideImages);
    }

    // Toggle reading guide
    function toggleReadingGuide() {
        a11yState.readingGuide = !a11yState.readingGuide;
        $('body').toggleClass('open-a11y-reading-guide-active', a11yState.readingGuide);
        $('.open-a11y-action-button[data-action="reading-guide"]').toggleClass('active', a11yState.readingGuide);
    }

    // Handle reading guide mouse movement
    function handleReadingGuide(e) {
        if (a11yState.readingGuide) {
            $('.open-a11y-reading-guide').css('top', e.clientY - 15);
        }
    }

    // Toggle focus outline
    function toggleFocusOutline() {
        a11yState.focusOutline = !a11yState.focusOutline;
        $('body').toggleClass('open-a11y-focus-outline', a11yState.focusOutline);
        $('.open-a11y-action-button[data-action="focus-outline"]').toggleClass('active', a11yState.focusOutline);
    }

    // Toggle line height
    function toggleLineHeight() {
        a11yState.lineHeight = !a11yState.lineHeight;
        $('body').toggleClass('open-a11y-big-line-height', a11yState.lineHeight);
        $('.open-a11y-action-button[data-action="line-height"]').toggleClass('active', a11yState.lineHeight);
    }

    // Set text align
    function setTextAlign(align) {
        // Remove existing text align classes
        $('body').removeClass('open-a11y-text-align-left open-a11y-text-align-center open-a11y-text-align-right');

        // Clear active state on all text align buttons
        $('.open-a11y-action-button[data-action="text-align"]').removeClass('active');

        // If the same alignment is clicked again, deactivate it
        if (a11yState.textAlign === align) {
            a11yState.textAlign = '';
            return;
        }

        // Apply new text alignment
        $('body').addClass(`open-a11y-text-align-${align}`);
        $(`.open-a11y-action-button[data-action="text-align"][data-value="${align}"]`).addClass('active');

        a11yState.textAlign = align;
    }

    // Toggle pause animations
    function togglePauseAnimations() {
        a11yState.pauseAnimations = !a11yState.pauseAnimations;
        $('body').toggleClass('open-a11y-pause-animations', a11yState.pauseAnimations);
        $('.open-a11y-action-button[data-action="pause-animations"]').toggleClass('active', a11yState.pauseAnimations);
    }

    // Reset all settings to default
    $('.open-a11y-reset-button').on('click', function() {
        // Remove all accessibility classes
        $('body').removeClass('open-a11y-high-contrast open-a11y-negative-contrast open-a11y-light-background open-a11y-dark-background');
        $('body').removeClass('open-a11y-grayscale open-a11y-readable-font open-a11y-links-underline');
        $('body').removeClass('open-a11y-hide-images open-a11y-reading-guide-active open-a11y-focus-outline');
        $('body').removeClass('open-a11y-big-line-height open-a11y-pause-animations');
        $('body').removeClass('open-a11y-text-align-left open-a11y-text-align-center open-a11y-text-align-right');

        // Remove text size classes
        for (let i = 1; i <= 5; i++) {
            $('body').removeClass(`open-a11y-text-size-${i}`);
        }

        // Reset all buttons active state
        $('.open-a11y-action-button').removeClass('active');

        // Reset state
        a11yState = {
            active: true,
            contrast: '',
            grayscale: false,
            textSize: 0,
            readableFont: false,
            linksUnderline: false,
            hideImages: false,
            readingGuide: false,
            focusOutline: false,
            lineHeight: false,
            textAlign: '',
            pauseAnimations: false
        };

        // Save reset state
        saveState();
    });

    // Save state to local storage
    function saveState() {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(storageKey, JSON.stringify(a11yState));
        }
    }

    // Load state from local storage
    function loadState() {
        if (typeof localStorage !== 'undefined') {
            const savedState = localStorage.getItem(storageKey);
            if (savedState) {
                try {
                    a11yState = JSON.parse(savedState);
                } catch (e) {
                    console.error('Error parsing saved accessibility state', e);
                }
            }
        }
    }

    // Apply current state to the UI
    function applyState() {
        // Apply contrast
        if (a11yState.contrast) {
            handleContrast(a11yState.contrast);
        }

        // Apply grayscale
        if (a11yState.grayscale) {
            $('body').addClass('open-a11y-grayscale');
            $('.open-a11y-action-button[data-action="grayscale"]').addClass('active');
        }

        // Apply text size
        if (a11yState.textSize > 0) {
            $('body').addClass(`open-a11y-text-size-${a11yState.textSize}`);
        }

        // Apply readable font
        if (a11yState.readableFont) {
            $('body').addClass('open-a11y-readable-font');
            $('.open-a11y-action-button[data-action="readable-font"]').addClass('active');
        }

        // Apply links underline
        if (a11yState.linksUnderline) {
            $('body').addClass('open-a11y-links-underline');
            $('.open-a11y-action-button[data-action="links-underline"]').addClass('active');
        }

        // Apply hide images
        if (a11yState.hideImages) {
            $('body').addClass('open-a11y-hide-images');
            $('.open-a11y-action-button[data-action="hide-images"]').addClass('active');
        }

        // Apply reading guide
        if (a11yState.readingGuide) {
            $('body').addClass('open-a11y-reading-guide-active');
            $('.open-a11y-action-button[data-action="reading-guide"]').addClass('active');
        }

        // Apply focus outline
        if (a11yState.focusOutline) {
            $('body').addClass('open-a11y-focus-outline');
            $('.open-a11y-action-button[data-action="focus-outline"]').addClass('active');
        }

        // Apply line height
        if (a11yState.lineHeight) {
            $('body').addClass('open-a11y-big-line-height');
            $('.open-a11y-action-button[data-action="line-height"]').addClass('active');
        }

        // Apply text align
        if (a11yState.textAlign) {
            $('body').addClass(`open-a11y-text-align-${a11yState.textAlign}`);
            $(`.open-a11y-action-button[data-action="text-align"][data-value="${a11yState.textAlign}"]`).addClass('active');
        }

        // Apply pause animations
        if (a11yState.pauseAnimations) {
            $('body').addClass('open-a11y-pause-animations');
            $('.open-a11y-action-button[data-action="pause-animations"]').addClass('active');
        }
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
        const hidden = getCookie('open_a11y_hidden');
        if (hidden === '1') {
            $('.open-a11y-widget-wrapper').css('display', 'none');
        } else {
            $('.open-a11y-widget-wrapper').css('display', ''); // Reset to default display
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initAccessibility();
        checkWidgetVisibility();
    });

})(jQuery);