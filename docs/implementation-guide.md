# Evolve — Custom Theme System for evolvevapornh.com (v1.4.0)

A complete dark-luxury vape retail experience built on **Hello Elementor** + **Elementor Pro** + **WooCommerce**. Everything is editable inside Elementor.

```
build/
├── evolve-child/          ← WordPress child theme (zip & upload as a theme)
├── evolve-core/           ← Companion plugin (zip & upload as a plugin)
└── README.md              ← this file
```

---

## 0. Requirements

Install + activate (in this order):

1. **WordPress** ≥ 6.4
2. **Hello Elementor** (parent theme) — wordpress.org/themes/hello-elementor/
3. **Elementor** (free) — already in `/Desktop/Evolve/elementor/`
4. **Elementor Pro** — already in `/Desktop/Evolve/elementor-pro/`
5. **WooCommerce**

---

## 1. Install the Child Theme

1. Zip the `build/evolve-child/` folder → `evolve-child.zip`
2. WP Admin → **Appearance → Themes → Add New → Upload Theme**
3. Upload `evolve-child.zip` and **Activate**

The child theme:
- Loads Poppins + Inter from Google Fonts
- Registers all WooCommerce theme supports
- Drops the design-system CSS for buttons, cards, forms, headers, footers, hero, etc.
- Adds a pickup notice above cart / checkout / single-product

Menus to create at **Appearance → Menus** and assign to these locations:
- **Primary Menu** → desktop header
- **Mobile Menu** → mobile slide-out
- **Footer — Quick Links**
- **Footer — Customer Service**

---

## 2. Install the Companion Plugin

1. Zip the `build/evolve-core/` folder → `evolve-core.zip`
2. WP Admin → **Plugins → Add New → Upload Plugin**
3. Upload and **Activate**

What it adds:
- **21+ Age Gate** — full-screen glass overlay shown to unverified visitors, dismissible via Enter button, persisted in a 30-day cookie. (Cookie name: `evolve_age_verified`. Override expiry via the `evolve_age_gate_days` filter, exit URL via `evolve_age_gate_exit_url`.)
- **Elementor Dynamic Tags** under the *Evolve* group: Store Address, Store Phone, Store Hours, Cart Count — drop them into any Elementor field
- **Sale badge** rewritten to say `DEAL`, plus an "In-store pickup" loop chip
- **Template Importer** at **Tools → Evolve Templates** (next step)

---

## 3. Import the Elementor Templates

WP Admin → **Tools → Evolve Templates** → **Import All Templates**.

The importer loads these JSON files into Elementor (Templates → Saved Templates / Theme Builder):

| Template | Type | Set Display Condition |
|---|---|---|
| Kit — Global Colors & Fonts | `kit` | Site Settings → Site Identity → set as active kit |
| Header (Sticky Glass) | `header` | Entire Site |
| Footer (4-column dark) | `footer` | Entire Site |
| Homepage | `page` | Set as Reading → Static page = Homepage |
| Shop / Product Archive | `product-archive` | Shop Page + All Product Archives |
| Single Product | `single-product` | All Products |
| Cart | `cart` | Cart Page |
| Checkout | `checkout` | Checkout Page |
| My Account | `my-account` | My Account Page |
| Blog Archive | `archive` | Posts Archive |
| Single Blog Post | `single-post` | All Posts |
| Search Results | `search-results` | Search Results |
| 404 | `error-404` | 404 |
| Age Verification Popup | `popup` | (Display on entire site, on page load) |
| Mobile Menu Popup | `popup` | (Triggered via Elementor Popup action on hamburger icon) |

> The Age Gate is also enforced server-side by the companion plugin, so even if the Elementor popup template is disabled, the 21+ overlay still renders.

---

## 4. Activate the Kit

1. WP Admin → **Templates → Saved Templates** → find **"Evolve — Global Kit"**
2. **Apply Kit** (Elementor will set the global colors + typography)
3. Or: Elementor → **Site Settings → Site Identity → Active Kit** → choose **Evolve — Global Kit**

---

## 5. Theme Builder — Assigning Conditions

Templates → **Theme Builder**. For each imported part:

- **Header → Edit Conditions → Include → Entire Site**
- **Footer → Edit Conditions → Include → Entire Site**
- **Single Product → Edit Conditions → Include → Products → All**
- **Product Archive → Include → Product Archive → All**
- **Cart / Checkout / My Account / 404 / Search Results** — they auto-target their pages
- **Single Post → Include → Posts → All**
- **Posts Archive → Include → Archive → Posts Archive**

---

## 6. Design Tokens (CSS variables)

All exposed in `:root` inside `evolve-child/assets/css/evolve.css`:

```css
--evolve-bg:           #050505;
--evolve-bg-2:         #0D0D0D;
--evolve-card:         #111111;
--evolve-border:       #1E1E1E;
--evolve-accent:       #B7FF00;   /* primary neon */
--evolve-accent-2:     #7CFF4F;   /* hover neon */
--evolve-text:         #FFFFFF;
--evolve-text-dim:     #B3B3B3;
--evolve-error:        #FF4D4D;
--evolve-success:      #00FF99;

--evolve-font-head:    'Poppins';
--evolve-font-body:    'Inter';

--evolve-radius:       20px;
--evolve-radius-sm:    14px;
--evolve-container:    1200px;

--evolve-glow:         0 0 24px rgba(183,255,0,0.35);
```

Override any of them in Elementor → **Site Settings → Custom CSS** without touching the theme files.

---

## 7. Mobile / Performance Notes

- **Mobile-first** — all sections collapse cleanly to 1- or 2-col grids below 1024px and 600px
- **Sticky glass header** — backdrop-blur kicks in on scroll
- **Lazy load images** — leave Elementor's defaults on
- **WebP** — recommend running through ShortPixel or similar
- **Avoid heavy popup builders / addons** — everything ships in this bundle

---

## 8. Customizing Store Info

Store address/phone/hours are centralized in `evolve-core/includes/class-dynamic-tags.php` via the `evolve_store_info` filter. To change, drop this in your child theme's `functions.php`:

```php
add_filter('evolve_store_info', function($info){
    $info['phone'] = '(603) 555-0199';
    $info['hours'] = "Mon–Sat 11:00–22:00\nSunday 12:00–20:00";
    return $info;
});
```

That value flows into the **Evolve / Store Phone** etc. dynamic tags throughout the templates.

---

## 9. v1.1.0 — Custom Elementor widgets

Two new widgets ship in **Evolve Core 1.1.0**, both under the **Evolve** Elementor category (also pinned to the *WooCommerce* category for the filter).

### 9a. Evolve Shop Filter

Drop into the left sidebar of the **Shop / Product Archive** Theme Builder template.

Toggleable sections (each with its own Elementor switch):

- Search keyword
- Product Categories (hierarchical, optional counts)
- Price range (min / max number inputs, configurable bounds + step)
- Rating filter (★…★★★★★ "and up")
- Dynamic product attributes (`pa_brand`, `pa_flavor`, `pa_nicotine`, etc. — picks itself up automatically; selectable via Select2)
- On-Sale toggle
- In-Stock toggle
- Clear-all link / Apply button
- Collapsible `<details>` sections + open-by-default toggle

Behavior:

- **AJAX filtering ON by default.** Filters debounce (280 ms input, 120 ms checkbox) and POST to `wp_ajax_evolve_filter_products`, which re-runs the product loop and replaces the contents of your **Target product grid selector** (default: `.woocommerce ul.products` / Elementor Pro's archive products widgets).
- URL is updated via History API → filters are shareable, back-button works.
- No-JS fallback: form `GET`s to `/shop/?…` and the same filters apply server-side via `pre_get_posts`.
- **Mobile drawer mode** swaps the sidebar for a "FILTERS" button that opens a slide-in panel from the right (lockable body scroll).

Style controls: panel bg / border / radius, headings color, accent color (drives all neon highlights), count color.

### 9b. Evolve Header Search

Drop into the header (replace the small magnifier icon). The widget is a glass pill with a magnifier; focusing or typing reveals a wide dropdown panel anchored to the input.

Features:

- **Live AJAX** — debounced 240 ms, hits `wp_ajax_evolve_search_products`
- Skeleton shimmer while loading
- Dark luxury product cards (image, title, price, optional **DEAL** badge)
- Top 6 most-popular categories shown as quick-link pills above the results
- **Keyboard nav** — `↑ / ↓` to highlight a result, `Enter` to visit, `Esc` to close
- **"View all results for X"** CTA button with the total count
- Clear-X button inside the pill
- Mobile: dropdown becomes a full-screen overlay below the header

Elementor controls: placeholder, min characters (default 2), max results (default 8), columns (2–5), show price y/n, show category quick-links y/n, dropdown width, input bg/border, accent color, dropdown bg.

### How to wire them up

1. Open **Templates → Theme Builder → Header** in Elementor.
2. Delete the existing search icon. Drag in **Evolve → Evolve Header Search**.
3. Open **Templates → Theme Builder → Shop / Product Archive**.
4. In the left sidebar container, drag in **Evolve → Evolve Shop Filter**. Toggle whichever sections you want, set the target selector if your product grid is wrapped (default works for the bundled Shop template).
5. Update.

---

## 10. v1.0.1 — Patch notes (single-product readability)

If you've already installed v1.0.0, replace the theme + plugin with the new zips. The patch fixes:

- **Variation `<select>` dropdown** — was black-on-near-black. Now has white text, a neon ▼ caret, visible `<option>` rows, and the "Colors" / variation label sits to the left in a 2-column grid instead of being hidden behind the select.
- **Add-to-Cart in WooCommerce's "disabled" state** — was rendering brick-red until a variation was chosen. Now renders as a neutral card-grey button with a `— choose option` suffix; turns neon green the moment a variation is picked.
- **Star ratings** — empty stars were `rgba(255,255,255,0.15)` (invisible). Now `0.28` with a neon glow on filled stars.
- **Product gallery** — kills the stacked duplicate main image and shows a proper 5-wide thumbnail strip below the viewport.
- **Admin-bar overlap** — the topbar's "21+ Orders ready" pill no longer collides with WP's `Howdy, …` bar; the sticky header drops 32px (or 46px on mobile) when the admin bar is visible.
- **Header search + cart pill** — both now styled as pill icons matching the rest of the chrome.

### Empty filter sidebar on /products

The empty "FILTERS" panel in the screenshot is a missing-widget issue, not a CSS bug. The shop archive template ships with Search + Product Categories widgets, but if you customized the sidebar you may need to drop the filter widgets in yourself:

1. Edit **Templates → Theme Builder → Shop / Product Archive** in Elementor.
2. Into the left sidebar container, drag:
   - **Product Categories** (Elementor Pro)
   - **Product Search** (or a WordPress search widget pointed at `product` post type)
   - **Products Filter** widget (Elementor Pro 3.13+) — for price slider, rating filter, on-sale toggle
3. (Optional) Set the **Products Filter** widget's "Query" → `Manual Selection → Current Query` so it filters the archive in place via AJAX.

Elementor Pro's Products Filter widget gives you the off-canvas AJAX filtering called for in the spec. If you don't see it, you're on a pre-3.13 Elementor Pro and need to upgrade.

---

## 11. v1.1.1 / 1.0.2 — Shop archive polish

Patch addresses the layout regressions seen the first time the filter was dropped onto the live shop page:

- **Apply button rendered in WC's brick-red** — Hello Elementor's default `button` style was outranking ours. The filter Apply/Clear All buttons now have `!important` neon-green / outline rules scoped to `.evolve-filter`.
- **Filter sidebar squashed to ~80px** — added `min-width: 240px` on the panel so a narrow Elementor column can't collapse it.
- **Products stacking 1-per-row with huge white image squares** — the WC archive widget was set to `columns=1`. CSS now force-grids `.woocommerce ul.products` into 4 (desktop) / 3 (tablet) / 2 (small tablet) / 1 (phone) regardless of the widget's column setting. Product images are capped at `aspect-ratio: 1 / 1` and `max-height: 260px` with `object-fit: contain` so the white tile stays small.
- **Pagination duplicated + floating right** — pagination is now centered, styled in dark cards with neon hover and an active-page chip; any second `.woocommerce-pagination` sibling is hidden.
- **Duplicate filter widget** — if two `.evolve-filter` instances end up on a page, the second one is dimmed and outlined red with a "Duplicate widget — delete one" badge. **In Elementor, edit the Shop Archive template and delete one of them.** This is the root cause of the double Apply / Clear All seen in the screenshot.

After updating, hard-refresh the page (`Cmd+Shift+R`) so the new CSS replaces what the browser cached.

### Recommended widget settings for the Shop Archive
- **Evolve Shop Filter** — leave all sections on, ensure only one instance exists in the sidebar
- **WooCommerce Archive Products / Elementor Pro Products** widget — set `Columns: 4` (desktop), `3` (tablet), `2` (mobile), `Rows: 5`
- Pagination: keep enabled on the WC widget; the bundled template doesn't add a separate Elementor pagination widget

---

## 12. v1.2.x — OmniSuggest AI + pagination + category picker

### OmniSuggest AI integration (1.2.0)

If **OmniSuggest AI** (Cao-Tech) is installed and active, the Evolve Header Search routes every query through `GET /wp-json/omnisuggest/v1/search-suggest` instead of the local WP_Query. The widget keeps its dark-luxury markup; results now carry:

- An **AI** corner pill on each card
- A per-product **reason** line below the price (italic, 2-line clamp, full text in `title` tooltip)
- A subtle "Ranked by OmniSuggest AI for X" line at the bottom of the dropdown
- An **"AI fallback — these are related picks"** banner when OmniSuggest returns its alternative fallback set (zero direct matches)
- Out-of-stock flag rendered as a red `Out of stock` tag

When OmniSuggest is missing, errors, or returns no matches, the widget silently falls back to the local in-stock WP_Query. The "Use OmniSuggest AI" switch in the widget (default ON) hides the AI path entirely if you want to force local search.

The integration is server-side via `rest_do_request`, so there's no extra HTTP round-trip and OmniSuggest's nonce check is satisfied with a freshly minted `wp_rest` nonce. No JS or REST routing changes were needed on the OmniSuggest side.

### Pagination broken on AJAX-filtered grid (1.2.1)

Clicking page 2/3/4 on a filtered shop archive was navigating to `/wp-admin/admin-ajax.php?paged=2` (blank `0` response). Root cause: `woocommerce_pagination()` uses `home_url($wp->request)` for its base, which during admin-ajax is `admin-ajax.php`. Fix: in the AJAX handler we now apply `woocommerce_pagination_args` with the shop permalink as the base, and we preserve every active filter param in `add_query_arg()` so page-2 of a price-filtered view stays price-filtered.

### Category picker on the Shop Filter widget (1.2.1)

The Categories section now has two new controls under the widget's **Behavior** tab:

- **Categories to Show** — Select2 multi-picker of every product category. Empty = all (existing behavior). Pick to limit the list.
- **Flat List (ignore parent/child hierarchy)** — when on, even unfiltered category trees render as a flat alphabetical list with no indentation.

When you pick specific categories, they render in the order you picked them. Hierarchical nesting is automatically disabled in picker mode.

### Loop card polish (1.0.3)

Each shop-archive product card now uses a flex column layout with sane minimums:

- Title clamps to 2 lines (`-webkit-line-clamp: 2`) so cards stay the same height
- Pickup chip wraps to its own line with `margin: 6px 0 2px`
- Price bumped to 16px / bold
- Select Options button anchored to the bottom of the card via `margin-top: auto`
- Star rating capped at the standard 5-star width

---

## 13. v1.2.2 / 1.2.3 — Filter labels + Single Product completeness

### 1.2.2 — Filter labels black-on-black
Hello Elementor and WooCommerce both ship `label { color: … }` at the same specificity as `.evolve-filter__check`, so their darker color was winning the cascade. The filter widget's body text was rendering nearly invisible. Patch forces:
- `.evolve-filter__head` / `summary` → `#FFFFFF`
- Every `label` / checkbox label / rating row / price `–` → `#B3B3B3` (with `:hover` lift to `#FFFFFF`)
- Counts → `#6E6E6E`
- `<input type="search|number">` inside the panel → white text on `#161616`, placeholders forced to `#6E6E6E` across all prefixes
- Section caret stays neon green

Plus a CSS guard against truly empty `<li.product>` slots that the WC loop sometimes emits for trashed/private/hidden products. The AJAX handler also now skips any post whose `wc_get_product()` isn't `is_visible()`.

### 1.2.3 — Single Product template missing widgets

The first `single-product.json` shipped used dashed IDs (`sp-title`, `sp-rating`…). Elementor's importer expects 7-character alphanumeric IDs and silently drops widgets whose IDs don't match, which is why the live page rendered only title + image + short description — the price, rating, add-to-cart, meta, and tabs widgets were never imported.

The template is rewritten with proper `evpNNNNN`-style IDs and a fuller layout:

1. **Top row (2 columns):**
   - Left — Product Images (full gallery)
   - Right — Title, Rating, Price, Short Description, **In-store pickup notice**, Add to Cart (variations + quantity), Meta
2. **Long-description card** — `woocommerce-product-content` widget in a dark glass card under the heading "Product Description"
3. **Tabs** — `woocommerce-product-data-tabs` (Description / Additional info / Reviews) in the Evolve tabs styling
4. **Related products** — 4-up grid with the dark-luxury card style

The child theme (1.0.5) also force-styles `.elementor-widget-woocommerce-product-content` (long description) as a dark glass card with proper heading, paragraph, list, link, and image typography — so even a manually-built single-product template inherits the look.

### How to re-import the new template
1. WP Admin → **Tools → Evolve Templates**
2. Find **Single Product** in the list → click **Import**
3. WP Admin → **Templates → Theme Builder** → **delete the old "Evolve — Single Product"** entry
4. On the new import's row, click **Add Condition** → Products → All
5. Hard-refresh any open product page

If the live template was manually edited and you want to keep your edits: drop in the missing widgets manually from the Elementor panel — **WooCommerce → Product Content** (long description), **Product Add to Cart**, **Product Meta**, **Product Data Tabs**, **Product Related**. The new CSS will style them automatically.

---

## 14. v1.3.0 — Editorial templates

Four new / rebuilt Elementor templates ship in **1.3.0**, all using valid 8-char alphanumeric IDs so they import cleanly.

| Template | Type | What it covers |
|---|---|---|
| Blog Archive | `archive` | Editorial hero (eyebrow + big archive title + lede) → 3-column posts grid (date / author / excerpt / "Read article →") → centered pagination → neon newsletter card at the bottom |
| Single Blog Post | `single-post` | Breadcrumb → category eyebrow → display-size H1 → meta (date/author/comments) → rounded-20 featured image → 760px-wide post body with neon-underlined links, neon `>` blockquotes, neon-tinted inline `<code>`, code blocks → tag row + share buttons → dark author card → 3-up "More from Vape News" grid |
| Contact Page | `page` | Radial-gradient hero with neon eyebrow + display H1 + quick-action buttons (Call / Directions / Email) → 2-column row: Elementor Pro Form (Name / Email / Phone / Topic / Message) + dark glass **Contact Info** card (phone, address, hours, email, pickup pill) → full-width Google Map → FAQ accordion (5 default Q&A — shipping, 21+, juice promo, testers, pickup time) |
| Default Page | `page` | Reusable shell for About / Privacy / Terms / FAQ / any internal page. Radial-gradient hero with dynamic page title + breadcrumb → 2-column body: dark glass `theme-post-content` card + sidebar with **Visit Evolve** info block + neon CTA card → "Shop the store →" bottom strip |

### Importing
**Tools → Evolve Templates → Import All** picks up the two new ones too. Or import each individually from the table on that admin page.

After import, set conditions in **Templates → Theme Builder**:
- Blog Archive → **Posts Archive**
- Single Blog Post → **Posts → All**
- Contact Page → assign the imported page as your **/contact/** WordPress page (Pages → Edit → "Use Elementor")
- Default Page → use it as a starting point: open any new WP page → "Edit with Elementor" → load template → start tweaking

The child theme (1.0.6) adds matching CSS for every custom HTML block used inside the templates:

- `.evolve-newsletter` — neon-bordered card at the foot of the blog archive
- `.evolve-contact-card` — dark glass card with neon icons for phone/address/hours/email
- `.evolve-sideblock` — sidebar info card on the Default Page
- `.evolve-bottom-cta` — bottom CTA strip on the Default Page
- `.evolve-accordion` — FAQ-style accordion styling
- Full Elementor Posts/Archive-Posts widget restyle (dark cards, neon hover, neon pagination, "Read →" link styling)
- 760px reading-width post body with proper H2/H3, blockquote, code, image, link, list styles

---

## 15. v1.3.1 / 1.0.7 — WooCommerce templates + mini-cart fix

### Mini-cart popup (header) was white-on-white

Clicking the cart icon was opening a popup with **white background and white text** — you literally couldn't read what was in the cart. Patched `woocommerce.css` with a full dark-luxury restyle of the mini-cart structure:

- Slide-in container: rgba-blackened with backdrop blur + neon-edged border
- Close X: outlined pill, neon on hover
- Each mini-cart item: 60px image (light tile, contained), bold title (2-line clamp, neon on hover), dim quantity line with neon price, **red trash X** on the right (turns solid red on hover)
- Subtotal row: bordered with neon price treatment
- **View Cart** = ghost outline button, **Checkout** = neon green pill

### Checkout "Your Order" panel was black-on-black

Patched every table cell in `.woocommerce-checkout .shop_table` to force `color: #FFFFFF` (title) / `#B3B3B3` (qty / dim text) / `#B7FF00` (price + order total) so every number reads cleanly on the dark card.

### Polished WooCommerce templates (1.3.1)

Each WooCommerce page template got a proper editorial hero matching the rest of the site (neon eyebrow + display H1 + supporting line + pickup notice). The page-type Woo widget renders below it.

- **Cart** — hero + pickup notice + WC Cart widget + **3-up trust row** ("Ready in 30 min" / "21+ ID Required" / "Buy 4 e-juice, 5th free")
- **Checkout** — hero "Almost done." + pickup notice + WC Checkout Page widget
- **My Account** — hero "Welcome back." + vertical-tabs WC My Account widget
- **Order Received (Thank-you)** — *new template*. Centered with an animated neon ✓ glyph, big "Order received." headline, pickup-ready copy, and the `[woocommerce_order_received]` shortcode underneath

### Where the templates live

| Template | Type | Display Condition |
|---|---|---|
| Cart | `cart` | Cart Page (auto) |
| Checkout | `checkout` | Checkout Page (auto) |
| My Account | `my-account` | My Account Page (auto) |
| Order Received (Thank-you) | `page` | Load into the WC Thank-You page — see below |

**Wiring up Order Received:** WooCommerce ships its thank-you screen as a sub-route of the Checkout page (`/checkout/order-received/`). It uses the same page WP-side. To use this template **only** there:

1. Create a new WP Page called "Thank You" → load **Evolve — Order Received** in Elementor
2. WooCommerce → Settings → Advanced → set **Order Received endpoint** to point at it (or leave default and just use the `[woocommerce_order_received]` shortcode inside any Page that uses this template)

Or simpler: just paste `[woocommerce_order_received]` into your existing Checkout-completion page — the template will load and the shortcode handles the WC data.

---

## 16. v1.3.2 — Template importer rewrite (Cart / Theme Builder fix)

### Why Cart wasn't importing

`Tools → Evolve Templates → Import` was routing every template through Elementor's `Source_Local::import_template()`. That method works fine for `page`, `popup`, `section`, `kit`, but for Elementor Pro **Theme Builder** document types — `cart`, `checkout`, `my-account`, `single-product`, `product-archive`, `header`, `footer`, `archive`, `single-post`, `search-results`, `error-404` — it can silently no-op or fail to register the right `_elementor_template_type` post meta, leaving the template invisible in Theme Builder.

### What the new importer does

1. **Preferred path** — `\Elementor\Plugin::$instance->documents->create( $type, … )`. This is the same API the Elementor Pro UI uses internally; it instantiates the correct document class (`ElementorPro\Modules\ThemeBuilder\Documents\Cart`, `Checkout`, etc.), saves the elements + page settings, and writes the proper template-type meta. Works for every Pro type as well as `page`, `popup`, `kit`.
2. **Fallback** — if the documents API throws (e.g. unknown type, older Elementor), we fall back to the old `Source_Local::import_template()` path so generic `page` / `section` / `kit` types still import.
3. **Pro check** — Theme Builder types now short-circuit with a clear "Elementor Pro must be active" error instead of silently failing.
4. **Visible errors** — each row in the importer table shows ✅ + an "Edit" link, or ❌ + the actual error message returned by Elementor. The "Import All" summary tells you "Imported N templates, M failed".

### How to use it

WP Admin → **Tools → Evolve Templates** → **Import All Templates**

After import:
- Theme Builder types appear in **Templates → Theme Builder** — click **Add Condition** to assign each
- Page-type templates (Homepage, Contact, Default Page, Order Received) appear in **Templates → Saved Templates** — load them into a WP Page via Elementor's library
- The Kit appears in Saved Templates as `kit` — apply via **Site Settings → Site Identity → Active Kit**

If a row stays ❌ after re-import, the error message tells you exactly why — most commonly:
- `Elementor Pro must be active to import a "cart" template` → activate Elementor Pro
- `JSON for X is missing the "type" field` → file corruption, re-deploy the zip
- `File missing: X.json` → zip uploaded incomplete

---

## 17. v1.3.3 — Template types corrected + mini-cart empty state

### "Invalid template type" on cart / checkout / my-account / single-product

The Elementor Pro build on this site registers exactly **two** WooCommerce Theme Builder document types: `product` (Single Product) and `product-archive` (Shop / Product Archive). Earlier Evolve versions shipped templates typed `cart`, `checkout`, `my-account`, and `single-product` — none of which are registered as Theme Builder doc types in this Pro build, so `documents->create()` correctly rejected them with **"Invalid template type"**.

Fixed in 1.3.3:

| Template | Old type (broken) | New type |
|---|---|---|
| Single Product | `single-product` | **`product`** |
| Cart | `cart` | **`page`** (load into WP Cart page) |
| Checkout | `checkout` | **`page`** (load into WP Checkout page) |
| My Account | `my-account` | **`page`** (load into WP My-Account page) |
| Shop Archive | `product-archive` | unchanged |
| Order Received | `page` | unchanged |

**How to use the page-type Woo templates**: WooCommerce already creates **/cart/**, **/checkout/**, **/my-account/** WordPress pages on activation. Edit each WP page → Edit with Elementor → click the **folder icon** → **My Templates** → load "Evolve — Cart" (or Checkout, or My Account). The Elementor Pro WC widgets inside the template (`woocommerce-cart`, `woocommerce-checkout-page`, `woocommerce-my-account`) detect the current WC page context automatically.

For the Single Product template: now imports cleanly as a `product` Theme Builder doc → set its display condition to **Products → All** in Theme Builder.

### Mini-cart popup empty state

When the cart was empty, the popup rendered as just a thin line with a close X — no message, no visible structure. Fixed:

- PHP: `Woo_Customizations::inject_empty_state_fragment()` swaps the WC mini-cart fragment for our styled empty card whenever the cart is empty (and `woocommerce_empty_mini_cart_html` filter for newer WC versions)
- CSS: enforced `min-width: 380px` / `min-height: 360px` / `width: 380px` on `.elementor-menu-cart__main` so the popup has presence even before content loads. Mobile breakpoint snaps to full viewport.
- New `.evolve-cart-empty` card with:
  - 80 px neon-tile circle around a cart-glyph SVG
  - Big "Your cart is empty." headline
  - 280-px lede line
  - **"Browse the store →"** neon CTA linked to `/shop/`

So now clicking the cart icon with nothing in it shows a proper empty-state card, not a void.

---

## 18. v1.4.0 — Evolve Subscribe + GhostPilot Ghost-Convert CRM bridge

A unified subscribe pipeline that routes **every newsletter form on the site** through GhostPilot's Ghost-Convert CRM when the plugin is active, with a local-storage fallback when it isn't.

### What ships in this version

**New PHP class — `\Evolve_Core\Subscribe`**

- AJAX endpoint at `wp_ajax_evolve_subscribe` (+ `nopriv`)
- Validates email, honoring `consent_required` for GDPR / CAN-SPAM
- Honeypot on `name="evolve_hp"` field
- Forwards submissions to GhostPilot via `rest_do_request('/ghostpilot/v1/ghost-convert/capture')` with payload:
  - `email`, `name`, `phone`, `gdpr_consent: true`
  - `source_type: 'cta'`, `source_url`, `source_page_title`
  - `intent_tags[]` — includes the user-defined `tags` + an `evolve:<source>` tag so you can segment by which form fired the capture
- If GhostPilot is missing or errors, logs the lead to a private `evolve_lead` CPT (visible at **Tools → Evolve Leads**) and emails the admin
- Even on a successful GhostPilot capture we still write to the local CPT for an audit trail, with `forwarded: 'ghostpilot'` and the returned `lead_id`

**New shortcode — `[evolve_subscribe]`**

```text
[evolve_subscribe
   style="card"                      // card | inline | stacked
   eyebrow="STAY IN THE LOOP"
   title="Get new drops & deals in your inbox"
   lede="One short email a week. ..."
   button="Subscribe →"
   placeholder="you@email.com"
   show_name="no"
   show_phone="no"
   consent="yes"                     // GDPR checkbox
   tags="newsletter,homepage_footer" // forwarded as intent_tags
   source="evolve_widget"            // becomes "evolve:<source>" tag
   success="Subscribed! Check your inbox."]
```

Drop it anywhere. Defaults to the **neon-bordered card design** that matches the blog-archive newsletter card 1:1 (eyebrow / title / lede on the left, email pill + green Subscribe button on the right).

**New Elementor widget — Evolve Subscribe** (`Elementor → Evolve category → Evolve Subscribe`)

Same engine, same render path, every control mapped: layout (card / inline / stacked), eyebrow / title / lede / button / placeholder, optional name & phone fields, consent toggle, intent tags, source label, success message. The widget's settings panel shows a **green badge when GhostPilot is detected** or a red one when it isn't, so the editor always knows where submissions will go.

**Blog archive newsletter card** now routes through this shortcode — the previously inert `<form action="#">` on `blog-archive.json` is replaced with `[evolve_subscribe source="blog_archive" tags="newsletter,blog_archive"]`. So that card is a real, capturing form now.

### Where leads land

| Plugin state | Where the lead goes |
|---|---|
| GhostPilot **active** | Ghost-Convert CRM (`wp_omnisuggest`-style table inside GhostPilot) + local audit row in `evolve_lead` CPT |
| GhostPilot **inactive** | Local `evolve_lead` CPT under **Tools → Evolve Leads** + email to site admin |

### Front-end behavior

- Form submits via JS (no page reload)
- Button enters loading state with a tiny neon spinner
- Inline success / error message under the fields
- `evolve:subscribed` `CustomEvent` fires on `document` so other scripts can hook in (e.g. fire a GA event)

### How to wire it to existing forms

Any element with class `evolve-subscribe` that has `data-evolve-subscribe='{...}'` and the expected field names is picked up automatically. Easiest:

```html
[evolve_subscribe style="inline" source="footer_pill" tags="newsletter,footer"]
```

or in the Elementor editor — Evolve → Evolve Subscribe → drag → set Source / Tags.

---

## 19. Troubleshooting

- **Header / footer not showing** → Theme Builder conditions not set. Re-check section 5.
- **Products look unstyled** → ensure WooCommerce is active *before* the child theme so `evolve-woocommerce` CSS enqueues.
- **Age gate stuck** → clear cookie `evolve_age_verified` from devtools.
- **Importer errors** → make sure Elementor is active. The "Local Source" is created automatically by Elementor; reinstall it if missing.

---

Built for Evolve Vapor · Pheasant Lane Mall · Nashua, NH · 21+
