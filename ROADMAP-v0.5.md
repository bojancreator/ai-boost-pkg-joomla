# ROADMAP — AI Boost for Joomla v0.5

**Komandni sto za v0.5 sprint.** Svaka sesija počinje ovde: pročitaj ovaj fajl →
`BACKLOG.md` → relevantan fajl iz `docs/` → radi tačno jedan slice → ažuriraj
ovaj fajl na kraju.

Ostali dokumenti:
- `BACKLOG.md` — sve što postoji kao posao (dugoročna lista)
- `OPERATING.md` — procedure, pravila, Definition of Done
- `docs/v0.5-product-direction.md` — zaključane produktne odluke za v0.5
- `docs/architecture-refactor-plan.md` — evergreen metodologija za strukturne refaktore (nije tracker)

---

## Current Status

| Field | Value |
|---|---|
| **Repo** | `bojancreator/ai-boost-pkg-joomla` |
| **Branch** | `v0.5-simple-autopilot` |
| **Code version** | `0.74.0` (2026-06-12) |
| **v0.5 milestone phase** | **Pre-release plans** — Plan 1 (Integracije) shipped; preostaju Plan 2 (UI/UX), Plan 3 (totalna verifikacija), pa Faza C (LS + real-key QA + 1.0.0) |
| **Last completed step** | **Plan 1 (v0.74.0): Integracije** — master toggle (`integration_<key>_enabled`) u Integrations UI sa optimistic update/rollback + `paused` status; `AbstractIntegrationPlugin::isActive()`; Falang runtime gate na isActive(); Manifest lock grana `integration_off`; `IntegrationsController::saveToggle`; Admin Tools conflict fix (`admintools`+`com_admintools`, gated na enable_robots); **YOOtheme bridge SDK migracija** (`aiboost_int_yootheme`: filter-in-finalize FAQ/gallery schema, `hasPro('int_yootheme')` umesto mrtvog `license_tier`, `SLOT_SCHEMA_FAQ`, script.php migracija); novi `info_integration_master_toggle` health check; `docs/integrations.md` |
| **Active slice** | **Plan 2 (Sonnet) — UI/UX:** screenshot audit obe teme → izveštaj → Bojan bira → implementacija. Potom Plan 3, pa Faza C. Plan: `C:\Users\User\.claude\plans\proud-crunching-rain.md` |

> **Napomena (2026-06-08):** Originalni Phase Board (koraci 5–10 IA prerada) je
> bio materijalno netačan — označavao je „Not Started" za stranice koje **već
> postoje u kodu i u shippovanom bundle-u**. Verifikovano protiv `vue-admin/src`
> (`TechnicalSeoTab.vue`, `CrawlersRobotsTab.vue`, `AutopilotPage.vue`,
> `Sidebar.vue` + `navigation.js` grupisani sidebar i route aliasi). Board ispod
> je usklađen sa kodom. Stvarni preostali posao do prodaje je u sekciji
> **Release Hardening**.

---

## Phase Board (usklađen sa kodom 2026-06-08)

| Step | Slice | Status |
|------|-------|--------|
| 1 | Documentation & strategy | ✅ Done |
| 2 | Free/Pro removal slice 1 — remove visible Pro locks/badges | ✅ Done |
| 3 | Free/Pro removal slice 2 — simplify save gating / registry exposure | ✅ Done |
| 4 | Free/Pro removal slice 3 — License & Updates align + retire per-SKU simulator | 🟡 Partial — License & Updates vezan na **pravu** Lemon Squeezy proveru (Faza 1A); per-SKU simulator je JDEBUG-gated i van produkcije, finalno uklanjanje odloženo post-launch (Faza 3g) |
| 5 | Admin IA slice 1 — sidebar grouping + visible labels + route aliases | ✅ Done — `navigation.js` (`createSidebarGroups`, `settingsRouteAliases`, `pageRouteAliases`) |
| 6 | Admin IA slice 2 — Technical SEO page | ✅ Done — `TechnicalSeoTab.vue` + `/technical-seo` alias |
| 7 | Admin IA slice 3 — Crawlers & Robots page | ✅ Done — `CrawlersRobotsTab.vue` + `/robots`, `/crawlers-robots` aliasi |
| 8 | Health merge — Errors u Health, update fix targets | ✅ Done — Health nosi `badge: 'errors'`; Errors nije u primarnoj navigaciji |
| 9 | Autopilot MVP — SETUP ruta, guided checklist (no AI backend) | ✅ Done — `AutopilotPage.vue` + `/autopilot` + `/setup` alias |
| 10 | Admin IA slice 4 — Meta Pixel/Social cleanup, Schema.org reorder, AEO rename | 🟡 Partial — AEO→„AI Visibility" rename Done; Schema.org reorder **odložen post-launch** (visok rizik, niska hitnost na najvažnijoj strani) |
| 11 | QA pass — full staging verification | 🔲 In progress (Release Hardening Faza 4) |
| 12 | Release | 🔲 Pending |

---

## Release Hardening (stvarni put do prodaje)

Dubinska analiza (2026-06) je pokazala da je jezgro proizvoda solidno i radi
(Schema/Sitemap/OG/robots/llms end-to-end), ali su postojala 2 tvrda blokera +
build koji nije čisto prolazio na Windows-u. Ovo je sekvencirani put koji se
izvršava; statusi su stvarni.

| Faza | Posao | Status |
|------|-------|--------|
| 0 | Windows build pipeline (cp1252 UTF-8 shim u 3 skripte; mojibake partiali regenerisani; build tvrdo pada na Vue/empty-bundle) | ✅ Done |
| 1A | **Prava Lemon Squeezy provera licence** — `verifyLicense()` → `LicenseValidator::verify()` (validate→activate, fail-closed); `AB-VALID` mock samo pod JDEBUG; `pro_activated*` u import denylist | ✅ Done |
| 1B | **Stored XSS kroz JSON-LD** — `JSON_HEX_TAG|JSON_HEX_AMP` na svim json_encode tačkama schema/AEO izlaza | ✅ Done |
| 2 | Health false-positives + correctness — SVG `<title>` lažni CRITICAL, Crawlers/Integrations kategorije, scraper fix-action → crawlers tab, `enable_schema` master gate, `og:url`/article url kanonizacija, ujednačen AI Visibility skor | ✅ Done |
| 3a | CI gates — `phpstan.neon` dead excludePath uklonjen, `composer test`→`run-standalone-tests.php`, phpcs `node_modules` exclude, PHPCS/PHPStan advisory (`continue-on-error`) | ✅ Done |
| 3b | „/ GEO" uklonjen iz AEO naslova (GEO je rezervisan placeholder, van v0.5) | ✅ Done |
| 3c | GPL v2+ `LICENSE.txt` u root + build ga ubacuje u root oba ZIP-a (Free i Pro) | ✅ Done |
| 3d | ROADMAP usklađen sa kodom; `architecture-refactor-plan.md` označen kao evergreen referenca | ✅ Done |
| 3e | Strip test fajlova iz produkcijskog ZIP-a (`BridgeDetectorTest.php` itd.) + ožičiti ga u `phpunit.xml` | 🔲 Pending |
| 3f | Dead workspace cleanup — `@workspace/db` iz `scripts/package.json` + seed skripte + lockfile | 🔲 Pending |
| 3g | (Odloženo post-launch) phpcbf CRLF/style reformat ~1965 prekršaja + `.gitattributes`; dead-code (ProFeatureRegistry, simulator, ProGate.vue) | ⏸ Deferred |
| A | **Audit-blokeri (2026-06-11, v0.73.45–46)** — full-repo audit (50 agenata) našao 2 kritična + ~15 visokih; Faza A shipped: (K1) settings.save više ne briše `pro_activated`/`license_state`/`install_id` — `SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS` deli save+import+export granicu; (K2) LS store pinning u `LicenseValidator::verify()` (⚠️ `EXPECTED_STORE_ID=null` → vidi Blockers); uninstall ČUVA licencu (wipe samo dev_*); LS aktivacija preko Joomla HTTP klijenta; JDEBUG `AB-VALID` mock ide kroz simulator (nikad `markPerpetualActivation`); export bez licencnih ključeva + sa translations; Vue: ScopeSelector pravi SFC (scope kontrole se sad renderuju u prod bundle-u), AppShell više ne unmount-uje na cache-hit + unsaved-changes guard; entry guard u svih 8 plugina + `libReady()` u svim Extension klasama (partial-lib ne ruši admin); bazni uninstall gasi Pro dodatke + `mod_aiboost_health`; verifier: redosled uklanjanja (pro pre base) + `ensure_pro` umesto nemogućeg seed-a; obrisan `pkg_aiboost-9.9.9.zip`. Testovi: 306/306 PHPUnit (5.513 asercija) + 3/3 standalone + 7/7 Vue node | ✅ Done |
| B | **Sadržaj za kupce (2026-06-12, v0.73.47)** — docs/ sweep: 17 fajlova prepisano protiv stvarnog SPA UI-ja (bili zamrznuti na „JoomlaBoost v0.24", mrtvi Starter/Developer/Agency tieri; uklonjeno 9 tvrdnji o funkcijama koje ne postoje — preseti, „11 admin language packs", caching toggle…); `pkg_aiboost_pro.xml` → „AI Boost for Joomla — Pro Upgrade" + GPL v2+ (bilo „Legacy Add-on Package"/„Commercial closed-source"); `pkg_aiboost.xml` opis 7 plugina + `<updateservers>` → `https://aiboostnow.com/updates/pkg_aiboost.xml`; novi `scripts/generate-update-xml.py` (validan Joomla update XML iz Version.php); Release runbook u OPERATING.md; LicensesPage bez „automatic updates" + „How updates work" sekcija; Dashboard Danger Zone govori istinu (podaci+licenca preživljavaju) + first-run banner → Autopilot umesto backup alarma; Health fix-linkovi: licenses/integrations/errors/debug + latentan `field=`-posle-hash bug; `.github/workflows/brand-guard.yml` (CI fail na „JoomlaBoost"/„AI Boost Now" u docs/+component/). Testovi 311/311; E2E 25/25 oba sajta | ✅ Done |
| 4 | QA + release | 🟡 In progress — ✅ lockstep Free/Pro build (0.73.15, LICENSE + 0 test artefakata verifikovano u ZIP-ovima); ✅ `uninstall-guide.md` ispravljen; ✅ clean-uninstall PASS na živom Free staging-u (data preserved + licence wiped); ⏳ ostaje: Pro-target QA sa pravim LS ključem, license-activation/XSS/Health staging provere, version bump, release |

**Odloženo van v0.5 (potvrđeno):**
- Update server (`api.aiboostnow.com`) — ne postoji; v1 ide ručnim ZIP update-om.
- WordPress vertical — **v2.0 posle launcha**. Adapter šavovi postoje (8 CMS interfejsa + `AdapterRegistry` swap tačke + `_JEXEC||ABSPATH` guard na svih 78 lib fajlova + WP stub adapteri), ali impl fali: svi WP adapteri bacaju/no-op, `WpAppContext` vraća prazno, host sloj 0% (13 Joomla system plugina → nema WP hook ekvivalenta), `DatabaseAdapter` vraća Joomla `DatabaseInterface` (apstrakcija curi), 317 direktnih Joomla poziva u 35/78 lib fajlova još van adaptera. Zaseban projekat.
- GDPR purge-all-data pri uninstall-u — podaci se trenutno čuvaju namerno.

---

## Active Slice

```
Slice:      Release Hardening Faza 3e/3f → Faza 4
Goal:       Očistiti produkcijski ZIP (bez test fajlova, bez mrtvog workspace-a) pa pun staging QA i release.
Files:      scripts/build-package-zip.py, phpunit.xml, scripts/package.json, pnpm-lock.yaml, docs/uninstall-guide.md
Off-limits: Stored settings key deletion, Lemon Squeezy API contract, Pro @pro markeri u Pro plugin payload-u.
Done when:  Free i Pro ZIP iste verzije, bez test/dead artefakata; clean-uninstall prolazi za oba; staging potvrđuje da se sve čuva/učitava i front-end artefakti (JSON-LD/OG/sitemap/robots/llms) se stvarno pojavljuju.
```

---

## Decision Log

*(Kratke, datirane odluke — ne brisati starije unose)*

| Date | Decision |
|------|----------|
| 2026-06-11 | **Uninstall ČUVA licencu/Pro aktivaciju** (Bojan): perpetual obećanje važi i preko reinstalacije — uninstall briše samo `dev_license_preview`/`dev_force_free_tier`/`license_simulation`. `migrateActivateProPerpetual` docblock i `uninstall-guide.md` usklađeni |
| 2026-06-11 | **Lansiranje: JED Free + Pro prodaja ISTOVREMENO** (Bojan; protiv preporuke soft-launcha) — docs sweep i Free paket robusnost time postaju obavezni pre lansiranja |
| 2026-06-11 | **Launch verzija = 1.0.0** (bump 0.73.x→1.0.0 u Fazi C, pre release-a) |
| 2026-06-11 | **Update kanal v1 = statički `<updateserver>` XML na aiboostnow.com** (Bojan ima hosting) + download kroz LS portal; pun api.aiboostnow.com ostaje post-launch |
| 2026-06-11 | **Pro se NE MOŽE seed-ovati preko HTTP-a ni za QA** (potvrđeno radom: import denylist + save carry-forward) — verifier downgrade-uje Pro-only asercije; pun Pro-path QA traži `dev_license_preview=1` direktno u bazi ili pravi LS ključ |
| 2026-06-08 | **Site Types (#609) dovršen + Schema entity graph + Pro multilingual bug fix (v0.73.16).** (1) Dodata 3 polja koja su falila: `medicalSpecialty` (Medical/Dentist), `hasMenu`+`acceptsReservations` (Restaurant/Food), `currenciesAccepted` (local). (2) `@id` entity graph — Organization `#organization`, WebSite `#website` + `publisher` ref, Article publisher nosi isti `@id` (Google/AI spajaju u jedan entitet). (3) Pro decorator sveden na **samo-prevod** i primenjen na **svaki** identitetski blok preko `SiteTypePresetService::isBusinessIdentityType()` — popravlja bug gde prevod nije stizao do Restaurant/Hotel/Dentist; uklonjeno ~70 linija duplirane tip-logike. **Step 8 (per-polje Health) svesno preskočen** (false-positive rizik na opcionim poljima, parity sa postojećim #609). Live front-end render QA čeka sajt gde je AiBoost aktivni schema emitter (oba test sajta otpala: offroadserbia prazan, offroadbalkans koristi YooTheme Pro schemu pa je AiBoost conflict-suppressed) |
| 2026-06-08 | **WordPress port potvrđen kao v2.0 (posle launcha).** Provera koda: adapter šavovi su stvarni ali impl fali (detalji u „Odloženo van v0.5"). Wedge na WP = **AEO**, ne klasični SEO (Yoast/RankMath/AIOSEO dominiraju besplatno) — WP ekspanzija ide kroz AEO diferencijator. Prioritet sada: Joomla v0.5 do prodaje |
| 2026-06-08 | **Pricing (supersedes 2026-05-25 €45 single-license):** 3 site-count tiers, yearly subscription, iste Pro funkcije — **AI Boost PRO** 3 sajta €65, **AI Boost Pro+** 10 sajtova €120, **AI Boost Unlimited** ∞ €180. Kod ne hardkoduje tier/cenu (čita LS `activation_limit`); 3-tier je čisto LS konfiguracija, bez izmene koda |
| 2026-06-08 | Licenca: `verifyLicense()` vezan na **pravu** Lemon Squeezy proveru (`LicenseValidator::verify`, fail-closed); mock `AB-VALID` radi **samo** pod JDEBUG; `pro_activated*` dodat u import denylist da se Pro otključavanje ne prenosi između sajtova |
| 2026-06-08 | Stored XSS kroz JSON-LD zatvoren na encode sloju (`JSON_HEX_TAG`), ne diramo decode redosled u FaqAutoDetect (čuva legitiman tekst tipa „5 < 10") |
| 2026-06-08 | GPL v2+ `LICENSE.txt` se skida sa gnu.org direktno na disk (verbatim tekst ne sme kroz model output — content filter) i ide u root oba ZIP-a; manifest `<files folder="packages">` se NE dira (to je za sub-extension ZIP-ove) |
| 2026-06-08 | Schema.org reorder kartica i phpcbf masovni reformat **odloženi post-launch** (visok rizik / niska hitnost pred lansiranje) |
| 2026-06-08 | PHPCS/PHPStan postaju advisory (`continue-on-error`) — runtime se testira nezavisno; mehanički style backlog je post-launch čišćenje |
| 2026-06-04 | v0.5 fokus: uklanjamo vidljivu Free/Pro podelu iz admin iskustva; legacy license/pro keys ostaju kompatibilni dok se ne uklone kroz proverene slice-ove |
| 2026-06-04 | `replit.md` je deprecated; novi dokument je `OPERATING.md` |
| 2026-06-04 | `ROADMAP-v0.5.md` uveden kao jedini aktivni izvršni tracker za v0.5 sprint |
| 2026-06-04 | v0.5 marketing milestone ≠ semver; interno verzionisanje nastavlja 0.7x.y shemu |
| 2026-06-04 | Autopilot u v0.5 = guided checklist (bez AI inference engine) |
| 2026-06-04 | GEO stranica = placeholder u meniju, bez implementacije u v0.5 |
| 2026-06-04 | WordPress vertical slice: odloženo do posle v0.5 |
| 2026-06-04 | Stari tab aliasi (`tab=org`, `tab=aeo`, `tab=social`, `tab=analytics`, `tab=sitemap`, `tab=code`) ostaju backward-compatible |
| 2026-05-25 | Pricing: jedan Pro licens €45/god otključava sve; per-SKU model povučen |

---

## Blockers

| Blocker | Otključava |
|---------|-----------|
| Lemon Squeezy proizvodi (3 tier-a) još ne postoje | Pro-target staging QA sa pravim ključem + stvarna prodaja |
| **`LicenseValidator::EXPECTED_STORE_ID` je `null`** — aktivacija namerno odbija SVE ključeve ("store pinning missing") dok se ne upiše pravi LS store ID (~1 linija, uraditi ČIM Bojan napravi LS store, PRE real-key QA) | Bilo kakva aktivacija licence |
| Staging `dev_license_preview` obrisan tokom uninstall QA (nova semantika briše dev ključeve) | Runtime-emit Pro QA na stagingu — po potrebi ponovo `JSON_SET … dev_license_preview='1'` (SQL u CREDENTIALS.local.md §5) |
| ~~Staging env vars~~ | ✅ Rešeno — kredencijali u `CREDENTIALS.local.md`; Free staging QA prošao |

---

## Verification Log

| Date | Command / Action | Result |
|------|-----------------|--------|
| 2026-06-12 | **Plan 1 (v0.74.0)** — `vendor/bin/phpunit` + `composer test` + `pnpm build` + `build-package-zip.py --target all` | ✅ **327/327 PHPUnit** (5.677 asercija; +16: ConflictDetectorAdminTools, YoothemeBridgeParity, MasterToggle) + 3/3 standalone + Vue build čist; lockstep Free/Pro 0.74.0 + **Pro-leakage STRICT pass**; novi `plg_system_aiboost_int_yootheme-0.74.0.zip` (11 KB) gradi se |
| 2026-06-12 | **Plan 1 staging** — install base+pro+int_falang+int_yootheme na staging i offroadbalkans → `test-all-settings.js` oba | ✅ Sve instalacije OK; **E2E 25/25 na OBA sajta**; front-end: AI Boost blok emituje čist (bez fatala) |
| 2026-06-12 | **Plan 1 DoD live** — toggle round-trip (`_verify_toggle.py`) na Pro stagingu + YOOtheme Pro-gate na Free | ✅ YOOtheme toggle OFF→`paused`+`master_enabled=false` **persistira** posle re-fetch-a, ON→`support_active` vraćeno; na Free (offroadbalkans, YOOtheme template + int_yootheme instaliran) **FAQPage=0 / ImageGallery=0** → Pro-gate dokazan |
| 2026-06-12 | Build 0.73.47 `--target all` + install staging (base+pro) + offroadbalkans + `test-all-settings.js` | ✅ Lockstep + Pro-leakage STRICT; instalacije uspešne; E2E **25/25 na OBA sajta** |
| 2026-06-11 | Full-repo audit (ultracode workflow: 9 dimenzija × 50 agenata, svaki critical/high nalaz adversarialno verifikovan; K1 i ručno potvrđen u kodu) | ✅ Izveštaj u plan fajlu `proud-crunching-rain.md` — 2 kritična (K1 save-wipe licence, K2 LS bez store pinninga), ~15 visokih, faze A–D definisane |
| 2026-06-11 | Faza A implementacija (5 paralelnih agenata + adversarial review po grupi + fix runda) → `vendor/bin/phpunit` | ✅ 306/306 testova, 5.513 asercija; `composer test` 3/3; Vue node testovi 7/7; `pnpm build` čist; bundle grep potvrdio ScopeSelector markup u prod artefaktu |
| 2026-06-11 | `build-package-zip.py --target all` (0.73.45 pa 0.73.46) + install: staging (base+pro) i offroadbalkans | ✅ Lockstep, Pro-leakage STRICT pass; svi installi uspešni |
| 2026-06-11 | **INCIDENT (rešen):** verifier na pro targetu skinuo bazni paket pre Pro → partial-lib → admin 500 (`Logger` u catch bloku). Bojan oživeo admin SQL disable-om Pro plugina; root-cause hardening shipped u 0.73.46 (libReady + safe-catch + uninstall gasi Pro dodatke + verifier redosled) | ✅ Klasa incidenta zatvorena — bila bi i kupčev scenario |
| 2026-06-11 | `verify-clean-uninstall.py --target free` (Pass 1+2, posle brisanja 9.9.9: ispravan NEW zip) | ✅ PASS — **podaci + licenca preživljavaju uninstall, dev override obrisani** (živo, end-to-end); upgrade čuva org_name |
| 2026-06-11 | `verify-clean-uninstall.py --target pro` (posle ordering+ensure_pro fixa) | ✅ PASS — uklanjanje pro→base bez 500; data+licence preserved; translations preservation-only (Pro nije aktivan — ne može se seed-ovati po dizajnu) |
| 2026-06-11 | `test-all-settings.js` E2E — staging i free | ✅ PASS 25/25 na OBA sajta (0.73.46) |
| 2026-06-08 | Site Types #609 + `@id` graph + Pro multilingual fix → PHPUnit + lockstep build + staging install | ✅ Code-level PASS — **234 testa / 4690 assertion-a** (＋23 nova: emit `hasMenu`/`acceptsReservations`/`currenciesAccepted`/`medicalSpecialty`, `@id` graf, `isBusinessIdentityType` gate); build 0.73.16 Free+Pro lockstep (codegen guard + Vue + Pro-leakage); install 0.73.16 na offroadserbia + offroadbalkans (Joomla 6.1.1) bez greške; import.upload prihvata nove manifest ključeve (save round-trip OK). ⏳ **Live front-end render NIJE uhvaćen** — nijedan test sajt nema AiBoost kao aktivni schema emitter (offroadserbia prazan/nekonfigurisan; offroadbalkans schema dolazi iz YooTheme Pro pa je AiBoost conflict-suppressed). `scripts/verify-schema-fields.py` napisan (snapshot→test→restore), čeka čist QA sajt |
| 2026-06-08 | `verify-clean-uninstall.py --target free --uninstall-only` vs **živi** offroadbalkans.com (Joomla) | ✅ PASS — install 0.73.15 OK; posle uninstall: ekstenzija uklonjena, llms/sitemap/robots očišćeni, **sve #__aiboost_* tabele + seed redovi preživeli, licencni ključevi obrisani** (potvrđuje uninstall-guide ispravku end-to-end) |
| 2026-06-08 | `verify-clean-uninstall.py --target pro` Pro-seed korak | ⚠️ Pro-seam zatvoren namerno — verifier flipuje Pro importom `pro_activated`, a Faza 1A ga je dodala u IMPORT_DENYLIST. **Potvrda da bezbednosna ispravka radi** (Pro se ne može preneti importom). Pro-translation QA sad traži pravi LS ključ; QA tooling `seed_pro()` treba update post-launch |
| 2026-06-08 | Fix: `verify-clean-uninstall.py` cp1252 UnicodeEncodeError (UTF-8 shim, isti kao Faza 0) | ✅ Pass — skript radi na Windows konzoli |
| 2026-06-08 | `python -c ast.parse build-package-zip.py` posle LICENSE izmena | ✅ Pass — syntax OK |
| 2026-06-08 | GPL-2.0 download → `LICENSE.txt` | ✅ Pass — 17984 B, 280 linija, validan verbatim GPL v2 |
| 2026-06-05..08 | PHPUnit (`vendor/bin/phpunit`) | ✅ Pass — 183 testa, 4542 assertion-a |
| 2026-06-05..08 | `python scripts/build-package-zip.py` na Windows-u **bez** `--no-codegen-check` | ✅ Pass — exit 0 (cp1252 build blokeri rešeni u Fazi 0) |
| 2026-06-04 | Free/Pro removal slices 1-2 local source sweep | ✅ Pass — nema vidljivih Pro lock copy / ProGate wrappera |
| 2026-06-04 | `python scripts/codegen-from-manifest.py --check` + build | ✅ Pass — `pkg_aiboost-0.73.8.zip` + Pro-leakage verifier prošao |
| 2026-06-02 | v0.73.6 build + staging install | ✅ Pass |
| Pre-2026-06-04 | v0.72.x staging verifikacija (Task #567, #566) | ✅ Pass — 16 Health items su config gaps, ne code defekti |

---

## Next Handoff

Master plan v2 (3 plana pre Faze C) u `C:\Users\User\.claude\plans\proud-crunching-rain.md`.
**Plan 1 (Integracije, v0.74.0) je gotov i verifikovan na stagingu.** Sledeći korak je
**Plan 2 (Sonnet) — UI/UX audit**: screenshot obe teme (svetla/tamna) za sve SPA stranice
→ izveštaj sa P1/P2/P3 nalazima → Bojan bira → implementacija. Potom **Plan 3 (Opus)** —
verify-frontend-emission harness + native-ML testmyweb sajt (tu se rade i Plan-1 stavke koje
ovaj staging ne pokriva: Falang OFF hreflang u head/sitemap, admintools live-fire, YOOtheme
Pro-EMIT sa pravim ključem). Tek posle sva tri plana → **Faza C — release infrastruktura**:

1. **BOJAN (othotkey, ~1h):** napravi 3 Lemon Squeezy proizvoda (license keys ON; activation limits 3/10/unlimited; €65/€120/€180) → javi store ID.
2. **`EXPECTED_STORE_ID`** upisati u `LicenseValidator.php` (1 linija + test) — bez toga aktivacija odbija sve ključeve.
3. **Real-key end-to-end QA = TVRDI RELEASE GATE:** kupovina u LS test modu → aktivacija na stagingu → izmena podešavanja + save + reload → Pro i dalje aktivan (anti-K1) → prevodi/IndexNow/llms-full emit.
4. **CI minimum:** workflow koji gradi pravi paket (`build-package-zip.py --target all`) + `pnpm build` na svaki push.
5. **Pre release-a:** objavi update XML + Free ZIP na aiboostnow.com (runbook u OPERATING.md), pa bump verzije na **1.0.0**, lockstep build, full DoD QA, release.

Posle C: **Faza D** post-launch (SettingsStore konsolidacija, SEO correctness batch — canonical/breadcrumb/SearchAction, sitemap keširanje + Falang scoping, 404 retention, HeadBlockBuilder+sitemap testovi, deletion pass 3 mrtve gating generacije + truth-up dokumenata, JED Free submit; analytics tier-mismatch reconcile — docs sad prate registry, UI još ne gate-uje GA4/GTM).

> **Obavezno na kraju sesije:** ažuriraj `Current Status`, `Phase Board`/`Release Hardening`, `Verification Log` i ovaj `Next Handoff` blok. Ne reportuj „done" bez staging verifikacije.
