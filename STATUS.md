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
| **Code version** | `0.85.4` (2026-06-17) — source of truth `component/Version.php` |
| **Branch** | `qa-cycle-and-fixes` (pushed to `origin`); merge → `main` **deferred** by owner |
| **Milestone** | Pre-sale plugin work **done** (phases 1–4). Remaining pre-sale items → website plan (separate repo). |
| **Next gate** | Faza C — release infrastructure → bump to `1.0.0` (see "What's left to launch") |

---

## Deployed Versions

Single drift guard — **Free and Pro must match on the test sites**. Refresh with
`python _creds_run.py scripts/install-matrix.py --check` and update this table (and again at the end of any code task).

| Site | Edition | Installed | Verified |
|---|---|---|---|
| staging.offroadserbia.com (live) | Pro | `0.85.4` | 2026-06-17 (phase 4) |
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

Lifted from the old ROADMAP "Next Handoff" — the real remaining path to sale:

1. **OWNER:** create the 3 Lemon Squeezy products (license keys ON; activation limits 3/10/unlimited;
   €65/€120/€180) → report the store ID.
2. Write `EXPECTED_STORE_ID` (+ the 5 product IDs) into `component/lib/src/LicenseValidator.php` — until set,
   activation **fails closed** (rejects all keys, by design).
3. **Real-key end-to-end QA (hard release gate):** buy in LS test mode → activate on Pro staging → change a
   setting + save + reload → Pro still active → translations/IndexNow/llms-full emit.
4. Publish the Free update XML + Free ZIP on aiboostnow.com (Release runbook in `OPERATING.md`).
5. Bump to **`1.0.0`**, lockstep Free+Pro build, full Definition of Done QA, release.

**Open blockers:** LS products don't exist yet; `EXPECTED_STORE_ID` is `null`.

---

## Next step

1. ✅ **Test matrix current** (2026-06-18) — Pro ZIP built at 0.85.4, all four test sites refreshed,
   j6 front-end verified. The pre-Faza-C catch-up is done.
2. **Faza C** (release infrastructure → 1.0.0) is **deferred** by the owner — see "What's left to launch".
3. A larger task list from the owner is queued next.

---

## Start / end-of-chat ritual

- **Start:** read this file first.
- **End of any substantive chat:** update **Now** + **Deployed Versions** + **Next step**; bump
  `component/Version.php` if code changed; commit. Don't report "done" without a test-site verify.
