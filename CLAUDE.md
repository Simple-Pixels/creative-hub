# CLAUDE.md

Working rules for **Creative Hub**. Read [README.md](README.md) first: it explains what the plugin does, how it fits together, and its gotchas. **Keep this file and the README updated** whenever behaviour, settings, hooks, files or requirements change.

## Project

- Post-purchase members area for Scrapping Clearly's online classes on WooCommerce.
- This is a **site-specific** plugin for one client site. Client-specific behaviour is fine here, but keep anything that could be reused (e.g. a gallery, map or form helper) self-contained so it can move into a reusable plugin later.
- Settings and copy the client may want to change belong in a settings page or a filter, not hard-coded.
- The **repository root is the plugin folder** (deploys to `wp-content/plugins/creative-hub/`).

## Coding standards

- Follow the **WordPress Coding Standards** (PHP, JS, CSS): tabs, Yoda conditions, `array()` syntax, snake_case functions and variables, `Capitalised_Underscore` class names, `class-*.php` file names.
- Target **PHP 8.1+** and **WordPress 6.4+** for new code. Use type declarations. If that raises the plugin's real minimum, update the plugin header and README requirements.
- Write clean, readable, well-structured code that is easy to update. Keep comments short and useful.
- Escape output late (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`), sanitise input early, and check nonces and capabilities on every form, AJAX and REST handler.
- Make all user-facing strings translatable with the `creative-hub` text domain.
- Prefix everything global (functions, classes, constants, options, meta keys, hooks, handles, CSS classes) with this plugin's prefix: `ch_` / `CH_` / `ch-`.
- **Keep external packages to a minimum.** Prefer WordPress core APIs (and WooCommerce / ACF / Elementor APIs where the plugin already uses them). Add a dependency only when it clearly saves a lot of work, and record it in the README's Requirements.
- Register assets and enqueue them only where they're used.
- Admin-facing changes must also be reflected in `SETUP.md` (the client guide). Developer-facing changes go in `README.md`.
- WooCommerce is required. Keep the plugin HPOS compatible: use `wc_get_order()` and order methods, never direct `postmeta` queries for orders.

## Versioning and docs

- Bump `CH_VERSION`, the plugin header and the `readme.txt` "Stable tag" together, and add a `readme.txt` changelog entry. Changing `CH_VERSION` triggers the upgrade routine (rewrite flush) on the next admin page load.
- Update the README in the same change: Usage, Hooks, File structure, Gotchas and Changelog as relevant.

## Git workflow

- **Never push directly to `main`.** Always work on a new branch, push it, and open a PR into `main`.
- **Do not add "Co-Authored-By: Claude" or any Claude/AI attribution** to commit messages or PR descriptions.
- Branch names: `feature/<short-description>`, `fix/<short-description>`.

## Environment notes

- PHP isn't installed on the local dev machine, so code can't be linted or run locally. Test on a staging site.
- Assume full-page caching (Kinsta or similar). Per-visitor logic must not depend on PHP running for every page view; use an uncached REST/AJAX endpoint or client-side JS.
