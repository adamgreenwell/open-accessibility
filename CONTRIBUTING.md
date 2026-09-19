# Contributing to Open Accessibility

Thanks for helping make the web more accessible. This plugin is deliberately dependency-light:
there is no build step, no bundler, and no JavaScript framework. `assets/js/*.js` are hand-written
files loaded as-is. Please keep it that way — a pull request that introduces a build step needs to
justify it first in an issue.

- [Getting set up](#getting-set-up)
- [Running the tests](#running-the-tests)
- [Adding a new option key](#adding-a-new-option-key)
- [Adding a widget feature](#adding-a-widget-feature)
- [Releasing](#releasing)

## Getting set up

The development environment is a WordPress install running under Docker, using the
`docker-compose.yml` at the repository root:

```bash
docker compose up -d
```

The site is then at <http://localhost:9080>. The plugin lives at
`wp-content/plugins/open-accessibility/` and is normally already active.

## Running the tests

The suite runs against a **real WordPress and a real database**, not stubs. That matters for the
option layer in particular: `get_option()`, `update_option()` and `register_setting()`'s sanitize
callback are frequently the code under test, and a stubbed environment would not reproduce their
merge and serialisation behaviour.

One-time setup (installs WordPress core and the WordPress test library, and expects the Docker
database to be running):

```bash
composer install
composer test:setup
```

Then:

```bash
composer test
```

Credentials and paths come from environment variables, so the setup is non-interactive and can be
retargeted for CI. See the header of `tests/install-wp-tests.sh` for the full list.

**Every change should come with tests.** Run `composer check` before opening a pull request; it
validates version parity, PHP syntax, JavaScript syntax, whitespace, and builds the release
package.

## Adding a new option key

Option defaults are declared in exactly one place:
`Open_Accessibility_Utils::get_default_options()`. The activation seeder derives from it, so a key
can never be seeded without being declared, or declared without being seeded.

Adding a key requires **four** edits. Omitting any one of them fails silently, in a way that looks
like a different bug, so please work through the list rather than stopping at the first two:

| # | Where | What happens if you skip it |
|---|---|---|
| 1 | `includes/class-open-accessibility-utils.php` → `get_default_options()` | `checkbox_field_callback` renders the toggle **unchecked** on first load, whatever the default says |
| 2 | `admin/class-open-accessibility-admin.php` → the `$features` map, or a new `add_settings_field()` | No control appears in the settings at all |
| 3 | `admin/class-open-accessibility-admin.php` → `sanitize_options()` | **The key is discarded on every save** |
| 4 | `public/class-open-accessibility-public.php` → `get_frontend_options()` | The widget's JavaScript never receives the value |

### Why edit 3 matters most

`sanitize_options()` builds its return value from an empty array and hands back a **full
replacement** for the stored option. It does not merge with what is already saved. Anything it
does not explicitly write is gone:

- checkbox keys must be added to the `$checkboxes` list, which writes every listed key as `1` or
  `0` — so an absent key means *unchecked*, never "leave unchanged";
- every other type needs its own `isset( $input[...] )` branch.

### Reading options

Read through `Open_Accessibility_Utils::get_options()`, which merges the stored option over the
defaults. Do not call `get_option( 'open_accessibility_options' )` directly and invent a fallback —
that is how the defaults drifted apart before, and it is what
`tests/includes/test-option-defaults.php` exists to prevent.

The only deliberate exception is `uninstall.php`, which reads the raw option because it should
mirror what was actually saved rather than values fabricated by the defaults.

### Naming and types

- Prefix new keys (`enable_*`, `profile_*`, …). The `$features` map key doubles as the option key,
  the field id, and the input id, so an unprefixed name can collide.
- `select_field_callback` requires `$args['options']` and does **not** merge defaults. Give it an
  explicit `'default'`, or seed the key.
- Sanitize by type: `sanitize_text_field()`, `esc_url_raw()`, `sanitize_hex_color()`, or an
  `in_array()` whitelist for enums.

### Tests will tell you

`tests/includes/test-option-defaults.php` parses the plugin's own source to find which keys are
consumed, and asserts that every one has a declared default, that activation seeds exactly the
declared set, and that no declared default is unread. If you add a key and forget edit 1 or 4, that
suite fails with the key named.

## Adding a widget feature

The full path for a frontend control:

1. **State** — add the field to `DEFAULT_ACCESSIBILITY_STATE` and to
   `normalizeAccessibilityState()` in `assets/js/open-accessibility-public.js`, validating it
   against a whitelist constant.
2. **Apply** — handle it in `applyState()` so it survives reload, and restore it on reset.
3. **Targeting** — apply visual changes through the target resolver rather than to `body`. A CSS
   `filter` or `transform` on an ancestor creates a containing block for `position: fixed`
   descendants, which has previously made the widget unreachable. This is not theoretical: 1.1.0
   and 1.4.01 both shipped fixes for that class of bug.
4. **Markup** — add the control to `public/partials/widget-template.php`, gated on its option.
5. **Strings** — route labels through the filtered strings array from `get_strings()`, not a
   direct `esc_html_e()`, so the `open_accessibility_strings` filter reaches them.
6. **Admin** — the four edits above.
7. **CSS** — verify the control is distinguishable in all four contrast modes, and that its active
   state does not rely on colour alone.

## Releasing

Releases are built and published by the maintainer. Version numbers appear in three places —
the plugin header, the `OPEN_ACCESSIBILITY_VERSION` constant, and `README.txt`'s stable tag — and
`bin/check-release.sh` enforces that they agree.

`composer build` produces a local zip. Publishing to WordPress.org is a separate, manual,
maintainer-only step.
