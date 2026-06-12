# Debug & Diagnostics

Diagnostic tools live on **ADVANCED → Debug** in the admin sidebar. They help you see exactly what AI Boost for Joomla does on a page and capture errors for support requests.

> **Editions note:** the Debug & Diagnostics toolset is included in **Pro**. The **Health** page (OVERVIEW → Health) is included in every edition and covers the most common diagnostics with one-click fix actions.

---

## Debug options

### Enable debug mode (verbose logging)

Turns on verbose logging of AI Boost operations. Use it temporarily while diagnosing a problem, then switch it off — verbose logging has no value on a healthy production site.

### Hide comments in HTML source

By default, AI Boost wraps everything it injects in one consolidated, clearly marked block per region:

```html
<!-- AI Boost for Joomla - Start -->
<script type="application/ld+json">…</script>
<meta property="og:title" content="…" />
…
<!-- AI Boost for Joomla - End -->
```

This makes it easy to find AI Boost's output in the page source (`Ctrl+F` → `AI Boost`). If you prefer a cleaner HTML source, enable **Hide comments** — the inner comments are removed while the outer start/end pair is kept.

### Staging mode

Enable this on a development or staging copy of your site. While active, AI Boost **suppresses real-world side effects** — analytics tracking, IndexNow pings and redirect execution — so your test site cannot pollute your production analytics or notify search engines about staging URLs.

**Never leave staging mode enabled on the live site.**

---

## Error logging

AI Boost keeps its own error log (visible from the **Health** page):

- **Enable AI Boost error log** — master toggle.
- **Minimum severity to log** — choose how verbose the log is:

| Level | When to use |
|-------|-------------|
| Debug (very verbose) | Troubleshooting only |
| Info | Detailed activity |
| Warning | Recommended default |
| Error only | Quietest |

---

## Troubleshooting workflow

1. Open **OVERVIEW → Health** and click **Re-run Checks**. Most problems (missing schema, sitemap issues, conflicting plugins, duplicate tags) are caught here, and every failed check links straight to the setting that fixes it.
2. If you need more detail, enable **debug mode** on **ADVANCED → Debug**.
3. Visit the problematic page on the front end → right-click → **View Page Source** → search for `AI Boost` to inspect the consolidated output block.
4. If no block appears, another extension may be stripping it — see [Compatibility](compatibility.md) (JCH Optimize and aggressive minifiers are the usual suspects).
5. Check the **Error Log** (via Health) for warnings or errors.
6. When you contact support, use **ADVANCED → Help → Support Request** — it builds a copyable report with your site details, Health result and active plugins.
7. After troubleshooting, switch debug mode off again.

---

## Performance notes

AI Boost is designed to be light at runtime: all head and body output is computed once per request and written through Joomla's document APIs into a single consolidated block. There is no separate AI Boost cache to configure — standard Joomla caching (System → Global Configuration → System → Cache) and any page cache you already use work normally alongside it.

---

## Recommended settings

| Setting | Production | Development / Staging |
|---------|:----------:|:---------------------:|
| Debug mode | **No** | Yes (temporary) |
| Hide comments in HTML source | optional | No |
| Staging mode | **No** | **Yes** |
| Error log | Yes (Warning) | Yes (Debug/Info) |

---

*← [Analytics & Indexing](analytics-indexing.md) | [Documentation Index](index.md) | [Site Types →](vertical-presets.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
