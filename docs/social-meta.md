# Social & Meta — OpenGraph & Meta Pixel

The **Social & Meta** settings manage two features: OpenGraph tags for rich social sharing previews, and Meta Pixel (formerly Facebook Pixel) for advertising and conversion tracking.

---

## Open Graph

OpenGraph is a protocol (created by Facebook, now used universally) that controls how your pages appear when shared on social platforms. When enabled, AI Boost for Joomla injects `og:*` and `twitter:*` meta tags into the `<head>` of every page.

Platforms that use OpenGraph: Facebook, LinkedIn, WhatsApp, Slack, Telegram, Discord, iMessage link previews, Twitter/X.

### Enable OpenGraph

**Field:** `enable_opengraph`  
**Default:** Yes

Master toggle. When **Yes**, AI Boost for Joomla injects the full set of OpenGraph and Twitter Card meta tags.

> **Conflict warning:** If another SEO plugin (Sh404SEF, YooSEO, OSMap, etc.) also generates OpenGraph tags, disable that feature in the other plugin to avoid duplicate `og:` tags. AI Boost for Joomla deduplicates its own output but cannot remove tags added by other plugins.

### OG Site Name (Multilingual)

**Field:** `og_site_name_{lang}`  
**Example:** `Acme Hotel Manhattan`

Your brand name as displayed in social share card headers. If left empty, falls back to Joomla's configured site name, then the Organization Name.

### OG Default Image (Multilingual)

**Field:** `og_image_{lang}`

The default image shown in social preview cards when no article-specific image is available.

**Recommendations:**
- **Size:** 1200×630 pixels (16:9 ratio) — optimal for all major platforms
- **Format:** JPG or PNG (avoid SVG — it is not supported by most platforms)
- **Content:** Brand visual with logo and site name works well as a default

**Priority chain for the OG image on article pages:**

```
1. Custom Field: custom_og_image   (article-specific override)
2. Article Featured Image          (automatically used if set)
3. Global OG Default Image         (this field — the fallback)
4. Organization Logo               (last-resort fallback)
```

> See [Per-Article Overrides](per-article-overrides.md) for how to set a custom OG image per article.

---

### Per-Article OpenGraph Overrides

AI Boost for Joomla reads Joomla Custom Fields to override OG tags for specific articles. Create fields with these exact names in **Content → Fields**:

| Custom Field Name | Type | Effect |
| ------------------- | ------ | -------- |
| `custom_og_image` | Media | Overrides OG image for this article |
| `custom_og_title` | Text | Overrides OG title (defaults to article title) |
| `custom_og_description` | Textarea | Overrides OG description (defaults to meta description) |

This approach lets you set perfect social previews for important articles without changing site-wide defaults. See [Per-Article Overrides](per-article-overrides.md) for setup instructions.

---

## Meta Pixel (Facebook Ads Tracking)

Meta Pixel (formerly Facebook Pixel) is required for Facebook and Instagram advertising conversion tracking, audience retargeting, and lookalike audience creation.

### Enable Meta Pixel

**Field:** `enable_meta_pixel`  
**Default:** No

When **Yes**, AI Boost for Joomla injects the Pixel base code on every page.

### Primary Pixel ID

**Field:** `meta_pixel_id`  
**Example:** `123456789012345`

Your 15-digit Meta Pixel ID from **Facebook Business Manager → Events Manager → Pixels**. Find it by clicking on your pixel and copying the numeric ID displayed.

### Secondary Pixel ID (Optional)

**Field:** `meta_pixel_id_2`  
**Example:** `784973044476993`

A second Pixel ID for simultaneous tracking under two different Meta Ad Accounts. This is useful for agency setups where both the agency and the client need to track the same site. Leave empty if you only have one pixel.

### GDPR Consent Mode

**Field:** `pixel_consent_mode`  
**Default:** None

| Option | Behavior | Use when |
| -------- | ---------- | --------- |
| **None (Direct inject)** | Pixel fires immediately on page load | Non-EU sites or sites handling consent elsewhere |
| **YooTheme Pro 5** | Pixel waits for user to accept "Marketing" category in YooTheme Consent Manager | Sites using YooTheme Pro 5 as the template |

> **Legal requirement (EU/EEA):** Under GDPR and the ePrivacy Directive, the Meta Pixel requires explicit user consent before loading. If your site targets EU users and you do not have a compliant consent mechanism, use a Joomla cookie consent plugin alongside AI Boost for Joomla's pixel integration.

### Facebook Domain Verification

**Field:** `fb_domain_verification`  
**Example:** `abcdefgh1234567890`

The verification code from **Facebook Business Manager → Brand Safety → Domains**. Paste only the `content` value (the alphanumeric code), not the full `<meta>` tag HTML.

Domain verification is required for:
- Running Facebook conversion ads with CAPI (Conversions API)
- Sharing pixel events with multiple ad accounts
- Accessing some Business Manager features

---

## Meta Pixel Events — Advanced

> **Visible when:** Show Advanced Options = Yes (set in the Setup area)

Standard Pixel events enable specific conversion tracking beyond the automatic PageView event that fires on every page load.

| Event | Field | Fires when |
| ------- | ------- | ----------- |
| **Purchase** | `meta_pixel_track_purchase` | A purchase or order is completed |
| **Add to Cart** | `meta_pixel_track_add_to_cart` | A product is added to cart |
| **Contact** | `meta_pixel_track_contact` | A contact form is submitted |
| **Lead** | `meta_pixel_track_lead` | A lead or inquiry form is submitted |

> **Note:** These events are injected as client-side JavaScript calls. For e-commerce events (Purchase, Add to Cart), accurate firing requires that your shop or booking component fires the event at the right moment. Contact-form and lead events fire when a Joomla contact component page loads — which may not perfectly match your actual form submission flow. For precise e-commerce tracking, consider server-side events via Meta CAPI.

---

## Recommended Settings

| Setting | Recommended value |
| --------- | ------------------ |
| Enable OpenGraph | Yes |
| OG Site Name | Your brand name |
| OG Default Image | Upload a 1200×630 image |
| Enable Meta Pixel | Yes (if running Facebook/Instagram Ads) |
| GDPR Consent Mode | YooTheme Pro 5 (if using that template) or None |
| Secondary Pixel ID | Only if needed for dual-account tracking |
| Facebook Domain Verification | Paste token if running conversion ads |

---

*← [Sitemap](sitemap.md) | [Documentation Index](index.md) | [Analytics & Indexing →](analytics-indexing.md)*

*AI Boost for Joomla v0.73.15 — © 2025–2026 AI Boost (aiboostnow.com).*
