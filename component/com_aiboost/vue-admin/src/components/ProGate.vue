<!--
  AI Boost — ProGate

  Tier lock wrapper. When the Pro PACKAGE is installed (isProInstalled — true on
  any Pro build, even before a licence key is entered), the slot renders
  normally. On a Free build it renders a locked state with a small "Upgrade to
  Pro" prompt, so Pro features are advertised rather than silently dead. The
  licence itself is still enforced at runtime by the front-end emitters.

  Modes:
    - mode="field"  — compact inline lock chip (e.g. in place of a translation
                      expander). Does NOT render the slot.
    - mode="card"   — keeps the slotted content visible but greyed + disabled,
                      with a small "🔒 Upgrade to Pro" button in the top-right.

  forceUnlock (dev/QA) bypasses the gate and always renders the slot.
-->
<template>
  <slot v-if="unlocked" />

  <a
    v-else-if="mode === 'field'"
    :href="upgradeUrl" target="_blank" rel="noopener"
    class="ab-pg-field"
    :title="fieldTitle"
  >
    <span class="ab-pg-lock">🔒</span>
    <span class="ab-pg-field-label">{{ fieldLabel }}</span>
    <span class="ab-pg-pro">Pro</span>
  </a>

  <div v-else class="ab-pg-card">
    <a
      :href="upgradeUrl" target="_blank" rel="noopener"
      class="ab-pg-upbtn" :title="fieldTitle"
    >
      <span class="ab-pg-lock" aria-hidden="true">🔒</span> Upgrade to Pro
    </a>
    <div class="ab-pg-dim">
      <slot />
    </div>
  </div>
</template>

<script>
import { isProInstalled, proUpgradeUrl } from '../api.js'

export default {
  name: 'ProGate',
  props: {
    // Optional identifier for the gated feature (telemetry / aria only).
    gateKey:     { type: String,  default: '' },
    // 'field' (compact inline chip) or 'card' (grey + small upgrade button).
    mode:        { type: String,  default: 'field' },
    // Dev/QA bypass — always render the slot.
    forceUnlock: { type: Boolean, default: false },
    // Label shown next to the lock in field mode (e.g. "Translate").
    label:       { type: String,  default: '' },
  },
  computed: {
    unlocked()   { return this.forceUnlock || isProInstalled() },
    upgradeUrl() { return proUpgradeUrl() },
    fieldLabel() { return this.label || 'Translate' },
    fieldTitle() { return (this.label || 'This feature') + ' is available in AI Boost Pro — click to upgrade' },
  },
}
</script>

<style scoped>
/* ── Field mode: compact inline lock chip ─────────────────────────────── */
.ab-pg-field {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 2px 9px;
  margin-top: 4px;
  font-size: .76rem;
  line-height: 1.4;
  color: var(--secondary-color, #6c757d);
  background: #fffbf0;
  border: 1px solid #ffe8a1;
  border-radius: 999px;
  text-decoration: none;
  cursor: pointer;
  transition: border-color .15s, background .15s;
}
.ab-pg-field:hover { background: #fff6dc; border-color: #ffd75e; }
.ab-pg-pro {
  font-weight: 700;
  font-size: .68rem;
  letter-spacing: .03em;
  text-transform: uppercase;
  color: #b8860b;
}
.ab-pg-lock { font-size: .8rem; }

/* ── Card mode: subtle grey + small top-right upgrade button ───────────── */
.ab-pg-card { position: relative; }
.ab-pg-dim {
  opacity: .72;
  filter: grayscale(.25);
  pointer-events: none;
  user-select: none;
}
.ab-pg-upbtn {
  position: absolute;
  top: 8px;
  right: 10px;
  z-index: 3;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 11px;
  font-size: .72rem;
  font-weight: 600;
  line-height: 1.5;
  color: #fff;
  background: #0d6efd;
  border-radius: 6px;
  text-decoration: none;
  box-shadow: 0 1px 3px rgba(0, 0, 0, .18);
  transition: background .15s;
}
.ab-pg-upbtn:hover { background: #0b5ed7; color: #fff; }
.ab-pg-upbtn .ab-pg-lock { font-size: .72rem; }

[data-bs-theme=dark] .ab-pg-field   { background: #2a2000; border-color: #4a3800; color: #c9c4b8; }
[data-bs-theme=dark] .ab-pg-field:hover { background: #332700; }
</style>
