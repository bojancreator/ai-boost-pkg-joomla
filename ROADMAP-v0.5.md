# ROADMAP — AI Boost for Joomla v0.5

**Komandni sto za v0.5 sprint.** Svaka sesija počinje ovde: pročitaj ovaj fajl →
`BACKLOG.md` → relevantan fajl iz `docs/` → radi tačno jedan slice → ažuriraj
ovaj fajl na kraju.

Ostali dokumenti:
- `BACKLOG.md` — sve što postoji kao posao (dugoročna lista)
- `OPERATING.md` — procedure, pravila, Definition of Done
- `docs/v0.5-product-direction.md` — zaključane produktne odluke za v0.5
- `docs/architecture-refactor-plan.md` — arhitekturni gates za strukturne refaktore

---

## Current Status

| Field | Value |
|---|---|
| **Repo** | `bojancreator/ai-boost-pkg-joomla` |
| **Branch** | `v0.5-simple-autopilot` |
| **Code version** | `0.73.6` (2026-06-02) |
| **v0.5 milestone phase** | Free/Pro removal slices u toku — korak 2 sledeći |
| **Last completed step** | Step 1 — Documentation & strategy ✅ |
| **Active slice** | Free/Pro removal slice 1 |

---

## Phase Board

| Step | Slice | Status |
|------|-------|--------|
| 1 | Documentation & strategy (`OPERATING.md`, `BACKLOG.md`, `docs/v0.5-product-direction.md`, `ROADMAP-v0.5.md`) | ✅ Done |
| 2 | **Free/Pro removal slice 1** — remove visible Pro locks/badges from admin UI and route guards | ✅ Local Complete — staging pending |
| 3 | Free/Pro removal slice 2 — simplify save gating / registry exposure, keep legacy settings compatible | ✅ Local Complete — staging pending |
| 4 | Free/Pro removal slice 3 — retire per-SKU simulator/admin surfaces and align License & Updates | 🔲 Not Started |
| 5 | Admin IA slice 1 — sidebar grouping + visible labels + route aliases | 🔲 Not Started |
| 6 | Admin IA slice 2 — Technical SEO page (canonical + 404 monitoring) | 🔲 Not Started |
| 7 | Admin IA slice 3 — Crawlers & Robots page | 🔲 Not Started |
| 8 | Health merge — merge Errors into Health, update fix targets | 🔲 Not Started |
| 9 | Autopilot MVP — new SETUP route, guided checklist (no AI backend) | 🔲 Not Started |
| 10 | Admin IA slice 4 — Meta Pixel move, Social Meta cleanup, Schema.org reorder, AEO rename | 🔲 Not Started |
| 11 | QA pass — full staging verification on all test sites | 🔲 Not Started |
| 12 | Release | 🔲 Not Started |

> Koraci 2–7 mogu raditi odvojeni agenti paralelno, **osim ako diraju iste fajlove**.
> Koraci 2–4 su najvažniji UX dobici i treba ih prikazati prvi.

---

## Active Slice

*(Popuniti kad se krene sa radom)*

```
Slice:      Free/Pro removal slices 1-2 local verification
Goal:       Confirm admin UI and server save paths behave as one AI Boost package before staging QA.
Files:      Vue admin tabs/shell/router/sidebar, manifest/codegen, save definition, legacy feature registry, Health/license copy.
Off-limits: Stored settings key deletion, package split deletion, Lemon Squeezy API contract removal.
Done when:  Staging confirms settings remain reachable/saveable and no visible Pro/Free lock states remain.
```

---

## Decision Log

*(Kratke, datirane odluke — ne brisati starije unose)*

| Date | Decision |
|------|----------|
| 2026-06-04 | v0.5 fokus promenjen: uklanjamo vidljivu Free/Pro podelu iz admin iskustva; legacy license/pro keys ostaju kompatibilni dok se ne uklone kroz proverene slice-ove |
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

*(Šta nas zaustavlja — popuniti kad nastane blokada)*

| Blocker | Otključava |
|---------|-----------|
| — | — |

---

## Verification Log

| Date | Command / Action | Result |
|------|-----------------|--------|
| 2026-06-04 | Free/Pro removal slices 1-2 local source sweep | ✅ Pass — no Vue admin `<ProGate gate-key>` wrappers or visible Free/Pro lock copy in component/lib grep |
| 2026-06-04 | `pnpm install --frozen-lockfile` | ✅ Pass — Windows-compatible preinstall helper added; node_modules rebuilt from lockfile |
| 2026-06-04 | `python scripts/codegen-from-manifest.py` | ✅ Pass — Windows PHP subprocess fallback added; generated partials no longer emit ProGate wrappers |
| 2026-06-04 | `pnpm run build` in `component/com_aiboost/vue-admin` | ✅ Pass — `admin-vue.js` rebuilt |
| 2026-06-04 | Targeted PHPUnit: ProFeatureRegistryParityTest, SettingsSaveDefinitionTest, ManifestProRegistryParityTest | ✅ Pass — 25 tests |
| 2026-06-04 | PHP/Python/Node syntax checks + `git diff --check` | ✅ Pass — only Git CRLF warning on generated `admin-vue.js` |
| 2026-06-04 | `python scripts/codegen-from-manifest.py --check` + `python scripts/build-package-zip.py` | ✅ Pass — complex-field coverage fixed for existing hand-written controls; `pkg_aiboost-0.73.8.zip` built and Pro-leakage verifier passed |
| 2026-06-04 | `python scripts/install-to-staging.py --zip deliverables/plugin/pkg_aiboost-0.73.8.zip` | ⚠️ Blocked locally — `requests` installed, but `STAGING_URL` / admin env vars are not set in this shell |
| 2026-06-02 | v0.73.6 build + staging install | ✅ Pass |
| Pre-2026-06-04 | v0.72.x staging verification (Task #567, #566) | ✅ Pass — 16 Health items su pre-existing config gaps, ne code defekti |

---

## Next Handoff

Sledeći agent treba da nastavi **tačno jedan** od ovih koraka:

1. **Staging QA for Free/Pro removal slices 1-2** *(trenutno aktivno)*
   - Scope: install/build package on staging and confirm admin routes/tabs/settings are reachable and saveable without Pro locks.
   - Focus: Sidebar, Dashboard, License & Updates, Schema, Sitemap, AEO, Social, Analytics, Code, Debug, translations, import/redirect/analyzer routes.
   - Current local artifact: `deliverables/plugin/pkg_aiboost-0.73.8.zip`.
   - Local blocker: export `STAGING_URL`, `STAGING_ADMIN_USER`, `STAGING_ADMIN_PASS` in the terminal session before running `python scripts/install-to-staging.py --zip deliverables/plugin/pkg_aiboost-0.73.8.zip`.
   - Blocker to close: no full “done” until staging confirms settings remain reachable.

2. Ako staging prođe — kreni sa **Free/Pro removal slice 3** (retire per-SKU simulator/admin surfaces and deeper legacy package wording)

3. Tek nakon Free/Pro removal slice-ova nastavi sa **Admin IA slice 1** (sidebar grouping + visible labels + route aliases)

> **Obavezno na kraju sesije:** ažuriraj `Active Slice`, `Phase Board`, `Verification Log` i ovaj `Next Handoff` blok. Ne reportuj "done" bez staging verifikacije.
