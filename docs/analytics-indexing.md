# Analytics & Indexing — GSC, GA4, GTM, Meta Pixel, IndexNow & llms.txt

Tracking integrations live on **SEO → Analytics & Tracking** in the admin sidebar. The indexing and AI-visibility features (IndexNow, `llms.txt`) live on **AI VISIBILITY → AI Visibility**. This page covers both.

> **Editions note:** the analytics integrations (site verification, GA4, GTM, Meta Pixel) and IndexNow are included in **Pro** — they take effect only with the Pro Upgrade installed and activated (in the Free edition the Meta Pixel card appears as a locked card with an **Upgrade to Pro** button). `llms.txt`, Markdown pages and AI signals are included in Free.

---

## Site Verification (Pro)

**Where:** SEO → Analytics & Tracking → Site Verification

Injects ownership verification tags into the `<head>` of every page:

- **Google Search Console verification** — enable the toggle, then paste one or more **GSC verification codes**. Get the code from [Google Search Console](https://search.google.com/search-console) → Settings → Ownership Verification → **HTML Tag** — copy only the value inside `content="..."`, not the whole tag. You can add multiple codes (useful when several team members verify under different Google accounts).
- **Facebook domain verification** — paste the code from Meta Business Manager.
- **Additional verification HTML** — paste any other `<meta>` tags a platform requires (Bing Webmaster Tools, Pinterest, etc.).

---

## Google Analytics 4 (Pro)

**Where:** SEO → Analytics & Tracking → Google Analytics 4

- **Enable Google Analytics 4** — master toggle.
- **GA4 Measurement ID** — format `G-XXXXXXXXXX`; find it in **Google Analytics → Admin → Data Streams → [your stream]**.
- **GDPR Consent Mode** — choose how GA4 loads:

| Option | Behaviour |
|--------|-----------|
| None (direct inject — no GDPR) | GA4 loads immediately on every page |
| Via GTM (skip direct GA4) | No direct GA4 script — configure GA4 inside your GTM container instead |
| YooTheme Pro Consent Manager (Consent Mode v2) | GA4 respects the consent state from YooTheme's Consent Manager |
| Consent denied by default (custom CMP) | GA4 starts with consent denied until your consent platform grants it |

> **If you use Google Tag Manager:** select **Via GTM**, leave the Measurement ID empty, and configure GA4 as a tag inside GTM. This prevents double tracking.

> **GDPR note:** in the EU/EEA, loading Google Analytics without user consent violates GDPR. Use a consent mode option together with a cookie consent solution.

---

## Google Tag Manager (Pro)

**Where:** SEO → Analytics & Tracking → Google Tag Manager

- **Enable Google Tag Manager** — injects the GTM container snippet in both `<head>` and `<body>`, as Google requires.
- **GTM Container ID** — format `GTM-XXXXXXX`; find it in **Google Tag Manager → Admin → Container Settings**.

> **YooTheme conflict note:** if YooTheme Pro's Customizer also injects a GA4 or GTM tag, disable it there and let AI Boost manage the injection — otherwise you will get duplicate tracking.

---

## Meta Pixel (Pro)

**Where:** SEO → Analytics & Tracking → Meta Pixel

- **Enable Meta Pixel** — injects the Meta (Facebook/Instagram) pixel.
- **Meta Pixel IDs** — one or more pixel IDs.
- **Consent Mode** — direct inject, or consent-required (the pixel is revoked until consent is granted).
- **Standard Events** — fire standard conversion events (Purchase, Lead, ViewContent, Search, AddToCart, Contact, Subscribe and more) based on the visited page.
- **Custom Events** — define your own event name + URL pattern pairs.

---

## IndexNow — Instant Indexing (Pro)

**Where:** AI VISIBILITY → AI Visibility → IndexNow

IndexNow is a protocol supported by Bing and other IndexNow-enabled search engines that lets your site push URL changes directly instead of waiting for the next crawl. New and updated articles get crawled in minutes, not days.

- **Enable IndexNow** — master toggle.
- **API Key** — click **Generate** to create a key. AI Boost stores it and serves the required verification file at `yoursite.com/{your-api-key}.txt` automatically — you do not need to register the key anywhere.
- **Auto-submit URLs on article publish / update** — sends a ping to `api.indexnow.org` whenever a Joomla article is published, updated or unpublished.

**Verify it is working:**

1. Visit `yoursite.com/{your-api-key}.txt` — it should return your API key as plain text.
2. After publishing an article, check **Bing Webmaster Tools → IndexNow** for submission logs.

---

## llms.txt — AI Site Index (Free)

**Where:** AI VISIBILITY → AI Visibility → llms.txt

`llms.txt` is an emerging standard ([llmstxt.org](https://llmstxt.org)) — a structured, human-readable summary of your site that AI assistants (ChatGPT, Claude, Perplexity, Gemini) read to understand what your site is about.

- **Enable /llms.txt** — serves the file dynamically at `yoursite.com/llms.txt`.
- **Site Description for AI** — a short description of your site (translatable per language with Pro).
- **Recent Articles** — how many recent articles to list (1–50).
- **Custom Pages** — add rows of URL + description for important pages that should always be listed.

**After saving**, verify the file at `yoursite.com/llms.txt`.

### llms-full.txt — Full Site Index (Pro)

**Where:** AI VISIBILITY → AI Visibility → llms-full.txt

Serves `yoursite.com/llms-full.txt` — a full index of your articles and categories for AI crawlers, with a configurable maximum article count.

### Markdown Pages & AI Signals (Free)

Also on the AI Visibility page:

- **Markdown Pages** — serves any page as clean Markdown for AI agents, triggered by a `.md` URL suffix, a `?markdown=1` query parameter, or an `Accept: text/markdown` request header.
- **AI Signals** — adds AI-oriented meta tags.

---

## Recommended Settings

| Setting | Recommended value |
|---------|------------------|
| Site verification | Yes — paste your GSC code *(Pro)* |
| Google Analytics 4 | Yes — paste your Measurement ID, pick a consent mode *(Pro)* |
| Google Tag Manager | Yes, if you manage tags via GTM *(Pro)* |
| IndexNow | Yes — click Generate, enable auto-submit *(Pro)* |
| llms.txt | Yes — fill in the Site Description for AI |
| Markdown Pages | Yes |

---

*← [Social & Meta](social-meta.md) | [Documentation Index](index.md) | [Debug & Diagnostics →](debug-performance.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
