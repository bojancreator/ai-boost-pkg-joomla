# Schema.org — Structured Data, FAQ, HowTo & Events

The **Schema.org** page (sidebar **SEO → Schema.org**) controls the structured data AI Boost for Joomla injects into every page. Structured data is machine-readable JSON-LD that search engines and AI assistants use to understand and categorise your content.

The page is organised in four sections: **Core**, **Business**, **Hours** and **FAQ / Rich**.

---

## Core

### Enable Schema.org structured data

Master toggle for all Schema.org output. When on, AI Boost emits `<script type="application/ld+json">` markup on every page. Disable only if another extension manages your structured data.

### WebSite Schema (homepage)

Emits a `WebSite` entity on the homepage. Optionally include **SearchAction** (the Sitelinks Search Box markup).

### Article Schema

Emits `Article`-family markup (`Article`, `NewsArticle`, `BlogPosting`) on article pages, generated automatically from the article's title, dates, author, meta description and featured image — no per-article configuration needed.

### Author Entity (Pro)

Emits a full `Person` entity for each article's author, fed from author custom fields (job title, bio, website, LinkedIn, Wikipedia). Strengthens author authority signals for Google and AI engines.

---

## Business

### Business / Organization Type

Pick a **Category** and a specific **Schema Type** — from generic `Organization` and `Person` through dozens of business types (Restaurant, LodgingBusiness, MedicalClinic, Dentist, LegalService, Store, EducationalOrganization, NewsMediaOrganization and more). See [Site Types](vertical-presets.md) for the full list and recommendations.

The identity data itself (name, logo, address, GPS, social profiles) comes from **SETUP → Site Identity**.

### Type-specific fields

Depending on the selected type, matching cards appear. Examples:

| Card | Fields |
|------|--------|
| Hotel Details | Star rating, check-in/check-out times, pets allowed |
| Food & Restaurant | Cuisine types, menu URL, accepts reservations |
| Medical / Dental | Specialty, patient area served |
| Business Operations | Price range, payment accepted, currencies accepted, amenity features |
| Person Profile | Job title, affiliation, topics & expertise |
| News & Media | Founding date, masthead URL, ethics policy URL |

### Services & Prices (Pro)

Named services with optional prices and currency, emitted in your business schema. Service names are translatable per language.

### More Details (Pro)

Type-aware extras: accepting new patients, number of rooms, credentials/licences, languages spoken, dietary suitability, return policy, number of employees, target audience, slogan, awards and more.

---

## Hours

For LocalBusiness-type schema, the **Hours** section provides a weekly schedule: per day (Monday–Sunday), mark the business closed or set opening and closing times. The schedule is emitted as `OpeningHoursSpecification`.

---

## FAQ / Rich

### FAQ / QAPage Schema (Pro)

- **Auto-Detect FAQ from Content** — scans article content for FAQ-style patterns (definition lists, question headings followed by paragraphs, accordions) and emits the markup automatically. Works with Falang-translated content.
- **Manual FAQ Items** — add your own question/answer pairs as JSON:

```json
[
  {"question": "What are your check-in hours?", "answer": "Check-in is from 14:00."},
  {"question": "Do you offer airport transfer?", "answer": "Yes, on request, 48 hours in advance."}
]
```

Each question and answer can be translated per language via the Translations expanders.

- **Schema Output Type** — emit `FAQPage`, `QAPage`, or both. (Google no longer shows FAQ rich results for most sites, but FAQ markup is still read by ChatGPT, Perplexity and Google AI Overviews — which is exactly where AEO value lies.)

### HowTo Schema (Pro)

Step-by-step `HowTo` markup: name, description, total time (ISO 8601, e.g. `PT15M`) and an editable list of steps.

### Event Schema (Pro)

`Event` markup driven by your Joomla content: point it at an **Events category**, and articles in that category are emitted as events. Event descriptions can be translated per language.

> **Dates:** event start dates must be valid ISO 8601 values for Google's Event rich results to qualify.

---

## Verification

After saving, validate your output:

| Tool | What to test |
|------|--------------|
| [Google Rich Results Test](https://search.google.com/test/rich-results) | FAQ, Event, LocalBusiness types |
| [Schema.org Validator](https://validator.schema.org) | Full schema validation |
| **TOOLS → Analyzers → JSON-LD Validator** | Validate directly inside the admin panel |
| **OVERVIEW → Health** | Schema checks with one-click fix actions |

---

## Recommended Settings

| Setting | Recommended value |
|---------|------------------|
| Enable Schema.org structured data | Yes |
| WebSite Schema + SearchAction | Yes |
| Article Schema | Yes for any site that publishes articles |
| Schema Type | The most specific type that truly matches your business |
| Hours | Fill in for any business customers visit |
| FAQ auto-detect | Yes *(Pro)* |
| Author Entity | Yes for publishers *(Pro)* |

---

*← [Site Identity](organization.md) | [Documentation Index](index.md) | [Sitemap →](sitemap.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
