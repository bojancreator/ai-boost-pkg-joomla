---
name: Integration SDK — registry + dispatcher invariants
description: Cross-component cache lockstep and filter-dispatcher mutation semantics for third-party bridges (AI Boost Joomla).
---

- **Integration bridge enumeration is dynamic, not constant.** A known-bridge enumerator must consult the runtime bridge registry and overlay a fallback whitelist (dynamic wins), not a hardcoded const map. Use array-union (`+`), never `array_merge`, so a registered bridge's metadata is not clobbered by the whitelist row.
  - **Why:** Bridges ship as separate ZIPs; a const map silently drops third-party bridges from capability scans. The fallback exists only to keep capability scans non-empty when the registry cache is cold or a bridge plugin failed to boot.
  - **How to apply:** Route every "installed/known bridges" enumeration through the dynamic helper. When renaming the helper, grep both `self::CONST` and external `::CONST` access — old call styles do not raise warnings until runtime.

- **The two integration caches must reset in lockstep.** Any install/uninstall flow that resets the plugin-side cache MUST also reset the descriptor registry cache in the same request, or capability lookups serve stale "installed=false" rows for a freshly installed bridge.
  - **Why:** The two caches are populated from different sources (extensions table vs. discovery event) but joined when answering "is this bridge usable?". A stale dynamic cache silently hides a brand-new install from the dashboard.
  - **How to apply:** Wrap the cross-reset in `try/catch (\Throwable)` because legacy boot paths may not have the SDK classes loaded.

- **Filter dispatcher preserves partial mutations on listener throw.** A dispatcher that fans out to ordered listeners must return the accumulated mutation state even when a later listener throws — never revert to the original input. Downstream consumers (claim ledgers, mutation logs) may have already taken irreversible action on the partial state; silent reversion corrupts the ledger.
  - **Why:** Listeners run in (priority ASC, plugin-name ASC) order; a late crash that wipes earlier mutations makes the rendered output disagree with whatever audit trail watchers recorded mid-flight.
  - **How to apply:** Catch `\Throwable`, log via `error_log`, fall through to returning the accumulator. Document the priority order in the public SDK doc so third parties can opt into running after first-party bridges.
