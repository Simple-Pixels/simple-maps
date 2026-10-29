# CLAUDE.md

Working rules for **Simple Maps**. Read [README.md](README.md) first: it explains what the plugin does, how it fits together, and its gotchas. **Keep this file and the README updated** whenever behaviour, settings, hooks, files or requirements change.

## Project

- A Google Map pinning every post of a chosen post type from an ACF Google Map field (`[simple_map]`).
- This is a **reusable component**, installed on several client sites. Keep it generic: no client names, copy, colours, URLs or email addresses in code. Anything site-specific becomes a setting, a shortcode attribute or a filter, with a sensible default.
- Don't break existing installs. Keep shortcode names, attributes, option keys and hooks backwards compatible, or migrate them on upgrade.
- The **repository root is the plugin folder** (deploys to `wp-content/plugins/simple-maps/`).

## Coding standards

- Follow the **WordPress Coding Standards** (PHP, JS, CSS): tabs, Yoda conditions, `array()` syntax, snake_case functions and variables, `Capitalised_Underscore` class names, `class-*.php` file names.
- Target **PHP 8.1+** and **WordPress 6.4+** for new code. Use type declarations. If that raises the plugin's real minimum, update the plugin header and README requirements.
- Write clean, readable, well-structured code that is easy to update. Keep comments short and useful.
- Escape output late (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitise input early, and check nonces and capabilities on every form, AJAX and REST handler.
- Make all user-facing strings translatable with the `sp-map` text domain.
- Prefix everything global (functions, classes, constants, options, meta keys, hooks, handles, CSS classes) with this plugin's prefix: `sp_map_` / `SP_Map_` / `sp-map`.
- **Keep external packages to a minimum.** Prefer WordPress core APIs (and WooCommerce / ACF / Elementor APIs where the plugin already uses them). Add a dependency only when it clearly saves a lot of work, and record it in the README's Requirements.
- Register assets and enqueue them only where they're used.
- Existing code uses spaces and short array syntax. Match the file for small changes; use the standards above for new files and refactors.

## Versioning and docs

- Bump the plugin header and the hard-coded `wp_register_script()` versions together.
- Update the README in the same change: Usage, Hooks, File structure, Gotchas and Changelog as relevant.

## Git workflow

- **Never push directly to `main`.** Always work on a new branch, push it, and open a PR into `main`.
- **Do not add "Co-Authored-By: Claude" or any Claude/AI attribution** to commit messages or PR descriptions.
- Branch names: `feature/<short-description>`, `fix/<short-description>`.

## Environment notes

- PHP isn't installed on the local dev machine, so code can't be linted or run locally. Test on a staging site.
- Assume full-page caching (Kinsta or similar). Per-visitor logic must not depend on PHP running for every page view; use an uncached REST/AJAX endpoint or client-side JS.
