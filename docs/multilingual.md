# Multilingual Sites — Hreflang, Falang & Native Joomla Multilingual

JoomlaBoost is built for multilingual Joomla sites. It integrates natively with both Joomla's built-in multilingual system and the Falang translation plugin, and exposes per-language fields across all major configuration areas.

---

## How Multilingual Fields Work

Fields marked as **(Multilingual)** in the JoomlaBoost admin panel are injected dynamically based on the languages published in your Joomla instance.

**Example with English and German installed:**
- Organization Name (English — ★ Default)
- Organization Name (German)
- Organization Description (English — ★ Default)
- Organization Description (German)
- … and so on for all multilingual fields

**The ★ Default language** serves as the fallback. You must always fill in the Default language field. Other languages can be left empty — they will inherit the default value automatically.

### Which fields are multilingual?

| Field | Location |
|-------|----------|
| Organization Name | Organization tab |
| Organization Description | Organization tab |
| Organization Logo | Organization tab |
| City / Locality | Organization tab |
| Street Address | Organization tab |
| OG Site Name | Social & Meta tab |
| OG Default Image | Social & Meta tab |
| Manual FAQ items | Schema.org tab *(Developer/Agency)* |
| Events JSON | Schema.org tab *(Developer/Agency)* |
| LLMs.txt Custom Pages | Analytics tab *(Developer/Agency)* |

---

## Joomla Native Multilingual System

If you use **Joomla's built-in Language Filter plugin** and native Content Associations:

1. Ensure the **Language Filter** system plugin is enabled in **System → Manage → Plugins**.
2. Publish at least two languages in **System → Manage → Languages**.
3. For each article, use the **Associations** tab to link translated versions of the same content.
4. Enable **Hreflang** in JoomlaBoost's Sitemap tab (enabled by default).

JoomlaBoost reads Joomla's language associations to generate the correct `hreflang` tags.

---

## Falang Integration

**Falang** is a popular Joomla translation plugin that stores translations in a separate database table rather than creating separate Joomla content items.

JoomlaBoost automatically detects Falang if it is installed and active:

1. Language codes are read from `#__falang_languages` and merged with native Joomla languages.
2. Multilingual fields in JoomlaBoost's admin panel expand to include Falang languages.
3. FAQ auto-detection works with Falang-translated article content — FAQs are detected from the translated version of each article.
4. No special configuration in JoomlaBoost is needed — Falang compatibility is automatic.

---

## Hreflang Tags

Hreflang tags tell search engines which language version of a page to show to users in different countries/language settings. They are critical for multilingual sites to prevent keyword cannibalization and ensure users land on the correct language version.

### Enabling Hreflang

Go to the **Sitemap** tab → set **Enable Hreflang** to **Yes** (default).

JoomlaBoost automatically generates `<link rel="alternate" hreflang="...">` tags in the `<head>` of every page.

### Hreflang in the Sitemap

For double coverage (both head tags and sitemap), enable **Add Hreflang to Sitemap** under Sitemap tab → Advanced Options.

**Sitemap hreflang example:**
```xml
<url>
  <loc>https://yourdomain.com/en/rooms</loc>
  <priority>0.8</priority>
  <xhtml:link rel="alternate" hreflang="en" href="https://yourdomain.com/en/rooms"/>
  <xhtml:link rel="alternate" hreflang="de" href="https://yourdomain.com/de/zimmer"/>
  <xhtml:link rel="alternate" hreflang="sr" href="https://yourdomain.com/sr/sobe"/>
  <xhtml:link rel="alternate" hreflang="x-default" href="https://yourdomain.com/en/rooms"/>
</url>
```

---

## Supported Languages

JoomlaBoost ships with 11 translated admin panel language packs:

| Language | Code |
|----------|------|
| English | `en-GB` |
| Serbian | `sr-RS` |
| German | `de-DE` |
| French | `fr-FR` |
| Spanish | `es-ES` |
| Italian | `it-IT` |
| Russian | `ru-RU` |
| Portuguese (Brazil) | `pt-BR` |
| Chinese (Simplified) | `zh-CN` |
| Arabic | `ar-AA` |
| Japanese | `ja-JP` |

The admin panel language is controlled by your Joomla administrator language setting, not by the content language. The content language determines which multilingual field set is shown.

---

## Schema.org & Multilingual Content

JoomlaBoost generates Schema.org output in the language of the active page:

- Organization Name, Description, Address, and City are output in the current page language
- The `sameAs` social links and GPS coordinates are language-independent
- FAQ Schema from auto-detection uses the translated article content
- Manual FAQs require separate JSON input per language (Developer/Agency)

---

## Manual FAQ in Multiple Languages

> **🔒 Requires Developer or Agency license.**

When Manual FAQs are enabled, a JSON editor appears **per installed language**. Enter the FAQ translations separately for each language:

**English FAQ field (`manual_faqs_en_GB`):**
```json
[
  {"question": "Do you offer free WiFi?", "answer": "Yes, complimentary WiFi is available throughout the property."}
]
```

**German FAQ field (`manual_faqs_de_DE`):**
```json
[
  {"question": "Bieten Sie kostenloses WLAN an?", "answer": "Ja, kostenloses WLAN ist im gesamten Hotel verfügbar."}
]
```

> **Auto-detected FAQs** from Falang-translated article content are automatically translated — Falang serves the translated content to JoomlaBoost's parser.

---

## Troubleshooting Multilingual Issues

### Multilingual fields show only one language

**Cause:** Only one language is published in Joomla.  
**Fix:** Go to **System → Manage → Languages** and ensure at least 2 languages are **Published** (Status = Published).

### Hreflang tags show wrong language codes

**Cause:** Language codes in Joomla's language settings may differ from the ISO hreflang codes.  
**Fix:** Verify your installed language tags in **System → Manage → Languages** — the Tag column shows the code JoomlaBoost uses for hreflang (e.g., `en-GB`, `de-DE`, `sr-RS`).

### Hreflang tags missing for some pages

**Cause:** Article Associations are not set up between translated content items.  
**Fix:** Open each article → **Associations** tab → link all language versions of the article. Only associated articles generate hreflang pairs.

### Falang translations not appearing in JoomlaBoost fields

**Cause:** Falang languages may not be published.  
**Fix:** Open Falang component → Languages → ensure each language is **Active**.

---

*← [Per-Article Overrides](per-article-overrides.md) | [Documentation Index](index.md) | [Troubleshooting →](troubleshooting.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
