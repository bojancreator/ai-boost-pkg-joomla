# Schema.org Tab — Structured Data, FAQ & Events

The **Schema.org** tab controls the structured data output JoomlaBoost injects into every page. Structured data is machine-readable information formatted as JSON-LD that search engines and AI assistants use to understand and categorize your content.

---

## Enable Schema Markup

**Field:** `enable_schema`  
**Default:** Yes

Master toggle for all Schema.org output. When **Yes**, JoomlaBoost injects a `<script type="application/ld+json">` block in the `<head>` of every page.

Disable only if you have another plugin managing Schema.org and want to avoid conflicts.

---

## Schema Type

**Field:** `schema_type`  
**Default:** Auto-detect

Determines the primary Schema.org type used for your site-wide Organization block.

| Option | When to use |
|--------|-------------|
| **Auto-detect (recommended)** | JoomlaBoost selects the best type based on your other settings |
| **Organization (generic)** | Companies, agencies, services, non-profits without a fixed physical location |
| **LocalBusiness (physical location)** | Any brick-and-mortar business customers visit in person |
| **Hotel (accommodation)** | Hotels, hostels, bed & breakfasts, vacation rentals, camping sites |

When set to **Hotel** or **LocalBusiness**, additional type-specific fields appear below.

---

## Hotel-Specific Fields

> **Visible when:** Schema Type = Hotel

### Star Rating

**Field:** `schema_hotel_star_rating`  
**Default:** 3 stars

Official star classification (1–5). Maps to the `starRating` property in the `LodgingBusiness` Schema type.

### Check-in Time / Check-out Time

**Fields:** `schema_hotel_checkin_time` / `schema_hotel_checkout_time`  
**Default:** `14:00` / `12:00`  
**Format:** 24-hour (`HH:MM`)

Standard check-in and check-out times. These appear in Google's rich results for accommodations and are used by booking platforms that read your Schema.

### Pets Allowed

**Field:** `schema_hotel_pets_allowed`  
**Default:** No

Set **Yes** if your property is pet-friendly. Surfaces in Google's rich result filters and AI-generated travel summaries.

---

## LocalBusiness & Hotel Service Details

> **Visible when:** Schema Type = LocalBusiness or Hotel

### Price Range

**Field:** `schema_price_range`  
**Default:** `$$`

A general pricing indicator:

| Value | Meaning |
|-------|---------|
| `$` | Budget / inexpensive |
| `$$` | Moderate |
| `$$$` | Expensive |
| `$$$$` | Very expensive / luxury |

### Opening Hours

**Field:** `schema_opening_hours`  
**Default:** `Mo-Su 09:00-18:00`  
**Format:** Schema.org `openingHours` notation

Examples:
- `Mo-Fr 09:00-18:00` — Monday to Friday, 9 AM – 6 PM
- `Mo-Fr 09:00-18:00, Sa 10:00-14:00` — plus Saturday morning
- `Mo-Su 00:00-23:59` — open 24 hours

---

## FAQ Schema

FAQ Schema enables **FAQ rich results** in Google Search — expandable Q&A entries that appear directly in the search results page, significantly increasing visibility and click-through rates. AI engines also prioritize FAQ-structured content when generating Q&A-style responses.

### Auto-Detect FAQ from Content

**Field:** `faq_auto_detect`  
**Default:** Yes

When **Yes**, JoomlaBoost scans each article's rendered HTML for common FAQ markup patterns:

| Pattern detected | HTML structure |
|------------------|----------------|
| Description lists | `<dl>` / `<dt>` (question) / `<dd>` (answer) |
| Heading + paragraph | `<h3>` or `<h4>` followed by `<p>` |
| Accordions | `<details>` / `<summary>` |

When a pattern is detected, a `FAQPage` JSON-LD block is injected automatically for that page — no configuration required.

> **Recommendation:** Enable this on all site types. Structure your FAQ content with `<h3>` headings for questions and `<p>` paragraphs for answers to get the best auto-detection results.

---

## Manual FAQ — Developer / Agency Feature

> **🔒 Requires Developer or Agency license.**  
> **Visible when:** Show Advanced Options = Yes  
> Starter and unlicensed users see an upgrade notice instead.

### Enable Manual FAQs

**Field:** `enable_manual_faqs`  
**Default:** No

When **Yes**, the JSON editor fields appear (one per installed language) to enter custom FAQ items. Manual FAQs are **merged with auto-detected FAQs** from article content.

### FAQ JSON Format

Enter an array of question/answer objects per language:

```json
[
  {
    "question": "What are your check-in hours?",
    "answer": "Check-in is from 14:00. Early check-in from 11:00 is available on request."
  },
  {
    "question": "Do you offer airport transfer?",
    "answer": "Yes, we offer private airport transfer. Please contact us 48 hours in advance."
  }
]
```

### Manual FAQ Scope

**Field:** `manual_faq_scope`  
**Default:** Fallback on all pages

Controls on which pages the manually entered FAQ is injected:

| Option | Behavior |
|--------|----------|
| **Fallback on all pages** | Injects global FAQ on any page that has no auto-detected FAQ |
| **All pages — always** | Always injects global FAQ, regardless of auto-detect results |
| **Homepage fallback** | Injects on homepage only if no auto-detected FAQ is found there |
| **Homepage only — always** | Always injects on homepage only, ignores auto-detect |
| **Disabled** | FAQs are saved in the database but never output to the page |

> **Note:** Auto-detected FAQs from article content are automatically translated by Falang when Falang is active. Manual FAQs must be entered separately per language in the multilingual JSON fields.

---

## Events Schema — Developer / Agency Feature

> **🔒 Requires Developer or Agency license.**  
> Starter and unlicensed users see an upgrade notice instead.

Events with proper `Event` Schema are eligible for **Google's Event rich results** — high-visibility placement in the search results events carousel, especially on mobile. They also appear in Google Maps and AI Overviews.

### Enable Event Schema

**Field:** `schema_events_enabled`  
**Default:** No

When **Yes**, JoomlaBoost injects `Event` JSON-LD blocks based on the JSON you define per language.

### Events JSON Format

Enter an array of event objects. Required fields per event: `name`, `startDate` (ISO 8601 format).

```json
[
  {
    "name": "Summer Jazz Festival 2026",
    "startDate": "2026-07-15T19:00:00+02:00",
    "endDate": "2026-07-17T23:00:00+02:00",
    "description": "Three nights of live jazz in the courtyard garden.",
    "location": "Knez Mihailova 10, Belgrade",
    "url": "https://yourdomain.com/jazz-festival",
    "price": "25",
    "currency": "EUR"
  }
]
```

**Optional event fields:**

| Field | Description |
|-------|-------------|
| `endDate` | ISO 8601 end date/time |
| `description` | Short event description |
| `url` | Link to the event page |
| `location` | Overrides the organization's address for this event |
| `price` | Ticket price as a number |
| `currency` | Currency code (default: `EUR`) |

> **Dates must be ISO 8601 format:** `YYYY-MM-DDTHH:MM:SS+HH:MM` (with timezone offset). Example: `2026-09-15T09:00:00+02:00`.

---

## Verification Tools

After saving your Schema.org settings, validate your output with these tools:

| Tool | URL | What to test |
|------|-----|-------------|
| Google Rich Results Test | [search.google.com/test/rich-results](https://search.google.com/test/rich-results) | FAQPage, Event, LocalBusiness, Hotel |
| Schema.org Validator | [validator.schema.org](https://validator.schema.org) | Full Schema validation |
| Google Search Console | Enhancements → Rich Results | Production monitoring |

---

## Recommended Settings (Schema.org Tab)

| Setting | Recommended value |
|---------|------------------|
| Enable Schema Markup | Yes |
| Schema Type | Auto-detect (or set by vertical preset) |
| FAQ Auto-Detect | Yes |
| Star Rating (Hotel) | Set accurately |
| Check-in / Check-out (Hotel) | Set accurately |
| Manual FAQ | Use for homepage or site-wide FAQs *(Developer/Agency)* |
| Events | Enable if you run events *(Developer/Agency)* |

---

*← [Organization Tab](organization.md) | [Documentation Index](index.md) | [Sitemap Tab →](sitemap.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
