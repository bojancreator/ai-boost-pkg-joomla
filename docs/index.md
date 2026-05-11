# AI Boost (JoomlaBoost) — Documentation

**Version:** 0.24.0  
**Compatible with:** Joomla 4.x, 5.x, 6.x | PHP 8.1+  
**Publisher:** AI Boost Now — [aiboostnow.com](https://aiboostnow.com)

---

## What is JoomlaBoost?

JoomlaBoost is an all-in-one SEO and AEO (Answer Engine Optimization) system plugin for Joomla. It automatically generates structured data, XML sitemaps, OpenGraph tags, AI-readable signals, and analytics integrations — everything in one place, with no coding required.

**Key benefits:**
- Get found by Google, Bing, ChatGPT, Perplexity, and Google AI Overviews
- Schema.org JSON-LD for every page type (Organization, Article, FAQ, Events, Hotel, LocalBusiness)
- Dynamic XML sitemap with hreflang for multilingual sites
- AI-aware `robots.txt` that explicitly allows all major AI crawlers
- `llms.txt` — the emerging standard for AI crawler visibility
- Google Analytics 4, GTM, Meta Pixel with GDPR consent support
- IndexNow for instant page indexing on Bing, Yandex, and Seznam

---

## License Tiers

| Feature | Unlicensed | Starter | Developer | Agency |
|---------|:----------:|:-------:|:---------:|:------:|
| Schema.org, Sitemap, OpenGraph, Robots.txt | ✓ | ✓ | ✓ | ✓ |
| GA4, GTM, GSC Verification | ✓ | ✓ | ✓ | ✓ |
| FAQ Auto-detect | ✓ | ✓ | ✓ | ✓ |
| Meta Pixel | ✓ | ✓ | ✓ | ✓ |
| IndexNow | — | — | ✓ | ✓ |
| LLMs.txt | — | — | ✓ | ✓ |
| Manual FAQ (multilingual) | — | — | ✓ | ✓ |
| Events Schema | — | — | ✓ | ✓ |
| Number of sites | — | 1 | 5 | Unlimited |

> Features marked **Developer / Agency** display an upgrade notice in the admin panel for Starter and unlicensed users.

---

## Documentation Pages

### Getting Started
- [Getting Started Guide](getting-started.md) — Installation, license activation, first 5-minute setup
- [License Plans & Feature Gating](license-plans.md) — Tiers, what's included, how to activate

### Admin Panel — Tab by Tab
- [Plugin Tab](plugin-tab.md) — Quick Setup, Vertical Presets, Domain, Robots.txt
- [Organization Tab](organization.md) — Business identity, contact info, social links, location
- [Schema.org Tab](schema-org.md) — Structured data, FAQ Schema, Events Schema
- [Sitemap Tab](sitemap.md) — XML Sitemap, Hreflang tags
- [Social & Meta Tab](social-meta.md) — OpenGraph, Twitter Cards, Meta Pixel
- [Analytics & Indexing Tab](analytics-indexing.md) — GSC, GA4, GTM, IndexNow, LLMs.txt
- [Debug & Performance Tab](debug-performance.md) — Caching, Debug mode, Staging badge

### Guides
- [Vertical Presets Guide](vertical-presets.md) — Hotel, Restaurant, Blog, E-commerce, Generic presets
- [Per-Article Overrides](per-article-overrides.md) — Custom Fields for per-page OG and Schema control
- [Multilingual Sites](multilingual.md) — Hreflang, Falang, native Joomla multilingual
- [Troubleshooting](troubleshooting.md) — Common issues and solutions
- [Compatibility Matrix](compatibility.md) — Joomla versions, PHP, templates, third-party plugins

---

## Quick Verification Checklist

After installation and initial setup, verify these URLs work:

| URL | Expected result |
|-----|-----------------|
| `yoursite.com/sitemap.xml` | XML sitemap with your pages |
| `yoursite.com/robots.txt` | AI-aware robots.txt with `Allow:` rules |
| `yoursite.com/llms.txt` | AI-readable site summary *(Developer/Agency)* |
| View source → `application/ld+json` | Schema.org JSON-LD block |

---

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now. All rights reserved.*  
*[aiboostnow.com/docs](https://aiboostnow.com/docs) | support@aiboostnow.com*
