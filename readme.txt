=== Creative Hub ===
Contributors: scrappingclearly
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.3
Stable tag: 0.4.0
License: GPLv2 or later

Post-purchase members area for online classes, built on WooCommerce.

== Description ==

Phase 1:

* "Classes" post type, editable in Elementor, separate from the product page.
* Link each class to one or more WooCommerce products (online-only and with-kit).
* Class pages are gated: full content for buyers, a teaser with buy buttons for everyone else.
* A "Creative Hub" area (My Account tab + optional standalone page) showing the
  customer's classes and downloads.

Not included yet: retreat bookings, retreat content, teacher fees.

== Installation ==

See SETUP.md in the plugin folder.

1. Upload the `creative-hub` folder to `/wp-content/plugins/`.
2. Activate through the 'Plugins' menu.
3. Visit Settings > Permalinks and save once.
4. Configure under Classes > Settings.

== Changelog ==

= 0.4.0 =
* The Creative Hub now lives on the My Account "Dashboard" tab (the separate
  "Creative Hub" tab is removed).
* New: "My retreats" section + [ch_my_retreats] shortcode — shows purchased
  products in the "retreats" collection as cards linking to the product page.
* Class cards drop the class-type label and always show the featured image.

= 0.3.1 =
* Locked class pages no longer redirect to wp-login.php. With no Access page set,
  everyone sees the teaser; its log-in link now points to the WooCommerce account page.
* Order confirmation shows guest buyers how to get access (create an account with
  the same email).

= 0.3.0 =
* Order confirmation screen now names the class(es) bought and links to them + the Creative Hub.

= 0.2.0 =
* Class slug changed from /creative-hub/classes/ to /classes/ (auto-flushes on upgrade).
* New: Access page redirect + [ch_access_notice] shortcode (buy links + log-in link).

= 0.1.0 =
* Initial build: Classes CPT, product links, purchase gating, hub, My Account tab.
