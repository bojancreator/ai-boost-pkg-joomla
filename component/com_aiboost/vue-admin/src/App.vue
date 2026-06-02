<template>
  <div class="ab-vue-settings" :style="{ '--ab-tab-color': activeTabColor }">

    <!-- ── Sticky top action bar (Settings A) ───────────────────── -->
    <div class="ab-action-bar">
      <div class="ab-action-bar__title">
        <span class="ab-action-bar__dot" :style="{ background: activeTabColor }"></span>
        <h2>Settings</h2>
        <span v-if="hasChanges" class="ab-action-bar__dirty">• Unsaved changes</span>
      </div>
      <div class="ab-action-bar__actions">
        <span :class="msgCls" class="ab-save-msg" v-if="message">{{ message }}</span>
        <button
          type="button"
          class="ab-btn ab-btn--secondary"
          :disabled="!hasChanges || saving"
          @click="discardChanges"
        >Discard</button>
        <button
          type="button"
          class="ab-btn ab-btn--primary"
          :disabled="saving"
          @click="save"
        >
          <span v-if="saving">Saving…</span>
          <span v-else>💾 Save All Settings</span>
        </button>
      </div>
    </div>

    <!-- ── Staging Mode Banner ──────────────────────────────────── -->
    <div v-if="s.staging_mode == 1 || s.staging_mode === '1' || s.staging_mode === true" class="ab-staging-banner">
      <span class="ab-staging-banner__icon">⚠️</span>
      <span class="ab-staging-banner__text">
        <strong>Staging Mode is ON.</strong>
        These plugins produce <strong>no HTML output</strong> on the front end:
        Analytics (GA4, GTM, Meta Pixel, GSC &amp; Facebook verification),
        Perf (canonical, title templates, redirects), Custom Code.
      </span>
      <button type="button" class="ab-staging-banner__btn" @click="disableStagingMode">
        Disable Staging Mode
      </button>
      <button type="button" class="ab-staging-banner__close" @click="selectTab('debug')" title="Open Debug tab">
        Open Debug tab →
      </button>
    </div>

    <!-- Plan B (v0.65.0) — in SPA mode the vertical grouped Sidebar drives
         sub-tab selection by routing to /settings?tab=<id> (handled by the
         `$route.query.tab` watcher below). The horizontal strip is kept for
         LEGACY standalone mode (view=settings, no router), where there is
         no Sidebar to switch tabs. -->
    <div v-if="isStandalone" class="ab-tab-strip" role="tablist">
      <button
        v-for="tab in tabs" :key="tab.id"
        class="ab-tab-strip__btn"
        :class="{ active: activeTab === tab.id }"
        :style="{ '--tab-color': tab.color }"
        type="button"
        role="tab"
        :aria-selected="activeTab === tab.id"
        @click="selectTab(tab.id)"
      >
        <span class="ab-tab-strip__icon" v-html="tab.icon"></span>
        <span class="ab-tab-strip__label">{{ tab.label }}</span>
      </button>
    </div>

    <!-- ── Tab panels ───────────────────────────────────────────── -->
    <div class="ab-tab-content">
      <GeneralTab   v-show="activeTab === 'general'"   :s="s" />
      <OrgTab       v-show="activeTab === 'org'"        :s="s" />
      <SchemaTab    v-show="activeTab === 'schema'"     :s="s" />
      <SitemapTab   v-show="activeTab === 'sitemap'"    :s="s" />
      <SocialTab    v-show="activeTab === 'social'"     :s="s" />
      <AnalyticsTab v-show="activeTab === 'analytics'"  :s="s" />
      <AeoTab       v-show="activeTab === 'aeo'"        :s="s" />
      <CodeTab      v-show="activeTab === 'code'"       :s="s" />
      <DebugTab     v-show="activeTab === 'debug'"      :s="s" />
    </div>

  </div>
</template>

<script>
import { saveSettings } from './api.js'
import { loadTranslationData, getAllTranslations } from './composables/useTranslations.js'
import GeneralTab   from './tabs/GeneralTab.vue'
import OrgTab       from './tabs/OrgTab.vue'
import SchemaTab    from './tabs/SchemaTab.vue'
import SitemapTab   from './tabs/SitemapTab.vue'
import SocialTab    from './tabs/SocialTab.vue'
import AnalyticsTab from './tabs/AnalyticsTab.vue'
import AeoTab       from './tabs/AeoTab.vue'
import CodeTab      from './tabs/CodeTab.vue'
import DebugTab     from './tabs/DebugTab.vue'

const ICONS = {
  general:   '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872l-.1-.34zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/></svg>',
  org:       '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M7 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1H7zm4-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5.784 6A2.238 2.238 0 0 1 5 13c0-1.355.68-2.75 1.936-3.72A6.325 6.325 0 0 0 5 9c-4 0-5 3-5 4s1 1 1 1h5.216zM4.5 8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/></svg>',
  schema:    '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M2.114 8.063V7.9c1.005-.102 1.497-.615 1.497-1.6V4.503c0-1.094.39-1.538 1.354-1.538h.273V2h-.376C3.25 2 2.49 2.759 2.49 4.352v1.524c0 1.094-.376 1.456-1.49 1.456v1.299c1.114 0 1.49.362 1.49 1.456v1.524c0 1.593.759 2.352 2.372 2.352h.376v-.964h-.273c-.964 0-1.354-.444-1.354-1.538V9.663c0-.984-.492-1.497-1.497-1.6zM13.886 7.9v.163c-1.005.103-1.497.616-1.497 1.6v1.798c0 1.094-.39 1.538-1.354 1.538h-.273v.964h.376c1.613 0 2.372-.759 2.372-2.352v-1.524c0-1.094.376-1.456 1.49-1.456V7.332c-1.114 0-1.49-.362-1.49-1.456V4.352C13.51 2.759 12.75 2 11.138 2h-.376v.964h.273c.964 0 1.354.444 1.354 1.538V6.3c0 .984.492 1.497 1.497 1.6z"/></svg>',
  sitemap:   '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5H2zm0 4a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5H2zm0 4a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5H2zm3-8a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1H5zm0 4a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1H5zm0 4a.5.5 0 0 0 0 1h7a.5.5 0 0 0 0-1H5z"/></svg>',
  social:    '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5z"/></svg>',
  analytics: '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M1 11a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1v-3zm5-4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v7a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V7zm5-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V2z"/></svg>',
  aeo:       '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M3.05 3.05a7 7 0 0 0 0 9.9.5.5 0 0 1-.707.707 8 8 0 0 1 0-11.314.5.5 0 0 1 .707.707zm2.122 2.122a4 4 0 0 0 0 5.656.5.5 0 1 1-.707.707 5 5 0 0 1 0-7.07.5.5 0 0 1 .707.707zm5.656-.707a.5.5 0 0 1 .707 0 5 5 0 0 1 0 7.07.5.5 0 1 1-.707-.707 4 4 0 0 0 0-5.656.5.5 0 0 1 0-.707zm2.122-2.122a.5.5 0 0 1 .707 0 8 8 0 0 1 0 11.314.5.5 0 1 1-.707-.707 7 7 0 0 0 0-9.9.5.5 0 0 1 0-.707zM10 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>',
  code:      '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M0 3a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3zm9.5 5.5h-3a.5.5 0 0 0 0 1h3a.5.5 0 0 0 0-1zm-6.354-.354a.5.5 0 1 0 .708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708l-2-2a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146z"/></svg>',
  debug:     '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M4.978.855a.5.5 0 1 0-.956.29l.41 1.352A4.985 4.985 0 0 0 3 6h10a4.985 4.985 0 0 0-1.432-3.503l.41-1.352a.5.5 0 1 0-.956-.29l-.291.956A4.978 4.978 0 0 0 8 1a4.979 4.979 0 0 0-2.731.811l-.29-.956zM13 6.5a.5.5 0 0 1 .5-.5h2a.5.5 0 0 1 0 1H14v1h1.5a.5.5 0 0 1 0 1H14v1h1.5a.5.5 0 0 1 0 1H14v.5a.5.5 0 0 1-.5.5h-.5a4.986 4.986 0 0 1-4 2 4.986 4.986 0 0 1-4-2H4.5a.5.5 0 0 1-.5-.5V11H2.5a.5.5 0 0 1 0-1H4v-1H2.5a.5.5 0 0 1 0-1H4V8H2.5a.5.5 0 0 1 0-1H4V6.5a.5.5 0 0 1 .5-.5H5V6h6v.5h.5z"/></svg>',
  health:    '<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314zM7.5 6.207 5.353 8.354a.5.5 0 1 0 .708.708L7.5 7.621V11.5a.5.5 0 0 0 1 0V7.621l1.439 1.44a.5.5 0 0 0 .708-.706L8.5 6.207V5a.5.5 0 0 0-1 0v1.207z"/></svg>',
  urlchecker:'<svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>',
}

const DEFAULTS = {
  schema_type:             'Organization',
  specific_price_range:    '',
  schema_hours_mode:       'none',
  manual_faq_scope:        'fallback_all',
  schema_faq_output_type:  'faqpage',
  schema_howto:            '',
  schema_author_entity_enabled: '0',
  ga4_consent_mode:        'none',
  pixel_consent_mode:      'none',
  default_changefreq:      'weekly',
  hide_comments:           '0',
  error_log_enabled:       '1',
  error_log_min_severity:  'warning',
}

export default {
  name: 'AiBoostSettings',
  components: { GeneralTab, OrgTab, SchemaTab, SitemapTab, SocialTab, AnalyticsTab, AeoTab, CodeTab, DebugTab },

  mounted() {
    // Hash-based deep link: #tab=<id>   (e.g. #tab=analytics)
    const hash = window.location.hash
    if (hash) {
      const match = hash.match(/^#tab=([a-z]+)$/)
      if (match) {
        const tabId = match[1]
        if (this.tabs.some(t => t.id === tabId)) {
          this.activeTab = tabId
        }
      }
    }
    // Query-based deep link: ?tab=<id>&field=<key>  (used by Health Fix It)
    try {
      const params = new URLSearchParams(window.location.search)
      const qTab   = params.get('tab')
      const qField = params.get('field')
      if (qTab && this.tabs.some(t => t.id === qTab)) {
        this.activeTab = qTab
      }
      if (qField) {
        this.$nextTick(() => setTimeout(() => this._scrollToField(qField), 180))
      }
    } catch (e) {}

    // Plan B (v0.65.0) — initialise the active sub-tab from the vue-router
    // query (the Sidebar links to /settings?tab=<id>). Hash routing keeps
    // this query inside the hash, so it is read here rather than from
    // window.location.search above.
    if (this.$route && this.$route.name === 'settings') {
      const rTab = this.$route.query.tab
      if (rTab && this.tabs.some(t => t.id === rTab)) {
        this.activeTab = rTab
      }
    }

    // Global event from Health tab → switch tab + scroll/highlight field
    this._gotoHandler = (e) => {
      const target = e.detail || {}
      if (target.tab && this.tabs.some(t => t.id === target.tab)) {
        this.activeTab = target.tab
      }
      if (target.field) {
        this.$nextTick(() => setTimeout(() => this._scrollToField(target.field), 180))
      }
    }
    window.addEventListener('aiboost:goto-field', this._gotoHandler)

    // Snapshot for Discard
    this._initialSnapshot = JSON.stringify(this.s)

    // Load Joomla languages + existing translations (non-blocking)
    loadTranslationData()
  },

  beforeUnmount() {
    if (this._gotoHandler) {
      window.removeEventListener('aiboost:goto-field', this._gotoHandler)
      this._gotoHandler = null
    }
  },

  data() {
    return {
      activeTab:   'general',
      saving: false,
      message: '',
      msgCls: '',
      s: Object.assign({}, DEFAULTS, window.aiBoostSettings || {}),
      tabs: [
        { id: 'general',   label: 'General',       icon: ICONS.general,   color: '#6366f1' },
        { id: 'org',       label: 'Organization',  icon: ICONS.org,       color: '#3b82f6' },
        { id: 'schema',    label: 'Schema.org',    icon: ICONS.schema,    color: '#8b5cf6' },
        { id: 'sitemap',   label: 'Sitemap',       icon: ICONS.sitemap,   color: '#14b8a6' },
        { id: 'social',    label: 'Social & Meta', icon: ICONS.social,    color: '#ec4899' },
        { id: 'analytics', label: 'Analytics',     icon: ICONS.analytics, color: '#f97316' },
        { id: 'aeo',       label: 'AEO',           icon: ICONS.aeo,       color: '#06b6d4' },
        { id: 'code',      label: 'Custom Code',   icon: ICONS.code,      color: '#f59e0b' },
        { id: 'debug',     label: 'Debug',         icon: ICONS.debug,     color: '#64748b' },
      ],
      _initialSnapshot: '',
      dirty: false,
    }
  },

  computed: {
    // Legacy standalone mode: this component is mounted directly on
    // #ab-vue-settings (view=settings) with no vue-router, so it must
    // render its own horizontal tab strip. In SPA mode the Sidebar
    // provides navigation instead.
    isStandalone() {
      return !this.$router
    },
    activeTabColor() {
      return this.tabs.find(t => t.id === this.activeTab)?.color || '#6366f1'
    },
    hasChanges() {
      return this.dirty
    },
  },

  watch: {
    s: {
      deep: true,
      handler() {
        // Lightweight flip — only stringify on save/discard, not per keystroke
        if (this._initialSnapshot) this.dirty = true
      },
    },
    // Plan B (v0.65.0) — the Sidebar switches settings sub-tabs by routing
    // to /settings?tab=<id>. React to query changes so the panel updates
    // without remounting this component.
    '$route.query.tab'(tab) {
      if (!this.$route || this.$route.name !== 'settings') return
      const id = tab || 'general'
      if (this.tabs.some(t => t.id === id)) this.activeTab = id
    },
  },

  methods: {
    selectTab(id) {
      this.activeTab = id
      // Keep the URL (and therefore the Sidebar active state) in sync when
      // running inside the SPA. In legacy standalone mode there is no
      // router, so we simply set activeTab above.
      if (this.$router && this.$route && this.$route.name === 'settings'
          && (this.$route.query.tab || 'general') !== id) {
        this.$router
          .replace({ path: '/settings', query: id === 'general' ? {} : { tab: id } })
          .catch(() => {})
      }
    },

    disableStagingMode() {
      this.s.staging_mode = 0
      this.selectTab('debug')
    },

    /**
     * Fix It deep-link target. Tries (in order):
     *   1. an element tagged with [data-ab-field="<key>"]   ← preferred
     *   2. an <input>/<select>/<textarea> whose v-model binds s.<key>
     *      (heuristic: id contains the key, or name === key)
     * If nothing matches, scrolls to top of tab panel.
     */
    _scrollToField(key) {
      if (!key) return
      const root = this.$el
      if (!root) return
      let el = root.querySelector('[data-ab-field="' + CSS.escape(key) + '"]')
      if (!el) {
        el = root.querySelector('#field-' + CSS.escape(key))
      }
      if (!el) {
        // Try matching by name attribute, then by v-model expression's data-key
        el = root.querySelector('[name="' + CSS.escape(key) + '"]')
      }
      if (!el) {
        // Heuristic: id contains the key (e.g. "g-ga4-id" for ga4_measurement_id)
        const cands = root.querySelectorAll('input, select, textarea, button')
        const k = key.toLowerCase()
        for (const c of cands) {
          const id = (c.id || '').toLowerCase()
          if (id && (id === k || id.includes(k))) { el = c; break }
        }
      }
      if (!el) return
      try {
        // Auto-open any collapsed <details> ancestors so the target is visible.
        let parent = el.parentElement
        while (parent && parent !== root) {
          if (parent.tagName === 'DETAILS' && !parent.open) {
            parent.open = true
          }
          parent = parent.parentElement
        }
        el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        el.classList.add('ab-field-highlight')
        setTimeout(() => el.classList.remove('ab-field-highlight'), 4200)
      } catch (e) {}
    },

    discardChanges() {
      if (!this._initialSnapshot) return
      if (!confirm('Discard all unsaved changes?')) return
      try {
        const snapshot = JSON.parse(this._initialSnapshot)
        // Restore field-by-field so the reactive object updates
        Object.keys(this.s).forEach(k => {
          if (!(k in snapshot)) delete this.s[k]
        })
        Object.keys(snapshot).forEach(k => {
          this.s[k] = snapshot[k]
        })
        this.$nextTick(() => { this.dirty = false })
        this.message = 'Changes discarded'
        this.msgCls  = 'text-secondary'
        setTimeout(() => { this.message = '' }, 2500)
      } catch (err) {
        this.message = 'Could not discard: ' + err.message
        this.msgCls  = 'text-danger'
      }
    },

    async save() {
      this.saving = true
      this.message = ''
      try {
        const result = await saveSettings(this.s, getAllTranslations())
        if (result.success) {
          this.message = '✓ Saved'
          this.msgCls  = 'text-success fw-medium'
          this._initialSnapshot = JSON.stringify(this.s)
          this.$nextTick(() => { this.dirty = false })
        } else {
          this.message = result.message || 'Save failed'
          this.msgCls  = 'text-danger'
        }
      } catch (err) {
        this.message = 'Error: ' + err.message
        this.msgCls  = 'text-danger'
      } finally {
        this.saving = false
        setTimeout(() => { this.message = '' }, 3500)
      }
    },
  },
}
</script>

<style>
/* ── Box sizing reset ────────────────────────────────────────── */
.ab-vue-settings,
.ab-vue-settings * { box-sizing: border-box; }
.ab-vue-settings { font-family: inherit; }

/* ── Sticky top action bar (Settings A) ──────────────────────── */
.ab-vue-settings .ab-action-bar {
  position: sticky;
  top: 0;
  z-index: 90;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  padding: 10px 14px;
  margin: -10px -10px 14px;
  background: var(--body-bg, #fff);
  border-bottom: 1px solid var(--border-color, #dee2e6);
  box-shadow: 0 2px 6px rgba(0, 0, 0, .04);
}
.ab-vue-settings .ab-action-bar__title {
  display: flex;
  align-items: center;
  gap: 10px;
}
.ab-vue-settings .ab-action-bar__title h2 {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  color: var(--body-color, #212529);
}
.ab-vue-settings .ab-action-bar__dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--ab-tab-color, #6366f1);
  transition: background .2s ease;
}
.ab-vue-settings .ab-action-bar__dirty {
  font-size: .78rem;
  color: #f59e0b;
  font-weight: 500;
}
.ab-vue-settings .ab-action-bar__actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.ab-vue-settings .ab-save-msg { font-size: .85rem; }

/* ── Horizontal tab strip (Settings A) ───────────────────────── */
.ab-vue-settings .ab-tab-strip {
  display: flex;
  gap: 2px;
  margin: 0 0 0;
  padding: 0 0 0;
  border-bottom: 1px solid var(--border-color, #dee2e6);
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: thin;
}
.ab-vue-settings .ab-tab-strip__btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  border: none;
  border-bottom: 3px solid transparent;
  background: transparent;
  color: var(--secondary-color, #6c757d);
  font-size: .875rem;
  cursor: pointer;
  white-space: nowrap;
  transition: color .15s, border-color .15s, background .15s;
  margin-bottom: -1px;
}
.ab-vue-settings .ab-tab-strip__btn:hover {
  color: var(--body-color, #212529);
  background: color-mix(in srgb, var(--tab-color, #6366f1) 6%, transparent);
}
.ab-vue-settings .ab-tab-strip__btn.active {
  color: var(--tab-color, #6366f1);
  border-bottom-color: var(--tab-color, #6366f1);
  font-weight: 600;
}
.ab-vue-settings .ab-tab-strip__icon {
  display: inline-flex;
  align-items: center;
}

/* ── Tab content ─────────────────────────────────────────────── */
.ab-vue-settings .ab-tab-content {
  overflow: visible;
  min-height: 300px;
  padding-top: 16px;
}

/* ── Form controls: inherit Atum theme colors ────────────────── */
.ab-vue-settings .form-select,
.ab-vue-settings .form-control {
  background-color: var(--body-bg, #fff);
  color: var(--body-color, #212529);
  border-color: var(--border-color, #ced4da);
}
.ab-vue-settings .form-select:focus,
.ab-vue-settings .form-control:focus {
  background-color: var(--body-bg, #fff);
  color: var(--body-color, #212529);
  border-color: var(--ab-tab-color, #86b7fe);
  outline: 0;
  box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--ab-tab-color, #6366f1) 25%, transparent);
}
.ab-vue-settings .form-select,
.ab-vue-settings select.form-select {
  -webkit-appearance: none !important;
  -moz-appearance: none !important;
  appearance: none !important;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
  background-repeat: no-repeat !important;
  background-position: right .75rem center !important;
  background-size: 16px 12px !important;
  padding-right: 2.25rem !important;
  cursor: pointer;
}
[data-bs-theme=dark] .ab-vue-settings .form-select,
[data-bs-theme=dark] .ab-vue-settings select.form-select {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23adb5bd' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
}
.ab-vue-settings .form-control::placeholder { color: var(--secondary-color, #6c757d); opacity: 1; }

/* ── Shared card styles (used in all tabs) ───────────────────── */
.ab-vue-settings .ab-card {
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 6px;
  background: var(--body-bg, #fff);
  color: var(--body-color, #212529);
  overflow: hidden;
  margin-bottom: 12px;
}
.ab-vue-settings .ab-card-header {
  display: flex;
  align-items: center;
  gap: .45rem;
  padding: .6rem 1rem .6rem .875rem;
  background: var(--secondary-bg, #f8f9fa);
  border-bottom: 1px solid var(--border-color, #dee2e6);
  border-left: 4px solid var(--ab-tab-color, #6366f1);
  font-weight: 600;
  font-size: .9375rem;
  color: var(--ab-tab-color, #6366f1);
  transition: border-left-color .2s ease, color .2s ease;
}
.ab-vue-settings .ab-card-body {
  padding: 1rem;
  color: var(--body-color, #212529);
}
.ab-vue-settings .form-label,
.ab-vue-settings .form-check-label { color: var(--body-color, #212529); }
.ab-vue-settings .form-text,
.ab-vue-settings small { color: var(--secondary-color, #6c757d); }
.ab-vue-settings code {
  color: #d63384;
  background: var(--secondary-bg, #f8f9fa);
  padding: .1em .3em;
  border-radius: 3px;
}
.ab-vue-settings pre {
  background: var(--secondary-bg, #f8f9fa);
  color: var(--body-color, #212529);
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 4px;
  padding: .75rem;
  font-size: .8rem;
}

/* ── Badges — pill shape ─────────────────────────────────────── */
.ab-vue-settings .ab-badge-free {
  display: inline-block;
  padding: .18rem .52rem;
  border-radius: 50px;
  font-size: .6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  background: #198754;
  color: #fff;
  vertical-align: middle;
  line-height: 1.4;
}
.ab-vue-settings .ab-badge-pro {
  display: inline-block;
  padding: .18rem .52rem;
  border-radius: 50px;
  font-size: .6rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .05em;
  background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
  color: #fff;
  vertical-align: middle;
  line-height: 1.4;
}

/* ── Staging Mode Banner ─────────────────────────────────────── */
.ab-vue-settings .ab-staging-banner {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 10px 14px;
  margin: 0 0 12px;
  background: #fff8e1;
  border: 1px solid #f59e0b;
  border-left: 4px solid #f59e0b;
  border-radius: 6px;
  font-size: .875rem;
  color: #7c5a00;
}
[data-bs-theme=dark] .ab-vue-settings .ab-staging-banner {
  background: #2a1f00;
  border-color: #b97a00;
  color: #ffd370;
}
.ab-vue-settings .ab-staging-banner__icon { font-size: 1.1rem; flex-shrink: 0; }
.ab-vue-settings .ab-staging-banner__text { flex: 1; min-width: 200px; }
.ab-vue-settings .ab-staging-banner__text strong { color: #92400e; }
[data-bs-theme=dark] .ab-vue-settings .ab-staging-banner__text strong { color: #ffc107; }
.ab-vue-settings .ab-staging-banner__btn {
  padding: 5px 14px;
  background: #f59e0b;
  color: #fff;
  border: none;
  border-radius: 4px;
  font-size: .8rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}
.ab-vue-settings .ab-staging-banner__btn:hover { background: #d97706; }
.ab-vue-settings .ab-staging-banner__close {
  padding: 5px 10px;
  background: transparent;
  color: #92400e;
  border: 1px solid #f59e0b;
  border-radius: 4px;
  font-size: .8rem;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
}
[data-bs-theme=dark] .ab-vue-settings .ab-staging-banner__close { color: #ffd370; }
.ab-vue-settings .ab-staging-banner__close:hover { background: color-mix(in srgb, #f59e0b 12%, transparent); }

/* ── Upgrade bar ─────────────────────────────────────────────── */
.ab-vue-settings .ab-upgrade-bar {
  padding: .55rem 1rem .7rem;
  background: #fffbf0;
  border-top: 1px solid #ffe8a1;
  text-align: center;
  color: var(--secondary-color, #6c757d);
  font-size: .85rem;
}
.ab-vue-settings .ab-upgrade-bar a { color: #0d6efd; }
[data-bs-theme=dark] .ab-vue-settings .ab-upgrade-bar {
  background: #2a2000;
  border-top-color: #4a3800;
}

/* ── Pro-lock overlay ────────────────────────────────────────── */
.ab-vue-settings .ab-disabled { opacity: .42; pointer-events: none; user-select: none; }

/* ── Section separator ───────────────────────────────────────── */
.ab-vue-settings .ab-sec {
  display: flex;
  align-items: center;
  gap: .4rem;
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--ab-tab-color, #6366f1);
  margin: 18px 0 10px;
  padding: 5px 8px 4px;
  background: color-mix(in srgb, var(--ab-tab-color, #6366f1) 7%, var(--secondary-bg, #f8f9fa));
  border-radius: 4px;
  border-bottom: none;
  transition: background .2s ease, color .2s ease;
}
.ab-vue-settings .ab-sec:first-child { margin-top: 0; }

/* ── Field row grid helper (for future per-tab migration) ──── */
.ab-vue-settings .ab-field-row {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 16px;
  align-items: start;
  padding: 10px 0;
  border-bottom: 1px solid var(--border-color, #dee2e6);
}
.ab-vue-settings .ab-field-row:last-child { border-bottom: none; }
.ab-vue-settings .ab-field-row > .ab-label {
  padding-top: 6px;
  margin: 0;
  font-weight: 500;
}
@media (max-width: 640px) {
  .ab-vue-settings .ab-field-row {
    grid-template-columns: 1fr;
    gap: 6px;
  }
}

/* ── Sticky-bar offset for embedded Joomla admin chrome ─── */
@media (min-width: 768px) {
  .ab-vue-settings .ab-action-bar {
    /* Joomla 4/5/6 admin toolbar is ~48px tall and itself sticky;
       sit below it so both stay visible. */
    top: 48px;
  }
}

/* ── Fix It field highlight pulse ──────────────────────────── */
.ab-vue-settings .ab-field-highlight,
.ab-vue-settings [data-ab-field].ab-field-highlight {
  outline: 4px solid #10b981;
  outline-offset: 4px;
  border-radius: 6px;
  background-color: rgba(16, 185, 129, .12);
  animation: ab-pulse 1.4s ease-out 3;
  transition: background-color .4s ease-out;
  position: relative;
  z-index: 2;
}
@keyframes ab-pulse {
  0%   { box-shadow: 0 0 0 0   rgba(16, 185, 129, .65); }
  70%  { box-shadow: 0 0 0 22px rgba(16, 185, 129, 0); }
  100% { box-shadow: 0 0 0 0   rgba(16, 185, 129, 0); }
}

/* ── Tab strip mobile: allow horizontal scroll ─────────────── */
@media (max-width: 640px) {
  .ab-vue-settings .ab-tab-strip__label { display: none; }
  .ab-vue-settings .ab-tab-strip__btn { padding: 10px 12px; }
}
</style>
