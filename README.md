# Open Accessibility

An open-source accessibility widget that helps make your WordPress site more accessible to users with disabilities.

## Description

Open Accessibility is a free, open-source accessibility plugin. It gives visitors control over how they experience your content, and it gives you a plain account of what your content's accessibility problems actually are.

It does two things.

**A widget** your visitors can use: contrast modes, text and spacing adjustments, readable fonts, a reading guide and mask, cursor sizing, link highlighting and saturation control, plus five one-click profiles for common needs.

**An audit** for you: accessibility checks inside the block editor as you write, and a site-wide report that scans every post and page and lists what it found, worst first.

The plugin adds a customizable accessibility widget to your website that gives users control over how they experience your content, with features like:

* Five accessibility profiles, and the individual controls behind them
* High contrast mode
* Text size adjustment
* Adjustable letter spacing, word spacing and line height
* Grayscale and saturation control
* Reading guide and reading mask
* Readable fonts
* Link underlining and link highlighting
* Cursor size
* Focus indicators
* Animation control
* And more!

### Key Features

* **Skip to Content Link**: Allows keyboard users to bypass navigation menus
* **Contrast Modes**: Multiple contrast options including high contrast, negative contrast, light, and dark backgrounds
* **Text Adjustments**: Increase text size, line height, letter spacing, word spacing, and enable readable fonts
* **Navigation Aids**: Reading guide, focus outlines, and link underlining
* **Visual Accommodations**: Grayscale mode, hide images, and pause animations
* **Accessibility Profiles**: Five presets — Seizure Safe, Vision Impaired, ADHD Friendly, Blind, and Epilepsy Safe — each switching on a tested combination of controls
* **Editor Audit**: Flags alt text, heading, link, button and table problems in the block editor while you write
* **Site Report**: Scans every post and page in batches and lists the posts with findings, worst first, with a WCAG criterion for each
* **Accessibility Statement**: Generates a statement that cites your chosen WCAG version and conformance level, including "not assessed"
* **User Preferences**: Settings are saved between visits
* **Fully Customizable**: Admins can control appearance, position, and enabled features
* **Lightweight**: Minimal impact on page load times
* **Honest About Scope**: The checks are a defined set of rules, and the plugin names the categories it cannot check rather than implying it checked everything

### Benefits

* Improves usability for people with disabilities
* Helps meet legal accessibility requirements
* Enhances user engagement by making your site more accessible
* Shows your commitment to inclusivity

### Important Note

**This plugin does not make your site compliant, and does not claim to.**

It checks a defined set of rules and reports what it finds. Automated checks cannot decide everything — colour contrast, focus order, keyboard traps and ARIA correctness all need a person — and the plugin names those categories explicitly rather than implying it covered them.

No widget can make a site accessible on its own. Use this plugin as one input alongside manual testing and, where the stakes justify it, a human audit.

## Installation

1. Upload the `open-accessibility` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'Accessibility' menu in your admin panel to configure settings

## Frequently Asked Questions

### Will this plugin make my site fully WCAG compliant?

No. Nothing can.

The widget helps visitors adapt your content to their needs, and the built-in audit checks a defined set of rules, but automated checks cannot decide everything. This plugin does **not** check colour contrast, focus order or visibility, keyboard traps, ARIA roles and states, or anything inside third-party blocks whose output is not in the block data — and it says so on screen, in both the editor panel and the site report.

Treat it as one input alongside manual testing with real users, and a human audit where the stakes justify one. Any tool that tells you it has made your site compliant is overstating what it can know.

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

Yes. **Accessibility → Statement** generates one from your organisation details, your chosen WCAG version and conformance level — including "not assessed" — your assessment method and date. If you have run a content report, the statement can cite what it actually found, and it names the categories automated checks cannot decide. An unassessed site is described as unassessed rather than asserted to conform.

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

## Accessibility Profiles

Five presets, each switching on a combination of controls that has been tested together. Visitors pick one from the widget; you choose which are offered on **Accessibility → Profiles**.

| Profile | What it does |
| --- | --- |
| **Seizure Safe** | Pauses animation and softens contrast. |
| **Vision Impaired** | Larger text with more line height and underlined links. |
| **ADHD Friendly** | Cuts distraction with a reading mask, hidden images and paused motion. |
| **Blind** | Emphasises links and focus visibility for keyboard and screen reader use. |
| **Epilepsy Safe** | Pauses motion, removes colour, and applies high contrast. |

Each profile has an option (`enable_profile_seizure_safe`, `enable_profile_vision_impaired`, `enable_profile_adhd_friendly`, `enable_profile_blind`, `enable_profile_epilepsy_safe`). A profile that depends on a control you have switched off is not offered to visitors, so the widget never presents a choice that would do nothing.

## Widget Controls

Beyond the profiles, visitors can use individual controls:

| Control | Details |
| --- | --- |
| **Cursor size** | Normal, large or extra large. Useful for anyone who loses the pointer. |
| **Saturation** | Low, medium or high, drawn as an overlay so it composes with grayscale and contrast rather than replacing them. |
| **Highlight links** | Gives links a visible background in addition to underlining them. |

These sit alongside the existing text size, spacing, readable font, reading guide and mask, contrast, grayscale, hide images, focus outline and animation controls.

## Content Audit

Two places, one set of rules.

**In the editor.** While you write, a panel in the block editor flags problems in the post you are working on. It reads block data, so findings update as you type without a round trip.

**Site-wide.** **Accessibility → Report** scans every post and page in batches and lists the posts with findings, worst first, with a WCAG criterion for each. You can rescan a single post, and a scan left running finishes in the background if you close the tab.

### What the checks look for

Seven rules, each mapped to a WCAG success criterion:

| Severity | What it flags | WCAG |
| --- | --- | --- |
| Error | An image with no alt text | 1.1.1 |
| Warning | Alt text that does not describe the image | 1.1.1 |
| Warning | A heading that skips a level | 1.3.1 |
| Error | An empty heading | 1.3.1 |
| Warning | Link text that does not say where the link goes | 2.4.4 |
| Error | A button with no label | 4.1.2 |
| Review | A table with no header cells | 1.3.1 |

### What the checks cannot look at

These categories are **not** checked, and both the editor panel and the site report say so on screen:

* Colour contrast
* Focus order and focus visibility
* Keyboard traps
* ARIA roles and states
* Anything inside third-party blocks whose output is not in the block data

An empty `alt` attribute is never reported. `alt=""` is how you mark decorative images, so treating it as an error would train people to ignore the check. A heading starting at `h2` is likewise not a skipped level — the page already has an `h1` in most themes.

## Filters

### Relabelling widget controls

`open_accessibility_strings` receives the array of strings the widget renders, so any label or heading can be reworded without touching a template:

```php
add_filter( 'open_accessibility_strings', function ( $strings ) {
	$strings['widget_title']  = 'Reading options';
	$strings['grayscale_text'] = 'Remove colour';

	return $strings;
} );
```

Every control has a `_title` (the heading) and, where it has one, a `_text` (the label or description). The full set is `widget_title`, `reset_title`, `reset_text`, `keyboard_nav_title`, `keyboard_nav_text`, `contrast_title`, `contrast_modes`, `high`, `negative`, `light`, `dark`, `grayscale_title`, `grayscale_text`, `text_size_title`, `text_size_increase`, `text_size_decrease`, `readable_font_title`, `font_default`, `font_atkinson`, `font_opendyslexic`, `links_underline_title`, `links_underline_text`, `hide_images_title`, `hide_images_text`, `reading_guide_title`, `reading_guide_text`, `reading_mask_title`, `reading_mask_text`, `focus_outline_title`, `focus_outline_text`, `line_height_title`, `line_height_text`, `text_align_title`, `text_align_center`, `text_align_left`, `text_align_right`, `pause_animations_title`, `pause_animations_text`, `letter_spacing_title`, `letter_spacing_text`, `word_spacing_title`, `word_spacing_text`, `statement_title`, `statement_text`, `dismiss_text`, `skip_to_content`, `help_text`, `feedback_text` and `sitemap_text`.

This affects the widget only. It does not rename anything in the admin screens or in a generated statement.

### Panel title and links

`open_accessibility_panel_title` filters the widget's heading, and `open_accessibility_panel_links` filters the array of links shown in the panel.

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

### 1.5.0
* Add five accessibility profiles: Seizure Safe, Vision Impaired, ADHD Friendly, Blind, and Epilepsy Safe
* Add a Cursor Size control (normal, large, extra large)
* Add a Saturation control that composes with grayscale and contrast instead of replacing them
* Add a Highlight Links control that gives links a visible background as well as an underline
* Add accessibility checks inside the block editor, covering alt text, headings, links, buttons and tables
* Add a site-wide content report that scans every post and page in batches and lists findings worst first
* Add a content report screen with per-post rescanning and background scanning
* Rebuild the accessibility statement generator: it now cites your chosen WCAG version and conformance level, including "not assessed", and lists real findings from the content report
* Make the statement generator's conformance claim honest: an unassessed site is described as unassessed rather than asserted to be partially conformant
* Clear the stored statement URL when its page is deleted, so the settings field and widget link cannot point at a 404
* Improve the widget so a control switched off in settings also stops appearing in a profile
* Fix the accessibility report screen, which returned a 404 for every user who opened it

### 1.4.01
* Fix the widget becoming unreachable when a contrast mode is switched on
* Raise the skip-to-content link above theme chrome
* Improve content detection for page builders, including Divi
* Add a Reading Mask control that dims the page except for a band tracking the pointer
* Tested against Divi, Blocksy, Neve, Sydney, and Hestia

### 1.4.0
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
* Add an `open_accessibility_skip_target_candidates` filter to control where skip-to-content lands
* Tested against Hello Elementor, Astra, GeneratePress, OceanWP, Kadence, and Twenty Twenty-Five

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
