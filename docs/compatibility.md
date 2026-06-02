# Compatibility Matrix

JoomlaBoost is tested against a wide range of Joomla versions, PHP versions, templates, and third-party extensions. Use this page to verify compatibility before installing.

---

## Joomla Versions

| Joomla Version | Status | Notes |
|----------------|--------|-------|
| 3.x | ❌ Not supported | AI Boost requires Joomla 5.0 minimum |
| 4.x | ❌ Not supported | Dropped in v0.57.0 (Joomla 4 reached end-of-life August 2025) |
| 5.0 – 5.x | ✅ Fully supported | Primary development target (LTS through 2027) |
| 6.x | ✅ Compatible | Tested on Joomla 6.1 |

> **Recommended:** Joomla 5.x for the best experience and longest support window.

---

## PHP Versions

| PHP Version | Status | Notes |
|-------------|--------|-------|
| 7.x | ❌ Not supported | |
| 8.0 | ❌ Not supported | 8.1 is the minimum |
| 8.1 | ✅ Supported | Minimum required version |
| 8.2 | ✅ Fully supported | Recommended |
| 8.3 | ✅ Supported | Tested and compatible |

> **Recommended:** PHP 8.2 or 8.3 for performance and security.

---

## Database

| Database | Version | Status |
|----------|---------|--------|
| MySQL | 5.7+ | ✅ Supported |
| MySQL | 8.0+ | ✅ Recommended |
| MariaDB | 10.3+ | ✅ Supported |
| MariaDB | 10.6+ | ✅ Recommended |
| PostgreSQL | — | ❌ Not supported (Joomla 5/6 limitation) |

---

## Joomla Templates

| Template | Compatibility | Notes |
|----------|:-------------:|-------|
| Cassiopeia (Joomla default) | ✅ Full | |
| Helium (JoomlaShack) | ✅ Full | |
| YooTheme Pro 4.x / 5.x | ✅ Full | Includes Consent Manager integration for GA4 and Meta Pixel |
| Helix Ultimate | ✅ Full | |
| T4 Framework | ✅ Full | |
| Astroid Framework | ✅ Full | |
| Protostar / Beez (Joomla 3) | ❌ N/A | Joomla 3 not supported |

---

## Third-Party Plugins & Extensions

### SEO Plugins

| Plugin / Extension | Compatibility | Action required |
|-------------------|:-------------:|-----------------|
| Sh404SEF | ✅ Compatible | Disable Sh404's OpenGraph meta tag generation to avoid duplicate `og:` tags |
| JoomSEF | ✅ Compatible | Disable JoomSEF's meta tag generation |
| OSMap | ✅ Compatible | JoomlaBoost's sitemap replaces OSMap's sitemap — disable OSMap's sitemap output to avoid conflicts |
| EasyBlog SEO | ✅ Compatible | Review EasyBlog's Schema.org output to ensure no duplicates |

### Performance & Caching Plugins

| Plugin / Extension | Compatibility | Action required |
|-------------------|:-------------:|-----------------|
| JCH Optimize | ✅ Compatible | Ensure JCH does not strip `<script type="application/ld+json">` — check JCH's "Exclude Scripts" settings |
| Akeeba Boost | ✅ Compatible | No action needed |
| CDN / CloudFlare | ✅ Compatible | Ensure CDN caching does not serve stale `robots.txt` or `sitemap.xml` (set edge cache TTL appropriately) |

### Multilingual Plugins

| Plugin / Extension | Compatibility | Notes |
|-------------------|:-------------:|-------|
| Joomla Language Filter (native) | ✅ Full | Hreflang uses Joomla's native language associations |
| Falang | ✅ Full | JoomlaBoost auto-detects Falang languages; multilingual fields expand accordingly |
| FaLang Translate | ✅ Full | |
| Multilingual Manager (native Joomla) | ✅ Full | |

### E-commerce Extensions

| Extension | Schema.org support | Notes |
|-----------|:-----------------:|-------|
| VirtueMart | ⚠️ Partial | JoomlaBoost adds site-level Organization Schema; VirtueMart's own product Schema is not covered — use VirtueMart's built-in Schema or a dedicated product Schema plugin |
| J2Store | ⚠️ Partial | Same as VirtueMart |
| HikaShop | ⚠️ Partial | Same as VirtueMart |
| Akeeba Subs | ✅ Compatible | No conflicts |

### Analytics & Tracking

| Plugin / Tool | Compatibility | Notes |
|--------------|:-------------:|-------|
| Google Site Kit (WordPress) | ❌ N/A | WordPress only |
| YooTheme Customizer Analytics | ⚠️ Avoid duplicate | Disable YooTheme's GA4 if using JoomlaBoost's GA4 integration, or route via GTM |
| Facebook Pixel (manual) | ⚠️ Avoid duplicate | Disable manual pixel code if using JoomlaBoost's Meta Pixel feature |

### Event Plugins

| Plugin / Extension | Sitemap | Schema.org |
|-------------------|:-------:|:----------:|
| JEvents | ⚠️ Partial | JEvents events are not automatically added to JoomlaBoost's sitemap. Use the manual Events JSON in JoomlaBoost (Developer/Agency) for key events |
| DPCalendar | ⚠️ Partial | Same as JEvents |

### Backup Plugins

| Plugin | Compatibility |
|--------|:-------------:|
| Akeeba Backup | ✅ Full — no conflicts |
| EasyJoomlaBackup | ✅ Full |

---

## Known Conflicts

### OSMap — Sitemap Conflict

If both OSMap and JoomlaBoost are active with sitemaps enabled:
- Both attempt to serve `yoursite.com/sitemap.xml`
- Whichever plugin handles the URL first wins

**Resolution:** Disable OSMap's sitemap generation (keep JoomlaBoost's sitemap active). JoomlaBoost's sitemap includes hreflang support and AI-era priorities that OSMap does not provide.

### JCH Optimize — JSON-LD Stripping

JCH Optimize's HTML minification can occasionally strip or break `<script type="application/ld+json">` blocks.

**Resolution:** In JCH Optimize settings → Optimize JavaScript → add `application/ld+json` to the exclusion list, or disable inline script optimization.

### Template-Level OpenGraph Tags

Some templates (including older versions of YooTheme Pro and some Helix-based templates) generate their own OpenGraph tags.

**Resolution:** Disable the template's OpenGraph generation (usually in the template's options or customizer), and use JoomlaBoost exclusively for OG tags.

---

## Minimum Server Requirements Summary

| Component | Minimum |
|-----------|---------|
| Joomla | 5.0.0 |
| PHP | 8.1.0 |
| MySQL | 5.7 |
| MariaDB | 10.3 |
| Disk space | 2 MB |
| Admin access | Super Administrator |

---

*← [Troubleshooting](troubleshooting.md) | [Documentation Index](index.md)*

*JoomlaBoost v0.24.0 — © 2025–2026 AI Boost Now.*
