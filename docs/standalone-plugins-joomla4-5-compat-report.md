# AI Boost Standalone Plugins — Joomla 4 & 5 Compatibility Report

**Plugins:** 5 standalone system plugins (v1.0.0)  
**Joomla versions in scope:** Joomla 4.4 (LTS) and Joomla 5.x  
**Review date:** 2026-05-15  
**Method:** Full source-code review against Joomla 4/5 API + PHP syntax check (`php -l`)  
**Note:** No Joomla 4/5 staging environment is provisioned (Joomla 6.1 staging confirmed install ✅ in task #232). This report covers static compatibility analysis; live smoke-test checklist is at the bottom of this file.

---

## Summary

| Plugin | Joomla 4.4 | Joomla 5.x | PHP 8.1–8.5 | Bugs found |
|--------|-----------|-----------|------------|------------|
| AI Boost Schema | ✅ Compatible | ✅ Compatible | ✅ | None |
| AI Boost OpenGraph | ✅ Compatible | ✅ Compatible | ✅ | None |
| AI Boost Code Manager | ✅ Compatible | ✅ Compatible | ✅ | None |
| AI Boost AEO | ✅ Compatible | ✅ Compatible | ✅ | None |
| AI Boost Hreflang | ⚠️ Bug found → **Fixed** | ⚠️ Bug found → **Fixed** | ✅ | 1 — `getLanguages('published')` mismatch (fixed in task #236) |

**Overall: 5/5 plugins confirmed Joomla 4/5 compatible after fix. 1 bug found and fixed.**

---

## PHP Syntax Check

```
php -l plugins/aiboost-aeo/src/Extension/AiBoostAeo.php
→ No syntax errors detected ✅

php -l plugins/aiboost-opengraph/src/Extension/AiBoostOpengraph.php
→ No syntax errors detected ✅

php -l plugins/aiboost-codemanager/src/Extension/AiBoostCodemanager.php
→ No syntax errors detected ✅

php -l plugins/aiboost-schema/src/Extension/AiBoostSchema.php
→ No syntax errors detected ✅

php -l plugins/aiboost-hreflang/src/Extension/AiBoostHreflang.php
→ No syntax errors detected ✅ (after fix)
```

---

## Bug Found and Fixed

### Bug #1 — Hreflang: `getLanguages('published')` returns wrong result on Joomla 4/5

**File:** `plugins/aiboost-hreflang/src/Extension/AiBoostHreflang.php`  
**Method:** `detectJoomlaLanguages()`

**Root cause:**  
`LanguageHelper::getLanguages($key)` in Joomla 4 and 5 uses `$key` as an **array index key**, not as a filter. Calling `getLanguages('published')` returns an associative array indexed by the `published` field value (0 or 1), giving at most 2 entries total — the last language with each published status.

On a site with 3 published languages (e.g. en, de, fr), only 1 language object would be returned.

**Impact on multi-language sites (Joomla 4/5):**
- Hreflang tags would only include 1 language instead of all published languages
- `x-default` and canonical could point to wrong language
- `/sitemap-hreflang.xml` would be incomplete

**Why it was hidden on Joomla 6.1 staging:**  
The staging site runs only 1 published language (English), so `[1 => englishLang]` returned exactly 1 correct result and the bug was undetected.

**Fix applied:**

Before:
```php
$langs = \Joomla\CMS\Language\LanguageHelper::getLanguages('published');
```

After:
```php
$allLangs = \Joomla\CMS\Language\LanguageHelper::getLanguages();
foreach ($allLangs as $lang) {
    if ((int) ($lang->published ?? 0) !== 1) {
        continue;
    }
    // ...
}
```

**Status:** Fixed and rebuilt into `plg_system_aiboost_hreflang-1.0.0.zip` ✅

---

## Per-Plugin Joomla API Analysis

### AI Boost Schema

**Events:** `onBeforeCompileHead()`

| API | J4.4 | J5.x | Notes |
|-----|------|------|-------|
| `Factory::getApplication()` | ✅ | ✅ | |
| `Factory::getDbo()` | ✅ | ✅ | Deprecated 4.2+, non-breaking |
| `Factory::getUser($id)` | ✅ | ✅ | Not deprecated |
| `$app->getDocument()` | ✅ | ✅ | |
| `$document->addCustomTag()` | ✅ | ✅ | |
| `Uri::getInstance()` / `Uri::current()` | ✅ | ✅ | |

**Expected output (homepage):**
```json
{ "@context": "https://schema.org", "@type": "WebSite", "name": "Site Name", "url": "https://example.com/",
  "potentialAction": { "@type": "SearchAction", "target": { "@type": "EntryPoint",
    "urlTemplate": "https://example.com/index.php?option=com_search&searchword={search_term_string}" },
    "query-input": "required name=search_term_string" } }
```

**Expected output (article page):**
```json
{ "@context": "https://schema.org", "@type": "Article", "headline": "Article Title",
  "datePublished": "2026-01-15T10:00:00+00:00", "author": { "@type": "Person", "name": "Admin" } }
```

---

### AI Boost OpenGraph

**Events:** `onBeforeCompileHead()`

| API | J4.4 | J5.x | Notes |
|-----|------|------|-------|
| `Factory::getApplication()` | ✅ | ✅ | |
| `Factory::getDbo()` | ✅ | ✅ | Deprecated 4.2+, non-breaking |
| `Factory::getUser($id)` | ✅ | ✅ | |
| `Factory::getLanguage()` | ✅ | ✅ | |
| `$db->getTableList()` | ✅ | ✅ | Falang detection |
| `$document->addCustomTag()` | ✅ | ✅ | |

**Expected head output:**
```html
<meta property="og:type" content="article">
<meta property="og:url" content="https://example.com/blog/my-article">
<meta property="og:site_name" content="My Site">
<meta property="og:title" content="Article Title">
<meta property="og:description" content="Article intro text">
<meta property="og:image" content="https://example.com/images/articles/photo.jpg">
<meta property="article:published_time" content="2026-01-15T10:00:00+00:00">
<meta name="twitter:card" content="summary_large_image">
```

---

### AI Boost Code Manager

**Events:** `onBeforeCompileHead()`, `onAfterRender()`

| API | J4.4 | J5.x | Notes |
|-----|------|------|-------|
| `Factory::getApplication()` | ✅ | ✅ | |
| `$app->getBody()` / `$app->setBody()` | ✅ | ✅ | onAfterRender body injection |
| `$document->addCustomTag()` | ✅ | ✅ | |

`onAfterRender` body injection uses `preg_replace()` on `$app->getBody()` — standard pattern, works identically on Joomla 4 and 5.

**Expected output (GA4 enabled, G-XXXXXXXXXX):**
```html
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
```

---

### AI Boost AEO

**Events:** `onAfterInitialise()`, `onBeforeCompileHead()`, `onContentAfterSave()`, `onContentChangeState()`

| API | J4.4 | J5.x | Notes |
|-----|------|------|-------|
| `Factory::getApplication()->close()` | ✅ | ✅ | Virtual file serving |
| `Factory::getDbo()` | ✅ | ✅ | Deprecated 4.2+, non-breaking |
| `Uri::getInstance()` | ✅ | ✅ | |

`onAfterInitialise` virtual file serving (llms.txt, robots.txt, IndexNow key): reading `$_SERVER['REQUEST_URI']`, sending `header()` calls, and calling `$app->close()` is Joomla 4/5 safe and fires before Joomla routing resolves.

`onContentAfterSave(string $context, object $article, bool $isNew)` — Joomla 4/5 passes a 4th `$data` parameter which PHP silently ignores. No error.

**Expected output (GET /llms.txt):**
```
# My Site
> Site description

## More
- [Full content index](https://example.com/llms-full.txt)
```

**Expected output (GET /robots.txt):**
```
User-agent: *
Disallow: /administrator/
Allow: /

User-agent: GPTBot
Allow: /

Sitemap: https://example.com/sitemap.xml
```

---

### AI Boost Hreflang

**Events:** `onAfterInitialise()`, `onBeforeCompileHead()`

| API | J4.4 | J5.x | Notes |
|-----|------|------|-------|
| `LanguageHelper::getLanguages()` | ✅ | ✅ | Fixed (was `'published'`) |
| `Factory::getLanguage()->getTag()` | ✅ | ✅ | |
| `Factory::getDbo()` | ✅ | ✅ | Deprecated 4.2+, non-breaking |
| `$app->close()` | ✅ | ✅ | Sitemap serving |
| `$db->getTableList()` | ✅ | ✅ | Falang detection |

**Expected head output (multi-language site: en + de + fr):**
```html
<link rel="alternate" hreflang="en-gb" href="https://example.com/en/blog/my-article">
<link rel="canonical" href="https://example.com/en/blog/my-article">
<link rel="alternate" hreflang="de-de" href="https://example.com/de/blog/mein-artikel">
<link rel="alternate" hreflang="fr-fr" href="https://example.com/fr/blog/mon-article">
<link rel="alternate" hreflang="x-default" href="https://example.com/en/blog/my-article">
```

---

## Common Non-Breaking Deprecation (All Plugins)

All 5 plugins use `Factory::getDbo()`, deprecated since Joomla 4.2 in favour of dependency injection. This triggers `E_USER_DEPRECATED` **only when Joomla debug mode is on**. In production (debug off) there is no user-visible warning. The method works correctly on Joomla 4.x and 5.x.

Refactoring to constructor-injected DB is planned as future tech-debt (task #245).

---

## Joomla Event System

All 5 plugins use the legacy method-naming pattern (`onBeforeCompileHead()`, etc.). Joomla 4 and 5 maintain a compatibility shim for this pattern. No issues on either version.

---

## PHP Compatibility

All features used require PHP 8.0 at minimum; declared minimum is PHP 8.1. ✅

| Feature | Min PHP | Used by |
|---------|---------|---------|
| `str_ends_with()` / `str_starts_with()` / `str_contains()` | 8.0 | AEO, Hreflang, CodeManager |
| `catch (\Throwable)` without variable | 8.0 | AEO, Hreflang |
| Arrow functions `fn()` | 7.4 | Hreflang |
| `?string` nullable types | 7.1 | CodeManager |

---

## XML Manifest Check

All 5 manifests declare:
```xml
<joomla_minimum>4.0.0</joomla_minimum>
<php_minimum>8.1.0</php_minimum>
<namespace path="src">AiBoost\Plugin\System\AiBoost[Name]</namespace>
```

The `<namespace>` declaration triggers Joomla 4+'s PSR-4 autoloader. Each entry point also includes a legacy `require_once` + `class_alias` for maximum compatibility. This dual-bootstrap approach works on Joomla 4 and 5 without conflict.

---

## Live Test Checklist (for when Joomla 4.4 / 5.x env is available)

### Install (all 5 plugins)
- [ ] Upload ZIP via Joomla installer → green success message, no PHP errors
- [ ] Plugin appears in Extensions → Plugins
- [ ] Plugin edit form opens (all params load without 500 error)

### Schema
- [ ] Homepage → view source → `@type: WebSite` JSON-LD in `<head>`
- [ ] Article page → `@type: Article` JSON-LD with `datePublished`
- [ ] Set `org_name` → Organization JSON-LD on all pages

### OpenGraph
- [ ] Any page → `og:title`, `og:url`, `og:type` meta tags in `<head>`
- [ ] Article page → `og:type = article` + `article:published_time`
- [ ] Twitter card tags present: `twitter:card`, `twitter:title`

### Code Manager
- [ ] Enter GA4 measurement ID → `gtag.js` script in `<head>`
- [ ] Enter GSC verification code → `google-site-verification` meta tag
- [ ] GTM ID → GTM script in `<head>` + noscript after `<body>`

### AEO
- [ ] Visit `/llms.txt` → plain text response with site name and links
- [ ] Visit `/llms-full.txt` → article list
- [ ] Visit `/robots.txt` → robots file with AI crawlers section
- [ ] Markdown Pages enabled → article URL + `.md` → Markdown response

### Hreflang (multi-language site, 2+ languages installed)
- [ ] Frontend page → one `hreflang` tag per published language
- [ ] `hreflang="x-default"` is present
- [ ] `<link rel="canonical">` matches current language URL
- [ ] Visit `/sitemap-hreflang.xml` → valid XML with `xhtml:link` alternates for every language

---

## Conclusion

After full static analysis and the Hreflang bug fix:

- **4/5 plugins had zero issues** — Schema, OpenGraph, CodeManager, AEO ready for Joomla 4.4 and 5.x
- **1 plugin (Hreflang) had one real bug** on multi-language Joomla 4/5 sites — fixed and rebuilt
- **All 5 plugins are safe to distribute for Joomla 4.4+ and 5.x** pending live smoke-test
- No breaking PHP warnings beyond cosmetic `Factory::getDbo()` deprecation notice (non-breaking)
- PHP syntax: 5/5 clean ✅

*Task #236 — 2026-05-15*
