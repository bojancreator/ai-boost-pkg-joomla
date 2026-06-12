# Per-Article Overrides — Custom Fields for OpenGraph (Pro)

AI Boost for Joomla can override social preview data on a per-article basis using Joomla's built-in Custom Fields (com_fields). This lets you set a specific share image, title or description for individual pages — perfect for landing pages and campaign articles — without changing your site-wide defaults.

> **Pro feature.** Per-article OG lives on **SEO → Social Meta / OG → Per-article OG**. In the Free edition this card is visible but locked with an **Upgrade to Pro** button.

---

## How it works

For most pages, AI Boost composes OpenGraph tags from:

1. Article-level data (title, meta description, featured image)
2. Your global defaults on **SEO → Social Meta / OG** (OG Site Name, Default OG Image)

With **Per-article OG** enabled, a set of AI Boost custom fields appears on every article edit form. Any value you fill in there wins over both of the above for that article.

---

## Setup

1. Go to **SEO → Social Meta / OG → Per-article OG**.
2. Enable **Use per-article OG image & description**.
3. Click **Create / Repair OG Fields**.

AI Boost creates the custom fields for you in **Content → Fields** (group: **AI Boost — OpenGraph**) — you do not create them by hand:

| Field | Type | Effect |
|-------|------|--------|
| `aiboost_og_title` | Text | Overrides `og:title` for this article |
| `aiboost_og_description` | Textarea | Overrides `og:description` |
| `aiboost_og_image` | Media | Overrides `og:image` |
| `aiboost_og_type` | Text | Overrides `og:type` |
| `aiboost_og_video` | URL | Adds an `og:video` tag |
| `aiboost_twitter_card` | Text | Overrides the `twitter:card` type |

If the fields ever go missing (for example after a site migration), click **Create / Repair OG Fields** again — existing values are kept.

Optionally also enable **Set og:type = article on article pages** in the same card.

---

## Using the fields on an article

1. Open any article in the Joomla Article Manager.
2. Find the **AI Boost — OpenGraph** fields (in the Fields area of the edit form).
3. Fill in only what you want to override:
   - **OG Image** — select an image via the media picker
   - **OG Title** — the social share title (50–60 characters recommended)
   - **OG Description** — the social share description (150–160 characters recommended)
4. Save the article.

Values you leave empty fall back to the article's own data and then to your global defaults.

> **Multilingual sites with Falang:** per-article OG values are translatable through Falang — AI Boost serves the translation for the active page language and falls back to the default value.

---

## Priority chain

AI Boost resolves OpenGraph values in this order (highest priority first):

```
1. AI Boost per-article custom field (aiboost_og_*)
2. Article data — featured image (og:image), title (og:title), meta description (og:description)
3. Global defaults on SEO → Social Meta / OG (Default OG Image, OG Site Name)
```

---

## Schema.org per-article data

Article structured data does **not** need custom fields. With Article schema enabled (**SEO → Schema.org → Article Schema**), AI Boost generates it automatically from standard Joomla article data:

| Schema property | Source |
|-----------------|--------|
| `headline` | Article title |
| `description` | Meta description |
| `datePublished` / `dateModified` | Article dates |
| `author` | Article author |
| `image` | Article featured image |
| `url` | Article canonical URL |

*(Pro adds the Author Entity card — a full `Person` entity per author, fed from author custom fields — see the Schema.org page.)*

---

## Best practices

**When to use per-article OG fields:**

- High-traffic landing pages where the featured image is not suitable for social sharing
- Campaign articles that need a specific promotional image
- Articles whose title is too long or technical for a social card

**Image specifications for the OG image:**

- **Recommended size:** 1200×630 pixels
- **Minimum size:** 600×315 pixels (below this, Facebook shows a small thumbnail)
- **Format:** JPG or PNG (not SVG)
- **File size:** under 1 MB recommended

After changing an image, use the [Facebook Sharing Debugger](https://developers.facebook.com/tools/debug/) → **Scrape Again** to refresh Facebook's cache.

---

*← [Site Types](vertical-presets.md) | [Documentation Index](index.md) | [Multilingual Sites →](multilingual.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
