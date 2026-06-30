# Part 8 — Over-engineering / Simplification (CRITIC pass)

> The owner is ONE person shipping to sale and asks: *"maybe it's too complicated — maybe simplify."*
> This part hunts for where the product is **over-built or carries dead weight** for a near-sale,
> one-developer product, and says concretely what can be **cut or simplified WITHOUT weakening the
> foundation** — i.e. without touching the two things that are genuinely load-bearing: the **output
> pipeline** (`HeadBlockBuilder`/`BodyBlockBuilder` accumulate→finalize) and the **integration
> extensibility seam** (the versioned Integration SDK).
>
> Evidence read directly from source on 2026-06-28, cross-checked against `Procena1/2/3.txt`,
> `option-map.json` (276 options), `part-1-koren.md`, `part-5-prosirivost-wp.md`,
> `part-6-sigurnost.md`. Every claim cites a file/class.
>
> NOTE: `part-4-mogucnosti-ogranicenja.md` and `part-7-masinerija.md` named in the order do not yet
> exist on disk (only parts 1,2,3,5,6 are present) — they are sibling sections being written in
> parallel. This pass leans on the available siblings + the three independent assessments instead.

---

## Verdict (short)

The **root is not over-engineered** — it is, if anything, the right amount of engineering for what it
does (one principle, two sinks, one settings blob, one render contract; see part-1). The
over-engineering and dead weight live in **three rings around the root**, none of which the foundation
needs:

1. **A whole retired Pro-architecture left running in parallel** — 5 dormant `*_pro` decorator
   plugins, a no-op `ProFeatureRegistry`, and a dead `ProGate` license-tier path. The real gate is
   **one flag** (`pro_activated`). The rest is scaffolding from an abandoned design that still ships,
   still gets built, and still confuses the docs.
2. **A second, legacy copy of the whole product** in top-level `plugins/` (5 standalone plugins) that
   CLAUDE.md itself calls "not the active codebase" — yet it consumes **4 of the 5 CI jobs**.
3. **A speculative WordPress port** (16 adapter files, half of them `throw new RuntimeException('not
   implemented')`) built before the Joomla product has earned a single sale — and all three
   independent reviewers explicitly say *don't*.

Plus a long tail of **dead/duplicate options** (the map flags 6 `⚫ Mrtvo` + 8 `Izbaciti`) and a few
**marketing-driven features that can't deliver** (`ai-content-verified` AI meta tags), unanimously
called to be cut.

Cutting all of the above **removes weight, not capability** — and in two cases (the dual Pro
architecture, the dead ProGate path) actively *reduces* the risk surface. The genuinely valuable,
keep-at-all-costs parts are small: the output pipeline, the Integration SDK, the Conflict Manager, and
the multilingual output. Everything else is negotiable.

---

## 1. The single biggest over-build: TWO Pro architectures running at once

This is the clearest "we built it, changed our minds, but never deleted the old one" in the codebase —
and it is pure carrying cost.

**What the live gate actually is (tiny):** Pro is a **single server-resolved flag**.
`PluginRegistry::isProActive($settings)` returns `pro_activated === '1'`, else false
(`PluginRegistry.php:375-378`). Every decision point delegates to that one call (part-6 §6). That is
the entire gate. It is correct, fail-closed, and unforgeable.

**What still ships around it (large, all dormant):**

- **5 dormant `*_pro` decorator plugins** — `aiboost_schema_pro`, `aiboost_social_pro`,
  `aiboost_aeo_pro`, `aiboost_code_pro`, `aiboost_hreflang_pro` (confirmed: `ls .../plugins/system | grep _pro`).
  Each is now a **no-op**: `AiBoostSchemaPro.php:3` self-describes as a "dormant decorator", its
  `onAfterInitialise()` (`:34`) "does nothing", docblock (`:7`) says the Pro logic was *"relocated INTO
  the free aiboost_schema plugin"*. `aiboost_hreflang_pro` is literally a *"skeleton"*
  (`status_razlog` in the map) — head hreflang comes from the Falang bridge, not this plugin. So these
  five plugins are built, packaged, version-bumped, and installed on every site to **do nothing**.
  part-5 §(a) confirms: *"the `*_pro` decorator path is retired and ProFeatureRegistry is a no-op
  shell … the decorator pattern the prompt asks about is historical, not the live path."*

- **`ProFeatureRegistry` is a compatibility shell.** `stripLocked()`/`stripProOptions()` are no-ops
  that `return $payload` unchanged (`ProFeatureRegistry.php:144-151`, `:303-311`); `proOptions()` and
  `lockedSettingsKeys()` return `[]` (`:126`, `:283`). The class docblocks literally say *"Compatibility
  no-op for the retired Free/Pro enum gate."* Yet **CLAUDE.md still mandates** registering every Pro
  surface in it ("Cosmetic 'Pro' badges without a registry entry are forbidden") — a rule that now
  enforces nothing. This is documentation chasing a ghost.

- **Dead `ProGate` license-tier path.** `ProGate.php` still carries the *old* per-plugin licensing:
  `validateAndStoreLicense()` (`:60`), `storeLicenseTier()` (`:87`) writing `license_tier` into
  `#__extensions.params`, and `onExtensionAfterSave()` (`:149`). part-6 §6 + §10.1 flags this as live
  dead code that *"cannot grant core Pro … but is confusing surface area that should be removed to
  prevent a future maintainer wiring a gate to it"* — i.e. a latent security footgun, not just clutter.

**Why this is over-engineering for a 1-person near-sale product:** the team is maintaining the
*surface area* of a 12-SKU decorator-based licensing system while running a 1-flag gate. Every build,
every staging install, every `verify-clean-uninstall` run, every codegen pass has to account for
plugins and a registry that exist only to be inert.

**Cut, safely:**
- **Now (zero-risk):** stop documenting `ProFeatureRegistry` as the gate. Reconcile CLAUDE.md +
  the joomla-development skill to say "the gate is `pro_activated` via `isProActive()`" (part-5
  recommendation 2). Costs nothing, prevents future work chasing a dead registry.
- **Now (zero-risk):** delete the dead `ProGate::validateAndStoreLicense/storeLicenseTier/onExtensionAfterSave`
  path (part-6 §10.1). It is unreachable by the live gate and removing it shrinks the attack surface.
- **Before launch (low-risk, sequence carefully):** retire the 5 dormant `*_pro` plugins. They are
  empty. The only reason to keep them one more cycle is the install/uninstall migration story (a site
  that already has them installed). Plan a single "collapse" release that uninstalls them cleanly via
  `pkg_script`, then never ship them again. The map's own note ("Pro-replaces-Free collapse … dormant
  until the final phase retires it") shows this was always the intended end state — it just hasn't been
  executed. Do not let "the final phase" become "forever."

**Foundation impact: none.** The output pipeline and the Integration SDK do not touch any of this. The
gate stays exactly as it is (one flag).

---

## 2. A second, legacy copy of the product — eating 80% of CI

CLAUDE.md is explicit: top-level `plugins/` (`aiboost-aeo`, `aiboost-codemanager`, `aiboost-hreflang`,
`aiboost-opengraph`, `aiboost-schema`) are *"legacy standalone plugins … not the active codebase."*
The real product is `component/`.

But these legacy plugins are **not dormant in CI** — they dominate it. Of the CI jobs in
`.github/workflows/ci.yml`, **four are dedicated to the legacy tree**:
`standalone-plugins-lint`, `standalone-safety-tests`, `standalone-plugins-build`,
`standalone-plugins-smoke`, plus `standalone-plugins-joomla-runtime` — versus **one**
(`canonical-tests`) that could be the live product. `composer test` (what CI runs) is
`scripts/run-standalone-tests.php`. There is a dedicated `scripts/build-plugin.py` whose entire job is
building these legacy ZIPs (`--plugin aeo|opengraph|codemanager|schema|hreflang`).

**Why this is dead weight:** the team pays the **full test/build/runtime matrix cost** (across PHP
versions) on every push for code that ships to no one. For a solo developer, green CI on the *wrong*
codebase is worse than no CI — it spends the maintenance budget and the wall-clock on the artifact
that doesn't matter, while the live component leans on staging-install verification.

**Simplify:**
- Decide the legacy tree's fate **explicitly**. If it is truly frozen reference material, move it out
  of the active CI path (mark the jobs `if: false`, or relocate the folder to an `_archive/` outside
  the build) so it stops consuming the matrix. If any of it is still a real shipping artifact, that
  contradicts CLAUDE.md and needs reconciling — but nothing in the architecture parts suggests it is.
- Re-point `composer test` and the headline CI job at the **component** safety tests, so "CI green"
  means "the product is green."

**Foundation impact: none.** The component, the SDK, and the output pipeline are untouched; this is
pure CI/scope hygiene.

---

## 3. The WordPress port — speculative, half-stubbed, unanimously "not yet"

part-5 §(b) is blunt: WordPress is *"a designed seam awaiting implementation, not a near-build."* The
scaffolding is real and (to its credit) cheap to *keep*: a full `Cms/` adapter interface set with both
Joomla and Wp impls, an `AppContextInterface`, the SDK guarding on `ABSPATH`. But the decisive
adapters **throw**: `WpDatabaseAdapter`, `WpHttpAdapter`, `WpApplicationAdapter`, `WpDocumentAdapter`
all `throw new RuntimeException('… not implemented (v2.0 WordPress port)')` (confirmed by grep). There
is **no content seam** for the ~200 `#__` queries, **no WP entry point, no WP build** (part-5 §(b)).

**This is the most expensive kind of over-engineering: building for a market you haven't entered.** All
three independent assessments converge:
- `Procena3.txt` (§7 rizici): *"WordPress adapter posebno: to je ulazak na tržište gde su Yoast i Rank
  Math … ne savetujem da trošiš resurse na taj sloj dok Joomla verzija ne dokaže tržište."*
- `Procena1.txt` (§7) and `Procena2.txt` (§7 "Prerana WordPress apstrakcija") both name premature WP
  abstraction as a top risk — *"može povećati složenost bez trenutne koristi."*
- part-5 recommendation 3 agrees: *"Defer the WordPress build, keep the seam."*

**The nuance that saves this from being a pure cut:** the *abstraction* (adapter interfaces, the
CMS-agnostic lib — `grep "use Joomla\CMS"` over all 81 lib files returns 0, per part-5 §(b)) is what
makes the Joomla code clean and testable **today**, independent of WP. That part earns its keep. The
**Wp impl files** (the throwing stubs) earn nothing yet.

**Simplify (do NOT over-correct):**
- **Keep** the adapter interfaces and the Joomla impls — they are load-bearing for testability and add
  no weight to a Joomla-only ship.
- **Freeze, don't extend** the `Cms/Wp/*` stubs. Do not add the content-repository seam, the WP entry
  point, or any further WP work until Joomla revenue justifies it. Every hour on WP now is an hour not
  spent making the **multilingual output** (the actual moat, per all three assessments) bulletproof.
- The danger to actively guard against (Procena2 §7, part-5): do **not** let the *Joomla* data model
  start bending to fit a future WP shape. Keep the WP seam as a clean boundary the Joomla code happens
  to honour, never a tax the Joomla code pays.

**Foundation impact: none** (the seam stays; only the unimplemented half is frozen). Net effect: stop
the bleed of effort toward an unproven second platform.

---

## 4. Dead and duplicate options — the long tail the map already found

The 276-option map (`option-map.json`) is itself the evidence: **6 options `⚫ Mrtvo` (dead)** and
**8 marked `Izbaciti` (cut)**. These are not architecture — they are accreted cruft that still costs
manifest lines, codegen output, `.ini` keys, Vue partials, Health stubs, and the three-way-alignment
audit burden (part-1 §3-4: every option must agree across v-model ↔ whitelist ↔ consumer).

The map's own cut list:
- `robots_auto_sync` — *"Obrisati ključ — ne radi ništa"* (dead, no runtime consumer).
- `custom_code_scope`, `custom_code_menu_ids` — *"Obrisati legacy ključ"* (pre-v0.8 reserves, no UI).
- `hreflang_sitemap` + `sitemap_hreflang` + `hreflang_enabled` + `hreflang_primary_language` — **four
  keys for one thing**, all dead/orphan/drift; the map says *"Spojiti na jedan ključ; obrisati
  duplikate"* and *"konsolidovati na enable_hreflang."* This is a small but real over-engineering: the
  hreflang config grew three parallel knobs (head, sitemap, primary-language) where the runtime only
  honours the Falang-bridge path. Collapse to one.
- `aeo_ai_meta_enabled` — see §5.

**Simplify:** delete the 6 dead keys + collapse the 4 hreflang duplicates to one in a single manifest
cleanup → codegen pass. The codegen invariant (part-1 §4) means this is mechanical and the build
`--check` will keep it honest. **Each deleted dead option is one fewer thing to test, document, and
explain to a customer.** This is the cheapest weight to shed.

**Foundation impact: none** — these keys feed no live consumer; that's *why* they're dead.

---

## 5. Marketing-driven features the product can't actually deliver

This is over-engineering of a different kind: **building UI + storage + plumbing for a claim that has
no mechanism behind it.** All three assessments single out the same culprit.

**`ai-content-verified` / `ai-content-optimized` AI meta tags** (`aeo_ai_meta_enabled` in the map,
marked **Izbaciti**):
- `Procena2.txt`: *"Ove tagove bih izbacio. Nema priznatog standarda ni dokumentovane podrške Google-a,
  OpenAI-ja ili Perplexity-ja … Naziv `verified` stvara tvrdnju koju komponenta tehnički ne može da
  dokaže."*
- `Procena3.txt`: *"funkcija koja obećava nešto što tehnički ne može da ispuni. Nijedan pretraživač ni
  AI sistem ne čita te tagove … Tretiraj ih kao dekoraciju bez funkcije."*
- The map's `preporuka_razlog`: *"IZBACITI — nijedan pretraživač/AI ih ne čita; nema standarda."*

**Cut the tags entirely.** They are not just dead weight — they are a **liability** (a `verified` claim
the product cannot substantiate invites refunds and reputational damage from an SEO audience that
checks). This is the rare case where simplifying is also de-risking.

**Reposition, don't necessarily cut, the AEO layer around them:** `llms.txt`, Markdown alternates,
IndexNow. All three assessments agree these have *real but narrow* value (B2A / agent accessibility,
and IndexNow is genuinely useful) and that 4SEO already ships `llms.txt`+Markdown — so they are **not a
moat and must not be sold as a ranking mechanism**. Keep them as a clearly-labelled "experimental / AI
discovery" layer (Procena2 §"Evidence labels", Procena3 §4). That's a *positioning* simplification, not
a code cut — but it reduces the marketing surface the solo owner has to defend.

---

## 6. What is NOT over-engineered — explicitly keep (so simplification doesn't go too far)

A critic's job is also to stop the owner from cutting muscle with the fat. These earn their complexity:

- **The output pipeline** (`HeadBlockBuilder`/`BodyBlockBuilder`, accumulate→idempotent finalize,
  fixed section order, byte-safe splice). part-1 §7 calls it "ship-grade"; the cooperative-dedup is a
  *pure, unit-testable* function with a structural safety invariant (can only delete OUR tags). The
  complexity here is **concentrated and justified** — it is the product. Do not touch.
- **The Integration SDK** (`component/lib/src/Integration/*`, versioned `Sdk::SDK_VERSION`,
  `FilterDispatcher`, `onAiBoostRegisterIntegration/Fields`). part-5 §(a) proves it works end-to-end
  (Falang + YOOtheme). This is the genuine extensibility the owner asked about — and the one part of
  the "more architecture than a 1-person product needs" that actually pays for itself, because it lets
  add-ons ship **without a core release**. Keep, and *document it as a product surface* (part-5 rec 1).
- **The Conflict Manager** and **multilingual output** — every assessment names these as the real
  differentiators (Procena1 §3, Procena2 §3, Procena3 §3). Complexity here is the value, not the waste.
- **The manifest-first codegen** (part-1 §4). It *looks* heavy (10 manifest files, 258 Vue files,
  codegen + `--check`), but it is what keeps the three-way key alignment honest at build time and makes
  the dead-option cleanup in §4 mechanical instead of dangerous. Keeping it is what makes the rest of
  this simplification *safe*. Keep.
- **The CMS adapter *interfaces*** (not the WP impls) — load-bearing for testability today (§3).

---

## 7. Concrete simplification backlog (ordered by value ÷ risk for a solo near-sale)

| # | Action | Effort | Risk | Why it's safe (foundation untouched) |
|---|--------|--------|------|--------------------------------------|
| 1 | Reconcile docs: gate is `pro_activated`, not `ProFeatureRegistry` (CLAUDE.md + skill) | XS | none | Doc-only; matches part-5 §(a), part-6 §6 |
| 2 | Delete 6 dead options + collapse 4 hreflang dupes to one (manifest → codegen) | S | low | No live consumer (map `⚫ Mrtvo`); build `--check` guards |
| 3 | Cut `ai-content-verified`/`ai-content-optimized` AI meta tags | S | none | All 3 assessments + map say Izbaciti; removes a liability |
| 4 | Remove dead `ProGate` license-tier path (`validateAndStoreLicense`/`storeLicenseTier`/`onExtensionAfterSave`) | S | low | Unreachable by live gate; part-6 §10.1 — shrinks attack surface |
| 5 | Take the legacy `plugins/` tree out of the active CI matrix; re-point `composer test` at the component | S | low | CLAUDE.md already says it's not the active codebase |
| 6 | Plan ONE "collapse" release that uninstalls the 5 dormant `*_pro` plugins and stops shipping them | M | med | They are no-ops; needs a clean `pkg_script` uninstall + `verify-clean-uninstall` both targets |
| 7 | Freeze WordPress work: keep adapter interfaces, do NOT build the content seam / WP entry / Wp impls until Joomla revenue justifies it | — (stop work) | none | part-5 rec 3 + all 3 assessments; keeps the cheap seam, stops the expensive bleed |
| 8 | Reposition AEO (`llms.txt`/Markdown/IndexNow) as a labelled "experimental / AI discovery" layer, not a ranking claim | S (copy) | none | Marketing-surface reduction; Procena2/3 |

Items 1–5 are **pure subtraction** a solo developer can do in a few focused sessions, each leaving the
output pipeline and the SDK exactly as they are. Items 6–7 are the bigger structural simplifications and
should be deliberate, sequenced releases — but both *reduce* what has to be maintained and shipped, not
what the product can do.

**Bottom line for the owner (plain framing):** the engine at the centre is not too complicated — it is
the right size. What's "too much" is everything the team built and then walked away from but never
deleted (an old Pro system, an old copy of the plugins) and everything built ahead of demand (the
WordPress half-port). Deleting those makes the product *lighter and safer to sell*, and the only
muscle to protect while cutting is the output pipeline, the integration seam, the conflict manager and
the multilingual output.
