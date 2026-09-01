# Creative Hub — setup

What this build covers (phase 1):

- A **Classes** content type, editable in Elementor, separate from the product page.
- Linking each class to one or more **WooCommerce products** (e.g. an "online only" product and a "with kit" product).
- **Gating**: a class page shows its full content only to customers who bought a linked product. Everyone else is sent to your **Access page** (or, if you don't set one, sees a built-in teaser).
- A **Creative Hub** area on the *My Account* **Dashboard** tab (`/my-account/`) where customers see their classes, retreat bookings and downloads without digging through emails. Also available as `[creative_hub]` for a standalone page.
- The **order confirmation screen** names the class(es) just bought and links straight to them and to the hub. No setup — it appears whenever an order contains a class-linked product.

Retreats, retreat content and teacher fees are **not** in this build.

---

## 1. Install

1. Copy the `creative-hub` folder into `wp-content/plugins/` (or zip it and upload via **Plugins → Add New → Upload**).
2. **Plugins → Installed Plugins → activate "Creative Hub"**.
3. Go to **Settings → Permalinks** and click **Save Changes** once. (Activation already flushes the rewrite rules; this is just belt-and-braces so the new `/creative-hub/classes/…` URLs and the My Account tab resolve.)

On activation the plugin automatically:

- registers the **Classes** post type (`ch_class`) at `/classes/…` and a **Class Types** taxonomy with three terms: *Online Class*, *Kit Class*, *Free Resource*;
- registers the `creative-hub` My Account endpoint;
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
3. **Display Conditions → Include → Classes** (optionally narrow to a Class Type).
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

- **No Access page set:** the plugin falls back to a built-in teaser template (`templates/teaser.php`) with editable heading/message under **Classes → Settings**. Override it by copying to `wp-content/themes/your-child-theme/creative-hub/teaser.php`.
- **Build the locked/unlocked states inside one Elementor layout:** untick **Auto-lock class pages**, then wrap protected parts in `[ch_locked] … [/ch_locked]` and the pitch in `[ch_teaser] … [/ch_teaser]`.

---

## 4. Create the products

Nothing special — they are ordinary WooCommerce products. The class link is what matters.

- **Online class with downloads:** Product → tick **Virtual** and **Downloadable** → add the PDFs / templates / cut files under *Downloadable files*. These appear in the customer's hub automatically.
- **Online class with a posted kit:** Product → leave **Virtual unticked** (so it needs shipping) → set weight/dimensions and stock. You can still attach downloadable files to the same product. Access unlocks at **Processing** (payment received), so the customer can start the class immediately while the kit is in the post.
- Selling the same class both ways = **two products**. Link both to the class (step 5).

Set prices, tax and shipping classes as usual.

---

## 5. Create a class and link it

1. **Classes → Add New**.
2. Title, and set a **Featured image** (used on hub cards and the teaser).
3. **Excerpt** — one or two lines, shown on the teaser.
4. **Class Types** — pick *Online Class*, *Kit Class*, or *Free Resource*.
   *Free Resource* classes are open to any logged-in customer with no purchase.
5. **Access — linked products** (right-hand box) — search and add every product that should unlock this class. The "Fulfilment check" underneath shows which linked products ship a kit vs. are digital only, so you can sanity-check.
6. Add the content (Gutenberg or **Edit with Elementor**): lesson steps, embedded video, etc.
7. Publish.

> A class with **no** linked products is locked for everyone — the admin list shows a red warning in the *Access products* column.

**Video:** paste a Vimeo/YouTube/Bunny embed into the class content. Host private videos on Vimeo (domain-private) or Bunny Stream — don't upload them to the Media Library. (Signed per-view video URLs are a later phase.)

---

## 6. The Creative Hub (My Account → Dashboard)

Nothing to build. The plugin replaces the default **Dashboard** tab of *My Account* (`/my-account/`) with the hub: *My classes*, *My retreats*, *My downloads*, and a link to full order history. Point your menus / links at `/my-account/`.

If you'd rather also have it as a standalone page (e.g. `/creative-hub`), create a page with the `[creative_hub]` shortcode and select it under **Classes → Settings → Creative Hub page** — internal links then use that page instead. You can also compose your own layout with `[ch_my_classes]`, `[ch_my_retreats]`, `[ch_my_downloads]`.

> This replaces the Dashboard for the classic My Account page (the `[woocommerce_my_account]` shortcode — the default). If your My Account page is built with the newer WooCommerce **blocks**, put `[creative_hub]` on a standalone page instead and link to that.

### Retreats

Retreat cards are driven entirely by your product taxonomy — any product the customer has bought that sits in the **`collection` → `retreats`** term shows as a card linking to the product page. No class or extra setup needed. (Different taxonomy or term? Use the `ch_retreat_taxonomy` / `ch_retreat_term` filters.)

---

## 7. Settings reference

**Classes → Settings**

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

## Troubleshooting

- **"It sent me to wp-login.php"** — fixed in 0.3.1. Update the plugin. Locked class pages now use the Access page, or the teaser, and their log-in links go to `/my-account/`.
- **Bought a class but it still shows as locked** — check the order status is one of the ticked statuses in **Classes → Settings** (card payments land on *Processing*; bank transfer stays *On hold* until you confirm it). Access refreshes within ~15 minutes or on the order's next status change.
- **Access page shows the wrong buy links** — it reads `?ch_class=` from the URL; make sure you didn't strip query strings in a redirect or cache rule (see §9).

---

## Shortcodes

| Shortcode | Output |
|---|---|
| `[creative_hub]` | Full hub: my classes + my downloads + order-history link |
| `[ch_my_classes]` | Grid of the visitor's accessible classes |
| `[ch_my_retreats]` | Grid of the visitor's purchased retreat products (collection = `retreats`) |
| `[ch_my_downloads]` | The visitor's downloadable files, grouped by class |
| `[ch_class_products]` | Buy options for the current class (`class_id="123"` to target another) |
| `[ch_access_notice]` | "You don't have access" notice for the Access page — buy links + log-in link (reads `?ch_class=123`) |
| `[ch_locked]…[/ch_locked]` | Inner content shown only to customers with access |
| `[ch_teaser]…[/ch_teaser]` | Inner content shown only to visitors without access |

## Developer hooks

| Hook | Type | Purpose |
|---|---|---|
| `ch_access_statuses` | filter | Order statuses that grant access |
| `ch_class_product_ids` | filter | Products that unlock a class |
| `ch_user_can_access_class` | filter | Final yes/no access decision |
| `ch_user_class_ids` | filter | The list of a user's accessible class IDs |
| `ch_retreat_taxonomy` / `ch_retreat_term` | filter | Which product taxonomy + term counts as a retreat (default `collection` / `retreats`) |
| `ch_no_access_redirect` | filter | The Access-page URL a no-access visitor is redirected to |
| `ch_locked_logged_out_redirect` | filter | Where a logged-out visitor is sent when no Access page is set |
