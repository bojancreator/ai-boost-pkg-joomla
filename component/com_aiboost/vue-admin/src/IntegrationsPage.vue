<template>
  <div class="ab-integrations-page">

    <!-- Summary bar -->
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
      <div class="d-flex align-items-center gap-2">
        <span class="ab-badge ab-badge--success" :title="tooltip('support_active')">{{ supportActiveCount }} AI Boost active</span>
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
            <p class="text-muted small mb-0">{{ item.description }}</p>
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
      Add-on plugins extend AI Boost's schema and meta features for specific third-party components.
      <a href="https://aiboostnow.com/integrations" target="_blank" rel="noopener">Browse all integrations →</a>
    </div>

  </div>
</template>

<script>
export default {
  name: 'IntegrationsPage',

  data() {
    return {
      integrations: window.aiBoostIntegrations || [],
      search:          '',
      categoryFilter:  '',
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
    detectedCount()      { return this.integrations.filter(i => i.status === 'detected').length },
    comingSoonCount()    { return this.integrations.filter(i => i.status === 'coming_soon').length },
    roadmapCount()       { return this.integrations.filter(i => i.status === 'roadmap').length },
    notDetectedCount()   { return this.integrations.filter(i => i.status === 'not_detected').length },
  },

  methods: {
    statusLabel(status) {
      const map = {
        support_active:  '✅ AI Boost support active',
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
        support_active: 'Extension is installed in Joomla AND AI Boost actively integrates with it (avoids duplicate meta, reads its settings, etc.).',
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
.ab-int-card--detected       .ab-card__header { border-left: 3px solid var(--bs-secondary, #6c757d); }
.ab-int-card--coming_soon    .ab-card__header { border-left: 3px solid var(--bs-warning, #f59e0b); }
.ab-int-card--roadmap        .ab-card__header { border-left: 3px solid var(--bs-secondary, #6c757d); opacity: .7; }
.ab-int-card--not_detected   .ab-card__header { border-left: 3px solid var(--bs-secondary, #6c757d); opacity: .85; }

.ab-int-card--roadmap .ab-card__body,
.ab-int-card--roadmap .ab-card__footer { opacity: .7; }

.ab-badge--neutral { background: #e5e7eb; color: #374151; }
.ab-badge--warning { background: #fef3c7; color: #92400e; }
[data-bs-theme="dark"] .ab-badge--neutral { background: #374151; color: #e5e7eb; }
[data-bs-theme="dark"] .ab-badge--warning { background: #78350f; color: #fde68a; }

</style>
