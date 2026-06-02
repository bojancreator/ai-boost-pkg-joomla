# How to Add Business Hours to Your Joomla Schema (LocalBusiness Opening Hours)

**Category:** Schema.org | **Read time:** 6 min | **Published:** 2026-04-22

---

If you run a local business — a restaurant, clinic, law firm, gym, or any service with set opening times — Google and AI assistants need to know when you're open. That information doesn't live in your written content alone; search engines and AI engines read it from structured data baked into your page's HTML. The Schema.org property that carries this information is called `openingHoursSpecification`, and getting it right can directly impact whether your business appears in AI-powered answers, local packs, and knowledge panels.

This article explains exactly what `openingHoursSpecification` is, why it matters in 2026, and how you can add it to your Joomla site in minutes — without writing a single line of JSON.

---

## What Is openingHoursSpecification?

`openingHoursSpecification` is a property of the `LocalBusiness` schema type (and all its subtypes — `Restaurant`, `MedicalClinic`, `LegalService`, etc.). It tells machines, in a structured and unambiguous way, the exact days and hours your business is open.

A complete `openingHoursSpecification` entry looks like this in JSON-LD:

```json
{
  "@context": "https://schema.org",
  "@type": "Restaurant",
  "name": "Bella Vista",
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
      "opens": "09:00",
      "closes": "18:00"
    },
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Saturday"],
      "opens": "10:00",
      "closes": "14:00"
    }
  ]
}
```

Notice that Sunday is simply omitted — which correctly signals that the business is closed that day.

---

## Why Business Hours Markup Matters More Than Ever

### Google uses it in Knowledge Panels and local packs

When someone searches for your business name or a service you provide nearby, Google may display a knowledge panel with your hours. If the markup is missing or wrong, Google falls back to guessing — often incorrectly — or shows nothing at all.

### AI assistants read it directly

Perplexity AI, ChatGPT with browsing, and Google AI Overviews increasingly answer questions like "Is [business] open on Saturday?" by reading your schema. If you have no `openingHoursSpecification`, the AI either skips your site or gives a generic "check their website" response. With correct markup, you become the cited source for the answer.

### It removes ambiguity for international visitors

Written text like "Open weekdays 9–6" is ambiguous — especially for AI models trained on multilingual data. `openingHoursSpecification` uses a standardised machine-readable format (ISO 8601 times, full day names) that leaves no room for misinterpretation.

---

## Common Mistakes with Business Hours Schema

**1. Using the wrong property**

`openingHours` (the simple string version, e.g., `"Mo-Fr 09:00-18:00"`) is the older shorthand. It still works, but `openingHoursSpecification` is the recommended modern form and allows for much more detail — including special holiday hours (`validFrom` / `validThrough`).

**2. Omitting closed days**

You don't list days when you're closed — you simply don't include them. If you add a Sunday entry with `"opens": "00:00"` and `"closes": "00:00"`, that actually means open all day on Sunday. Leave it out instead.

**3. Getting the time format wrong**

Hours must be in 24-hour format: `"09:00"` not `"9am"`. Midnight closing time is `"23:59"`, not `"00:00"` (which would mean midnight opening).

**4. Hardcoding hours in the wrong place**

Many Joomla sites try to embed this information in article content or a custom HTML module. AI engines and search bots need it in the page `<head>` or as an inline `<script type="application/ld+json">` block — not as visible text.

---

## Split Hours and Special Schedules

Some businesses have a lunch break — say, a clinic open 08:00–12:00 and 14:00–18:00. You handle this by creating two separate `OpeningHoursSpecification` entries for the same day:

```json
"openingHoursSpecification": [
  {
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
    "opens": "08:00",
    "closes": "12:00"
  },
  {
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
    "opens": "14:00",
    "closes": "18:00"
  }
]
```

For holiday hours, you add `validFrom` and `validThrough`:

```json
{
  "@type": "OpeningHoursSpecification",
  "dayOfWeek": ["Sunday"],
  "opens": "10:00",
  "closes": "16:00",
  "validFrom": "2026-12-26",
  "validThrough": "2027-01-02"
}
```

---

## How to Validate Your Business Hours Schema

After adding your markup, always validate it with:

- **Google Rich Results Test** — [search.google.com/test/rich-results](https://search.google.com/test/rich-results)
- **Schema.org Validator** — [validator.schema.org](https://validator.schema.org)

Check that all days are parsed correctly, no warnings appear, and the `LocalBusiness` type is detected.

---

## Adding Business Hours to Joomla — Without Touching Code

Editing JSON-LD by hand is error-prone. And manually re-editing a PHP template every time your hours change is not a sustainable workflow.

**AI Boost for Joomla** solves this with a dedicated Business Hours interface in the plugin admin — a clean table-based widget where you set each day's opening and closing times using dropdowns. The plugin builds the correct `openingHoursSpecification` JSON-LD automatically and injects it into every page of your site.

Features of the Business Hours widget in AI Boost:

- **All same / Individual days toggle** — set one schedule for all days or customise each day separately
- **Split hours support** — morning and afternoon sessions per day
- **Instant preview** — see the generated JSON-LD before saving
- **Automatically linked to your LocalBusiness type** — works with all 13 supported schema types including Restaurant, MedicalClinic, LegalService, HealthClub, Dentist, and more

No template editing. No PHP knowledge required. Your hours stay in one place and update across the entire site instantly.

---

## The Bigger Picture: Business Hours as Part of Your Local Schema

Business hours work best as part of a complete `LocalBusiness` schema that includes:

- **name** — your exact business name
- **address** — full postal address
- **telephone** — formatted correctly with country code
- **geo** — latitude/longitude coordinates
- **openingHoursSpecification** — as described above
- **priceRange** — from $ to $$$$ (optional but useful)
- **url** — your website

When all of these properties are present and accurate, you give Google and AI engines everything they need to confidently feature your business in local results and AI-generated answers.

---

## Ready to Add Business Hours to Your Joomla Site?

Stop leaving your opening hours to chance. With AI Boost for Joomla, you set your hours once in a simple table interface and the plugin handles all the schema markup — injected correctly, validated, and automatically updated whenever you make a change.

**[Get AI Boost for Joomla →](https://aiboostnow.com)**

Starter licence from €59 — one-time payment, works with Joomla 4, 5, and 6.
