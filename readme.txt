=== Creative Hub ===
Contributors: scrappingclearly
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.3
Stable tag: 0.6.2
License: GPLv2 or later

Post-purchase members area for online classes, built on WooCommerce.

== Description ==

Phase 1:

* "Classes" post type, editable in Elementor, separate from the product page.
* Link each class to one or more Studio Sessions WooCommerce products.
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

= 0.6.2 =
* `[ch_gallery]`: default frame is now square (1:1). `ratio="3/4"` still gives portrait.
* `[ch_gallery]`: removed the thin outline around the main image and thumbnails. It
  was painted underneath the picture, so it only showed in the gaps and looked broken.

= 0.6.1 =
* Fixed: on phones the gallery could sit on top of the content below it (title,
  price, Add to Cart) inside Elementor layouts. The browser sized the gallery's
  wrapper without knowing its width, so the wrapper collapsed. The gallery script
  now keeps a pixel min-height in sync with the gallery's real height, and the
  gallery stylesheet loads in the head on product and class pages.
* `[ch_gallery]`: default frame is now portrait 3:4, thumbnails sit underneath
  (cropped to fill, `cover`) instead of on the right.
* `[ch_gallery]`: clicking the main image opens a lightbox with the full-size
  image — prev/next, Esc to close, swipe, focus kept inside, and click (or the
  zoom button) to zoom to full size with drag-to-pan. Also works for a single image.

= 0.6.0 =
* Fixed: a locked class page stayed covered by the open mini-cart for logged-out
  visitors. The teaser is now loaded through `template_include`, after Elementor
  has initialised, instead of being printed from `template_redirect`.
* Teaser is 1550px wide, says "Studio Sessions" in its header, and the default
  heading now reads "This studio session is waiting for you".
* New: `[ch_gallery]` image gallery (featured image, then the product gallery;
  thumbnails on the right, hover arrows, counter bottom-right, images shown whole
  with `contain`). Curated Kits products get the curated-kit image appended at the end.
* New: Classes → Settings → "Curated kits" tab to choose that image from the media library.
* The curated-kit image also appears on the order confirmation screen.
* My retreats now shows each booking with payment status, paid and balance
  amounts, next payment date and a "Pay balance" link, using
  Deposits & Partial Payments for WooCommerce Pro. Partially-paid bookings now appear.

= 0.5.0 =
* Removed the Class Types taxonomy (Online Class / Kit Class / Free Resource),
  including the "Free Resource = open to any logged-in user" rule.
* Only products tagged `studio-sessions` unlock classes. Curated Kits and Kit
  Classes products behave as normal WooCommerce products.
* Class editor flags linked products that aren't Studio Sessions.

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
