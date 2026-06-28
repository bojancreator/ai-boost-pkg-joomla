# Critic Review — Part 6 (Security / Breach-Resistance)

**Lens:** Security skeptic. Goal: find the worst REALISTIC breach and decide whether the
original verdict ("solid and unusually disciplined; core breach classes each closed") is
too generous. Every claim below was re-verified against the live code, not the prose.

**Bottom line:** The verdict HOLDS. After independently spot-checking the load-bearing
claims, I could not break any of the four breach classes the owner worries about
(custom-code injection, stored XSS, Pro-gate spoof, secret leak). The original assessment
is generous in *tone* but accurate in *substance*; its self-nominated weakest point
(surface #5 — distributed output-escaping) is real but correctly rated "low / monitor",
not a launch-blocker. I found no item that should be re-graded from "Closed" to "Open".

---

## What I independently verified (file:line evidence)

### Pro-gate spoof — claim "Closed" CONFIRMED
- `PluginRegistry::isProActive()` is literally `settingEnabled($settings,'pro_activated')`
  i.e. `(string)($settings['pro_activated'] ?? '0') === '1'` — single server-resolved flag
  (`PluginRegistry.php:375-378`, `:428-431`).
- The flag is on `SYSTEM_PRESERVED_KEYS` (`SettingsSaveDefinition.php:51`), so the save and
  import endpoints physically refuse to write it from client input. I read the actual list
  (`:42-62`) — `license_key`, `license_state`, `pro_activated`, `pro_installed`,
  `install_id`, plus dev overrides are ALL present. No dangerous key is missing, so the
  denylist has no hole.
- The only writer is `markPerpetualActivation()` (`PluginRegistry.php:599-609`), reached
  only via `saveLicenseState()` (`:533-549`), which flips the flag only when
  `coreLicenseActive()` is true — and that function `continue`s past every `int_*` key
  (`:583-594`), so a cheap integration add-on key cannot unlock the core bundle. Anti-leak
  confirmed in code, not just comment.
- `resolveRealStatus()` is fail-closed: returns `null` with no key, and only `'active'`
  when status is literally `'active'` AND not expired (`:669-692`).
- **Verdict on this surface: genuinely closed.** Editing the in-page `isPro` JS unlocks
  nothing — save, import and every runtime emitter re-derive Pro from the DB flag.

### Stored XSS / output-escaping — claim "verified clean" CONFIRMED at every sink I checked
This is the surface the original author flagged as weakest (#5: escaping is per-emitter,
not central). I treated it as the prime suspect and checked the actual sinks:
- **OG / Twitter tags** (user-controlled site_name, og_description_override, etc.):
  `OgTagBuilder::renderProps()` wraps BOTH attribute and content in
  `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (`OgTagBuilder.php:160-161`, `:171-172`).
- **Analytics** (GA4/GTM/Pixel IDs, custom event names+URLs, FB verify): every value passes
  `htmlspecialchars(...)` before emission (`AiBoostAnalytics.php:108,116,133,153,190,305,316,335,337,357`).
- **Schema JSON-LD** — the classic `</script>` breakout: encoded with
  `JSON_HEX_TAG | JSON_HEX_AMP` (`AiBoostSchema.php:154-158`), with a comment naming the
  stored-XSS vector it closes. The YOOtheme integration uses the SAME flags on its own
  separate sink (`AiBoostIntYootheme.php:53, 614-618`). Both JSON-LD sinks are hardened.
- **Canonical URL**: `htmlspecialchars($canonical)` before `addHeadLink` (`AiBoostCore.php:246`).
- **Title / meta-description templates**: written via Joomla `setTitle()` / `setMetaData()`
  (`AiBoostCore.php:500, 550`), which escape on render — no raw `addCustomTag`.
- I confirmed the front-end output-discipline rule is real: a repo-wide grep for
  `addCustomTag` in the system plugins finds it only in COMMENTS describing the old
  forbidden API, never as a live call. All live output goes through
  `HeadBlockBuilder::pushSection` / `BodyBlockBuilder::pushBody|pushFooter`.
- **The escaping is genuinely distributed**, so the original's "low, monitor" rating is the
  honest one: today it's clean, but the invariant lives in ~14 emitters, not one helper. A
  future emitter that forgets `htmlspecialchars` on a NEW non-`custom_code_*` field would
  reintroduce stored XSS. This is a maintainability risk, not a current defect.

### Custom-code injection — claim "by design, admin-trust" CONFIRMED
- `custom_code_head/body/footer` are pushed verbatim/unescaped (intended) only after
  `isClient('site')` + HTML-doc + `isEnabled()` gates (`AiBoostCode.php:160-216`).
- The only writer is `SettingsController::save()`, gated by `Session::checkToken()` +
  `authorise('core.manage', 'com_aiboost')` (`SettingsController.php:24-32`). Same trust
  model as Joomla's own Custom HTML modules. Not a front-end injection point.

### Secret leak (export/import) — claim "Closed" CONFIRMED
- Export strips every `SYSTEM_PRESERVED_KEYS` entry before writing the file
  (`buildExportPayload()`, `SettingsController.php:397-399`) — the license key never lands
  in a backup.
- Import denylist is `SettingsSaveDefinition::SYSTEM_PRESERVED_KEYS` *by reference*
  (`ImportController.php:39`), enforced by `unset()` (`:109-114`), behind CSRF + core.manage
  + 5 MB cap + JSON validation + merge-not-overwrite (`:43-77, :144`). Export and import
  cannot drift because they share ONE constant. This is the strongest part of the design.

---

## The single highest-risk item

**Surface #1 — custom-code injection (`aiboost_code` / `custom_code_*`), i.e. an authenticated
stored-XSS / script-injection surface available to any `core.manage` user.**

This is the worst *realistic* breach not because it is a bug — it is intentional — but
because it is the only place where, by design, attacker-controlled raw `<script>` reaches
every front-end page with NO escaping, and the protection is purely "we trust whoever holds
`core.manage`". Everything else (license forgery, key leak, upload RCE/SVG-XSS, SQL,
traversal) has an active fail-closed mechanism; this one has only an ACL boundary.

Why it tops the ranking over the other "closed" items:
- **Blast radius is total**: raw JS on every page = full session/credential theft for every
  visitor and admin, persisted in the DB blob.
- **Privilege required is lower than people assume**: `core.manage` on `com_aiboost`, NOT
  Super User. In a multi-author Joomla site, a Manager-level account that would *not*
  normally be trusted to edit templates can flip this on. The original assessment's framing
  ("any user who can edit custom HTML modules already has this power") is true for a typical
  2-admin site but understates the risk on a delegated-roles site, where com_aiboost manage
  rights may be granted more liberally than template/module-HTML rights.

**Is this a reason to block launch? No.** It is the inherent admin-trust model and removing
it would remove the feature. But two cheap hardenings would shrink the residual:
1. Gate the *custom-code save path* specifically on `core.admin` (or a dedicated
   `aiboost.editcode` ACL) rather than the broad `core.manage` used for all settings, so the
   raw-HTML power is not handed out with ordinary settings-edit rights.
2. Surface a Health warning when `custom_code_*` is non-empty AND more than one user group
   holds `core.manage` on the component — make the trust assumption visible to the owner.

---

## Where the verdict is generous but still correct

- The prose repeatedly says "textbook" / "strongest-hardened". That is a tone choice; the
  underlying mechanisms are in fact present and correct, so it is not misleading.
- Surface #5 (distributed escaping) is the one place a skeptic could argue for a higher
  grade. I verified every current sink is clean, so "Low, monitor" is defensible — but I
  would add recommendation #2 from the original (a central escaping test) to the
  *should-do-before-wide-launch* tier, not the optional tier, precisely because the
  invariant is spread across 14 files and WILL be the source of the next XSS regression if
  one appears.
- Legacy `ProGate::validateAndStoreLicense` writing a dead `license_tier` param
  (`ProGate.php`) is correctly rated low: I confirmed the active gate reads `pro_activated`,
  not `license_tier`, so the dead path cannot grant Pro. It is hygiene, not a hole.

## Nothing I would re-grade upward to "Open"
None of the four owner-worry breach classes survived the spot-check as exploitable. The
"Closed" labels on Pro-gate, export/import leak, media upload, and SQL are earned.
