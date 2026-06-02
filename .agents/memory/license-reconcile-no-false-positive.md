---
name: License reconcile — install_id is the no-false-positive anchor
description: Why perpetual-Pro reconciliation matches on bound install_id only, never domain
---

# Perpetual-activation reconciliation (lapsed past purchasers)

The `pro_activated` perpetual flag is normally set by the verify flow or the
`migrateActivateProPerpetual()` backfill, both of which read **local** state. A
genuine past purchaser whose licence lapsed AND whose local licence markers
(`license_state` / `license_tier` / `pro_skus`) were cleared slips through and is
left Free. The runtime safety net is `AiBoost\Lib\LicenseReconcile` (called
admin-only, throttled, from `aiboost_core` `onAfterInitialise`): it POSTs the
install's `install_id` (+ domain, for logging) to the update server, and on an
`eligible` verdict sets `pro_activated` perpetually — regardless of the recovered
licence's status, because expiry must never relock paid code.

**Rule:** server-side eligibility (`/license/reconcile`) is decided **only** by
matching `licenses.bound_install_id`. Domain is logged but is NOT sufficient on
its own.

**Why:** `install_id` is an unguessable per-site UUIDv4 the server bound on the
first heartbeat — a match proves a real prior purchase. Domains are public and
spoofable, so domain-only matching would activate installs that never paid,
violating the hard "no false positives" constraint. Installs that lost their
install_id (full reinstall) must re-enter their key — the safe fallback.

**How to apply:** if you extend recovery (e.g. recover-by-email), keep the same
bar — only activate on a server-held secret tied to a genuine purchase, never on
public, guessable identifiers. The plugin trusts the server verdict; all
anti-false-positive logic lives server-side.
