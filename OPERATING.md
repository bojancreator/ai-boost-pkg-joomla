# Workspace — AI Boost for Joomla

Single global plan: everything operational that isn't code. The live status board is
**`STATUS.md`** (current version, what's deployed on which site, what's left to launch) —
read it first, update it last. **This file** holds procedures; **`BACKLOG.md`** holds
remaining work. `ROADMAP-v0.5.md` is now an **ARCHIVE** (decision log + verification
history). Auto-memory only **points** to `STATUS.md` — it is never a parallel ledger.

Companion files: `STATUS.md` (live board), `BACKLOG.md` (forward work),
`docs/architecture-refactor-plan.md` (decision gates for large structural refactors — not a status board).

---

## Project

- **Owner:** Bojan (bojancreator) · **Brand:** AI Boost (aiboostnow.com)
- **Product:** AI Boost for Joomla — commercial Joomla 5–6 SEO & AEO package (PHP 8.1–8.5).
- **Mission:** all-in-one package generating Schema.org, XML sitemap, OpenGraph,
  robots.txt, llms.txt and AI-search signals so AI engines (ChatGPT, Perplexity,
  Google AI Overview, Bing Copilot) recommend the site.
- **Scope:** this repo is the Joomla plugin + Pro add-ons + bridges + build tooling.
  WordPress and the marketing site (`bojancreator/aiboostnow`) are separate repositories.

**Key paths:** package source `component/` · version source of truth
`component/Version.php` · deliverables (gitignored) `deliverables/plugin/` · Joomla
dev skill (load before any plugin/component work) `.agents/skills/joomla-development/SKILL.md`.

---

## ⚠️ Branding — single source of truth

| Element | Correct name |
|---|---|
| Website / Brand | **AI Boost** |
| Joomla plugin | **AI Boost for Joomla** |
| WordPress plugin | **AI Boost for WordPress** |
| Domain | aiboostnow.com |
| NEVER use | ~~JoomlaBoost~~, ~~AI Boost Now~~ |

- Repos: `bojancreator/ai-boost-pkg-joomla` 🔒 (plugin source) · `bojancreator/aiboostnow` 🌐 (website).
- Plugin slug stays forever `plg_system_joomlaboost` — changing it breaks existing installs.

---

## ⚠️ Language Rules — ALWAYS FOLLOW

| What | Language |
|------|----------|
| Website, docs, marketing, plugin UI (`.ini`) | 🇬🇧 English ONLY (en-GB) |
| Agent ↔ Bojan conversation | 🇷🇸 Serbian |

Other 6 language packs (`de-DE`, `fr-FR`, `es-ES`, `it-IT`, `ru-RU`, `pt-BR`) and any
non-English content only when Bojan explicitly asks — after English is final.

---

## 🤖 Agent Operating Procedure

1. **Language** — Serbian with Bojan; English for all code, UI, docs, marketing.
2. **Orientation (read in order):** `STATUS.md` (live board — where we are now) →
   `OPERATING.md` → `BACKLOG.md` → for `component/` work,
   load `joomla-development` skill → for
   options, the matching `Manifest/*.php` → before requesting any secret/API key,
   check the `integrations` skill **first**.
3. **Skill routing:**

   | Situation | Skill |
   |---|---|
   | `component/` work, build, staging | `joomla-development` |
   | Creating/updating a procedure or skill | `skill-authoring` |
   | Env vars / secrets | `environment-secrets` (read *before* the work) |
   | Third-party service / API key | `integrations` (check *before* asking Bojan) |
   | Deploy / production logs | `deployment` |
   | Read-only live DB check | `database` (production) |
   | Deep review after a big feature | `code_review` |
   | UI / e2e testing | `testing` |
   | Unsure which fits | run `skillSearch(query)` |

4. **Report proportionally — don't explain every small action.** Trivial/routine edits
   (typo, one line, a version bump) need at most a one-line confirmation, or nothing.
   Write a short Serbian report only for **substantive work** (code change, build, a
   multi-step task): what changed, new version (if code touched), staging result, and
   anything left unfinished.
5. **Suggestions — only when useful.** May offer 1–3 next steps, but skip it when there's
   nothing worth raising. A suggestion does **not** act without Bojan's "da"; for risky
   actions (data loss, broad refactor, swapping a library/API) ask first and explain why.
6. **No silent fallbacks** (fail loudly), **no fabricated/placeholder data** unless
   asked, suggest the agent **tier** with each proposed task, keep memory hygiene
   (durable lessons → `.agents/memory/`, never secrets, no per-task changelogs).
7. **Escalation (after 5–6 attempts) — MANDATORY.** Send this block to Bojan verbatim,
   in Serbian. STOP, don't loop, and **NEVER** report a false "gotovo":

   > **⛔ Pravilo eskalacije (5–6 pokušaja) — OBAVEZNO.** Posle 5–6 ozbiljnih
   > neuspjelih pokušaja da riješi **isti** problem, agent STAJE — ne vrti se u krug
   > i **NIKAD** ne prijavljuje lažno "gotovo". Javi Bojanu na srpskom, ovim redom:
   > 1. **Šta pokušavam** — cilj.
   > 2. **Šta sam probao** — svaki pokušaj i zašto je pao.
   > 3. **Gdje tačno zapinje** — konkretna tačka / greška.
   > 4. **Opcije koje vidim** — sa posljedicom svake.
   > 5. Pitanje: **„Imaš li ideju, ili da stopiramo ovo?"**

---

## Architecture (pointer)

Architecture overview (read before touching `component/`): `component/` is the
`pkg_aiboost` package — admin component (`com_aiboost`, Vue SPA in `vue-admin/`) +
shared `AiBoost\Lib` (`component/lib/src`) + `plugins/system/` Free orchestrators
paired with `*_pro` decorators. `Manifest/*.php` is the single source of truth for
every option; `scripts/codegen-from-manifest.py` derives the rest.

For structural refactors that change settings persistence, Pro gating, service
boundaries, installer behavior, or shared Joomla/WordPress logic, follow
`docs/architecture-refactor-plan.md` before editing code. It defines the
Architecture Decision Gates and when to escalate to XHigh; `BACKLOG.md` remains
the only forward task list.

---

## Manifest-first codegen

`component/lib/src/Manifest/*.php` is the **single source of truth** for every
setting. To add/change an option:

1. **Add the field** to `Manifest/{tab}.php` (or `schema.php`): `key`, `tab`,
   `section`, `label`, `type`, `default`, `tier`, `sku`. Wrap Pro entries in
   `// @pro:start` / `// @pro:end` so the Free ZIP strips them. Vue `type`:
   `toggle|text|textarea|select|number`. Complex types (`json`, `media`) need a
   hand-written control — register the key in `COMPLEX_COVERAGE_ALLOWLIST` if the UI
   lands later.
2. **Declare metadata** (recommended): `feature_class`, `health`, `i18n`.
3. **Run** `python3 scripts/codegen-from-manifest.py` — emits four derived families
   (never hand-edit): Pro feature stubs, en-GB `.ini` keys, Vue partials
   (`vue-admin/src/tabs/generated/`), Health stubs. Build runs `--check` first
   (aborts on any missing stub/key/partial) then `verify-no-pro-leakage.py` STRICT.

**Never** hand-write a Pro feature class without first adding its manifest field.

### Pro gating — registry is mandatory

Every Pro-gated UI surface MUST be registered in
`component/lib/src/ProFeatureRegistry.php` **in the same commit** that adds the UI.
It drives the Vue `<ProGate>`, server-side `stripLocked()` in
`SettingsController::save()`, and the `info_pro_gating_active` check.

- **Field-level:** `key` = settings key. **Section-level:** `key` = `section:{tab}.{section_id}`.
- **Enum-gated** (field editable on Free, subset of values locked): use
  `proOptions()`/`proOptionDefaults()`, render per-option lock (`:disabled` + 🔒),
  server rewrites via `stripProOptions()`.
- **Forbidden:** cosmetic inline `Pro` badges with no registry entry — they leave the
  input editable on Free.

**Gating model (perpetual activation):** `isProActive()` is a single-flag gate —
`pro_activated==='1'` → Pro, else Free. It never walks `license_state` /
`license_tier` / heartbeat. `pro_activated='1'` is set **once** by
`PluginRegistry::saveLicenseState()` the first time any key verifies active and is
**never cleared**: Pro then works **forever**. An expired licence only pauses updates +
support (enforced by the update server), it does **not** relock code. Free =
`pro_activated` never set. (The old `dev_license_preview` / `dev_force_free_tier` QA
overrides were removed in v0.86.5 — they no longer affect the gate; they remain on the
save/import denylist as a permanent anti-self-promotion guard. To put a QA site into
Pro: activate a real key, or DB-seed `pro_activated='1'` — see below.)

---

## ✅ Build, verify & close (Definition of Done)

A task is done only when every applicable step is finished, in order. **Never report
"done" from a build alone — verify on staging first.**

1. **Do the work.**
2. **Update the Health registry** AND **verify the import/export round-trip** if any option
   was added/changed/removed (see the two MANDATORY rules below).
3. **Bump the version** before building (see Versioning Rule).
4. **Build:** `python3 scripts/build-package-zip.py`.
5. **Install to a Pro AND a Free test site** — Free and Pro share one codebase, so every
   shipped change is Free-affecting; never verify Pro only. Routine:
   `python _creds_run.py scripts/install-matrix.py --sites j6pro,j6free` (add `j5pro,j5free`
   when an install/schema path changed). The **live** sites (offroadserbia / offroadbalkans)
   are touched only at release (Release runbook).
6. **Schema / install / pkg_script changes only** — run the clean-uninstall verifier,
   **both targets**: `python3 scripts/verify-clean-uninstall.py` (`--target pro`
   default) and `--target free`. It checks no leftover `#__aiboost_*` tables /
   `#__extensions` rows / `robots.txt` / `llms.txt` / `sitemap*.xml`, and that settings
   + translations survive an upgrade.
7. **Verify on BOTH a Pro and a Free test site** — open the admin Dashboard (Pro:
   `joomla6-pro.testmyweb.info`; Free: `joomla6-free.testmyweb.info`), confirm the feature
   works, the relevant **Health** item passes, the front-end artifact (meta tag / JSON-LD /
   script) actually appears, and on Free that Pro-gated surfaces render **locked**.
8. **Update `STATUS.md`** — run `python _creds_run.py scripts/install-matrix.py --check`
   and refresh the **Deployed Versions** table; a task is not done until Free and Pro match
   on the test sites. Bump `component/Version.php` if code changed.
9. **Report** (step 4 of Operating Procedure) and **close** by deleting the item's line
   from `BACKLOG.md`.

Doc-only / website / non-plugin changes skip steps 2–7 but still get a report and a
BACKLOG update if they came from a backlog item.

Public releases additionally follow the **Release runbook** below (upload ZIPs,
publish the update XML, announce).

**Test sites:**

| Site | URL | Version | PHP | Build |
|------|-----|---------|-----|-------|
| Staging (primary) | `staging.offroadserbia.com` | Joomla 6.1 | 8.5 | Pro (full package) |
| Free staging | `offroadbalkans.com` | Joomla 6 | 8.5 | Free only |
| Joomla 5 Free | `joomla5-free.testmyweb.info` | Joomla 5 | 8.3 | Free |
| Joomla 5 Pro | `joomla5-pro.testmyweb.info` | Joomla 5 | 8.3 | Pro |
| Joomla 6 Free | `joomla6-free.testmyweb.info` | Joomla 6 | 8.3 | Free |
| Joomla 6 Pro | `joomla6-pro.testmyweb.info` | Joomla 6 | 8.3 | Pro |

Admin path for all `testmyweb.info` sites: `/administrator/`. All share user `neimar` —
see `test_sajtovi.md` for credentials (do NOT commit passwords here). CentOS 7 host:
PHP 8.4+ not available on `testmyweb.info` sites.

WordPress test sites (`wp6-*/wp7-*` on `testmyweb.info`) are out of scope for this
repo — the WordPress plugin lives in a separate repository.

> **Staging note (not a bug):** Falang `Deprecated` notices come from the third-party
> Falang plugin + PHP 8.5 + staging `display_errors=On`. Not AI Boost code — don't "fix".

### 🩺 Health registry rule (MANDATORY)

Every time you add/change/remove/fix ANY option, update the Health registry — without a
check item the user can't confirm the feature works. Each entry needs: **id**
(`critical_*`/`warning_*`/`info_*`/`duplicate_*`/`conflict_*`), **category** (from
`HealthCheckService::CATEGORIES`), **label + message** (EN), **expected HTML artifact**,
and **fix_actions[]** (≥1 link to target tab+field: `#tab-{name}-btn` +
`data-ab-field="{key}"`).

Locations: server check `HealthCheckService.php` · duplicate scan
`DuplicateTagScanner.php` · conflict scan `ConflictDetector.php` · frontend
`vue-admin/src/HealthApp.vue`.

### 🔁 Import/export round-trip rule (MANDATORY)

Every time you add/change/remove ANY option, verify the settings **import/export JSON
round-trip** — a setting that saves but is dropped on export (or is lost on re-import) is a
silent data-loss bug, and it is invisible without this check. The three-way key alignment
(Vue `v-model` ↔ `SettingsController::$fields` whitelist ↔ consuming Service) is exactly
what breaks here, so this gate runs alongside the Health registry rule for any option change.

Run the non-destructive verifier against a live test site:
`python _creds_run.py scripts/verify-import-export.py --target staging`. It confirms the
export covers the new key, the Free/Pro licence boundary holds, and a sentinel value
survives an export → import → export round-trip idempotently. To author test variants as
JSON (no manual backend entry), generate the full-key template with
`scripts/make-import-template.py`, then change only the keys you want to test (import merges).

---

## Release runbook (v1 — manual channel)

There is **no update server** in v1 (decision 2026-06-11). Free updates are announced
via a **static** Joomla update-server XML hosted at
`https://aiboostnow.com/updates/pkg_aiboost.xml` (the URL referenced by
`<updateservers>` in `component/package/pkg_aiboost.xml`). Pro ZIPs are delivered
through the Lemon Squeezy "My Orders" portal + e-mail notifications — no in-app Pro
update channel, by design (see the comment in `pkg_aiboost_pro.xml`).

Per public release, in order:

1. **Bump + build (lockstep):** `python3 scripts/bump-version.py <patch|minor|major>`,
   then `python3 scripts/build-package-zip.py --target all` — Free and Pro ZIPs must
   ship at the same version.
2. **Full QA** per Definition of Done above, including a **real-licence smoke test**:
   verify a genuine Lemon Squeezy key on Pro staging (License & Updates → Verify) —
   not just the dev-preview override.
3. **Upload the ZIPs:** `pkg_aiboost_pro-{v}.zip` to the Lemon Squeezy product files
   (Pro buyers get it via "My Orders" + the release e-mail); `pkg_aiboost-{v}.zip` to
   the public download location `https://aiboostnow.com/downloads/`.
4. **Publish the update XML:** `python3 scripts/generate-update-xml.py`, then upload
   `deliverables/updates/pkg_aiboost.xml` to `https://aiboostnow.com/updates/`.
   Existing Free installs now get Joomla's native update notice.
5. **Announce** the release to the customer e-mail list (Lemon Squeezy e-mail or the
   list tool).
6. **Update the ROADMAP `Verification Log`** with the release version and staging
   verification results.

> **Before the first public release:** the update XML must already be live at
> `https://aiboostnow.com/updates/pkg_aiboost.xml` — the Free package manifest points
> there from install time. On sites installed before the XML went live, Joomla may
> have auto-disabled the update site after failed fetches — re-enable it in
> **System → Update Sites**.

---

## Pricing / Entitlement (updated 2026-06-16)

**Core = one commercial license, three site-count tiers** — all three core tiers
ship the SAME Pro code and unlock the same perpetual activation (`pro_activated`);
the tier differs only commercially (the plugin never counts sites). The two
integrations are sold separately, each its own Lemon Squeezy product + key.

| Lemon Squeezy product | Price/year | License SKU (code) |
|---|---|---|
| AI Boost for Joomla — PRO (3 sites) | €65 | `bundle` (core) |
| AI Boost for Joomla — PRO+ (10 sites) | €120 | `bundle` (core) |
| AI Boost for Joomla — Unlimited web sites | €180 | `bundle` (core) |
| AI Boost Joomla plugin for Multilang — PRO | €25 | `int_falang` |
| AI Boost Joomla plugin for YOOtheme Pro — PRO | €20 | `int_yootheme` |

Processor: Lemon Squeezy (Merchant of Record, handles EU VAT).

**Per-product locking (each key unlocks only its product).** A same-store key for
one product can never activate another: core is pinned to the set of the three core
product IDs, each integration to its single product ID. This closes the leak where a
cheap €20/€25 add-on key could otherwise unlock the €65+ core bundle.

**RELEASE BLOCKERS — fill these constants in `component/lib/src/LicenseValidator.php`
before launch (from the Lemon Squeezy dashboard; store + product IDs are stable across
test and live mode):**

- `EXPECTED_STORE_ID` — the aiboostnow.com store ID.
- `EXPECTED_CORE_PRODUCT_IDS` — `[<PRO 3>, <PRO+ 10>, <Unlimited>]` product IDs.
- `EXPECTED_PRODUCT_IDS['int_falang']` — the Multilang product ID.
- `EXPECTED_PRODUCT_IDS['int_yootheme']` — the YOOtheme product ID.

While any are unset they FAIL CLOSED (core falls back to store-pin only if core IDs
are empty; integrations refuse to verify until their product ID is set). The Licenses
screen shows one core "AI Boost" key plus a row per installed sellable integration.

---

## User Preferences

### Plugin Versioning Rule
**Always bump the version after every change, before building:** Patch (+0.0.1) for
fixes/tweaks; Minor (+0.1.0) for new features/fields/tabs. Never edit `<version>` in XML
manifests — the build script writes it from `component/Version.php`.

### Forcing Pro on a QA site
There is no licence-bypass override (the `dev_license_preview` / `dev_force_free_tier`
keys were removed in v0.86.5). To put a QA site into Pro, **activate a real key** (the
Licenses page → `settings.verifyLicense`; issue a key from the api.aiboostnow.com
backend admin API or `bin/issue-key.php`), or DB-seed the perpetual flag directly:

```sql
UPDATE jos_aiboost_settings
SET settings_json = JSON_SET(settings_json, '$.pro_activated', '1')
WHERE setting_key = 'main';
```

### Agent tier guidance (Lite · Economy · Power)
Suggest a tier with each proposed task. Default **Economy**; drop to **Lite** for purely
mechanical edits; escalate to **Power** only when clearly warranted.

- **Lite:** typo/wording in one known file, one table row, pasting supplied text, a
  single version/date bump with no logic.
- **Economy:** copy/translation tweaks, version bump + build + staging install (no logic),
  CSS-only fixes, file cleanup/renames, one Health entry for a shipped feature, doc edits,
  single-plugin bug fix ≤3 files.
- **Power:** new plugin/tab with PHP services, Health changes spanning service + Vue +
  verification, refactors touching ≥3 plugins, Lemon Squeezy / licensing / gating work, DB
  schema / `install.sql` / `pkg_script.php` migrations, Vue SPA refactors, anything reading
  >5 source files or modifying >3 plugins, or touching `DocumentInspector` / conflict /
  scanner logic.
