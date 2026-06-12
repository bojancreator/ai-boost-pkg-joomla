# Getting Started with AI Boost for Joomla

**Compatible with:** Joomla 5.x and 6.x | PHP 8.1+

This guide covers your first ten minutes: installing the Free package, adding the Pro Upgrade, verifying your licence, and completing the guided Autopilot setup.

---

## What You Will Achieve

By the end of this guide, your Joomla site will have:

- Schema.org structured data on every page
- A dynamic XML sitemap at `yoursite.com/sitemap.xml`
- An AI-aware `robots.txt` with per-bot controls for AI crawlers
- OpenGraph tags for rich social sharing previews
- An `llms.txt` AI site summary
- A Health report confirming everything works

**Time required:** approximately 10 minutes.

---

## System Requirements

| Component | Minimum | Recommended |
| --- | --- | --- |
| Joomla | 5.0 | latest 5.x or 6.x |
| PHP | 8.1 | 8.2+ |
| MySQL / MariaDB | 5.7 / 10.3 | 8.0 / 10.6+ |
| Access level | Super Administrator | — |

---

## Step 1 — Install the Free package

1. Download the latest Free package `pkg_aiboost-x.y.z.zip` from [aiboostnow.com](https://aiboostnow.com).
2. Log in to your Joomla administrator panel at `yoursite.com/administrator`.
3. Go to **System → Install → Extensions → Upload Package File**.
4. Drag and drop the ZIP into the upload area (or click **Browse for file**).

You should see a green success message. The package installs the admin component and its system plugins, and enables them automatically.

> **Upgrading?** Upload the new ZIP through the same screen. The installer detects the existing installation and upgrades it without losing your settings.

---

## Step 2 — Install the Pro Upgrade (Pro customers)

If you purchased a Pro plan:

1. Download `pkg_aiboost_pro-x.y.z.zip` from your Lemon Squeezy **My Orders** page (the link is in your purchase e-mail).
2. Install it through the same **System → Install → Extensions** screen, **on top of** the Free package.

The Pro Upgrade requires the Free package to be installed first.

Free users skip this step — everything below works in the Free edition too; Pro-only features simply stay as locked cards with an **Upgrade to Pro** button.

---

## Step 3 — Verify your licence (Pro customers)

1. Go to **Components → AI Boost**.
2. In the sidebar, under **SETUP**, click **License & Updates**.
3. Paste your licence key into the **License key** field.
4. Click **Verify**.

When verification succeeds, the status badge turns **Active** and a confirmation banner appears: your Pro features are now permanently activated on this site. Activation is **perpetual** — even if the licence later expires, the features keep working; expiry only pauses updates and support (see [Licence & Plans](license-plans.md)).

---

## Step 4 — Run the Autopilot checklist

1. In the sidebar, under **SETUP**, click **Autopilot**.
2. Work through the four cards — click **Edit identity** / **Edit schema** / **Edit sitemap** / **Edit social meta** on each one. Autopilot takes you straight to the right setting and tracks your progress.
3. Aim for **4/4 complete (100% configured)**.

Autopilot is a guided setup checklist that shows how much of the core configuration is complete (for example "2/4 complete"):

| Step | What you fill in |
| --- | --- |
| **Site Identity** | Organisation name, URL and logo — used across structured data and previews |
| **Schema.org Core** | Enable schema output and pick your schema type |
| **Sitemap** | Enable the XML sitemap |
| **Social Meta** | OpenGraph site name, default share image, Twitter Cards |

---

## Step 5 — Check Health

1. In the sidebar, under **OVERVIEW**, click **Health**.
2. Click **Re-run Checks**.

Health gives your installation a 0–100 score and lists every check by category. Each failed check includes a **fix action** that jumps directly to the responsible setting. Use Health as your feedback centre after any configuration change.

---

## Step 6 — Verify the front end

Run these checks to confirm AI Boost for Joomla is live:

| Check | What to do | What to look for |
| --- | --- | --- |
| Sitemap | Visit `yoursite.com/sitemap.xml` | XML list of your pages |
| robots.txt | Visit `yoursite.com/robots.txt` | The AI Boost managed block with crawler rules and a `Sitemap:` line |
| llms.txt | Visit `yoursite.com/llms.txt` | AI-readable site summary |
| Schema.org | Right-click any page → View Source → search `ld+json` | JSON-LD inside the `<!-- AI Boost for Joomla - Start -->` block |

---

## Optional Next Steps

### Analytics & tracking (Pro)
Open **SEO → Analytics & Tracking** to connect Google Analytics 4, Google Tag Manager, site verification (Google Search Console, Bing, Facebook) and Meta Pixel.

### Instant indexing with IndexNow (Pro)
Open **AI VISIBILITY → AI Visibility**, enable **IndexNow**, and click **Generate** to create the API key. Bing and other IndexNow-enabled engines are then notified automatically when you publish or update an article.

### Redirects and 404 monitoring
Open **TOOLS → Redirects** to manage 301/302 redirects, review the 404 log and import rules from CSV.

### Analyse your pages
Open **TOOLS → Analyzers** for the SEO Analyzer and JSON-LD Validator, or **TOOLS → URL Checker** to scan URLs from your sitemap.

---

## Staying Up to Date

- **Free package:** Joomla's native updater shows an "Update available" notice under **System → Update → Extensions** when a new Free release is published.
- **Pro Upgrade:** new Pro releases are delivered through your Lemon Squeezy **My Orders** customer portal, and you are notified by e-mail. Download the new ZIP and install it over the existing version — your settings and licence are preserved.

---

## Quick Troubleshooting

| Problem | Solution |
| --- | --- |
| Sitemap returns 404 | Delete any static `sitemap.xml` from your site root; ensure SEF URLs are enabled in Global Configuration |
| robots.txt unchanged | A hand-written `robots.txt` is respected — AI Boost only manages its own fenced block. See [Troubleshooting](troubleshooting.md) |
| Schema.org missing | Check **OVERVIEW → Health** — the schema checks point at the exact setting to fix |
| OG image not on Facebook | Use the [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) → Scrape Again |

---

## Getting Help

| Resource | Link |
| --- | --- |
| Full documentation | [aiboostnow.com/docs](https://aiboostnow.com/docs) |
| Support e-mail | [support@aiboostnow.com](mailto:support@aiboostnow.com) |
| Pricing & licences | [aiboostnow.com/pricing](https://aiboostnow.com/pricing) |
| In-app help | sidebar **ADVANCED → Help** — problem solver, launch checklist and a copyable support request template |

---

*AI Boost for Joomla — Getting Started Guide*
*© 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)). All rights reserved.*
