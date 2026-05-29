# Open Accessibility Targeting And Integration Design

## Summary

The first slice will add a developer-facing targeting and integration layer that lets Open Accessibility work across more themes without hard-coding theme-specific fixes. The plugin already scopes typography controls with PHP filters; this design generalizes that idea so other controls can resolve the right DOM elements, honor exclusions, expose a small JavaScript API, and give theme developers documented escape hatches.

This slice intentionally favors a stable foundation over an admin selector editor. Admin UI for custom selectors is deferred until the API and defaults prove themselves on real themes.

## Problems To Solve

- Typography has scoped targets, but grayscale, readable fonts, link underline, hide images, focus outline, pause animations, and contrast still depend heavily on broad body classes, global selectors, or inline changes across `body *`.
- Some modern themes use fixed heights, overflow clipping, and line clamps. Increasing text size can work technically while still hiding content visually.
- Theme developers need predictable PHP filters, HTML attributes, JavaScript hooks, and debug output so they can integrate edge cases without forking plugin behavior.
- The widget controls will expose better state to assistive technology while this DOM work is already being touched.

## Non-Goals

- No theme-specific Athletic Turf selectors in plugin defaults.
- No admin textarea or selector builder in this first slice.
- No full contrast-system rewrite in this first slice. Contrast continues to use body classes while the shared targeting foundation is added.
- No claim that this plugin alone makes a site WCAG conformant. The goal is better controls, safer defaults, and clearer integration points.

## Target Configuration

Add a shared frontend target configuration generated in PHP and passed to JavaScript alongside the existing localized options. The configuration will include these selector groups:

- `roots`: content regions where feature controls are allowed to operate.
- `readable_text`: paragraphs, list items, table cells, labels, captions, blockquotes, and similar readable text.
- `headings`: `h1` through `h6`.
- `links`: links inside allowed roots.
- `media`: images, pictures, videos, SVGs, canvases, iframes, and embeds where media-specific controls apply.
- `interactive`: links, buttons, inputs, selects, textareas, summaries, and tabindex-enabled controls.
- `layout_containers`: opt-in containers that may need height, overflow, or line-clamp relief when text-affecting controls are active.
- `excluded`: widget internals, skip links, reading tools, sidebars, navigation, widgets, forms where appropriate, screen-reader-only text, and elements marked as ignored.

The existing `get_typography_targets()` behavior will remain backward compatible. The implementation will introduce `get_target_config()` and map the typography groups to the new shape while still applying the old filters.

## PHP Integration API

Add filters that let themes refine the shared config:

- `open_accessibility_target_config`: final array filter for the full configuration.
- `open_accessibility_target_roots`: allowed root regions.
- `open_accessibility_target_excluded_selectors`: global exclusions.
- `open_accessibility_target_group_selectors`: per-group selector lists, with the group name passed as a filter argument.
- `open_accessibility_layout_relief_selectors`: selectors eligible for layout relief.

Keep these existing typography filters as compatibility aliases:

- `open_accessibility_typography_targets`
- `open_accessibility_typography_content_roots`
- `open_accessibility_typography_text_elements`
- `open_accessibility_typography_heading_elements`
- `open_accessibility_typography_excluded_selectors`

Document simple theme examples in `README.md` and `README.txt`, including how to add a custom content root, exclude a component, and opt a fixed-height card into layout relief.

## HTML Integration API

Support these attributes/classes as stable theme integration points:

- `data-oa-root`: mark an element as an allowed content root.
- `data-oa-ignore`: exclude an element and its descendants from plugin mutations.
- `data-oa-target="readable_text links media interactive"`: add an element to one or more target groups.
- `data-oa-relax-layout`: opt a container into layout relief.
- `data-oa-preserve-layout`: prevent layout relief on an element and its descendants.
- `.open-accessibility-ignore`: class alias for `data-oa-ignore`.

The attributes are additive. Theme developers can use filters for broad integration and attributes for one-off template edge cases.

## JavaScript Target Resolver

Add a small resolver module inside the existing public script. It will:

- Normalize selector arrays and discard empty values.
- Catch invalid selectors and report them only in debug mode.
- Resolve roots from configured selectors and `[data-oa-root]`, falling back to `document.body` when no roots are found.
- Resolve target groups inside roots, apply exclusions, and de-duplicate elements.
- Exclude widget internals, reading-guide elements, skip links, ignored elements, and preserved-layout elements where applicable.
- Provide `refresh()` so dynamic themes, sliders, and AJAX-rendered content can be re-scanned after the DOM changes.

Expose a public API at `window.OpenAccessibility`:

- `refresh()`: re-resolve targets and re-apply the current state.
- `getState()`: return a copy of the current accessibility state.
- `setState(partialState)`: merge supported state keys and apply them.
- `getTargets(group)`: return the currently resolved elements for one target group.
- `debug()`: return target counts, invalid selectors, skipped selectors, and layout relief counts when diagnostics are enabled.

Dispatch lifecycle events on `document`:

- `openAccessibility:ready`
- `openAccessibility:targetsRefreshed`
- `openAccessibility:beforeApply`
- `openAccessibility:afterApply`
- `openAccessibility:reset`

Events will include the current state and target summary in `event.detail`.

## Feature Behavior In This Slice

Typography controls will keep their current adaptive behavior but resolve elements through the shared target resolver.

Readable font controls will stop relying only on `body.open-accessibility-font-* *`. They will apply to `readable_text` and `headings` targets, store original inline font values when needed, and restore them on reset.

Links underline will apply to the `links` group. Existing body classes can stay as compatibility fallback while the new targeted behavior becomes primary.

Hide images will apply to the `media` group where the element is image-like. It will store and restore inline visibility/display values instead of assuming global CSS is enough.

Grayscale will stop mutating `body *`. It will apply either to configured roots or target groups, store original inline filter values, and always skip widget internals and ignored elements.

Focus outline keeps its body class in this slice, and the resolver exposes an `interactive` group so a future pass can make it fully targeted. The first slice will still improve button ARIA state.

Pause animations will keep the current broad body-class implementation in this slice. The resolver and documentation will expose ignore/preserve hooks for theme integration, and a deeper targeted animation pass remains outside this implementation boundary.

Contrast will remain body-class based in this slice. The targeting layer will avoid making the contrast system more specific until the selector API is proven by lower-risk controls.

## Layout Relief

When text-affecting controls are active, apply layout relief only to elements matching `layout_containers` or `[data-oa-relax-layout]`.

Text-affecting controls are:

- Text size
- Line height
- Letter spacing
- Word spacing
- Readable font

For matched containers, store original inline values for:

- `height`
- `max-height`
- `overflow`
- `overflow-x`
- `overflow-y`
- `display`
- `-webkit-line-clamp`
- `line-clamp`

Then apply conservative relief:

- `height: auto`
- `max-height: none`
- `overflow: visible`
- `-webkit-line-clamp: unset`
- `line-clamp: unset`
- `display: block` only when the matched element currently uses `-webkit-box` for line clamping

Do not auto-relax navigation, sidebars, widgets, form controls, or plugin UI. Respect `data-oa-preserve-layout` even when a selector or filter would otherwise match.

## Widget Semantics

Improve widget markup and state updates while touching the frontend script:

- Toggle button gets `aria-expanded` and `aria-controls`.
- Panel gets a stable `id`, `role="region"`, and `aria-labelledby`. Use `role="dialog"` only in a later focus-management pass.
- Toggle-style action buttons update `aria-pressed`.
- Exclusive option groups, such as contrast and font selection, update `aria-pressed` so the active option is exposed.
- Increment/decrement controls continue updating visual indicators and also update an `aria-live="polite"` status.
- Escape closes the panel and returns focus to the toggle button.

## Debug Diagnostics

Diagnostics are available when the existing debug option is enabled or when a local developer enables a localStorage flag such as `openAccessibilityDebug=1`.

Debug output will include:

- Target counts by group.
- Invalid selectors skipped by the resolver.
- Missing root fallback status.
- Elements skipped by exclusions.
- Layout relief count and preserved-layout skips.
- Current state snapshot.

Diagnostics will be available through `window.OpenAccessibility.debug()` and concise console output. Debug mode must not write noisy console messages for normal visitors.

## Backward Compatibility

- Existing localStorage state keys remain unchanged.
- Existing body classes remain available where needed for CSS compatibility.
- Existing typography filters keep working.
- Existing shortcode/widget rendering remains compatible.
- No existing admin setting is removed or renamed.
- Reset restores stored inline styles added by this slice.

## Testing And Verification

Run repo checks:

- `composer check`

Browser smoke checks:

- Default WordPress/block-theme page: open panel, toggle each affected control, reset, verify no console errors.
- Athletic Turf stage/local page: text size and line-height change readable content, and opt-in layout relief prevents clipped hero/card text.
- Sidebar/navigation/widget areas remain excluded unless explicitly opted in.
- Dynamic content test: add content after load, call `window.OpenAccessibility.refresh()`, and verify targets update.
- Reset restores inline styles and removes targeted feature effects.
- `aria-expanded`, `aria-controls`, `aria-pressed`, and `aria-live` update as expected.

Accessibility spot checks:

- Keyboard open/close flow.
- Escape closes the panel and returns focus.
- Screen-reader labels and state are coherent for toggle controls.
- Resize-text and reflow behavior are improved for clipped text containers.

## Implementation Boundary

This is a single implementation slice: shared targeting config, public integration hooks, targeted behavior for lower-risk controls, layout relief, documentation, and widget state semantics. A later slice can add an admin selector editor and deeper contrast/animation targeting after the API has been validated on real sites.
