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

1. **Content type not included** — Articles, categories or menu items can be toggled individually.
   *Fix:* Go to **SEO → Sitemap → Content to Include** and check the toggles.

2. **Categories or menu items excluded by ID** — IDs listed in the exclusion fields.
   *Fix:* Go to **SEO → Sitemap → Exclusions** and verify **Exclude Article Category IDs** and **Exclude Menu Item IDs**.

3. **URL Limit reached** — URLs past the cap are excluded.
   *Fix:* Go to **SEO → Sitemap → URL Limit** and raise it (or set `0` for unlimited).

4. **Articles not published** — Unpublished articles are never included.
   *Fix:* Verify article status in Content → Articles.

> **Tip:** the **Preview sitemap.xml** button on the Sitemap page shows the URL count and validation warnings without leaving the admin panel.

---

## Robots.txt Issues

### robots.txt does not contain the AI Boost rules

**How it works:** `robots.txt` is always a physical file in your Joomla root — there is no dynamic version. AI Boost writes and maintains only its own fenced section (between the `# BEGIN AI Boost for Joomla managed block` and `# END` markers) inside that file when **Enable robots.txt management** is on and the settings are saved. Everything you wrote outside the managed block is preserved — **never delete your `robots.txt` file**.

**Fix:**
1. Go to **AI VISIBILITY → Crawlers & Robots** and confirm **Enable robots.txt management = Yes**.
2. Click **Save** — saving rewrites the AI Boost managed block inside the physical `robots.txt`.
3. Click **Preview robots.txt** on the same page to inspect the live file without leaving the admin panel.
4. Visit `yoursite.com/robots.txt` and search for `# BEGIN AI Boost for Joomla managed block`.
5. If the block is still missing, the file is probably not writable by the web server — check the file permissions on `robots.txt` in your Joomla root.

---

## Schema.org Issues

### Schema.org JSON-LD is not appearing in page source

**Diagnosis steps:**
1. Open **Components → AI Boost** → **SEO → Schema.org** → confirm **Enable Schema.org structured data = Yes**.
2. Run **OVERVIEW → Health → Re-run Checks** — the schema checks link straight to whatever is missing.
3. Visit the affected page → View Page Source → search for `AI Boost for Joomla` (all output sits in one marked block).
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
| ------- | ----- |
| Missing `name` property | Fill in Organization Name in **SETUP → Site Identity** |
| Missing `address` for LocalBusiness | Fill in Country Code and City in **SETUP → Site Identity** |
| Missing `starRating` for Hotel | Set Star Rating in **SEO → Schema.org → Business** |
| `AggregateRating` must have `ratingCount` | Fill in Review Count in **SETUP → Site Identity → Guest / Customer Rating** |
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
1. Confirm **Enable Google Analytics 4 = Yes** in **SEO → Analytics & Tracking** (a Pro feature — verify your licence under **SETUP → License & Updates**).
2. Confirm the Measurement ID format is correct: `G-XXXXXXXXXX`.
3. Check the **GDPR Consent Mode** setting:
   - If set to **YooTheme Pro Consent Manager**: GA4 only loads after the user grants statistics consent — this is correct behaviour.
   - If set to **None (direct inject)**: GA4 should load immediately.
4. Check **ADVANCED → Debug → Staging mode** — while staging mode is on, analytics is deliberately suppressed.
5. In your browser, open DevTools → Network tab → filter by `gtag` or `analytics.js` — verify the script loads.

---

### Duplicate GA4 tracking (double-counting pageviews)

**Cause:** GA4 is configured both in AI Boost for Joomla AND in another location (YooTheme Customizer, GTM, or another plugin).

**Fix:**
- If using GTM: set **GDPR Consent Mode** in AI Boost for Joomla to **Via GTM (skip direct GA4)** and leave the Measurement ID empty. Configure GA4 only inside your GTM container.
- If using YooTheme's Customizer GA4: disable it in YooTheme and use AI Boost for Joomla instead (or vice versa).

---

## IndexNow Issues

### IndexNow is enabled but pages are not indexed faster

**Check 1 — API key file accessible:**
Visit `yoursite.com/{your-api-key}.txt` in a browser. It should return your API key as plain text. If it returns 404, re-save the plugin settings — the file should be recreated automatically.

**Check 2 — Submission logs in Bing:**
Open [Bing Webmaster Tools](https://www.bing.com/webmasters) → IndexNow → view recent submissions. If submissions appear but pages are still slow to index, the delay is on Bing's side — IndexNow is a suggestion, not a guarantee.

**Check 3 — Pro licence and staging mode:**
IndexNow is a Pro feature — verify your licence under **SETUP → License & Updates**. Also check **ADVANCED → Debug → Staging mode**: while staging mode is on, IndexNow pings are deliberately suppressed.

---

## LLMs.txt Issues

### LLMs.txt returns a 404

**Causes and fixes:**

1. **Feature disabled** — Confirm **Enable LLMs.txt = Yes** in **AI Visibility**.
2. **SEF URLs** — Joomla's SEF URL routing must be enabled (Global Configuration → Site → SEF URLs = Yes).
3. **Static file conflict** — If a physical `llms.txt` file exists in the root, delete it.

---

## Multilingual Issues

### The Translations expander shows no extra languages

**Cause:** Only one content language is published in Joomla.

**Fix:**
1. Go to **System → Manage → Languages**.
2. Ensure at least 2 languages are **Published** (green status).
3. Clear Joomla cache: **System → Clear Cache → All**.
4. Reopen AI Boost for Joomla settings — the **Translations** expanders (Pro) now list each extra language.

See [Multilingual Sites](multilingual.md) for the full picture.

---

## Performance Issues

### The site feels slower after installing

AI Boost computes its output once per request and emits it through Joomla's document APIs, so its runtime cost is small. If you notice slowness:

1. Enable Joomla's own caching: **System → Global Configuration → System → Cache**.
2. Check for conflicts with minification plugins (see [Compatibility](compatibility.md)).
3. Run **OVERVIEW → Health** — the conflict scan flags plugins fighting over the same output.

---

## Getting More Help

If your issue is not listed here:

| Channel | Details |
| --------- | --------- |
| Documentation | [aiboostnow.com/docs](https://aiboostnow.com/docs) |
| Support email | support@aiboostnow.com |
| Response time | 1–3 business days |

When contacting support, use the copyable **Support Request** template under **ADVANCED → Help** — it gathers your AI Boost version, Joomla/PHP versions, Health result and active plugins for you.

---

*← [Multilingual Sites](multilingual.md) | [Documentation Index](index.md) | [Compatibility Matrix →](compatibility.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
