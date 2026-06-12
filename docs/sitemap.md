# Sitemap — XML Sitemap & Hreflang

The **Sitemap** page (sidebar **SEO → Sitemap**) manages the dynamic XML sitemap that search engines and AI crawlers use to discover your content, plus the Pro extras: sitemap index, image data, hreflang alternates and a Google News sitemap.

---

## XML Sitemap

**Enable XML Sitemap** serves a dynamic sitemap at `yoursite.com/sitemap.xml`.

Key characteristics:

- Generated on the fly — always reflects your current published content
- No static file to maintain or regenerate
- Advertised automatically via the `Sitemap:` line in your `robots.txt`
- Google and Bing typically pick it up within hours to days of first activation

> **Conflict with static files:** if a physical `sitemap.xml` file exists in your site root, it overrides the dynamic version. Delete it to let AI Boost for Joomla take control.

---

## Content to Include

| Toggle | Notes |
|--------|-------|
| **Articles** | All published Joomla articles — the primary content source for most sites; always enable |
| **Categories** | Content category pages — important for topical authority |
| **Menu Items** | Home, About, Contact and other menu-driven pages not tied to articles |
| **Tags** *(Pro)* | Joomla tag pages |

### Exclusions

- **Exclude Article Category IDs** — comma-separated category IDs whose articles should stay out of the sitemap.
- **Exclude Menu Item IDs** — comma-separated menu item IDs to skip.

---

## Sitemap Settings

- **URL Limit** — cap the total number of URLs (Google's practical limit is 50,000 URLs per sitemap file).
- **Default changefreq** — Always / Hourly / Daily / Weekly / Monthly / Yearly / Never. Advisory hint only; Weekly suits most sites, Daily suits news sites.
- **Default Priority** — the `<priority>` hint (0–1) applied to URLs without a more specific value.

### Per-Type Priority (Pro)

Set separate priority sliders for **Homepage**, **Articles**, **Categories** and **Tags**. Priorities are hints that guide crawl budget — they do not directly change rankings.

| Content type | Suggested value |
|--------------|-----------------|
| Homepage | 0.9–1.0 |
| Articles | 0.8 (1.0 for news sites) |
| Categories | 0.7 |
| Tags | 0.5 |

---

## Advanced Sitemap (Pro)

- **Sitemap Index** — splits large sites into multiple sitemap files behind a sitemap index.
- **Include Image Sitemap data** — embeds `<image:image>` entries so image search can find your media.
- **Add hreflang to sitemap** — adds `<xhtml:link rel="alternate" hreflang="...">` entries inside each `<url>` block for multilingual sites:

```xml
<url>
  <loc>https://yourdomain.com/en/about-us</loc>
  <xhtml:link rel="alternate" hreflang="en" href="https://yourdomain.com/en/about-us"/>
  <xhtml:link rel="alternate" hreflang="de" href="https://yourdomain.com/de/ueber-uns"/>
</url>
```

The sitemap is the place where AI Boost emits hreflang — for the full multilingual picture (associations, Falang, per-language values) see [Multilingual Sites](multilingual.md).

---

## Google News Sitemap (Pro)

Serves a separate news sitemap for recent articles from a chosen category — the format Google News requires.

- **Enable Google News Sitemap** — master toggle.
- **News Category ID** — the Joomla category whose articles qualify as news.
- **Publication Name** — your publication's name (translatable per language).

---

## Live Preview

Click **Preview sitemap.xml** on the Sitemap page to render the sitemap inside the admin panel: URL count, image entries, hreflang groups, latest modification date, file size, the first lines of XML, and validation warnings — without leaving Joomla.

---

## Submitting Your Sitemap

The sitemap URL is included in `robots.txt`, so search engines discover it automatically. For faster pickup, submit it manually once:

| Engine | Where to submit |
|--------|----------------|
| Google | Google Search Console → Sitemaps → Add sitemap URL |
| Bing | Bing Webmaster Tools → Sitemaps → Submit sitemap |

Submit URL: `https://yourdomain.com/sitemap.xml`

*(Pro)* **IndexNow** (AI VISIBILITY → AI Visibility) additionally pushes individual URL changes to IndexNow-enabled engines the moment you publish.

---

## Recommended Settings

| Setting | Recommended value |
|---------|------------------|
| Enable XML Sitemap | Yes |
| Articles / Categories / Menu Items | Yes |
| URL Limit | Default (raise only for very large sites) |
| Default changefreq | Weekly (Daily for news sites) |
| Add hreflang to sitemap | Yes on multilingual sites *(Pro)* |
| Google News Sitemap | Yes for news publishers *(Pro)* |

---

*← [Schema.org](schema-org.md) | [Documentation Index](index.md) | [Social & Meta →](social-meta.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
