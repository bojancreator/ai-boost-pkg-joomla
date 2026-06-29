# Joomla: SITE default content language ≠ global Default Language

Joomla has **two different "default language" settings** — do not conflate them. For
anything **front-end / SEO / hreflang / x-default**, ALWAYS use the SITE default
content language, NEVER the global/admin default.

| Setting | Where set | Stored in | Read in code | Meaning |
|---|---|---|---|---|
| **Global Default Language** | System → Global Configuration → Site → Default Language | `configuration.php` `$language` | `Factory::getApplication()->get('language')` (⚠ also the per-request active language, and Falang/languagefilter overwrite it per page), `new \JConfig()->language` | Global/admin fallback default. NOT the multilingual front-end default. |
| **SITE default content language** | Languages → **Installed Languages → filter Site → "Default" star** | **`com_languages` component params, key `site`** (`#__extensions` params for `element='com_languages'`) | **`ComponentHelper::getParams('com_languages')->get('site', 'en-GB')`** → a lang_code e.g. `sr-YU` | The site's primary/front-end content language — the one served at the bare root and used for SEO. THIS is the x-default. |

These genuinely differ: a site can have global default = `en-GB` while the SITE
default content language = `sr-YU` (e.g. an English-admin install serving a Serbian
front end). On a **Falang** site the bare root is redirected to the SITE-default
language's prefix (e.g. `/` → `/sr/`); `Factory::getApplication()->get('language')`
on a prefixed page returns the *active* (current) language, so it is useless for the
default.

**Authoritative source** (Joomla 5/6 `administrator/components/com_languages/src/Model/InstalledModel.php`):
- read: `$params->get(ApplicationHelper::getClientInfo($clientId)->name, 'en-GB')` where `$params = ComponentHelper::getParams('com_languages')` and the client name is `'site'`/`'administrator'`.
- write (setDefault): `$params->set($client->name, $cid)` then saves the `com_languages` extension params — it does **not** touch `configuration.php`.

**Rule for this codebase:** hreflang/x-default and any "what language does the front
end default to" logic must read the SITE default content language (`com_languages`
`site`), map the lang_code → SEF via the published `#__languages`. Since **T1·S6**
(order 0027) this comes from the ONE resolver field **`PageContext::siteDefaultLanguage`**
(`PageResolver::resolveSiteDefaultLanguage()`), consumed by
`AiBoostIntFalang::resolveSiteDefaultLanguage()` → `primaryLanguageSef()` →
`BridgeDetector::resolvePrimaryLanguageSef()`. The resolver's PRIMARY source is still
`com_languages` `site`. Discovered order 0006 (B7) after wrongly reading the global
config default first.

**Fallback-direction rule (Bojan, order 0027) — never assume English, never cross the
front/back boundary:** Joomla always has *some* default, but it need NOT be English (a
German install may have no English pack); the back-end (admin) and front-end default
languages can differ, and the front-end may not even contain the back-end language (a
Japanese admin running an en/fr/it site). Therefore:
- A **FRONT-END** signal (x-default, hreflang, og:locale, any "site defaults to" value)
  falls back to a **FRONT-END** default — NEVER the admin/back-end default, NEVER the
  per-request active language, NEVER a hardcoded `'en'`.
- A **BACK-END** value falls back to the **BACK-END** default.
- The admin language must never leak into a front-end signal.

Applied: when `com_languages` `site` is empty, `resolveSiteDefaultLanguage()` falls back
to the **stable front-end config default** (`$app->get('language')` read on the SITE
application — stable, NOT the per-request active language; confirmed by B7: staging
front-end global default = `en-GB` while the active lang on `/sr/` is `sr-YU`).
`BridgeDetector::resolvePrimaryLanguageSef()` then maps that code to a **published**
front-end language's SEF, so a value that is not a published front-end language can never
leak into x-default; the legacy `falang_primary_language`/`'en'` survives ONLY as the
very-last-resort when nothing matches. On every functional multilingual site `site` is
set, so x-default is unchanged (golden-diff identical) — the fallback is a broken-config
safety net, not the normal path.
