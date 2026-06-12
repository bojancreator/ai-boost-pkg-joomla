# Admin Navigation Guide — Where Every Setting Lives

Open **Components → AI Boost** in the Joomla administrator panel. All configuration happens in one place: a sidebar on the left groups every page, and the settings pages share one **Save** action.

> **Reading an older guide?** Early versions of AI Boost for Joomla had a single "Plugin" tab containing the licence field, presets, domain and robots.txt settings. That layout is gone — the table at the bottom of this page maps the old locations to the current ones.

---

## The sidebar

### OVERVIEW

| Page | What it does |
|------|--------------|
| **Dashboard** | Status of every AI Boost plugin (enable/disable), quick actions, conflict warnings |
| **Health** | 0–100 health score, per-check results with one-click fix actions, error log access |

### SETUP

| Page | What it does |
|------|--------------|
| **Autopilot** | Guided first-time setup checklist: Site Identity → Schema.org Core → Sitemap → Social Meta, with live completion tracking |
| **Site Identity** | Organisation name, description, logo, contact details, social profiles, address, GPS coordinates, customer rating |
| **License & Updates** | Paste and **Verify** your Pro licence key; shows activation status, expiry and the verification heartbeat |
| **Integrations** | Directory of third-party integrations and their status on your site |

### SEO

| Page | What it does |
|------|--------------|
| **Technical SEO** | Domain detection/override, conflict resolution mode, page title templates, meta description templates, canonical URL management, 404 logging |
| **Schema.org** | Master schema toggle, WebSite + SearchAction, Article schema, business/organisation type selection with type-specific fields, opening hours; Pro: FAQ/QAPage, HowTo, Event, Author Entity, extended business details |
| **Sitemap** | XML sitemap content, priorities and change frequency, live preview; Pro: sitemap index, image sitemap, hreflang, Google News sitemap |
| **Social Meta / OG** | OpenGraph site name and default share image, Twitter Cards; Pro: per-article OG fields, `og:locale`, Facebook App ID, Twitter site handle |
| **Analytics & Tracking** | Pro: site verification (GSC, Bing, Facebook), Google Analytics 4 with consent modes, Google Tag Manager, Meta Pixel with events |

### AI VISIBILITY

| Page | What it does |
|------|--------------|
| **AI Visibility** | `llms.txt` with custom pages, Markdown page endpoint, AI signals; Pro: `llms-full.txt`, IndexNow instant indexing |
| **Crawlers & Robots** | robots.txt management with live preview, SEO scraper blocking, per-bot AI crawler allow/block rules, custom rules |

### TOOLS

| Page | What it does |
|------|--------------|
| **Redirects** | 301/302/303/307/308 redirect rules with hit counters, 404 log with one-click rule creation, CSV import |
| **Analyzers** | On-page SEO analyzer (single URL or batch from sitemap), JSON-LD validator, AI visibility analysis |
| **URL Checker** | Batch-scan URLs for status codes, redirect chains, canonical issues, noindex and thin content |

### ADVANCED

| Page | What it does |
|------|--------------|
| **Custom Code** | Pro: head/body/footer code injection, scopable to specific menu items |
| **Debug** | Debug mode, HTML comment hiding, staging mode, error log settings — see [Debug & Diagnostics](debug-performance.md) |
| **Import** | Export all settings as JSON; import a previously exported file |
| **Help** | Troubleshooting hub: problem solver, launch validation checklist, copyable support request template |

---

## Free edition and locked cards

In the Free edition, Pro-only cards and fields stay **visible but locked**: the content is dimmed and an **Upgrade to Pro** button links to [aiboostnow.com](https://aiboostnow.com/pricing). Nothing is hidden, so you always see what the Pro Upgrade adds. As soon as the Pro Upgrade package is installed, the locks disappear; verify your licence under **SETUP → License & Updates** to activate the Pro features' front-end output (the licence is enforced at runtime).

---

## Where did the old "Plugin tab" settings go?

| Old location (Plugin tab) | Current location |
|---------------------------|------------------|
| License Key field | **SETUP → License & Updates** (paste key → **Verify**) |
| Site Type preset / "Apply Preset on Save" | Retired. Choose your business type under **SEO → Schema.org → Business / Organization Type** — type-specific fields appear automatically |
| Show Advanced Options toggle | Retired — advanced options are always visible on their pages (Pro options show as locked cards in Free) |
| Auto Domain Detection / Manual Domain | **SEO → Technical SEO → Domain & Environment** |

---

*← [Documentation Index](index.md) | Next: [Site Identity →](organization.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
