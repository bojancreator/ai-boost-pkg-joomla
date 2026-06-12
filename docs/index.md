# AI Boost for Joomla — Documentation

**Compatible with:** Joomla 5.x and 6.x | PHP 8.1+
**Publisher:** AI Boost — [aiboostnow.com](https://aiboostnow.com)

---

## What is AI Boost for Joomla?

**AI Boost for Joomla** is an all-in-one SEO and AEO (Answer Engine Optimisation) package for Joomla. It generates structured data, an XML sitemap, OpenGraph tags, AI-readable signals, crawler rules and analytics integrations from one admin component — no coding required.

**Key benefits:**

- Get found by Google, Bing, ChatGPT, Perplexity and Google AI Overviews
- Schema.org JSON-LD for your organisation, articles and dozens of business types
- Dynamic XML sitemap, canonical URL management and 404 monitoring
- AI-aware `robots.txt` with per-bot allow/block controls for AI crawlers
- `llms.txt` and Markdown page endpoints — emerging standards for AI visibility
- OpenGraph and Twitter Card tags for rich social previews
- Built-in tools: Health check, Redirect manager, SEO Analyzer, URL Checker

---

## Editions: Free and Pro

AI Boost for Joomla ships in two editions:

- **AI Boost for Joomla (Free)** — the full SEO/AEO foundation: Schema.org core output, XML sitemap, technical SEO controls, OpenGraph and Twitter Cards, `llms.txt`, Markdown pages, crawler and robots.txt management, plus the Health, Redirects, Analyzer and URL Checker tools.
- **AI Boost for Joomla — Pro Upgrade** — an add-on package installed on top of Free that unlocks the advanced features listed below.

The Free edition shows the Pro features as **visible locked cards** in the admin panel, each with an **Upgrade to Pro** button. You can see exactly what Pro adds before buying — locked settings are dimmed, not hidden.

| Area | Free | Pro Upgrade adds |
| --- | --- | --- |
| Schema.org | Organisation/business schema (all site types), WebSite + SearchAction, Article schema, opening hours, breadcrumbs | FAQ/QAPage schema (auto-detect + manual builder), HowTo schema, Event schema, Author Entity (Person), extended business details, Services & Prices |
| Technical SEO | Title and meta description templates, canonical URL management, 404 logging, conflict resolution, domain controls | — |
| Sitemap | XML sitemap (articles, categories, menu items), change frequency, default priority | Sitemap index, image sitemap, hreflang in sitemap, tag URLs, per-type priorities, Google News sitemap |
| Social Meta / OG | OpenGraph site name, default share image, Twitter Cards | Per-article OG overrides (custom fields), `og:locale`, Facebook App ID, Twitter site handle |
| Analytics & Tracking | — | Google Analytics 4, Google Tag Manager, site verification (Google Search Console, Bing, Facebook), Meta Pixel with standard and custom events |
| AI Visibility | `llms.txt` with custom pages, Markdown page endpoint, AI signals | `llms-full.txt` full-site index, IndexNow instant indexing |
| Crawlers & Robots | robots.txt management, SEO scraper blocking, per-bot AI crawler rules | — |
| Multilingual output | — | Per-language translations of schema/OG values, Falang integration |
| Custom Code | — | Head/body/footer code injection with per-menu-item scope |
| Tools | Health check, Redirects (+ 404 log, CSV import), SEO Analyzer, JSON-LD Validator, URL Checker, Import/Export | — |

Pro is sold as a yearly subscription in three plans that differ **only in the number of sites** — see [Licence & Plans](license-plans.md) for pricing, the perpetual activation promise and what licence expiry means.

---

## Documentation Pages

### Getting Started
- [Getting Started Guide](getting-started.md) — install Free and Pro, verify your licence, run the Autopilot checklist
- [Licence & Plans](license-plans.md) — pricing, activation, updates and support
- [Admin Navigation Guide](plugin-tab.md) — where every setting lives in the admin sidebar

### Feature Guides
- [Site Identity](organization.md) — business identity, contact info, social links, location
- [Schema.org](schema-org.md) — structured data, FAQ, HowTo and Event schema
- [Site Types](vertical-presets.md) — choosing the right business/organisation schema type
- [Sitemap](sitemap.md) — XML sitemap and hreflang
- [Social & Meta](social-meta.md) — OpenGraph, Twitter Cards, Meta Pixel
- [Analytics & Indexing](analytics-indexing.md) — GSC, GA4, GTM, Meta Pixel, IndexNow, llms.txt
- [Per-Article Overrides](per-article-overrides.md) — per-article OG custom fields (Pro)
- [Multilingual Sites](multilingual.md) — sitemap hreflang, Falang and per-language output
- [Debug & Diagnostics](debug-performance.md) — debug mode, error log, staging mode

### Reference
- [Troubleshooting](troubleshooting.md) — common issues and solutions
- [Compatibility Matrix](compatibility.md) — Joomla versions, PHP, templates, third-party extensions
- [Uninstall Guide](uninstall-guide.md) — what is removed, what survives, how to reinstall
- [Changelog](changelog.md) — release notes

---

## Quick Verification Checklist

After installation and initial setup, verify these URLs work:

| URL | Expected result |
| --- | --- |
| `yoursite.com/sitemap.xml` | XML sitemap with your pages |
| `yoursite.com/robots.txt` | robots.txt containing the AI Boost managed block |
| `yoursite.com/llms.txt` | AI-readable site summary |
| View source → `application/ld+json` | Schema.org JSON-LD inside the `<!-- AI Boost for Joomla - Start -->` block |

The **Health** page (sidebar **OVERVIEW → Health**) runs these checks for you and links every problem to the setting that fixes it.

---

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)). All rights reserved.*
*[Documentation](https://aiboostnow.com/docs) | [info@aiboostnow.com](mailto:info@aiboostnow.com)*
