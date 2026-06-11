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
| **Code version** | `0.73.16` (2026-06-08) |
| **v0.5 milestone phase** | **Release hardening** — IA i Free/Pro removal gotovi; preostaje pakovanje + QA |
| **Last completed step** | Site Types #609 dovršen + Schema `@id` entity graph + Pro multilingual bug fix (v0.73.16) ✅ |
| **Active slice** | Faza 4 (QA + release) — Korak 3 fixes/features u toku |

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
| ~~Staging env vars~~ | ✅ Rešeno — kredencijali u `CREDENTIALS.local.md`; Free staging QA prošao |

---

## Verification Log

| Date | Command / Action | Result |
|------|-----------------|--------|
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

Sledeći korak je **tačno jedan** od ovih (redom):

1. **Faza 3e** — izbaci test fajlove (`component/lib/tests/BridgeDetectorTest.php` i sl.) iz produkcijskog ZIP-a u `build-package-zip.py`; ožiči `BridgeDetectorTest` u `phpunit.xml` da se izvršava.
2. **Faza 3f** — ukloni mrtav `@workspace/db` iz `scripts/package.json` + seed skripte koje ga importuju (`seed-license-mock.ts`, `seed-pkg-versions.ts`); regeneriši `pnpm-lock.yaml`.
3. **Faza 4 (QA + release)** — bump verzije; rebuild **Free i Pro u lockstep-u** (ista verzija, inače Health prijavljuje version-mismatch platnom kupcu); `verify-clean-uninstall.py --target pro` i `--target free`; popravi kontradiktoran `docs/uninstall-guide.md`; pun staging QA (licenca/XSS/Health/front-end artefakti); tek onda release.

> **Obavezno na kraju sesije:** ažuriraj `Current Status`, `Phase Board`/`Release Hardening`, `Verification Log` i ovaj `Next Handoff` blok. Ne reportuj „done" bez staging verifikacije.
