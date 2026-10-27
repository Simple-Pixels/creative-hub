# Creative Hub — setup

What this build covers (phase 1):

- A **Classes** content type, editable in Elementor, separate from the product page.
- Linking each class to one or more **Studio Sessions** WooCommerce products. Products in other collections (*Curated Kits*, *Kit Classes*, …) are sold as normal products and never unlock a class.
- **Gating**: a class page shows its full content only to customers who bought a linked product. Everyone else is sent to your **Access page** (or, if you don't set one, sees a built-in teaser).
- A **Creative Hub** area on the *My Account* **Dashboard** tab (`/my-account/`) where customers see their classes, retreat bookings and downloads without digging through emails. Also available as `[creative_hub]` for a standalone page.
- The **order confirmation screen** names the class(es) just bought and links straight to them and to the hub. No setup — it appears whenever an order contains a class-linked product. If the order contains a **Curated Kit**, it also shows the curated-kit image.
- An **image gallery** shortcode, `[ch_gallery]`: a square main image, thumbnails underneath, and a click-to-zoom lightbox. Curated Kits products get the curated-kit image added to the end (choose the file under **Classes → Settings → Curated kits**).
- A **downloadable freebies** shortcode, `[ch_freebies]`, that asks visitors to sign up or log in (in a popup) before they can download. See §12.
- **Retreat bookings** on the hub dashboard, with payment status, balance and a "Pay balance" link when you use *Deposits & Partial Payments for WooCommerce Pro*.

Past-retreat content and teacher fees are **not** in this build.

---

## 1. Install

1. Copy the `creative-hub` folder into `wp-content/plugins/` (or zip it and upload via **Plugins → Add New → Upload**).
2. **Plugins → Installed Plugins → activate "Creative Hub"**.
3. Go to **Settings → Permalinks** and click **Save Changes** once. (Activation already flushes the rewrite rules; this is just belt-and-braces so the `/classes/…` URLs resolve.)

On activation the plugin automatically:

- registers the **Classes** post type (`ch_class`) at `/classes/…`;
- adds **Classes** to Elementor's list of editable post types;
- creates the settings record with sensible defaults (access granted on *Processing* + *Completed*).

You do **not** need to register any CPT yourself — the plugin does it.

---

## 2. Let Elementor build class pages

The plugin adds `ch_class` to Elementor's editable types on activation. Confirm it:

**Elementor → Settings → General → Post Types** — make sure **Classes** is ticked. Save.

Now you have two ways to design class pages:

### Option A — Elementor Pro Theme Builder (recommended: one reusable layout)

1. **Templates → Theme Builder → Single Post → Add New**.
2. Editor type: **Single**. Design the layout (title, featured image, content area, sidebar, etc.).
3. **Display Conditions → Include → Classes**.
4. Publish. Every class now renders through this template for customers who have access.

### Option B — Free Elementor (design each class page individually)

Open a class and click **Edit with Elementor**. The theme header/footer stay; you lay out the body. Save one class as a **Template** and load it into new classes to keep them consistent.

Either way, the plugin handles the locked state — you only design the **unlocked** page.

---

## 3. The locked state — Access page

Create a normal WordPress page (e.g. `/access-page`) and add the shortcode:

```
[ch_access_notice]
```

It renders:

- **"It looks like you don't have access"**
- **"To get access, buy this class:"** followed by the buy button(s) for whichever class the visitor tried to open (online-only and with-kit shown separately)
- **"Already bought it? Log into your account to continue."** linking to `/my-account/`

Then set it under **Classes → Settings → Access page**. Visitors without access are redirected there, with `?ch_class=123` on the URL so the buy links match the class they wanted. You can lay the page out however you like in Elementor around that shortcode.

If a class isn't purchasable, the buy block is skipped and just the log-in line shows. If someone reaches the page already logged in and *does* have access, they get an "Open the class" button instead.

### Alternatives

- **No Access page set:** the plugin falls back to a built-in teaser template (`templates/teaser.php`) with editable heading/message under **Classes → Settings**. It sits in a 1550px-wide container (image and title side by side on wide screens, stacked on phones) and is labelled "Studio Sessions". The default heading is "This studio session is waiting for you"; if you've typed your own, edit it under **Classes → Settings → Teaser heading**. Override the whole template by copying it to `wp-content/themes/your-child-theme/creative-hub/teaser.php`.
- **Build the locked/unlocked states inside one Elementor layout:** untick **Auto-lock class pages**, then wrap protected parts in `[ch_locked] … [/ch_locked]` and the pitch in `[ch_teaser] … [/ch_teaser]`.

---

## 4. Create the products

They are ordinary WooCommerce products. What they do depends on which collection term they carry (checked in the `collection` taxonomy, product tags and product categories):

| Term | Behaviour |
|---|---|
| `curated-kits` | Normal product — nothing extra. |
| `kit-classes` | Normal product — nothing extra. |
| `studio-sessions` | Can be linked to a Class (step 5); buying it unlocks that class. |

- **Studio Session with downloads:** tick **Virtual** and **Downloadable** and add the PDFs / templates / cut files under *Downloadable files*. These appear in the customer's hub automatically.
- **Studio Session that ships something:** leave **Virtual unticked** so it needs shipping. Access unlocks at **Processing** (payment received), so the customer can start the class while the parcel is in the post.
- Selling the same class two ways = **two Studio Sessions products**. Link both to the class.

Set prices, tax and shipping classes as usual. If a linked product later loses the `studio-sessions` term, it stops unlocking the class straight away (and starts again if the term is added back).

### Curated kits and the gallery

Put `[ch_gallery]` where you want the gallery — in the product page template, or in the Class (Single) template in Elementor, using a Shortcode widget.

- **On a product page:** the product's featured image is the main slide, followed by its gallery images. Thumbnails run along underneath.
- **On a Class page:** the class's featured image first, then the images of its first linked product.
- **Elsewhere:** `[ch_gallery product_id="123"]`.
- **Curated kits:** if the product is in the **`curated-kits`** collection, the curated-kit image is added as the last slide.

Change that image any time under **Classes → Settings → Curated kits** (choose from the media library, or paste a URL; "Remove image" turns it off). The same image appears on the order confirmation screen whenever a curated kit is bought.

The gallery is a crossfading slider: arrows appear when you hover the main image (always visible on touch screens), a counter sits bottom-right, and you can also click a thumbnail, swipe, or use the arrow keys. The main image is shown whole (`contain`) in a square **1:1** frame with no border; thumbnails sit underneath and are cropped to fill (`cover`). Use `ratio="3/4"` for a portrait frame, `ratio="4/3"` for landscape, or `fit="cover"` to fill the main frame instead. Fine-tune with CSS variables on `.ch-gallery` (e.g. `--ch-gal-radius`, `--ch-gal-thumb`, `--ch-gal-thumb-fit`). It respects reduced-motion settings.

**Click the main image** to open a lightbox showing the full-size file. Use the arrows, swipe or ← → to move between images, Esc or ✕ to close, and click the picture (or the magnifier button) to zoom to full size — then drag to pan. The lightbox also works when there is only one image.

---

## 5. Create a class and link it

1. **Classes → Add New**.
2. Title, and set a **Featured image** (used on hub cards and the teaser).
3. **Excerpt** — one or two lines, shown on the teaser.
4. **Access — linked products** (right-hand box) — search and add every **Studio Sessions** product that should unlock this class. The "Studio Sessions check" underneath flags any linked product that isn't tagged `studio-sessions` — those won't unlock the class until they are.
5. Add the content (Gutenberg or **Edit with Elementor**): lesson steps, embedded video, etc.
6. Publish.

> A class with **no** linked Studio Sessions products is locked for everyone — the admin list shows a red warning in the *Access products* column.

**Video:** paste a Vimeo/YouTube/Bunny embed into the class content. Host private videos on Vimeo (domain-private) or Bunny Stream — don't upload them to the Media Library. (Signed per-view video URLs are a later phase.)

---

## 6. The Creative Hub (My Account → Dashboard)

Nothing to build. The plugin replaces the default **Dashboard** tab of *My Account* (`/my-account/`) with the hub: *My classes*, *My retreats*, *My downloads*, and a link to full order history. Point your menus / links at `/my-account/`.

If you'd rather also have it as a standalone page (e.g. `/creative-hub`), create a page with the `[creative_hub]` shortcode and select it under **Classes → Settings → Creative Hub page** — internal links then use that page instead. You can also compose your own layout with `[ch_my_classes]`, `[ch_my_retreats]`, `[ch_my_downloads]`.

> This replaces the Dashboard for the classic My Account page (the `[woocommerce_my_account]` shortcode — the default). If your My Account page is built with the newer WooCommerce **blocks**, put `[creative_hub]` on a standalone page instead and link to that.

### Retreats

*My retreats* is driven by your product taxonomy — every order line for a product in the **`collection` → `retreats`** term becomes a booking card. No class or extra setup needed. (Different taxonomy or term? Use the `ch_retreat_taxonomy` / `ch_retreat_term` filters.)

Each card shows the retreat's image and name, the booking (order) number and a status pill:

| Pill | Meaning |
|---|---|
| **Paid in full** | Nothing left to pay. Button: *View retreat* (the product page). |
| **Balance due** | A deposit is in but there's still money to pay. Shows *Paid so far*, *Balance* and *Next payment due* where known, and a **Pay balance** button. |
| **Awaiting payment** | Booked but the payment hasn't cleared yet (e.g. bank transfer on hold). |

Every card also has a *Booking details* link to the order page, where Deposits & Partial Payments shows the full payment schedule.

**How it works with Deposits & Partial Payments for WooCommerce Pro**

- Bookings in the *Partially Paid* status appear (they previously wouldn't — it isn't an access-granting status). Retreats count in the statuses *Processing, Completed, Partially Paid* and *On hold*; change that with the `ch_retreat_order_statuses` filter.
- **Pay balance** goes to the booking's payment URL, which the deposits plugin redirects to the next unpaid instalment.
- Amounts and the next-due date come from the deposits plugin's own order data (its `_awcdp_deposits_*` fields and instalment payment records). If a number can't be worked out it's simply left out; the status pill and Pay balance link still work.
- This was built from the plugin's published structure rather than a live deposit order, so **place one test retreat booking with a deposit** and check the card. If a figure is missing or wrong, the `ch_retreat_payment_summary` filter is the one place to adjust it.

---

## 7. Settings reference

**Classes → Settings** has two tabs. Each tab saves on its own without affecting the other.

**Curated kits tab**

| Setting | Notes |
|---|---|
| Kit image | The picture appended to curated-kit galleries and shown on the order confirmation screen. Choose from the media library, or paste a URL. Defaults to the Image-1.jpeg uploaded in September 2026. |
| Alt text | Optional. Blank uses the media library alt text. |

**General tab**

| Setting | Default | Notes |
|---|---|---|
| Grant access on these order statuses | Processing, Completed | Keep *Processing* ticked so buyers get in before a kit ships. Tick *On hold* only if you want bank-transfer customers to have access before you confirm payment. |
| Auto-lock class pages | On | Off = you control locking with `[ch_locked]` / `[ch_teaser]`. |
| Access page | — | Page holding `[ch_access_notice]`. No-access visitors are redirected here. Leave blank to use the built-in teaser. |
| Staff preview | On | Shop managers / editors can open any class without buying. |
| Buy button text | "Get this class" | |
| Teaser heading / message | — | Shown on the locked page. |
| Creative Hub page | — | Optional standalone page holding `[creative_hub]`. Leave blank to use the My Account Dashboard. |

---

## 8. Test before launch

1. As a logged-out visitor, open a class URL → you should land on the Access page with the right buy links and a log-in link.
2. As a logged-in customer who has **not** bought it → same Access page.
3. Buy a linked product with a test order, set it to **Processing** → the class page now shows full content, and the class + its downloads appear on the *My Account → Dashboard*.
4. Refund / cancel the order → access is withdrawn within ~15 minutes (cache), or immediately on the next page load after the status change.

---

## 9. Caching note (Kinsta)

The Access page uses a `?ch_class=…` query string. Kinsta's full-page cache normally varies by query string, so this is usually fine. If you ever see stale buy links there, add `/access-page` (or `access-page` / query strings) to **Kinsta → Tools → Cache → exclusions**, or ask Kinsta support to bypass cache for that path. Class pages themselves are handled server-side on every request and are safe.

## 10. Styling

Headings and body text inherit the site's Elementor kit (Manrope) — nothing to configure. The only plugin-specific stylesheet, `assets/css/creative-hub.css`, styles the cards, buttons and teaser, using the brand colours (`#f47c77`, `#f4bebc`, white, black). Buttons follow the given spec: `#f47c77` background, white text, Manrope 18px/400, shrink on hover.

Adjust anything via **Elementor → Site Settings → Custom CSS** targeting the `.ch-` classes, or by overriding the stylesheet in a child theme.

---

## 11. Accounts & guest checkout (important)

Access is tied to a **customer account**, because a guest order has no user to attach access to.

- **WooCommerce → Settings → Accounts & Privacy** — tick **"Allow customers to create an account during checkout"** (and consider **"…on the My account page"**). Ideally require an account for orders that contain a class.
- If someone still checks out as a guest, the order confirmation screen tells them to **create an account with the same email address**. When they do, WooCommerce automatically links that past order, and their class unlocks.
- A logged-out visitor opening a class they own is shown the Access page (or teaser) with a **"Log into your account"** link — never the raw `wp-login.php` screen.

## 12. Freebies (sign up to download)

Put `[ch_freebies]` on any page (an Elementor Shortcode widget is fine). It lists every **published `freebie`** that has a **featured image**: the image as a preview, with a Download button underneath. The featured image's original file is what gets downloaded.

Options: `[ch_freebies columns="4" count="12" orderby="date" order="DESC" ids="12,15,18"]`. Columns drop to 3 on tablets, 2 on larger phones and 1 on small phones. Style it with CSS variables on `.ch-freebies` (e.g. `--ch-fb-gap`).

**What a visitor sees**

- **Logged in:** Download starts straight away.
- **Logged out:** a popup with two tabs, *Create account* (email + password) and *Log in*. As soon as they're in, the download starts and the popup says so, with a "download it here" link in case a browser blocks it. After that, every other Download button on the page works without the popup.
- Creating an account makes a normal WooCommerce **customer** (so WooCommerce's "new account" email goes out and they can see the account in *My Account* and the Creative Hub). If the email already has an account, the popup moves them to the Log in tab with the email filled in.
- With JavaScript off, the Download link sends logged-out visitors to *My Account* to log in, then straight back to the download.

**Settings: Classes → Settings → Freebies**

| Setting | Notes |
|---|---|
| Popup heading / message | The wording at the top of the popup. |
| Email the freebie | Off by default. When on, each download also emails the freebie (attached, in your WooCommerce email style) to the customer. Files over 8MB aren't attached, and each person gets at most one email per freebie per hour. |

**Things to know**

- **The `freebie` post type must exist on the site.** The shortcode reads post type `freebie` (change it with the `ch_freebie_post_type` filter). If it isn't registered, editors see a short message where the grid would be; visitors see nothing.
- **Protecting the file.** The Download button points at `/?ch_freebie=ID`, which checks that the visitor is logged in before sending the file, and the original file's URL is never written into the page. The preview is the "medium_large" (768px) size (change with `ch_freebie_preview_size`). Two limits: if an image is no bigger than that, the preview *is* the original; and the file itself still sits in the Media Library, so anyone who knows or guesses its direct URL (for example by changing the number in a filename like `…FREEBIES-12.jpeg`) can open it without logging in. To use a different file for a freebie, filter `ch_freebie_attachment_id`.
- **Spam protection.** Sign-up has a hidden honeypot field and allows 8 attempts per visitor per 15 minutes; log-in locks out after 10 failed tries. The popup fetches a fresh security token each time it opens, so Kinsta's page cache can't serve an expired one.
- **Newsletter sign-up** isn't included. Accounts are created in WooCommerce only. If the client wants these emails added to Mailchimp, Klaviyo or similar, hook the `ch_freebie_downloaded` action (it passes the user ID and the freebie) or tell me which service.

## Troubleshooting

- **"It sent me to wp-login.php"** — fixed in 0.3.1. Update the plugin. Locked class pages now use the Access page, or the teaser, and their log-in links go to `/my-account/`.
- **Bought a class but it still shows as locked** — check the order status is one of the ticked statuses in **Classes → Settings** (card payments land on *Processing*; bank transfer stays *On hold* until you confirm it). Access refreshes within ~15 minutes or on the order's next status change.
- **Access page shows the wrong buy links** — it reads `?ch_class=` from the URL; make sure you didn't strip query strings in a redirect or cache rule (see §9).
- **A locked class page is covered by the open cart and can't be closed (logged out)** — fixed in 0.6.0. The built-in teaser was being printed before Elementor had loaded its scripts, so the header's cart never initialised. It is now loaded through the normal template step.
- **The gallery overlaps the content below it on a phone** — fixed in 0.6.1 (see the changelog). After deploying, clear your caching/minify plugin (e.g. WP Rocket) and Kinsta cache so phones pick up the new `creative-hub-gallery.js` and `.css`.
- **A retreat booking is missing from *My retreats*** — check the order's status is one of Processing, Completed, Partially Paid or On hold, and that the retreat product is in the `collection` → `retreats` term.

---

## Shortcodes

| Shortcode | Output |
|---|---|
| `[creative_hub]` | Full hub: my classes + my retreats + my downloads + order-history link |
| `[ch_my_classes]` | Grid of the visitor's accessible classes |
| `[ch_my_retreats]` | The visitor's retreat bookings (collection = `retreats`), with payment status, balance and a Pay balance link |
| `[ch_my_downloads]` | The visitor's downloadable files, grouped by class |
| `[ch_freebies]` | Grid of `freebie` posts with a Download button each; logged-out visitors get a sign-up / log-in popup first. Options: `columns`, `count`, `orderby`, `order`, `ids`. |
| `[ch_gallery]` | Image gallery for the current product or class (`product_id="123"` to target one; `fit="cover"` and `ratio="3/4"` optional; default frame is 1/1 square). Click the main image for a zoomable lightbox. Curated kits get the kit image added at the end. |
| `[ch_class_products]` | Buy options for the current class (`class_id="123"` to target another) |
| `[ch_access_notice]` | "You don't have access" notice for the Access page — buy links + log-in link (reads `?ch_class=123`) |
| `[ch_locked]…[/ch_locked]` | Inner content shown only to customers with access |
| `[ch_teaser]…[/ch_teaser]` | Inner content shown only to visitors without access |

## Developer hooks

| Hook | Type | Purpose |
|---|---|---|
| `ch_access_statuses` | filter | Order statuses that grant access |
| `ch_class_product_ids` | filter | Products that unlock a class |
| `ch_studio_session_term` | filter | Product term that makes a product able to unlock classes (default `studio-sessions`) |
| `ch_studio_session_taxonomies` | filter | Taxonomies checked for that term (default `collection`, `product_tag`, `product_cat`) |
| `ch_user_can_access_class` | filter | Final yes/no access decision |
| `ch_user_class_ids` | filter | The list of a user's accessible class IDs |
| `ch_retreat_taxonomy` / `ch_retreat_term` | filter | Which product taxonomy + term counts as a retreat (default `collection` / `retreats`) |
| `ch_retreat_order_statuses` | filter | Order statuses that count as a retreat booking (default access statuses + `partially-paid` + `on-hold`) |
| `ch_retreat_booking` | filter | A retreat booking (`product`, `order`, `payment`) before it's shown |
| `ch_retreat_payment_summary` | filter | A booking's payment summary — state, label, paid, balance, next due, pay URL |
| `ch_curated_kit_term` / `ch_curated_kit_taxonomies` | filter | Which term makes a product a curated kit (default `curated-kits`, in `collection`) |
| `ch_curated_kit_image` | filter | The curated-kit image description |
| `ch_gallery_items` | filter | The images `[ch_gallery]` is about to show |
| `ch_freebie_post_type` | filter | The post type `[ch_freebies]` lists (default `freebie`) |
| `ch_freebie_preview_size` | filter | Image size shown as the preview (default `medium_large`) |
| `ch_freebie_attachment_id` | filter | The attachment a freebie downloads (default: its featured image) |
| `ch_freebie_email_max_bytes` | filter | Largest file attached to the freebie email (default 8MB) |
| `ch_freebie_downloaded` | action | After a freebie is sent: user ID, freebie post ID, attachment ID |
| `ch_no_access_redirect` | filter | The Access-page URL a no-access visitor is redirected to |
| `ch_locked_logged_out_redirect` | filter | Where a logged-out visitor is sent when no Access page is set |
