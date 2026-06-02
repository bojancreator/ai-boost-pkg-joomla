# Debug & Performance Tab — Caching, Debug Mode & Staging

The **Debug & Performance** tab covers three areas: output caching for site speed, debug tools for troubleshooting, and the staging badge for development environments.

---

## Performance — Caching

JoomlaBoost performs several operations on each page request: generating Schema.org JSON-LD, building the XML sitemap, composing robots.txt, and resolving hreflang tags. Caching stores the computed results so subsequent requests are served instantly from cache rather than recomputed.

### Enable Caching

**Field:** `enable_caching`  
**Default:** Yes

When **Yes**, JoomlaBoost caches computed outputs (Schema JSON-LD, sitemap XML, robots.txt content, hreflang data) using Joomla's built-in cache system.

**Always leave enabled on production sites.** Disabling caching causes JoomlaBoost to recompute all outputs on every single page request, which adds unnecessary server load — especially on high-traffic sites.

> **Cache invalidation:** When you publish, update, or unpublish an article, or when you save plugin settings, the cache is automatically invalidated. You do not need to manually clear it.

### Cache TTL (Time To Live)

**Field:** `cache_ttl`  
**Default:** `3600` (1 hour)  
**Range:** 60 seconds minimum — 86400 seconds (24 hours) maximum  
**Visible when:** Enable Caching = Yes

How long cached data is kept before it is regenerated. The default of 3600 seconds (1 hour) is appropriate for most sites.

| TTL value | Scenario |
|-----------|---------|
| 60–300 | High-traffic news sites publishing several times per day |
| 3600 | Standard recommended value (most sites) |
| 86400 | Static brochure sites that rarely change |

---

## Debug Mode

Debug tools are intended for troubleshooting only. **Never leave debug settings enabled on a production site** — they expose internal information and can slow the site for visitors.

### Enable Debug Mode

**Field:** `debug_mode`  
**Default:** No

When **Yes**, JoomlaBoost outputs detailed flash messages in the **Joomla administrator panel** after each page render. These messages list every operation performed:

- Schema.org: type generated, properties included
- Canonical tag: URL used
- Hreflang: languages and URLs injected
- Robots.txt: rules applied
- IndexNow: ping sent / skipped / failed
- Cache: hit or miss per component

Use debug mode when:
- Schema is not appearing in page source
- Hreflang tags seem incorrect
- IndexNow pings are not being sent
- You want to see exactly what JoomlaBoost does on a specific page

### HTML Wrap Markers

**Field:** `debug_wrap_markers`  
**Default:** No  
**Visible when:** Debug Mode = Yes

When **Yes**, JoomlaBoost wraps every block it injects in HTML comments that identify the start and end of each output. This makes it easy to locate and inspect JoomlaBoost's output in the page HTML source.

**Example:**
```html
<!-- JoomlaBoost: Schema.org JSON-LD start -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Hotel",
  "name": "Acme Hotel Manhattan",
  ...
}
</script>
<!-- JoomlaBoost: Schema.org JSON-LD end -->

<!-- JoomlaBoost: OpenGraph start -->
<meta property="og:type" content="website" />
<meta property="og:title" content="Acme Hotel Manhattan" />
...
<!-- JoomlaBoost: OpenGraph end -->
```

This is the fastest way to confirm which blocks JoomlaBoost is (or is not) outputting, and to identify conflicts with other plugins.

---

## Staging

### Show Staging Badge

**Field:** `show_staging_badge`  
**Default:** No

When **Yes**, a visible overlay badge is displayed on every frontend page. The badge shows:
- "STAGING" label
- JoomlaBoost version number
- Current domain
- Current date and time

**⚠️ Never enable on a live production site.**

When this setting is active, JoomlaBoost also automatically injects:

```html
<meta name="robots" content="noindex,nofollow">
```

…on every page, preventing the staging site from being indexed by search engines.

**Intended use:** Enable on development or staging servers to:
- Visually confirm you are viewing the staging site (not production)
- Guarantee the staging site cannot accidentally be indexed
- Share staging previews with clients without confusion

---

## Troubleshooting with Debug Mode

**Step-by-step process for diagnosing issues:**

1. Enable **Debug Mode** in this tab.
2. Enable **HTML Wrap Markers**.
3. Set **Enable Caching** to **No** temporarily (to bypass cached output).
4. Save settings.
5. Visit the problematic page on the frontend.
6. Right-click the page → **View Page Source**.
7. Search (`Ctrl+F`) for `JoomlaBoost` — all injected blocks are marked.
8. If no blocks appear, Schema.org, OpenGraph, or another feature may be off, or a conflicting plugin may be stripping them.
9. Check the Joomla admin flash messages for detailed operation logs.
10. After troubleshooting, re-enable caching and disable debug mode.

---

## Recommended Settings (Debug & Performance Tab)

| Setting | Production | Development / Staging |
|---------|:----------:|:---------------------:|
| Enable Caching | Yes | Optional |
| Cache TTL | 3600 | 60–300 |
| Debug Mode | **No** | Yes (temporary) |
| HTML Wrap Markers | **No** | Yes (temporary) |
| Show Staging Badge | **No** | Yes |

---

*← [Analytics & Indexing Tab](analytics-indexing.md) | [Documentation Index](index.md) | [Vertical Presets Guide →](vertical-presets.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
