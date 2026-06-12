# Site Identity — Identity, Contact, Social & Location

**Site Identity** (sidebar **SETUP → Site Identity**) defines who you are. This data powers the `Organization` (or LocalBusiness-type) Schema.org block injected on every page — the foundation that AI engines, Google and Bing use to identify your business as a trusted entity. It also feeds `llms.txt` and acts as a fallback for social previews.

Site Identity is the first step of the **Autopilot** checklist (SETUP → Autopilot).

---

## Identity

### Organization Name

Your official business or website name as it should appear in search results and AI citations. Keep it consistent with your registered business name.

### Organization Description

A 1–3 sentence description of your organisation. Keep it factual and authoritative — AI engines use this as a primary source when generating citations and summaries about your business.

### Organization Logo (+ alt text)

Select your logo via the media picker and give it a short alt text. Recommendations:

- Minimum size: 112×112 pixels
- Square format for the Google Knowledge Panel, or landscape (e.g. 600×200)
- Format: PNG (transparent background) or JPG
- The logo appears in rich results and serves as the last-resort fallback for OpenGraph images

### Optional identity details

- **Business Photo** — a photo of your premises or team, used in business-type schema.
- **Registered Legal Name** — if it differs from your public name.
- **Year / Date Established** — founding year or date.

> **Multilingual sites (Pro):** Organisation Name, Description, Logo (with its alt text) and the Street Address and City / Locality fields carry a **Translations** expander so the front-end output can differ per language. See [Multilingual Sites](multilingual.md).

---

## Contact Information

| Field | Notes |
|-------|-------|
| **Organization URL** | Your canonical website URL — used as the `url` property in Schema.org |
| **Email Address** | Public contact e-mail |
| **Phone Number** | International (E.164) format with country code, e.g. `+44 20 7946 0958` |
| **VAT / Tax ID** | Optional |
| **Map / Directions URL** | A Google Maps (or similar) link to your location |

---

## Social Media Links

These URLs populate the `sameAs` array in your Schema.org Organization block — a key signal for AI entity disambiguation. AI engines use `sameAs` to confirm that your website and your social profiles represent the same real-world entity.

Fields are provided for **Facebook, Instagram, YouTube, Twitter / X, LinkedIn and TikTok**. Fill in only the networks where you have an active presence — empty fields are excluded from the output, so never enter placeholder URLs.

> **Best practice:** add at least 2–3 social profiles. More `sameAs` references increase the confidence AI engines have when deciding whether to cite your business.

---

## Address

| Field | Example |
|-------|---------|
| **Street Address** | `Knez Mihailova 10` |
| **City / Locality** | `Belgrade` |
| **State / Region** | `Greater London` |
| **Postal Code** | `W1A 1AA` |
| **Country Code** | `GB`, `DE`, `US` — two-letter ISO 3166-1 alpha-2 code |

A complete address is required for LocalBusiness-type schema (local shops, restaurants, hotels, clinics, etc.) to be eligible for local rich results.

---

## Geographic Coordinates

**Latitude / Longitude** in decimal degrees (WGS84), e.g. `44.8178` / `20.4569`. To find them: open Google Maps, right-click your location, and copy the numbers shown.

GPS coordinates are **strongly recommended** for LocalBusiness-type schema. They enable:

- Google Maps integration in rich results
- "Near me" search matching
- AI answers that include location context

---

## Guest / Customer Rating (AggregateRating)

Adding `AggregateRating` to your schema can enable **star ratings in search results** — a significant click-through boost. Only use this if you have verifiable reviews from a recognised third-party platform.

| Field | Example |
|-------|---------|
| **Rating Value** | `4.6` |
| **Review Count** | `2341` |
| **Best Rating** | `5` |
| **Worst Rating** | `1` |
| **Rating Source** | `Booking.com` |

> **Important:** Google's structured data guidelines prohibit fabricated or self-authored ratings. Only enter data sourced from a third-party platform (Booking.com, TripAdvisor, Google, etc.). Violations can result in a manual penalty.

---

## Recommended priorities

| Field | Priority |
|-------|----------|
| Organization Name | Required |
| Organization URL | Required |
| Organization Description | Strongly recommended |
| Organization Logo | Strongly recommended |
| Phone Number | Recommended |
| Country Code + City | Required for LocalBusiness-type schema |
| Street Address | Recommended for LocalBusiness-type schema |
| GPS Coordinates | Strongly recommended for LocalBusiness-type schema |
| At least 2 social media links | Recommended for all sites |
| Customer Rating | Optional (verifiable data only) |

The matching business/organisation **type** (Restaurant, Hotel, MedicalClinic, …) is chosen on **SEO → Schema.org** — see [Site Types](vertical-presets.md).

---

*← [Admin Navigation Guide](plugin-tab.md) | [Documentation Index](index.md) | [Schema.org →](schema-org.md)*

*AI Boost for Joomla — © 2025–2026 AI Boost ([aiboostnow.com](https://aiboostnow.com)).*
