<template>
  <div class="ab-integrations-page">

    <!-- Summary bar -->
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
      <div class="d-flex align-items-center gap-2">
        <span class="ab-badge ab-badge--success" :title="tooltip('support_active')">{{ supportActiveCount }} AI Boost active</span>
        <span v-if="pausedCount" class="ab-badge ab-badge--warning" :title="tooltip('paused')">{{ pausedCount }} Paused</span>
        <span class="ab-badge ab-badge--neutral" :title="tooltip('detected')">{{ detectedCount }} Detected in Joomla</span>
        <span class="ab-badge ab-badge--warning" :title="tooltip('coming_soon')">{{ comingSoonCount }} Add-ons</span>
        <span class="ab-badge" :title="tooltip('roadmap')">{{ roadmapCount }} Roadmap</span>
        <span class="ab-badge" :title="tooltip('not_detected')">{{ notDetectedCount }} Not installed</span>
      </div>
      <div class="ms-auto d-flex gap-2 align-items-center">
        <input
          v-model="search"
          type="text"
          class="ab-input form-control-sm"
          placeholder="Filter integrations…"
          style="max-width:200px"
        />
        <select v-model="categoryFilter" class="ab-select form-select-sm" style="max-width:160px">
          <option value="">All categories</option>
          <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
        </select>
      </div>
    </div>

    <!-- Cards grid -->
    <div class="row g-3">
      <div
        v-for="item in filtered"
        :key="item.key"
        class="col-sm-6 col-lg-4"
      >
        <div class="ab-card h-100 ab-int-card" :class="'ab-int-card--' + item.status">

          <!-- Card header: icon + name + status badge -->
          <div class="ab-card__header d-flex align-items-center gap-2">
            <span :class="[item.icon || 'icon-puzzle', 'fs-5 text-primary flex-shrink-0']" aria-hidden="true"></span>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate">{{ item.name }}</div>
              <div class="text-muted small">{{ item.vendor }}</div>
            </div>
            <span
              :class="['ab-badge flex-shrink-0', statusBadge(item.status)]"
              :title="tooltip(item.status)"
            >
              {{ statusLabel(item.status) }}
            </span>
          </div>

          <!-- Card body: description + category -->
          <div class="ab-card__body">
            <span class="ab-badge border mb-2 small">{{ item.category }}</span>
            <p class="text-muted small mb-2">{{ item.description }}</p>

            <!-- Master switch (only integrations that expose one) -->
            <div v-if="item.has_master_toggle" class="ab-int-switch d-flex align-items-center gap-2 mb-2">
              <label class="ab-switch mb-0" :title="item.master_enabled === false ? 'Switched off' : 'Switched on'">
                <input
                  type="checkbox"
                  :checked="item.master_enabled !== false"
                  :disabled="!!saving[item.key]"
                  @change="toggleIntegration(item)"
                />
                <span class="ab-switch__slider" aria-hidden="true"></span>
              </label>
              <span class="small fw-semibold">
                {{ item.master_enabled === false ? 'Off' : 'On' }}
              </span>
              <span v-if="saving[item.key]" class="ab-spinner" aria-hidden="true"></span>
            </div>

            <!-- Expandable: what it does / what turning off changes -->
            <details v-if="copyFor(item)" class="ab-int-details small">
              <summary>What this does</summary>
              <p class="text-muted mb-2 mt-2">{{ copyFor(item).does }}</p>
              <template v-if="item.has_master_toggle && copyFor(item).off">
                <strong class="d-block small">What turning it off changes</strong>
                <p class="text-muted mb-0 mt-1">{{ copyFor(item).off }}</p>
              </template>
            </details>
          </div>

          <!-- Card footer: CTAs -->
          <div class="ab-card__footer bg-transparent border-top-0 pt-0 pb-3 px-3 d-flex gap-2 flex-wrap align-items-center">
            <a
              v-if="hasCardAction(item)"
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
    </div>

    <!-- Empty state -->
    <div v-if="filtered.length === 0" class="text-center py-5 text-muted">
      <span class="icon-search fs-2 d-block mb-2" aria-hidden="true"></span>
      No integrations match your filter.
    </div>

    <!-- Info box -->
    <div class="ab-alert ab-alert--info mt-4 small" role="note">
      <span class="icon-info-circle me-1" aria-hidden="true"></span>
      <strong>Detection</strong> checks the Joomla extensions table for installed &amp; enabled extensions.
      Switching an integration off keeps all your settings — it only pauses AI Boost's extra output for that extension.
      <a href="https://aiboostnow.com/integrations" target="_blank" rel="noopener">Browse all integrations →</a>
    </div>

  </div>
</template>

<script>
import { postWithCsrf } from './api.js'

const TOGGLE_URL = 'index.php?option=com_aiboost&task=integrations.saveToggle'

// Per-integration plain-English copy for the expandable card section. Keep in
// sync with docs/integrations.md.
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

  data() {
    return {
      integrations: window.aiBoostIntegrations || [],
      search:          '',
      categoryFilter:  '',
      saving:          {},
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
        support_active:  '✅ AI Boost support active',
        paused:          '⏸ Paused — integration off',
        detected:        '🔍 Detected in Joomla',
        coming_soon:     '🚧 Add-on available soon',
        roadmap:         'Roadmap',
        not_detected:    '⚪ Not installed',
        // Legacy fallbacks (older server data)
        installed:       '✅ Installed',
        addon_available: '🚧 Coming soon',
      }
      return map[status] || status
    },

    statusBadge(status) {
      const map = {
        support_active:  'ab-badge--success',
        paused:          'ab-badge--warning',
        detected:        'ab-badge--neutral',
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
          this.applyToggle(item, previous) // rollback (postWithCsrf already toasted)
        }
      } catch (_e) {
        this.applyToggle(item, previous)   // rollback on transport error
      } finally {
        const s = { ...this.saving }
        delete s[item.key]
        this.saving = s
      }
    },
  },
}
</script>

<style scoped>
.ab-int-card {
  transition: box-shadow .15s;
}
.ab-int-card:hover {
  box-shadow: 0 2px 12px rgba(0,0,0,.08);
}
.ab-int-card--support_active .ab-card__header { border-left: 3px solid var(--bs-success, #28a745); }
.ab-int-card--paused         .ab-card__header { border-left: 3px solid var(--bs-warning, #f59e0b); }
.ab-int-card--detected       .ab-card__header { border-left: 3px solid var(--bs-secondary, #6c757d); }
.ab-int-card--coming_soon    .ab-card__header { border-left: 3px solid var(--bs-warning, #f59e0b); }
.ab-int-card--roadmap        .ab-card__header { border-left: 3px solid var(--bs-secondary, #6c757d); opacity: .7; }
.ab-int-card--not_detected   .ab-card__header { border-left: 3px solid var(--bs-secondary, #6c757d); opacity: .85; }

.ab-int-card--roadmap .ab-card__body,
.ab-int-card--roadmap .ab-card__footer { opacity: .7; }
.ab-int-card--paused .ab-card__body p { opacity: .85; }

.ab-badge--neutral { background: #e5e7eb; color: #374151; }
.ab-badge--warning { background: #fef3c7; color: #92400e; }
[data-bs-theme="dark"] .ab-badge--neutral { background: #374151; color: #e5e7eb; }
[data-bs-theme="dark"] .ab-badge--warning { background: #78350f; color: #fde68a; }

/* Master switch */
.ab-switch { position: relative; display: inline-block; width: 38px; height: 22px; flex-shrink: 0; cursor: pointer; }
.ab-switch input { position: absolute; opacity: 0; width: 0; height: 0; }
.ab-switch__slider {
  position: absolute; inset: 0; border-radius: 22px;
  background: var(--bs-secondary, #adb5bd);
  transition: background .15s;
}
.ab-switch__slider::before {
  content: ''; position: absolute; height: 16px; width: 16px; left: 3px; top: 3px;
  background: #fff; border-radius: 50%; transition: transform .15s;
}
.ab-switch input:checked + .ab-switch__slider { background: var(--bs-success, #28a745); }
.ab-switch input:checked + .ab-switch__slider::before { transform: translateX(16px); }
.ab-switch input:disabled + .ab-switch__slider { opacity: .6; cursor: progress; }

.ab-int-details > summary { cursor: pointer; color: var(--bs-primary, #2563eb); }
.ab-int-details[open] > summary { margin-bottom: .25rem; }

.ab-spinner {
  width: 12px; height: 12px; border: 2px solid var(--bs-secondary, #adb5bd);
  border-top-color: transparent; border-radius: 50%; display: inline-block;
  animation: ab-spin .6s linear infinite;
}
@keyframes ab-spin { to { transform: rotate(360deg); } }
</style>
