# Conflict Resolution Redesign — marker/signature-based (Deliverable B)

**Status:** **Phase 1b SHIPPED v0.76.2** (2026-06-13) — OG-set + AI-meta dedup, head-scoped,
wired into finalize, 3 distinct modes, Health off-gate. Verified live on offroadbalkans
(4SEO og:title 2→1, our schema preserved). **Phase 1c PENDING:** single-instance JSON-LD
dedup (must decode each node → TOP-LEVEL `@type`, handle `@graph`; an adversarial review
caught that a substring `@type` match wrongly trims our Article node's nested publisher
Organization) and analytics GA4/GTM/Pixel dedup (multi-part + body `<noscript>` via
BodyBlockBuilder). **Phase 2 PENDING:** retire `shouldSkip()` for og/analytics/schema.
**Tier:** Opus. **Supersedes the dedup role of:** `DocumentInspector::shouldSkip()` (kept for canonical/hreflang).

## Why

Today `DocumentInspector::shouldSkip()` decides whether to emit each tag at
`onBeforeCompileHead` — too early. Extensions like **4SEO (`forseo`)** inject
their tags later, by rewriting the finished HTML, so at decision time ours sees
"no conflict" and emits → duplicate. Verified live: offroadbalkans shows two
`og:title` (one `class="4SEO_ogp_tag"`, one ours).

Owner's principle (adopted): **everything inside our `<!-- AI Boost for Joomla -
Start/End -->` block is ours; everything outside is third-party. At the last
moment (`finalize`, when the whole page exists) trim OUR tag per `conflict_mode`;
NEVER strip theirs.** This needs no per-extension detection for the dedup — only
"does a competing signal exist outside our block?". It generalises to every
present and future SEO tool.

## The three modes (now genuinely distinct — owner decision)

Today `aggressive` and `off` are identical (`DocumentInspector.php:60-63`: both
`!== cooperative` → always emit). Make them distinct:

| Mode | Dedup our block at finalize? | Health conflict warnings? |
|---|---|---|
| `cooperative` (default) | YES — trim our duplicate | YES |
| `aggressive` | NO — emit all (duplicates allowed) | YES |
| `off` | NO — emit all | **NO** — suppressed ("I know, stop telling me") |

Update the `<option>` copy in `Manifest/core.php` (conflict_mode field) +
`TechnicalSeoTab.vue` to describe these three.

## Where it runs

`HeadBlockBuilder::finalize()` — between `$block = self::render($version)`
(HeadBlockBuilder.php:271) and the `FilterDispatcher` hook (`:281-289`), so
bridges see an already-deduped block. At that instant the page body is the full
third-party head and **our block is NOT yet spliced**, so "outside our block" =
the entire current `$body` — exact, race-free (HeadBlockBuilder.php:254-296).
Mirror in `BodyBlockBuilder::finalize()` for the GTM/Pixel `<noscript>` region.

## Settings plumbing

`HeadBlockBuilder`/`BodyBlockBuilder` are static accumulators with no settings.
Add `setConflictMode(string $mode)` mirroring the existing `setHideComments()`
(HeadBlockBuilder.php:139). `aiboost_core` (ordering=1) calls it from
`onBeforeCompileHead` with `$settings['conflict_mode']`. Default `'cooperative'`.
No change to `finalize()` signatures (avoids touching every plugin's caller).

## The trim — pure, testable core

```php
public static function trimBlockConflicts(string $block, string $theirs, string $mode): string
```
Pure function (no Joomla) so it is fully unit-testable in isolation. Returns
`$block` unchanged unless `strtolower($mode) === 'cooperative'`. For each catalogue
entry whose `detect` regex matches `$theirs`, remove the matching span(s) from
`$block` with its `trim` regex; finally collapse `\n{3,}` → `\n\n`.

### Signature catalogue

Reuse `DocumentInspector`'s detect regexes (DocumentInspector.php:122-145) for the
"present in theirs?" test. Trim rules act ONLY on our block string.

| Signal | detect in theirs | trim from our block | policy |
|---|---|---|---|
| **OpenGraph (whole set)** | any `property="og:…"` **with a value we own** (og:title/url/type) | remove our entire `<!-- OpenGraph & Twitter -->` section (label line through to the next `<!-- … -->` section marker or block end) | **all-or-nothing**: one emitter owns OG; never leave a mixed set |
| Organization/LocalBusiness JSON-LD | `@type":"(Organization\|LocalBusiness\|…subtypes…)"` (the DocumentInspector list) | remove only OUR `<script type="application/ld+json">…</script>` node(s) whose top-level `@type` is in that single-instance set | per-@type; **never** touch BreadcrumbList/FAQPage/Article/Product/WebPage/ItemList (legitimately repeatable) |
| GA4 | `googletagmanager.com/gtag/js` or `gtag('config'` | remove our GA4 `<script>`(src+inline) | single per measurement id |
| GTM | `googletagmanager.com/gtm.js` | remove our GTM head `<script>` **and** the body `<noscript>` (BodyBlockBuilder) | single per container |
| Meta Pixel | `connect.facebook.net` or `fbq('init'` | remove our Pixel `<script>` + body `<noscript>` | single per pixel id |
| AI verification meta | `name="ai-content-verified"` | remove our `<meta name="ai-content-verified">` | single |

NOT trimmed here (stay an early decision, emitted via Joomla `addHeadLink` →
land OUTSIDE our block, so finalize can't trim them out of it):
- **canonical** — keep `DocumentInspector::shouldSkip(SIG_CANONICAL)` at emit time.
- **hreflang** — legitimately multiple; native stream.

### Reporting "what was trimmed"

Each trim records `noteSkip(section, "<sig> already present in page")` →
surfaces as a `<!-- Skipped: OpenGraph — already present in page -->` line (when
`hide_comments` off) and a Health row "AI Boost trimmed N tag types to avoid
duplicates: …". The owner sees WHAT was deduped without us identifying WHO.

## ConflictDetector / Health gate (mode `off`)

In `HealthCheckService` (the `new ConflictDetector(...)->scan()` loop at
HealthCheckService.php:245-248) skip the conflict scan when
`conflict_mode === 'off'`. All other modes keep the warnings. (Also leave the
4seo→`forseo` etc. element-name fix from v0.76.1 in place.)

## The safety invariant (structural, not by-care)

`finalize()` only ever **adds** our (possibly trimmed) block to the body via the
substring splice. `trimBlockConflicts` mutates ONLY the in-hand `$block` string;
no regex ever deletes from `$body`. Therefore a foreign tag can NEVER be stripped
— guaranteed by construction. This is the property the owner asked for.

## Rollout

- **Phase 1:** add `trimBlockConflicts` + catalogue (OG-set, Org JSON-LD, GA4/GTM/
  Pixel, AI meta) + `setConflictMode` + BodyBlockBuilder parallel + the 3-mode
  semantics + Health `off` gate. Keep the early `shouldSkip()` in parallel
  (idempotent — if it already skipped, our block has nothing to trim; if it
  didn't because the foreign tag came late, finalize catches it). No flag.
- **Phase 2 (separate):** retire `shouldSkip()` for og/twitter/analytics/schema
  (keep for canonical/hreflang) to remove double-handling.

## Test plan (all must pass; unit first, then staging matrix J5/J6 × Free/Pro)

Unit (pure `trimBlockConflicts`, no Joomla — the safety net before any deploy):
1. 4SEO-style: `$theirs` has `og:title class=4SEO_ogp_tag` → our OG section removed; `$theirs` unchanged (function never sees/edits it).
2. No competitor → block byte-identical (nothing trimmed).
3. `aggressive`/`off` → block byte-identical regardless of `$theirs`.
4. Multi-instance preserved: our block has BreadcrumbList + FAQPage JSON-LD and `$theirs` has an Organization → only nothing/Org trimmed, Breadcrumb/FAQ kept.
5. OG all-or-nothing: `$theirs` has only `og:image` → still removes our whole OG set (no mixed set).
6. GTM head+noscript removed together (HeadBlockBuilder + BodyBlockBuilder).
7. Our own tags never matched as "theirs" (function is given `$theirs` = body without our block; assert it operates only on `$block`).

Staging:
8. offroadbalkans (has 4SEO): cooperative → exactly one OG set in final HTML, 4SEO's intact, ours gone; aggressive → two sets; off → two sets + no `conflict_4seo` Health row.
9. A clean Pro site → AI Boost OG/JSON-LD present, nothing trimmed.
10. `verify-frontend-emission.py --group conflicts` green; the existing `og_unique_*` static checks now pass on offroadbalkans in cooperative mode.

DoD: 348+ PHPUnit (+ new trim tests) + 3/3 standalone; build Pro-leakage STRICT;
Version patch bump; staging verify on offroadbalkans (the live 4SEO dedup proof);
Health check. Then update OPERATING.md.

## Files

`component/lib/src/HeadBlockBuilder.php` (setConflictMode + trimBlockConflicts +
finalize wiring), `component/lib/src/BodyBlockBuilder.php` (parallel trim),
`component/lib/src/HealthCheckService.php` (off gate at :245),
`component/lib/src/Manifest/core.php` + `vue-admin/src/tabs/TechnicalSeoTab.vue`
(3-mode copy), the `aiboost_core` plugin (call setConflictMode), `Version.php`,
new unit test `component/lib/tests/.../TrimBlockConflictsTest.php`.
