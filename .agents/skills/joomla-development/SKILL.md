# Joomla Development Skill — AI Boost for Joomla

## When to load this skill

Load this skill any time you are working on:
- Any file inside `component/` (plugins, component, lib, package)
- Build scripts (`scripts/build-*.py`, `scripts/install-to-staging.py`)
- Staging installs or version bumps
- Any task plan that touches plugin or component PHP code

> **Source validation:** All facts in this skill were verified directly against the source files:
> `component/Version.php`, `component/package/pkg_aiboost.xml`,
> `component/plugins/system/aiboost_schema/aiboost_schema.{php,xml}`,
> `component/plugins/system/aiboost_core/aiboost_core.php`,
> `component/lib/src/ProGate.php`, `scripts/build-component-plugins.py`,
> `scripts/install-to-staging.py`. Update this skill whenever source code diverges.

> **Package manifest vs active plugins:** `component/package/pkg_aiboost.xml` lists the plugins
> bundled in the outer package ZIP (used for bulk distribution). The authoritative list of
> *actively developed* plugins is the `PLUGINS` array in `scripts/build-component-plugins.py`.
> When these two lists differ, trust the build script — the package manifest may lag behind.

---

## Branding (non-negotiable)

| Correct | Never use |
|---------|-----------|
| **AI Boost for Joomla** | ~~JoomlaBoost~~, ~~AI Boost Now~~ |
| **AI Boost** (brand) | — |
| Domain: `aiboostnow.com` | — |

Single source of truth: `replit.md` → "Branding" section.

---

## Package Architecture

```
pkg_aiboost (outer Joomla Package)
├── com_aiboost              — Admin component (Dashboard, Settings, Import)
└── plugins/system/
    ├── aiboost_core         — Shared lib bootstrap (ordering=1, MUST be first)
    ├── aiboost_schema       — Schema.org JSON-LD
    ├── aiboost_sitemap      — XML Sitemap + hreflang
    ├── aiboost_social       — OpenGraph + Twitter Cards
    ├── aiboost_seo          — Canonical URL, robots meta
    ├── aiboost_aeo          — llms.txt + IndexNow [Pro]
    └── aiboost_code         — Custom code injection (head/body/footer)

Add-ons (separate ZIPs, NOT in main package):
    aiboost_yootheme         — YooTheme Pro bridge
    aiboost_falang           — Falang Pro hreflang bridge
```

**Version source of truth:** `component/Version.php`
- Build scripts inject this version into every manifest and installer script
- Never manually edit `<version>` in any XML — the build script does it

**Shared lib:** `component/lib/src/`
- Contains: `ProGate.php`, `ConflictManager.php`, `LicenseValidator.php`, and service classes
- Each plugin bundles its own copy at install time (see Build section)

---

## Plugin Anatomy

Every plugin follows this exact structure:

```
aiboost_{slug}/
├── aiboost_{slug}.php        ← Legacy entry point (REQUIRED — see Class Alias section)
├── aiboost_{slug}.xml        ← Manifest (REQUIRED)
├── script.php                ← Installer script (optional, required for aiboost_core)
├── src/
│   └── Extension/
│       └── AiBoost{Slug}.php ← Main plugin class (extends CMSPlugin)
├── lib/
│   └── src/
│       ├── ProGate.php       ← Bundled at build time by build script
│       ├── ConflictManager.php
│       └── LicenseValidator.php
└── language/
    ├── en-GB/
    │   ├── plg_system_aiboost_{slug}.ini
    │   └── plg_system_aiboost_{slug}.sys.ini
    ├── de-DE/  (and 4 other languages)
    └── ...
```

---

## Plugin Entry Point (`aiboost_{slug}.php`)

This file does three things in this exact order:

```php
<?php
defined('_JEXEC') or die;

// 1. Load shared lib (conditional — safe to call on every request)
(static function () {
    $dir = __DIR__ . '/lib/src/';
    foreach (['ProGate', 'ConflictManager', 'LicenseValidator'] as $cls) {
        if (!class_exists("AiBoost\\Lib\\{$cls}", false) 
            && !trait_exists("AiBoost\\Lib\\{$cls}", false) 
            && file_exists($dir . $cls . '.php')) {
            require_once $dir . $cls . '.php';
        }
    }
})();

// 2. Load the real extension class
require_once __DIR__ . '/src/Extension/AiBoost{Slug}.php';

// 3. Register legacy class alias (see Class Alias section)
if (!class_exists('PlgSystemAiboost_{slug}', false)) {
    class_alias(
        \AiBoost\Plugin\System\AiBoost{Slug}\Extension\AiBoost{Slug}::class,
        'PlgSystemAiboost_{slug}'
    );
}
```

The `aiboost_core` plugin is the exception — it has no `src/Extension/` class, only the bootstrap IIFE and a minimal `PlgSystemAiboost_core extends CMSPlugin` stub.

---

## Class Alias Pattern (critical — never skip)

Joomla 3/4/5/6 legacy loader constructs the class name as:
```
PlgSystem + ucfirst($element)
```

For `element = aiboost_schema`:
- `ucfirst('aiboost_schema')` = `'Aiboost_schema'`
- Expected class: `PlgSystemAiboost_schema`

The real class lives in a namespaced path. Without the alias, Joomla cannot find it.

**Rule:** Every plugin entry point MUST register `PlgSystem{ucfirst(element)}` as a class alias pointing to the namespaced class. Check the alias in `aiboost_schema.php` as the reference.

---

## Manifest XML Rules

Required fields (every plugin):
```xml
<?xml version="1.0" encoding="utf-8"?>
<extension type="plugin" group="system" method="upgrade">
  <name>PLG_SYSTEM_AIBOOST_{SLUG}</name>           <!-- language constant, NOT plain text -->
  <version>0.7.0</version>                          <!-- injected by build script -->
  <description>PLG_SYSTEM_AIBOOST_{SLUG}_XML_DESCRIPTION</description>
  <php_minimum>8.1.0</php_minimum>
  <joomla_minimum>5.0.0</joomla_minimum>
  <namespace path="src">AiBoost\Plugin\System\AiBoost{Slug}</namespace>
  <files>
    <filename plugin="aiboost_{slug}">aiboost_{slug}.php</filename>
    <folder>src</folder>
    <folder>lib</folder>
    <folder>language</folder>
  </files>
  <languages>
    <language tag="en-GB">language/en-GB/plg_system_aiboost_{slug}.ini</language>
    <language tag="en-GB">language/en-GB/plg_system_aiboost_{slug}.sys.ini</language>
    <!-- ... other language tags ... -->
  </languages>
  <config>
    <fields name="params">
      <!-- fieldsets here -->
    </fields>
  </config>
</extension>
```

Critical rules:
- `method="upgrade"` is MANDATORY — without it reinstall fails
- `<name>` must use a language constant (not plain text) — Joomla uses it in the Extensions list
- `<namespace path="src">` declares the PSR-4 root; Joomla autoloads from `src/`
- `<filename plugin="aiboost_{slug}">` — the `plugin` attribute tells Joomla which file is the entry point
- Version is injected by the build script — never hardcode it manually

---

## `showon` Attribute Syntax

Used in XML `<field>` and `<fieldset>` to conditionally show/hide:

```xml
<!-- Show only when license_tier = pro -->
<fieldset name="rating" showon="license_tier:pro">

<!-- Show for multiple schema_type values -->
<field name="specific_price_range"
       showon="schema_type:localbusiness[OR]schema_type:restaurant[OR]schema_type:hotel" />

<!-- Show when a toggle is off (value = 0) -->
<field name="hours_mon_opens" showon="hours_mon_closed:0" />
```

Rules:
- `[OR]` joins multiple conditions — no spaces around it
- `[AND]` also available for AND logic
- Use `showon` on `<fieldset>` to hide the entire group
- `showon` on a `<fieldset>` does NOT prevent the fields inside from saving — only hides them visually

---

## Pro Gating

Every Pro feature is gated via the `ProGate` trait:

```php
class AiBoostSchema extends CMSPlugin
{
    use \AiBoost\Lib\ProGate;

    public function onBeforeCompileHead(): void
    {
        if (!$this->isProEnabled()) {
            return; // Free tier — skip Pro logic
        }
        // ... Pro-only code ...
    }
}
```

In XML manifest, every plugin MUST have these fields in a `license` fieldset:
```xml
<fieldset name="license" label="PLG_SYSTEM_AIBOOST_{SLUG}_FIELDSET_LICENSE">
  <field name="license_key"    type="text"   ... />
  <field name="license_tier"   type="hidden" default="free" />
  <field name="license_status" type="text"   readonly="true" default="Free" ... />
</fieldset>
```

The `license_tier` hidden field is what `isProEnabled()` reads. It is written by `ProGate::validateAndStoreLicense()` which fires automatically from `onExtensionAfterSave` (already in the trait — nothing extra needed).

Pro-only fieldsets use `showon="license_tier:pro"` to hide them visually on Free tier.

---

## ConflictManager

Prevents two plugins from injecting the same type of output (e.g., two Schema.org plugins):

```php
// In onAfterInitialise — claim the slot early
public function onAfterInitialise(): void
{
    if ($this->ensureConflictManager()) {
        $this->schemaSlotClaimed = \AiBoost\Lib\ConflictManager::claim(
            \AiBoost\Lib\ConflictManager::SLOT_SCHEMA_ORG,
            'aiboost_schema'
        );
    } else {
        $this->schemaSlotClaimed = true; // No ConflictManager — proceed anyway
    }
}

// In onBeforeCompileHead — skip if slot not owned
public function onBeforeCompileHead(): void
{
    if (!$this->schemaSlotClaimed) {
        return;
    }
    // ... inject output ...
}
```

Available slots: `SLOT_SCHEMA_ORG`, `SLOT_SITEMAP`, `SLOT_SOCIAL`, `SLOT_CANONICAL`, `SLOT_AEO`.

The `ensureConflictManager()` method is provided by `ProGate` — it lazy-loads the class from the component lib if `aiboost_core` isn't installed.

---

## Language Files

Two files per language per plugin:

| File | Purpose |
|------|---------|
| `plg_system_aiboost_{slug}.ini` | Labels/descriptions shown in plugin config form |
| `plg_system_aiboost_{slug}.sys.ini` | Plugin name/description shown in Extensions list |

Naming convention for constants:
```ini
; .sys.ini
PLG_SYSTEM_AIBOOST_SCHEMA="AI Boost — Schema.org"
PLG_SYSTEM_AIBOOST_SCHEMA_XML_DESCRIPTION="Generates Schema.org JSON-LD..."

; .ini
PLG_SYSTEM_AIBOOST_SCHEMA_FIELDSET_LICENSE="License"
PLG_SYSTEM_AIBOOST_SCHEMA_LICENSE_KEY_LABEL="License Key"
PLG_SYSTEM_AIBOOST_SCHEMA_ORG_NAME_LABEL="Organization Name"
```

Rules:
- **Only `en-GB` until the final version** — do NOT touch `de-DE`, `fr-FR`, etc.
- All string values must be in double quotes
- No trailing spaces
- Constants are ALL_CAPS with underscores
- String values may contain HTML but must be on one line

---

## Shared Lib Bundle

`component/lib/src/` contains the shared classes used by all plugins. Since plugins are distributed as standalone ZIPs (installable without `com_aiboost`), each plugin ZIP must contain its own copy of the lib files.

The build script (`build-component-plugins.py`) does this automatically:
```python
lib_src = REPO_ROOT / "component" / "lib" / "src"
lib_dst = plugin_tmp / "lib" / "src"
for cls in ["ProGate", "ConflictManager", "LicenseValidator"]:
    shutil.copy2(lib_src / f"{cls}.php", lib_dst / f"{cls}.php")
```

The entry point loads them with a conditional check (see Entry Point section) so multiple plugins loading the same file is safe — each class/trait has `if (!class_exists / !trait_exists)` guards.

---

## aiboost_core Plugin (Special Rules)

- **Must always be installed FIRST** with ordering = 1
- Its `script.php` sets ordering = 1 automatically via `postflight()`
- It has no event handlers — exists solely to bootstrap `ProGate`, `ConflictManager`, `LicenseValidator` once
- Install order on staging: always use `--all-plugins` which installs `aiboost_core` before feature plugins

---

## Build Workflow

### Build individual plugin ZIPs (most common)
```bash
python3 scripts/build-component-plugins.py --all
# or single plugin:
python3 scripts/build-component-plugins.py --plugin aiboost_schema
# with version bump:
python3 scripts/build-component-plugins.py --all --bump patch
```
Output: `deliverables/plugin/plg_system_aiboost_{slug}-{version}.zip`

### Build full package ZIP
```bash
python3 scripts/build-package-zip.py
```
Output: `deliverables/plugin/pkg_aiboost-{version}.zip`

⚠️ **Warning:** The full package ZIP currently fails to install on staging (Joomla 6.1 compatibility issue). Use individual plugin ZIPs instead.

### Build add-on ZIPs
```bash
python3 scripts/build-addon-zip.py
```

---

## Staging Deploy Procedure (MANDATORY after every change)

**Never finish a plugin/component task without staging install + verification.**

```bash
# Step 1: Build
python3 scripts/build-component-plugins.py --all

# Step 2: Install on staging (all plugins in one session)
python3 scripts/install-to-staging.py --all-plugins

# Step 3: Verify
# Open: https://staging.offroadserbia.com/administrator/index.php?option=com_aiboost
# Check Dashboard shows all plugins enabled, no errors
```

**Single plugin deploy:**
```bash
python3 scripts/install-to-staging.py --zip deliverables/plugin/plg_system_aiboost_schema-0.7.0.zip
```

Staging details:
- URL: `staging.offroadserbia.com` (Joomla 6.1)
- Credentials: env vars `STAGING_URL`, `STAGING_ADMIN_USER`, `STAGING_ADMIN_PASS`
- If installer fails with IP block error: visit `https://staging.offroadserbia.com/administrator/index.php?admintools_rescue=EMAIL`

---

## Version Management

**Single source of truth: `component/Version.php`**

```php
final class Version {
    public const VERSION = '0.7.6';
    // ...
}
```

Version is injected by build scripts into:
- All plugin XML manifests (`<version>` tag)
- `com_aiboost.xml`
- `pkg_aiboost.xml`
- Installer script `VERSION` constants

**Bumping versions:**

For component plugins (in `component/plugins/system/`), each plugin has its own version in its XML manifest. Use the build script's `--bump` flag:
```bash
python3 scripts/build-component-plugins.py --all --bump patch
```

For the legacy plugin (archived, `plugin/src/`):
```bash
python3 scripts/bump-version.py patch
```

**Rule:** Always bump version before building a ZIP that will be installed anywhere.
- Patch (+0.0.1): bug fixes, small tweaks
- Minor (+0.1.0): new features, new tabs/fields
- Major: breaking changes (rare)

---

## com_aiboost Component

Admin URL: `/administrator/index.php?option=com_aiboost`

Structure:
```
com_aiboost/
├── com_aiboost.xml               ← Component manifest
└── admin/
    ├── com_aiboost.php           ← Entry point (MVC bootstrap)
    ├── script.php                ← Installer script
    ├── access.xml                ← ACL
    ├── css/admin.css
    ├── js/settings.js            ← AJAX save
    ├── js/import.js              ← Import wizard
    ├── language/en-GB/
    ├── lib/autoload.php          ← PSR-4 loader for AiBoost\Lib
    ├── services/provider.php     ← Joomla DI container
    ├── sql/install.sql           ← Creates #__aiboost_settings + #__aiboost_translations
    └── src/Administrator/        ← MVC: Controller, Extension, View, tmpl/
```

DB tables:
| Table | Purpose |
|-------|---------|
| `#__aiboost_settings` | Single-row JSON blob (key=`main`) |
| `#__aiboost_translations` | Per-field, per-language text values |

Admin UI tech stack: **PHP templates + vanilla JS** (no Vue, no React, no framework).

---

## Add-on Plugins (Separate Distribution)

Add-ons are NOT included in `pkg_aiboost`. They require paid third-party software:

| Add-on | Requires |
|--------|---------|
| `aiboost_yootheme` | YooTheme Pro |
| `aiboost_falang` | Falang Pro |

Build: `python3 scripts/build-addon-zip.py`
Install: manually via Joomla Extension Manager after `pkg_aiboost`

---

## Joomla Event System

Plugins subscribe to events by implementing methods with the `on` prefix, or explicitly via `getSubscribedEvents()`:

```php
// Auto-subscription (CMSPlugin scans for on* methods)
public function onBeforeCompileHead(): void { ... }
public function onAfterInitialise(): void { ... }
public function onAfterRender(): void { ... }

// Explicit subscription (preferred, modern Joomla style)
public static function getSubscribedEvents(): array
{
    return [
        'onBeforeCompileHead' => 'handleHead',
        'onAfterInitialise'   => 'handleInit',
    ];
}
```

Key events used by AI Boost plugins:
| Event | When | Used by |
|-------|------|---------|
| `onAfterInitialise` | Early request — before routing | ConflictManager slot claiming |
| `onBeforeCompileHead` | Just before `<head>` output | Schema, Social, SEO, AEO |
| `onBeforeRender` | Just before full page output (buffered body available) | Code injection, body-level modifications |
| `onAfterRender` | After full page render (response body in `$app->getBody()`) | Sitemap response, post-render body rewrites |
| `onExtensionAfterSave` | After plugin config save | ProGate license validation |

---

## Lessons Learned / Gotchas

**L001 — Tab label conflict (Task #90)**
The old monolithic plugin was adding `<script>` tags that overwrote CSS classes used by ALL Joomla plugins' tab labels. Fix: scope CSS selectors to `#com_plugins .aiboost-*` to avoid global namespace pollution. Any admin CSS must be scoped to the plugin's own form container.

**L002 — Class alias for legacy loader**
Joomla's plugin loader calls `new PlgSystem{ucfirst($element)}()`. Without the `class_alias()` in the entry point, the plugin silently fails to load — no error, just missing functionality. Always verify the alias matches exactly: `PlgSystemAiboost_schema` (note the underscore is preserved by `ucfirst`).

**L003 — ProGate trait redeclaration (fixed v0.7.0)**
When multiple plugins include the same trait file, PHP throws a fatal if not guarded. All `lib/src/` classes/traits must be wrapped in `if (!class_exists / !trait_exists)` guards. `aiboost_core` (ordering=1) loads them first; all other plugins skip if already loaded.

**L004 — Version sync across files (Tasks #93, #95)**
Never manually edit `<version>` in XML manifests — the build script injects from `Version.php`. The old PowerShell build script was removed in favor of Python scripts. Use `scripts/build-component-plugins.py` for everything.

**L005 — pkg_aiboost full ZIP fails on Joomla 6.1 staging**
The outer package ZIP (`pkg_aiboost-*.zip`) fails on staging. Use individual plugin ZIPs via `install-to-staging.py --all-plugins`. The individual ZIPs install fine on Joomla 5 and 6.

**L006 — PHP 8.5 CI (Task #103)**
PHP 8.5 was added to the test matrix in `bojancreator/ai-boost-pkg-joomla` GitHub Actions. If new PHP syntax is used, verify it passes on 8.1–8.5. Avoid deprecated dynamic properties — use explicit property declarations.

**L462 — Manifest-first codegen + build-time Pro stripping (Task #462)**
`component/lib/src/Manifest/*.php` is the single source of truth. New fields can declare `feature_class`, `health`, and `i18n` blocks; `scripts/codegen-from-manifest.py` then auto-generates the Pro feature stub at `component/plugins/system/aiboost_{sku}_pro/src/Features/{Class}.php`, appends en-GB `.ini` keys, and `HealthCheckService::registerFromManifest()` registers the Health entry at runtime.

For Pro fields, wrap the array entry between `// @pro:start` and `// @pro:end` — `build-package-zip.py` strips those blocks from the Free ZIP (admin/, lib/, every plugin/) but NOT from Pro plugin ZIPs (those keep their @pro blocks because they ARE the Pro payload). The verifier (`verify-no-pro-leakage.py`) now runs in STRICT mode and fails the build on any leaked Pro token.

Gotcha: never put the literal `@pro:start` … `@pro:end` text in a comment — the stripping regex matches across lines and will eat the surrounding code. Use prose like "opt-in opening/closing markers" instead.

**L007 — `method="upgrade"` is mandatory**
Without `method="upgrade"` in the extension XML, Joomla refuses to reinstall over an existing version. ALWAYS include it.

**L008 — `showon` does not prevent saving**
Fields hidden by `showon` are still saved when the form is submitted. If you need to block saving of hidden Pro fields on Free tier, validate server-side in the plugin's event handler before using the value.

**L009 — aiboost_core ordering**
`aiboost_core` must have Joomla ordering = 1. Its `script.php` sets this automatically via `postflight()`. If you create a new bootstrap-style plugin, copy this pattern. Joomla ordering is global across all system plugins — do not use ordering=1 for feature plugins, only the core bootstrap.

**L010 — Standalone plugin vs component plugin**
Plugins in `component/plugins/system/` are part of `pkg_aiboost` and can depend on `aiboost_core` being installed. Plugins in `plugins/` (now archived/add-ons) are truly standalone and cannot depend on any shared component. Always check which category a new plugin belongs to before writing its lib-loading code.

**L011 — Installer script class naming**
Plugin installer script class must be named `PlgSystem{Ucfirst(element)}InstallerScript`. For `aiboost_core`: `PlgSystemAiboost_coreInstallerScript`. This matches Joomla's naming convention for plugin installers.

**L012 — `defined('_JEXEC') or die` required everywhere**
Every PHP file in a plugin must start with `defined('_JEXEC') or die;` immediately after `<?php`. Without it, the file can be accessed directly via HTTP. No exceptions.

**L013 — subform field type for FAQ/repeatable data**
Use `type="subform"` with `layout="joomla.form.field.subform.repeatable-table"` for repeatable sets of fields (FAQ items, events). Set `multiple="true"` and `max="N"`. The saved value is a JSON object keyed by `item0`, `item1`, etc.

**L014 — Language tag in `<languages>` block**
The `<languages>` block in the manifest must list both `.ini` and `.sys.ini` for every language tag. Missing `.sys.ini` causes the plugin name to show as the raw constant in the Extensions list.

**L015 — Vue SPA shell in Joomla admin component (task #335)**
Trying to win the war against Joomla/YooTheme/Atum Bootstrap class overrides per-component is a losing battle. Instead, mount a single Vue 3 SPA inside one PHP shell view and let Vue Router (hash mode) own all UI routing.

Pattern:
- Add a `view=app` (default view in `DisplayController`) whose `tmpl/app/default.php` renders only `<div id="ab-app"></div>` plus a single inline `<script>window.aiBoostBootstrap = {...}</script>` with CSRF token (`Session::getFormToken()`), base URL, AJAX endpoint URLs, `legacyUrls` for every legacy view (`?option=...&view=X&tmpl=component`), `isPro`, and localized nav labels.
- `vue-router@4` in hash mode — Joomla swallows the path, so only `#/foo` survives. Routes register all Vue pages (`/dashboard`, `/health`, `/settings`, `/integrations`, `/analyzers`, `/help`).
- Component manifest `<menu link="option=com_aiboost&amp;view=app">` so the sidebar opens the SPA, not the legacy dashboard.
- Legacy fallback: `main.js` mounts the SPA only if `#ab-app` exists, otherwise falls back to per-ID mounts. This keeps `&view=dashboard` etc. working for incremental migration.
- For Vue routes that still need data from a legacy PHP view, use a `useLegacyGlobals(viewName)` composable that fetches `legacyUrls[viewName]` (which appends `tmpl=component`) and executes inline `<script>` blocks to populate `window.aiBoost*` globals.
- `api.js` reads CSRF from `window.aiBoostBootstrap.csrf.tokenName` and auto-injects it into POST bodies.
- `useColorScheme()` composable: `MutationObserver` on `<body data-bs-theme>` → reactive ref. Do NOT poll.
- Build script (`scripts/build-package-zip.py`) already runs `pnpm build` in `vue-admin/` automatically.

Gotcha: existing per-ID Vue mounts (`#ab-vue-settings`, `#ab-vue-health`, …) must be preserved during the transition — delete them only after the legacy PHP view is migrated to a Vue route.

**L016 — AI Boost Design System: do not fight Bootstrap (task #336)**
Overriding `--bs-btn-*` per-screen to fix Atum/YooTheme dark-mode regressions is a losing battle: Bootstrap 5.3 reads CSS variables (not plain `color:`), YooTheme widgetkit re-declares `--bs-*` aggressively, and every new tab opens a new override round. Solution — own the surface entirely with a parallel namespace.

Pattern:
- `admin/css/ab-tokens.css` — declare every design value as `--ab-*` (palette, typography, spacing, radius, shadow, focus ring). Scope the root declaration to `body[class*="com_aiboost"]` so tokens never leak to other Joomla admin screens. Cover both Joomla orderings for dark: `body[class*="com_aiboost"][data-bs-theme="dark"]` AND `[data-bs-theme="dark"] body[class*="com_aiboost"]`.
- `admin/css/ab-components.css` — primitives (`.ab-btn`, `.ab-card`, `.ab-input`, `.ab-badge`, `.ab-tabs`, `.ab-alert`, `.ab-tag`, `.ab-toggle`) that consume only `--ab-*` tokens. Never read `--bs-*`. Avoid `!important` unless an upstream `.btn`/`.card` rule wins the cascade; comment why.
- Load both files BEFORE `admin.css` in every `tmpl/*/default.php`, via `HTMLHelper::_('stylesheet', 'com_aiboost/ab-tokens.css', ['relative' => true, 'version' => 'auto'])`.
- Vue mount points migrate `btn btn-outline-*` → `ab-btn ab-btn--ghost`, `btn btn-primary` → `ab-btn ab-btn--primary`, `card` → `ab-card`, etc.
- Reference page: a Vue route `/_styleguide` (registered in `vue-admin/src/router.js`, hidden from the nav) renders every primitive in every variant with a dark/light toggle. Open it after any token change to verify visually.
- Once a view is migrated, delete the corresponding `.btn-outline-*` dark override from `admin.css` — it becomes dead code. Keep transitional `.text-muted`/`.form-text` overrides until every non-Vue view migrates.

Gotcha: scope every component selector with `body[class*="com_aiboost"] .ab-…`, otherwise the classes would render on Joomla front-end pages or other admin contexts if accidentally emitted.

**L017 — Migrating a legacy PHP view to a Vue page (task #337)**
To replace an inline-PHP+JS template (e.g. Redirects, URL Checker, Import) with a Vue SFC living inside the existing SPA bundle:

1. Add a JSON list endpoint to the matching `*Controller` if one does not exist (e.g. `RedirectsController::listJson`). All endpoints must enforce `core.manage` auth + `Session::checkToken()` (for write tasks) and `echo json_encode([...])` + `$this->app->close()` so Joomla never appends the admin chrome.
2. Build the Vue SFC under `vue-admin/src/<Name>Page.vue`. Fetch via `postWithCsrf(makeAdminUrl('controller.task'))` from `api.js` — never craft URLs or token names by hand.
3. Wire it into BOTH router targets in the same change:
   - `router.js` — replace the `LegacyRedirect` stub with the real component (keep `meta.legacyUrl: ''` so AppShell skips the bootstrap fetch).
   - `main.js` — add a `document.getElementById('ab-vue-<slug>')` mount in `mountLegacy()` so the per-view PHP shell still works when users hit `?option=com_aiboost&view=<slug>` directly (Joomla menu items, bookmarks).
4. Reduce the corresponding `tmpl/<view>/default.php` to a thin shell: load `ab-tokens.css`, `ab-components.css`, `admin.css`, `admin-vue.js`, render the nav `<ul class="nav ab-view-nav">`, set `window.aiBoostToken = <?= json_encode($tokenName) ?>`, render `<div id="ab-vue-<slug>"></div>`. Do not delete the `View/<Name>/HtmlView.php` PHP class — Joomla still routes to it; the View just returns the shell. Removing the View class requires also rewriting the routing to land on the SPA `app` view, which is a separate refactor.
5. `python3 scripts/build-package-zip.py` (auto-runs Vue `pnpm build`) → `python3 scripts/install-to-staging.py` → verify on staging admin.

Gotcha: `postWithCsrf` POSTs the CSRF token under its random name (`window.aiBoostBootstrap.tokenName || window.aiBoostToken`). If you forget to inject `window.aiBoostToken` in the per-view shell template, every write task fails with "Invalid security token" but reads (no `Session::checkToken()`) still work — easy to miss until the first toggle/delete.

---

**L018 — Vue ↔ Controller ↔ Service three-way alignment (task #374)**
When a single setting is touched by three layers, ALL three must agree on the key names or the option is silently dead:
1. **Vue tab** (`vue-admin/src/tabs/*.vue`) — `v-model="s[key]"` declares the canonical setting key. Add `:data-ab-field="key"` so Health → Fix-It can scroll to the exact control.
2. **SettingsController** (`admin/src/Administrator/Controller/SettingsController.php`) — every key must appear in the `$fields` whitelist (line ~120+). Missing keys are silently dropped on save. Any controller method that writes derived files (e.g. `regenerateRobotsTxt`) must read the same keys.
3. **Plugin Service** (`plugins/system/aiboost_*/src/Service/*.php`) — runtime emitter reads from the settings array passed in. Must reference the SAME keys as the Vue model.

Symptom of misalignment: toggling a setting in the admin UI appears to save but has no effect on output. The audit found `robots_block_scrapers` (aggregate, used by service) vs `scraper_*` (per-bot, used by Vue) — two parallel models that never met. Fix: pick one canonical model (per-bot wins for UX), whitelist all per-bot keys, refactor both writers, add a one-shot migration in `pkg_script.php postflight` for legacy installs (idempotent: only run if legacy key present AND no new keys saved yet).

Audit checklist when touching a setting key:
- [ ] Vue `v-model` references the key
- [ ] Vue input has `:data-ab-field="key"` for Fix-It scroll
- [ ] Key is in `SettingsController::$fields` whitelist
- [ ] Every controller method that derives files reads the key
- [ ] Every plugin service that consumes the key uses the same name
- [ ] If renaming/replacing an old key, add idempotent migration in `pkg_script.php`
- [ ] Health registry has a corresponding `info_*` / `warning_*` entry in `HealthCheckService::CATEGORIES`

---

**L019 — Detect and prune orphan settings (task #375)**
A setting is an "orphan" when it is saved to the DB but never consumed by any plugin service or controller method — toggling it has zero observable effect. Orphans accumulate when a feature gets descoped but the UI control is left behind. Symptom: user picks an option and saves successfully, but nothing changes on the front-end.

Before adding a new setting, and during periodic audits, run this 4-step check for each key:

```bash
# 1. Vue model declaration (UI exists)
rg -n "v-model[^\"]*[\"\\.]<key>" component/com_aiboost/vue-admin/src/tabs/
# 2. Controller whitelist (save path)
rg -n "'<key>'" component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php
# 3. Plugin service consumers (the only proof the setting drives behaviour)
rg -n "<key>" component/plugins/system/
# 4. Health/Service consumers (cosmetic-only counts as orphan)
rg -n "<key>" component/lib/src/
```

If step 3 returns zero hits AND step 4 only reads the value to render a label, the setting is an orphan. Removal checklist:
- [ ] Delete the form control from the Vue tab
- [ ] Delete the key from `DEFAULTS` in `App.vue`
- [ ] Delete the key from `SettingsController::$fields` whitelist
- [ ] Delete any cosmetic Health check method + its call + its `CATEGORIES` entry
- [ ] Leave existing DB rows alone — the JSON blob will simply stop being updated for that key; old data is inert and harmless (no DROP COLUMN needed because settings are stored as JSON). Add a one-shot stripper in `pkg_script.php postflight` only if the key is large enough to bloat the row.
- [ ] Bump patch version; build; install on staging

Rationale: keeping dead controls in the UI erodes trust ("did my save work?") and creates support load. Removing them is a net UX win even when the underlying DB cleanup is skipped.

---

**L020 — Never emit empty wrapper comments (task #376)**
Debug wrap markers (`<!-- AI Boost: {plugin}/{block} START -->` … `END -->`) must never appear with an empty body between them. Empty pairs:
- pollute production HTML with developer-only artefacts
- mislead the DuplicateTagScanner and human inspectors into believing the feature is active when in fact it has emitted nothing
- misrepresent Cooperative-mode skips (where we deliberately stay silent)

Two structural sources of empty markers — both fixed in #376:

1. **Conditional bodies inside an unconditional wrap.** A block whose body has internal branches that may emit nothing (e.g. `ga4` when `consent === 'gtm'`; `google-verification` when both `gsc_codes` and `gsc_verification_code` are blank) was wrapped with markers before the body branch ran. Fix: build the entire body as a single `$body` string, then call `ConflictMarkerWrapper::wrap($plugin, $block, $body, $wrap)` (in `component/lib/src/`). The helper returns `''` when `trim($body) === ''`; caller short-circuits the `addCustomTag()` call.

2. **Markers around `setMetaData()` / `addHeadLink()`.** Those APIs write to the document's dedicated meta/link streams, NOT via `addCustomTag()`. Wrapping them with `addCustomTag('<!-- … START -->')` produces markers that are always adjacent with no body between them, because the actual `<meta>`/`<link>` tag renders elsewhere in the `<head>`. Two acceptable fixes:
   - **Fix A (preferred when you want a verifiable wrapper):** emit the tag as raw HTML via `addCustomTag('<meta name="…" content="…">')` so it lives in the same head stream as the wrapper comments. The whole block (markers + tags) then renders contiguously and `ConflictMarkerWrapper` can correctly suppress empty bodies. Pattern: build `$body = '<meta …>' . "\n" . '<link …>'`, then `$wrapped = ConflictMarkerWrapper::wrap('plugin', 'block', $body, $wrap); if ($wrapped !== '') $doc->addCustomTag($wrapped);`. Trade-off: you lose Joomla's automatic de-duplication of `<meta name="…">` keys, so add a Cooperative-mode `DocumentInspector::shouldSkip()` guard ahead of the emit when another extension is known to set the same name.
   - **Fix B (when you must use the meta/link stream):** do **not** call the helper at all for those blocks — simply omit the wrapper markers and add an inline comment noting why. Use this when the tag must merge with Joomla core's meta map (e.g. canonical link via `addHeadLink()` where downstream filters expect the dedicated stream).
   Task #377 (C1/C2) chose Fix A for `aiboost_aeo/ai-meta-tags` and `aiboost_aeo/markdown-discovery`; task #376 chose Fix B for `aiboost_perf/canonical` because its presence is consumed by other Joomla canonical-link consumers.

Audit checklist when adding or refactoring a wrap site:
- [ ] Does the block emit via `addCustomTag()`? If not, do not wrap.
- [ ] Is the entire block body buildable as a single string? If yes, use `ConflictMarkerWrapper::wrap()`.
- [ ] Does any internal branch produce an empty body? Trust the helper to short-circuit; never emit `addCustomTag(START)` unconditionally before the body.
- [ ] Verify on staging with `curl -s {site}/ | grep -A0 'AI Boost:'` — every START must have non-empty content before its matching END.

---

**L021 — One consolidated AI Boost head + body block, Yoast/GTM style (tasks #380, #382, #384)**
Every major modern SEO / analytics tool (Yoast, Rank Math, All in One SEO, Google Tag Manager, Meta Pixel) emits **one** clearly-labelled head block with a single outer START/END pair and short sub-section comments inside. AI Boost follows the same convention since v0.33.0; v0.34.0 (#384) inverted the default to "always verbose" (matches Yoast / GTM behaviour) and extended the pattern to `<body>` — GTM noscript, Meta Pixel noscript, custom body code, and custom footer code all consolidate into ONE AI Boost wrapper at the start of `<body>` and ONE just before `</body>`.

**Two render modes (v0.34.0+):**
- **Outer pair is ALWAYS minimal (v0.34.1+):** `<!-- AI Boost for Joomla - Start -->` / `<!-- AI Boost for Joomla - End -->`. No version, no URL — that information already lives in `<meta name="generator">` and would just bloat View Source if repeated on every wrapper. Same compact pair for head, body, and footer.
- **Default (`hide_comments` OFF — production-friendly + verbose, just like Yoast/GTM):** outer pair as above, then sub-section labels (`<!-- Schema.org -->`, `<!-- OpenGraph & Twitter -->`, `<!-- AEO -->`, `<!-- Analytics -->`, `<!-- Custom Code -->`, `<!-- Google Tag Manager (noscript) -->`, `<!-- Custom Body Code -->`, …), `<!-- Also emitted via Joomla head: … -->`, and `<!-- Skipped: … -->` lines all render inside. This is the experience site owners expect — they can see what each plugin is doing in View Source.
- **Hide comments (`hide_comments` ON — minimal source):** only the bare outer pair is emitted, with raw section bodies concatenated inside. No sub-section labels, no `Also emitted` / `Skipped` lines.

`debug_mode` and `hide_comments` are TWO INDEPENDENT toggles in the Debug tab:
- `debug_mode` → controls per-request `error_log` lines from each plugin; no front-end effect.
- `hide_comments` → controls HTML comment density (default 0 = verbose, 1 = minimal); no logging effect.

Any plugin can call `HeadBlockBuilder::setHideComments($hide)` and `BodyBlockBuilder::setHideComments($hide)` from `onBeforeCompileHead` — all read the same setting so last-write-wins is consistent. Call them BEFORE any early-return paths so skip-only contributions still respect the user's preference.

**The rules:**
1. No AI Boost plugin may call `$document->addCustomTag()` for `<head>` content directly. All head HTML flows through `AiBoost\Lib\HeadBlockBuilder::pushSection($section, $body)`.
2. No AI Boost plugin may `preg_replace` directly against `<body>` or `</body>`. All body/footer HTML flows through `AiBoost\Lib\BodyBlockBuilder::pushBody($label, $body)` / `pushFooter($label, $body)` (queued from `onBeforeCompileHead`, never from `onAfterRender` — push order matters for the consolidated wrapper, but finalize order does not).
3. Both builders splice into the rendered page via `onAfterRender` and are idempotent (static flag, first caller wins). All 6 AI Boost plugins call both `HeadBlockBuilder::finalize($app, Version::VERSION)` AND `BodyBlockBuilder::finalize($app)` in their `onAfterRender` — plugin order does not matter.
4. The orphan `ConflictMarkerWrapper` helper was deleted in #384 — body content now flows through `BodyBlockBuilder` which handles wrapper markers + hide-comments respect in one place.

**Fixed head sub-section order (do not reorder):**
1. Schema.org   (`SECTION_SCHEMA`)    — JSON-LD blocks, most important for SEO
2. OpenGraph & Twitter (`SECTION_SOCIAL`) — social-share meta tags
3. AEO          (`SECTION_AEO`)       — AI signals, markdown discovery
4. Analytics    (`SECTION_ANALYTICS`) — GSC/FB verification, GTM, GA4, Meta Pixel
5. Custom Code  (`SECTION_CODE`)      — user-supplied HTML, runs last so it can override

**Body / footer push order:** literal push order is preserved. Convention: analytics noscripts first (Google + Facebook specs want them right after `<body>`), then user-supplied Custom Body Code; footer typically only carries Custom Footer Code.

**Stays outside the block (by design):**
- `<link rel="canonical">` via `addHeadLink()` — Joomla and other extensions dedup the link stream
- `<link rel="alternate" hreflang>` via `addHeadLink()` — same reason
- Anything via `setMetaData()` — writes to dedicated meta map

Call `HeadBlockBuilder::noteNative('canonical')` (or `'hreflang (de)'`, `'title template'`, etc.) for each tag emitted through Joomla's native streams so the consolidated header comment can list it under `<!-- Also emitted via Joomla head: … -->`.

**Cooperative skips:** when `DocumentInspector::shouldSkip()` returns true, call `HeadBlockBuilder::noteSkip($section, 'reason')` so the user sees a `<!-- Skipped: OpenGraph & Twitter — already emitted by 4SEO -->` line inside the block instead of silent absence.

**Audit checklist for any new head/body-emitting plugin or refactor:**
- [ ] No `addCustomTag()` call for `<head>` content — only `HeadBlockBuilder::pushSection()`
- [ ] No direct `preg_replace` against `<body>` / `</body>` — only `BodyBlockBuilder::pushBody()` / `pushFooter()` (called from `onBeforeCompileHead`)
- [ ] Plugin's `onAfterRender()` calls BOTH `HeadBlockBuilder::finalize($app, Version::VERSION)` AND `BodyBlockBuilder::finalize($app)` (idempotent — safe to add to every plugin)
- [ ] `setHideComments($hide)` called on BOTH builders FIRST in `onBeforeCompileHead`, before any early-return path
- [ ] Cooperative-mode skip path calls `HeadBlockBuilder::noteSkip()`, never stays silent
- [ ] Any `addHeadLink()` / `setMetaData()` call is paired with `HeadBlockBuilder::noteNative('name')`
- [ ] In default mode, `curl -s {site}/` shows exactly ONE bare `<!-- AI Boost for Joomla - Start -->` in `<head>`, ONE after `<body>` (only if body content exists), and ONE before `</body>` (only if footer content exists). The outer pair must NEVER include version or URL — those go to `<meta name="generator">` only.
- [ ] In `hide_comments=1` mode, every wrapper still has the same bare outer pair but with zero inner labels (no sub-section labels, no Also-emitted, no Skipped)
- [ ] No fresh references to the removed `ConflictMarkerWrapper` class, `setDebug()` / `isDebug()` methods, or `debug_wrap_markers` setting in Vue, PHP, controllers, or docs

---

## Mandatory Step for Every Plugin/Component Task

At the end of every task that modifies plugin or component PHP code, add a final step:

> **Update Joomla skill** — If any new lessons were learned (new gotcha, new pattern, new script), add them to `.agents/skills/joomla-development/SKILL.md` under "Lessons Learned / Gotchas" with a task reference.

See also: `OPERATING.md` (global plan: codegen recipe, Pro-gating rule, Health rule, Definition of Done).

**L022 — Plugin rename requires pkg_script `postflight` cleanup (Task #438)** — When you change a Joomla plugin's slug (e.g. `aiboost_perf` → `aiboost_core`), Joomla's package installer does NOT automatically uninstall the old plugin. It happily ships both. You must add a `postflight()` step that: (1) `SELECT extension_id FROM #__extensions WHERE element='<old_slug>' AND type='plugin' AND folder='system'`; (2) read its `enabled` column and transfer it to the new plugin row; (3) call `(new \Joomla\CMS\Installer\Installer())->uninstall('plugin', $oldId)`. Wrap in try/catch and enqueueMessage on failure so users can clean up manually. Idempotent — no-op if the old row is absent. Settings stored in `#__aiboost_settings` (not plugin params) are unaffected by the slug change and need no migration.

**L023 — Styling a checkbox as an ON/OFF switch (Task #559)** — To turn a boolean `<input type="checkbox">` into a sliding green-ON / red-OFF switch purely in CSS, set `appearance:none` on the input and draw the knob with `::before` and the "ON"/"OFF" text with `::after`; `:checked` flips colour (`--ab-success` / `--ab-danger`) and slides the knob via `left:calc(100% - knob - gap)`. Scope the rule to `[type="checkbox"]` only — `CodeTab.vue` reuses the same `.ab-toggle__input` class on radios that MUST keep their native look. Pseudo-elements DO render on `appearance:none` checkboxes in all modern browsers, but only when the input is NOT also hidden by a sibling `:has()` rule — the existing `.ab-toggle__track` hide rule targets a different markup shape, so verify your switch input is followed by a `<label>`, not a `.ab-toggle__track`. Pure-CSS approach means manifest-generated Vue partials (`tabs/generated/*.vue`) and hand-written tabs are covered without touching markup, except Bootstrap `form-check-input` toggles which must be reclassed to `ab-toggle__input`.
