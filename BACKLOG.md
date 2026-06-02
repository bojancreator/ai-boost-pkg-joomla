# AI Boost for Joomla — Backlog

The **only** forward plan: real remaining work, in plain language, organised by
**type of work** — not just new features. When you pick up an item, follow the
**Task completion procedure (Definition of Done)** in `replit.md`; when it's
shipped and verified on staging, delete its line from this file. That deletion
*is* marking it done — no parallel task panel.

---

## New options / features

- **Alias Assistant** — suggest and fix article aliases, with automatic 301
  redirects when an alias changes.
- **YOOtheme Pro integration** — bridge AI Boost into YOOtheme-built sites so they
  also get the schema / OG / AEO output.
- **Warn the admin when custom code is unusually large** — flag injected code that
  could slow the site down.
- **Preview injected custom code before saving it** — let admins see what will be
  output before it goes live.

## Refactors & technical work

_(structural / gating / cleanup work that isn't a user-facing feature)_

## Bugs & fixes

_(confirmed defects to fix)_

## Testing & infrastructure

_(test/CI infrastructure tasks)_

## Health scan polish

_(improvements to the Health registry / scanner)_

## Documentation / skill

_(docs and `joomla-development` skill lessons)_

- **v0.72.x staging verification complete** — see `deliverables/docs/v0.72.x-staging-verification.md`.
  All 0.72.x changes (Task #567 LicenseReconcile, Task #566 robots.txt uninstall fix) verified live
  on staging. No regressions. 16 Health issues found are all pre-existing config gaps, not code
  defects. Known follow-up: build script doesn't inject package version into plugin XML manifests
  → persistent `warning_install_integrity` (tracked as task #572).

---

## Not in this backlog (on purpose)


