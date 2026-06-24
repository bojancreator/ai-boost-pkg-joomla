# AI Boost for Joomla — Backlog

The **only** forward list of remaining work, in plain language, organised by **type of work**.
When you pick up an item, follow the **Definition of Done** in `OPERATING.md`; when it's shipped
and verified on a Free **and** a Pro test site, delete its line from this file. That deletion *is*
marking it done — no parallel task panel.

**Where we are now (version, branch, what's deployed, what's left to launch):** `STATUS.md`.
Product strategy and release sequence: `docs/v0.5-product-direction.md`.

> Pruned 2026-06-18: the entire Admin IA rework (sidebar grouping, Technical SEO page, Crawlers &
> Robots page, menu renames, Autopilot MVP, Meta Pixel move, AEO rename) shipped (phases 1–4), and
> the 4SEO/Sh404SEF/JoomSEF conflict-detector element-name bug is fixed — all removed from below.

---

## New options / features

- **Integration improvements (from Plan 1 review, 2026-06-12 — deferred by Bojan):**
  - **Admin Tools: detect AI-bot blocking** *(high value, AEO-on-brand)* — Admin Tools' WAF /
    "Block user agents" can block GPTBot/ClaudeBot/PerplexityBot, killing AEO. Detect such rules and
    warn. Also: redirect-manager overlap, `.htaccess` overlap.
  - **YOOtheme: validate / rework menu-param schema mapping** — the Event/Product/Organization mapping
    reads ~30 *guessed* param keys (`yoo_event_name`…) that likely rarely match real builder params.
    Verify on a real YOOtheme site; if it never fires, parse the YOOtheme builder JSON or drop it
    (FAQ/gallery are body-based and reliable). Also: configurable gallery selector, DOMDocument instead
    of regex, HowTo/Breadcrumb schema.
  - **Falang new options** — `inLanguage` on Schema.org, `og:locale:alternate`, per-language meta
    title/description templates, per-language sitemap priority.
  - **Product decision** — should the YOOtheme bridge be Pro (current) or Free (Falang is Free)?
  - *Verify before reopening:* "render per-feature integration options in the SPA" may already have
    shipped under Phase 3 ("integration options UI", `ce895f0`) — confirm in the SPA before re-listing.
- **Alias Assistant** — suggest and fix article aliases, with automatic 301 redirects when an alias
  changes. *(post-v0.5)*
- **Warn the admin when custom code is unusually large** — flag injected code that could slow the site
  down. *(post-v0.5)*
- **Preview injected custom code before saving it** — let admins see the output before it goes live. *(post-v0.5)*

## Admin UX / navigation

- **Polish Schema.org card ordering** — reorder cards so foundational schema comes before optional
  rich-result types (`Schema.org Core`, `Business / Organization Type`, conditional business details,
  `Opening Hours`, `WebSite`, `Article`, `FAQ/QAPage`, `Author Entity`, `HowTo`, `Event`). Feature set
  unchanged; this is ordering only. **Deferred post-launch** (high risk / low urgency on the most
  important page). *This is the only remaining item from the Admin IA rework — the rest shipped.*

## Refactors & technical work

- **Make settings save manifest-driven** — derive accepted keys/defaults/types/tier rules from the
  manifest registry instead of the `SettingsController.php` whitelist. Gate 2 and most of Gate 3 done
  (176/318 keys manifest-backed); remaining: Analytics and Sitemap SKU-ownership decisions. Architecture
  gate required (`docs/architecture-refactor-plan.md`); XHigh before the first slice.
- **Build a small WordPress vertical slice** — Organization/WebSite schema end-to-end on WordPress first,
  to expose missing CMS abstractions before the next wave of options. *(post-v0.5 — hold until Joomla
  v0.5 ships and shared service boundaries are stable.)* Architecture gate + XHigh required.
- **Thin Joomla plugin classes into platform entrypoints** — keep plugin classes as event/bootstrap
  layers; move business logic into shared services, starting with `AiBoostCore.php`. Architecture gate +
  XHigh required. (Biggest long-term risk is future Joomla/WordPress duplication, not the current product.)
- **UI colour tokens — extract the genuine colour bypasses** so a status-colour change is one place.
  Spots: `App.vue` staging/upgrade banner (~25 amber hex), `HealthApp` pass/fail (`#198754` / `#dc3545`
  + dark variants), per-tab accent palette (`App.vue`/`DashboardApp`). Add `--ab-warning` / `--ab-success`
  / `--ab-danger` (+ accent) and point those spots at them. Small, CSS-only, visually verifiable.
  *(post-1.0 — not before launch.)*
- **Cards: consolidate the two overlapping families** (`.ab-card` vs `.ab-section`) into one card family.
  Do this around the WordPress port, where shared components get unified anyway. *(post-1.0)*
- **AbButton + AbCard wrapper components** to remove repeated markup; sweep the ~180 one-off inline layout
  styles into utility/scoped rules. Nice-to-have only — these are mostly one-off LAYOUT, not shared theme,
  so they do NOT block the "one CSS fix = all pages" goal. *(optional, low value — only if there's appetite
  after 1.0.)*

## Bugs & fixes

(none open)

## Testing & infrastructure

- **Targeted screenshot: `--only`+`--theme` on `scripts/ui-audit-screenshots.js`** so one screen can be shot in both themes without the full 46-shot set. Until then the subagent fallback runs the full set and judges only the two relevant PNGs. **Raise priority if the fallback fires often in practice** (decide from real measurement, not assumption).

## Health scan polish

(none open)

## Documentation / skill

(none open)

---

## Not in this backlog (on purpose)
