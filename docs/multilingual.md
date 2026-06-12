# Multilingual Sites — Hreflang, Falang & Per-Language Output

AI Boost for Joomla works on multilingual Joomla sites. The **admin interface is English**, but the **front-end output** (Schema.org values, OpenGraph values, `llms.txt` description and similar) can be translated per language using the Pro translation fields and the Falang integration.

---

## How per-language output works (Pro)

Translatable fields in the admin panel — for example Organisation Name, Organisation Description, the default OG image, or the llms.txt site description — carry a **Translations** expander next to the field:

1. Fill in the main field. This is the default value, used for every language you do not translate.
2. Click **Translations** to expand one row per installed content language.
3. Enter the translated value for each language you want to cover. Languages left empty fall back to the default automatically.

On the front end, AI Boost serves the value matching the active page language.

> **Pro feature:** in the Free edition the Translations expander appears as a locked **Translate — Pro** chip. The default (single-language) value always works in Free.

### Where you will find translatable fields

| Field | Location (sidebar) |
|-------|--------------------|
| Organisation Name / Description / Logo / Logo alt text | SETUP → Site Identity |
| Street Address, City / Locality | SETUP → Site Identity |
| OG Site Name, Default OG Image (+ alt), OG Description Override | SEO → Social Meta / OG |
| News sitemap Publication Name | SEO → Sitemap (Pro) |
| Site Description for AI (llms.txt) | AI VISIBILITY → AI Visibility |
| Manual FAQ questions/answers, Event descriptions, Service names | SEO → Schema.org (Pro) |

---

## Joomla native multilingual sites

If you use **Joomla's built-in Language Filter** and Content Associations:

1. Enable the **Language Filter** system plugin in **System → Manage → Plugins**.
2. Publish at least two languages in **System → Manage → Languages**.
3. For each article, use the **Associations** tab to link the translated versions.
4. Enable **Add hreflang to sitemap** under **SEO → Sitemap → Advanced Sitemap** (Pro — see below).

AI Boost reads Joomla's language associations to generate the correct `hreflang` pairs in the sitemap.

---

## Falang integration

**Falang** stores translations in its own database tables rather than creating separate Joomla content items. AI Boost detects Falang automatically when it is installed and active:

- Front-end output (including per-article OG custom field values) is served from the Falang translation of the current page, falling back to the default value.
- FAQ auto-detection (Pro) works with Falang-translated article content — questions and answers are detected from the translated version of each article.
- No special configuration in AI Boost is needed.

---

## Hreflang (Pro)

Hreflang annotations tell search engines which language version of a page to show to users in different language/country settings — critical for multilingual sites.

AI Boost emits hreflang **in the XML sitemap**: `<xhtml:link rel="alternate" hreflang="...">` entries inside each `<url>` block, enabled with **Add hreflang to sitemap** under **SEO → Sitemap → Advanced Sitemap**.

AI Boost does not add hreflang `<link>` tags to the page `<head>` — on native multilingual sites Joomla's own Language Filter plugin already emits head alternate links, so the sitemap alternates complement them without duplication.

**Sitemap hreflang example:**

```xml
<url>
  <loc>https://yourdomain.com/en/rooms</loc>
  <xhtml:link rel="alternate" hreflang="en" href="https://yourdomain.com/en/rooms"/>
  <xhtml:link rel="alternate" hreflang="de" href="https://yourdomain.com/de/zimmer"/>
  <xhtml:link rel="alternate" hreflang="x-default" href="https://yourdomain.com/en/rooms"/>
</url>
```

---

## Admin interface language

The AI Boost admin panel is **English only**. There are no translated admin language packs. The content languages installed on your site control the front-end output and the languages offered in the Translations expanders — not the admin UI language.

---

## Schema.org & multilingual content

- Organisation Name, Description, Address and City are output in the current page language when translations are filled in (Pro).
- `sameAs` social links and GPS coordinates are language-independent.
- Auto-detected FAQ schema (Pro) uses the translated article content.
- Manual FAQ items (Pro) accept per-language translations of every question and answer via the Translations expanders in the FAQ card.

---

## Troubleshooting multilingual issues

### The Translations expander shows no extra languages

**Cause:** only one content language is published in Joomla.
**Fix:** go to **System → Manage → Languages** and ensure at least two languages are **Published**.

### Sitemap hreflang entries show wrong language codes

**Cause:** language codes in Joomla's language settings differ from the expected hreflang codes.
**Fix:** verify the installed language tags in **System → Manage → Languages** — the Tag column shows the code used for the sitemap hreflang entries (e.g. `en-GB`, `de-DE`, `sr-RS`).

### Sitemap hreflang entries missing for some pages

**Cause:** article Associations are not set up between translated content items.
**Fix:** open each article → **Associations** tab → link all language versions. Only associated articles generate hreflang pairs in the sitemap.

### Falang translations not appearing in the output

**Cause:** the Falang language may not be active.
**Fix:** open the Falang component → Languages → ensure each language is **Active**.

---

*← [Per-Article Overrides](per-article-overrides.md) | [Documentation Index](index.md) | [Troubleshooting →](troubleshooting.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
