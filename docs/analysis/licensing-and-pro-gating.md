# Licensing & Free/Pro gating — full code-verified analysis

> **Status:** code-verified against the `refactor/structural` branch on 2026-06-28 (order 0013).
> Every claim below carries `file:line` evidence. This document is the **permanent record** — it is
> written to stand alone, so that the whole topic can be understood without re-reading the source or
> the order that produced it. Where it disagrees with `CLAUDE.md`, this document is newer and was
> checked line-by-line; the stale `CLAUDE.md` lines are called out explicitly in Part C.

---

## Sažetak za Bojana (na običnom jeziku)

Ovde je objašnjeno kako program zna da li je sajt **besplatan (Free)** ili **plaćeni (Pro)**, i šta se
zbog toga uključuje ili isključuje.

Ukratko: kad korisnik unese ispravan ključ i program ga jednom potvrdi kod našeg servera, sajt dobije
trajnu oznaku „ovo je Pro". Ta oznaka se **postavlja jednom i nikad se ne briše** — čak i kad ključ
kasnije istekne, Pro mogućnosti ostaju upaljene; istek samo zaustavi automatska ažuriranja i podršku.
Sve Pro mogućnosti (bogatija schema, napredni društveni tagovi, llms-full.txt, IndexNow…) zapravo se
proizvode unutar **besplatnih** dodataka, a uključuju se tek kad ta trajna oznaka postoji. U besplatnoj
verziji ti delovi koda **fizički ne postoje** — izbacuju se pri pakovanju — pa nema načina da neko dođe
do Pro-a bez plaćanja. To smo proverili.

Našao sam i nekoliko **stvarnih problema** koje treba da odlučiš (detaljno dole, sa oznakom „ODLUKA"):

- **ODLUKA 1 (najvažnije — rizik podrške):** kupac koji instalira Pro ali **ne unese ključ** dobija
  ekran koji izgleda kao besplatna verzija — nijedna Pro mogućnost se ne vidi niti radi dok ne ukuca
  ključ. To je tehnički ispravno, ali lako vodi do utiska „platio sam, a ne radi". Predlažem jasnu
  poruku/čarobnjak posle instalacije koji ga vodi da unese ključ.
- **ODLUKA 2 (čišćenje):** postoji **5 starih „Pro" dodataka** koji više ništa ne rade (prazni su) —
  pri Pro instalaciji se ionako sami brišu. Predlažem da ih obrišemo iz koda da ne zbunjuju.
- **ODLUKA 3 (sitna nedoslednost):** 4 podešavanja na društvenim mrežama su označena kao „besplatna",
  a u stvari ih koristi samo Pro deo — na besplatnom sajtu su mrtva (uključiš ih, ništa se ne desi).
  Predlažem da ih označimo kao Pro ili da ih u besplatnoj verziji ne prikazujemo.
- Tri manje stvari: stara „brava" za Pro (ProFeatureRegistry/ProGate) više ništa ne radi ali stoji u
  kodu i u uputstvu (CLAUDE.md) piše da radi — uputstvo je zastarelo; i jedno staro polje
  (`license_tier`) se i dalje upisuje iako ga ništa bitno ne čita.

Najvažnije: **nema rupe** kroz koju bi neko dobio Pro besplatno, i **nema situacije** da kupac koji je
jednom uneo ključ izgubi Pro (osim te jedne: ako ključ nikad nije uneo). Detalji i tačne lokacije u
kodu su ispod (na engleskom).

---

## Executive summary (English)

AI Boost is a **one-product** package with a **perpetual-activation** Pro gate. The entire Free/Pro
decision reduces to **one boolean flag** in the settings blob:

```
pro_activated === '1'   →  Pro   (forever)
otherwise               →  Free
```

resolved by **`PluginRegistry::isProActive(array $settings)`** ([component/lib/src/PluginRegistry.php:375-378](../../component/lib/src/PluginRegistry.php#L375-L378)).
Three layers all delegate to that single function so they can never drift:

1. **Runtime emitters** — every Pro front-end feature is produced *inside a Free plugin*, guarded by
   `class_exists(ProClass) && PluginRegistry::isProActive($settings)`.
2. **Admin UI** — the Vue bootstrap `isPro` flag (`HtmlView::buildBootstrap`) drives every `<ProGate>`.
3. **Settings-save** — `SettingsController::isProSetting()` derives the same value.

Pro is unlocked the first time a real key verifies **active** against our own licence server
(`api.aiboostnow.com`, store-pinned + product-pinned). At that moment
`PluginRegistry::markPerpetualActivation()` sets `pro_activated='1'` **once and never clears it**;
licence expiry only pauses updates/support, it never relocks features.

Two distinct "is this Pro?" signals exist and must not be confused:

| Signal | Source | Meaning | Drives |
|---|---|---|---|
| **`isProActive($settings)`** | `pro_activated` flag | "Is Pro **functionally** ON right now?" | runtime emitters, `<ProGate>`, save gate |
| **`isProInstall()`** | `pro_installed` marker / activation / legacy split rows | "Is this a Pro **install**?" | keeps the **Licenses page reachable** so a paying customer can enter their key |

The Free vs Pro **build** is physical: the Free ZIP has all Pro Service/Feature classes removed
(`FREE_EXCLUDE` whole-file omission + `// @pro:start/@pro:end` block stripping), enforced by a STRICT
leakage verifier with no escape hatch. Integration SKUs (`int_falang`, `int_yootheme`) are licensed
**independently** of the core bundle (separate Lemon Squeezy products, product-pinned both ways).

All seven first-round findings are **confirmed** (with two of them stronger than originally stated).
The catalogue of problems and decisions is in **Part C**.

---

# Part A — How it works, end to end

## A1. The one canonical gate — `pro_activated`

`PluginRegistry::isProActive()` is the single source of truth for non-per-SKU Pro behaviour:

```php
// component/lib/src/PluginRegistry.php:375-378
public static function isProActive(array $settings): bool
{
    return self::settingEnabled($settings, 'pro_activated');   // ($settings['pro_activated'] ?? '0') === '1'
}
```

The class docblock ([PluginRegistry.php:349-374](../../component/lib/src/PluginRegistry.php#L349-L374)) states the contract verbatim:
> *"A Pro install behaves exactly like Free until a licence key is verified active **once**; from then
> on the permanent `pro_activated` flag keeps Pro unlocked **forever**, even after the licence expires.
> Expiry only stops automatic updates + support … it never relocks features. … NEVER gate on
> `license_tier`, on the current `license_state` status, or on the heartbeat — those drift on expiry
> and were the source of the recurring relock bugs this model removes."*

`settingEnabled()` ([PluginRegistry.php:428-431](../../component/lib/src/PluginRegistry.php#L428-L431)) does the strict string compare `=== '1'`.

## A2. Activation path — key → verify → perpetual flag

The real activation flow (the only path that sets `pro_activated`) is the **Licenses tab AJAX endpoint**:

1. **`SettingsController::verifyLicense()`** ([SettingsController.php:1817-1858](../../component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php#L1817-L1858))
   reads `sku` + `license_key`, validates the SKU against `LICENSE_SKUS`, and for an `int_*` SKU
   **fails closed** if its product pin is unconfigured ([SettingsController.php:1841-1848](../../component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php#L1841-L1848)).

2. **`resolveLicenseState()`** ([SettingsController.php:1964-1978](../../component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php#L1964-L1978))
   chooses the product pin: core SKUs are pinned to the **set** of three core tier products, integration
   SKUs to their single product:
   ```php
   $expectedProductId = str_starts_with($sku, 'int_')
       ? LicenseValidator::expectedProductId($sku)        // single int product
       : LicenseValidator::expectedCoreProductIds();      // [1126398, 1126399, 1126400]
   return LicenseValidator::verify($key, $instanceName, $instanceId, $expectedProductId);
   ```

3. **`LicenseValidator::verify()`** ([LicenseValidator.php:299-443](../../component/lib/src/LicenseValidator.php#L299-L443))
   calls our server (`/validate` an existing instance, else `/activate` a new one — [L340-358](../../component/lib/src/LicenseValidator.php#L340-L358)),
   then **fails closed** on: missing store pin ([L331-335](../../component/lib/src/LicenseValidator.php#L331-L335)), foreign/absent store ([L390-397](../../component/lib/src/LicenseValidator.php#L390-L397)),
   wrong/absent product when a pin is supplied ([L406-416](../../component/lib/src/LicenseValidator.php#L406-L416)). Only `valid===true && license_key.status==='active'`
   yields `status='active'` ([L427-429](../../component/lib/src/LicenseValidator.php#L427-L429)).

4. **`PluginRegistry::saveLicenseState($sku, $state)`** ([PluginRegistry.php:533-549](../../component/lib/src/PluginRegistry.php#L533-L549))
   stores the per-SKU record, recomputes `coreLicenseActive()`, sets the back-compat `license_tier`,
   and calls `markPerpetualActivation()`.

5. **`markPerpetualActivation()`** ([PluginRegistry.php:599-609](../../component/lib/src/PluginRegistry.php#L599-L609))
   is the **write-once** step:
   ```php
   if (!$anyActive || (string) ($data['pro_activated'] ?? '0') === '1') {
       return;                       // already set, or nothing active → no-op
   }
   $data['pro_activated'] = '1';     // set ONCE
   $data['pro_activated_at'] = gmdate('c');         // first time only
   $data['pro_activated_version'] = \AiBoost\Version::VERSION;
   ```
   There is **no code path anywhere that sets `pro_activated` back to `'0'`** — confirmed by the
   whole-repo write inventory in A8.

`coreLicenseActive()` ([PluginRegistry.php:583-594](../../component/lib/src/PluginRegistry.php#L583-L594)) is the **anti-leak hinge**:
it walks the per-SKU `license_state` map but **skips every `int_*` key**, so activating an integration
licence can never set `pro_activated` (which would unlock the whole core bundle for free) nor flip
`license_tier` to `'pro'`.

There is a **second, server-driven** way `pro_activated` is set — **`LicenseReconcile`**
([component/lib/src/LicenseReconcile.php](../../component/lib/src/LicenseReconcile.php), `$data['pro_activated']='1'` at line ~211),
which recovers Pro for a lapsed past-purchaser by server-matching the bound, unguessable `install_id`.
This is the no-false-positive recovery anchor (domain alone is spoofable, so it is never sufficient).

## A3. Persistence & client-write protection

`pro_activated` (and the rest of the licence/identity state) lives in the single JSON blob
`#__aiboost_settings` row `setting_key='main'`. It is protected from client writes by
**`SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS`** ([SettingsSaveDefinition.php:42-62](../../component/lib/src/SettingsSaveDefinition.php#L42-L62)):

```php
public const SYSTEM_PRESERVED_KEYS = [
    'license_key', 'license_tier', 'license_state', 'license_heartbeat',
    'license_reconcile', 'license_simulation',
    'pro_activated', 'pro_activated_at', 'pro_activated_version', 'pro_skus',
    'pro_installed',                       // edition marker — package script only
    'install_id', 'last_backup_at',
    'dev_license_preview', 'dev_force_free_tier',   // DB-only QA overrides
];
```

**`mergeSystemPreservedKeys()`** ([SettingsSaveDefinition.php:240-250](../../component/lib/src/SettingsSaveDefinition.php#L240-L250))
is **fail-closed in both directions**: every preserved key is overwritten from the existing row (so a
client POSTing `pro_activated=1` cannot self-promote a Free install), and a preserved key absent from
the existing row is **unset** from the payload (so it can never be introduced through Settings save).
`ImportController::IMPORT_DENYLIST` is built on the same constant so the save and import lists cannot
drift. (The legacy import field `jb_is_paid → license_tier` ([ImportController.php:332](../../component/com_aiboost/admin/src/Administrator/Controller/ImportController.php#L332))
is therefore inert on import — `license_tier` is on the denylist.)

The blob is a **full-replace** row; all writers use read-modify-write or a full snapshot
(`persistMainSettings()` [PluginRegistry.php:628-638](../../component/lib/src/PluginRegistry.php#L628-L638)),
a contract enforced project-wide by `SettingsWriterRmwContractTest`.

## A4. Runtime gate consumers — every Pro emitter

Every Pro **front-end feature is produced inside the corresponding FREE plugin**, behind the same
`class_exists(ProClass) && isProActive($settings)` guard. The Pro class only exists on disk in the Pro
build (see A6), so the guard is true only when **both** the code is present **and** the install is
activated.

| Free plugin | Pro class instantiated | Gate (file:line) |
|---|---|---|
| `aiboost_schema` | `SchemaProBuilder` | `class_exists() && isProActive()` — [AiBoostSchema.php:127](../../component/plugins/system/aiboost_schema/src/Extension/AiBoostSchema.php#L127) |
| `aiboost_social` | `OgTagProDecorator` | `class_exists() && isProActive()` — [AiBoostSocial.php:129](../../component/plugins/system/aiboost_social/src/Extension/AiBoostSocial.php#L129) |
| `aiboost_aeo` | `LlmsTxtProGenerator` (virtual routes) | `class_exists() && isProActive()` — [AiBoostAeo.php:79](../../component/plugins/system/aiboost_aeo/src/Extension/AiBoostAeo.php#L79) |
| `aiboost_aeo` | `LlmsTxtProGenerator` (/llms.txt enrich) | `class_exists() && isProActive()` — [AiBoostAeo.php:164](../../component/plugins/system/aiboost_aeo/src/Extension/AiBoostAeo.php#L164) |
| `aiboost_aeo` | X-Robots-Tag header | `isProActive()` only — [AiBoostAeo.php:219](../../component/plugins/system/aiboost_aeo/src/Extension/AiBoostAeo.php#L219) |
| `aiboost_aeo` | `IndexNowService` (publish) | `class_exists() && isProActive()` — [AiBoostAeo.php:279](../../component/plugins/system/aiboost_aeo/src/Extension/AiBoostAeo.php#L279) |
| `aiboost_aeo` | `IndexNowService` (state change) | `class_exists() && isProActive()` — [AiBoostAeo.php:311](../../component/plugins/system/aiboost_aeo/src/Extension/AiBoostAeo.php#L311) |
| `aiboost_sitemap` | sitemap index / image / news | `isProActive()` via `$this->isPro()` — [AiBoostSitemap.php:599-604](../../component/plugins/system/aiboost_sitemap/src/Extension/AiBoostSitemap.php#L599-L604) |
| `aiboost_sitemap` | `HreflangSitemapExtension` | `isPro() && hasPro('int_falang')` — [AiBoostSitemap.php:383-385](../../component/plugins/system/aiboost_sitemap/src/Extension/AiBoostSitemap.php#L383-L385) |

`aiboost_core`, `aiboost_code`, `aiboost_analytics` produce **no Pro-gated output** — they are Free-only
infrastructure. (Custom Code is "Pro" only as a UI-section concept in the legacy registry; the
`aiboost_code` plugin itself ships no licence gate.)

Note the **cross-cutting rule**: translated schema/OG and sitemap hreflang additionally require the
Multilang integration licence (`hasPro('int_falang')`), e.g. [AiBoostSchema.php:134](../../component/plugins/system/aiboost_schema/src/Extension/AiBoostSchema.php#L134),
[AiBoostSocial.php (TranslationService)](../../component/plugins/system/aiboost_social/src/Extension/AiBoostSocial.php#L129). Core Pro alone does **not** unlock per-language output.

## A5. Integration (`int_*`) licensing — independent and **non-perpetual**

Integration SKUs are gated by a **different, independent** path — `hasPro('int_*')`:

```php
// component/lib/src/PluginRegistry.php:285-347
public static function hasPro(string $sku): bool {
    if (str_starts_with($sku, 'int_')) {
        return self::hasIntegrationPro($sku);          // independent of core bundle
    }
    // core SKUs delegate to the SAME signal as isProActive() (pro_activated)
    ...
}
private static function hasIntegrationPro(string $sku): bool {
    return self::resolveRealStatus(self::loadLicenseStates()[$sku] ?? null) === 'active';
}
```

Two consequences, both confirmed in code:

1. **Independence (anti-leak, both directions).** Buying YOOtheme Pro or Multilang never sets
   `pro_activated` (because `coreLicenseActive()` skips `int_*`, A2), and a core-bundle buyer does not
   get the integrations for free (product pinning on `verify()`, A2/A9). The two integration plugins
   gate **all** their runtime emission on `hasPro('int_<key>')`:
   - `aiboost_int_falang` → `proOn()` → `hasPro('int_falang')` ([AiBoostIntFalang.php:198-208](../../component/plugins/system/aiboost_int_falang/src/Extension/AiBoostIntFalang.php#L198-L208)); Multilang is **pure Pro, no free floor** — the Free ZIP is a stripped discovery shell.
   - `aiboost_int_yootheme` → `proOn()` → `hasPro('int_yootheme')` ([AiBoostIntYootheme.php:156-167](../../component/plugins/system/aiboost_int_yootheme/src/Extension/AiBoostIntYootheme.php#L156-L167)); its **OG-override is Free** (`bridgeOn()`), only schema/FAQ/gallery/sitemap-exclusion are Pro.

2. **Asymmetry with core — integrations are NOT perpetual.** `hasIntegrationPro()` resolves the **live**
   status via `resolveRealStatus()` ([PluginRegistry.php:669-707](../../component/lib/src/PluginRegistry.php#L669-L707)),
   which returns `'expired'` once `expires_at` has passed ([L689-702](../../component/lib/src/PluginRegistry.php#L689-L702)).
   So an **integration relocks when its licence expires**, whereas core Pro (`pro_activated`) stays on
   forever. This is a deliberate-looking design split, but it is an **undocumented asymmetry** worth a
   conscious decision (see Part C, P7).

## A6. Free vs Pro **component build** — physical separation

The split is physical, not just runtime. `scripts/build-package-zip.py`:

- **Whole-file omission** — `FREE_EXCLUDE` ([build-package-zip.py:67-83](../../scripts/build-package-zip.py#L67-L83)) lists every Pro Service/Feature
  class removed from the Free ZIP: `SchemaProBuilder`, `BusinessHoursBuilder`, `SiteTypePresetService`,
  `BreadcrumbPro` (schema); `OgTagProDecorator`, `CustomFieldReader` (social); `LlmsTxtProGenerator`,
  `IndexNowService`, `RobotsBotRules` (aeo). Applied only when `strip_pro=True` ([L237](../../scripts/build-package-zip.py#L237)).
- **Block stripping** — `// @pro:start … // @pro:end` blocks are removed from every `.php`
  (`_PRO_BLOCK_RE` [L146-149](../../scripts/build-package-zip.py#L146-L149); `strip_pro_blocks()` [L171-181](../../scripts/build-package-zip.py#L171-L181)). An unbalanced
  opener truncates to EOF on purpose so a half-typed marker fails loud.
- **STRICT verifier, no escape hatch** — after the Free build, `verify-no-pro-leakage.py` runs in
  STRICT mode and aborts on any leaked token ([build-package-zip.py:810-825](../../scripts/build-package-zip.py#L810-L825)); its token list includes
  `@pro`, `ProGate::`, `use AiBoost\Lib\ProGate;`, `isProEnabled(`, and the old
  `licenseStatus('…')==='active'` pattern (`verify-no-pro-leakage.py` PRO_TOKENS).

**The Pro edition build is a critical nuance.** For `--target pro`, `main()` calls
**`build_package_zip(pro_edition=True)`** ([build-package-zip.py:786-791](../../scripts/build-package-zip.py#L786-L791)), which produces
`pkg_aiboost_pro-<v>.zip` but with the **SAME package element `aiboost`** (so it upgrades a Free base
**in place**), the **same 7 core plugins built FULL** (no `@pro` strip, `FREE_EXCLUDE` not applied —
[L471-475](../../scripts/build-package-zip.py#L471-L475)), `(Pro)` display names, the licence-gated Pro update feed, and **`pkg_script.php` with
`IS_PRO_EDITION=true`** ([L528-534](../../scripts/build-package-zip.py#L528-L534)). The inline comment is explicit
([L790](../../scripts/build-package-zip.py#L790)): *"Replaces the old decorator-only add-on package (pkg_aiboost_pro)."*

⚠️ The legacy split-package path — `build_pro_package_zip()` ([L578-596](../../scripts/build-package-zip.py#L578-L596)) which builds the 5
`PRO_PLUGIN_NAMES` decorator ZIPs and writes `pkg_script_pro.php` — **is never called by `main()`**. It
is orphaned build code (see Part C, P4).

## A7. Free vs Pro **plugin model** — the 5 `*_pro` plugins are dead weight

The five `*_pro` decorator plugins are **dormant no-ops** confirmed by reading each class:

| Plugin | State | Evidence |
|---|---|---|
| `aiboost_schema_pro` | dormant no-op `onAfterInitialise()` (boots lib, returns) | [AiBoostSchemaPro.php:34-43](../../component/plugins/system/aiboost_schema_pro/src/Extension/AiBoostSchemaPro.php#L34-L43) |
| `aiboost_social_pro` | dormant no-op | [AiBoostSocialPro.php:33-41](../../component/plugins/system/aiboost_social_pro/src/Extension/AiBoostSocialPro.php#L33-L41) |
| `aiboost_aeo_pro` | dormant no-op (+ explicit double-fire guard comment) | [AiBoostAeoPro.php:39-47](../../component/plugins/system/aiboost_aeo_pro/src/Extension/AiBoostAeoPro.php#L39-L47) |
| `aiboost_code_pro` | skeleton (boot + empty `onAiBoostRegisterFields()`) | [AiBoostCodePro.php:39-45,94-101](../../component/plugins/system/aiboost_code_pro/src/Extension/AiBoostCodePro.php#L39-L45) |
| `aiboost_hreflang_pro` | skeleton (boot + empty field reg) | [AiBoostHreflangPro.php:39-45,94-101](../../component/plugins/system/aiboost_hreflang_pro/src/Extension/AiBoostHreflangPro.php#L39-L45) |

Their Pro logic was relocated into the Free plugins during the "Pro replaces Free" collapse (see each
class's comment). Stronger than the first-round claim: the **combined Pro install actively uninstalls
them**. `pkg_script.php` Phase 6.5 `sweepCollapsedProDecorators()` ([pkg_script.php:637-714](../../component/package/pkg_script.php#L637-L714))
detaches + row-deletes all five decorator rows and the old `pkg_aiboost_pro` package row, gated by
`IS_PRO_EDITION`. The method comment names them *"dead weight"* and says *"a Pro site should show ONLY
the 7 single elements."* So a current Pro site never runs these plugins; they survive only in the repo
and in the orphaned `build_pro_package_zip()` path.

## A8. The install-edition marker `pro_installed` and the UI unlock

`pro_installed` answers a different question from `pro_activated`: **"is this a Pro install?"** (vs "is
Pro functionally on?"). It is written **only** by the package installer:

- **Written:** `pkg_script.php` `setInstalledEdition()` → `$data['pro_installed'] = $isProBuild ? '1' : '0'`
  (≈ [pkg_script.php:1429](../../component/package/pkg_script.php#L1429)), called from postflight Phase 6 (≈ L193). Free build → `'0'`, Pro
  edition (IS_PRO_EDITION=true) → `'1'`.
- **Never** written via form/import (it is in `SYSTEM_PRESERVED_KEYS`, A3).

The admin UI consumes **two** signals from the bootstrap ([HtmlView.php:164-277](../../component/com_aiboost/admin/src/Administrator/View/App/HtmlView.php#L164-L277)):

```php
$isPro = PluginRegistry::isProActive($settings);   // L204 — functional gate → <ProGate>
...
'isPro'        => $isPro,                            // L255
'isProInstall' => $this->detectProInstall(),        // L264 → PluginRegistry::isProInstall() (L91)
```

`isProInstall()` ([PluginRegistry.php:397-423](../../component/lib/src/PluginRegistry.php#L397-L423)) is true when **any** of:
(1) `pro_installed==='1'`, (2) `isProActive()`, or (3) a legacy split layout
(`pkg_aiboost_pro` package row or any `aiboost_%_pro` plugin row) is physically present.

**This is the heart of finding #5.** On a fresh Pro install **before any key is entered**:
`pro_installed='1'`, `pro_activated='0'` ⇒ `isProInstall = true` (Licenses page + Pro controls
reachable) but `isPro = false` (every `<ProGate>` locks, every emitter is silent). The customer sees a
Free-looking product whose only Pro affordance is the Licenses page. Intentional per the design
comment ([pkg_script.php ≈1415-1418](../../component/package/pkg_script.php#L1415)), but a real support/UX risk.

## A9. The licence authority server

`LicenseValidator` talks to **our own server**, not Lemon Squeezy directly:
`https://api.aiboostnow.com/api/license/{validate,activate}` ([LicenseValidator.php:40-41](../../component/lib/src/LicenseValidator.php#L40-L41)).
The server returns the **same envelope** Lemon Squeezy would (`valid`, `license_key.status`,
`meta.store_id`, `meta.product_id`, `instance.id`), so the existing pinning works unchanged. Pinning
constants ([LicenseValidator.php:60-104](../../component/lib/src/LicenseValidator.php#L60-L104)):

- `EXPECTED_STORE_ID = 367944` — store pin (fail-closed while null).
- `EXPECTED_CORE_PRODUCT_IDS = [1126398, 1126399, 1126400]` — the PRO 3-site / PRO+ 10-site /
  Unlimited tiers; **any one** unlocks the same core bundle (the plugin never counts sites).
- `EXPECTED_PRODUCT_IDS = ['int_yootheme'=>1138446, 'int_falang'=>1138396]` — per-integration pins.

All failure modes (no transport, network error, empty/malformed body, foreign store, wrong product,
unconfigured pin) resolve to a **non-active** status → Pro never unlocks without a confirmed live
licence from our store. The token-substitution update mechanism (`{LICENSE_KEY}`/`{SITE_DOMAIN}`/
`{CURRENT_VERSION}`) is wired in `AiBoostCore` + `SettingsController::fillUpdateDownloadKey()`
([SettingsController.php:1920-1959](../../component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php#L1920-L1959)).

---

# Part B — First-round findings: verified

Each first-round finding from the order, checked against code (✅ confirmed / ✏️ corrected/expanded):

| # | First-round finding | Verdict | Evidence |
|---|---|---|---|
| — | The gate = perpetual `pro_activated`, set once by `markPerpetualActivation()`, never cleared; `isProActive()` reads it; in `SYSTEM_PRESERVED_KEYS`; `LicenseValidator` fail-closed on store 367944 + core IDs | ✅ **confirmed** | A1, A2, A3, A9 |
| — | Free vs Pro **component**: Pro classes physically stripped (`@pro` + `FREE_EXCLUDE`) | ✅ **confirmed** | A6 |
| — | Free vs Pro **plugins**: 5 `*_pro` are dormant no-ops; all Pro output inside Free plugins gated by `class_exists && isProActive`; `int_*` gated separately; `coreLicenseActive()` ignores `int_*` | ✅ **confirmed, stronger** — the 5 are not just dormant, they are **actively swept (uninstalled)** on Pro install | A4, A5, A7 |
| 1 | Dead `ProGate` trait (`isProEnabled()` never called) | ✅ **confirmed** | P1 below |
| 2 | Dead `ProFeatureRegistry` (`stripLocked()`/`stripProOptions()` no-ops) but CLAUDE.md still claims they enforce the gate | ✅ **confirmed** | P2 below |
| 3 | `license_tier` written but read only by `mod_aiboost_health` (drift) | ✏️ **confirmed + corrected** — also read by `HealthCheckService` (diagnostic) and the two **dead** legacy readers; **no runtime gate** reads it; and `mod_aiboost_health` is **not currently shipped** | P3 below |
| 4 | The 5 dormant `*_pro` plugins = dead weight | ✅ **confirmed, stronger** — plus the **orphaned `build_pro_package_zip()` + `pkg_script_pro.php`** that still build/install them are dead code never called by `main()` | P4 below |
| 5 | Pro install sets `pro_installed` but NOT `pro_activated` → a paying customer who hasn't verified a key sees ZERO Pro output | ✅ **confirmed** | A8, P5 below |
| 6 | 4 tier-mismatched keys marked Free but consumed only in Pro code | ✅ **confirmed** | P6 below |
| 7 | Integration (Falang/YOOtheme) licensing model needs a clear written explanation | ✅ **done** | A5; plus the non-perpetual asymmetry P7 |

---

# Part C — Problems & decisions

Severity key: **S1** = customer-facing/revenue risk · **S2** = correctness/maintenance · **S3** = cosmetic/doc.

### P1 — Dead `ProGate` trait — S3 (maintenance)
**What.** The `ProGate` trait ([ProGate.php](../../component/lib/src/ProGate.php)) still defines `isProEnabled()` ([L44-47](../../component/lib/src/ProGate.php#L44-L47)),
`validateAndStoreLicense()`, `storeLicenseTier()` (writes `license_tier` into **plugin params**), and an
`onExtensionAfterSave()` hook. **No shipped plugin uses the trait.** Whole-`component/` search for
`isProEnabled(` / `use …ProGate;` returns only the trait's own definition + tests; the only
`onExtensionAfterSave` in a plugin is `aiboost_sitemap`'s own ping handler ([AiBoostSitemap.php:181](../../component/plugins/system/aiboost_sitemap/src/Extension/AiBoostSitemap.php#L181)),
unrelated to the trait. The leakage verifier even scans for `isProEnabled(` as a banned token, so a
shipped use would fail the build — strong proof it is dead.
**Why it matters.** Misleads readers into thinking per-plugin params gating is live; it isn't.
**ODLUKA 4 (recommended fix).** Delete `ProGate.php` (and the now-pointless `validateAndStoreLicense`
path) once confirmed no add-on bundles it. Until then, leave the banned-token verifier as the guard.

### P2 — Dead `ProFeatureRegistry` save-gate, but CLAUDE.md says it enforces — S2/S3
**What.** `ProFeatureRegistry::stripLocked()` ([ProFeatureRegistry.php:309-312](../../component/lib/src/ProFeatureRegistry.php#L309-L312)),
`stripProOptions()` ([L149-152](../../component/lib/src/ProFeatureRegistry.php#L149-L152)), `proOptions()` ([L124-127](../../component/lib/src/ProFeatureRegistry.php#L124-L127)) and
`lockedSettingsKeys()` ([L281-284](../../component/lib/src/ProFeatureRegistry.php#L281-L284)) are **compatibility no-ops** — `stripLocked()`/`stripProOptions()`
return the payload unchanged; `proOptions()`/`lockedSettingsKeys()` return `[]`. They are still **called**
from `SettingsController::save()` ([L141-158](../../component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php#L141-L158)), so the call sites are dead-but-present.
**Why it matters.** `CLAUDE.md` (Pro-gating section) still states ProFeatureRegistry *"drives … server-side
`stripLocked()` on save"* — **stale and wrong**. The real save-side protection is
`mergeSystemPreservedKeys()` (A3) plus the physical Free build (A6), not `stripLocked()`.
**ODLUKA 5 (recommended fix).** Update `CLAUDE.md` to describe the perpetual-activation gate as the
single mechanism; either delete the no-op methods + their call sites, or keep `ProFeatureRegistry`
**only** as the historical SKU→surface map it now is (it is still legitimately consumed by Health/diagnostics
for labels) and rename its docblock to say "no longer enforces saves".

### P3 — `license_tier` is a back-compat field with no runtime reader — S2
**What.** `license_tier` is **written** by `saveLicenseState()` ([PluginRegistry.php:542](../../component/lib/src/PluginRegistry.php#L542)) as a
back-compat materialisation. Its **live readers** are diagnostic/cosmetic only:
- `HealthCheckService` ([L794](../../component/lib/src/HealthCheckService.php#L794), [L2569](../../component/lib/src/HealthCheckService.php#L2569), [L2689](../../component/lib/src/HealthCheckService.php#L2689)) — an `info_license_tier`
  row + a "legacy `license_tier` detected" warning.
- `mod_aiboost_health` ([mod_aiboost_health.php:77](../../component/modules/mod_aiboost_health/mod_aiboost_health.php#L77)) — **but this module is not currently shipped**
  (`MODULE_NAMES = []`, [build-package-zip.py:90](../../scripts/build-package-zip.py#L90)).
- `HtmlView.php:185` reads it for display only, with a comment that it does **not** gate on it.
- Two **dead** legacy readers: `ProGate::isProEnabled()` (P1) and `AbstractService::isProTier()` /
  `getLicenseTier()` ([AbstractService.php:35-55](../../component/lib/src/AbstractService.php#L35-L55)) — defined, only self-referenced, no external caller
  (a contract test, `EmissionProGateSourceContractTest`, asserts emitters do **not** use them).
**Why it matters.** No correctness bug today (nothing gates on it), but it is drift: a written field that
no runtime path consumes invites a future relock bug if someone "re-uses" it. The first-round "read only
by `mod_aiboost_health`" was close but imprecise.
**ODLUKA 6 (recommended fix).** Keep writing it only as long as Health surfaces it; otherwise drop the
write and the Health rows. If kept, document it as **diagnostic-only, never a gate** (mirror the
`PluginRegistry` docblock that already says "NEVER gate on `license_tier`").

### P4 — 5 dormant `*_pro` plugins + their orphaned build/install path — S2
**What.** The 5 `*_pro` plugin directories are dormant no-ops (A7) and are **actively uninstalled** on a
Pro install (`sweepCollapsedProDecorators()`, [pkg_script.php:637-714](../../component/package/pkg_script.php#L637-L714)). Separately,
`build_pro_package_zip()` ([build-package-zip.py:578-648](../../scripts/build-package-zip.py#L578-L648)) + `pkg_script_pro.php` still build and install
these decorators into a legacy split `pkg_aiboost_pro` package — but **`main()` never calls that
function** (A6); the current Pro edition is the combined same-element build.
**Why it matters.** ~5 plugin trees + a whole build function + an installer script that nothing ships =
maintenance noise and a real risk someone "fixes" the dead path. The skeletons do still satisfy the
per-SKU `PluginRegistry::PRO_SKUS` scan, so deletion needs care (the registry's `pro_*` capability rows
would always report `installed=false` afterward — confirm nothing depends on those rows for a non-cosmetic
decision; today they feed the Licenses/Integrations dashboards' "installed" badges only).
**ODLUKA 2 (recommended fix).** Delete the 5 `*_pro` directories, `build_pro_package_zip()`,
`PRO_PLUGIN_NAMES`, and `pkg_script_pro.php` / `pkg_aiboost_pro.xml` once it is confirmed no live install
relies on the split layout; **keep** `sweepCollapsedProDecorators()` (it cleans up sites that still have
the old rows). This is a follow-up implementation order, not part of this read-only analysis.

### P5 — Pro install with no key entered looks like Free — **S1 (the important one)**
**What.** Confirmed in A8: `pro_installed='1'` + `pro_activated='0'` ⇒ `isProInstall=true` (Licenses
reachable) but `isPro=false` (all `<ProGate>` locked, all emitters silent). A paying customer who installs
Pro and never enters their key gets a Free-looking product.
**Why it matters.** Direct "I paid and it doesn't work" support/refund risk. It is the single most
likely real-world complaint. (It is *intentional* fail-closed behaviour, not a bug — but the UX around it
is the gap.)
**ODLUKA 1 (recommended fix — pick one or combine).**
(a) Post-install/first-run banner or wizard step that detects `isProInstall && !isProActive` and routes
the admin straight to the Licenses tab with a clear "Enter your key to switch on Pro" call-to-action;
(b) a persistent dashboard notice in that state; (c) optionally, if the install ZIP can carry the key
(download-key flow), pre-seed activation. The cleanest is (a)+(b). **Needs Bojan's decision on wording +
whether to add the wizard step.**

### P6 — 4 "Free" social keys consumed only by Pro code — S2 (dead-on-Free UX)
**What.** `enable_per_article_fields`, `enable_article_og_type`, `fb_app_id`, `twitter_site_handle` are
declared **`tier='free'`** in the manifest ([Manifest/og.php:45,50,60,70](../../component/lib/src/Manifest/og.php#L45)) — asserted `'free'` by
`SettingsSaveDefinitionTest` ([L269-272](../../component/tests/Lib/SettingsSaveDefinitionTest.php#L269-L272)) — shown in the Free Social UI
([SocialTab.vue:84-176](../../component/com_aiboost/vue-admin/src/tabs/SocialTab.vue#L84) + generated partials), saveable on any tier, default `'1'`. But their
**only consumer is `OgTagProDecorator`** ([OgTagProDecorator.php:131,192,211,216](../../component/plugins/system/aiboost_social/src/Service/OgTagProDecorator.php#L131)) — a Pro class that is
`FREE_EXCLUDE`-stripped from the Free build **and** `isProActive`-gated. So on a Free install these four
controls are **orphans**: the user toggles them and nothing happens.
**Why it matters.** Erodes trust ("did my save work?") and contradicts the orphan-prevention discipline
(SKILL L019). It is the inverse of a Pro leak — not a security issue, a UX one.
**ODLUKA 3 (recommended fix — pick one).** (a) Reclassify the four to `tier='pro'` in the manifest + run
codegen so they show as Pro-gated, OR (b) hide them on Free installs. (a) is the smaller, more honest
change. **Needs Bojan's decision.**

### P7 — Core Pro is perpetual, integration Pro is not — S2/S3 (undocumented asymmetry)
**What.** Core Pro never relocks (`pro_activated`, A1). Integration Pro **does** relock on licence expiry
because `hasIntegrationPro()` reads the live `resolveRealStatus()` which returns `'expired'` past
`expires_at` (A5). Both are reasonable on their own; the asymmetry is just nowhere documented and could
surprise (a customer whose Multilang key lapses loses hreflang output, while their core Pro stays on).
**Why it matters.** Not a bug, but a support-clarity gap and a possible future "why did my hreflang
disappear?" ticket.
**ODLUKA 7 (decision).** Confirm the intended behaviour: should integrations also be perpetual-on-activate
like core, or stay yearly-live? Document whichever is chosen (in OPERATING.md + the website pricing copy).

### P8 — `CLAUDE.md` Pro-gating section is stale — S3 (doc)
**What.** Beyond P2, the `CLAUDE.md` "Pro gating" paragraph mixes the current model with retired claims
(it correctly states the single-flag `pro_activated` gate, but still credits `ProFeatureRegistry`'s
`stripLocked()` and server-side enforcement). The `dev_license_preview`/`dev_force_free_tier` note is
accurate (removed from the gate, kept on the denylist).
**ODLUKA (folds into 5).** Refresh that paragraph to match Part A of this document.

---

# Part D — No-leak / no-false-lock proof

**No path leaks Pro to Free** (belt-and-suspenders, three independent layers):
1. **Physical** — the Free ZIP has every Pro Service/Feature class removed (`FREE_EXCLUDE`) and every
   `@pro` block stripped, enforced by the STRICT verifier with no escape hatch (A6). `class_exists(ProClass)`
   is therefore `false` on a Free install, so the emitter guards (A4) can never fire.
2. **State** — even if a Free DB somehow had `pro_activated='1'`, it cannot be *introduced* via the form
   or import (`SYSTEM_PRESERVED_KEYS` + fail-closed merge, A3); and the Pro classes still are not on disk.
3. **Server** — activation fails closed on store/product mismatch, missing pin, or any transport error
   (A9); a cheap same-store add-on key cannot activate the core bundle (`EXPECTED_CORE_PRODUCT_IDS`), and
   integration keys never set `pro_activated` (`coreLicenseActive()` skips `int_*`, A2).

**No path falsely locks an activated Pro customer.** Once `pro_activated='1'` it is never written back to
`'0'` (verified by the whole-repo write inventory: the only writers are
`markPerpetualActivation()`/`LicenseReconcile`/the pkg_script migration, all set `'1'`), and `isProActive()`
ignores `license_state`, `license_tier` and the heartbeat — the exact fields that drift on expiry. Expiry
pauses updates/support only.

**The one known gap** is the inverse: a Pro *install* that was never *activated* (no key entered) presents
as Free — P5. That is fail-closed-correct but a UX risk.

---

# Part E — Where each Pro feature is produced and how it is gated (master map)

| Surface | Produced in | Pro class / mechanism | Gate |
|---|---|---|---|
| Pro Schema.org enrichment (ratings, business details, author entity, HowTo, Event, breadcrumb) | `aiboost_schema` (Free) | `SchemaProBuilder` (+ `BusinessHoursBuilder`, `SiteTypePresetService`, `BreadcrumbPro`) | `class_exists && isProActive` |
| Per-article OG, `og:type=article`, `article:*`, `fb:app_id`, `og:locale`, `twitter:site`, custom fields | `aiboost_social` (Free) | `OgTagProDecorator` (+ `CustomFieldReader`) | `class_exists && isProActive` |
| `/llms-full.txt`, `/llms-{sef}.txt`, `/{key}.txt`, llms.txt enrichment | `aiboost_aeo` (Free) | `LlmsTxtProGenerator` | `class_exists && isProActive` |
| IndexNow auto-submit on publish/state change | `aiboost_aeo` (Free) | `IndexNowService` | `class_exists && isProActive` |
| `X-Robots-Tag` header | `aiboost_aeo` (Free) | inline | `isProActive` |
| Sitemap index / image sitemap / news sitemap | `aiboost_sitemap` (Free) | inline | `isProActive` |
| Sitemap hreflang alternates | `aiboost_sitemap` (Free) | `HreflangSitemapExtension` | `isProActive && hasPro('int_falang')` |
| Translated schema / OG (per language) | `aiboost_schema` / `aiboost_social` (Free) | `TranslationService` | gate above **AND** `hasPro('int_falang')` |
| Multilang hreflang head tags + sitemap registration | `aiboost_int_falang` (Pro ZIP) | `proOn()` | `hasPro('int_falang')` (non-perpetual) |
| YOOtheme typed schema, FAQ, gallery, sitemap exclusion | `aiboost_int_yootheme` (Pro ZIP) | `proOn()` | `hasPro('int_yootheme')` (non-perpetual) |
| YOOtheme OG override (page meta) | `aiboost_int_yootheme` (Free) | `bridgeOn()` | host present + admin toggle (no licence) |
| 5 `*_pro` decorator plugins | — | dormant no-ops, swept on Pro install | n/a |

---

# Appendix — key file inventory

| Concern | File |
|---|---|
| The gate + activation + capabilities scan | `component/lib/src/PluginRegistry.php` |
| Licence verify + pinning + server URLs | `component/lib/src/LicenseValidator.php` |
| Lapsed-purchaser recovery (install_id anchor) | `component/lib/src/LicenseReconcile.php`, `LicenseHeartbeat.php` |
| Save whitelist + preserved/denylist + fail-closed merge | `component/lib/src/SettingsSaveDefinition.php` |
| Dead legacy save-gate (no-ops) + historical SKU→surface map | `component/lib/src/ProFeatureRegistry.php` |
| Dead per-plugin params gate (trait) | `component/lib/src/ProGate.php`; dead `isProTier()` in `AbstractService.php` |
| Activation endpoint + product-pin selection | `…/Controller/SettingsController.php` (`verifyLicense`, `resolveLicenseState`) |
| Bootstrap `isPro` / `isProInstall` | `…/View/App/HtmlView.php` |
| Free vs Pro build (strip, FREE_EXCLUDE, STRICT verify, Pro edition) | `scripts/build-package-zip.py`; `scripts/verify-no-pro-leakage.py` |
| Free installer (pro_installed marker, decorator sweep, migrations) | `component/package/pkg_script.php` |
| Orphaned legacy split-Pro installer (never invoked) | `component/package/pkg_script_pro.php` |
| Pro emitters (Free plugins) | `component/plugins/system/aiboost_{schema,social,aeo,sitemap}/…` |
| Dormant decorators (dead weight) | `component/plugins/system/aiboost_{schema,social,aeo,code,hreflang}_pro/…` |
| Integration bridges | `component/plugins/system/aiboost_int_{falang,yootheme}/…` |
