# Workspace — AI Boost for Joomla

Single global plan: everything operational that isn't code. Status lives in exactly
two places — **this file** (procedures) and **`BACKLOG.md`** (remaining work). No
parallel ledger or status panel.

Companion files: `BACKLOG.md` (forward work), `.local/tasks/master-plan-v5.md`
(long-term vision), `.local/docs/architecture.md` (runtime code map — read before
touching `component/`), `docs/architecture-refactor-plan.md` (decision gates for
large structural refactors — not a status board).

---

## Project

- **Owner:** Bojan (bojancreator) · **Brand:** AI Boost (aiboostnow.com)
- **Product:** AI Boost for Joomla — commercial Joomla 5–6 SEO & AEO package (PHP 8.1–8.5).
- **Mission:** all-in-one package generating Schema.org, XML sitemap, OpenGraph,
  robots.txt, llms.txt and AI-search signals so AI engines (ChatGPT, Perplexity,
  Google AI Overview, Bing Copilot) recommend the site.
- **Scope:** this repo is the Joomla plugin + Pro add-ons + bridges + build tooling.
  WordPress and the marketing site (`bojancreator/aiboostnow`) get their own Replit
  projects later.

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

- Repos: `bojancreator/aiboost-joomla` 🔒 (plugin source) · `bojancreator/aiboostnow` 🌐 (website).
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
2. **Orientation (read in order):** `replit.md` → `BACKLOG.md` → for `component/`
   work, `.local/docs/architecture.md` + load `joomla-development` skill → for
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

Full runtime map with exact `path:line` pointers: **`.local/docs/architecture.md`** —
read before touching any `component/` code. In brief: `component/` is the
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

**Gating model (perpetual activation):** `isProActive()` is a flat 4-branch gate —
`dev_force_free_tier`→false, `pro_activated`→true, `dev_license_preview`→true, else
false. It never walks `license_state` / `license_tier` / heartbeat.
`pro_activated='1'` is set **once** by `PluginRegistry::saveLicenseState()` the first
time any key verifies active and is **never cleared**: Pro then works **forever**. An
expired licence only pauses updates + support (enforced by the update server), it does
**not** relock code. Free = `pro_activated` never set. The DB-only `dev_license_preview`
forces Pro for QA; the DB-only `dev_force_free_tier` forces Free and wins over
everything.

---

## ✅ Build, verify & close (Definition of Done)

A task is done only when every applicable step is finished, in order. **Never report
"done" from a build alone — verify on staging first.**

1. **Do the work.**
2. **Update the Health registry** if any option was added/changed/removed (see rule below).
3. **Bump the version** before building (see Versioning Rule).
4. **Build:** `python3 scripts/build-package-zip.py`.
5. **Install to staging:** `python3 scripts/install-to-staging.py` (also
   `install-to-free.py` for Free-affecting changes).
6. **Schema / install / pkg_script changes only** — run the clean-uninstall verifier,
   **both targets**: `python3 scripts/verify-clean-uninstall.py` (`--target pro`
   default) and `--target free`. It checks no leftover `#__aiboost_*` tables /
   `#__extensions` rows / `robots.txt` / `llms.txt` / `sitemap*.xml`, and that settings
   + translations survive an upgrade.
7. **Verify on staging** — open the admin Dashboard
   (`staging.offroadserbia.com/administrator/index.php?option=com_aiboost`), confirm the
   feature works, the relevant **Health** item passes, and the front-end artifact
   (meta tag / JSON-LD / script) actually appears.
8. **Report** (step 4 of Operating Procedure) and **close** by deleting the item's line
   from `BACKLOG.md`.

Doc-only / website / non-plugin changes skip steps 2–7 but still get a report and a
BACKLOG update if they came from a backlog item.

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
repo — the WordPress plugin lives in a separate Replit project.

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

---

## Pricing (updated 2026-05-25)

**One license unlocks the entire Pro component** — per-feature SKUs are retired.

| Product | Price/year |
|---------|-----------|
| AI Boost (Free) | €0 |
| **AI Boost Pro** (all features) | **€45** |
| Integration bridges (AcyMailing, K2, …) | sold separately |

Processor: Lemon Squeezy (Merchant of Record, handles EU VAT). Bridge plugins each have
their own key and appear in the Licenses tab only when installed.

---

## User Preferences

### Plugin Versioning Rule
**Always bump the version after every change, before building:** Patch (+0.0.1) for
fixes/tweaks; Minor (+0.1.0) for new features/fields/tabs. Never edit `<version>` in XML
manifests — the build script writes it from `component/Version.php`.

### Developer Pro simulation (QA fallback)
No UI toggle (removed so customers can't screenshot a license bypass); the keys are
DB-only. To force Pro on a Free install (or Free on a Pro install with
`dev_force_free_tier`), edit `#__aiboost_settings` directly and set back to `'0'` when done:

```sql
UPDATE jos_aiboost_settings
SET settings_json = JSON_SET(settings_json, '$.dev_license_preview', '1')
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
