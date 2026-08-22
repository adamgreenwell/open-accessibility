# Open Accessibility

An open-source accessibility widget that helps make your WordPress site more accessible to users with disabilities.

## Description

Open Accessibility is a comprehensive accessibility solution that helps your website comply with WCAG 2.1 standards and improve usability for people with disabilities.

The plugin adds a customizable accessibility widget to your website that gives users control over how they experience your content, with features like:

* High contrast mode
* Text size adjustment
* Adjustable letter spacing
* Adjustable word spacing
* Grayscale filter
* Reading guide
* Readable fonts
* Link underlining
* Focus indicators
* Line height adjustment
* Animation control
* And more!

### Key Features

* **Skip to Content Link**: Allows keyboard users to bypass navigation menus
* **Contrast Modes**: Multiple contrast options including high contrast, negative contrast, light, and dark backgrounds
* **Text Adjustments**: Increase text size, line height, letter spacing, word spacing, and enable readable fonts
* **Navigation Aids**: Reading guide, focus outlines, and link underlining
* **Visual Accommodations**: Grayscale mode, hide images, and pause animations
* **Accessibility Statement**: Built-in generator for creating accessibility statements
* **User Preferences**: Settings are saved between visits
* **Fully Customizable**: Admins can control appearance, position, and enabled features
* **Lightweight**: Minimal impact on page load times
* **WCAG 2.1 Compliant**: Helps sites meet accessibility guidelines

### Benefits

* Improves usability for people with disabilities
* Helps meet legal accessibility requirements
* Enhances user engagement by making your site more accessible
* Shows your commitment to inclusivity

### Important Note

While this plugin helps improve your website's accessibility, it does not guarantee full compliance with all accessibility standards and regulations. Regular accessibility audits and testing with real users are recommended.

## Installation

1. Upload the `open-accessibility` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'Accessibility' menu in your admin panel to configure settings

## Frequently Asked Questions

### Will this plugin make my site fully WCAG compliant?

This plugin helps improve accessibility and addresses many WCAG criteria, but complete compliance requires a comprehensive approach that includes proper content structure, image alt text, semantic HTML, and more. We recommend using this plugin as part of your accessibility strategy, not as a complete solution.

### Where will the accessibility widget appear?

You can choose from four positions: left side, right side, bottom left, or bottom right of the screen. You can also customize the size and appearance of the widget.

### Can I place the widget somewhere specific, like my site header?

Yes. Use the `[open_accessibility]` shortcode in your content, a block, or a template file (e.g. `<?php echo do_shortcode('[open_accessibility]'); ?>`). By default the panel automatically opens toward the side of the screen with the most room, so a toggle placed in your header opens downward and a toggle near the footer opens upward. You can also force a placement with the `direction` and `align` attributes:

```
[open_accessibility direction="down" align="right"]
```

`direction` accepts `auto` (default), `up`, or `down`. `align` accepts `auto` (default), `left` (panel extends to the right of the button), or `right` (panel extends to the left of the button).

### Can users hide the widget if they don't need it?

Yes, there's a "Hide Accessibility Panel" option in the widget that allows users to hide it. It will remain hidden for 24 hours.

### Can I generate an accessibility statement for my website?

Yes, the plugin includes a built-in accessibility statement generator that creates a statement based on your organization details.

### Does this plugin slow down my website?

The plugin is designed to be lightweight and only loads what's necessary. The impact on page load times should be minimal.

### Can users save their accessibility preferences?

Yes, all user preferences are saved using local storage in their browser, so settings persist between visits.

### Will this plugin work in a multisite environment?

Yes, this plugin is compatible with multisite installations.

### Can my theme fine-tune which content gets accessibility adjustments?

Yes. Open Accessibility exposes a shared targeting layer that themes can adjust with PHP filters, HTML attributes, and a small JavaScript API. Typography controls use these targets, and the same resolver also supports links, media, interactive controls, and opt-in layout relief.

```php
add_filter( 'open_accessibility_target_roots', function( $roots ) {
	$roots[] = '.my-theme-article-body';

	return $roots;
} );

add_filter( 'open_accessibility_target_excluded_selectors', function( $selectors ) {
	$selectors[] = '.my-theme-sidebar';
	$selectors[] = '.promo-card';

	return $selectors;
} );

add_filter( 'open_accessibility_layout_relief_selectors', function( $selectors ) {
	$selectors[] = '.my-theme-fixed-story-card';

	return $selectors;
} );

add_filter( 'open_accessibility_target_group_selectors', function( $selectors, $group_name ) {
	if ( 'readable_text' === $group_name ) {
		$selectors[] = '.my-theme-readable-copy';
	}

	return $selectors;
}, 10, 2 );

add_filter( 'open_accessibility_target_config', function( $config ) {
	$config['groups']['interactive'][] = '.my-theme-action';

	return $config;
} );
```

Use `open_accessibility_target_group_selectors` to refine a specific target group such as `readable_text`, `headings`, `links`, `media`, or `interactive`. If a theme needs complete control, `open_accessibility_target_config` receives the final full targeting array before it is passed to the frontend. Returning an empty selector array disables that default selector set; for example, empty `roots` disables the automatic body fallback unless a template opts back in with `data-oa-root`. Built-in widget exclusions and explicit ignore attributes still apply.

Templates can also use attributes:

```html
<main data-oa-root>
	<article class="story-card" data-oa-target="readable_text headings" data-oa-relax-layout>
		<h2>Story title</h2>
		<p>Readable story text.</p>
	</article>
	<aside class="open-accessibility-ignore" data-oa-ignore data-oa-preserve-layout>
		This region is ignored by Open Accessibility target mutations.
	</aside>
</main>
```

Use `data-oa-root` to add content roots, `data-oa-target` to opt an element into one or more groups, `data-oa-ignore` or `.open-accessibility-ignore` to exclude a region, `data-oa-relax-layout` to opt a container into layout relief, and `data-oa-preserve-layout` to keep a region out of layout relief.

Dynamic themes can refresh targets after rendering new content:

```js
window.OpenAccessibility.refresh();
```

### Is there a frontend JavaScript API?

Yes. The plugin exposes `window.OpenAccessibility` after the widget initializes:

```js
window.OpenAccessibility.refresh();
window.OpenAccessibility.getState();
window.OpenAccessibility.setState({ textSize: 2 });
window.OpenAccessibility.getTargets('readable_text');
window.OpenAccessibility.debug();
```

`window.OpenAccessibility.debug()` returns current state and target diagnostics. Frontend selector diagnostics are available from the console and API when the plugin debug option is enabled, or when a developer enables them in the browser with `localStorage.setItem('openAccessibilityDebug', '1')`.

It also dispatches lifecycle events on `document`: `openAccessibility:ready`, `openAccessibility:targetsRefreshed`, `openAccessibility:beforeApply`, `openAccessibility:afterApply`, and `openAccessibility:reset`.

### How do I enable debug logging?

To see debug messages from this plugin, you need to do two things:
1. Enable the "Enable Debugging" option in the plugin's settings page (under the 'Accessibility' menu).
2. Ensure that WordPress's core debugging constants are enabled in your `wp-config.php` file. Specifically, `WP_DEBUG` must be set to `true`, and `WP_DEBUG_LOG` must also be set to `true`. Logs will then appear in the `/wp-content/debug.log` file.

Frontend selector diagnostics are browser-side diagnostics. Use `window.OpenAccessibility.debug()` or the browser console for those; they are not written to WordPress `debug.log`.

## Changelog

### 1.3.02
* Fix shortcode-embedded widget panel opening offscreen when the toggle is placed near the top or right edge of the page (e.g. in a site header)
* Panel placement for shortcode embeds now adapts to available viewport space, and its height is capped so it scrolls instead of overflowing
* Add `direction` (auto/up/down) and `align` (auto/left/right) shortcode attributes to control which way the panel opens
* Fix shortcode-embedded widget panel being invisible on small screens; it now uses the same full-screen panel as the standard widget
* Add a shared frontend targeting resolver for typography, links, media, and layout-sensitive controls
* Add theme integration filters, HTML attributes, lifecycle events, and `window.OpenAccessibility` helpers
* Scope readable fonts, link underlining, hide-images, and grayscale controls to resolved content targets
* Improve widget ARIA state syncing for expanded panels, toggle buttons, and live indicators
* Harden saved and public API state so malformed values are normalized before applying settings
* Honor intentionally empty target config arrays while preserving built-in widget and explicit ignore exclusions
* Verified compatibility with WordPress 7.1 on PHP 8.4 and PHP 8.5
* Add a Links settings tab with optional Help and Feedback links in the widget panel
* Expose the Sitemap link in the settings UI; it had no field and could not be set
* Add a Panel Title setting to rename the widget heading
* Add an `open_accessibility_strings` filter so any widget label can be overridden
* Add an `open_accessibility_panel_title` filter and an `open_accessibility_panel_links` filter
* Add an opt-in "Open Links in Same Tab" setting that strips `target="_blank"` (WCAG 2.1 SC 3.2.5)
* Add an opt-in, off-by-default Usage Logging setting; feature usage is now recorded only when enabled
* Fix usage logging recording the admin-ajax action name instead of the interaction; the field is now `feature_action`
* Stop storing IP addresses and user agents with usage data, which could imply a visitor's disability
* Remove a `SHOW TABLES` query that ran on every single page load
* Fix the skip-to-content link doing nothing on themes without an `#content` element, including all block themes and Kadence
* Skip-to-content now moves keyboard focus to the target rather than only scrolling to it (WCAG 2.1 SC 2.4.1)
* Tested against Hello Elementor, Astra, GeneratePress, OceanWP, Kadence, and Twenty Twenty-Five

### 1.3.01
* Make typography controls adapt to theme-defined line height, spacing, and font sizing instead of overriding whole-page styles
* Scope text size, line height, spacing, and alignment controls to readable content areas
* Add theme filters and element opt-outs for typography targeting

### 1.2.76
* Fix analytics stats and cleanup queries when the stats table is missing or outdated
* Preserve accessibility statement cleanup during uninstall

### 1.2.75
* Accessibility widget can now be placed with a shortcode

### 1.2.74
* Add visual indication of scale level for text size, letter spacing, word spacing and line height

### 1.2.73
* Fix accessibility options panel positioning on mobile when negative or high contrast color modes are enabled

### 1.2.72
* Improved translation support and internationalization

### 1.2.71
* Improve color mode application for Bootstrap 5 elements
* Persist active tab when saving settings

### 1.2.7
* Added comprehensive CSS targeting for `.wp-block-*` elements in high contrast and negative contrast modes
* Fixed paragraph tags (`p`) not being properly targeted in contrast modes
* Improved accessibility icon styling in light background mode (now displays as black for better contrast)
* Better support for block-based themes like Twenty Twenty-Four and child themes

### 1.2.6
* Fixed bug causing duplicate local storage objects for accessibility settings
* Improve icon styling when in high contrast and negative contrast modes 

### 1.2.5
* Fixed widget panel display on mobile when accessibility panel button is set to a middle position

### 1.2.4
* Multisite compatibility: Frontend accessibility settings (localStorage and cookies) are now isolated per site in multisite subfolder setups
* Fixed admin settings checkboxes save and display correctly per subsite

### 1.2.3
* Fix for grayscale and text size preferences not persisting if a user leaves the site then returns

### 1.2.2
* Fixed plugin writing log files directly to the plugin directory, which is disallowed by WordPress Plugin Directory guidelines.
* Debug logging now uses the standard WordPress debug log (`wp-content/debug.log`) and requires both the plugin's debug setting and the `WP_DEBUG` and `WP_DEBUG_LOG` constants to be enabled.

### 1.2.1
* Added font selection option (Default, Atkinson Hyperlegible, OpenDyslexic).

### 1.2.0
* Added adjustable letter spacing control
* Added adjustable word spacing control
* Added reading guide (line focus) tool

### 1.1.4
* Remove legacy translations function and class as it is no longer needed

### 1.1.3
* Improve translation handing in WordPress versions prior to 4.6

### 1.1.2
* Update uninstaller with database query execution safety

### 1.1.1
* Added support for theme color modes (light and dark modes)

### 1.1.0
* Improved high contrast and negative contrast modes by switching from CSS filters to direct element styling
* Fixed widget positioning issue when contrast modes are enabled
* Made under the hood improvements for WordPress coding standards compliance

### 1.0.2
* Fixed grayscale toggle causing accessibility button and panel to lose fixed positioning

### 1.0.1
* Updated and refined icons for better display in the settings page and on the frontend

### 1.0.0
* Initial release

## Requirements

* WordPress 5.2 or higher
* PHP 7.4 or higher

## Credits

This plugin utilizes the following fonts under their respective open licenses:
* Atkinson Hyperlegible: Copyright (c) 2020, Braille Institute of America, Inc. ([https://brailleinstitute.org/freefont](https://brailleinstitute.org/freefont)) - SIL Open Font License, Version 1.1
* OpenDyslexic: Copyright (c) 2011, Abelardo Gonzalez ([https://opendyslexic.org/](https://opendyslexic.org/)) - Creative Commons Attribution 3.0 Unported License

This plugin was developed to help make the web more accessible to people with disabilities.

## License

This plugin is licensed under the GPL v2 or later.
