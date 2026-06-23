<template>
  <div class="ab-help-page">
    <PageHeader
      title="Help &amp; Troubleshooting"
      subtitle="Start from what you see, open the right AI Boost screen in one click, then verify on your public URL."
    >
      <a class="ab-btn ab-btn--ghost ab-btn--sm" :href="route('/health')"><AbIcon name="heart" /> Health</a>
      <a class="ab-btn ab-btn--ghost ab-btn--sm" :href="route('/autopilot')"><AbIcon name="bolt" /> Quick Setup</a>
    </PageHeader>

    <!-- A) I have a problem -->
    <div class="ab-section">
      <div class="ab-section__head"><AbIcon name="bug" /> I have a problem</div>
      <div class="ab-section__body">
        <div class="ab-help-grid">
          <article v-for="item in problems" :key="item.title" class="ab-card">
            <div class="ab-card__header">{{ item.title }}</div>
            <div class="ab-card__body">
              <p class="ab-help" style="margin:0">{{ item.meaning }}</p>
              <div class="ab-help-actions">
                <a
                  v-for="a in item.actions" :key="a.label" :href="a.url"
                  :class="['ab-btn', 'ab-btn--sm', a.primary ? 'ab-btn--primary' : 'ab-btn--ghost']"
                >{{ a.label }}</a>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <!-- B) I want to do something -->
    <div class="ab-section">
      <div class="ab-section__head"><AbIcon name="cog" /> I want to do something</div>
      <div class="ab-section__body">
        <div class="ab-help-grid">
          <article v-for="item in tasks" :key="item.title" class="ab-card">
            <div class="ab-card__header">{{ item.title }}</div>
            <div class="ab-card__body">
              <p class="ab-help" style="margin:0">{{ item.meaning }}</p>
              <div class="ab-help-actions">
                <a
                  v-for="a in item.actions" :key="a.label" :href="a.url"
                  :class="['ab-btn', 'ab-btn--sm', a.primary ? 'ab-btn--primary' : 'ab-btn--ghost']"
                >{{ a.label }}</a>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>

    <!-- C) Is my site ready to launch? -->
    <div class="ab-section">
      <div class="ab-section__head"><AbIcon name="check" /> Is my site ready to launch?</div>
      <div class="ab-section__body">
        <p class="ab-help" style="margin:0 0 .75rem">
          Run the automatic Health check first, then confirm each area below on your public site.
        </p>
        <div class="ab-row" style="margin-bottom:1rem">
          <a class="ab-btn ab-btn--primary ab-btn--sm" :href="route('/health')">Run Health check</a>
        </div>
        <div class="ab-help-checklist">
          <div v-for="item in launchChecklist" :key="item.area" class="ab-help-check-row">
            <AbIcon :name="item.icon" />
            <span>
              <strong>{{ item.area }}</strong>
              <small class="ab-help" style="display:block; margin-top:.1rem">{{ item.expected }}</small>
            </span>
            <a :href="item.url" class="ab-btn ab-btn--ghost ab-btn--sm">Open</a>
          </div>
        </div>
      </div>
    </div>

    <!-- D) Still stuck? -->
    <div class="ab-section">
      <div class="ab-section__head"><AbIcon name="help" /> Still stuck?</div>
      <div class="ab-section__body">
        <p class="ab-help" style="margin:0 0 .75rem">
          Copy this template, fill it in, and send it with your support request — it gives us
          everything we need to help quickly.
        </p>
        <textarea class="ab-textarea ab-help-template" readonly :value="supportTemplate" rows="11"></textarea>
        <div class="ab-help-actions" style="margin-top:.75rem">
          <button type="button" class="ab-btn ab-btn--secondary ab-btn--sm" @click="copySupportTemplate">
            {{ copied ? 'Copied' : 'Copy template' }}
          </button>
          <a class="ab-btn ab-btn--primary ab-btn--sm" href="https://aiboostnow.com/support" target="_blank" rel="noopener">
            Contact support
          </a>
        </div>

        <div style="margin-top:1.25rem">
          <div class="ab-help" style="margin:0 0 .5rem">Validate your output with external tools:</div>
          <div class="ab-help-actions">
            <a
              v-for="link in validators" :key="link.url" :href="link.url"
              target="_blank" rel="noopener" class="ab-btn ab-btn--ghost ab-btn--sm"
            ><AbIcon :name="link.icon" /> {{ link.title }}</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import PageHeader from './components/PageHeader.vue'
import AbIcon from './components/AbIcon.vue'

const APP_BASE = 'index.php?option=com_aiboost&view=app#'

export default {
  name: 'HelpPage',

  components: { PageHeader, AbIcon },

  data() {
    return {
      copied: false,

      // A) "I have a problem" — plain symptom → what it usually means → one-click
      //    jumps into the matching AI Boost screen. (Help links to the tools; it
      //    never duplicates Health / Conflict / Analyzer logic.)
      problems: [
        {
          title: 'Nothing is happening at all',
          meaning: 'No schema, sitemap or meta tags appear anywhere. Usually the plugins are not published, the main switch is off, or the page is cached.',
          actions: [
            { label: 'Run Health', url: this.route('/health'), primary: true },
            { label: 'Open Quick Setup', url: this.route('/autopilot') },
          ],
        },
        {
          title: "A feature isn't on my site",
          meaning: 'One area (schema, sitemap, social tags or AI files) is missing on the public page. Check its toggle, then test the exact public URL with the Analyzer.',
          actions: [
            { label: 'Open Analyzer', url: this.route('/analyzers'), primary: true },
            { label: 'Run Health', url: this.route('/health') },
            { label: 'Open Schema', url: this.settings('schema', 'enable_schema') },
          ],
        },
        {
          title: "An option won't take effect",
          meaning: 'You changed a setting but nothing changed on the site. Confirm it is enabled and saved, and that another extension has not taken over that output.',
          actions: [
            { label: 'Open Conflict Manager', url: this.route('/conflicts'), primary: true },
            { label: 'Open Settings', url: this.route('/settings') },
          ],
        },
        {
          title: 'Duplicate tags / plugin clash',
          meaning: 'Two extensions emit the same schema, meta or analytics. Use the Conflict Manager to decide which one owns each type of output.',
          actions: [
            { label: 'Open Conflict Manager', url: this.route('/conflicts'), primary: true },
          ],
        },
      ],

      // B) "I want to do something" — common tasks.
      tasks: [
        {
          title: 'Fine-tune my configuration',
          meaning: 'Revisit the guided setup to adjust the basics, or jump straight into a specific area to change it.',
          actions: [
            { label: 'Open Quick Setup', url: this.route('/autopilot'), primary: true },
            { label: 'Open Settings', url: this.route('/settings') },
          ],
        },
        {
          title: 'Back up & restore settings',
          meaning: 'Export downloads a backup file of every AI Boost setting; Import restores it here or on another site. Always export before a big change.',
          actions: [
            { label: 'Open Import / Export', url: this.route('/import'), primary: true },
          ],
        },
        {
          title: 'Set everything up the first time',
          meaning: 'The guided wizard walks you through identity, schema, sitemap and social in a few minutes.',
          actions: [
            { label: 'Open Quick Setup', url: this.route('/autopilot'), primary: true },
          ],
        },
      ],

      // C) Launch readiness — each row opens the matching settings tab.
      launchChecklist: [
        { area: 'Site Identity', icon: 'id',     expected: 'Organization name, logo, URL, contact and sameAs links are saved.', url: this.settings('org', 'org_name') },
        { area: 'Schema.org',    icon: 'schema', expected: 'The selected Schema Type appears in the frontend JSON-LD.',          url: this.settings('schema', 'schema_type') },
        { area: 'Sitemap',       icon: 'map',    expected: 'sitemap.xml opens publicly and includes the expected content.',      url: this.settings('sitemap', 'enable_sitemap') },
        { area: 'Social Meta',   icon: 'share',  expected: 'OpenGraph and X/Twitter tags match the public preview.',             url: this.settings('social', 'enable_opengraph') },
        { area: 'Analytics',     icon: 'chart',  expected: 'GA4, GTM and Meta Pixel are each emitted from one place only.',      url: this.settings('analytics', 'meta_pixel_id') },
        { area: 'AI Visibility', icon: 'ai',     expected: 'llms.txt and AI signals are reachable after cache is cleared.',      url: this.settings('aeo', 'llmstxt_enabled') },
      ],

      // External validators only (not our own website — those guide links are
      // deferred until the website redesign ships).
      validators: [
        { title: 'Google Rich Results', url: 'https://search.google.com/test/rich-results', icon: 'search' },
        { title: 'Search Console',      url: 'https://search.google.com/search-console',    icon: 'chart' },
        { title: 'Schema.org Validator', url: 'https://validator.schema.org/',              icon: 'code' },
      ],
    }
  },

  computed: {
    supportTemplate() {
      return [
        'AI Boost support request', '', 'Site URL:', 'Frontend URL tested:', 'AI Boost version:', 'Joomla version:', 'PHP version:', '',
        'What I expected:', 'What happened instead:', '', 'AI Boost screen involved:', 'Health result after saving:',
        'Analyzer result for the exact URL:', '', 'Cache / SEO / page builder / analytics plugins active:',
        'Recent change before the issue appeared:',
      ].join('\n')
    },
  },

  methods: {
    route(path) {
      return `${APP_BASE}${path}`
    },

    settings(tab, field = '') {
      const query = new URLSearchParams()
      if (tab) query.set('tab', tab)
      if (field) query.set('field', field)
      const suffix = query.toString() ? `?${query.toString()}` : ''

      return this.route(`/settings${suffix}`)
    },

    async copySupportTemplate() {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(this.supportTemplate)
      } else {
        const textarea = document.createElement('textarea')
        textarea.value = this.supportTemplate
        textarea.setAttribute('readonly', '')
        textarea.style.position = 'fixed'
        textarea.style.opacity = '0'
        document.body.appendChild(textarea)
        textarea.select()
        document.execCommand('copy')
        document.body.removeChild(textarea)
      }
      this.copied = true
      window.setTimeout(() => { this.copied = false }, 1800)
    },
  },
}
</script>

<style scoped>
/* Layout only — all colours come from the shared --ab-* tokens via the standard
   .ab-section / .ab-card / .ab-btn classes, so the page follows light & dark. */
.ab-help-page { max-width: 1080px; }

.ab-help-page > .ab-section { margin-bottom: 1rem; }

.ab-help-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
}

.ab-help-actions {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  margin-top: .75rem;
}

.ab-help-checklist { display: grid; gap: .5rem; }

.ab-help-check-row {
  display: grid;
  grid-template-columns: auto 1fr auto;
  gap: .6rem;
  align-items: start;
}

.ab-help-check-row > .ab-icon {
  margin-top: .15rem;
  color: var(--ab-primary);
}

.ab-help-template {
  width: 100%;
  font-family: var(--ab-font-mono);
  font-size: var(--ab-font-size-sm);
}

@media (max-width: 860px) {
  .ab-help-grid { grid-template-columns: 1fr; }
}
</style>
