<template>
  <div class="ab-conflicts-page">
    <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
      <div>
        <h2 class="h5 mb-1">Conflict Manager</h2>
        <p class="text-muted small mb-0">
          Decide what AI Boost outputs when another extension already does the same job.
          AI Boost never disables another plugin — it only links you to Joomla so you can.
        </p>
      </div>
      <button class="ab-btn ab-btn--ghost ab-btn--sm ms-auto" :disabled="scanning" @click="rescan">
        <span v-if="scanning" class="ab-spinner me-1" aria-hidden="true"></span>
        {{ scanning ? 'Scanning…' : 'Re-scan' }}
      </button>
    </div>

    <!-- Detected conflicts -->
    <div class="ab-card mb-4">
      <div class="ab-card__header d-flex align-items-center gap-2">
        <span class="icon-warning text-warning" aria-hidden="true"></span>
        <span class="fw-semibold">Detected conflicts</span>
        <span class="ab-badge ms-1" :class="conflicts.length ? 'ab-badge--warning' : 'ab-badge--success'">
          {{ conflicts.length }}
        </span>
      </div>
      <div class="ab-card__body">
        <p v-if="!conflicts.length" class="text-muted small mb-0">
          No conflicting SEO/analytics extensions detected. AI Boost is the only tool emitting these tags.
        </p>
        <ul v-else class="ab-conflict-list list-unstyled mb-0">
          <li v-for="c in conflicts" :key="c.id" class="ab-conflict-item">
            <div class="d-flex align-items-start gap-2">
              <span class="ab-badge flex-shrink-0" :class="severityBadge(c.status)">{{ severityLabel(c.status) }}</span>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold">{{ c.label }}</div>
                <p class="text-muted small mb-1">{{ c.message }}</p>
                <div v-if="(c.affects || []).length" class="small mb-1">
                  <span class="text-muted">Overlaps:</span>
                  <span v-for="f in c.affects" :key="f" class="ab-badge border ms-1">{{ featureLabel(f) }}</span>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-1">
                  <a
                    v-for="(a, i) in (c.fix_actions || [])"
                    :key="i"
                    :href="a.url"
                    :target="isExternal(a.url) ? '_blank' : '_self'"
                    rel="noopener"
                    class="ab-btn ab-btn--ghost ab-btn--xs"
                  >{{ a.label }}</a>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>

    <!-- Mode chooser -->
    <div class="ab-card mb-4">
      <div class="ab-card__header fw-semibold">How should AI Boost behave?</div>
      <div class="ab-card__body">
        <div class="row g-3">
          <div v-for="opt in modeOptions" :key="opt.value" class="col-md-4">
            <button
              type="button"
              class="ab-mode-card w-100 text-start"
              :class="{ 'ab-mode-card--active': mode === opt.value }"
              :disabled="saving"
              @click="chooseMode(opt.value)"
            >
              <span class="ab-mode-card__icon" aria-hidden="true">{{ opt.icon }}</span>
              <span class="ab-mode-card__title">{{ opt.title }}</span>
              <span class="ab-mode-card__desc">{{ opt.desc }}</span>
            </button>
          </div>
        </div>

        <!-- Custom per-feature -->
        <div v-if="mode === 'custom'" class="ab-custom mt-4">
          <p class="text-muted small mb-2">For each output type, choose who owns it:</p>
          <div v-for="f in features" :key="f.key" class="ab-feature-row">
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold">{{ f.label }}</div>
              <div class="text-muted small">{{ f.desc }}</div>
              <div v-if="competitorsFor(f.key).length" class="small mt-1">
                <span class="ab-badge ab-badge--warning">Also emitted by: {{ competitorsFor(f.key).join(', ') }}</span>
              </div>
            </div>
            <div class="ab-seg flex-shrink-0">
              <button
                type="button"
                class="ab-seg__btn"
                :class="{ 'ab-seg__btn--active': custom[f.key] === 'takeover' }"
                @click="custom[f.key] = 'takeover'"
              >AI Boost handles it</button>
              <button
                type="button"
                class="ab-seg__btn"
                :class="{ 'ab-seg__btn--active': custom[f.key] === 'defer' }"
                @click="custom[f.key] = 'defer'"
              >Defer to existing</button>
            </div>
          </div>
          <div class="mt-3">
            <button class="ab-btn ab-btn--primary ab-btn--sm" :disabled="saving" @click="saveCustom">
              <span v-if="saving" class="ab-spinner me-1" aria-hidden="true"></span>
              Save custom setup
            </button>
          </div>
        </div>
      </div>
    </div>

    <div class="ab-alert ab-alert--info small" role="note">
      <span class="icon-info-circle me-1" aria-hidden="true"></span>
      “AI Boost handles it” emits AI Boost's own output. If a competitor also emits it you'll see a duplicate
      until you disable that competitor in Joomla — use the links above. “Defer to existing” keeps AI Boost quiet
      for that output and lets the other extension own it.
    </div>
  </div>
</template>

<script>
import { postWithCsrf } from './api.js'

const SCAN_URL = 'index.php?option=com_aiboost&task=conflicts.scan&format=json'
const SAVE_URL = 'index.php?option=com_aiboost&task=conflicts.savePolicy&format=json'

// Output features the user can steer (mirror of AiBoost\Lib\ConflictPolicy).
const FEATURES = [
  { key: 'schema',    label: 'Schema (JSON-LD)',              desc: 'Structured data for rich results.' },
  { key: 'og',        label: 'Open Graph / social meta',      desc: 'Link previews on social platforms.' },
  { key: 'sitemap',   label: 'XML sitemap',                   desc: 'The /sitemap.xml AI Boost serves.' },
  { key: 'analytics', label: 'Analytics (GA4 / GTM / Pixel)', desc: 'Tracking snippets in the page head.' },
  { key: 'canonical', label: 'Canonical link',                desc: 'The canonical URL for each page.' },
  { key: 'titles',    label: 'Page titles & meta description', desc: 'Title and description templates.' },
]

// Features that OVERWRITE/own a resource — they apply unless EXPLICITLY deferred
// (mirror of ConflictPolicy::shouldApplyExclusive).
const SET_TYPES = ['canonical', 'titles', 'sitemap']

function effectiveFor(feature, policy) {
  const o = String(policy['conflict_' + feature] || 'inherit').toLowerCase()
  if (SET_TYPES.includes(feature)) {
    return o === 'defer' ? 'defer' : 'takeover'
  }
  if (o === 'takeover' || o === 'defer') return o
  return String(policy.conflict_mode || 'cooperative').toLowerCase() === 'cooperative' ? 'defer' : 'takeover'
}

export default {
  name: 'ConflictManagerPage',

  data() {
    const boot = (window.aiBoostBootstrap && window.aiBoostBootstrap.conflicts) || {}
    return {
      features: FEATURES,
      conflicts: Array.isArray(boot.detected) ? boot.detected : [],
      policy: { conflict_mode: 'cooperative' },
      mode: 'custom',
      custom: { schema: 'takeover', og: 'takeover', sitemap: 'takeover', analytics: 'takeover', canonical: 'takeover', titles: 'takeover' },
      scanning: false,
      saving: false,
      modeOptions: [
        { value: 'takeover', icon: '🛡️', title: 'AI Boost takes over', desc: 'Emit all AI Boost output. Disable overlapping tools in Joomla to avoid duplicates.' },
        { value: 'defer',    icon: '🤝', title: 'Defer to others',     desc: 'Stay out of the way — let your existing tools own every output.' },
        { value: 'custom',   icon: '🎛️', title: 'Custom',             desc: 'Decide output-by-output which tool wins.' },
      ],
    }
  },

  mounted() {
    this.applyPolicy(this.gatherPolicyFromSettings())
    this.rescan()
  },

  methods: {
    gatherPolicyFromSettings() {
      // window.aiBoostSettings is only present once Settings was visited; fall
      // back to the safe default. The scan endpoint returns the authoritative
      // policy anyway, so this is only the pre-scan seed.
      const s = window.aiBoostSettings || {}
      const p = { conflict_mode: s.conflict_mode || 'cooperative' }
      FEATURES.forEach((f) => { p['conflict_' + f.key] = s['conflict_' + f.key] || 'inherit' })
      return p
    },

    applyPolicy(policy) {
      this.policy = policy
      FEATURES.forEach((f) => { this.custom[f.key] = effectiveFor(f.key, policy) })
      this.mode = this.inferMode()
    },

    inferMode() {
      const vals = FEATURES.map((f) => this.custom[f.key])
      if (vals.every((v) => v === 'takeover')) return 'takeover'
      if (vals.every((v) => v === 'defer')) return 'defer'
      return 'custom'
    },

    async rescan() {
      this.scanning = true
      try {
        const resp = await postWithCsrf(SCAN_URL)
        if (resp && resp.success) {
          if (Array.isArray(resp.conflicts)) this.conflicts = resp.conflicts
          if (resp.policy) this.applyPolicy(resp.policy)
        }
      } catch (_e) { /* postWithCsrf already toasted */ } finally {
        this.scanning = false
      }
    },

    payloadForMode(mode) {
      const p = { conflict_setup_done: '1' }
      if (mode === 'takeover') {
        p.conflict_mode = 'aggressive'
        FEATURES.forEach((f) => { p['conflict_' + f.key] = 'takeover' })
      } else if (mode === 'defer') {
        p.conflict_mode = 'cooperative'
        FEATURES.forEach((f) => { p['conflict_' + f.key] = 'defer' })
      } else {
        p.conflict_mode = 'cooperative'
        FEATURES.forEach((f) => { p['conflict_' + f.key] = this.custom[f.key] === 'defer' ? 'defer' : 'takeover' })
      }
      return p
    },

    async chooseMode(mode) {
      this.mode = mode
      if (mode === 'custom') return // reveal toggles; save via the Save button
      await this.persist(this.payloadForMode(mode))
    },

    async saveCustom() {
      await this.persist(this.payloadForMode('custom'))
    },

    async persist(payload) {
      this.saving = true
      try {
        const resp = await postWithCsrf(SAVE_URL, payload)
        if (resp && resp.success) {
          if (resp.policy) this.applyPolicy(resp.policy)
          // Mirror into the settings global so a later Settings save preserves it.
          try {
            window.aiBoostSettings = window.aiBoostSettings || {}
            Object.keys(payload).forEach((k) => { window.aiBoostSettings[k] = payload[k] })
          } catch (_e) { /* ignore */ }
        }
      } catch (_e) { /* postWithCsrf already toasted */ } finally {
        this.saving = false
      }
    },

    competitorsFor(feature) {
      return this.conflicts
        .filter((c) => Array.isArray(c.affects) && c.affects.includes(feature))
        .map((c) => c.label)
    },

    featureLabel(key) {
      const f = FEATURES.find((x) => x.key === key)
      return f ? f.label : key
    },

    severityBadge(status) {
      if (status === 'critical') return 'ab-badge--danger'
      if (status === 'warning') return 'ab-badge--warning'
      return 'ab-badge--neutral'
    },

    severityLabel(status) {
      if (status === 'critical') return 'Critical'
      if (status === 'warning') return 'Warning'
      return 'Info'
    },

    isExternal(url) {
      return /^https?:\/\//i.test(String(url || ''))
    },
  },
}
</script>

<style scoped>
.ab-conflicts-page { max-width: 920px; }

.ab-conflict-item { padding: .75rem 0; border-top: 1px solid var(--ab-border, #e5e7eb); }
.ab-conflict-item:first-child { border-top: 0; }

.ab-mode-card {
  display: flex; flex-direction: column; gap: .25rem;
  padding: 1rem; height: 100%;
  background: var(--ab-bg-muted, #f8f9fa);
  border: 2px solid var(--ab-border, #e5e7eb); border-radius: 10px;
  cursor: pointer; transition: border-color .15s, box-shadow .15s;
}
.ab-mode-card:hover { border-color: var(--ab-primary, #2563eb); }
.ab-mode-card--active { border-color: var(--ab-primary, #2563eb); box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
.ab-mode-card:disabled { opacity: .7; cursor: progress; }
.ab-mode-card__icon { font-size: 1.4rem; }
.ab-mode-card__title { font-weight: 600; }
.ab-mode-card__desc { font-size: .82rem; color: var(--ab-text-muted, #6c757d); }

.ab-feature-row {
  display: flex; align-items: center; gap: 1rem;
  padding: .75rem 0; border-top: 1px solid var(--ab-border, #e5e7eb);
}
.ab-feature-row:first-of-type { border-top: 0; }

.ab-seg { display: inline-flex; border: 1px solid var(--ab-border, #d0d5dd); border-radius: 8px; overflow: hidden; }
.ab-seg__btn {
  padding: .35rem .7rem; font-size: .82rem; border: 0; cursor: pointer;
  background: var(--ab-bg, #fff); color: var(--ab-text, #1f2937);
}
.ab-seg__btn + .ab-seg__btn { border-left: 1px solid var(--ab-border, #d0d5dd); }
.ab-seg__btn--active { background: var(--ab-primary, #2563eb); color: #fff; }

.ab-spinner {
  width: 12px; height: 12px; border: 2px solid var(--ab-border, #adb5bd);
  border-top-color: transparent; border-radius: 50%; display: inline-block;
  animation: ab-spin .6s linear infinite; vertical-align: -1px;
}
@keyframes ab-spin { to { transform: rotate(360deg); } }

.ab-btn--xs { padding: .15rem .5rem; font-size: .75rem; }
</style>
