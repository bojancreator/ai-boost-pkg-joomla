<template>
  <div class="ab-wizard-overlay" role="dialog" aria-modal="true" aria-labelledby="ab-wizard-title">
    <div class="ab-wizard">
      <div class="ab-wizard__head">
        <h2 id="ab-wizard-title" class="h5 mb-1">Welcome to AI Boost</h2>
        <p class="text-muted small mb-0">
          We found <strong>{{ conflicts.length }}</strong>
          {{ conflicts.length === 1 ? 'extension' : 'extensions' }} on your site that can produce the same
          SEO output as AI Boost. Choose how AI Boost should behave — you can change this any time in the
          Conflict Manager.
        </p>
      </div>

      <ul v-if="conflicts.length" class="ab-wizard__list list-unstyled">
        <li v-for="c in conflicts" :key="c.id" class="small">
          <span class="ab-badge" :class="c.status === 'critical' ? 'ab-badge--danger' : 'ab-badge--warning'">
            {{ c.status === 'critical' ? 'Critical' : 'Warning' }}
          </span>
          <strong class="ms-1">{{ c.label }}</strong>
        </li>
      </ul>

      <div class="ab-wizard__choices">
        <button type="button" class="ab-wizard__choice" :disabled="busy" @click="choose('takeover')">
          <span class="ab-wizard__icon" aria-hidden="true">🛡️</span>
          <span class="ab-wizard__title">AI Boost takes over</span>
          <span class="ab-wizard__desc">Emit all AI Boost output. We'll point you to Joomla to switch off the overlapping tools.</span>
        </button>
        <button type="button" class="ab-wizard__choice" :disabled="busy" @click="choose('defer')">
          <span class="ab-wizard__icon" aria-hidden="true">🤝</span>
          <span class="ab-wizard__title">AI Boost defers to others</span>
          <span class="ab-wizard__desc">Stay out of the way — let your existing tools own every output.</span>
        </button>
        <button type="button" class="ab-wizard__choice" :disabled="busy" @click="choose('custom')">
          <span class="ab-wizard__icon" aria-hidden="true">🎛️</span>
          <span class="ab-wizard__title">Let me choose per output</span>
          <span class="ab-wizard__desc">Open the Conflict Manager and decide output-by-output.</span>
        </button>
      </div>

      <div class="ab-wizard__foot">
        <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" :disabled="busy" @click="later">
          Decide later
        </button>
        <span v-if="busy" class="ab-spinner ms-2" aria-hidden="true"></span>
      </div>
    </div>
  </div>
</template>

<script>
import { postWithCsrf } from './api.js'

const SAVE_URL = 'index.php?option=com_aiboost&task=conflicts.savePolicy&format=json'
const FEATURE_KEYS = ['schema', 'og', 'sitemap', 'analytics', 'canonical', 'titles']

export default {
  name: 'ConflictWizard',

  props: {
    conflicts: { type: Array, default: () => [] },
  },

  emits: ['close'],

  data() {
    return { busy: false }
  },

  methods: {
    payloadFor(mode) {
      const p = { conflict_setup_done: '1' }
      if (mode === 'takeover') {
        p.conflict_mode = 'aggressive'
        FEATURE_KEYS.forEach((k) => { p['conflict_' + k] = 'takeover' })
      } else if (mode === 'defer') {
        p.conflict_mode = 'cooperative'
        FEATURE_KEYS.forEach((k) => { p['conflict_' + k] = 'defer' })
      }
      return p
    },

    async choose(mode) {
      // 'custom' only marks the wizard answered, then routes to the full manager.
      const payload = mode === 'custom' ? { conflict_setup_done: '1' } : this.payloadFor(mode)
      await this.save(payload)
      this.$emit('close', { goTo: mode === 'custom' ? '/conflicts' : null })
    },

    async later() {
      // Don't nag again, but leave the (safe cooperative) defaults untouched.
      await this.save({ conflict_setup_done: '1' })
      this.$emit('close', {})
    },

    async save(payload) {
      this.busy = true
      try {
        await postWithCsrf(SAVE_URL, payload)
        try {
          window.aiBoostSettings = window.aiBoostSettings || {}
          Object.keys(payload).forEach((k) => { window.aiBoostSettings[k] = payload[k] })
        } catch (_e) { /* ignore */ }
        // Reflect in the bootstrap so the wizard never re-opens this session.
        try {
          if (window.aiBoostBootstrap) {
            window.aiBoostBootstrap.conflictSetupDone = true
            if (window.aiBoostBootstrap.conflicts) window.aiBoostBootstrap.conflicts.setupDone = true
          }
        } catch (_e) { /* ignore */ }
      } catch (_e) {
        /* postWithCsrf already toasted; close anyway so the user isn't trapped */
      } finally {
        this.busy = false
      }
    },
  },
}
</script>

<style scoped>
.ab-wizard-overlay {
  position: fixed; inset: 0; z-index: 1080;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem; background: rgba(0, 0, 0, .5);
}
.ab-wizard {
  width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto;
  background: var(--ab-bg, #fff); color: var(--ab-text, #1f2937);
  border: 1px solid var(--ab-border, #e5e7eb); border-radius: 12px;
  padding: 1.5rem; box-shadow: 0 12px 48px rgba(0, 0, 0, .25);
}
.ab-wizard__head { margin-bottom: 1rem; }
.ab-wizard__list { margin: 0 0 1rem; display: flex; flex-direction: column; gap: .35rem; }
.ab-wizard__choices { display: flex; flex-direction: column; gap: .6rem; }
.ab-wizard__choice {
  display: flex; flex-direction: column; gap: .15rem; text-align: left;
  padding: .85rem 1rem;
  background: var(--ab-bg-muted, #f8f9fa);
  border: 2px solid var(--ab-border, #e5e7eb); border-radius: 10px; cursor: pointer;
  transition: border-color .15s, box-shadow .15s;
}
.ab-wizard__choice:hover { border-color: var(--ab-primary, #2563eb); }
.ab-wizard__choice:disabled { opacity: .7; cursor: progress; }
.ab-wizard__icon { font-size: 1.3rem; }
.ab-wizard__title { font-weight: 600; }
.ab-wizard__desc { font-size: .82rem; color: var(--ab-text-muted, #6c757d); }
.ab-wizard__foot { margin-top: 1rem; display: flex; align-items: center; }

.ab-spinner {
  width: 14px; height: 14px; border: 2px solid var(--ab-border, #adb5bd);
  border-top-color: transparent; border-radius: 50%; display: inline-block;
  animation: ab-spin .6s linear infinite;
}
@keyframes ab-spin { to { transform: rotate(360deg); } }
</style>
