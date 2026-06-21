# STATUS — AI Boost for Joomla (single live board)

**This is the one place that tells the truth about *where we are now*.** Read it first at the start of
every chat; update it at the end of every chat. It replaces the old `ROADMAP-v0.5.md` as the command post.

- **What's left to do** → `BACKLOG.md`
- **How we work** (rules + Definition of Done) → `OPERATING.md`
- **Why / history** (decisions, past verification) → `ROADMAP-v0.5.md` (ARCHIVE) and `docs/`

---

## Now

| Field | Value |
|---|---|
| **Code version** | `0.87.24` (2026-06-21) — source of truth `component/Version.php` |
| **Branch** | `design/admin-ui-refresh` (local only, not pushed); base `main` |
| **Milestone** | Implementing the Instrument mockup (`screens.html`) into the real Vue admin. Phases 1–5 DONE. **Phase 5 correction (v0.87.18–19)** — stripped Joomla outer wrapper padding (`.main-card`, Bootstrap col/row/container) so the component sits flush edge-to-edge; restored 1rem sidebar→content gap; removed sidebar border-radius. JS fallback in AppShell.vue walks the DOM and zeros all Bootstrap wrapper padding (works regardless of Joomla version). Verified both themes on staging v0.87.19. **Header pass (v0.87.20–22)** — imported the authoritative `screens.html` from the claude.ai design project (id `953d98be-…`, "UI mockup collection"); fixed Redirects/Analyzers pill sub-tabs → flat `.ab-tab` strip; moved the "no settings backup" reminder off every page to Dashboard-only (per design); Settings title now shows the active tab name (not generic "Settings"); added a shared `PageHeader` (Instrument `.ab-topbar`) to ALL pages — bold title + right-aligned actions slot + optional subtitle. NB: uppercase section/card/tab labels are INTENTIONAL (Instrument signature in `ab-components.css`), not a bug. Verified both themes on staging v0.87.22. **Design round 2 (v0.87.23–24, plan `dizajner-i-ja-smo-async-muffin.md`)** — applied the Designer's round-2 changes (committed `d5c5d4c` was the header pass; round 2 is NEW, uncommitted): font scale up (2-dec rems), IBM Plex Mono 700, badges (no dot, bold, light-stroke/dark-solid), tags bold, NEW `OnOffSwitch` (ON/OFF labelled), **light sidebar in light theme** + **single-open accordion** groups + wider/bigger items, Dashboard module cards = framed ENABLED/DISABLED + ON/OFF switch (instant toggle, transient flash) in a 3-col grid, Integrations = status label by name + top-right ON/OFF switch + no card icon, removed superfluous Schema sub-tab emoji. Verified both themes on staging v0.87.24. |
| **Next gate** | Design round 2 complete + verified — awaiting Bojan's review and his per-page copy notes (he flagged more rounds coming). Pending follow-ups: refresh `handoff/` copies in the claude.ai project (approved); mirror the Module-card + ON/OFF specimens into `StyleguidePage.vue`; optional light-theme topbar contrast tweak. |

---

## Deployed Versions

Single drift guard — **Free and Pro must match on the test sites**. Refresh with
`python _creds_run.py scripts/install-matrix.py --check` and update this table (and again at the end of any code task).

| Site | Edition | Installed | Verified |
|---|---|---|---|
| staging.offroadserbia.com (live) | Pro | `0.87.24` | 2026-06-21 — Design round 2 (light sidebar + accordion, module/integration ON/OFF switches, font/badge/tag refresh) verified both themes. Header pass committed `d5c5d4c`; round-2 changes NOT yet committed |
| offroadbalkans.com (live) | Free | `0.77.2` | release-only — do not routine-touch |
| joomla6-pro.testmyweb.info | Pro | `0.85.4` ✅ | 2026-06-18 — front-end verified (17/17; 8 JSON-LD nodes) |
| joomla6-free.testmyweb.info | Free | `0.85.4` ✅ | 2026-06-18 — front-end verified (17/17; 4 JSON-LD nodes) |
| joomla5-pro.testmyweb.info | Pro | `0.85.4` ✅ | 2026-06-18 — installed (3/3, 229 fields) |
| joomla5-free.testmyweb.info | Free | `0.85.4` ✅ | 2026-06-18 — installed (3/3, 218 fields) |

> **Whole test matrix CURRENT at `0.85.4` (2026-06-18):** Free (j5free/j6free) and Pro (j5pro/j6pro) all
> refreshed and confirmed. Pro ZIP `pkg_aiboost_pro-0.85.4.zip` (695 KB) built via
> `build-package-zip.py --target all` — STRICT Pro-leakage check passed (no Pro code in the Free packages).
> j6 deep-verified on the front-end (17/17 read-only checks each); the Pro site emits visibly more than Free
> (8 vs 4 JSON-LD nodes, 4067 vs 2844-byte llms.txt). The **live** Free site (offroadbalkans) stays at
> `0.77.2` by design — release only.
>
> **Free test sites confirmed genuinely Free (2026-06-18):** j6free/j5free admin bootstrap reports
> `isPro=false` / `proActivated=false`, and the DB settings carry no activation (`pro_activated` absent,
> all `pro_skus` false) — so the SPA `<ProGate>` locks Pro surfaces exactly as a real Free customer sees.
> The earlier scary "core-Pro active" reading was a **stale check in `verify-frontend-emission.py`**: it
> inferred Pro from the per-field `locked` flag, but core `tier=pro` fields stopped being field-locked in
> the v0.5 one-product transition (`Manifest\Registry::applyLockState`), so that signal reported Pro on
> every site. Fixed to read the authoritative bootstrap `isPro` — it now shows Free=`core Free`,
> Pro=`core-Pro active`. No data change was needed.

---

## What's left to launch (Faza C → 1.0.0)

Reframed 2026-06-18 around the **website + self-hosted licensing backend** project (plan approved;
`…/.claude/plans/ovo-se-malo-zagu-valo-valiant-bachman.md`). Payment is externally blocked/undecided
(LS won't allow test payments, Stripe-from-Serbia uncertain, PayPal/local fallback) → we build a
**payment-agnostic** licensing authority on the Contabo VPS so a **Manual-issued key unblocks full E2E
QA now**, with payment plugged in later.

1. **Track A — website redesign:** migrate aiboostnow.com to Astro (SSG, en + 6 langs), correct
   pricing/content, build `/account` + `/eula`, keep the plugin's URL contract. Visuals via Claude Design.
2. **Track B — backend on VPS:** `api.aiboostnow.com` (license validate/activate/heartbeat/reconcile +
   Manual key issuance) + `updates.aiboostnow.com` (token-gated update/download server). PHP + MariaDB.
3. **Track C — plugin wiring:** point manifests at `updates.`, stop stripping `<updateservers>` from Pro,
   repoint validation authority to `api.`.
4. **E2E QA (manual key):** issue key → activate Pro on staging → save/reload (Pro persists) → System →
   Update shows the Pro feed → one-click update installs.
5. Bump to **`1.0.0`**, lockstep Free+Pro build, full Definition of Done QA, release.

**Corrected:** `EXPECTED_STORE_ID` is **not** null — it is `367944` with all 5 product IDs filled
(`LicenseValidator.php`, configured 2026-06-16). Remaining LS items: re-confirm IDs vs the live dashboard,
and a real-key **payment** test once a processor is unblocked. **Open blockers:** VPS access for the
backend; payment-processor decision (does not block A/B/C or manual-key QA).

---

## Next step

1. ✅ **Instrument CSS applied to staging** (2026-06-20, v0.87.4) — petrol-teal palette, IBM Plex fonts,
   very dark sidebar + dark theme. Visual review ongoing with designer.
2. **Next (tomorrow):** designer sends revised screenshots (icons + other elements) → review → decide
   final direction → CSS cleanup (remove Bootstrap utility classes from Vue templates, tačka 6).
3. **Test matrix** (j5/j6 × free/pro) is behind at `0.85.4` — update after design is settled.
4. **Faza C** (release infrastructure → 1.0.0) deferred — see "What's left to launch".

---

## Start / end-of-chat ritual

- **Start:** read this file first.
- **End of any substantive chat:** update **Now** + **Deployed Versions** + **Next step**; bump
  `component/Version.php` if code changed; commit. Don't report "done" without a test-site verify.
