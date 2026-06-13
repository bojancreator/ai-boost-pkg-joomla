# Native-Multilanguage Test-Site Runbook (Plan 3)

One-time (~45 min) setup of a **native Joomla multilanguage** test site so the
verification harness (`scripts/verify-frontend-emission.py --target ml`) can
prove per-language emission **without Falang** — pure Joomla Associations.

Why a dedicated site: the Pro staging (`staging.offroadserbia.com`) runs Falang.
Since the Multilang Pro re-tier, head hreflang + sitemap alternates now also
serve **native** multilingual sites (gate = master toggle + `hasPro('int_falang')`,
no Falang host required). That native path has no live coverage anywhere else.

Use one of the existing **testmyweb.info J6** matrix sites (J6 × Pro preferred,
so the Multilang Pro path can be exercised). Languages chosen: **en-GB** (default)
+ **sr-RS** (easy for Bojan to eyeball).

---

## 0. Before you start
- Admin access to the chosen testmyweb J6 site.
- The AI Boost **Pro** package installed (so `int_falang` can be activated).
- ~45 min. Every step is in Joomla core admin; nothing here touches code.

## 1. Install the second language pack
**System → Install → Languages** → install **Serbian (sr-RS)** (and confirm
English en-GB is present). After install, **Extensions → Manage → Languages**
shows both as installed.

## 2. Publish Content Languages
**System → Manage → Content Languages** → ensure a published row for **each**:
| Title | Lang tag | URL code (sef) | Default |
|---|---|---|---|
| English (UK) | en-GB | `en` | yes |
| Srpski | sr-RS | `sr` | no |

The **URL code** values (`en`, `sr`) are what the harness fetches as `/en/`, `/sr/`.

## 3. Enable + configure the Language Filter plugin
**System → Plugins → "System - Language Filter"** → enable, then set:
- **Remove URL Language Code = No** → URLs become `/en/…` and `/sr/…`
  (the harness depends on this; with "Yes" the default language drops its prefix
  and `/en/` 404s).
- **Item Associations = Yes**.
- **Automatic Language Change = Yes** (optional, nicer switcher behaviour).

> SEF URLs must be ON: **Global Configuration → Site → Search Engine Friendly
> URLs = Yes** (URL rewriting on if the host supports it).

## 4. One Home menu item **per language**
Create one menu module/menu per language, each with a **Home (default)** menu
item set to that language:
- Menu `Main EN` → item `Home` → **Language = English (en-GB)**, set as Default.
- Menu `Main SR` → item `Početna` → **Language = Srpski (sr-RS)**, set as Default
  for that language.

Each language **must** have exactly one Default menu item or Joomla won't route
`/sr/`.

## 5. Two associated articles
Create (or reuse) one article per language on the same topic, then link them:
1. Article **EN**: Language = English (en-GB), published, on the EN menu.
2. Article **SR**: Language = Srpski (sr-RS), published, on the SR menu.
3. Open the EN article → **Associations** tab → pick the SR article (and vice
   versa). This writes the `#__associations` rows the native hreflang builder
   reads (`HreflangSitemapExtension` JOINs `#__associations` × `#__content`).

A second associated pair (e.g. a category or a menu item) makes the sitemap
alternates check less likely to flap — optional but recommended.

## 6. Language switcher module (optional, for visual sign-off)
**Content → Site Modules → New → "Language Switcher"** → position in the
template, Language = All. Lets Bojan click EN/SR while eyeballing.

## 7. Install the full AI Boost package + activate Multilang
- Install `pkg_aiboost_pro-<current>` (≥ 0.76.0).
- **Integrations** page → ensure **Multilang** master toggle is ON.
- Activate Multilang Pro for QA via **one** of:
  - **JDEBUG simulator (preferred, HTTP-flippable):** Global Config → System →
    **Debug System = Yes**, then the harness flips `int_falang` itself; or
  - **DB:** in `#__aiboost_settings` (`setting_key='main'`) JSON blob set
    `license_state.int_falang.status = "active"` (or `dev_license_preview="1"`).
- Turn ON the hreflang toggles: `falang_hreflang_head`, `enable_hreflang`
  (Settings → AEO / Sitemap), plus `falang_schema_translate` / `falang_og_translate`
  if testing translated Schema/OG.

## 8. Smoke-check by hand
- Visit `/en/` and `/sr/` → both render.
- View source of `/en/` → `<link rel="alternate" hreflang="sr-RS" …>` and
  `hreflang="x-default"` present (only when Multilang Pro active).
- `/sitemap.xml` → `<xhtml:link rel="alternate" hreflang="…">` rows.

---

## 9. Credentials → `CREDENTIALS.local.md` (never commit)
Add a block (PowerShell `$env:` lines parsed by `_creds_run.py`):

```
$env:ML_URL="https://<site>.testmyweb.info/administrator"
$env:ML_ADMIN_USER="<admin user>"
$env:ML_ADMIN_PASS="<admin pass>"
$env:ML_NO_SSL_VERIFY="1"   # ONLY if the testmyweb cert is self-signed/invalid
```

⚠️ SSL note: `_creds_run.py` deliberately **pops** `AIBOOST_NO_SSL_VERIFY` (the
Pro/Free staging hosts have real certs). The ML site controls TLS verification
through its **own** `ML_NO_SSL_VERIFY` flag, which `_creds_run.py` does **not**
pop. Leave it unset if the cert is valid.

## 10. Run the harness
```
python _creds_run.py scripts/_qa_common.py --target ml                 # connectivity self-check
python _creds_run.py scripts/verify-frontend-emission.py --target ml --group multilingual
python _creds_run.py scripts/verify-frontend-emission.py --target ml   # full sweep
```

The multilingual group's authoritative gate test (Multilang sim OFF → no
hreflang; sim ON → hreflang appears) runs only when **Debug System = Yes**
(JDEBUG); otherwise it SKIPs with the DB instructions above.

---

### Troubleshooting
- `/en/` or `/sr/` 404 → Language Filter "Remove URL Language Code" is still Yes,
  or that language has no Default menu item, or SEF URLs are off.
- No hreflang in `<head>` → Multilang Pro not active (check JDEBUG/DB), master
  toggle off, or fewer than 2 published languages.
- Sitemap has no `<xhtml:link>` → same Pro gate, or no `#__associations` rows
  (step 5 not done).
- Harness can't log in → `ML_URL` must be the **/administrator** URL; check creds.
