# Creative Hub

> **Site-specific** · Post-purchase members area for Scrapping Clearly's online classes, built on WooCommerce: gated Class pages, a customer hub on My Account, retreat bookings, galleries, freebies and class file downloads.

| | |
| --- | --- |
| Type | Site-specific (Scrapping Clearly) |
| Version | 0.8.4 (`CH_VERSION` + plugin header + `readme.txt` stable tag) |
| Main file | `creative-hub.php` |
| Text domain | `creative-hub` |
| Prefix | `CH_` (classes, constants), `ch_` (functions, options, hooks, shortcodes, query vars), `_ch_` (meta), `ch-` (handles, CSS) |
| Repository | `Simple-Pixels/creative-hub` |

**Other docs in this repo**

- [SETUP.md](SETUP.md): the full admin/client guide (how to set up classes, products, pages and settings, troubleshooting). Read it before changing behaviour.
- [readme.txt](readme.txt): WordPress-format readme with the **changelog**.

## What it does

- **Classes** post type (`ch_class`, `/classes/<slug>/`), editable in Elementor, separate from product pages.
- **Purchase gating**: a class unlocks for customers who bought one of its linked **Studio Sessions** products (a product with the `studio-sessions` term). Everyone else is redirected to an Access page or sees a built-in teaser.
- **Creative Hub** on the My Account **Dashboard** tab: *My classes*, *My retreats*, *My downloads*. Also available as `[creative_hub]` for a standalone page.
- **Order confirmation** names the class(es) bought and links to them. Curated Kit orders show the curated-kit image.
- **Retreat bookings** from products in `collection → retreats`, with payment status, balance and a Pay balance link via *Deposits & Partial Payments for WooCommerce Pro*.
- **`[ch_gallery]`**: product/class image gallery with a zoomable lightbox.
- **`[ch_freebies]`**: free downloads behind a sign-up/log-in popup that creates WooCommerce customers.
- **`[ch_class_files]`**: a Download button on Class pages that lists the class's files with "Download all" as a zip, served through access checks.

## Requirements

| Requirement | Version / notes |
| --- | --- |
| WordPress | 6.2+ |
| PHP | 7.4+ (`zip` extension for "Download all") |
| Required plugins | **WooCommerce** 7.0+ (tested to 9.3, HPOS compatible). Without it the plugin shows an admin error and loads nothing. |
| Optional plugins | **Elementor / Elementor Pro** (class templates), **ACF** (`downloadable_files` repeater on products; there's a post-meta fallback), **Deposits & Partial Payments for WooCommerce Pro** (retreat balances), a plugin registering the **`freebie`** post type, a `collection` product taxonomy. |
| External services | None. |

## Installation

1. Deploy the repository root to `wp-content/plugins/creative-hub/`.
2. Activate **Creative Hub**. Activation registers the post type and My Account endpoint, flushes rewrite rules and creates `ch_settings` with defaults.
3. **Elementor → Settings → General → Post Types**: confirm **Classes** is ticked.
4. Follow [SETUP.md](SETUP.md) for products, the Access page, class templates and settings.

## Configuration

**Classes → Settings**, tabs **General**, **Curated kits** and **Freebies**. Everything is stored in the `ch_settings` option array.

| Setting | Default |
| --- | --- |
| Grant access on these order statuses | Processing, Completed |
| Auto-lock class pages | On |
| Access page | none (built-in teaser) |
| Staff preview | On |
| Buy button text | "Get this class" |
| Teaser heading / message | "This studio session is waiting for you" |
| Creative Hub page | none (My Account Dashboard) |
| Curated kit image + alt text | set in admin |
| Freebie popup heading / message, email the freebie | email off |

Full descriptions: [SETUP.md §7](SETUP.md#7-settings-reference) and §12.

## Usage

| Shortcode | Output |
| --- | --- |
| `[creative_hub]` | Full hub: classes, retreats, downloads, order history link |
| `[ch_my_classes]` / `[ch_my_retreats]` / `[ch_my_downloads]` | Individual hub sections |
| `[ch_class_files label="" class_id=""]` | Download button + file popup for a class |
| `[ch_freebies columns="" count="" orderby="" order="" ids=""]` | Freebie grid with sign-up popup |
| `[ch_gallery product_id="" ratio="1/1" fit="contain"]` | Gallery + lightbox |
| `[ch_class_products class_id=""]` | Buy options for a class |
| `[ch_access_notice]` | "You don't have access" block for the Access page (reads `?ch_class=`) |
| `[ch_locked]…[/ch_locked]` / `[ch_teaser]…[/ch_teaser]` | Manual gating when Auto-lock is off |

## How it works

### Data model

| Thing | Stored as |
| --- | --- |
| Class | `ch_class` post type at `/classes/<slug>/` |
| Class → unlocking products | `_ch_access_product_ids` post meta on the class |
| Product → classes (reverse index) | `ch_product_class_index` option |
| Settings | `ch_settings` option array; `ch_version` option for the upgrade routine |
| Paid-for products per user | Transient `ch_purchased_<user_id>`, 15 minutes |
| Rate limits / freebie email throttle | Short-lived transients |
| Relied on, not created | `studio-sessions` / `curated-kits` / `retreats` terms (in `collection`, `product_tag` or `product_cat`), the `freebie` post type, the ACF `downloadable_files` repeater (`class_file` sub-field) on products, Deposits plugin `_awcdp_deposits_*` order data |

### Request flow

Opening a class page:

1. `CH_Entitlements` works out which products the user has paid for (orders in the access statuses) and caches the list. The cache is cleared on any `woocommerce_order_status_changed` for that customer.
2. Only products that **currently** carry the `studio-sessions` term unlock classes.
3. `CH_Access_Control` gates single `ch_class` views: entitled users (and staff, with Staff preview on) get the normal Elementor/theme template. Everyone else is redirected to the Access page (`?ch_class=<id>`), or gets `templates/teaser.php` through `template_include`.

Upgrades: on `admin_init`, if `ch_version` differs from `CH_VERSION`, the plugin re-registers rewrites, flushes them, runs any one-off setting migrations, and stores the new version.

### Endpoints

| Endpoint | Purpose / checks |
| --- | --- |
| `/?ch_freebie=<id>` | Freebie download. Logged-in users only. |
| `/?ch_class_file=…` | Single class file. User can access the class, and the file belongs to it. |
| `/?ch_class_zip=…` | All class files as a zip built on the fly. Same checks. |
| `admin-ajax.php` `ch_freebie_state`, `ch_freebie_register`, `ch_freebie_login` (also `nopriv`) | Freebie popup. Fresh nonce per popup open, honeypot, 8 sign-ups per 15 min per visitor, log-in lockout after 10 failures. |
| My Account Dashboard | The hub replaces the default dashboard template (`woocommerce_locate_template`). |

Real file URLs are never printed. Files on a CDN/bucket (e.g. WP Offload Media) are handed off after the check.

### Front end

- `CH_Assets` loads `creative-hub.css` (cards, buttons, teaser). Gallery CSS loads in the head on product and class pages; gallery, freebies and class-files JS load with their shortcodes.
- Typography comes from the site's Elementor kit (Manrope); brand colours `#f47c77`, `#f4bebc`.
- Class pages are rendered server-side per request, so they're safe with page caching. The Access page varies by `?ch_class=`.

## File structure

```
creative-hub.php                  Bootstrap: header, constants, HPOS declaration, requires/init, upgrade routine, activation
uninstall.php                     Deletes ch_settings, ch_product_class_index and ch_purchased_* transients
SETUP.md                          Admin / client setup guide
readme.txt                        WordPress readme + changelog
includes/
  helpers.php                     Settings access, class ↔ product links, studio-session checks
  class-ch-post-types.php         ch_class post type + admin columns
  class-ch-product-link.php       Access products box on the class editor
  class-ch-entitlements.php       Paid-for products per user, cached
  class-ch-access-control.php     Gating, Access page redirect, teaser
  class-ch-shortcodes.php         Hub, access and gating shortcodes
  class-ch-account.php            Hub on My Account Dashboard
  class-ch-order.php              Order confirmation additions
  class-ch-retreats.php           Retreat bookings + deposit payment summary
  class-ch-gallery.php            [ch_gallery]
  class-ch-freebies.php           [ch_freebies], sign-up/log-in AJAX, freebie download + email
  class-ch-class-files.php        [ch_class_files], file and zip downloads
  class-ch-elementor.php          Adds ch_class to Elementor's editable post types
  class-ch-settings.php           Classes → Settings
  class-ch-assets.php             Front-end stylesheet loading
templates/
  teaser.php                      Locked-class teaser (theme override: {theme}/creative-hub/teaser.php)
  myaccount/dashboard.php         Hub on the My Account Dashboard
assets/css/                       creative-hub, gallery, freebies, class-files
assets/js/                        gallery, freebies, class-files, admin-settings
```

## Hooks

The full list (about 30 filters and actions) is in [SETUP.md → Developer hooks](SETUP.md#developer-hooks). The most used:

| Hook | Type | Purpose |
| --- | --- | --- |
| `ch_access_statuses` | filter | Order statuses that grant access. |
| `ch_user_can_access_class` | filter | Final yes/no access decision. |
| `ch_studio_session_term` / `ch_studio_session_taxonomies` | filter | What makes a product able to unlock classes. |
| `ch_retreat_payment_summary` | filter | Retreat card figures (adjust here if the deposits plugin data differs). |
| `ch_freebie_downloaded`, `ch_class_file_downloaded`, `ch_class_zip_downloaded` | action | After a download (e.g. newsletter sync, logging). |
| `ch_account_downloads` | filter | Class files added to My Account → Downloads (WooCommerce downloads format). |

## Gotchas

- **Access needs a customer account.** Guest orders have no user to attach access to. Guests must create an account with the same email, and WooCommerce then links the order.
- **Access is cached for up to 15 minutes** per user. Status changes clear it, but other changes (e.g. editing a product's terms) can take up to 15 minutes to show.
- **Only `studio-sessions` products unlock.** Removing the term from a product immediately stops it unlocking classes. Curated Kits and Kit Classes are normal products.
- **Retreat balances were built from the deposits plugin's published data structure**, not a live order. Check figures on a real deposit booking after updating that plugin; `ch_retreat_payment_summary` is the one place to fix them.
- **Media Library files are still public.** Downloads are gated, but anyone who knows or guesses a direct upload URL can open it.
- **Class files are injected into WooCommerce's downloads list** through `woocommerce_customer_available_downloads` (so My Account → Downloads and `[ch_my_downloads]` both show them). Entries have no order (`order_id` 0), unlimited downloads and no expiry; anything else reading that list sees them too.
- **The `freebie` post type isn't registered here.** If it's missing, `[ch_freebies]` shows editors a notice and visitors nothing.
- **Block-based My Account** isn't supported for the Dashboard hub. Use `[creative_hub]` on a page instead.
- **Kinsta caching.** The Access page uses `?ch_class=`. If buy links look stale, exclude that page from cache. After CSS/JS changes, clear WP Rocket and Kinsta caches.
- **Three places hold the version**: `CH_VERSION`, the plugin header and `readme.txt` "Stable tag". Changing `CH_VERSION` also triggers the upgrade routine (rewrite flush) on the next admin page load.
- **`load_plugin_textdomain()` points at `/languages`**, which doesn't exist yet.
- The plugin header's author is "Scrapping Clearly" (the client), not Simple Pixels.

## Development

- Coding standards, git workflow and environment notes: [CLAUDE.md](CLAUDE.md).
- Versioning: bump `CH_VERSION`, the plugin header and `readme.txt` "Stable tag" together, and add a changelog entry to `readme.txt`. Update [SETUP.md](SETUP.md) when admin-facing behaviour changes.
- Testing: follow [SETUP.md §8 "Test before launch"](SETUP.md#8-test-before-launch) on staging (logged out, logged in without purchase, test order to Processing, refund).

## Changelog

See [readme.txt](readme.txt). Latest: **0.8.4**, class files also appear in My Account → Downloads.
