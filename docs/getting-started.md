# Getting Started with JoomlaBoost

**Version:** 0.24.0  
**Compatible with:** Joomla 4.x, 5.x, 6.x | PHP 8.1+

---

## What You Will Achieve

By the end of this guide, your Joomla site will have:

- ✅ Schema.org structured data on every page
- ✅ A dynamic XML sitemap at `yoursite.com/sitemap.xml`
- ✅ An AI-aware `robots.txt` allowing ChatGPT, Perplexity, Claude, and Googlebot
- ✅ OpenGraph tags for rich social sharing previews
- ✅ A solid foundation for AI Search visibility

**Time required:** approximately 5–10 minutes.

---

## System Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| Joomla | 4.0.0 | 5.x or 6.x |
| PHP | 8.1.0 | 8.2+ |
| MySQL / MariaDB | 5.7 / 10.3 | 8.0 / 10.6+ |
| Disk space | 2 MB | — |
| Access level | Super Administrator | — |

---

## Step 1 — Download the Plugin

Download the latest `plg_system_joomlaboost-0.24.0.zip` from [aiboostnow.com/download](https://aiboostnow.com/download).

After purchasing, the download link is also emailed to you by Gumroad (check your spam folder if you do not see it).

---

## Step 2 — Install via Extension Manager

1. Log in to your Joomla administrator panel at `yoursite.com/administrator`.
2. Go to **System → Install → Extensions**.
3. Click the **Upload Package File** tab.
4. Drag and drop the `plg_system_joomlaboost-0.24.0.zip` into the upload area, or click **Browse for file** and select it.
5. Click **Upload & Install**.

You should see a green success message: *"Installation of the plugin was successful."*

> **Upgrading?** Upload the new ZIP through the same Extension Manager screen. The installer detects an existing installation and upgrades it without losing your settings.

---

## Step 3 — Enable the Plugin

The plugin is **disabled by default** after installation. You must enable it:

1. Go to **System → Manage → Plugins**.
2. In the search box, type `JoomlaBoost`.
3. The plugin appears in the list with a **red circle** (disabled).
4. Click the red circle to enable it. It turns **green**.

---

## Step 4 — Enter Your License Key

1. Click the plugin name **JoomlaBoost** to open its settings.
2. You are on the **Plugin** tab by default.
3. Under **License**, find the **License Key** field.
4. Paste your license key from Gumroad. Format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`.
5. Click **Save** (do not close yet).

After saving, the field shows a **Licensed** badge and your plan tier (Starter, Developer, or Agency).

> **No license key?** The plugin works without a key but IndexNow, LLMs.txt, Events Schema, and Manual FAQ are locked. [Purchase a license →](https://aiboostnow.com/pricing)

---

## Step 5 — Quick Setup (2 minutes)

Still on the **Plugin** tab, under **Quick Setup**:

### 5a. Choose Your Site Type

Select the preset that best matches your website from the **Site Type** dropdown:

| Preset | Best for |
|--------|----------|
| 🏨 Hotel / Accommodation | Hotels, guesthouses, rental properties |
| 🍽️ Restaurant / Cafe | Restaurants, cafes, food businesses |
| 📰 Blog / Magazine | News sites, blogs, online magazines |
| 🛍️ E-commerce / Online Shop | Online stores |
| 🎯 Generic Business / Corporate | Agencies, services, corporate sites |

### 5b. Apply the Preset

Set **Apply Preset on Save** to **Yes**.

Click **Save**. The preset instantly configures dozens of recommended settings (Schema type, FAQ detection, sitemap priorities, and more).

---

## Step 6 — Fill in Organization Information

Click the **Organization** tab. Fill in at minimum:

| Field | Example | Why it matters |
|-------|---------|----------------|
| Organization Name | `Acme Hotel Belgrade` | Appears in Google Knowledge Panel and AI citations |
| Website URL | `https://yourdomain.com` | Canonical URL in Schema.org |
| Phone | `+1 212 555 1234` | Used in LocalBusiness Schema and Google Maps |
| Country Code | `RS`, `DE`, `US` | ISO 3166-1 two-letter code |
| City / Locality | `Belgrade` | Address Schema for local search |

Add at least one or two **Social Media Links** (Facebook, LinkedIn, etc.) — these connect your website to your social profiles as the same entity in AI systems.

---

## Step 7 — Verify the Sitemap is Active

Click the **Sitemap** tab. Confirm:
- **Enable XML Sitemap** = **Yes** (default)
- **Include Articles** = **Yes**

Your sitemap is immediately live at `yoursite.com/sitemap.xml` — no server configuration needed.

---

## Step 8 — Enable OpenGraph

Click the **Social & Meta** tab. Confirm:
- **Enable OpenGraph** = **Yes** (default)

Optionally upload an **OG Default Image** (recommended size: **1200×630 pixels**, JPG or PNG). This image appears when anyone shares your site on Facebook, LinkedIn, or WhatsApp.

---

## Step 9 — Save & Verify

Click **Save & Close**.

Run these three checks to confirm JoomlaBoost is working:

| Check | What to do | What to look for |
|-------|-----------|-----------------|
| Sitemap | Visit `yoursite.com/sitemap.xml` | XML list of your pages |
| Robots.txt | Visit `yoursite.com/robots.txt` | `Allow: /` rules for AI bots |
| Schema.org | Right-click any page → View Source → search `ld+json` | JSON-LD structured data block |

---

## Optional Next Steps

### Enable Google Analytics 4
1. Go to **Analytics** tab → **Enable GA4** → **Yes**.
2. Paste your Measurement ID (format: `G-XXXXXXXXXX`).
3. Save.

### Enable Google Search Console Verification
1. Go to **Analytics** tab → **Enable GSC Verification** → **Yes**.
2. Paste the verification token from Google Search Console → Settings → Ownership Verification → HTML Tag.
3. Save.

### Enable IndexNow — Instant Indexing *(Developer / Agency)*

> **Requires Developer or Agency license.**

1. Go to **Analytics** tab → **Enable IndexNow** → **Yes**.
2. Click **Generate API Key**.
3. Save.

From now on, Bing, Yandex, and Seznam are notified within minutes whenever you publish or update an article.

### Enable LLMs.txt — AI Crawler Visibility *(Developer / Agency)*

> **Requires Developer or Agency license.**

1. Go to **Analytics** tab → **Enable LLMs.txt** → **Yes**.
2. Save.

AI assistants (ChatGPT, Claude, Perplexity, Gemini) can now read `yoursite.com/llms.txt` to understand your site's structure and content.

---

## Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| Sitemap returns 404 | Delete any static `sitemap.xml` from your site root; ensure SEF URLs are enabled in Joomla Global Config |
| robots.txt unchanged | Delete the static `robots.txt` file from your Joomla root |
| Schema.org missing | Enable Debug Mode in the Debug tab → check page source for `ld+json` |
| OG image not on Facebook | Use [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) → Scrape Again |
| Plugin slowing site | Enable Caching in the Debug tab, set TTL to 3600 |

---

## Getting Help

| Resource | Link |
|----------|------|
| Full Documentation | [aiboostnow.com/docs](https://aiboostnow.com/docs) |
| Support Email | support@aiboostnow.com |
| Plugin Updates | [aiboostnow.com/download](https://aiboostnow.com/download) |
| Pricing & Licenses | [aiboostnow.com/pricing](https://aiboostnow.com/pricing) |

---

*JoomlaBoost v0.24.0 — Getting Started Guide*  
*© 2025–2026 AI Boost Now. All rights reserved.*
