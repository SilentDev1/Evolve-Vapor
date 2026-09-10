# Evolve AI Vape Match — WordPress / WooCommerce Plugin

AI-powered "Find My Perfect Vape" + "Flavor Match" guided shopping wizard for WooCommerce vape stores. The AI is **strictly bounded** — it can only rank and explain products that are **published, visible, in stock, and have a price** in your WooCommerce catalog. It cannot hallucinate product names.

---

## File structure

```
evolve-ai-vape-match/
├── evolve-ai-vape-match.php       Main plugin file (bootstrap, constants, WC check)
├── includes/
│   ├── class-plugin.php           Singleton — registers subsystems, enqueues, shortcode
│   ├── class-admin.php            Settings page (Settings API)
│   ├── class-product-meta.php     "AI Vape Match Data" meta box on WC products
│   ├── class-recommender.php      Rule-based product query + scoring
│   ├── class-openai.php           Locked-down OpenAI rerank + explanations
│   └── class-rest-api.php         /wp-json/evolve-ai-vape-match/v1/recommend
├── assets/
│   ├── css/frontend.css           Dark glassmorphism + neon wizard
│   ├── css/admin.css              Settings page + meta box
│   ├── js/frontend.js             Multi-step wizard + AJAX submit
│   └── js/admin.js                Color-swatch preview
└── README.md
```

---

## 1. Installation

1. Zip the `evolve-ai-vape-match/` directory.
2. WP Admin → Plugins → Add New → Upload Plugin → upload the zip → Activate.
3. WooCommerce must be active. If it isn't the plugin won't run and you'll see a yellow "WooCommerce required" admin notice. No fatal errors either way.

## 2. Settings — `WP Admin → Vape Match`

| Setting | Notes |
|---|---|
| **OpenAI API Key** | Stored in `wp_options` (option key `evolve_aivm_settings.api_key`), never sent to the front-end. |
| **Model** | `gpt-5-mini` (recommended), `gpt-5`, `gpt-4o-mini`, `gpt-4o`. |
| **AI explanations** | When off → AI is bypassed; rule-based copy used. |
| **Max products sent to AI** | 4–40. 8–16 is the sweet spot for cost vs. quality. |
| **Fallback to rule-based** | Recommended ON. If OpenAI errors or rate-limits, the quiz still returns results. |
| **Accent / Button color** | Both default `#B7FF00`. Painted onto the wizard via CSS variable. |
| **Floating "Find My Match" button** | Site-wide sticky FAB that opens a modal-mounted quiz. |
| **Age confirmation text** | Required for compliance. Shown on the last step before recommendations. |

## 3. Shortcode

```text
[evolve_ai_vape_match]
```

Optional attributes:

```text
[evolve_ai_vape_match
    show_intro="yes|no"
    title="Find your perfect vape"
    subtitle="Answer a few quick questions..."
    class="my-extra-class"]
```

Drop it on any WordPress Page (e.g. `/quiz/`) or into Elementor's **Shortcode** widget. The wizard mounts itself client-side.

## 4. Per-product metadata — "AI Vape Match Data" meta box

Open any WooCommerce product → scroll down to the **AI Vape Match Data** meta box and fill in:

| Field | Meta key | Type |
|---|---|---|
| Flavor family | `_evolve_aivm_flavor_family` | select (`fruity`, `mint-ice`, `candy`, `dessert`, `tobacco`, `drink-inspired`) |
| Flavor notes | `_evolve_aivm_flavor_notes` | free text — comma-separated |
| Sweetness level | `_evolve_aivm_sweetness_level` | 1–10 |
| Cooling / ice level | `_evolve_aivm_cooling_level` | 1–10 |
| Throat hit | `_evolve_aivm_throat_hit` | `smooth` / `medium` / `strong` |
| Nicotine strength | `_evolve_aivm_nicotine_strength` | free text — `50`, `35 mg`, `20`, etc. |
| Device type | `_evolve_aivm_device_type` | `disposable`, `pod-system`, `vape-juice`, `mod-device`, `pod`, `coil`, `accessory` |
| Beginner friendly | `_evolve_aivm_beginner_friendly` | `yes` / `no` |
| Brand | `_evolve_aivm_brand` | free text |
| Compatibility | `_evolve_aivm_compatibility` | free text (e.g. "Smok Novo 5, RPM 4 coils") — used for "Complete Your Setup" |
| AI keywords | `_evolve_aivm_ai_keywords` | free text — comma-separated tags for semantic ranking |

Every field is sanitized on save and stored as standard `post_meta`. Empty fields are deleted (no stale rows).

## 5. Endpoint

```text
POST  /wp-json/evolve-ai-vape-match/v1/recommend
Header:  X-WP-Nonce: <wp_rest nonce>
Body (JSON):
{
  "experience":      "beginner|intermediate|advanced",
  "product_type":    "disposable|pod-system|vape-juice|mod-device|not-sure",
  "flavor_family":   "fruity|mint-ice|candy|dessert|tobacco|drink-inspired",
  "flavor_specific": "Strawberry",
  "sweetness_level": 5,
  "cooling_level":   3,
  "throat_hit":      "smooth|medium|strong",
  "nicotine":        "20",
  "budget_min":      0,
  "budget_max":      40,
  "brand":           "",
  "age_confirmed":   true
}
```

Response:

```json
{
  "source": "ai|fallback|rule-based",
  "primary": { "id": 123, "name": "...", "match_percent": 92, "reason": "...", "image": "...", "permalink": "...", "price": 35, "price_html": "$35.00", "add_to_cart_url": "...", "ajax_add_to_cart": true, "label": "Your Perfect Match" },
  "flavor_matches": [ { ...same shape, "label": "Sweeter Pick" }, ... ],
  "setup":          [ { ...same shape, "label": "Compatible Pods" }, ... ],
  "answers": { ...echoed back for analytics ... }
}
```

## 6. How the recommendation pipeline works

1. **Quiz arrives** (REST POST). Every field is sanitized to known shapes (whitelisted enums, int clamps, sanitize_text_field for free text).
2. **Age gate** is verified. Missing → 403.
3. **Rule-based pass** — `Recommender::get_candidates()` runs a single `WP_Query` with these guards:
   - `post_status = publish`
   - `_stock_status = instock`
   - `_price IS NOT NULL`
   - `tax_query NOT IN exclude-from-search/catalog`
   - Optional: budget min/max, device-type pre-filter
4. **Scoring** — 10 weighted dimensions (flavor family, flavor keyword, sweetness Δ, cooling Δ, throat hit, nicotine ±, device type, experience, brand, keyword overlap). Max raw score 100.
5. **AI rerank** — top `max_products_to_ai` candidates get sent to OpenAI with a strict system prompt:
   - "You MUST only output products whose `id` appears in the provided products array."
   - "JSON only. No prose."
   - Schema with `primary`, `flavor_matches`, `setup`.
   - Labels are constrained to: `Sweeter Pick`, `More Ice`, `Less Ice`, `Same Flavor Type`, `Best Value`, `Popular Choice`, `Smoother Hit`, `Bolder Hit`.
6. **Validation** — any product ID the AI returns that isn't in our candidate list gets dropped. So even if the AI misbehaves, we never render a hallucinated product.
7. **Fallback** — if OpenAI errors or returns bad JSON, the rule-based ranking is used directly with generic per-category labels and a generic "Strong match" reason. The quiz never breaks.

## 7. Compliance

- **Age confirmation is server-enforced.** Front-end gate is for UX; the REST endpoint independently rejects the request with a 403 if `age_confirmed` isn't true. There is no client-side bypass.

## 8. Customizing

- **Filter the candidate pool size**: `Recommender::get_candidates( $answers, 60 )` — bump the second arg.
- **Filter scoring weights**: edit `Recommender::score()` — clearly commented.
- **Customize AI prompt**: edit `OpenAI::system_prompt()`.
- **Add a new quiz step**: append to the `STEPS` array in `assets/js/frontend.js` and add a matching whitelist in `REST_API::sanitize_answers()`.
- **Customize labels**: pass the AI a new allowed-label list in the system prompt, and add new ones to the fallback `$labels` array in `REST_API::build_fallback()`.

## 9. Testing checklist

1. **Plugin loads with WC inactive** → admin notice, no fatals. ✓
2. **Settings page** saves API key, model, color, age text. Reload page — values persist. ✓
3. **Meta box** appears on `wp-admin/post-new.php?post_type=product` and saves all 11 fields. ✓
4. **Front-end shortcode** renders quiz wizard. Each step has tap-friendly cards. ✓
5. **Age step** blocks submit until checked. ✓
6. **AJAX POST** to `/wp-json/evolve-ai-vape-match/v1/recommend` returns JSON with `primary`, `flavor_matches`, `setup`. ✓
7. **AI off / Fallback off / API key empty** → rule-based path returns valid results. ✓
8. **Out-of-stock products** never appear, even if tagged with matching meta. ✓
9. **Add to Cart** button → simple products use `?wc-ajax=add_to_cart`; variable products navigate to the product page. ✓
10. **Floating button** toggle works site-wide. ✓

## 10. Hooks / filters offered for further customization

(None yet — the codebase is deliberately small. Open PRs welcome.)

---

**Version:** 1.0.0
**Requires WP:** 6.4 · **Requires PHP:** 8.0 · **WC tested up to:** latest
