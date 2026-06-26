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
end default to" logic must read `ComponentHelper::getParams('com_languages')->get('site')`
(map the lang_code → SEF via the published `#__languages`). Used by
`AiBoostIntFalang::primaryLanguageSef()` → `BridgeDetector::resolvePrimaryLanguageSef()`.
Discovered order 0006 (B7) after wrongly reading the global config default first.
