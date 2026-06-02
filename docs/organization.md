# Organization Tab — Identity, Contact, Social & Location

The **Organization** tab defines who you are. This data powers the `Organization` (or `LocalBusiness` / `Hotel`) Schema.org block injected on every page — the foundation that AI engines, Google, and Bing use to identify your business as a trusted entity.

---

## Identity (Multilingual)

These fields are injected dynamically based on the languages installed in your Joomla instance. If you have English and German installed, you see one field set per language. Always fill in the **Default** language (marked with ★); other languages fall back to the default if left empty.

### Organization Name

**Field:** `org_name_{lang}`  
**Example:** `Acme Hotel Manhattan`

Your official business or website name as it should appear in search results and AI citations. Keep it consistent with your registered business name.

### Organization Description

**Field:** `org_description_{lang}`  
**Example:** *"Acme Hotel is a 4-star hotel in Manhattan, New York, USA, offering luxury accommodation since 1999."*

A 1–3 sentence description of your organization. Keep it factual and authoritative — AI engines use this as the primary source when generating citations and summaries about your business.

### Organization Logo

**Field:** `org_logo_{lang}`

Select your logo via the Joomla media picker. Recommendations:
- Minimum size: 112×112 pixels
- Recommended: square format for Knowledge Panel, or landscape (e.g., 600×200)
- Format: PNG (transparent background) or JPG
- This logo appears in Google Knowledge Panel, rich results, and as the fallback for OpenGraph images

---

## Contact Information

### Organization URL

**Field:** `schema_url`  
**Example:** `https://www.yourdomain.com`

Your canonical website URL. Used as the `url` property in Schema.org. Leave empty to use the auto-detected domain.

### Email Address

**Field:** `schema_email`  
**Example:** `info@yourdomain.com`

Your public contact email. Used in the Schema.org `ContactPoint` block.

### Phone Number

**Field:** `schema_phone`  
**Example:** `+1 212 555 1234`

Use international format with country code. Used in `ContactPoint` Schema and displayed in local search results on Google.

---

## Social Media Links

These URLs populate the `sameAs` array in your Schema.org Organization block — a key signal for AI entity disambiguation. AI engines use `sameAs` to confirm that your website and your social profiles represent the same real-world entity.

| Platform | Field | Example value |
|----------|-------|---------------|
| Facebook | `schema_social_facebook` | `https://www.facebook.com/yourbusiness` |
| Instagram | `schema_social_instagram` | `https://www.instagram.com/yourbusiness` |
| YouTube | `schema_social_youtube` | `https://www.youtube.com/c/yourchannel` |
| Twitter/X | `schema_social_twitter` | `https://twitter.com/yourhandle` |
| LinkedIn | `schema_social_linkedin` | `https://www.linkedin.com/company/yourcompany` |

Fill in only the networks where you have an active presence. Empty fields are excluded from the Schema output — do not enter placeholder URLs.

> **Best practice:** Add at least 2–3 social profiles. More `sameAs` references increase the confidence score AI engines use when deciding whether to cite your business.

---

## Location

### Country Code

**Field:** `schema_address_country`  
**Example:** `US` (United States), `DE` (Germany), `GB` (United Kingdom), `AU` (Australia)

Two-letter ISO 3166-1 alpha-2 country code. Required for LocalBusiness and Hotel Schema types.

### Postal Code

**Field:** `schema_address_zip`  
**Example:** `10001`, `W1A 1AA`, `80331`

Your ZIP or postal code. Used in the `PostalAddress` Schema.

### City / Locality (Multilingual)

**Field:** `schema_address_locality_{lang}`  
**Example:** `New York` (en), `Nueva York` (es), `New York` (de)

Your city name, translated per installed language if applicable.

### Street Address (Multilingual)

**Field:** `schema_address_street_{lang}`  
**Example:** `Knez Mihailova 10`

Street name and number. Translate per language if needed.

### GPS Coordinates

**Fields:** `schema_latitude` / `schema_longitude`  
**Example:** `44.8178` / `20.4569`

Decimal degree format (WGS84). To find coordinates: open Google Maps, right-click your location, and copy the numbers shown.

GPS coordinates are **strongly recommended** for LocalBusiness and Hotel schema types. They enable:
- Google Maps integration in rich results
- "Near me" search matching
- AI Overviews that include location context

---

## Guest Ratings (AggregateRating) — Advanced

> **Visible when:** Show Advanced Options = Yes (set in Plugin tab)

Adding `AggregateRating` to your Schema enables **star ratings in Google Search results** — a significant CTR boost. Only use this if you have verifiable reviews from a recognized third-party platform.

| Field | Description | Example |
|-------|-------------|---------|
| **Average Rating** (`schema_rating_value`) | Your current average score | `4.6` |
| **Number of Reviews** (`schema_rating_count`) | Total review count | `2341` |
| **Best Rating** (`schema_rating_best`) | Maximum scale value | `5` |
| **Worst Rating** (`schema_rating_worst`) | Minimum scale value | `1` |
| **Rating Source** (`schema_rating_source`) | Platform name | `Booking.com` |

> **Important:** Google's structured data guidelines explicitly prohibit fabricated or self-authored ratings. Only enter data sourced from a third-party platform (Booking.com, TripAdvisor, Google, etc.). Violating this can result in a manual penalty.

---

## Recommended Settings (Organization Tab)

| Field | Priority |
|-------|----------|
| Organization Name (default language) | Required |
| Organization Description (default language) | Strongly recommended |
| Organization Logo | Strongly recommended |
| Website URL | Required |
| Phone Number | Recommended |
| Country Code | Required for LocalBusiness/Hotel |
| City / Locality | Required for LocalBusiness/Hotel |
| Street Address | Recommended for LocalBusiness/Hotel |
| GPS Coordinates | Strongly recommended for LocalBusiness/Hotel |
| At least 2 Social Media Links | Recommended for all sites |
| Guest Ratings | Optional (verifiable data only) |

---

*← [Plugin Tab](plugin-tab.md) | [Documentation Index](index.md) | [Schema.org Tab →](schema-org.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
