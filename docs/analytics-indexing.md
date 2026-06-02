# Analytics & Indexing Tab — GSC, GA4, GTM, IndexNow & LLMs.txt

The **Analytics & Indexing** tab brings together all tracking integrations and the two key AI Search features: IndexNow for instant indexing and LLMs.txt for AI crawler visibility.

---

## Site Verification

### Enable Google Site Verification

**Field:** `enable_google_verification`  
**Default:** No

When **Yes**, JoomlaBoost injects the Google Search Console (GSC) ownership verification meta tag in the `<head>` of every page.

### Verification Token — Primary

**Field:** `gsc_verification_meta`

Paste the `content` value from the HTML tag method in Google Search Console:

1. Go to [Google Search Console](https://search.google.com/search-console) → Settings → Ownership Verification.
2. Click **HTML Tag** method.
3. Copy only the value inside `content="..."` — not the full `<meta>` tag.
4. Paste it into this field.

**Example:** `abcdefghij-1234567890ABCDEFGHIJ`

### Verification Token — Secondary (Optional)

**Field:** `gsc_verification_meta_2`

A second verification token — useful when multiple team members need their own GSC access under different Google accounts, or when verifying multiple GSC properties for the same domain (e.g., `https://` vs `http://` variants).

### Additional Verification HTML — Advanced

**Field:** `gsc_additional_html`  
**Visible when:** Show Advanced Options = Yes

Paste any additional `<meta>` tags or HTML that should be injected into `<head>`. Use this for:
- Bing Webmaster Tools verification
- Pinterest site claim
- Facebook domain verification (if not using Meta Pixel)
- Any other platform that requires a head meta tag

---

## Google Analytics 4

### Enable GA4

**Field:** `enable_ga4`  
**Default:** No

When **Yes**, JoomlaBoost injects the GA4 tracking script (`gtag.js`) on every page, enabling page view tracking and event collection in Google Analytics.

### Measurement ID

**Field:** `ga4_measurement_id`  
**Format:** `G-XXXXXXXXXX`

Find this in **Google Analytics → Admin → Data Streams → [your stream] → Measurement ID**.

### GDPR Consent Mode

**Field:** `ga4_consent_mode`  
**Default:** None

| Option | Behavior |
|--------|----------|
| **None (Direct inject)** | GA4 script loads immediately on page load — no GDPR consent check |
| **YooTheme Pro 5** | GA4 script is blocked until the user accepts the "Statistics" category in YooTheme's Consent Manager |
| **Via GTM** | No direct GA4 script is injected; GA4 is handled inside your GTM container |

> **If you use Google Tag Manager:** Select **Via GTM** here, leave the Measurement ID empty, and configure GA4 as a tag inside your GTM container. This prevents duplicate tracking.

> **GDPR note:** In the EU/EEA, loading Google Analytics without user consent violates GDPR. Use the YooTheme Consent Mode option or an equivalent Joomla cookie consent solution.

---

## Google Tag Manager

Google Tag Manager (GTM) lets you manage multiple tracking scripts, conversion pixels, and custom tags from a single dashboard without modifying Joomla code.

### Enable GTM

**Field:** `enable_gtm`  
**Default:** No

When **Yes**, JoomlaBoost injects the GTM container snippet in both `<head>` and `<body>` (as Google requires).

### Container ID

**Field:** `gtm_container_id`  
**Format:** `GTM-XXXXXXX`

Find this in **Google Tag Manager → Admin → Container Settings**.

> **YooTheme conflict note:** If YooTheme Pro's Customizer also injects a GA4 or GTM tag, disable it there and let JoomlaBoost manage the injection — otherwise you will get duplicate tracking.

---

## IndexNow — Instant Indexing

> **🔒 Requires Developer or Agency license.**  
> Starter and unlicensed users see an upgrade notice in this section.

IndexNow is a protocol supported by Bing, Yandex, and Seznam.cz that lets websites push URL change notifications directly to these search engines — instead of waiting for their crawlers to revisit.

**Result:** New and updated articles are indexed in minutes, not days.

### Enable IndexNow

**Field:** `indexnow_enabled`  
**Default:** No

When **Yes**, JoomlaBoost automatically sends an IndexNow ping to `api.indexnow.org` every time a Joomla article is published, updated, or unpublished.

### Generate API Key

Click the **🔑 Generate API Key** button to create a random 32-character hex API key. JoomlaBoost automatically:
1. Stores the key in the `indexnow_api_key` field
2. Creates the required key verification file at `yoursite.com/{your-api-key}.txt`

You do not need to register the key anywhere — the IndexNow protocol auto-discovers it from the verification file.

### API Key

**Field:** `indexnow_api_key`  
**Format:** 32-character hex string  
**Example:** `a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4`

Use the **Generate** button to create one automatically, or paste an existing key if you have already used IndexNow on this domain through another service.

### How IndexNow Works

```
Article published/updated in Joomla
        ↓
JoomlaBoost sends ping → api.indexnow.org
        ↓
Bing, Yandex, Seznam receive notification
        ↓
Search engines crawl the URL within minutes
        ↓
Page appears in search results faster
```

**Verify it is working:**
1. Visit `yoursite.com/{your-api-key}.txt` — it should return your API key as plain text.
2. After publishing an article, check **Bing Webmaster Tools → IndexNow** for submission logs.

---

## LLMs.txt — AI Crawler Visibility

> **🔒 Requires Developer or Agency license.**  
> Starter and unlicensed users see an upgrade notice in this section.

`llms.txt` is an emerging standard (analogous to `robots.txt` but for AI language models) that provides AI systems with a structured, human-readable summary of your site. AI assistants like ChatGPT, Claude, Perplexity, and Gemini can read this file to quickly understand what your site is about, what pages exist, and who you are.

**Standard reference:** [llmstxt.org](https://llmstxt.org)

### Enable LLMs.txt

**Field:** `llmstxt_enabled`  
**Default:** No

When **Yes**, JoomlaBoost dynamically serves `yoursite.com/llms.txt`. The file is auto-generated from:
- Organization name and description
- Contact information and social links
- Menu pages (with titles and descriptions where available)
- Guest ratings (if configured)
- Custom pages you add manually

### Custom Pages (Multilingual)

**Field:** `llmstxt_custom_pages_{lang}`

Add extra pages or important content not automatically discovered by JoomlaBoost. Format:

```json
[
  {
    "title": "About Us",
    "url": "/about",
    "description": "Our company history, team, and mission since 1999."
  },
  {
    "title": "Rooms & Suites",
    "url": "/rooms",
    "description": "Luxury rooms and suites with panoramic city views."
  },
  {
    "title": "Contact",
    "url": "/contact",
    "description": "Reservations, inquiries, and directions to our property."
  }
]
```

**After saving**, verify the file at `yoursite.com/llms.txt`.

**Example output:**
```
# Acme Hotel Manhattan

> A 4-star hotel in Manhattan, New York, USA, offering luxury accommodation since 1999.

Contact: info@acmehotel.com | +1 212 555 0123
Address: 123 W 44th St, New York, USA

## Pages

- [Home](https://yourdomain.com/)
- [About Us](https://yourdomain.com/about): Our company history, team, and mission since 1999.
- [Rooms & Suites](https://yourdomain.com/rooms): Luxury rooms and suites with panoramic city views.
- [Contact](https://yourdomain.com/contact): Reservations, inquiries, and directions to our property.
```

---

## Recommended Settings (Analytics & Indexing Tab)

| Setting | Recommended value |
|---------|------------------|
| Enable GSC Verification | Yes — paste your token from Google Search Console |
| Enable GA4 | Yes — paste your Measurement ID |
| GA4 Consent Mode | YooTheme Pro 5 (if applicable) or None |
| Enable GTM | Yes (if you manage tags via GTM) |
| Enable IndexNow | Yes — click Generate Key *(Developer/Agency)* |
| Enable LLMs.txt | Yes *(Developer/Agency)* |
| LLMs.txt Custom Pages | Add your key pages with descriptions |

---

*← [Social & Meta Tab](social-meta.md) | [Documentation Index](index.md) | [Debug & Performance Tab →](debug-performance.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
