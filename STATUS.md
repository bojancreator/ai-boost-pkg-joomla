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
| staging.offroadserbia.com (live) | Pro | current (Phase-4 verified) | 2026-06-17 (phase 4) |
| offroadbalkans.com (live) | Free | `0.77.2` | release-only — do not routine-touch |
| joomla6-pro.testmyweb.info | Pro | behind — needs refresh¹ | — |
| joomla6-free.testmyweb.info | Free | `0.85.4` ✅ | 2026-06-18 — installed; admin + settings load |
| joomla5-pro.testmyweb.info | Pro | behind — needs refresh¹ | — |
| joomla5-free.testmyweb.info | Free | `0.85.4` ✅ | 2026-06-18 — installed; admin + settings load |

> **Free gap CLOSED on test sites (2026-06-18):** j5free/j6free upgraded `0.77.2`→`0.85.4` via
> `install-matrix.py --sites j5free,j6free` (3/3 packages each; settings field count 216→218 confirms the
> upgrade landed; admin + settings API load cleanly). The **live** Free site (offroadbalkans) is left at
> `0.77.2` by design — touch it only at release.
>
> **¹ Pro test sites are behind, and the current Pro ZIP is not built yet** — the newest Pro package in
> `deliverables/plugin/` is `pkg_aiboost_pro-0.79.7.zip` (Free is current at 0.85.4). Before refreshing the
> Pro test sites (and before release), run `python scripts/build-package-zip.py --target all` to produce a
> current `pkg_aiboost_pro-0.85.4.zip`, then `install-matrix.py --sites j5pro,j6pro`.

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

1. **Deep-verify the Free build on j6free** — open the admin Health page and a front-end page; confirm
   Health passes, the JSON-LD/OG/sitemap/robots/llms artifacts appear, and Pro-gated cards render **locked**.
   (Install + settings-load already confirmed; this is the front-end/E2E pass.)
2. **Build the current Pro ZIP** (`build-package-zip.py --target all`) and refresh the Pro test sites
   (`install-matrix.py --sites j5pro,j6pro`) so the whole matrix is current.
3. Then resume **Faza C** (release infrastructure → 1.0.0) above.

---

## Start / end-of-chat ritual

- **Start:** read this file first.
- **End of any substantive chat:** update **Now** + **Deployed Versions** + **Next step**; bump
  `component/Version.php` if code changed; commit. Don't report "done" without a test-site verify.
