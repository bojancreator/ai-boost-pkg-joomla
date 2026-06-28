<template>
  <div class="ab-autopilot-page">

    <PageHeader title="Quick Setup" subtitle="A guided, step-by-step setup of the core AI Boost configuration." />

    <!-- SUMMARY / INFO — shown once Quick Setup has been finished before -->
    <template v-if="!settingsReady">
      <div class="ab-section"><div class="ab-section__body"><p class="ab-help" style="margin:0">Loading your settings…</p></div></div>
    </template>

    <template v-else-if="mode === 'summary'">
      <div class="ab-alert ab-alert--success ab-wiz-doneline">
        <strong>Quick Setup complete.</strong> Your core AI Boost configuration is in place. Fine-tune anything from the menu, or run the guided setup again.
      </div>
      <div class="ab-section">
        <div class="ab-section__head">Your configuration</div>
        <div class="ab-section__body">
          <ul class="ab-wiz-summary">
            <li v-for="step in steps.slice(0, 4)" :key="step.id" :class="{ done: step.done }">
              <span class="ab-wiz-summary__tick" :class="{ 'is-no': !step.done }">{{ step.done ? '✓' : '!' }}</span>
              {{ step.title }} — {{ step.done ? 'configured' : 'needs attention' }}
            </li>
          </ul>
          <div class="ab-row" style="margin-top:1rem">
            <button type="button" class="ab-btn ab-btn--primary ab-btn--sm" @click="runAgain">Run setup again</button>
            <router-link class="ab-btn ab-btn--ghost ab-btn--sm" to="/dashboard">Open Dashboard</router-link>
            <router-link class="ab-btn ab-btn--ghost ab-btn--sm" to="/health">Open Health</router-link>
          </div>
        </div>
      </div>
    </template>

    <template v-else>
    <!-- Stepper -->
    <section class="ab-wiz-head" aria-label="Quick Setup progress">
      <div class="ab-wiz-head__top">
        <span class="ab-wiz-head__label">Step {{ currentIndex + 1 }} of {{ steps.length }} · {{ current.title }}</span>
        <span class="ab-tag ab-tag--neutral">{{ progressPercent }}% complete</span>
      </div>
      <div class="ab-wiz-head__bar"><div class="ab-wiz-head__fill" :style="{ width: progressPercent + '%' }"></div></div>
      <ol class="ab-wiz-steps">
        <li
          v-for="(step, i) in steps"
          :key="step.id"
          class="ab-wiz-step"
          :class="{ 'is-active': i === currentIndex, 'is-done': step.done }"
        >
          <span class="ab-wiz-step__dot">
            <template v-if="step.done && i !== currentIndex">✓</template>
            <template v-else>{{ i + 1 }}</template>
          </span>
          <span class="ab-wiz-step__name">{{ step.title }}</span>
        </li>
      </ol>
    </section>

    <!-- Step body -->
    <div class="ab-section ab-wiz-panel">
      <div class="ab-section__body">

        <!-- 1 · Identity -->
        <template v-if="current.id === 'identity'">
          <div class="ab-eyebrow">Site Identity</div>
          <p class="ab-help mb-3">Name, URL and logo — used across structured data and social previews.</p>
          <div class="ab-field">
            <label class="ab-label">Organization name</label>
            <input v-model="s.org_name" type="text" class="ab-input" placeholder="Acme Inc.">
          </div>
          <div class="ab-field">
            <label class="ab-label">Website URL</label>
            <input v-model="s.org_url" type="url" class="ab-input" placeholder="https://example.com">
          </div>
          <div class="ab-field">
            <label class="ab-label">Logo</label>
            <MediaPicker
              v-model="s.org_logo"
              field-key="org_logo"
              label="Organization logo"
              recommended-size="Recommended: square, at least 112×112 px."
            />
          </div>
        </template>

        <!-- 2 · Schema -->
        <template v-else-if="current.id === 'schema'">
          <div class="ab-eyebrow">Schema.org</div>
          <p class="ab-help mb-3">The structured-data foundation for your website or business.</p>
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable Schema.org output</div></div>
            <span class="ab-toggle" :class="{'is-on': s.enable_schema === '1'}">
              <input v-model="s.enable_schema" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <div class="ab-field">
            <label class="ab-label">What best describes this site?</label>
            <div class="ab-wiz-pills">
              <button
                v-for="opt in businessTypes"
                :key="opt.value"
                type="button"
                class="ab-wiz-pill"
                :class="{ 'is-active': s.schema_type === opt.value }"
                @click="s.schema_type = opt.value"
              >{{ opt.label }}</button>
            </div>
            <div class="ab-help">Sets the homepage Schema type. You can pick a more specific type any time on the Schema.org page.</div>
          </div>
        </template>

        <!-- 3 · Sitemap -->
        <template v-else-if="current.id === 'sitemap'">
          <div class="ab-eyebrow">Sitemap</div>
          <p class="ab-help mb-3">An XML sitemap helps search engines discover and index your pages.</p>
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable XML sitemap</div><div class="ab-help">Generates /sitemap.xml</div></div>
            <span class="ab-toggle" :class="{'is-on': s.enable_sitemap === '1'}">
              <input v-model="s.enable_sitemap" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-row">
            <div><div class="ab-label">Include articles</div></div>
            <span class="ab-toggle" :class="{'is-on': s.include_articles === '1'}">
              <input v-model="s.include_articles" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-row">
            <div><div class="ab-label">Include categories</div></div>
            <span class="ab-toggle" :class="{'is-on': s.include_categories === '1'}">
              <input v-model="s.include_categories" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-row">
            <div><div class="ab-label">Include menu items</div></div>
            <span class="ab-toggle" :class="{'is-on': s.include_menu_items === '1'}">
              <input v-model="s.include_menu_items" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
        </template>

        <!-- 4 · Social -->
        <template v-else-if="current.id === 'social'">
          <div class="ab-eyebrow">Social Meta</div>
          <p class="ab-help mb-3">Default preview data (OpenGraph + Twitter Cards) for shared pages and articles.</p>
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable OpenGraph tags</div></div>
            <span class="ab-toggle" :class="{'is-on': s.enable_opengraph === '1'}">
              <input v-model="s.enable_opengraph" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <div class="ab-field">
            <label class="ab-label">OG site name</label>
            <input v-model="s.site_name" type="text" class="ab-input" placeholder="Leave empty to use the Joomla site name">
          </div>
          <div class="ab-field">
            <label class="ab-label">Default share image</label>
            <MediaPicker
              v-model="s.default_og_image"
              field-key="default_og_image"
              label="Default OG image"
              recommended-size="Recommended: 1200×630 px. Shown when a page has no image."
            />
          </div>
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable Twitter Cards</div></div>
            <span class="ab-toggle" :class="{'is-on': s.enable_twitter_cards === '1'}">
              <input v-model="s.enable_twitter_cards" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
        </template>

        <!-- 5 · Finish -->
        <template v-else>
          <div class="ab-eyebrow">Review &amp; finish</div>
          <p class="ab-help mb-3">Here's what you've configured. Click <strong>Finish setup</strong> to complete — you can fine-tune anything later from the menu.</p>
          <ul class="ab-wiz-summary">
            <li v-for="step in steps.slice(0, 4)" :key="step.id" :class="{ done: step.done }">
              <span class="ab-wiz-summary__tick" :class="{ 'is-no': !step.done }">{{ step.done ? '✓' : '!' }}</span>
              {{ step.title }} — {{ step.done ? 'configured' : 'needs attention' }}
            </li>
          </ul>
        </template>

      </div>
    </div>

    <!-- Nav -->
    <div class="ab-wiz-nav">
      <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" :disabled="currentIndex === 0 || saving" @click="back">← Back</button>
      <span v-if="saveMsg" class="ab-help ab-wiz-nav__msg" :style="{ color: saveOk ? 'var(--ab-success)' : 'var(--ab-danger)' }">{{ saveMsg }}</span>
      <div class="ab-wiz-nav__right">
        <button
          v-if="currentIndex < steps.length - 1"
          type="button"
          class="ab-btn ab-btn--primary ab-btn--sm"
          :disabled="saving"
          @click="next"
        >
          <span v-if="saving" class="ab-spinner ab-spinner--sm me-1" aria-hidden="true"></span>
          {{ saving ? 'Saving…' : 'Continue →' }}
        </button>
        <button v-else type="button" class="ab-btn ab-btn--success ab-btn--sm" :disabled="saving" @click="finishSetup">
          <span v-if="saving" class="ab-spinner ab-spinner--sm me-1" aria-hidden="true"></span>
          {{ saving ? 'Saving…' : 'Finish setup' }}
        </button>
      </div>
    </div>

    </template>
    <!-- /WIZARD -->

  </div>
</template>

<script>
import { reactive } from 'vue'
import PageHeader from './components/PageHeader.vue'
import MediaPicker from './components/MediaPicker.vue'
import { saveSettings } from './api.js'
import { ensureLegacyGlobals } from './composables/useLegacyGlobals.js'

function configured(value) {
  if (value === true || value === 1) return true
  const normalized = String(value ?? '').trim().toLowerCase()
  return normalized !== '' && normalized !== '0' && normalized !== 'false' && normalized !== 'none'
}

// Business-type pills → real schema_type values (subset of SchemaTab's SCHEMA_CATEGORIES).
const BUSINESS_TYPES = [
  { value: 'LocalBusiness', label: 'Local Business' },
  { value: 'Organization',  label: 'Organization' },
  { value: 'Person',        label: 'Person' },
  { value: 'Store',         label: 'Online Store' },
]

// Keys saved when leaving each step (sent through saveSettings, which merges
// the partial over the full window.aiBoostSettings blob — so nothing is wiped).
const STEP_KEYS = {
  identity: ['org_name', 'org_url', 'org_logo'],
  schema:   ['enable_schema', 'schema_type'],
  sitemap:  ['enable_sitemap', 'include_articles', 'include_categories', 'include_menu_items'],
  social:   ['enable_opengraph', 'site_name', 'default_og_image', 'enable_twitter_cards'],
}

export default {
  name: 'AutopilotPage',

  components: { PageHeader, MediaPicker },

  data() {
    return {
      // Local editable copy; persisted to window.aiBoostSettings on each save.
      s: reactive(Object.assign({}, window.aiBoostSettings || {})),
      // Guard against a load race: on a direct /autopilot visit the settings blob
      // may not be materialised yet. Until it is, the wizard must NOT save — an
      // empty copy would otherwise overwrite (wipe) real settings on Continue.
      settingsReady: Object.keys(window.aiBoostSettings || {}).length > 0,
      // 'summary' once Quick Setup has been finished before (quick_setup_done); else the wizard.
      mode: configured((window.aiBoostSettings || {}).quick_setup_done) ? 'summary' : 'wizard',
      currentIndex: 0,
      saving: false,
      saveMsg: '',
      saveOk: true,
      businessTypes: BUSINESS_TYPES,
    }
  },

  async mounted() {
    if (this.settingsReady) return
    // Settings blob wasn't ready at mount (direct /autopilot load). Materialise it
    // the same way AppShell does, then sync our editable copy + mode.
    const legacyUrl = this.$route && this.$route.meta ? this.$route.meta.legacyUrl : ''
    if (legacyUrl) {
      try { await ensureLegacyGlobals(legacyUrl) } catch (e) { /* leave settingsReady false → saves stay blocked */ }
    }
    const blob = window.aiBoostSettings || {}
    if (Object.keys(blob).length > 0) {
      Object.assign(this.s, blob)
      this.mode = configured(blob.quick_setup_done) ? 'summary' : 'wizard'
      this.settingsReady = true
    }
  },

  computed: {
    steps() {
      const s = this.s
      const idDone      = configured(s.org_name) && configured(s.org_url) && configured(s.org_logo)
      const schemaDone  = configured(s.enable_schema) && configured(s.schema_type)
      const sitemapDone = configured(s.enable_sitemap)
      const socialDone  = configured(s.site_name) && configured(s.default_og_image)
      return [
        { id: 'identity', title: 'Identity', done: idDone },
        { id: 'schema',   title: 'Schema',   done: schemaDone },
        { id: 'sitemap',  title: 'Sitemap',  done: sitemapDone },
        { id: 'social',   title: 'Social',   done: socialDone },
        { id: 'finish',   title: 'Finish',   done: idDone && schemaDone && sitemapDone && socialDone },
      ]
    },
    current() {
      return this.steps[this.currentIndex]
    },
    progressPercent() {
      const done = this.steps.slice(0, 4).filter((step) => step.done).length
      return Math.round((done / 4) * 100)
    },
  },

  methods: {
    // Persist the current step's keys (merge-save) before advancing.
    async saveCurrent() {
      const keys = STEP_KEYS[this.current.id]
      if (!keys) return true
      if (!this.settingsReady) { this.saveOk = false; this.saveMsg = 'Settings are still loading — please wait a moment.'; return false }
      this.saving = true
      this.saveMsg = ''
      const payload = {}
      keys.forEach((k) => { payload[k] = this.s[k] ?? '' })
      try {
        await saveSettings(payload)
        if (window.aiBoostSettings) Object.assign(window.aiBoostSettings, payload)
        this.saveOk = true
        return true
      } catch (e) {
        this.saveOk = false
        this.saveMsg = 'Could not save — check your connection and try again.'
        return false
      } finally {
        this.saving = false
      }
    },

    async next() {
      const ok = await this.saveCurrent()
      if (ok && this.currentIndex < this.steps.length - 1) this.currentIndex++
    },

    back() {
      if (this.currentIndex > 0) this.currentIndex--
    },

    // Mark the wizard finished and switch to the summary/info view (does not touch other settings).
    async finishSetup() {
      if (!this.settingsReady) { this.saveOk = false; this.saveMsg = 'Settings are still loading — please wait a moment.'; return }
      this.saving = true
      this.saveMsg = ''
      try {
        await saveSettings({ quick_setup_done: '1' })
        if (window.aiBoostSettings) window.aiBoostSettings.quick_setup_done = '1'
        this.s.quick_setup_done = '1'
        this.saveOk = true
        this.mode = 'summary'
        this.currentIndex = 0
      } catch (e) {
        this.saveOk = false
        this.saveMsg = 'Could not save — check your connection and try again.'
      } finally {
        this.saving = false
      }
    },

    // Re-open the guided wizard from the summary view (keeps all settings + the done flag).
    runAgain() {
      this.currentIndex = 0
      this.mode = 'wizard'
    },
  },
}
</script>

<style scoped>
.ab-autopilot-page { }

/* Stepper header */
.ab-wiz-head { margin-bottom: var(--ab-space-4); }
.ab-wiz-head__top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.ab-wiz-head__label {
  font-family: var(--ab-font-mono); font-size: var(--ab-font-size-xs);
  text-transform: uppercase; letter-spacing: .03em; color: var(--ab-text-muted);
}
.ab-wiz-head__bar { height: 6px; border-radius: 999px; background: var(--ab-bg-muted); overflow: hidden; margin: .6rem 0 0; }
.ab-wiz-head__fill { height: 100%; background: var(--ab-primary); transition: width .3s ease-out; }

.ab-wiz-steps { display: flex; list-style: none; margin: 1rem 0 0; padding: 0; }
.ab-wiz-step {
  flex: 1 1 0; display: flex; flex-direction: column; align-items: center; gap: .45rem;
  position: relative; text-align: center; min-width: 0;
}
.ab-wiz-step + .ab-wiz-step::before {
  content: ''; position: absolute; top: 13px; right: 50%; width: 100%; height: 2px;
  background: var(--ab-border); z-index: 0;
}
.ab-wiz-step__dot {
  position: relative; z-index: 1; width: 28px; height: 28px; border-radius: 50%;
  display: grid; place-items: center; font-size: .8rem; font-weight: 600; line-height: 1;
  background: var(--ab-bg-elev); border: 2px solid var(--ab-border); color: var(--ab-text-muted);
}
.ab-wiz-step.is-done .ab-wiz-step__dot { background: var(--ab-success); border-color: var(--ab-success); color: #fff; }
.ab-wiz-step.is-active .ab-wiz-step__dot { background: var(--ab-primary); border-color: var(--ab-primary); color: var(--ab-text-on-primary, #fff); }
.ab-wiz-step__name {
  font-family: var(--ab-font-mono); font-size: var(--ab-font-size-xs);
  text-transform: uppercase; letter-spacing: .025em; color: var(--ab-text-muted);
}
.ab-wiz-step.is-active .ab-wiz-step__name { color: var(--ab-text); }

/* Step panel */
.ab-wiz-panel { margin-bottom: var(--ab-space-4); }
.ab-wiz-doneline { margin-bottom: var(--ab-space-4); }

/* Business-type pills */
.ab-wiz-pills { display: flex; flex-wrap: wrap; gap: .5rem; margin: .25rem 0; }
.ab-wiz-pill {
  padding: .5rem 1rem; border: 1px solid var(--ab-border); border-radius: var(--ab-radius);
  background: var(--ab-bg-elev); color: var(--ab-text); font-size: .9rem; cursor: pointer;
  transition: border-color .12s, background .12s, color .12s;
}
.ab-wiz-pill:hover { border-color: var(--ab-primary); }
.ab-wiz-pill.is-active { border-color: var(--ab-primary); background: var(--ab-primary-soft); color: var(--ab-primary); font-weight: 600; }

/* Finish summary */
.ab-wiz-summary { list-style: none; margin: .5rem 0 1rem; padding: 0; display: grid; gap: .45rem; }
.ab-wiz-summary li { display: flex; align-items: center; gap: .5rem; color: var(--ab-text-muted); }
.ab-wiz-summary li.done { color: var(--ab-text); }
.ab-wiz-summary__tick { color: var(--ab-success); font-weight: 700; }
.ab-wiz-summary__tick.is-no { color: var(--ab-warning); }

/* Nav */
.ab-wiz-nav { display: flex; align-items: center; gap: 1rem; }
.ab-wiz-nav__right { margin-left: auto; }
.ab-wiz-nav__msg { white-space: nowrap; }
</style>
