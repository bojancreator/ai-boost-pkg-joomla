# Sitemap — XML Sitemap & Hreflang Tags

The **Sitemap** settings manage two closely related features: the dynamic XML sitemap that search engines and AI crawlers use to discover your content, and hreflang tags that tell search engines which language version of a page to show to each user.

---

## Hreflang Tags

**Field:** `enable_hreflang`  
**Default:** Yes

When **Yes**, AI Boost for Joomla injects `<link rel="alternate" hreflang="...">` tags inside the `<head>` of every page.

**Example output:**
```html
<link rel="alternate" hreflang="en" href="https://yourdomain.com/en/about-us" />
<link rel="alternate" hreflang="de" href="https://yourdomain.com/de/ueber-uns" />
<link rel="alternate" hreflang="x-default" href="https://yourdomain.com/en/about-us" />
```

**Why it matters:**
- Required for any multilingual site to avoid keyword cannibalization
- Tells Google, Bing, and other engines which language/region users should see which page version
- Works with both Joomla's native multilingual system and the Falang plugin

> Enable hreflang even on single-language sites if you plan to add more languages in the future — it has no negative impact on single-language setups.

---

## XML Sitemap

**Field:** `enable_sitemap`  
**Default:** Yes

When **Yes**, AI Boost for Joomla serves a dynamic XML sitemap at `yoursite.com/sitemap.xml`.

Key characteristics:
- Generated on-the-fly and cached — always reflects your current published content
- No static file to maintain or regenerate
- Automatically submitted via your `robots.txt` `Sitemap:` declaration
- Google and Bing pick it up within hours to days of first activation

> **Conflict with static files:** If a physical `sitemap.xml` file exists in your site root, it overrides the dynamic version. Delete it to let AI Boost for Joomla take control.

---

## What to Include

### Include Articles

**Field:** `sitemap_include_articles`  
**Default:** Yes

Adds all published Joomla articles to the sitemap. This is the primary content source for most sites — always enable it.

### Include Categories

**Field:** `sitemap_include_categories`  
**Default:** Yes

Adds Joomla content category pages (`/category/...`) to the sitemap. Category pages are important for topical authority signals.

### Include Menu Items

**Field:** `sitemap_include_menu`  
**Default:** Yes

Adds Joomla menu items (Home, About, Contact, etc.) to the sitemap. Captures pages not tied to articles or categories, such as custom HTML menu items and component views.

---

## Advanced Sitemap Options

> **Visible when:** Show Advanced Options = Yes (set in the Setup area)

### Menu Depth

**Field:** `sitemap_menu_depth`  
**Default:** All Levels  
**Visible when:** Include Menu Items = Yes

| Option | Behavior |
|--------|----------|
| **All Levels** | Includes menu items at every nesting depth |
| **Level 1 Only** | Only top-level menu items (fastest sitemap for large menus) |
| **Levels 1–2** | Top two levels |

### Select Menus

**Field:** `sitemap_menu_types`  
**Visible when:** Include Menu Items = Yes

Select specific Joomla menus to include. Leave empty to include all menus. Useful when you have menus containing internal/admin links that should not be indexed.

### Select Categories (Article Filter)

**Field:** `sitemap_article_categories`  
**Visible when:** Include Articles = Yes

Select specific content categories to include in the sitemap. Leave empty to include articles from all categories.

### Exclude Article IDs

**Field:** `sitemap_exclude_ids`  
**Visible when:** Include Articles = Yes  
**Format:** Comma-separated IDs  
**Example:** `15, 23, 47`

Article IDs to exclude from the sitemap. Useful for draft-style articles, thank-you pages, or internal pages you do not want indexed.

### Maximum Articles

**Field:** `sitemap_max_articles`  
**Default:** `0` (unlimited)  
**Visible when:** Include Articles = Yes

Cap the total number of articles in the sitemap. Google's documented practical limit is 50,000 URLs per sitemap file; the default of 0 (unlimited) is fine for most sites.

### Add Hreflang to Sitemap

**Field:** `sitemap_hreflang`  
**Default:** No  
**Visible when:** Enable XML Sitemap = Yes

When **Yes**, adds `<xhtml:link rel="alternate" hreflang="...">` entries inside each `<url>` block of the sitemap. This gives search engines hreflang signals in **both** the page head tags and the sitemap — double coverage for multilingual accuracy.

**Example sitemap entry with hreflang:**
```xml
<url>
  <loc>https://yourdomain.com/en/about-us</loc>
  <priority>0.8</priority>
  <xhtml:link rel="alternate" hreflang="en" href="https://yourdomain.com/en/about-us"/>
  <xhtml:link rel="alternate" hreflang="de" href="https://yourdomain.com/de/ueber-uns"/>
</url>
```

---

## Priority Settings — Advanced

> **Visible when:** Show Advanced Options = Yes

Sitemap `<priority>` values are hints to search engines about the relative importance of pages on your site. They do not directly affect ranking, but they guide crawl budget allocation.

| Content type | Default | Options |
|-------------|---------|---------|
| Articles | **0.8** (recommended) | 1.0 / 0.8 / 0.5 |
| Categories | **0.7** (recommended) | 1.0 / 0.7 / 0.5 |
| Menu Items | **0.6** (recommended) | 1.0 / 0.6 / 0.5 |

> **Tip:** Set articles to 1.0 only if your articles are the highest-priority content on your site (e.g., a news site). For a hotel or business site, the homepage and contact page (captured via menu items) should typically be 0.9 or 1.0.

---

## Update Frequency Settings — Advanced

> **Visible when:** Show Advanced Options = Yes

The `<changefreq>` value tells search engines how often content in each category typically changes. This is advisory — search engines use it as a hint, not a strict instruction.

| Content type | Default | Options |
|-------------|---------|---------|
| Articles | **Weekly** | Daily / Weekly / Monthly |
| Categories | **Weekly** | Daily / Weekly / Monthly |
| Menu Items | **Monthly** | Weekly / Monthly |

| Value | Use when |
|-------|---------|
| **Daily** | News sites that publish multiple articles per day |
| **Weekly** | Most standard websites (default for articles) |
| **Monthly** | Stable pages like About, Contact, Terms (default for menu items) |

---

## Submitting Your Sitemap

AI Boost for Joomla automatically includes the sitemap URL in `robots.txt`, which causes search engines to discover it automatically. For faster pickup, also submit it manually:

| Engine | Where to submit |
|--------|----------------|
| Google | Google Search Console → Sitemaps → Add sitemap URL |
| Bing | Bing Webmaster Tools → Sitemaps → Submit sitemap |
| Yandex | Yandex Webmaster → Indexing → Sitemap files |

Submit URL: `https://yourdomain.com/sitemap.xml`

---

## Recommended Settings

| Setting | Recommended value |
|---------|------------------|
| Enable Hreflang | Yes (required for multilingual sites) |
| Enable XML Sitemap | Yes |
| Include Articles | Yes |
| Include Categories | Yes |
| Include Menu Items | Yes |
| Add Hreflang to Sitemap | Yes (multilingual sites) |
| Articles Priority | 0.8 |
| Categories Priority | 0.7 |
| Menu Items Priority | 0.6 |

---

*← [Schema.org](schema-org.md) | [Documentation Index](index.md) | [Social & Meta →](social-meta.md)*

*AI Boost for Joomla v0.73.15 — © 2025–2026 AI Boost (aiboostnow.com).*
