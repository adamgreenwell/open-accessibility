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
            }
        });
        
        // Ensure widget wrapper stays in viewport on scroll
        $(window).on('scroll', function() {
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

    // Toggle widget panel
    function toggleAccessibilityPanel() {
        const $panel = $('.open-accessibility-widget-panel');
        const $wrapper = $('.open-accessibility-widget-wrapper');
        const isMobile = window.innerWidth < 768;

        $panel.toggleClass('oa-panel-is-active');
        const isActive = $panel.hasClass('oa-panel-is-active');
        $panel.attr('aria-hidden', !isActive);

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
        const $wrapper = $('.open-accessibility-widget-wrapper');
        const isMobile = window.innerWidth < 768;

        $panel.removeClass('oa-panel-is-active');
        $panel.attr('aria-hidden', 'true');
        
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
        $('body').removeClass('open-accessibility-grayscale open-accessibility-links-underline');
        $('body').removeClass('open-accessibility-hide-images open-accessibility-reading-guide-active open-accessibility-focus-outline');
        $('body').removeClass('open-accessibility-big-line-height open-accessibility-pause-animations');
        $('body').removeClass('open-accessibility-text-align-left open-accessibility-text-align-center open-accessibility-text-align-right');
        $('body').removeClass('open-accessibility-font-atkinson open-accessibility-font-opendyslexic');

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
            selectedFont: 'default',
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

        // Explicitly hide the reading guide on reset
        $('.open-accessibility-reading-guide').hide(); 

        // Clear the grayscale filter from all elements
        $('body *').css('filter', '');
        $('.open-accessibility-toggle-button').removeClass('widget-grayscale');
        $('.open-accessibility-widget-panel').removeClass('widget-grayscale');

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
                    accessibilityState = JSON.parse(savedState);
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

        // Apply text size
        if (accessibilityState.textSize > 0) {
            $('body').addClass(`open-accessibility-text-size-${accessibilityState.textSize}`);
        }
        // Update button states regardless of whether size > 0
        const maxTextSize = 5;
        $('.open-accessibility-action-button[data-action="text-size"][data-value="decrease"]').prop('disabled', accessibilityState.textSize <= 0);
        $('.open-accessibility-action-button[data-action="text-size"][data-value="increase"]').prop('disabled', accessibilityState.textSize >= maxTextSize);

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