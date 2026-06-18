# Architecture Refactor Plan

> **Status: evergreen methodology reference — not a status tracker.**
> The decision gates below are reusable for any future structural refactor.
> They do *not* describe in-flight work and are not a checklist to "finish".
> Current release status and remaining work live in `STATUS.md` (`ROADMAP-v0.5.md` is an
> archive — its Decision Log + Verification Log are kept as history). If a gate here ever
> conflicts with a shipped decision in the ROADMAP Decision Log, that decision wins.

This document is an architecture decision guide for structural refactors. It is
not a parallel task board. The forward work list stays in `BACKLOG.md`, and the
operating procedure stays in `OPERATING.md`. Product strategy and release
sequencing for v0.5 are in `docs/v0.5-product-direction.md`.

## Method: Architecture Decision Gates

Use these gates for any refactor that changes service boundaries, platform
abstractions, settings persistence, Pro gating, installer behavior, or shared
Joomla/WordPress logic.

### Settings Save Gate 0: Problem Statement

Define the exact problem, goal, and non-goals before editing code.

Required output:

- Problem
- Goal
- Non-goals
- Current behavior to preserve
- Release impact

Escalate to XHigh only if the problem itself is unclear or there are competing
product goals.

### Settings Save Gate 1: Architecture Sketch

Describe the current flow and target flow at file/class level.

Required output:

- Current flow
- Target flow
- New or changed services
- APIs or method signatures to introduce
- Backward-compatibility constraints
- Test strategy

Escalate to XHigh when there are multiple plausible designs or when the change
touches settings save, licensing, Pro gating, installer logic, or CMS boundaries.

### Gate 2: Thin Vertical Slice

Implement the smallest slice that proves the architecture without moving the
whole system.

Required output:

- Slice scope
- Files touched
- Tests added or updated
- Rollback path
- Validation commands

Escalate to XHigh before implementation if the slice touches shared Joomla /
WordPress boundaries, settings save, license state, or package integrity.

### Gate 3: Expansion

Expand only after the thin slice passes tests and staging smoke checks.

Required output:

- Remaining parts to migrate
- Repeated pattern confirmed by the slice
- Any new risks found during the slice

Use the normal model for mechanical repetition. Use XHigh again if the next
part introduces a new boundary or different failure mode.

### Gate 4: Compatibility Audit

Run a compatibility review before declaring the refactor complete.

Required checks:

- Free package has no Pro leakage.
- Pro-gated fields cannot be saved or unlocked from Free.
- Existing settings remain compatible.
- Joomla 5 and Joomla 6 behavior is unchanged unless intentionally changed.
- Package build succeeds.
- Staging smoke checks pass for affected Free and Pro sites.

Escalate to XHigh for review when the refactor changed more than one layer
(component, shared lib, plugin, build, or tests).

### Gate 5: Release Decision

Decide whether the refactor is safe for the current release.

Ship now if:

- The change is required for the release or removes a release blocker.
- Tests and package build pass.
- Staging verification is complete where applicable.
- Rollback is simple.

Hold if:

- The change is mostly future architecture.
- It changes data persistence or package behavior without staging coverage.
- It needs a broader review.

## Active Refactor: Make Settings Save Manifest-Driven

### Gate 0: Problem Statement

Problem: `SettingsController.php` accepts settings through a controller-level
whitelist. The manifest registry is already the intended source of truth for
options, defaults, tiers, and Pro metadata, but settings save still has a
separate list that can drift from the manifest.

Goal: derive accepted settings keys and basic field metadata from the manifest
registry so adding or removing an option requires fewer hand-edited lists and is
less likely to break Free/Pro gating.

Non-goals:

- Do not redesign the Vue settings UI in this refactor.
- Do not change the database schema.
- Do not change current setting names.
- Do not change Pro activation semantics.
- Do not port settings to WordPress in this slice.

Current behavior to preserve:

- Existing settings continue to save and load with the same keys.
- Free installs cannot persist locked Pro-only values.
- Pro installs keep current behavior.
- Unknown payload keys are ignored.
- Existing import/export behavior remains compatible.

Release impact: medium. This touches a hot path used by every settings save, so
it should not be bundled into a release without focused tests and staging
verification.

XHigh: required before Gate 2 implementation, because this touches settings save
and Pro gating behavior.

### Gate 1: Architecture Sketch

Code mapping finding from the first pass: the current settings save whitelist is
much wider than the static manifest. `SettingsController.php` accepts the full
historical settings payload, including legacy aliases, generated UI fields,
repeatable-row targets, QA/debug carry-forward keys, and section-level Pro-gated
fields. The static manifest currently covers only the manifest/codegen subset.

Therefore the first architecture slice must not replace the controller whitelist
with `Manifest\Registry::all()` directly. The safe target is a save-definition
adapter that combines manifest fields with explicit legacy/save-only keys until
manifest coverage is intentionally expanded.

Current flow:

```text
SettingsController::save()
  -> hardcoded accepted field list
  -> merge incoming payload into current settings
  -> strip Pro-locked values
  -> persist JSON in #__aiboost_settings
```

Target flow:

```text
SettingsController::save()
  -> SettingsSaveDefinition::acceptedKeys()
  -> SettingsSaveDefinition::defaults()
  -> merge incoming payload into current settings
  -> strip Pro-locked values using existing ProFeatureRegistry rules
  -> persist JSON in #__aiboost_settings
```

Proposed new service:

```text
AiBoost\Lib\SettingsSaveDefinition
  - acceptedKeys(): array
  - defaults(): array
  - manifestKeys(): array
  - legacyKeys(): array
  - saveOnlyKeys(): array
  - field(string $key): ?array
  - fields(): array
```

Definitions:

- `manifestKeys()` comes from `AiBoost\Lib\Manifest\Registry`.
- `legacyKeys()` keeps old database and pre-Vue payload keys writable until a
  deliberate migration removes them.
- `saveOnlyKeys()` covers payload targets that are not normal manifest fields,
  such as repeatable-row JSON targets, debug carry-forward fields, and endpoint
  bookkeeping.
- `acceptedKeys()` is the union used by `SettingsController::save()`.

The adapter should be read-only and deterministic. It should not write settings,
check the Joomla session, or decide whether an install is Pro. Those concerns
stay in the controller and `ProFeatureRegistry` for the first slice.

First implementation slice:

- Add `SettingsSaveDefinition` as a read-only adapter over the existing manifest
  registry plus explicit legacy/save-only key lists.
- Add tests proving it returns known manifest keys, current legacy keys, and
  defaults for manifest-backed fields.
- Add a parity test comparing `SettingsSaveDefinition::acceptedKeys()` to the
  current controller whitelist before replacing the controller behavior.
- Add a small explicit allowlist for manifest-backed generated fields that are
  currently missing from the controller whitelist, then decide whether those
  should be accepted in the same slice or tracked as a separate save bug.
- Replace only the accepted-key source in `SettingsController::save()` after the
  parity test is green.
- Leave deeper type coercion and default hydration for a later slice unless the
  test shows it is required for parity.

Likely files:

- `component/lib/src/SettingsSaveDefinition.php`
- `component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php`
- `component/tests/Lib/Manifest/*` or a new focused settings manifest test
- Existing manifest files only if missing metadata is discovered

Validation commands:

```bash
php -l component/lib/src/SettingsSaveDefinition.php
php -l component/com_aiboost/admin/src/Administrator/Controller/SettingsController.php
composer phpunit -- --filter ManifestProRegistryParityTest
composer phpunit -- --filter SettingsSaveDefinition
python scripts/build-package-zip.py --target all --no-codegen-check
```

Staging validation:

- Save settings on a Pro staging site and confirm settings persist.
- Save settings on a Free staging site and confirm Pro-only fields remain locked.
- Confirm package build still passes strict Pro-leakage verification.

### Gate 2 Entry Criteria

Before implementation, confirm with XHigh:

- Whether `SettingsSaveDefinition` should live in `AiBoost\Lib` or under
  `AiBoost\Lib\Manifest`.
- Whether the adapter should initially preserve an explicit legacy/save-only key
  list or require broader manifest coverage before any controller replacement.
- Whether defaults should be applied in the first slice or deferred.
- Whether accepted keys should include runtime integration fields contributed by
  `onAiBoostRegisterFields`.
- Whether current import/export paths share the same save whitelist and need the
  same adapter.

### Gate 2 Slice Outcome: Settings Save Adapter

The first vertical slice is complete. `SettingsController::save()` now delegates
accepted-key resolution to `AiBoost\Lib\SettingsSaveDefinition` instead of a
controller-local whitelist. The adapter preserves the historical compatibility
floor, accepts safe static and runtime manifest fields, and avoids replacing the
save path with `Manifest\Registry::all()` directly because the manifest does not
yet cover every legacy/save-only key.

The slice also found and fixed a real Pro-gating drift for advanced opening
hours: Vue payload aliases, legacy save keys, and `ProFeatureRegistry` locked
settings now cover the same key families. Focused settings/pro-gating tests,
the full PHPUnit suite, package build, and strict Pro-leakage verification pass.

Strict Pro gating parity is also closed: the accepted exception list was removed
from `ManifestProRegistryParityTest.php`, formerly tolerated hreflang,
breadcrumb, and HowTo manifest keys are now covered by `ProFeatureRegistry`, and
Joomla 6 staging verification confirmed Free cannot persist those Pro fields
while Pro can.

### Gate 3 Audit Outcome: Settings Save Coverage Map

The first expansion step is test-only and does not change runtime save behavior.
`SettingsSaveDefinitionTest` now documents the current coverage boundary:

- `acceptedKeys()` currently exposes 318 accepted save keys.
- The static/runtime manifest contributes 44 keys.
- The historical compatibility floor contributes 312 legacy/save-compatible
  keys, leaving 274 accepted keys that are still compatibility-only rather than
  manifest-backed.
- `saveOnlyKeys()` remains explicit. `meta_custom_events` is already
  manifest-backed; `meta_pixel_id`, `meta_pixel_ids`, `gsc_verification_code`,
  and `gsc_codes` are still compatibility-only controller plumbing.
- `ImportController` remains a separate persistence boundary with its own
  denylist and does not use `SettingsSaveDefinition` yet.

Next safe expansion: migrate a small group of compatibility-only fields into
manifest metadata only when their UI ownership, defaults, type, tier, and import
behavior are clear. Do not make `ImportController` reuse
`SettingsSaveDefinition` without a fresh XHigh review because import accepts a
different payload shape and currently has its own license/identity denylist.

### Gate 3 Expansion Slice: Meta Pixel Save Metadata

The first runtime-neutral expansion moved the Meta Pixel save-only pair into the
existing OpenGraph/social manifest metadata:

- `meta_pixel_id` is now manifest-backed with `type=text`, `default=''`,
  `tier=pro`, `sku=og`, `tab=social`, and `section=pixel`.
- `meta_pixel_ids` is now manifest-backed with `type=json`, `default='[""]'`,
  `tier=pro`, `sku=og`, `tab=social`, and `section=pixel`.
- No controller, import, stripping, or persistence behavior changed. These keys
  were already accepted by settings save and already locked by
  `ProFeatureRegistry` through `section:social.pixel`.
- Coverage moved from 44 to 46 manifest keys. The accepted save surface is still
  318 keys; 272 accepted keys remain compatibility-only.

The Google Search Console save-only pair (`gsc_verification_code`, `gsc_codes`)
remains compatibility-only for now. It is Pro-gated by
`section:analytics.non_ga4`, but the manifest layer does not yet have a clear
Analytics SKU/ownership model. Add that model before moving GSC metadata into a
manifest file.

### Gate 3 Expansion Slice: Custom Code Scope Metadata

The next runtime-neutral expansion filled in missing Custom Code manifest
metadata for the per-field scope/menu keys:

- `custom_code_head_menu_ids` is now manifest-backed as `type=json`,
  `default='[]'`, `tier=pro`, `sku=code`, `tab=code`, `section=head`.
- `custom_code_body_scope` and `custom_code_footer_scope` are now
  manifest-backed as `type=select`, `default='all'`, `tier=pro`, `sku=code`.
- `custom_code_body_menu_ids` and `custom_code_footer_menu_ids` are now
  manifest-backed as `type=json`, `default='[]'`, `tier=pro`, `sku=code`.
- No controller, import, stripping, or persistence behavior changed. These keys
  were already accepted by settings save and already locked through
  `section:code`.
- Coverage moved from 46 to 51 manifest keys. The accepted save surface is still
  318 keys; 267 accepted keys remain compatibility-only.

The legacy shared aliases (`custom_code_scope`, `custom_code_menu_ids`) remain
compatibility-only. They are still accepted and stripped on Free saves, but they
represent upgrade/fallback payload compatibility rather than current UI fields.

### Gate 3 Expansion Slice: AEO llms Metadata

The next runtime-neutral expansion filled in active AEO llms manifest metadata:

- `llmstxt_recent_articles` is now manifest-backed as `type=number`,
  `default='5'`, `tier=free`, `sku=aeo`, `tab=aeo`, `section=llmstxt`.
- `llmstxt_custom_pages` and `llmstxt_faq_items` are now manifest-backed as
  `type=json`, `default='[]'`, `tier=free`, `sku=aeo`, `tab=aeo`,
  `section=llmstxt`.
- `llms_full_max_articles` is now manifest-backed as `type=number`,
  `default='500'`, `tier=pro`, `sku=aeo`, `tab=aeo`, `section=llms_full`.
- No controller, import, stripping, or persistence behavior changed. These keys
  were already accepted by settings save; `llms_full_max_articles` was already
  locked on Free saves through `section:aeo.llms_full`.
- Coverage moved from 51 to 55 manifest keys. The accepted save surface is still
  318 keys; 263 accepted keys remain compatibility-only.

`llmstxt_custom_pages_en` remains compatibility-only because it has no current
Vue/runtime owner in the active AEO UI. Treat it as legacy payload compatibility
until multilingual llms content has its own explicit boundary review.

### Gate 3 Expansion Slice: AEO Robots Metadata

The next runtime-neutral expansion filled in active AEO robots metadata:

- `robots_custom_scrapers` and `robots_custom_rules` are now manifest-backed as
  `type=textarea`, `default=''`, `tier=free`, `sku=aeo`, `tab=aeo`,
  `section=robots`.
- The canonical SEO scraper toggles (`scraper_ahrefsbot`,
  `scraper_semrushbot`, `scraper_dotbot`, `scraper_mj12bot`,
  `scraper_blexbot`, `scraper_rogerbot`, `scraper_screamingfrog`,
  `scraper_sitebulb`, `scraper_siteauditor`, `scraper_serpstatbot`,
  `scraper_bytespider`, `scraper_petalbot`) are now manifest-backed as
  `type=toggle`, `default='0'`, `tier=free`, `sku=aeo`, `tab=aeo`,
  `section=robots_scrapers`.
- No controller, import, stripping, robots generation, or persistence behavior
  changed. These keys were already accepted by settings save and consumed by
  `RobotsTxtBuilder` / `RobotsTxtManager`.
- Coverage moved from 55 to 69 manifest keys. The accepted save surface is still
  318 keys; 249 accepted keys remain compatibility-only.

`enable_robots` and `robots_auto_sync` remain compatibility-only because their
current UI owner is still the General tab. `robots_block_scrapers` also remains
compatibility-only because it is a migrated legacy aggregate, not the current
canonical per-bot payload.

### Gate 3 Expansion Slice: Safe Core/Social Batch

To reduce the remaining compatibility surface faster without changing runtime
behavior, the next larger batch added metadata for fields with clear existing
UI/runtime ownership and no new SKU requirement.

Core-owned sitemap/canonical metadata added as `tier=free`, `sku=core`,
`tab=sitemap`:

- XML sitemap base controls: `enable_sitemap`, `include_articles`,
  `include_categories`, `include_menu_items`, `sitemap_limit`,
  `default_changefreq`, `default_priority`.
- Sitemap exclusions: `exclude_category_ids`, `exclude_menu_ids`.
- Legacy sitemap ping toggles: `ping_google`, `ping_bing`. Defaults remain `1`
  to match the existing runtime fallback in the sitemap plugin.
- Crawl hygiene controls: `redirect_404_log_enabled`, `enable_canonical`,
  `canonical_url_map`.

Social/OG metadata added to the existing `og` SKU:

- Free OG/Twitter controls: `og_image_width`, `og_image_height`,
  `enable_per_article_fields`, `enable_article_og_type`, `fb_app_id`,
  `twitter_site_handle`.
- Pro Pixel setting: `pixel_consent_mode`, covered by the existing
  `section:social.pixel` server-side lock.

No new `sitemap` SKU was introduced in this batch. Pro sitemap fields such as
`include_tags`, `ping_on_publish`, `enable_sitemap_index`, `enable_image_sitemap`,
`enable_news_sitemap`, `news_category_id`, and `news_publication_name` remain
compatibility-only until the SKU/lock ownership model is explicit. Legacy
aliases like `og_site_name`, `og_default_image`, and `sitemap_include_*` also
remain compatibility-only.

Coverage moved from 69 to 90 manifest keys. The accepted save surface is still
318 keys; 228 accepted keys remain compatibility-only.

Keep `PluginRegistry` complexity as a separate XHigh candidate. It is a
licensing/Pro hot path and should not be folded into mechanical cleanup or the
settings-save expansion unless Gate 0/Gate 1 are repeated for that boundary.

### Gate 3 Expansion Slice: Schema/Org Active Batch

The next larger runtime-neutral expansion filled in active Schema.org and
Organization metadata with clear current UI/runtime ownership.

Schema-owned Free metadata added as `tier=free`, `sku=schema`:

- Business basics: `specific_price_range`, `specific_serves_cuisine`,
  `specific_available_service`.
- Organization identity/contact/social: `org_name`, `org_description`,
  `org_logo`, `org_url`, `org_email`, `org_phone`, `social_facebook`,
  `social_instagram`, `social_youtube`, `social_twitter`, `social_linkedin`,
  `social_tiktok`.
- Address, geo, and rating: `org_address_street`, `org_address_city`,
  `org_address_state`, `org_address_zip`, `org_address_country`,
  `org_latitude`, `org_longitude`, `rating_value`, `rating_count`,
  `rating_best`, `rating_worst`, `rating_source`.
- Simple schema controls: `schema_opening_hours`, `enable_search_action`.

Schema Pro payload metadata added only where `ProFeatureRegistry` already has a
server-side section lock:

- FAQ payload: `manual_faq_scope`, `faq_items`, `schema_faq_output_type`.
- HowTo/Event payload: `schema_howto`, `events_category_id`.
- Active advanced-hours UI keys: `schema_hours_temp_closed`,
  `schema_holiday_closed`, and `hours_mon|tue|wed|thu|fri|sat|sun` variants for
  `opens`, `closes`, and `closed`.

No controller, import, stripping, schema generation, or persistence behavior
changed. The newly manifest-backed Pro payload keys were already accepted by
settings save and already stripped on Free saves through existing
`section:schema.*` locks.

Coverage moved from 90 to 147 manifest keys. The accepted save surface is still
318 keys; 171 accepted keys remain compatibility-only.

Deferred schema/org keys remain compatibility-only by design:

- Legacy duplicate aliases such as `schema_logo_url`, `schema_email`,
  `schema_social_*`, `schema_rating_*`, and `schema_price_range`.
- Translation/import payload such as `org_name_en`, `org_description_en`,
  `manual_faqs_en`, and `schema_events_en`.
- `social_pinterest`, because no active Org tab owner was confirmed in this
  slice.
- Full day-name and two-letter opening-hours aliases (`hours_monday_*`,
  `hours_mo_*`, etc.), which stay accepted and Free-stripped for compatibility
  but are not canonical manifest fields.
- Pro-only specific business detail fields (`specific_star_rating`,
  `specific_checkin_time`, `specific_checkout_time`, `specific_area_served`,
  `specific_pets_allowed`, and related `schema_*` aliases) until there is an
  explicit field lock or a separate schema Pro detail ownership review.

### Gate 3 Expansion Slice: Core General/Debug Batch

The next small runtime-neutral expansion filled in active General and Debug tab
metadata in the existing `core` SKU.

Core-owned Free metadata added as `tier=free`, `sku=core`:

- Domain controls: `auto_domain_detection`, `manual_domain`.
- Current General-tab robots controls: `enable_robots`, `robots_auto_sync`.

Core-owned Pro metadata added where `ProFeatureRegistry` already strips the
whole Debug section on Free saves:

- `debug_mode`, `hide_comments`, `staging_mode`.

No controller, robots generation, debug behavior, staging behavior, import, or
persistence behavior changed. The Debug keys were already accepted by settings
save and already locked on Free saves through `section:debug`.

Coverage moved from 147 to 154 manifest keys. The accepted save surface is still
318 keys; 164 accepted keys remain compatibility-only.

Deferred General/Debug keys remain compatibility-only by design:

- `show_advanced_options`, because it belongs to the legacy PHP settings view
  rather than the active Vue settings surface.
- `dev_license_preview` and `dev_force_free_tier`, because they are DB-only QA
  overrides deliberately filtered separately by `SettingsController` and should
  not become manifest-backed admin fields.

### Gate 3 Expansion Slice: AEO IndexNow Payload

The next small runtime-neutral expansion completed the current IndexNow manifest
metadata in the existing `aeo` SKU.

AEO Pro metadata added under `section=indexnow`:

- `indexnow_api_key` as `type=text`, `default=''`, `tier=pro`, `sku=aeo`.
- `indexnow_auto_submit` as `type=toggle`, `default='0'`, `tier=pro`,
  `sku=aeo`.

No controller, IndexNow key serving, auto-submit behavior, import, stripping, or
persistence behavior changed. These keys were already accepted by settings save
and already stripped on Free saves through `section:aeo.indexnow`.

Coverage moved from 154 to 156 manifest keys. The accepted save surface is still
318 keys; 162 accepted keys remain compatibility-only. No `indexnow_*` keys
remain compatibility-only.

### Gate 3 Expansion Slice: Schema Business Details Metadata

The next runtime-neutral Schema expansion moved active conditional business
detail fields from compatibility-only save keys into the existing `schema` SKU.

Schema Pro metadata added under `section=business_details`:

- `specific_star_rating` as `type=select`, `default=''`, `tier=pro`, `sku=schema`.
- `specific_checkin_time` as `type=text`, `default=''`, `tier=pro`, `sku=schema`.
- `specific_checkout_time` as `type=text`, `default=''`, `tier=pro`, `sku=schema`.
- `specific_pets_allowed` as `type=select`, `default=''`, `tier=pro`, `sku=schema`.
- `specific_area_served` as `type=text`, `default=''`, `tier=pro`, `sku=schema`.

Because these fields are conditional on Pro-only schema type choices, the slice
also added `section:schema.business_details` to `ProFeatureRegistry` and wrapped
the active Hotel and Real Estate cards in the same Vue `ProGate`. That keeps UI
preview, server stripping, and manifest metadata in parity. No schema output,
controller, import, or persistence behavior changed beyond stripping these Pro
payload keys from crafted Free saves.

Coverage moved from 156 to 161 manifest keys. The accepted save surface is still
318 keys; 157 accepted keys remain compatibility-only. No active `specific_*`
business detail keys remain compatibility-only.

### Gate 3 Expansion Slice: Core SEO Template Metadata

The next runtime-neutral core expansion moved the active title/meta template
settings used by `aiboost_core` from compatibility-only save keys into the
existing `core` SKU.

Core Free metadata added under `tab=general`, `section=seo_templates`:

- Title template keys: `title_template`, `title_separator`,
  `title_template_home`, `title_template_article`, `title_template_category`,
  `title_template_search`, `title_template_tag`, `title_template_default`,
  `title_template_maxlen`.
- Meta description template keys: `meta_desc_template`,
  `meta_desc_template_article`, `meta_desc_template_category`,
  `meta_desc_template_default`, `meta_desc_maxlen`.

No Vue admin UI, title rendering, meta description rendering, controller,
import, or persistence behavior changed. Defaults mirror the runtime fallbacks:
empty templates, `title_separator=' | '`, `title_template_maxlen='0'`, and
`meta_desc_maxlen='160'`.

Coverage moved from 161 to 175 manifest keys. The accepted save surface is still
318 keys; 143 accepted keys remain compatibility-only. No title/meta template
keys remain compatibility-only.

### Gate 3 Expansion Slice: Hreflang Active Sitemap Toggle

The next small existing-SKU expansion moved the active Sitemap tab hreflang
toggle into the existing `hreflang` SKU.

Hreflang Pro metadata added under `tab=sitemap`, `section=hreflang`:

- `enable_hreflang` as `type=toggle`, `default='0'`, `tier=pro`,
  `sku=hreflang`.

No Vue admin UI, sitemap rendering, controller, import, or persistence behavior
changed. The key was already inside the `section:sitemap.advanced` Vue `ProGate`
and already stripped from crafted Free saves through `ProFeatureRegistry`; this
slice only made the active save key manifest-backed. The older
`sitemap_hreflang` key remains compatibility-only until a deliberate alias
review.

Coverage moved from 175 to 176 manifest keys. The accepted save surface is still
318 keys; 142 accepted keys remain compatibility-only.

### Gate 3 Stop Point: Clear Expansion Exhausted

A final strict scan did not find another safe manifest expansion that meets all
three requirements at once: existing SKU ownership, active UI/runtime ownership,
and clear Free/Pro status without a new product decision.

Keep these groups compatibility-only until a deliberate decision is made:

- License/dev state keys such as `license_key`, `license_tier`,
  `dev_license_preview`, and `dev_force_free_tier`; these belong to the license
  boundary, not feature manifest metadata.
- Analytics/GSC/GTM keys such as `enable_ga4`, `ga4_measurement_id`,
  `enable_gtm`, `gtm_container_id`, `gsc_codes`, and verification fields;
  there is no static `analytics` SKU yet.
- Pro sitemap keys such as `enable_sitemap_index`, `enable_image_sitemap`,
  `enable_news_sitemap`, `news_category_id`, `news_publication_name`,
  `priority_*`, and `ping_on_publish`; these are active and gated, but there is
  no static `sitemap` SKU yet, and assigning them to `core` would be a product
  ownership decision rather than a mechanical refactor.
- Legacy/import aliases such as `sitemap_hreflang`, `og_site_name`,
  `og_default_image`, `schema_events_*`, `website_schema_search_enabled`,
  `article_schema_type_auto`, language-suffixed AEO/FAQ keys, and old opening
  hours aliases; current SPA/runtime ownership uses newer canonical keys.
- `enable_x_robots_header`; AEO Pro runtime still reads it, but the active AEO
  SPA tab does not expose this setting today, so manifest ownership needs an
  explicit UI/product decision.

At this point the remaining work is no longer a safe expansion batch. The next
architecture step should be a small decision record for Analytics and Sitemap
SKU ownership, followed by targeted manifest expansion if those decisions are
approved.

## Planned Refactor: Admin UX / Navigation Information Architecture

This track covers the `Admin UX / navigation` section in `BACKLOG.md`: sidebar
grouping, visible menu labels, settings page placement, deep links, Health fix
targets, docs, and tests.

This is primarily an information architecture refactor. It should avoid stored
settings churn unless a separate migration is deliberately planned.

### Admin IA Gate 0: Problem Statement

Problem: the admin navigation and settings grouping still reflect internal
feature/plugin boundaries more than the mental model of a normal Joomla admin.
Several technical SEO, crawler, analytics, social, and identity controls are in
places that make sense historically but are harder to discover during setup.

Goal: reorganize the admin sidebar and settings surfaces into clearer product
areas: `OVERVIEW`, `SETUP`, `SEO`, `AI VISIBILITY`, `TOOLS`, and `ADVANCED`, while
keeping existing stored setting keys and route aliases stable where possible.
See `docs/v0.5-product-direction.md §2` for the accepted v0.5 target menu.

Non-goals:

- Do not rename database setting keys just to match new labels.
- Do not change feature behavior in this refactor.
- Do not redesign the whole admin visual system.
- Do not migrate data unless a separate migration is explicitly approved.
- Do not remove old query tab aliases such as `tab=org`, `tab=aeo`, `tab=social`,
  `tab=analytics`, `tab=sitemap`, or `tab=code`.

Current behavior to preserve:

- Existing deep links continue to land on the correct page or scroll to the
  moved field.
- Pro preview behavior remains for normal Pro pages.
- `Import` stays hidden on Free/unlicensed installs.
- Existing setting keys keep loading and saving.
- Existing Health fix actions still guide the admin to the relevant field.

Release impact: medium. The change is visible and touches navigation, route
metadata, generated or hand-written settings tabs, Health target links, docs,
and screenshots. It can ship as a focused UX/navigation release if deep-link
compatibility is preserved.

XHigh: recommended before implementation for final wording and information
architecture review. Required if implementation would rename route IDs or stored
settings keys.

### Admin IA Gate 1: Architecture Sketch

Current flow:

```text
Sidebar groups
  -> route labels/meta
  -> Settings tab IDs and labels
  -> Health/fix links target old tabs and fields
```

Target flow:

```text
Admin navigation map
  -> sidebar groups and visible labels
  -> route aliases for old query tabs
  -> field-location map for moved settings
  -> Health/fix links and docs use the new visible locations
```

Preferred implementation shape:

- Introduce or centralize a small admin navigation/field-location map rather than
  scattering label and tab moves across unrelated files.
- Keep old tab IDs as aliases where practical, especially for `org`, `aeo`,
  `social`, `analytics`, `sitemap`, and `code`.
- Treat new visible names as labels, not storage keys.
- Move fields by UI placement first; defer manifest key renames.

Likely files:

- `component/com_aiboost/vue-admin/src/Sidebar.vue`
- `component/com_aiboost/vue-admin/src/router.js`
- `component/com_aiboost/vue-admin/src/App.vue` or settings shell files
- `component/com_aiboost/vue-admin/src/tabs/*`
- `component/lib/src/Manifest/*.php` only for metadata/section placement if the
  current UI reads sections from manifests
- `component/lib/src/HealthCheckService.php`
- `component/lib/src/ConflictDetector.php`
- docs pages and screenshots that show old navigation
- tests that assert route labels, tab names, Pro menu behavior, or Health targets

First implementation slice:

- Rework the sidebar grouping and visible menu labels only.
- Add route/query aliases so old links still resolve.
- Move `Organization` visible label to `Site Identity` without changing its
  internal setting keys.
- Update docs/tests for the sidebar labels touched by this slice.
- Do not move field cards between tabs in the first slice unless required by the
  route alias work.

Second implementation slice:

- Create `Technical SEO` and move canonical/404 UI placement there.
- Update Health/fix links for `enable_canonical` and `redirect_404_log_enabled`.
- Keep old `tab=sitemap&field=...` links as aliases to the new location.

Third implementation slice:

- Create `Crawlers & Robots` and move robots/crawler controls there.
- Update Health/fix links for `enable_robots`, robots preview, and crawler rules.
- Keep `aeo` setting keys and route aliases unless a migration is approved.

Fourth implementation slice:

- Move Meta Pixel into `Analytics & Tracking`.
- Refocus `Social Meta`, `Schema.org`, `Sitemap`, `AI Search / AEO`, `Custom
  Code`, and `Debug` ordering.
- Update docs, screenshots, tests, and Health links together.

Validation commands:

```bash
Push-Location component/com_aiboost/vue-admin; pnpm run build; Pop-Location
python scripts/build-package-zip.py --target all --no-codegen-check
```

Staging validation:

- Open each sidebar group and confirm visible ordering matches `BACKLOG.md`.
- Confirm old deep links still land on the moved setting or page.
- Confirm Health fix actions navigate to the new visible locations.
- Confirm Free/unlicensed installs keep Pro preview behavior and keep `Import`
  hidden.
- Confirm no settings keys are lost after saving on Free and Pro staging.

### Admin IA Gate 2 Entry Criteria

Before implementation, confirm:

- The final visible labels in `BACKLOG.md` are accepted.
- Which old tab IDs remain as route aliases.
- Whether field movement is driven by manifest metadata, hand-written tab layout,
  or a central field-location map.
- Which docs/screenshots are in scope for the first slice.
- Whether XHigh should review only wording/IA or also route alias design.

## Escalation Rules For This Refactor Set

Use XHigh for:

- Manifest-driven settings save design and first implementation slice.
- Admin navigation IA wording and route/deep-link compatibility review before the
  first UX/navigation implementation slice.
- `AiBoostCore.php` service-boundary design.
- WordPress vertical slice architecture and WordPress API correctness.
- Final review of any change that touches settings save plus Pro gating in the
  same patch.

Do not require XHigh for:

- Closing known Pro gating exceptions after the manifest save layer is stable.
- Small label-only admin navigation updates after the IA direction is accepted.
- Small manifest metadata fixes.
- Test-only parity updates.
- Build configuration fixes.

## Next Architecture Refactors

### Admin UX / Navigation Information Architecture

Gate requirement: required before implementation because the change touches
navigation, settings placement, Health fix targets, docs, and backwards-compatible
deep links. It should be handled as focused UX/navigation slices, starting with
sidebar grouping and labels before moving field cards between pages.

XHigh: recommended for final wording and required if route IDs, setting keys, or
stored data would change.

### Thin Joomla Plugin Classes Into Platform Entrypoints

Gate requirement: start with one service extraction from `AiBoostCore.php`, not
the whole plugin. Canonical URL handling is the preferred first slice because it
has visible output and can be tested independently.

XHigh: required for Gate 1 service-boundary design and Gate 4 review.

### WordPress Organization/WebSite Vertical Slice

Gate requirement: hold until the shared settings and service-boundary work is
stable. The slice should emit only Organization and WebSite schema through a
minimal WordPress plugin skeleton.

XHigh: required before Gate 1 and before implementation.
