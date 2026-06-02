---
name: Pro gating drift in ProFeatureRegistry
description: Why manifest tier=pro fields silently become Free-editable, and the parity test that catches it.
---

`SettingsController` calls `ProFeatureRegistry::stripLocked()` to drop Pro
keys from a Free install's save payload. `stripLocked()` looks at:
1. exact keys from `ProFeatureRegistry::all()`, and
2. exact keys listed inside `ProFeatureRegistry::sectionFields()`.

A `section:*` row in `all()` (e.g. `section:sitemap.advanced`) does **not** by
itself strip individual field keys — only the keys explicitly listed inside
`sectionFields()['section:sitemap.advanced']` are stripped. So a manifest field
flagged `tier=pro` whose key appears nowhere in either structure is silently
editable on Free, even though the SPA renders a `<ProGate>` lock over it.

**Why:** historical pattern was "wrap the whole tab in a Pro lock at the SPA
level" — fine for UX, useless for server-side enforcement. Found 5 such keys
in the May 2026 audit (hreflang_enabled, hreflang_primary_language,
hreflang_sitemap, schema_breadcrumb_pro, schema_howto_enabled).

**How to apply:**
- `ManifestProRegistryParityTest::testEveryProManifestFieldIsGatedByRegistry`
  walks both structures and fails on any unlisted key.
- The known-drift allowlist (`KNOWN_UNGATED_PRO_KEYS_FIX_IN_472`) is
  intentionally finite and dated. Adding a new entry means you are shipping
  a Free-editable Pro field; do it only with a tracked follow-up.
- The opposite-direction check (`testEverySectionFieldsKeyIsAKnownManifestKey`)
  uses a `legacyAllowlist` for historical row names; new entries there must
  carry a comment explaining the migration or removal plan.

## UI side: `<ProGate>` is fail-closed; the registry only drives the upsell copy

`ProGate.locked()` (component/com_aiboost/vue-admin/src/components/ProGate.vue):
unlock iff `forceUnlock` OR `isPro`; otherwise **always locked** even when the
gate-key has no `all()` entry (then it `console.warn`s + uses generic copy).
**Why this matters:** the registry lookup sets only the pill label / lock_reason
/ upsell URL — never the lock decision. So a missing registry entry on the UI is
**not** a leak (unlike the server side above), only cosmetic.

**Verify against the live SPA, not the scaffolds — two traps when auditing:**
- `all()` returns a **list of `['key' => …]` entries**, so a field is registered
  as `'key' => 'enable_manual_faqs'`. Grepping for `'enable_manual_faqs' =>`
  (as an array key) misses it and falsely flags it as unregistered. Grep for
  `'key' => '<x>'` instead.
- `tabs/generated/*` partials are **imported nowhere** (App.vue imports only the
  hand-written tabs). They reference keys that are NOT in `all()` (hreflang_*,
  schema_breadcrumb_pro, schema_howto_enabled, faq_auto_detect, events_enabled,
  enable_meta_pixel, custom_code_*, …) but are inert — do **not** count them as
  live UI drift. (ripgrep `-g '!tabs/generated/**'` does NOT exclude them; use
  `-g '!**/tabs/generated/**'`.)
- Net result of the May-2026 3-state UI audit: **every live gate-key resolves**
  to `all()` (8 live field keys + all `section:*`/`page:*`); zero live drift,
  zero UI leak.
- `SchemaTab.vue` computed is **named** `isProInstall` but returns `isPro`
  (`checkIsPro`). Behaviour correct (locks on `!isPro`); the name is a trap — if
  someone rewires it to the real `isProInstall`, Pro leaks on Pro-inactive.
- 3-state UI rule confirmed: **Pro-inactive (isProInstall=true, isPro=false) ==
  Free in the admin UI**, except the Licenses page + nav (force-unlock=isProInstall
  / allowOnProInstall). Full audit: deliverables/audit/3state/backend-ui.md.

## Server-side Pro gate — collapsed to one signal (v0.71.0)

**Historical (pre-0.71.0):** there were two divergent "is this install Pro?"
definitions — a strong one (verified `license_state[].status==='active'` + 14-day
heartbeat grace, used by the bootstrap + `hasPro()`) and a weak `license_tier`
one (used by `SettingsController::isProSetting()` + `aiboost_sitemap::isPro()`)
that leaked Pro after expiry. That whole split is **gone**.

**Now:** every server/runtime/UI gate routes through `PluginRegistry::isProActive()`,
which reads ONLY the perpetual `pro_activated` flag (see
`aiboost-pro-gating-rule.md`). No `license_state` walk, no `license_tier`, no
heartbeat hard-disable. Expiry is no longer a runtime concern — it only pauses
updates/support via the update server. **How to apply:** any new server/runtime
Pro gate must call `isProActive()`/`hasPro($sku)`; never re-derive from
`license_tier` or scan `license_state` directly.

## Pro plugins gate per-hook on hasPro() — except aiboost_social_pro (LEAK)

The `_pro` plugin entry points are thin skeletons (require_once + class_alias);
real logic is in `src/Extension/*`. The Free plugin always fires its
`EVENT_FILTER_*` (settings-gated, NOT license-gated), so each Pro listener MUST
gate itself on `PluginRegistry::hasPro($sku)`. schema_pro and aeo_pro do
(hasPro count 3 and 7); code_pro/hreflang_pro have no runtime yet. **aiboost_social_pro
has ZERO hasPro gate** (`onAiBoostFilterSocialProps` calls `OgTagProDecorator::decorate()`
unconditionally) → Pro OG/Twitter tags leak on a Pro-inactive frontend. Fix +
both DRIFTs handed to consolidation #537. Full audit: deliverables/audit/3state/server-plugins.md.

## Import endpoint bypasses the save-endpoint Pro hardening

`settings.save` is hardened against Free→Pro self-promotion (reads existing row
before merge, force carries-forward/strips `license_tier`/`license_state`/`dev_*`,
fail-closed stripLocked/stripProOptions). **`ImportController::upload()` does NONE
of this** — it writes the imported `settings` JSON verbatim to
`#__aiboost_settings`, and `mapLegacyParams()` even maps `jb_is_paid→license_tier`
and `license_key_value→license_key`. So an admin import (core.manage) can inject
`license_tier='pro'` or a fabricated active `license_state` and self-promote a
Free/Pro-inactive install. **How to apply:** any new code path that writes the
settings blob must run the same strip/carry-forward pipeline as settings.save —
never trust client-supplied `license_*`/`dev_*` keys.
