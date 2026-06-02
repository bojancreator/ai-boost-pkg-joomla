---
name: Vue 3 — never store Window/DOM/3rd-party objects in reactive data
description: Why "__v_isReadonly / __v_isRef from 'Window'" SecurityErrors appear, and the rule that prevents them.
---

# Symptom

Frontend error log shows: `SecurityError: Failed to read a named property '__v_isReadonly' from 'Window': An attempt was made to break through the security policy of the user agent` (also `__v_isRef`), stack at minified `bo.set` / `bo.get` (Vue's reactive Proxy get/set traps).

# Root cause

A `Window` object was stored in Vue **reactive** state. In Options API, every key returned from `data()` is made reactive; assigning a `Window` (e.g. `this.popup = window.open(...)` when `popup` is declared in `data()`) makes Vue's reactivity probe internal flags (`__v_isReadonly`, `__v_isRef`, `__v_skip`, `__v_raw`) on that Window. Cross-origin / privileged `Window` throws SecurityError on arbitrary property reads, so the whole handler aborts.

In AI Boost this also broke "Browse Media" in the Free build: the throw happened the moment the popup was assigned, aborting the media hand-back flow — looked like the button "did nothing".

# Rule (how to apply)

Never put a `Window`, DOM node, `Document`, event object, or any 3rd-party/host instance (editors, observers, popups, map/chart instances) into `data()` / `ref()` / `reactive()`.

- Keep them as plain non-reactive instance fields. This codebase's convention is an underscore prefix assigned outside `data()`, e.g. `this._popup`, `this._messageHandler`, `this._sinkEl` — Options API does NOT make ad-hoc `this._x` reactive.
- If a value must live in a reactive container, wrap it with `markRaw(value)` first.
- Template never needs these references, so keeping them off `data()` costs nothing.

**Why:** the reactive Proxy traps read internal flag keys on every tracked value; host objects with security policies throw on those reads, turning a silent design slip into runtime SecurityErrors and broken flows.
