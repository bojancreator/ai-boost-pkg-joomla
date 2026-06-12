# Social Meta / OG — OpenGraph & Twitter Cards

The **Social Meta / OG** page (sidebar **SEO → Social Meta / OG**) controls how your pages look when shared: OpenGraph tags, Twitter / X Cards, and the Pro per-article overrides.

> Looking for Meta Pixel? Tracking pixels live on **SEO → Analytics & Tracking** — see [Analytics & Indexing](analytics-indexing.md).

---

## OpenGraph (Facebook, LinkedIn, WhatsApp)

OpenGraph is the protocol (created by Facebook, now used universally) that controls how your pages appear when shared. Platforms that read it include Facebook, LinkedIn, WhatsApp, Slack, Telegram, Discord and iMessage link previews.

### Enable OpenGraph tags

Master toggle. When on, AI Boost for Joomla emits the `og:*` tag set on every page.

> **Conflict warning:** if another SEO extension or your template also generates OpenGraph tags, disable that feature there to avoid duplicate `og:` tags. The **Health** page's duplicate-tag scan flags this for you.

### OG Site Name

Your brand name as displayed in social share card headers. Translatable per language (Pro).

### Default OG Image (+ alt text)

The image shown in social preview cards when no article-specific image is available. Translatable per language (Pro).

**Recommendations:**

- **Size:** 1200×630 pixels — optimal for all major platforms (you can declare the dimensions in the **Default OG Image Width/Height** fields)
- **Format:** JPG or PNG (avoid SVG — most platforms do not support it)
- **Content:** a brand visual with logo and site name works well as a default

**Priority chain for the OG image on article pages:**

```
1. Per-article OG image custom field (Pro)
2. Article featured image
3. Default OG Image (this field)
```

### Default OG Description Override

Optional site-wide description used when a page has no better description of its own.

---

## Per-article OG (Pro)

Override the OG image, title, description, type and video per article through auto-created Joomla custom fields. Enable **Use per-article OG image & description**, click **Create / Repair OG Fields**, and the fields appear on every article edit form. You can also enable **Set og:type = article on article pages** here.

Full walkthrough: [Per-Article Overrides](per-article-overrides.md).

---

## Locale & Facebook (Pro)

- **Add og:locale tag** — emits the locale of the current page (useful on multilingual sites).
- **Facebook App ID** — adds the `fb:app_id` tag if you use Facebook insights/apps.

Looking for hreflang? AI Boost emits hreflang alternates in the XML sitemap (**SEO → Sitemap → Advanced Sitemap**, Pro) — see [Multilingual Sites](multilingual.md).

---

## Twitter / X Cards

- **Enable Twitter Card meta tags** — emits `twitter:*` tags so shares on X get a large summary card. Included in Free.
- **Twitter / X Site Handle** *(Pro)* — your `@handle`, added as `twitter:site`.

---

## Recommended Settings

| Setting | Recommended value |
| --------- | ------------------ |
| Enable OpenGraph tags | Yes |
| OG Site Name | Your brand name |
| Default OG Image | Upload a 1200×630 image with alt text |
| Enable Twitter Card meta tags | Yes |
| Per-article OG | Yes for sites with important landing pages *(Pro)* |
| og:locale | Yes on multilingual sites *(Pro)* |

> **After changing images:** Facebook and LinkedIn cache previews aggressively. Force a refresh with the [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) (Scrape Again) or the [LinkedIn Post Inspector](https://www.linkedin.com/post-inspector/).

---

*← [Sitemap](sitemap.md) | [Documentation Index](index.md) | [Analytics & Indexing →](analytics-indexing.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
