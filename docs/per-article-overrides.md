# Per-Article Overrides — Custom Fields for OpenGraph & Schema

JoomlaBoost reads Joomla's built-in Custom Fields (com_fields) to override global settings on a per-article basis. This lets you customize social preview cards and Schema.org data for individual pages without changing your site-wide configuration.

---

## Overview

For most pages, JoomlaBoost uses:
1. Your global settings (OG image, site name, etc.)
2. Article-level data (title, meta description, featured image)

Custom Fields allow you to override these on a page-by-page basis — perfect for important landing pages, campaign articles, or content where the generic defaults do not work well.

---

## Supported Custom Field Names

Create fields with **exactly** these names in **Content → Fields**:

| Field Name | Field Type | Effect |
|-----------|------------|--------|
| `custom_og_image` | Media | Overrides the OpenGraph image for this article |
| `custom_og_title` | Text | Overrides the OpenGraph title |
| `custom_og_description` | Textarea | Overrides the OpenGraph description |

> Field names are case-sensitive. `custom_og_image` works. `Custom_OG_Image` does not.

---

## Setup: Creating Custom Fields

### Step 1 — Open Fields Manager

1. Log in to your Joomla administrator panel.
2. Go to **Content → Fields**.

### Step 2 — Create the OG Image Field

1. Click **New** to create a new field.
2. Fill in:
   - **Name:** `custom_og_image` (exactly as shown)
   - **Label:** `Custom OG Image` (any label for your reference)
   - **Type:** Media
3. On the **Options** tab, set **Show Label** to No (the field does not need to show in frontend).
4. Click **Save & Close**.

### Step 3 — Create the OG Title Field (optional)

1. Click **New**.
2. Fill in:
   - **Name:** `custom_og_title`
   - **Label:** `Custom OG Title`
   - **Type:** Text
3. Save & Close.

### Step 4 — Create the OG Description Field (optional)

1. Click **New**.
2. Fill in:
   - **Name:** `custom_og_description`
   - **Label:** `Custom OG Description`
   - **Type:** Textarea
3. Save & Close.

---

## Using the Fields on an Article

1. Open any article in the Joomla Article Manager.
2. Scroll to the **Fields** section below the editor (or find it in the right sidebar depending on your template).
3. Fill in the custom field values:
   - **custom_og_image** — select an image via the media picker
   - **custom_og_title** — type the social share title (50–60 characters recommended)
   - **custom_og_description** — type the social share description (150–160 characters recommended)
4. Save the article.

---

## Priority Chain

JoomlaBoost resolves OpenGraph values using this priority order (highest priority first):

```
1. Joomla Custom Field (custom_og_image / custom_og_title / custom_og_description)
2. Article Featured Image (for og:image) / Article Title (for og:title) / Meta Description (for og:description)
3. Global settings in JoomlaBoost (OG Default Image, OG Site Name)
4. Organization Logo (last-resort fallback for og:image)
```

This means:
- If you set `custom_og_image` on an article, that image is used — the global default is ignored.
- If you do NOT set a custom field, the article's own featured image or title is used automatically.
- The global default only applies when no article-level data exists.

---

## Schema.org Per-Article Data

JoomlaBoost automatically generates `Article`, `NewsArticle`, or `BlogPosting` Schema from standard Joomla article fields — no custom fields are needed for this:

| Schema property | Source |
|-----------------|--------|
| `headline` | Article title |
| `description` | Meta description |
| `datePublished` | Article creation date |
| `dateModified` | Article last modified date |
| `author` | Article author (Joomla user) |
| `image` | Article featured image |
| `url` | Article canonical URL |

These values are read automatically. There is no custom field to configure for article Schema.

---

## Best Practices

**When to use custom OG fields:**
- High-traffic landing pages where the featured image is not suitable for social sharing
- Campaign articles where you want a specific promotional image
- Articles where the title is too long or technical for a social card (shorter titles perform better in shares)
- Evergreen content where you want to control the preview precisely

**Image specifications for custom_og_image:**
- **Recommended size:** 1200×630 pixels (16:9 ratio)
- **Minimum size:** 600×315 pixels (below this, Facebook shows it as a small thumbnail)
- **Format:** JPG or PNG
- **File size:** Under 8 MB (under 1 MB recommended for fast loading)

---

## Assigning Fields to a Field Group (Optional)

If you have many custom fields, organize them into a field group:

1. Go to **Content → Field Groups**.
2. Click **New** and name it `SEO / Social Override`.
3. When creating each custom field, assign it to this group.

This keeps your article editor organized.

---

*← [Vertical Presets Guide](vertical-presets.md) | [Documentation Index](index.md) | [Multilingual Sites →](multilingual.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
