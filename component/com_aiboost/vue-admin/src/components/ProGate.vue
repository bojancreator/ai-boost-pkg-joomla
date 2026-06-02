<template>
  <!-- Unlocked: pass-through, no extra DOM -->
  <slot v-if="!locked" />

  <!--
    Locked: muted-but-readable content + corner "Unlock to Pro" pill +
    invisible click-shield that opens the upsell modal. Section and field
    modes share the same structure; only the pill size and modifier class
    differ. (Task #458 — replaced the old full-section overlay+card with a
    corner-pill approach so locked sections no longer dominate the page.)
  -->
  <div v-else
       class="ab-progate"
       :class="['ab-progate--' + mode]">
    <div class="ab-progate__content">
      <slot />
    </div>

    <!-- v0.55.3 — Bojan's directive: clicking anywhere in the muted
         card was irritating because every stray click jumped to the
         pricing site. The only upsell click target is now the corner
         pill itself; the rest of the locked content is purely visual
         (still muted + pointer-events:none so inputs can't be edited).
         The old invisible full-cover <a> "shield" has been removed. -->
    <a class="ab-progate__pill"
       :class="'ab-progate__pill--' + mode"
       :href="upsellUrl"
       target="_blank"
       rel="noopener"
       :title="upsellTitle">
      <span class="ab-progate__pill-lock" aria-hidden="true">🔒</span>
      <span class="ab-progate__pill-label">{{ pillLabel }}</span>
    </a>
  </div>
</template>

<script>
import { isPro as checkIsPro } from '../api.js'

function lookupEntry(key) {
  const list = (typeof window !== 'undefined')
    ? (window.aiBoostProFeatures
       || (window.aiBoostBootstrap && window.aiBoostBootstrap.proFeatures)
       || [])
    : []
  return list.find(e => e && e.key === key) || null
}

function titleCase(s) {
  return (s || '').charAt(0).toUpperCase() + (s || '').slice(1)
}

export default {
  name: 'ProGate',
  props: {
    gateKey: { type: String, required: true },
    mode:    { type: String, default: 'field' }, // 'field' | 'section'
    // v0.55.0 — used by the Licenses page: on a Pro INSTALL with no
    // verified key, isPro is false (no active license) but we still
    // want the page to be usable so the user can paste their key.
    // The Licenses wrapper passes `:force-unlock="isProInstall"`.
    forceUnlock: { type: Boolean, default: false },
  },
  computed: {
    entry() {
      return lookupEntry(this.gateKey)
    },
    /**
     * Fail-closed semantics:
     *  - If license is Pro → unlocked (fast path, no registry lookup needed).
     *  - If license is Free AND the registry has this key → locked with the
     *    registry's label + reason.
     *  - If license is Free AND the registry is MISSING this key → still
     *    locked, with a generic "Pro feature" upsell, and a console warning so
     *    the missing registry entry is visible during dev. This is the safe
     *    default: a typo in gate-key must never silently ship as unlocked.
     */
    locked() {
      if (this.forceUnlock) return false
      if (checkIsPro()) return false
      if (!this.entry && typeof console !== 'undefined' && console.warn) {
        console.warn('[AI Boost] <ProGate gate-key="' + this.gateKey
          + '"> has no matching entry in ProFeatureRegistry — defaulting to LOCKED. '
          + 'Add an entry to component/lib/src/ProFeatureRegistry.php.')
      }
      const reason = this.lockReason
      if (reason === 'pro') return true
      // integration:* gating: future hook — defer to capabilities payload if
      // present; otherwise treat as locked so the upsell is shown.
      return true
    },
    lockReason() {
      return (this.entry && this.entry.lock_reason) || 'pro'
    },
    pillLabel() {
      // v0.55.0 — copy aligned with Bojan's directive: every locked
      // section advertises "Unlock Pro version" instead of the older,
      // slightly awkward "Unlock to Pro" phrasing.
      if (this.lockReason === 'pro') return 'Unlock Pro version'
      if (this.lockReason.startsWith('integration:')) {
        return 'Add-on required'
      }
      return 'Locked'
    },
    upsellTitle() {
      if (this.lockReason === 'pro') return 'Unlock with AI Boost Pro'
      if (this.lockReason.startsWith('integration:')) {
        return 'Install AI Boost ' + titleCase(this.lockReason.split(':')[1]) + ' Integration'
      }
      return 'Feature locked'
    },
    upsellBody() {
      if (this.lockReason === 'pro') {
        const what = this.entry && this.entry.label ? this.entry.label : 'this feature'
        return what + ' is part of the AI Boost Pro upgrade. Install Pro to enable it.'
      }
      if (this.lockReason.startsWith('integration:')) {
        const name = titleCase(this.lockReason.split(':')[1])
        return 'Install the AI Boost ' + name + ' Integration plugin to enable bridging with ' + name + '.'
      }
      return ''
    },
    upsellCta() {
      return this.lockReason === 'pro' ? 'View Pro pricing' : 'View integration'
    },
    upsellUrl() {
      if (this.lockReason === 'pro') return 'https://aiboostnow.com/pricing'
      if (this.lockReason.startsWith('integration:')) {
        return 'https://aiboostnow.com/integrations/' + this.lockReason.split(':')[1]
      }
      return 'https://aiboostnow.com/pricing'
    },
  },
}
</script>

<style scoped>
/* ── Locked wrapper (shared by section + field modes) ─────────── */
.ab-progate {
  position: relative;
  display: block;
}

/* Muted-but-readable content. Section uses a slightly stronger mute than
   field (sections are larger, so they need a tiny bit more visual
   damping to read as "off"). Pointer-events blocked so muted inputs
   can't be focused/edited; the shield above catches the click. */
.ab-progate__content {
  pointer-events: none;
  user-select: none;
}
.ab-progate--section .ab-progate__content {
  opacity: .55;
  filter: grayscale(.35);
}
.ab-progate--field .ab-progate__content {
  opacity: .6;
  filter: grayscale(.25);
}

/* Kill Joomla's auto-appended external-link ↗ icon on the pill. */
.ab-progate__pill::after,
.ab-progate__pill::before {
  content: none !important;
  display: none !important;
}

/* ── Corner "Unlock Pro version" pill ─────────────────────────── */
/* v0.55.0 — pill is now an <a>; pointer-events must be ON so it can
   be clicked / focused directly (with a higher z-index than the
   shield so it stays on top). */
.ab-progate__pill {
  position: absolute;
  z-index: 4;
  pointer-events: auto;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #fff8e1;
  color: #7c5a00;
  border: 1px solid #f59e0b;
  border-radius: 999px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  box-shadow: 0 1px 3px rgba(0, 0, 0, .08);
  text-decoration: none;
  cursor: pointer;
}
.ab-progate__pill:hover {
  background: #ffe9a8;
  color: #7c5a00;
  text-decoration: none;
}
.ab-progate__pill-lock { font-size: .85em; }
.ab-progate__pill-label { letter-spacing: .01em; }

/* Section: larger pill, comfortably inside the card header area. */
.ab-progate__pill--section {
  top: 10px;
  right: 12px;
  padding: 4px 10px;
  font-size: 12px;
}
/* Field: smaller, tighter — fits beside a single input. */
.ab-progate__pill--field {
  top: 2px;
  right: 6px;
  padding: 2px 7px;
  font-size: 10.5px;
}

</style>

<!-- Dark-mode overrides live in a NON-scoped style block. Joomla's
     `[data-bs-theme=dark]` lives on the <html> element, far above the
     scoped component, so `:global(...)` games inside scoped CSS are
     fragile. A plain unscoped block is the most reliable way to target
     the theme attribute. Class names are namespaced (`ab-progate__*`)
     so this won't bleed into anything else. -->
<style>
[data-bs-theme=dark] .ab-progate--section .ab-progate__content,
[data-bs-theme=dark] .ab-progate--field .ab-progate__content {
  opacity: .7;
  filter: grayscale(.2) brightness(1.05);
}
[data-bs-theme=dark] .ab-progate__pill {
  background: #3a2f10;
  color: #ffd97a;
  border-color: #f59e0b;
  box-shadow: 0 1px 3px rgba(0, 0, 0, .35);
}
</style>
