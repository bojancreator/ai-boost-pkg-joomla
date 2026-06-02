# AI Boost for Joomla — Changelog

All notable changes to **AI Boost for Joomla** are documented here.  
Latest version is always at the top. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

**Download:** [aiboostnow.com](https://aiboostnow.com)  
**Requires:** Joomla 5.0–6.x · PHP 8.1+

---

## [Unreleased]

> Changes staged for the next release will appear here.

---

## v0.27.1 — 2026-05-12

### Fixed
- **Tab labels in other plugins no longer corrupted** — `onContentPrepareForm` now guards against running multilang field injection, field prefix registration, and JS/CSS asset loading for any plugin edit form that is *not* AI Boost for Joomla. Previously, opening the edit form of any other system plugin (e.g. System – Cache, System – Debug, System – SEF) caused their tab labels to display as raw language constants (e.g. `PLG_SYSTEM_CACHE_TAB_CACHING`) instead of translated text. The fix checks `$data->element` (object, Joomla 4/5 standard), `$data['element']` (array, legacy), and falls back to the URL `element` parameter — covering all Joomla 4–6 variants. All other plugin forms now exit immediately; only the `joomlaboost` element proceeds to injection.

---

## v0.27.0 — 2026-05-11

### Added
- **Pro feature gating for 8 specialized Site Types** — MedicalClinic, LegalService, EducationalOrganization, HealthClub, Dentist, RealEstateAgent, Person, and NewsMediaOrganization are now restricted to Developer and Agency license tiers.
- **Pro feature gating for Advanced Business Hours** — Full per-day schedule customization is now a Developer/Agency feature.
- **Pro upgrade notices** — Free-tier users see a clear in-admin message explaining which plan unlocks each Pro feature, with a direct link to the pricing page.
- **Developer bypass toggle** — A Debug tab option lets developers preview all Pro features without a license key (for local development and testing only).

### Changed
- Free tier retains the 5 general-purpose Site Types: LocalBusiness, Restaurant, Hotel, ECommerce Store, and Generic (fallback).
- Feature gating silently skips Pro output rather than showing errors — no impact on site visitors.

---

## v0.26.0 — 2026-05-07

### Added
- **Compact Business Hours widget** — New 7-row table UI replaces the previous freeform hours input. Each day has its own open/close time fields.
- **"All same hours" toggle** — A single switch lets users apply one schedule to all 7 days at once, or switch to individual per-day configuration.
- Mobile-optimized layout for the Business Hours table in the Joomla admin panel.

### Changed
- Business hours display in Schema.org output improved for clarity and spacing.

---

## v0.25.1 — 2026-05-07

### Added
- Custom field class support added for improved layout control in the admin panel.

### Fixed
- Confirmed compatibility with Joomla 6.1 staging environment — all 13 Site Type presets produce valid Schema.org output (13/13 ✅).
- Fixed an error that occurred when displaying a weekly schedule via custom field rendering.

---

## v0.25.0 — 2026-05-07

### Added
- **License activation panel** — The Plugin tab now shows active license details (tier, domain count, expiry) once a key has been verified.
- Build tooling: automated ZIP generation script and version bump script for consistent releases.

### Changed
- Branding updated throughout admin UI: all references changed to **AI Boost for Joomla**.

---

## v0.24.0 — 2026-05-04

### Added
- **8 new specialized Site Type presets:**
  - MedicalClinic — structured data for healthcare providers
  - LegalService (Lawyer/Law Firm) — Schema.org LegalService type
  - EducationalOrganization — for schools, academies, and online courses
  - HealthClub (Gym/Fitness) — with amenity and membership fields
  - Dentist — dental practice schema with specialty fields
  - RealEstateAgent — agency and listing schema
  - Person (Portfolio) — for personal brands, freelancers, and creatives
  - NewsMediaOrganization — for news sites and digital publications
- **Advanced Business Hours system** — Full `OpeningHoursSpecification` support: per-department schedules, holiday overrides, seasonal hours.
- **Complete 11-language internationalization** — 319 string constants extracted to `JOOMLABOOST_*` keys across 22 `.ini` files. Supported languages: English (primary), Serbian, German, Spanish, French, Italian, Russian, Portuguese (BR), Chinese, Arabic, Japanese.
- **License key field** — New custom field in the Plugin tab for entering and activating Gumroad license keys. All 11 language files include key labels and status messages.

### Changed
- Admin UI strings are now fully translatable — no hardcoded English in the interface.
- Site Type selector reorganized: 5 general-purpose types remain free; specialized types are clearly labeled.

---

## v0.23.0 — 2026-04-29

### Added
- LiteSpeed server bypass for dynamically generated files (`robots.txt`, `llms.txt`, XML sitemap) to prevent static caching of these routes.
- IndexNow ping reliability improvements — key file now served correctly through LiteSpeed environments.

### Fixed
- XML manifest parsing errors on certain Joomla installations.
- Plugin installation failure caused by manifest file path mismatch.
- Duplicate phone field in Organization tab.
- Dynamic language detection now correctly falls back to `en-GB`.

### Changed
- Admin panel descriptions shortened for better readability across all 7 tabs.
- Multilingual fields moved under "Advanced Options" collapsible section to reduce visual clutter.
- Plugin interface reorganized to group related fields more clearly.

---

## v0.22.0 — 2026-04-29

### Added
- **llms.txt generation** — Automatically serves `/llms.txt` describing the site's content for AI crawlers (ChatGPT, Perplexity, Claude, Gemini).
- **Hreflang support** — Multi-language XML sitemap with `<xhtml:link>` alternates for all active Joomla languages.
- **Schema.org Wizard** — Guided setup for selecting the right schema type for any site.
- **FAQ auto-detect** — Automatically detects FAQ-style content in Joomla articles and generates `FAQPage` structured data.
- **Performance module** — DNS prefetch, preconnect, resource hints, and optional lazy-load image injection.
- **Manual FAQ builder** — Add custom Q&A pairs directly in the plugin admin (Developer/Agency tier).
- **Events schema** — Schema.org `Event` structured data with date, location, organizer, and offers fields (Developer/Agency tier).
- **IndexNow integration** — Instant URL submission to Bing and Yandex on article publish/update (Developer/Agency tier).
- **Google Analytics 4 + GTM** — Head injection for GA4 measurement ID and Google Tag Manager container.
- **Meta Pixel** — Facebook/Instagram pixel injection with standard event support.
- **Google Search Console verification** — Meta tag injection for GSC property verification.

### Changed
- Plugin restructured into 7 focused admin tabs: Plugin, Organization, Schema.org, Sitemap, Social & Meta, Analytics, Debug.
- Feature gating introduced: advanced features (Manual FAQ, Events, IndexNow, llms.txt) restricted to Developer and Agency tiers.

---

## v0.21.0 and earlier

Early development versions (April 2026). Core plugin scaffold including basic Schema.org LocalBusiness output, XML sitemap generation, OpenGraph/Twitter Card meta tags, and robots.txt management. Not publicly released.

---

## System Requirements

| Requirement | Minimum |
|-------------|---------|
| Joomla | 4.0 |
| PHP | 8.1 |
| MySQL | 5.7 / MariaDB 10.4 |
| Joomla Maximum | 6.x (tested on 6.1) |

---

## License Tiers

| Feature | Free (Starter) | Developer | Agency |
|---------|:--------------:|:---------:|:------:|
| Schema.org (5 general types) | ✓ | ✓ | ✓ |
| XML Sitemap + hreflang | ✓ | ✓ | ✓ |
| OpenGraph + Twitter Cards | ✓ | ✓ | ✓ |
| robots.txt management | ✓ | ✓ | ✓ |
| GA4, GTM, GSC, Meta Pixel | ✓ | ✓ | ✓ |
| 8 specialized Site Types | — | ✓ | ✓ |
| Advanced Business Hours | — | ✓ | ✓ |
| Manual FAQ builder | — | ✓ | ✓ |
| Events schema | — | ✓ | ✓ |
| IndexNow | — | ✓ | ✓ |
| llms.txt generation | — | ✓ | ✓ |
| Sites covered | 1 | 5 | Unlimited |
| Price | €59 | €119 | €199 |

Purchase at [aiboostnow.com](https://aiboostnow.com).
