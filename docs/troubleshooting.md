# Troubleshooting — Common Issues & Solutions

This page covers the most frequently reported issues with AI Boost for Joomla and their solutions.

---

## Sitemap Issues

### Sitemap returns a 404 error

**Symptoms:** Visiting `yoursite.com/sitemap.xml` shows a "Page not found" error.

**Causes and fixes:**

1. **Static file conflict** — A physical `sitemap.xml` file in your Joomla root takes precedence.  
   *Fix:* Log in via FTP or your hosting File Manager. Check if `sitemap.xml` exists in the root directory. If yes, delete or rename it.

2. **SEF URLs not enabled** — AI Boost for Joomla uses Joomla's routing to serve the sitemap.  
   *Fix:* Go to **System → Global Configuration → Site → Search Engine Friendly URLs** and set it to **Yes**.

3. **Plugin not enabled** — The plugin is installed but not active.  
   *Fix:* Go to **System → Manage → Plugins** → search `AI Boost for Joomla` → enable it (green circle).

4. **Plugin order conflict** — Another system plugin handles the same URL first.  
   *Fix:* In the Plugins list, set AI Boost for Joomla's ordering to a lower number (earlier execution).

---

### Sitemap is missing some articles

**Symptoms:** The sitemap exists but not all articles appear.

**Causes and fixes:**

1. **Category filter active** — You selected specific categories in the Sitemap tab.  
   *Fix:* Go to **Sitemap tab → Article Options → Select Categories** and clear the selection (leave empty = all categories).

2. **Articles excluded by ID** — IDs listed in the Exclude field.  
   *Fix:* Go to **Sitemap tab → Advanced → Exclude Article IDs** and verify the list.

3. **Max Articles limit reached** — Articles past the cap are excluded.  
   *Fix:* Go to **Sitemap tab → Advanced → Max Articles** and set to `0` (unlimited).

4. **Articles not published** — Unpublished articles are never included.  
   *Fix:* Verify article status in Content → Articles.

---

## Robots.txt Issues

### robots.txt still shows the default Joomla content

**Cause:** A physical `robots.txt` file in the Joomla root overrides AI Boost for Joomla's dynamic version. Joomla creates this file by default.

**Fix:**
1. Connect via FTP or your hosting File Manager.
2. Navigate to your Joomla root directory.
3. Delete or rename `robots.txt`.
4. Visit `yoursite.com/robots.txt` — AI Boost for Joomla's version now serves.

---

## Schema.org Issues

### Schema.org JSON-LD is not appearing in page source

**Diagnosis steps:**
1. Open AI Boost for Joomla settings → **Schema.org tab** → confirm **Enable Schema Markup = Yes**.
2. Enable **Debug Mode** and **HTML Wrap Markers** in the **Debug tab** → Save.
3. Visit the affected page → View Page Source → search for `AI Boost for Joomla`.
4. If marked blocks appear with empty content, check the Organization Name is filled in.
5. If no blocks appear at all, a conflicting plugin may be stripping `<script>` tags.

**Common conflicting plugins:**
- JCH Optimize (HTML minification may strip JSON-LD) — whitelist the script type in JCH settings
- Page builder caches — clear all caches after enabling Schema
- Third-party caching plugins with aggressive HTML manipulation

**Fix:** Disable caching/minification plugins one by one to identify the conflict.

---

### Schema.org validation errors in Google Rich Results Test

**Cause:** Missing required fields for the selected schema type.

**Common errors and fixes:**

| Error | Fix |
|-------|-----|
| Missing `name` property | Fill in Organization Name (Organization tab) |
| Missing `address` for LocalBusiness | Fill in Country Code and City (Organization tab) |
| Missing `starRating` for Hotel | Set Star Rating in Schema.org tab |
| `AggregateRating` must have `ratingCount` | Fill in Number of Reviews (Organization tab → Advanced) |
| Event missing `startDate` | Ensure all events have `startDate` in ISO 8601 format |

**Validation tool:** [search.google.com/test/rich-results](https://search.google.com/test/rich-results)

---

## OpenGraph Issues

### Social preview image is not showing on Facebook / LinkedIn

**Cause:** Facebook and LinkedIn cache OG data aggressively. After changing your OG image, you need to force them to re-scrape the page.

**Fix for Facebook:**
1. Go to [developers.facebook.com/tools/debug/](https://developers.facebook.com/tools/debug/)
2. Paste your page URL
3. Click **Debug** → then **Scrape Again**
4. The new image appears within 1–5 minutes

**Fix for LinkedIn:**
1. Go to [linkedin.com/post-inspector/](https://www.linkedin.com/post-inspector/)
2. Paste your URL and click **Inspect**

**Also verify:**
- OG image dimensions: minimum 600×315, recommended 1200×630 pixels
- Image format: JPG or PNG (not SVG — SVG is not supported by Facebook)
- Image file is publicly accessible (not behind a login or IP restriction)

---

### Duplicate OpenGraph tags (from AI Boost for Joomla and another plugin)

**Cause:** Another SEO or template plugin is also generating `og:` meta tags.

**Fix:**
1. Identify the other plugin (Sh404SEF, YooSEO, Helix template SEO settings, etc.)
2. Disable the OpenGraph feature in that plugin (not the plugin itself)
3. Let AI Boost for Joomla be the sole OG generator

AI Boost for Joomla deduplicates its own output but cannot remove tags added by other plugins.

---

## Analytics Issues

### Google Analytics 4 is not tracking

**Cause:** Consent mode is set to YooTheme but the user has not accepted cookies.

**Steps:**
1. Confirm **Enable GA4 = Yes** in the Analytics tab.
2. Confirm the Measurement ID format is correct: `G-XXXXXXXXXX`.
3. Check the **GA4 Consent Mode** setting:
   - If set to **YooTheme Pro 5**: GA4 only loads after the user accepts "Statistics" consent — this is correct behaviour.
   - If set to **None**: GA4 should load immediately.
4. In your browser, open DevTools → Network tab → filter by `gtag` or `analytics.js` — verify the script loads.

---

### Duplicate GA4 tracking (double-counting pageviews)

**Cause:** GA4 is configured both in AI Boost for Joomla AND in another location (YooTheme Customizer, GTM, or another plugin).

**Fix:**
- If using GTM: set **GA4 Consent Mode** in AI Boost for Joomla to **Via GTM** and leave the Measurement ID empty. Configure GA4 only inside your GTM container.
- If using YooTheme's Customizer GA4: disable it in YooTheme and use AI Boost for Joomla instead (or vice versa).

---

## IndexNow Issues

### IndexNow is enabled but pages are not indexed faster

**Check 1 — API key file accessible:**
Visit `yoursite.com/{your-api-key}.txt` in a browser. It should return your API key as plain text. If it returns 404, re-save the plugin settings — the file should be recreated automatically.

**Check 2 — Submission logs in Bing:**
Open [Bing Webmaster Tools](https://www.bing.com/webmasters) → IndexNow → view recent submissions. If submissions appear but pages are still slow to index, the delay is on Bing's side — IndexNow is a suggestion, not a guarantee.

**Check 3 — License tier:**
IndexNow requires a Developer or Agency license. Verify your license tier is shown correctly in the Plugin tab.

---

## LLMs.txt Issues

### LLMs.txt returns a 404

**Causes and fixes:**

1. **Feature disabled** — Confirm **Enable LLMs.txt = Yes** in the Analytics tab.
2. **License tier** — LLMs.txt requires Developer or Agency. Check your license badge.
3. **SEF URLs** — Joomla's SEF URL routing must be enabled (Global Configuration → Site → SEF URLs = Yes).
4. **Static file conflict** — If a physical `llms.txt` file exists in the root, delete it.

---

## Multilingual Field Issues

### Multilingual fields show only one language

**Cause:** Only one language is published in Joomla.

**Fix:**
1. Go to **System → Manage → Languages**.
2. Ensure at least 2 languages are **Published** (green status).
3. Clear Joomla cache: **System → Clear Cache → All**.
4. Reopen AI Boost for Joomla settings — language fields should now appear per language.

---

## Performance Issues

### The plugin is slowing down my site

**Cause:** Caching is disabled or the cache TTL is very low.

**Fix:**
1. Go to **Debug & Performance tab**.
2. Set **Enable Caching = Yes**.
3. Set **Cache TTL = 3600** (or higher for stable sites).
4. Click Save.

**Also check:** Enable Debug Mode temporarily to see operation timing in the admin flash messages, then disable it after diagnosing.

---

## Getting More Help

If your issue is not listed here:

| Channel | Details |
|---------|---------|
| Documentation | [aiboostnow.com/docs](https://aiboostnow.com/docs) |
| Support email | support@aiboostnow.com |
| Response time | 1–3 business days |

When contacting support, include:
- AI Boost for Joomla version (visible in Plugin tab)
- Joomla version (`yoursite.com/administrator` → bottom right corner)
- PHP version (System → System Information)
- Description of the issue and steps to reproduce

---

*← [Multilingual Sites](multilingual.md) | [Documentation Index](index.md) | [Compatibility Matrix →](compatibility.md)*

*AI Boost for Joomla v0.24.0 — © 2025–2026 AI Boost Now.*
