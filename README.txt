=== Open Accessibility ===
Contributors: adamgreenwell
Tags: accessibility, wcag, ada, disability, readable
Requires at least: 5.2
Tested up to: 6.8
Stable tag: 1.2.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An open-source accessibility widget that helps make your WordPress site more accessible to users with disabilities.

== Description ==

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

= Key Features =

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

= Benefits =

* Improves usability for people with disabilities
* Helps meet legal accessibility requirements
* Enhances user engagement by making your site more accessible
* Shows your commitment to inclusivity

= Important Note =

While this plugin helps improve your website's accessibility, it does not guarantee full compliance with all accessibility standards and regulations. Regular accessibility audits and testing with real users are recommended.

== Installation ==

1. Upload the `open-accessibility` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'Accessibility' menu in your admin panel to configure settings

== Frequently Asked Questions ==

= Will this plugin make my site fully WCAG compliant? =

This plugin helps improve accessibility and addresses many WCAG criteria, but complete compliance requires a comprehensive approach that includes proper content structure, image alt text, semantic HTML, and more. We recommend using this plugin as part of your accessibility strategy, not as a complete solution.

= Where will the accessibility widget appear? =

You can choose from four positions: left side, right side, bottom left, or bottom right of the screen. You can also customize the size and appearance of the widget.

= Can users hide the widget if they don't need it? =

Yes, there's a "Hide Accessibility Panel" option in the widget that allows users to hide it. It will remain hidden for 24 hours.

= Can I generate an accessibility statement for my website? =

Yes, the plugin includes a built-in accessibility statement generator that creates a statement based on your organization details.

= Does this plugin slow down my website? =

The plugin is designed to be lightweight and only loads what's necessary. The impact on page load times should be minimal.

= Can users save their accessibility preferences? =

Yes, all user preferences are saved using local storage in their browser, so settings persist between visits.

= Will this plugin work in a multisite environment? =

Yes, this plugin is compatible with multisite installations.

= How do I enable debug logging? =

To see debug messages from this plugin, you need to do two things:
1. Enable the "Enable Debugging" option in the plugin's settings page (under the 'Accessibility' menu).
2. Ensure that WordPress's core debugging constants are enabled in your `wp-config.php` file. Specifically, `WP_DEBUG` must be set to `true`, and `WP_DEBUG_LOG` must also be set to `true`. Logs will then appear in the `/wp-content/debug.log` file.

== Changelog ==

= 1.2.4 =
* Multisite compatibility: Frontend accessibility settings (localStorage and cookies) are now isolated per site in multisite subfolder setups
* Fixed admin settings checkboxes save and display correctly per subsite

= 1.2.3 =
* Fix for grayscale and text size preferences not persisting if a user leaves the site then returns

= 1.2.2 =
* Fixed plugin writing log files directly to the plugin directory, which is disallowed by WordPress Plugin Directory guidelines.
* Debug logging now uses the standard WordPress debug log (`wp-content/debug.log`) and requires both the plugin's debug setting and the `WP_DEBUG` and `WP_DEBUG_LOG` constants to be enabled.

= 1.2.1 =
* Added font selection option (Default, Atkinson Hyperlegible, OpenDyslexic).

= 1.2.0 =
* Added adjustable letter spacing control
* Added adjustable word spacing control
* Added reading guide (line focus) tool

= 1.1.4 =
* Remove legacy translations function and class as it is no longer needed

= 1.1.3 =
* Improve translation handing in WordPress versions prior to 4.6

= 1.1.2 =
* Update uninstaller with database query execution safety

= 1.1.1 =
* Added support for theme color modes (light and dark modes)

= 1.1.0 =
* Improved high contrast and negative contrast modes by switching from CSS filters to direct element styling
* Fixed widget positioning issue when contrast modes are enabled
* Made under the hood improvements for WordPress coding standards compliance

= 1.0.2 =
* Fixed grayscale toggle causing accessibility button and panel to lose fixed positioning

= 1.0.1 =
* Updated and refined icons for better display in the settings page and on the frontend

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.1.0 =
This update fixes widget positioning issues when contrast modes are enabled and improves code quality.

= 1.0.2 =
This update improves the behavior of the grayscale accessibility feature.

= 1.0.1 =
This update improves the visual appearance of icons in both the settings page and frontend widget.

= 1.0.0 =
Initial release of the Open Accessibility plugin.

== Credits ==

This plugin utilizes the following fonts under their respective open licenses:
* Atkinson Hyperlegible: Copyright (c) 2020, Braille Institute of America, Inc. (https://brailleinstitute.org/freefont) - SIL Open Font License, Version 1.1
* OpenDyslexic: Copyright (c) 2011, Abelardo Gonzalez (https://opendyslexic.org/) - Creative Commons Attribution 3.0 Unported License

This plugin was developed to help make the web more accessible to people with disabilities.