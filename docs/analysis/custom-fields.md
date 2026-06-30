# Custom Fields Analysis — Articles & Users

> **Scope:** how AI Boost for Joomla reads Joomla *custom fields* (`com_fields`) for **articles**
> (`com_content.article`) and **users** (`com_users.user`), whether it is correct, where the
> field→output mappings live, and what is missing. Code-verified against the source on branch
> `refactor/structural` (order 0014, 2026-06-28). This document is the **permanent, stand-alone
> record** — it carries every finding in full so the topic can be understood without re-reading the
> code.

---

## Plain-Serbian summary (for Bojan)

Naš dodatak ume da koristi Joomline „custom fields" (dodatna polja) na **člancima** i na **korisnicima
(autorima)** da bi pojedinačnom članku dao poseban naslov/sliku/opis za deljenje na društvenim mrežama,
poseban tip podatka za Google, datume događaja — i da bi autoru dodao zvanje, biografiju i linkove
(LinkedIn, Wikipedia) za jače „ko stoji iza teksta" signale.

Sve to **radi ispravno** i bezbedno (proverili smo upite i da ništa ne ruši stranicu). Ali postoji
nekoliko stvari koje treba znati:

1. **Imena tih polja su fiksno upisana u kod.** Korisnik ne može da ih preimenuje niti da izabere
   svoja postojeća polja — moraju da se zovu tačno onako kako mi očekujemo (npr. `aiboost_og_image`).
2. **Polja za društvene mreže (6 komada) dodatak sam pravi i briše** prilikom instalacije (Pro ih
   napravi, Free ih ukloni), i postoji dugme „Create / Repair OG Fields". **Polja za autora (5 komada)
   takođe imaju dugme** „Create author custom fields" — *(ovde je prvobitna procena bila pogrešna: za
   autore POSTOJI dugme za automatsko pravljenje).*
3. **Polja za događaje (3 komada: datum početka, datum kraja, lokacija) i polje za tip članka
   (`aiboost_schema_type`) NEMAJU nijedan automatski način pravljenja** — korisnik mora ručno da ih
   napravi sa tačnim imenom, inače ne rade. To je prava praznina.
4. **Nema jednog ekrana koji pokazuje sva ta polja i šta tačno rade.** Spisak je raštrkan po kodu i
   dokumentaciji.
5. **Prevodi rade na tri različita načina** za tri grupe polja (jedna grupa preko Falang-a, druga preko
   varijanti imena polja `_en`/`_de`, treća se uopšte ne prevodi). To nije greška, ali je nedosledno.

**Odluke koje tražim od tebe su na dnu** (`ODLUKA:` stavke) — ukratko: da li da napravimo jedan
zajednički „spisak/ekran" svih polja, da li da dodatak sam pravi i polja za događaje/tip članka kao što
već pravi OG polja, da li da upozorimo kad polje fali, i da li da imena polja postanu podesiva.

---

## 1. Executive summary (technical)

AI Boost reads **15 distinct custom-field names** in total — **10 on articles** and **5 on users**
(the 5 user fields additionally support per-language `_<lang>` variants). All reads are **Pro-gated**,
use **parameterised queries**, and **fail silently** (never break the page). The field names are
**hard-coded constants** in the consuming services — the admin cannot rename them or point the feature
at pre-existing fields.

Three independent code paths read these fields, each with a **different translation strategy**:

| Group | Fields | Reader | Translation strategy |
|---|---|---|---|
| Article OG/Twitter | 6 | `aiboost_social` → `CustomFieldReader` | **Falang** value overlay (`#__falang_content`) |
| Article schema @type | 1 | `aiboost_schema` → `SchemaProBuilder::loadCustomField()` | **none** |
| Article event | 3 | `aiboost_schema` → `SchemaProBuilder::loadEventCustomFields()` | **none** |
| User author (E-E-A-T) | 5 | `aiboost_schema` → `SchemaProBuilder::loadAuthorCustomFields()` | **field-name suffix** `_<lang>` → `_en` → base |

Lifecycle (auto-create / auto-remove) is **inconsistent**:

| Group | Installer auto-create | Manual button | On uninstall / Free | Health check |
|---|---|---|---|---|
| Article OG (6) | **Yes** (`ensureOgCustomFields`) | **Yes** ("Create / Repair OG Fields") | **Removed** (`removeOgCustomFieldsForFree`) | Yes (`info_article_custom_fields_pro`) |
| User author (5) | No | **Yes** ("Create author custom fields") | Left in place | Yes (`warning_schema_author_fields_missing` + coverage) |
| Article event (3) | No | **No** | Left in place | **No** |
| Article `aiboost_schema_type` (1) | No | **No** | Left in place | **No** |

---

## 2. Articles — full read path

### 2.1 Article OG/Twitter custom fields (6 fields) — `CustomFieldReader`

**Reader:** `component/plugins/system/aiboost_social/src/Service/CustomFieldReader.php`
**Consumer:** `component/plugins/system/aiboost_social/src/Service/OgTagProDecorator.php`

> **NOTE — stale docblock.** `CustomFieldReader.php:2-10` and `OgTagProDecorator.php:5-10` still claim
> these classes "live in the closed-source Pro plugin `aiboost_social_pro`". That is **out of date.**
> During the "Pro replaces Free" collapse the entire OG/Twitter enrichment was **relocated into the
> free `aiboost_social` plugin** (`AiBoostSocial.php:124-138`). `aiboost_social_pro` is now a **dormant
> no-op** retained only to keep a valid extension row on legacy split installs
> (`aiboost_social_pro/src/Extension/AiBoostSocialPro.php:1-16, 33-41`). The classes are kept Pro-only
> because they ship **only in the Pro build** (build `FREE_EXCLUDE`), so on a Free ZIP `class_exists()`
> is false and the base props pass through untouched.

**Field constants** (`CustomFieldReader.php:39-46`), context `com_content.article`, `f.state = 1`:

| # | Field name | Joomla type (as auto-created) | Output | Whitelist | Multiline |
|---|---|---|---|---|---|
| 1 | `aiboost_og_title` | text | `og:title` + `twitter:title` | — | yes |
| 2 | `aiboost_og_description` | textarea | `og:description` + `twitter:description` | — | yes |
| 3 | `aiboost_og_image` | media | `og:image` + `twitter:image` (+ recomputed `og:image:width`/`height`) | — | yes |
| 4 | `aiboost_og_type` | list | `og:type` | `article, website, video.movie, music.song, product` | no |
| 5 | `aiboost_og_video` | url | `og:video` (absolutised URL) | — | yes |
| 6 | `aiboost_twitter_card` | list | `twitter:card` | `summary, summary_large_image` | no |

**Query** (`CustomFieldReader.php:75-95`): `#__fields f LEFT JOIN #__fields_values fv ON fv.field_id =
f.id AND fv.item_id = :articleId`, filtered `f.context = 'com_content.article'`, `f.name IN (…)`,
`f.state = 1`. A `LEFT JOIN` is used so the field-id map is built even when a value row is absent
(needed for the Falang overlay below). Empty values are ignored (`mapRowToResult` early-returns on `''`,
`CustomFieldReader.php:115-119`).

**Falang overlay** (`CustomFieldReader.php:103-104, 131-204`): only runs if `#__falang_content` exists
(detected once per request, cached static, `:131-145`). It resolves the active language to a Falang
`lang_id` (`#__languages.lang_code = :langTag AND published = 1`, `:158-164`), then INNER-joins
`#__fields_values fv` to `#__falang_content fc ON fc.reference_id = fv.id AND fc.reference_table =
'fields_values' AND fc.reference_field = 'value' AND fc.language_id = :falangId AND fc.published = 1`
(`:171-188`). Translated non-empty values overwrite the base values via the same `mapRowToResult`
(`:193-200`). Falls back to base values when no translation exists.

**Application in the decorator** (`OgTagProDecorator.php:123-203`), only when `option=com_content`,
`view=article`, `id>0` **and** `enable_per_article_fields` is truthy (`:131`, default `?? 1`):
- `og_title` → `og:title` + `twitter:title` (`:135-138`)
- `og_description` → `og:description` + `twitter:description` (`:139-142`)
- `og_image` → article image source (overrides intro-image fallback) (`:143-145, 164-182`)
- `og_type` → `og:type` **if** in whitelist; sets `$ogTypeFromField` so the later `og:type=article`
  default does not clobber it (`:147-151, 192-195`)
- `og_video` → `og:video`, absolutised (`:153-155, 184-186`)
- `twitter_card` → `twitter:card` **if** in whitelist (`:157-160, 187-189`)

**Intro-image fallback** (`OgTagProDecorator.php:163-169, 254-270`): when no `aiboost_og_image` is set,
the decorator reads the article's own `images` JSON (`image_intro`, else `image_fulltext`) for the OG
image. This is Pro decorator behaviour, **not** a custom field, but it is the fallback the custom field
overrides.

### 2.2 Article schema-type custom field (1 field) — `aiboost_schema_type`

> **NOT in the order's first-round list — discovered during this analysis.**

**Reader/consumer:** `SchemaProBuilder::resolveArticleType()` (`SchemaProBuilder.php:668-676`) via the
generic `loadCustomField()` (`:678-702`).

| Field name | Output | Whitelist | Translation | Tier |
|---|---|---|---|---|
| `aiboost_schema_type` | overrides the article schema `@type` | `Article, BlogPosting, NewsArticle, TechArticle` | none | Pro |

Resolution: `loadCustomField($articleId, 'aiboost_schema_type')` (INNER JOIN `#__fields`/
`#__fields_values`, context `com_content.article`, `f.state = 1`, `LIMIT 1`). If the value is one of the
4 allowed types it becomes the article `@type`; otherwise it defaults to `'Article'`
(`:670-675`). Documented only in the en-GB `.ini` description for `article_enabled`
(`plg_system_aiboost_schema.ini:155`): *"The schema type can be overridden per-article using the Joomla
custom field aiboost_schema_type."* No auto-create, no Health check, no Vue mention.

> **Caveat (known issue B5, separate order 0007):** on YOOtheme sites a duplicate Article object can
> appear because the FREE `SchemaBuilder` co-emits a base `Article` while the Pro builder honours the
> override. Out of scope here, but relevant to anyone reading the @type output.

### 2.3 Article event custom fields (3 fields) — `loadEventCustomFields`

**Reader/consumer:** `SchemaProBuilder::buildEvent()` (`SchemaProBuilder.php:488-570`) via
`loadEventCustomFields()` (`:704-739`). Only runs when `events_enabled` (Pro) and the article's
category equals `events_category_id` (`:490-499`).

| Field name | Joomla type expected | Output | Fallback when empty | Translation |
|---|---|---|---|---|
| `aiboost_event_start_date` | date/text | `Event.startDate` (ATOM) | article `publish_up` (`:534-539`) | none |
| `aiboost_event_end_date` | date/text | `Event.endDate` (ATOM) | article `publish_down` (`:541-546`) | none |
| `aiboost_event_location` | text | `Event.location` → `{@type: Place, name}` | omitted (`:548-551`) | none (location name not translated; event *description* is translated via the settings-level `event_<idx>_desc` translation key, not a custom field, `:558-564`) |

**Query** (`:715-731`): `#__fields f INNER JOIN #__fields_values fv` (context `com_content.article`,
names IN the 3, `f.state = 1`). INNER JOIN means only populated fields return — acceptable because every
field has an explicit fallback. Values are `trim()`-ed.

### 2.4 Article author/section meta (no custom field)

`OgTagProDecorator::buildArticleMeta()` (`:303-332`) also emits `article:published_time`,
`article:modified_time`, `article:author` (from `#__users.name` by `created_by`, `:321-324, 334-350`),
and `article:section` (from `#__categories.title` by `catid`, `:326-329, 352-368`) — all from **standard
article columns, not custom fields**. Noted here only to show the author is always taken from
`created_by` (see gap G5).

---

## 3. Users — full read path (author E-E-A-T, 5 fields)

**Reader/consumer:** `SchemaProBuilder::loadAuthorCustomFields()` (`SchemaProBuilder.php:756-814`),
called from `buildArticle()` (`:436-459`) only when `schema_author_entity_enabled === '1'` (Pro) and the
article's `created_by` user has a name.

| # | Field base name | Output (nested `Person` on Article schema) |
|---|---|---|
| 1 | `aiboost_job_title` | `Person.jobTitle` (`:805, 444-446`) |
| 2 | `aiboost_bio` | `Person.description` (`:806, 447-449`) |
| 3 | `aiboost_website` | `Person.url` **and** added to `Person.sameAs` (`:807, 450-456`) |
| 4 | `aiboost_linkedin` | `Person.sameAs` (`:808, 453-456`) |
| 5 | `aiboost_wikipedia` | `Person.sameAs` (`:809, 453-456`) |

`sameAs` = `array_values(array_filter([linkedin, wikipedia, website]))` (`:453`).

**Read mechanism — different from articles.** Instead of a hand-written SQL join, this path uses
Joomla's core `FieldsHelper::getFields('com_users.user', $user, true)` (`:763, 775-777`), guarded by
`class_exists('Joomla\\Component\\Fields\\Administrator\\Helper\\FieldsHelper')` — if `com_fields` is
unavailable it returns all-empty (`:763-765`). The user is loaded via
`UserFactoryInterface::loadUserById()` (`:768-773`). Field values are flattened to a name→value map
(`rawvalue` preferred, arrays imploded with `, `) (`:782-792`).

**Per-language via field-name suffix** (`:794-802`): the active language is reduced to a **2-letter code**
(`substr(getActiveLanguage(), 0, 2)`, `:794`). For each base field the picker tries, in order:
`{$base}_{$lang}` → `{$base}_en` → `{$base}` (`:795-801`). So a multilingual site adds
`aiboost_job_title_de`, `aiboost_bio_fr`, etc., as **separate Joomla user fields** — this is **not**
Falang and **not** the `#__falang_content` overlay used for OG.

---

## 4. Full field catalogue (single table)

> The single source of truth this analysis recommends building (see §7). Every field name the product
> reads, its source context, output, translation mechanism, and tier.

| Field name (+`_<lang>` variants where noted) | Item context | Read by | Output | Translatable | Tier |
|---|---|---|---|---|---|
| `aiboost_og_title` | `com_content.article` | `CustomFieldReader` | `og:title`, `twitter:title` | Falang | Pro |
| `aiboost_og_description` | `com_content.article` | `CustomFieldReader` | `og:description`, `twitter:description` | Falang | Pro |
| `aiboost_og_image` | `com_content.article` | `CustomFieldReader` | `og:image`, `twitter:image`, dims | Falang | Pro |
| `aiboost_og_type` | `com_content.article` | `CustomFieldReader` | `og:type` (whitelisted) | Falang | Pro |
| `aiboost_og_video` | `com_content.article` | `CustomFieldReader` | `og:video` | Falang | Pro |
| `aiboost_twitter_card` | `com_content.article` | `CustomFieldReader` | `twitter:card` (whitelisted) | Falang | Pro |
| `aiboost_schema_type` | `com_content.article` | `SchemaProBuilder::loadCustomField` | Article `@type` (whitelisted) | no | Pro |
| `aiboost_event_start_date` | `com_content.article` | `SchemaProBuilder::loadEventCustomFields` | `Event.startDate` | no | Pro |
| `aiboost_event_end_date` | `com_content.article` | `SchemaProBuilder::loadEventCustomFields` | `Event.endDate` | no | Pro |
| `aiboost_event_location` | `com_content.article` | `SchemaProBuilder::loadEventCustomFields` | `Event.location` (Place) | no | Pro |
| `aiboost_job_title` (+`_<lang>`) | `com_users.user` | `SchemaProBuilder::loadAuthorCustomFields` | `Person.jobTitle` | field-name suffix | Pro |
| `aiboost_bio` (+`_<lang>`) | `com_users.user` | `SchemaProBuilder::loadAuthorCustomFields` | `Person.description` | field-name suffix | Pro |
| `aiboost_website` (+`_<lang>`) | `com_users.user` | `SchemaProBuilder::loadAuthorCustomFields` | `Person.url` + `sameAs` | field-name suffix | Pro |
| `aiboost_linkedin` (+`_<lang>`) | `com_users.user` | `SchemaProBuilder::loadAuthorCustomFields` | `Person.sameAs` | field-name suffix | Pro |
| `aiboost_wikipedia` (+`_<lang>`) | `com_users.user` | `SchemaProBuilder::loadAuthorCustomFields` | `Person.sameAs` | field-name suffix | Pro |

**Gating toggles** (the settings that switch each group on):

| Setting key | Manifest tier | Section | Runtime gate | Default |
|---|---|---|---|---|
| `enable_per_article_fields` | `og.php` **free** ⚠️ | social/og | Pro decorator (`isProActive`) | `1` |
| `enable_article_og_type` | `og.php` free | social/og | Pro decorator | `1` |
| `schema_author_entity_enabled` | `schema.php` **pro** | schema/author | `=== '1'` in builder | `0` |
| `events_enabled` | `schema.php` **pro** | schema/event | `!= 0` in builder | `0` |
| `events_category_id` | `schema.php` **pro** | schema/event | `> 0` in builder | `0` |

---

## 5. Lifecycle — how the fields get created and destroyed

### 5.1 Article OG fields (6) — fully managed by the installer + a manual button

**Installer auto-create** — `pkg_script.php::ensureOgCustomFields()` (`pkg_script.php:378-512`):
- Runs on **install and update** for Pro installs (the marker `IS_PRO_EDITION` / `pro_installed`
  drives this branch, `:190-237, 1414-1418`).
- Idempotent: INSERT if absent, UPDATE label/description/fieldparams/note if the version marker in the
  `note` column differs, SKIP if already current (`:382-387`).
- `#__fields_values` (user-entered article overrides) are **never** touched on create/update (`:385`).
- Detects optional columns (`fieldparams`, `only_use_in_subform`) via `SHOW COLUMNS` for Joomla 5/6
  portability (`:386-387`).
- Creates the group **"AI Boost — OpenGraph"** (`:678` and the inline group helper).
- Emits an admin summary message (created/updated/unchanged/failed, `:499-503`).

**Free / uninstall removal** — `pkg_script.php::removeOgCustomFieldsForFree()` (#454, `:741-826`,
called on Free installs `:233-237` and on **uninstall** `:2806-2813`):
- Ownership-checked: only deletes the 6 named fields whose group is **"AI Boost — OpenGraph"** with
  `description LIKE 'AI Boost%'` (`:764-795`).
- **Deletes** the `#__fields_values` rows for those field IDs too (NOT preserved) — because Joomla
  re-issues new field IDs on re-create, preserved values would orphan/rebind wrongly (`:752-812`).

**Manual button** — `SettingsController::repairOgFields()` (`SettingsController.php:651-756`), surfaced
as **"Create / Repair OG Fields"** on the **Social** tab (`SocialTab.vue:115-122, 208-238`):
- Creates the group **"AI Boost — OpenGraph"** and all 6 fields.
- **Force re-creates**: existing fields are UPDATEd (label/title/type/ordering/description/state/note +
  fieldparams) using a `manual-YYYYMMDD` note marker that never matches a real version (`:723-741`).
- Field values are not touched.

### 5.2 User author fields (5) — manual button only (no installer)

**Manual button** — `SettingsController::createAuthorFields()` (`SettingsController.php:764-854`),
surfaced as **"Create author custom fields"** on the **Schema** tab (`SchemaTab.vue:157-164, 1789-…`):
- Creates the group **"AI Boost — Author"** (`:799-804`).
- Creates the 5 fields (`aiboost_job_title` text, `aiboost_bio` textarea, `aiboost_website`/
  `aiboost_linkedin`/`aiboost_wikipedia` url) (`:807-813`).
- **Idempotent and non-destructive**: existing fields are **skipped, never overwritten** (the user may
  have customised them) (`:826-830`).
- Handles strict-MySQL NOT-NULL audit columns (`created_time`/`created_user_id`/… `:790-796, 836-838`).
- The field descriptions tell multilingual users to add `aiboost_job_title_en`, `_de`, etc.
  (`SettingsController.php:808`).

> **This corrects first-round gap #2** ("USERS have NO auto-create"): an auto-create button **does**
> exist for the author fields.

### 5.3 Event fields (3) and `aiboost_schema_type` (1) — NO auto-create at all

There is **no installer step and no admin button** for `aiboost_event_start_date`,
`aiboost_event_end_date`, `aiboost_event_location`, or `aiboost_schema_type`. Verified by absence:
a project-wide search for `createEventFields` / `repairEventFields` and for any `#__fields` INSERT of
these names returns nothing. The admin must hand-create them under **Content → Fields → Articles** with
the **exact** names (and, for the two whitelisted ones, the exact option values), or the feature
silently does nothing.

---

## 6. Health registry coverage

| Health entry | Category | What it checks | Fix-It target |
|---|---|---|---|
| `info_article_custom_fields_pro` (#454) | License | The 6 OG fields' presence in `#__fields` matches the license tier; on Pro warns "Only N of 6 installed. Reinstall…"; on Free expects 0 (`HealthCheckService.php:2672-2745`) | Reinstall package (`com_installer`) |
| `warning_schema_author_fields_missing` | Schema | When `schema_author_entity_enabled` is on but the 5 author fields don't exist (counts a field present if any `_<lang>` variant exists) (`:1084-1155`) | Schema → Author Entity → "Create author custom fields" |
| `info_schema_author_fields_coverage` | Schema | Counts authors-with-published-articles whose profile has any `aiboost_job_title%` value populated (`:998-1082`) | Schema → Author Entity |

**Gap:** there is **no Health entry** for the 3 event fields or for `aiboost_schema_type`. If
`events_enabled` is on but the 3 event fields don't exist, the user gets no signal — the Event schema
simply falls back to publish dates and omits the location.

---

## 7. Where the mappings are catalogued today (scattered)

There is **no single catalogue and no admin screen** listing field → output mappings. The information is
spread across **seven** places that must be kept in sync by hand:

1. **Code constants** — `CustomFieldReader::FIELD_NAMES` (6), `AiBoostSocial::$ogFields` guard (6),
   `SchemaProBuilder::loadEventCustomFields` keys (3), `loadAuthorCustomFields` `pick()` calls (5),
   `resolveArticleType` allow-list, `OgTagProDecorator` whitelists.
2. **Auto-create definitions** — `pkg_script.php::ensureOgCustomFields` field defs (6),
   `SettingsController::repairOgFields` defs (6), `SettingsController::createAuthorFields` defs (5).
   *(These duplicate the OG defs in two places — installer + controller — a real DRY risk.)*
3. **Manifest descriptions** — `Manifest/og.php`, `Manifest/schema.php` (toggle tiers/labels only; the
   field names live only in `.ini` prose).
4. **Health registry** — `HealthCheckService.php` (3 entries, OG + author only).
5. **en-GB language strings** — `plg_system_aiboost_schema.ini:155` (mentions `aiboost_schema_type`).
6. **Docs** — `docs/per-article-overrides.md` (6 OG fields), `docs/schema-org.md` (author entity).
7. **Verification log** — `docs/feature-verification.md` (lines 137, 169, 186-192, 443-456).

No single place lists all 15. The event fields and `aiboost_schema_type` are the least documented.

---

## 8. Correctness verdict

**Reads are correct and safe.** Every path:
- uses parameterised queries (`quoteName`/`quote`), no string interpolation of user input;
- filters by `f.context` and `f.state = 1`;
- wraps the whole read in `try/catch (\Throwable)` and returns empty on any failure — **never breaks the
  page** (the explicit design intent in every reader);
- ignores empty values and falls back to article data / global defaults;
- enforces value whitelists for the enum-like fields (`og:type`, `twitter:card`, `@type`).

**Output escaping** happens downstream (OG props are rendered by `OgTagBuilder`; schema is emitted via
`json_encode`) — the readers return raw trimmed strings, which is correct for that pipeline.

**One latent inconsistency, not a bug:** OG uses `LEFT JOIN` (needs the field-id map for Falang), event
uses `INNER JOIN` (only populated rows, fine because of fallbacks). Both are correct for their purpose.

---

## 9. Gaps & decisions (first-round findings verified + new)

Legend: ✅ confirmed · ❌ first-round finding was wrong · ◐ partly correct · ★ new finding.

- **G1 ✅ Field names are HARD-CODED.** All 15 names are constants across 4 read paths, 3 creators
  (installer + 2 buttons), 3 Health entries, and `pkg_script`. The admin cannot rename them or map the
  feature onto existing fields. Renaming requires a code change in every location above. *(Decision D4.)*

- **G2 ❌ "Users have NO auto-create" is WRONG.** `SettingsController::createAuthorFields()` exists with a
  **"Create author custom fields"** button on the Schema tab (idempotent). **However ★** the **3 event
  fields and `aiboost_schema_type` have NO auto-create** (no installer, no button) — that is the real
  auto-create gap. *(Decision D3.)*

- **G3 ◐ "No missing-field warning" is PARTLY WRONG.** Author fields **do** have
  `warning_schema_author_fields_missing` + `info_schema_author_fields_coverage`; OG fields have the
  `info_article_custom_fields_pro` tier-presence check. **But ★** there is **no Health check for the 3
  event fields or `aiboost_schema_type`** — if `events_enabled` is on and the fields are absent, the user
  gets no signal. *(Decision D2.)*

- **G4 ✅ No admin UI to view/verify the mappings.** There are two create/repair buttons but **no single
  screen** that lists every field → output mapping with a "present / missing / populated" status.
  Catalogue is scattered across seven locations (§7). *(Decision D1.)*

- **G5 ✅ Author is always `created_by`.** Both the schema author (`SchemaProBuilder` `row.created_by`)
  and the OG `article:author` (`OgTagProDecorator::buildArticleMeta` `created_by`) use the article's
  creator. There is **no per-article author override and no co-author / multiple-author support**.
  *(Decision D5 — optional, larger.)*

- **G6 ✅ `enable_per_article_fields` tier mismatch.** Declared `tier => 'free'` in
  `Manifest/og.php:45-48`, but consumed **only** inside the Pro `OgTagProDecorator`, which runs only when
  `class_exists(OgTagProDecorator)` (Pro build) **and** `PluginRegistry::isProActive()`
  (`AiBoostSocial.php:129`). The en-GB `.ini` lists it under "OpenGraph — Pro Settings [Pro]". So it is a
  Free-labelled toggle for a Pro-only feature → fix the manifest tier to `pro` (cheap, one line + codegen
  + a parity-test allowlist entry). *(Decision D6.)*

- **G7 ★ Three inconsistent translation strategies.** OG fields → Falang value overlay; author fields →
  field-name suffix `_<lang>`/`_en`; event + `@type` → no translation. Not a bug, but confusing to
  document and support. Consider unifying or at least documenting clearly. *(Folded into D1/D4.)*

- **G8 ★ Stale docblocks.** `CustomFieldReader.php` and `OgTagProDecorator.php` claim to live in
  `aiboost_social_pro`; they were relocated into the free `aiboost_social` plugin. Harmless but
  misleading — worth a one-line comment fix. *(Trivial; not a decision.)*

- **G9 ★ OG field definitions duplicated.** The 6 OG field defs exist **twice** (installer
  `ensureOgCustomFields` + controller `repairOgFields`) and can drift. A shared catalogue (D1) would let
  both read one definition.

---

## 10. Recommendation — a single field catalogue (source of truth)

**Proposal P1 (recommended): one PHP catalogue class** —
`component/lib/src/Manifest/CustomFields.php` (or `lib/src/CustomFieldCatalogue.php`) returning a typed
array of every field: `name`, `context` (`com_content.article` | `com_users.user`), `joomla_type`,
`group_title`, `outputs` (list of meta/schema props), `whitelist`, `translatable` (`falang` |
`name_suffix` | `none`), `tier`, `gating_setting`, `auto_create` (bool). Then:

- `ensureOgCustomFields`, `repairOgFields`, and `createAuthorFields` all build their field defs **from
  the catalogue** (kills the OG duplication, G9).
- `CustomFieldReader`, `loadEventCustomFields`, `loadAuthorCustomFields`, `resolveArticleType` read their
  expected names **from the catalogue** instead of inline constants.
- `HealthCheckService` registers presence/coverage checks **from the catalogue** — automatically
  covering event + `@type` (closes G3).
- The doc table in §4 is generated from the catalogue (closes G4's documentation half).

This is the same manifest-first discipline the project already uses for settings, applied to fields.

**Optional add-ons (each a separate, smaller decision):**
- **P2 (D2):** add Health entries for the 3 event fields + `aiboost_schema_type` (driven by the
  catalogue) — warn when the gating toggle is on but the fields are missing, with Fix-It pointing at a
  create button.
- **P3 (D3):** add **"Create event custom fields"** (and optionally an `aiboost_schema_type` list field)
  buttons mirroring the OG/author buttons, also driven by the catalogue.
- **P4 (D1):** a read-only **"Custom Fields" admin panel** (or a card in Health/Help) that renders the
  catalogue with live status (defined? populated on N items?) — the "view/verify mappings" screen.
- **P5 (D4):** make field names configurable (map AI Boost outputs onto admin-chosen existing fields).
  Larger; only if customers ask — most will prefer the one-click create buttons.
- **P6 (D5):** per-article author override / co-authors. Largest; product decision, likely out of scope
  for launch.

---

## 11. Open decisions for Bojan (`ODLUKA`)

- **ODLUKA D1 — Single catalogue + view screen?** Build the one source-of-truth catalogue (P1) and a
  read-only mappings panel (P4)? *(Recommended; cleans up scatter and duplication.)*
- **ODLUKA D2 — Warn when event/`@type` fields are missing?** Add Health checks for the 3 event fields
  and `aiboost_schema_type` (P2)?
- **ODLUKA D3 — Auto-create event/`@type` fields?** Add create buttons for them like OG/author (P3)?
- **ODLUKA D4 — Make field names configurable?** Let admins map outputs onto their own existing fields
  (P5), or keep the fixed one-click-create model? *(Recommend keep fixed for launch.)*
- **ODLUKA D5 — Per-article author override / co-authors?** (P6 — larger; likely post-launch.)
- **ODLUKA D6 — Fix the `enable_per_article_fields` tier?** Change `Manifest/og.php` from `free` to
  `pro` so the label matches the Pro-only runtime (cheap, recommended).

---

*Code-verified 2026-06-28 on `refactor/structural` (order 0014). Read-only analysis — no feature code
changed. Related: `docs/per-article-overrides.md`, `docs/schema-org.md`, `docs/feature-verification.md`,
`docs/analysis/architecture.md`.*
