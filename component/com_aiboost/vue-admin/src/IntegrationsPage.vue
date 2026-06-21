<template>
  <div class="ab-integrations-page">

    <!-- Summary bar -->
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="ab-badge ab-badge--success" :title="tooltip('support_active')">{{ supportActiveCount }} active</span>
        <span v-if="pausedCount" class="ab-badge ab-badge--warning" :title="tooltip('paused')">{{ pausedCount }} paused</span>
        <span class="ab-badge ab-tag--neutral" :title="tooltip('detected')">{{ detectedCount }} detected</span>
        <span class="ab-badge ab-badge--warning" :title="tooltip('coming_soon')">{{ comingSoonCount }} add-ons</span>
        <span class="ab-badge" :title="tooltip('roadmap')">{{ roadmapCount }} roadmap</span>
        <span class="ab-badge" :title="tooltip('not_detected')">{{ notDetectedCount }} not installed</span>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center">
        <input
          v-model="search"
          type="text"
          class="ab-input"
          placeholder="Filter integrations…"
          style="max-width:200px"
        />
        <select v-model="categoryFilter" class="ab-select" style="max-width:160px">
          <option value="">All categories</option>
          <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
        </select>
      </div>
    </div>

    <!-- Cards grid -->
    <div class="ab-int-grid">
      <div
        v-for="item in filtered"
        :key="item.key"
        class="ab-section ab-int-card"
        :class="'ab-int-card--' + item.status"
      >
        <!-- Head: icon + name + status badge -->
        <div class="ab-section__head ab-int-card__head">
          <span :class="[item.icon || 'icon-puzzle', 'ab-int-card__icon flex-shrink-0']" aria-hidden="true"></span>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-truncate">{{ item.name }}</div>
            <div class="ab-help">{{ item.vendor }}</div>
          </div>
          <span
            :class="['ab-badge flex-shrink-0', statusBadge(item.status)]"
            :title="tooltip(item.status)"
          >
            {{ statusLabel(item.status) }}
          </span>
        </div>

        <!-- Body: description + toggle + options -->
        <div class="ab-section__body ab-int-card__body">
          <span class="ab-badge ab-tag--neutral mb-2">{{ item.category }}</span>
          <p class="ab-help mb-2">{{ item.description }}</p>

          <!-- Master toggle -->
          <div v-if="item.has_master_toggle" class="d-flex align-items-center gap-2 mb-2">
            <span
              class="ab-toggle"
              :class="{ 'is-on': item.master_enabled !== false }"
              :title="item.master_enabled === false ? 'Switched off' : 'Switched on'"
              style="cursor:pointer"
              @click="!saving[item.key] && toggleIntegration(item)"
            >
              <input type="checkbox" class="ab-toggle__input" :checked="item.master_enabled !== false" @change.stop />
              <span class="ab-toggle__track" aria-hidden="true"></span>
            </span>
            <span class="ab-help fw-semibold">
              {{ item.master_enabled === false ? 'Off' : 'On' }}
            </span>
            <span v-if="saving[item.key]" class="ab-int-spinner" aria-hidden="true"></span>
          </div>

          <!-- Expandable: what it does -->
          <div v-if="copyFor(item)" class="ab-int-acc">
            <button type="button" class="ab-int-acc__btn" @click="toggleDoes(item.key)">
              <span>What this does</span>
              <span class="ab-int-acc__chev" :class="{ 'is-open': openDoes[item.key] }">▾</span>
            </button>
            <div v-show="openDoes[item.key]" class="ab-int-acc__body">
              <p class="ab-help mb-2">{{ copyFor(item).does }}</p>
              <template v-if="item.has_master_toggle && copyFor(item).off">
                <strong class="d-block" style="font-size:.82rem">What turning it off changes</strong>
                <p class="ab-help mb-0 mt-1">{{ copyFor(item).off }}</p>
              </template>
            </div>
          </div>

          <!-- Expandable: per-integration options -->
          <div v-if="optionsFor(item) && optsLoaded" class="ab-int-acc">
            <button type="button" class="ab-int-acc__btn" @click="toggleOpts(item.key)">
              <span>Options</span>
              <span class="ab-int-acc__chev" :class="{ 'is-open': openOpts[item.key] }">▾</span>
            </button>
            <div v-show="openOpts[item.key]" class="ab-int-acc__body">
              <p v-if="!item.installed" class="ab-help mb-2">
                Not detected on this site — these options take effect once the extension is installed and active.
              </p>

              <IntegrationOptionField
                v-for="f in optionsFor(item).free" :key="f.key"
                :field="f" v-model="settings[f.key]" />

              <ProGate v-if="optionsFor(item).pro.length" mode="card" :label="item.name + ' (Pro)'">
                <IntegrationOptionField
                  v-for="f in optionsFor(item).pro" :key="f.key"
                  :field="f" v-model="settings[f.key]" />
              </ProGate>

              <div class="d-flex align-items-center gap-2 mt-2">
                <button type="button" class="ab-btn ab-btn--sm ab-btn--primary"
                  :disabled="!!savingOpts[item.key]" @click="saveOptions(item)">
                  <span v-if="savingOpts[item.key]">Saving…</span>
                  <span v-else>Save options</span>
                </button>
                <span v-if="optsMsg[item.key]" class="ab-help"
                  :style="{ color: optsOk[item.key] ? 'var(--ab-success)' : 'var(--ab-danger)' }">
                  {{ optsMsg[item.key] }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer: CTA -->
        <div v-if="hasCardAction(item)" class="ab-int-card__footer">
          <a
            :href="item.learn_url"
            target="_blank"
            rel="noopener"
            class="ab-btn ab-btn--ghost ab-btn--sm"
          >
            Learn More
          </a>
        </div>

      </div>
    </div>

    <!-- Empty state -->
    <div v-if="filtered.length === 0" class="text-center py-5 ab-help">
      <AbIcon name="search" style="width:1.5rem;height:1.5rem;display:block;margin:0 auto .5rem" aria-hidden="true" />
      No integrations match your filter.
    </div>

    <!-- Info box -->
    <div class="ab-alert ab-alert--info mt-4" role="note">
      <strong>Detection</strong> checks the Joomla extensions table for installed &amp; enabled extensions.
      Switching an integration off keeps all your settings — it only pauses AI Boost's extra output for that extension.
      <a href="https://aiboostnow.com/integrations" target="_blank" rel="noopener">Browse all integrations</a>
    </div>

  </div>
</template>

<script>
import { postWithCsrf } from './api.js'
import ProGate from './components/ProGate.vue'
import IntegrationOptionField from './components/IntegrationOptionField.vue'
import AbIcon from './components/AbIcon.vue'

const TOGGLE_URL       = 'index.php?option=com_aiboost&task=integrations.saveToggle'
const OPTIONS_URL      = 'index.php?option=com_aiboost&task=integrations.saveOptions'
const GET_SETTINGS_URL = 'index.php?option=com_aiboost&task=settings.getSettings&format=json'

// Editable option fields per integration. Keys MUST match the bridge plugin's
// onAiBoostRegisterFields() AND IntegrationsController::INTEGRATION_OPTION_KEYS.
const INTEGRATION_OPTIONS = {
  falang: {
    free: [],
    pro: [
      { key: 'falang_hreflang_head',    type: 'toggle', label: 'Emit hreflang link tags in the page <head>', default: '1' },
      { key: 'falang_hreflang_sitemap', type: 'toggle', label: 'List translated URLs as hreflang alternates in the XML sitemap', default: '1' },
      { key: 'falang_hreflang_mode',    type: 'select', label: 'Hreflang source', default: 'auto', options: [
        { value: 'auto',          label: 'Auto (recommended)' },
        { value: 'joomla_native', label: 'Joomla language associations' },
        { value: 'falang',        label: 'Falang translations' },
      ] },
      { key: 'falang_schema_translate', type: 'toggle', label: 'Translate Schema.org per language', default: '1' },
      { key: 'falang_og_translate',     type: 'toggle', label: 'Translate OpenGraph per language', default: '1' },
      { key: 'falang_primary_language', type: 'text',   label: 'Primary language code', default: 'en', placeholder: 'en' },
    ],
  },
  yootheme: {
    free: [
      { key: 'yootheme_meta_override',  type: 'toggle', label: 'Use the YOOtheme page title & description for meta and OpenGraph', default: '1' },
    ],
    pro: [
      { key: 'yootheme_faq_enabled',     type: 'toggle', label: 'Build FAQ schema from YOOtheme Accordion elements', default: '1' },
      { key: 'yootheme_gallery_enabled', type: 'toggle', label: 'Build ImageGallery schema from YOOtheme Gallery elements', default: '1' },
      { key: 'yootheme_schema_mapping',  type: 'toggle', label: 'Map Schema.org type from YOOtheme menu params', default: '1' },
      { key: 'yootheme_accordion_selector', type: 'text', label: 'Accordion CSS selector', default: '.uk-accordion', placeholder: '.uk-accordion' },
      { key: 'yootheme_sitemap_exclude_builder', type: 'toggle', label: 'Exclude builder-only pages from the XML sitemap', default: '1' },
    ],
  },
}

// Per-integration plain-English copy for the expandable card section.
const INTEGRATION_COPY = {
  falang: {
    does: 'Multilang Pro — adds hreflang link tags to the page head, translates Schema.org and OpenGraph per language, and lists translated URLs as hreflang alternates in the XML sitemap. Works with native Joomla language associations and Falang.',
    off:  'AI Boost stops adding hreflang, translated Schema.org and translated OpenGraph. Your translations and every AI Boost setting are kept — only this extra output pauses, and a normal Settings save will not erase them.',
  },
  yootheme: {
    does: 'Reads YOOtheme Pro page content to build FAQ and image-gallery Schema.org, and uses the YOOtheme page title and description for per-page meta and OpenGraph.',
    off:  'AI Boost stops reading YOOtheme Pro page content for schema and meta. Your YOOtheme settings are untouched — only this extra output pauses, and a normal Settings save will not erase your YOOtheme options.',
  },
  admintools: {
    does: 'Detection only — AI Boost never changes Admin Tools. When both AI Boost and Admin Tools are set to manage robots.txt, AI Boost raises a Health warning so you keep robots.txt editing in one tool.',
    off:  null,
  },
}

export default {
  name: 'IntegrationsPage',
  components: { ProGate, IntegrationOptionField, AbIcon },

  data() {
    return {
      integrations: window.aiBoostIntegrations || [],
      search:          '',
      categoryFilter:  '',
      saving:          {},
      settings:        {},
      optsLoaded:      false,
      savingOpts:      {},
      optsMsg:         {},
      optsOk:          {},
      openDoes:        {},
      openOpts:        {},
    }
  },

  async mounted() {
    // Integration option fields are not part of this view's bootstrap, so pull
    // the current settings blob once. Defaults fill any key not yet stored so
    // toggles render in their correct state.
    try {
      const res  = await fetch(GET_SETTINGS_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      const data = await res.json()
      const s    = (data && data.settings) || {}
      for (const intKey of Object.keys(INTEGRATION_OPTIONS)) {
        const def = INTEGRATION_OPTIONS[intKey]
        for (const f of [...def.free, ...def.pro]) {
          if (s[f.key] === undefined || s[f.key] === null) s[f.key] = f.default
        }
      }
      this.settings = s
    } catch (_e) {
      // Leave settings empty — fields fall back to defaults below.
      const s = {}
      for (const intKey of Object.keys(INTEGRATION_OPTIONS)) {
        const def = INTEGRATION_OPTIONS[intKey]
        for (const f of [...def.free, ...def.pro]) s[f.key] = f.default
      }
      this.settings = s
    } finally {
      this.optsLoaded = true
    }
  },

  computed: {
    categories() {
      return [...new Set(this.integrations.map(i => i.category))].sort()
    },

    filtered() {
      return this.integrations.filter(item => {
        const q = this.search.toLowerCase()
        const matchSearch = !q ||
          item.name.toLowerCase().includes(q) ||
          item.vendor.toLowerCase().includes(q) ||
          item.description.toLowerCase().includes(q) ||
          item.category.toLowerCase().includes(q)
        const matchCat = !this.categoryFilter || item.category === this.categoryFilter
        return matchSearch && matchCat
      })
    },

    supportActiveCount() { return this.integrations.filter(i => i.status === 'support_active').length },
    pausedCount()        { return this.integrations.filter(i => i.status === 'paused').length },
    detectedCount()      { return this.integrations.filter(i => i.status === 'detected').length },
    comingSoonCount()    { return this.integrations.filter(i => i.status === 'coming_soon').length },
    roadmapCount()       { return this.integrations.filter(i => i.status === 'roadmap').length },
    notDetectedCount()   { return this.integrations.filter(i => i.status === 'not_detected').length },
  },

  methods: {
    statusLabel(status) {
      const map = {
        support_active:  'Active',
        paused:          'Paused',
        detected:        'Detected',
        coming_soon:     'Add-on soon',
        roadmap:         'Roadmap',
        not_detected:    'Not installed',
        installed:       'Installed',
        addon_available: 'Coming soon',
      }
      return map[status] || status
    },

    statusBadge(status) {
      const map = {
        support_active:  'ab-badge--success',
        paused:          'ab-badge--warning',
        detected:        'ab-tag--neutral',
        coming_soon:     'ab-badge--warning',
        roadmap:         '',
        not_detected:    '',
        installed:       'ab-badge--success',
        addon_available: 'ab-badge--warning',
      }
      return map[status] || ''
    },

    tooltip(status) {
      const map = {
        support_active: 'AI Boost actively integrates here — it adapts to your setup (the detected extension, or your multilingual configuration for Multilang), avoids duplicate output, and reads the relevant settings.',
        paused:         'Extension is installed, but you switched this integration off here. AI Boost adds no extra output for it until you switch it back on. Your settings are kept.',
        detected:       'Extension is installed in Joomla. Dedicated AI Boost support is not active yet.',
        coming_soon:    'Dedicated AI Boost support is planned as an add-on. Actions are disabled until the add-on is ready.',
        roadmap:        'Future integration placeholder. No action is available until the integration is scoped.',
        not_detected:   'Not installed on this Joomla site.',
        installed:      'Installed in Joomla.',
        addon_available:'Coming soon.',
      }
      return map[status] || ''
    },

    toggleDoes(key) { this.openDoes = { ...this.openDoes, [key]: !this.openDoes[key] } },
    toggleOpts(key) { this.openOpts = { ...this.openOpts, [key]: !this.openOpts[key] } },

    hasCardAction(item) {
      return item.status !== 'coming_soon' && item.status !== 'roadmap' && !!item.learn_url
    },

    copyFor(item) {
      return INTEGRATION_COPY[item.key] || null
    },

    /** Status this tile shows when its master switch is ON. */
    enabledStatus(item) {
      if (!item.installed) return item.status
      return item.status_type === 'addon' ? 'detected' : 'support_active'
    },

    applyToggle(item, enabled) {
      item.master_enabled = enabled
      if (item.installed) {
        item.status = enabled ? this.enabledStatus(item) : 'paused'
      }
      // Mirror into the legacy global so a cached SPA re-visit (no re-fetch)
      // reflects the new state instead of snapping back to the old value.
      try {
        const arr = window.aiBoostIntegrations
        if (Array.isArray(arr)) {
          const g = arr.find(x => x && x.key === item.key)
          if (g) { g.master_enabled = item.master_enabled; g.status = item.status }
        }
      } catch (_e) { /* ignore */ }
    },

    async toggleIntegration(item) {
      if (!item.has_master_toggle || this.saving[item.key]) return

      const previous = item.master_enabled !== false
      const next     = !previous

      // Optimistic update.
      this.applyToggle(item, next)
      this.saving = { ...this.saving, [item.key]: true }

      try {
        const resp = await postWithCsrf(TOGGLE_URL, { integration: item.key, enabled: next ? '1' : '0' })
        if (!resp || resp.success !== true) {
          this.applyToggle(item, previous) // rollback
        }
      } catch (_e) {
        this.applyToggle(item, previous)   // rollback on transport error
      } finally {
        const s = { ...this.saving }
        delete s[item.key]
        this.saving = s
      }
    },

    optionsFor(item) {
      return INTEGRATION_OPTIONS[item.key] || null
    },

    async saveOptions(item) {
      const def = this.optionsFor(item)
      if (!def || this.savingOpts[item.key]) return

      const opts = {}
      for (const f of [...def.free, ...def.pro]) {
        opts[f.key] = this.settings[f.key] ?? f.default
      }

      this.savingOpts = { ...this.savingOpts, [item.key]: true }
      this.optsMsg    = { ...this.optsMsg, [item.key]: '' }
      try {
        const resp = await postWithCsrf(OPTIONS_URL, { integration: item.key, options: JSON.stringify(opts) })
        const ok   = !!resp && resp.success === true
        this.optsOk  = { ...this.optsOk, [item.key]: ok }
        this.optsMsg = { ...this.optsMsg, [item.key]: ok ? 'Saved' : ((resp && resp.message) || 'Save failed') }
      } catch (_e) {
        this.optsOk  = { ...this.optsOk, [item.key]: false }
        this.optsMsg = { ...this.optsMsg, [item.key]: 'Request failed' }
      } finally {
        const s = { ...this.savingOpts }
        delete s[item.key]
        this.savingOpts = s
      }
    },
  },
}
</script>

<style scoped>
.ab-integrations-page { }

.ab-int-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  align-items: start;
}
@media (max-width: 1040px) { .ab-int-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px)  { .ab-int-grid { grid-template-columns: 1fr; } }

.ab-int-card { display: flex; flex-direction: column; }

/* Status left-accent */
.ab-int-card--support_active { border-left: 3px solid var(--ab-success); }
.ab-int-card--paused         { border-left: 3px solid var(--ab-warning); }
.ab-int-card--detected       { border-left: 3px solid var(--ab-border); }
.ab-int-card--coming_soon    { border-left: 3px solid var(--ab-warning); }
.ab-int-card--roadmap        { border-left: 3px solid var(--ab-border); opacity: .7; }
.ab-int-card--not_detected   { border-left: 3px solid var(--ab-border); opacity: .85; }

.ab-int-card__head {
  display: flex; align-items: flex-start; gap: .6rem; flex-wrap: wrap;
}
.ab-int-card__icon { font-size: 1.1rem; color: var(--ab-primary); margin-top: .1rem; }

.ab-int-card__body { flex: 1 1 auto; }
.ab-int-card__footer {
  padding: var(--ab-space-3) var(--ab-space-4);
  border-top: 1px solid var(--ab-border);
}

.ab-int-acc { border-top: 1px solid var(--ab-border); margin-top: .5rem; }
.ab-int-acc__btn { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: .5rem 0; background: none; border: none; cursor: pointer; color: var(--ab-text); font-size: .85rem; font-weight: 500; }
.ab-int-acc__btn:hover { color: var(--ab-primary); }
.ab-int-acc__chev { transition: transform .2s; font-size: .75rem; color: var(--ab-text-muted); }
.ab-int-acc__chev.is-open { transform: rotate(180deg); }
.ab-int-acc__body { padding: .25rem 0 .75rem; }

.ab-int-spinner {
  width: 12px; height: 12px;
  border: 2px solid var(--ab-border); border-top-color: transparent;
  border-radius: 50%; display: inline-block;
  animation: ab-int-spin .6s linear infinite;
}
@keyframes ab-int-spin { to { transform: rotate(360deg); } }
</style>
