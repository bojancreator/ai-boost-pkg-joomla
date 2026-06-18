# Compatibility Matrix

AI Boost for Joomla is tested against a range of Joomla versions, PHP versions, templates, and third-party extensions. Use this page to verify compatibility before installing.

---

## Joomla Versions

| Joomla Version | Status | Notes |
|----------------|--------|-------|
| 3.x / 4.x | ❌ Not supported | AI Boost for Joomla requires Joomla 5.0 minimum |
| 5.0 – 5.x | ✅ Fully supported | Primary development target |
| 6.x | ✅ Fully supported | Tested on Joomla 6.1 |

---

## PHP Versions

| PHP Version | Status | Notes |
|-------------|--------|-------|
| 7.x / 8.0 | ❌ Not supported | 8.1 is the minimum |
| 8.1 | ✅ Supported | Minimum required version |
| 8.2 | ✅ Fully supported | |
| 8.3 | ✅ Fully supported | Tested and compatible |
| 8.4 | ✅ Supported | |
| 8.5 | ✅ Supported | Tested and compatible |

> **Recommended:** PHP 8.2 or newer for performance and security.

---

## Database

| Database | Version | Status |
|----------|---------|--------|
| MySQL | 5.7+ | ✅ Supported |
| MySQL | 8.0+ | ✅ Recommended |
| MariaDB | 10.3+ | ✅ Supported |
| MariaDB | 10.6+ | ✅ Recommended |
| PostgreSQL | — | ❌ Not supported |

---

## Joomla Templates

| Template | Compatibility | Notes |
|----------|:-------------:|-------|
| Cassiopeia (Joomla default) | ✅ Full | |
| YooTheme Pro 4.x / 5.x | ✅ Full | Includes a GA4 consent-mode option for YooTheme's Consent Manager |
| Helix Ultimate | ✅ Full | |
| T4 Framework | ✅ Full | |
| Astroid Framework | ✅ Full | |

AI Boost for Joomla writes all of its head/body output through Joomla's document APIs into one consolidated, clearly marked block, so it works with any standards-compliant Joomla 5/6 template.

---

## Third-Party Plugins & Extensions

### SEO Extensions

| Plugin / Extension | Compatibility | Action required |
|-------------------|:-------------:|-----------------|
| Sh404SEF | ✅ Compatible | Disable Sh404's OpenGraph meta tag generation to avoid duplicate `og:` tags |
| JoomSEF | ✅ Compatible | Disable JoomSEF's meta tag generation |
| OSMap | ✅ Compatible | AI Boost's sitemap replaces OSMap's sitemap — disable OSMap's sitemap output to avoid conflicts |
| EasyBlog SEO | ✅ Compatible | Review EasyBlog's Schema.org output to ensure no duplicates |

> **Tip:** the default **Conflict Resolution Mode** (SEO → Technical SEO) is *Cooperative* — when another extension already emitted a tag, AI Boost skips its own copy instead of duplicating it. The **Health** page also runs a duplicate-tag and conflict scan.

### Performance & Caching

| Plugin / Extension | Compatibility | Action required |
|-------------------|:-------------:|-----------------|
| JCH Optimize | ✅ Compatible | Ensure JCH does not strip `<script type="application/ld+json">` — check JCH's "Exclude Scripts" settings |
| Joomla System Cache | ✅ Compatible | No action needed |
| CDN / Cloudflare | ✅ Compatible | Ensure edge caching does not serve stale `robots.txt` or `sitemap.xml` (set edge cache TTL appropriately) |

### Multilingual

| Plugin / Extension | Compatibility | Notes |
|-------------------|:-------------:|-------|
| Joomla Language Filter (native) | ✅ Full | Sitemap hreflang (Pro) uses Joomla's native language associations |
| Falang | ✅ Full | AI Boost detects Falang and serves translated values in its front-end output (Pro) |

See [Multilingual Sites](multilingual.md) for details.

### E-commerce

| Extension | Schema.org support | Notes |
|-----------|:-----------------:|-------|
| VirtueMart | ⚠️ Partial | AI Boost adds site-level Organisation schema; per-product schema is not covered — use VirtueMart's built-in schema or a dedicated product schema extension |
| J2Store | ⚠️ Partial | Same as VirtueMart |
| HikaShop | ⚠️ Partial | Same as VirtueMart |

### Analytics & Tracking

| Plugin / Tool | Compatibility | Notes |
|--------------|:-------------:|-------|
| YooTheme Customizer Analytics | ⚠️ Avoid duplicates | Disable YooTheme's GA4 if you use AI Boost's GA4 integration (Pro), or route everything through GTM |
| Manually pasted Meta Pixel code | ⚠️ Avoid duplicates | Remove manual pixel code if you use AI Boost's Meta Pixel feature (Pro) |

### Events

| Plugin / Extension | Notes |
|-------------------|-------|
| JEvents / DPCalendar | Their events are not automatically added to the sitemap. For key events, use AI Boost's **Event Schema** (Pro, SEO → Schema.org) driven by a Joomla article category |

### Backup

| Plugin | Compatibility |
|--------|:-------------:|
| Akeeba Backup | ✅ Full — no conflicts |

---

## Known Conflicts

### OSMap — sitemap conflict

If both OSMap and AI Boost are active with sitemaps enabled, both attempt to serve `yoursite.com/sitemap.xml`, and whichever handles the URL first wins.

**Resolution:** disable OSMap's sitemap generation and keep AI Boost's sitemap active.

### JCH Optimize — JSON-LD stripping

JCH Optimize's HTML minification can occasionally strip or break `<script type="application/ld+json">` blocks.

**Resolution:** in JCH Optimize settings → Optimize JavaScript → add `application/ld+json` to the exclusion list, or disable inline script optimisation.

### Template-level OpenGraph tags

Some templates (including older YooTheme Pro and some Helix-based templates) generate their own OpenGraph tags.

**Resolution:** disable the template's OpenGraph generation (usually in the template's options or customizer) and let AI Boost be the sole OG generator. The Health page's conflict scan flags duplicate OG tags for you.

---

## Performance & memory footprint

AI Boost adds a small, bounded amount of work to each front-end page. All of its
output (Schema.org JSON-LD, OpenGraph, AEO signals, analytics, custom code) is
accumulated during Joomla's `onBeforeCompileHead` and written into the page in a
single consolidation pass (`HeadBlockBuilder` / `BodyBlockBuilder::finalize()`).

- **CPU:** the consolidation pass — encoding the JSON-LD graph and splicing the
  blocks into the page — measures **~0.01 ms** on a representative page (a graph
  of Organization + WebSite + BreadcrumbList + Article + an 8-item FAQ + a 6-step
  HowTo encodes to ~7 KB). In practice AI Boost's per-page overhead is well under
  1 ms; total request time is dominated by Joomla core and the active template.
- **Memory:** AI Boost's own structures (the schema graph plus the accumulated
  head/body sections) are on the order of tens of KB. They do not meaningfully
  raise a request's peak memory, which is governed by Joomla core and the
  template / page builder in use.

**Recommended PHP `memory_limit`: 128 MB.** This matches the Joomla project's own
guidance for Joomla 5/6 — 64 MB is the absolute floor, 128 MB is recommended for a
typical site with a handful of extensions and a modern template. AI Boost runs
comfortably within that budget; sites built on heavy page builders or with many
extensions should provision 256 MB.

**Measuring your own site:** turn on **Debug mode** (AI Boost → Settings → Debug).
On the front end AI Boost then records a perf line for each page — `finalize`
(consolidation time), `peak` (request peak memory) and `request` (total request
time) — to the PHP error log, and, where the server allows response-header
injection at that stage, as an `X-AiBoost-Perf` header. Turn Debug mode back off
on production.

---

## Minimum Server Requirements Summary

| Component | Minimum |
|-----------|---------|
| Joomla | 5.0.0 |
| PHP | 8.1.0 |
| PHP `memory_limit` | 128 MB recommended (64 MB absolute minimum) |
| MySQL / MariaDB | 5.7 / 10.3 |
| Admin access | Super Administrator |

---

*← [Troubleshooting](troubleshooting.md) | [Documentation Index](index.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
