/**
 * Frontend JavaScript for the Open Accessibility plugin
 *
 * Handles the accessibility widget functionality
 */

(function($) {
    'use strict';

    // Store state in local storage
    const storageKey = 'open_accessibility_settings';
    let accessibilityState = {
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
        pauseAnimations: false,
        letterSpacingLevel: 0,
        wordSpacingLevel: 0
    };

    const MAX_SPACING_LEVEL = 3;

    // Initialize
    function initAccessibility() {
        // Load saved state
        loadState();

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
            if (
                $('.open-accessibility-widget-wrapper').hasClass('active') &&
                !$(e.target).closest('.open-accessibility-widget-wrapper').length &&
                !$(e.target).is('.open-accessibility-action-button')
            ) {
                closeAccessibilityPanel();
            }
        });

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // ESC key closes the panel
            if (e.key === 'Escape' && $('.open-accessibility-widget-wrapper').hasClass('active')) {
                closeAccessibilityPanel();
            }
        });
        
        // Ensure widget stays in viewport on scroll
        $(window).on('scroll', function() {
            const $widget = $('.open-accessibility-widget-wrapper');
            if ($widget.hasClass('position-left') || $widget.hasClass('position-right')) {
                // Basic positioning for all modes
                $widget.css({
                    'position': 'fixed',
                    'top': '50vh',
                    'transform': 'translateY(-50%)'
                });
            }
        });
    }

    // Toggle widget panel
    function toggleAccessibilityPanel() {
        $('.open-accessibility-widget-wrapper').toggleClass('active');
        const isActive = $('.open-accessibility-widget-wrapper').hasClass('active');
        $('.open-accessibility-widget-panel').attr('aria-hidden', !isActive);
    }

    // Close widget panel
    function closeAccessibilityPanel() {
        $('.open-accessibility-widget-wrapper').removeClass('active');
        $('.open-accessibility-widget-panel').attr('aria-hidden', true);
    }

    // Hide widget (user choice)
    function hideAccessibilityWidget() {
        $('.open-accessibility-widget-wrapper').css('display', 'none');

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

            case 'letter-spacing':
                adjustLetterSpacing(value);
                break;

            case 'word-spacing':
                adjustWordSpacing(value);
                break;
        }

        // Update state
        saveState();
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

    // Toggle grayscale
    function toggleGrayscale() {
        accessibilityState.grayscale = !accessibilityState.grayscale;

        if (accessibilityState.grayscale) {
            // Apply grayscale to the main content
            $('body *').not('.open-accessibility-widget-wrapper, .open-accessibility-widget-wrapper *').css('filter', 'grayscale(100%)');

            // Add a class to the widget wrapper for CSS targeting
            $('.open-accessibility-widget-wrapper').addClass('widget-grayscale');
        } else {
            // Remove grayscale from the main content
            $('body *').not('.open-accessibility-widget-wrapper, .open-accessibility-widget-wrapper *').css('filter', '');

            // Remove the class from the widget wrapper
            $('.open-accessibility-widget-wrapper').removeClass('widget-grayscale');
        }

        // Toggle the button active state
        $('.open-accessibility-action-button[data-action="grayscale"]').toggleClass('active', accessibilityState.grayscale);
    }

    // Adjust text size
    function adjustTextSize(direction) {
        // Remove existing text size classes
        for (let i = 1; i <= 5; i++) {
            $('body').removeClass(`open-accessibility-text-size-${i}`);
        }

        if (direction === 'increase') {
            accessibilityState.textSize = Math.min(accessibilityState.textSize + 1, 5);
        } else if (direction === 'decrease') {
            accessibilityState.textSize = Math.max(accessibilityState.textSize - 1, 0);
        }

        if (accessibilityState.textSize > 0) {
            $('body').addClass(`open-accessibility-text-size-${accessibilityState.textSize}`);
        }
    }

    // Toggle readable font
    function toggleReadableFont() {
        accessibilityState.readableFont = !accessibilityState.readableFont;
        $('body').toggleClass('open-accessibility-readable-font', accessibilityState.readableFont);
        $('.open-accessibility-action-button[data-action="readable-font"]').toggleClass('active', accessibilityState.readableFont);
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
        $('body').toggleClass('open-accessibility-reading-guide-active', accessibilityState.readingGuide);
        $('.open-accessibility-action-button[data-action="reading-guide"]').toggleClass('active', accessibilityState.readingGuide);
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

    // Toggle line height
    function toggleLineHeight() {
        accessibilityState.lineHeight = !accessibilityState.lineHeight;
        $('body').toggleClass('open-accessibility-big-line-height', accessibilityState.lineHeight);
        $('.open-accessibility-action-button[data-action="line-height"]').toggleClass('active', accessibilityState.lineHeight);
    }

    // Set text align
    function setTextAlign(align) {
        // Remove existing text align classes
        $('body').removeClass('open-accessibility-text-align-left open-accessibility-text-align-center open-accessibility-text-align-right');

        // Clear active state on all text align buttons
        $('.open-accessibility-action-button[data-action="text-align"]').removeClass('active');

        // If the same alignment is clicked again, deactivate it
        if (accessibilityState.textAlign === align) {
            accessibilityState.textAlign = '';
            return;
        }

        // Apply new text alignment
        $('body').addClass(`open-accessibility-text-align-${align}`);
        $(`.open-accessibility-action-button[data-action="text-align"][data-value="${align}"]`).addClass('active');

        accessibilityState.textAlign = align;
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
            // Remove previous level class
            for (let i = 1; i <= MAX_SPACING_LEVEL; i++) {
                $('body').removeClass(`open-accessibility-letter-spacing-${i}`);
            }
            // Add new level class if > 0
            if (newLevel > 0) {
                $('body').addClass(`open-accessibility-letter-spacing-${newLevel}`);
            }
            updateSpacingButtonStates('letter-spacing', newLevel);
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
            // Remove previous level class
            for (let i = 1; i <= MAX_SPACING_LEVEL; i++) {
                $('body').removeClass(`open-accessibility-word-spacing-${i}`);
            }
            // Add new level class if > 0
            if (newLevel > 0) {
                $('body').addClass(`open-accessibility-word-spacing-${newLevel}`);
            }
            updateSpacingButtonStates('word-spacing', newLevel);
        }
    }

    // Helper to update Increase/Decrease button states
    function updateSpacingButtonStates(action, level) {
        $(`.open-accessibility-action-button[data-action="${action}"][data-value="decrease"]`).prop('disabled', level <= 0);
        $(`.open-accessibility-action-button[data-action="${action}"][data-value="increase"]`).prop('disabled', level >= MAX_SPACING_LEVEL);
    }

    // Reset all settings to default
    $('.open-accessibility-reset-button').on('click', function() {
        // Remove all accessibility classes
        $('body').removeClass('open-accessibility-high-contrast open-accessibility-negative-contrast open-accessibility-light-background open-accessibility-dark-background');
        $('body').removeClass('open-accessibility-grayscale open-accessibility-readable-font open-accessibility-links-underline');
        $('body').removeClass('open-accessibility-hide-images open-accessibility-reading-guide-active open-accessibility-focus-outline');
        $('body').removeClass('open-accessibility-big-line-height open-accessibility-pause-animations');
        $('body').removeClass('open-accessibility-text-align-left open-accessibility-text-align-center open-accessibility-text-align-right');

        // Remove text size and spacing classes
        for (let i = 1; i <= 5; i++) {
            $('body').removeClass(`open-accessibility-text-size-${i}`);
        }
        for (let i = 1; i <= MAX_SPACING_LEVEL; i++) {
            $('body').removeClass(`open-accessibility-letter-spacing-${i}`);
            $('body').removeClass(`open-accessibility-word-spacing-${i}`);
        }

        // Reset all buttons active/disabled state
        $('.open-accessibility-action-button').removeClass('active').prop('disabled', false);

        // Reset state
        accessibilityState = {
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
            pauseAnimations: false,
            letterSpacingLevel: 0,
            wordSpacingLevel: 0
        };

        // Clear the grayscale filter from all elements
        $('body *').css('filter', '');
        $('.open-accessibility-widget-wrapper').removeClass('widget-grayscale');

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
            const savedState = localStorage.getItem(storageKey);
            if (savedState) {
                try {
                    accessibilityState = JSON.parse(savedState);
                } catch (e) {
                    console.error('Error parsing saved accessibility state', e);
                }
            }
        }
    }

    // Apply current state to the UI
    function applyState() {
        // Apply contrast
        if (accessibilityState.contrast) {
            handleContrast(accessibilityState.contrast);
        }

        // Apply grayscale
        if (accessibilityState.grayscale) {
            $('body').addClass('open-accessibility-grayscale');
            $('.open-accessibility-action-button[data-action="grayscale"]').addClass('active');
        }

        // Apply text size
        if (accessibilityState.textSize > 0) {
            $('body').addClass(`open-accessibility-text-size-${accessibilityState.textSize}`);
        }

        // Apply readable font
        if (accessibilityState.readableFont) {
            $('body').addClass('open-accessibility-readable-font');
            $('.open-accessibility-action-button[data-action="readable-font"]').addClass('active');
        }

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
            $('body').addClass('open-accessibility-reading-guide-active');
            $('.open-accessibility-action-button[data-action="reading-guide"]').addClass('active');
        }

        // Apply focus outline
        if (accessibilityState.focusOutline) {
            $('body').addClass('open-accessibility-focus-outline');
            $('.open-accessibility-action-button[data-action="focus-outline"]').addClass('active');
        }

        // Apply line height
        if (accessibilityState.lineHeight) {
            $('body').addClass('open-accessibility-big-line-height');
            $('.open-accessibility-action-button[data-action="line-height"]').addClass('active');
        }

        // Apply text align
        if (accessibilityState.textAlign) {
            $('body').addClass(`open-accessibility-text-align-${accessibilityState.textAlign}`);
            $(`.open-accessibility-action-button[data-action="text-align"][data-value="${accessibilityState.textAlign}"]`).addClass('active');
        }

        // Apply pause animations
        if (accessibilityState.pauseAnimations) {
            $('body').addClass('open-accessibility-pause-animations');
            $('.open-accessibility-action-button[data-action="pause-animations"]').addClass('active');
        }

        // Apply letter spacing level
        if (accessibilityState.letterSpacingLevel > 0) {
            $('body').addClass(`open-accessibility-letter-spacing-${accessibilityState.letterSpacingLevel}`);
        }
        updateSpacingButtonStates('letter-spacing', accessibilityState.letterSpacingLevel);

        // Apply word spacing level
        if (accessibilityState.wordSpacingLevel > 0) {
            $('body').addClass(`open-accessibility-word-spacing-${accessibilityState.wordSpacingLevel}`);
        }
        updateSpacingButtonStates('word-spacing', accessibilityState.wordSpacingLevel);
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
            $('.open-accessibility-widget-wrapper').css('display', 'none');
        } else {
            $('.open-accessibility-widget-wrapper').css('display', ''); // Reset to default display
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initAccessibility();
        checkWidgetVisibility();
    });

})(jQuery);