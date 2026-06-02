---
name: AI Boost Pro UI gating rule
description: Pro is unlocked by a perpetual pro_activated flag set once on first verify; never relocked.
---

There is ONE canonical bundle-level Pro gate: `PluginRegistry::isProActive(array $settings): bool`. The admin bootstrap (`HtmlView::buildBootstrap()`, consumed by TopNav + ProGate), the settings-save endpoint (`SettingsController::isProSetting()`) and the sitemap runtime (`AiBoostSitemap::isPro()`) ALL delegate to it, so UI gating, server enforcement and frontend rendering can never drift apart.

**Perpetual activation model (since v0.71.0):** Pro is unlocked by a permanent `pro_activated='1'` flag that `PluginRegistry::saveLicenseState()` sets ONCE the first time any SKU verifies active (also stamping `pro_activated_at` + `pro_activated_version`). The flag is **never cleared**. Once activated, Pro works **forever** — expiry, a non-active `license_state` status, or a lapsed heartbeat NEVER relock the code; an expired licence only pauses updates + support (enforced by the **update server**, not by disabling features). Free = `pro_activated` was never set.

Precedence inside `isProActive()` (flat 4-branch, reads only its `$settings` arg, no DB, no `license_state` walk, no `license_tier` read): (1) `dev_force_free_tier==='1'` → false; (2) `pro_activated==='1'` → true; (3) `dev_license_preview==='1'` → true; (4) else false.

**Why:** v0.54.2 ("Pro without a currently verified key == Free") meant a paying customer lost all Pro output the moment their licence lapsed — punishing the code instead of just gating updates. Bojan's #565 directive reverses that: payment is a one-time unlock; renewal is a commercial/update concern, not a runtime kill-switch. This is intentionally the opposite of the old "only an active verified key proves Pro" rule — the activation flag is now the durable proof.

**How to apply:**
- Any new "should this be locked/enforced?" check that is NOT per-SKU must call `PluginRegistry::isProActive($settings)` — never re-derive from `license_tier` or hand-roll a `license_state` scan.
- `hasPro($sku)` loads settings once (static cache) and delegates to `isProActive()`; there is no longer a per-SKU heartbeat hard-disable path.
- `dev_force_free_tier='1'` (DB-only QA) forces the whole install to behave as Free and wins over everything; `dev_license_preview='1'` (DB-only QA) forces Pro. Never expose either in the UI.
- Existing paid installs are backfilled by `pkg_script` `migrateActivateProPerpetual()` (active license_state OR legacy pro tier/pro_skus → set `pro_activated`).
- The Licenses page must stay usable so a key can be (re-)entered — it uses the separate `isProInstall` (extension-table) signal, NOT `isPro`.
- TopNav reads `boot.isPro`, NOT `boot.license_tier` (never populated at top level).
