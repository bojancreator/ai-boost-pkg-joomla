# Vertical Presets Guide — One-Click Site Configuration

Vertical Presets are one-click configuration bundles that apply a set of recommended settings across all JoomlaBoost tabs, optimized for a specific website type. Use them to get a well-configured starting point in seconds, then fine-tune individual settings as needed.

---

## How to Apply a Preset

1. Go to the **Plugin** tab in JoomlaBoost settings.
2. Under **Quick Setup**, select your site type from the **Site Type** dropdown.
3. Set **Apply Preset on Save** to **Yes**.
4. Click **Save**.

The preset is applied immediately. After saving, **Apply Preset on Save** automatically resets to **No** — so future saves do not re-apply the preset over your customizations.

> **Safe to apply:** Presets never overwrite manually entered data (addresses, phone numbers, social links, etc.). They only configure feature toggles, schema types, and sitemap settings.

---

## 🏨 Hotel / Accommodation

**Best for:** Hotels, boutique guesthouses, bed & breakfasts, serviced apartments, vacation rentals, glamping sites, camping facilities.

### What this preset configures

| Tab | Setting | Value applied |
|-----|---------|---------------|
| Schema.org | Schema Type | Hotel |
| Schema.org | FAQ Auto-Detect | Yes |
| Sitemap | Articles Priority | 0.8 |
| Sitemap | Menu Items Priority | 0.6 |
| Organization | Guest Ratings section | Visible (Advanced Options) |

### Additional fields that become available

After applying this preset, configure these Hotel-specific fields (Schema.org tab):

| Field | Description | Example |
|-------|-------------|---------|
| Star Rating | Official classification | 4 Stars |
| Check-in Time | Standard check-in (24h) | `14:00` |
| Check-out Time | Standard check-out (24h) | `12:00` |
| Pets Allowed | Pet-friendly? | Yes / No |
| Price Range | General pricing level | `$$$` |
| Opening Hours | Reception hours | `Mo-Su 00:00-23:59` |

**Strongly recommended to fill in:**
- GPS coordinates (Organization tab) — critical for Google Maps integration and local hotel search
- Guest ratings (Organization tab → Advanced Options) — star ratings in Google SERPs increase CTR significantly
- Organization description — used by AI engines when summarizing your property

---

## 🍽️ Restaurant / Cafe

**Best for:** Restaurants, cafes, bars, pubs, fast food outlets, food trucks, catering businesses, bakeries.

### What this preset configures

| Tab | Setting | Value applied |
|-----|---------|---------------|
| Schema.org | Schema Type | LocalBusiness |
| Schema.org | FAQ Auto-Detect | Yes |
| Sitemap | Articles Priority | 0.7 |
| Sitemap | Categories Priority | 0.7 |

### Additional fields to fill in after applying

| Field | Example |
|-------|---------|
| Opening Hours (Schema.org tab) | `Mo-Fr 12:00-23:00, Sa-Su 10:00-24:00` |
| Price Range (Schema.org tab) | `$$` |
| GPS Coordinates (Organization tab) | Latitude + Longitude |
| Phone Number (Organization tab) | `+1 212 555 1234` |

**Why GPS coordinates matter for restaurants:** Google's local pack (the map results at the top of "restaurants near me" searches) almost exclusively relies on structured data GPS coordinates. Without them, your Schema.org markup loses most of its local search value.

---

## 📰 Blog / Magazine

**Best for:** News sites, personal blogs, online magazines, educational content hubs, niche media, corporate blogs.

### What this preset configures

| Tab | Setting | Value applied |
|-----|---------|---------------|
| Schema.org | Schema Type | Auto-detect (Article/NewsArticle per page) |
| Schema.org | FAQ Auto-Detect | Yes |
| Sitemap | Articles Priority | 1.0 (highest) |
| Sitemap | Articles Frequency | Daily |
| Sitemap | Categories Priority | 0.7 |
| Sitemap | Menu Items Priority | 0.5 |

### After applying

For blog/magazine sites, the most impactful additional configurations are:

| Configuration | Location | Why |
|---------------|----------|-----|
| OG Default Image | Social & Meta tab | Hero image shown when articles are shared socially |
| Social Media Links | Organization tab | Establishes author entity for Google's authoritativeness signals |
| GA4 Measurement ID | Analytics tab | Track reader engagement and traffic sources |
| LLMs.txt | Analytics tab *(Developer/Agency)* | AI assistants discover your articles and cite them in answers |
| IndexNow | Analytics tab *(Developer/Agency)* | New articles indexed within minutes, not days |

---

## 🛍️ E-commerce / Online Shop

**Best for:** Online stores, product catalogs, digital downloads, subscription services, marketplaces.

### What this preset configures

| Tab | Setting | Value applied |
|-----|---------|---------------|
| Schema.org | Schema Type | Organization |
| Schema.org | FAQ Auto-Detect | Yes |
| Sitemap | Categories Priority | 0.8 (high — product categories are key) |
| Sitemap | Articles Priority | 0.7 |

### After applying

E-commerce sites benefit most from:

| Configuration | Location | Why |
|---------------|----------|-----|
| Meta Pixel ID | Social & Meta tab | Facebook/Instagram ad conversion tracking |
| Meta Pixel Events | Social & Meta → Advanced | Track Add to Cart, Purchase, Lead events |
| IndexNow | Analytics tab *(Developer/Agency)* | New products indexed rapidly for competitive search terms |
| Organization Description | Organization tab | Describe what you sell — AI engines use this for product search |

> **Note on product Schema:** JoomlaBoost does not automatically generate per-product `Product` Schema for Joomla shop extensions (VirtueMart, J2Store, etc.) — it generates site-level Organization and Article Schema. For product-level Schema, use your shop extension's built-in Schema features.

---

## 🎯 Generic Business / Corporate

**Best for:** Agencies, consulting firms, law firms, accounting practices, healthcare providers, service businesses, non-profits, portfolios, SaaS companies.

### What this preset configures

| Tab | Setting | Value applied |
|-----|---------|---------------|
| Schema.org | Schema Type | Organization |
| Schema.org | FAQ Auto-Detect | Yes |
| Sitemap | Articles Priority | 0.8 |
| Sitemap | Menu Items Priority | 0.7 (high — service pages matter) |

### After applying

Corporate/service sites benefit most from:

| Configuration | Location | Why |
|---------------|----------|-----|
| Organization Description | Organization tab | AI engines read this to understand what you do |
| Social Media Links (all) | Organization tab | Multiple `sameAs` references strengthen entity recognition |
| GSC Verification Token | Analytics tab | Monitor search performance in Google Search Console |
| GA4 Measurement ID | Analytics tab | Track visitor behaviour and lead generation |
| Manual FAQ | Schema.org tab *(Developer/Agency)* | Answer common client questions — eligible for FAQ rich results |

---

## Preset Application History

JoomlaBoost stores the last applied preset name and timestamp in hidden fields (`vertical_preset_last` and `vertical_preset_last_at`). This is for internal reference — the information is not displayed in the UI.

---

## After Applying Any Preset

1. **Review each tab** — presets apply sensible defaults, but your specific situation may need adjustments.
2. **Fill in the Organization tab** — presets do not touch your business name, address, or contact details.
3. **Upload the OG Default Image** — presets enable OpenGraph but cannot supply your image.
4. **Enable analytics** — GA4, GSC, and Meta Pixel require your personal IDs, which the preset cannot provide.
5. **Save & verify** — visit `yoursite.com/sitemap.xml`, `robots.txt`, and check page source for `ld+json`.

---

*← [Debug & Performance Tab](debug-performance.md) | [Documentation Index](index.md) | [Per-Article Overrides →](per-article-overrides.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
