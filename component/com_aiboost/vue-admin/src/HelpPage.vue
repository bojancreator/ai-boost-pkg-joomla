<template>
  <div class="ab-help-page">
    <section class="ab-help-intro ab-panel mb-4">
      <div>
        <span class="ab-kicker">Help</span>
        <h2>Help &amp; Troubleshooting</h2>
        <p>
          Start with the symptom you see, open the matching AI Boost screen, then validate the exact public URL.
        </p>
      </div>
      <div class="ab-help-actions" aria-label="Primary help actions">
        <a class="ab-help-action" :href="route('/health')">
          <span class="icon-heart" aria-hidden="true"></span>
          Health
        </a>
        <a class="ab-help-action" :href="route('/analyzers')">
          <span class="icon-search" aria-hidden="true"></span>
          Analyzer
        </a>
      </div>
    </section>

    <section class="ab-panel mb-4">
      <div class="ab-section-head">
        <span class="icon-compass" aria-hidden="true"></span>
        <div>
          <h3>Choose Your Next Move</h3>
          <p>Use the path that matches the job in front of you.</p>
        </div>
      </div>
      <div class="ab-start-grid">
        <article v-for="item in startHere" :key="item.title" class="ab-help-tile">
          <div class="ab-tile-top">
            <span :class="[item.icon, 'ab-tile-icon']" aria-hidden="true"></span>
            <strong>{{ item.title }}</strong>
          </div>
          <p>{{ item.goal }}</p>
          <ol>
            <li v-for="step in item.steps" :key="step">{{ step }}</li>
          </ol>
          <a :href="item.url" class="ab-link-btn">Open {{ item.openLabel }}</a>
        </article>
      </div>
    </section>

    <section class="ab-panel mb-4">
      <div class="ab-section-head">
        <span class="icon-wrench" aria-hidden="true"></span>
        <div>
          <h3>Problem Solver</h3>
          <p>Fast checks for issues that usually block launch or support tickets.</p>
        </div>
      </div>
      <div class="ab-problem-list">
        <article v-for="problem in problems" :key="problem.title" class="ab-problem-row">
          <div class="ab-problem-main">
            <strong>{{ problem.title }}</strong>
            <p>{{ problem.symptom }}</p>
          </div>
          <div class="ab-problem-check">
            <span>Check first</span>
            <p>{{ problem.check }}</p>
          </div>
          <div class="ab-problem-fix">
            <span>Open</span>
            <p>{{ problem.fix }}</p>
          </div>
          <a :href="problem.url" class="ab-icon-link" :aria-label="'Open ' + problem.title">
            <span class="icon-arrow-right" aria-hidden="true"></span>
          </a>
        </article>
      </div>
    </section>

    <div class="ab-help-columns mb-4">
      <section class="ab-panel">
        <div class="ab-section-head">
          <span class="icon-check-circle" aria-hidden="true"></span>
          <div>
            <h3>Launch Validation</h3>
            <p>Run these checks after the last save.</p>
          </div>
        </div>
        <div class="ab-validation-list">
          <div v-for="item in validationChecklist" :key="item.area" class="ab-validation-row">
            <span :class="[item.icon, 'ab-validation-icon']" aria-hidden="true"></span>
            <span>
              <strong>{{ item.area }}</strong>
              <small>{{ item.expected }}</small>
            </span>
            <a :href="item.url" class="ab-small-link">Open</a>
          </div>
        </div>
      </section>

      <section class="ab-panel">
        <div class="ab-section-head">
          <span class="icon-life-ring" aria-hidden="true"></span>
          <div>
            <h3>Support Request</h3>
            <p>Copy this template before contacting support.</p>
          </div>
        </div>
        <div class="ab-support-box">
          <textarea class="ab-support-template" readonly :value="supportTemplate" rows="11"></textarea>
          <div class="ab-support-actions">
            <button type="button" class="ab-link-btn" @click="copySupportTemplate">
              <span class="icon-copy" aria-hidden="true"></span>
              {{ copied ? 'Copied' : 'Copy template' }}
            </button>
            <a class="ab-link-btn" href="https://aiboostnow.com/support" target="_blank" rel="noopener">
              <span class="icon-comments" aria-hidden="true"></span>
              Support
            </a>
          </div>
        </div>
      </section>
    </div>

    <section class="ab-panel">
      <div class="ab-section-head">
        <span class="icon-link" aria-hidden="true"></span>
        <div>
          <h3>External Validators</h3>
          <p>Use them after AI Boost Health and Analyzer are clean.</p>
        </div>
      </div>
      <div class="ab-reference-row">
        <a v-for="link in references" :key="link.url" :href="link.url" target="_blank" rel="noopener" class="ab-reference-link">
          <span :class="[link.icon, 'ab-reference-icon']" aria-hidden="true"></span>
          <span>{{ link.title }}</span>
        </a>
      </div>
    </section>
  </div>
</template>

<script>
const APP_BASE = 'index.php?option=com_aiboost&view=app#'

export default {
  name: 'HelpPage',

  data() {
    return {
      copied: false,
      startHere: [
        {
          title: 'Finish setup',
          icon: 'icon-flag',
          goal: 'Use this when the site is not fully configured yet.',
          steps: ['Complete Site Identity first.', 'Choose the correct Schema Type.', 'Enable Sitemap and Social Meta, then save.'],
          openLabel: 'Autopilot',
          url: this.route('/autopilot'),
        },
        {
          title: 'Fix missing output',
          icon: 'icon-eye',
          goal: 'Use this when frontend source, schema, or meta tags are missing.',
          steps: ['Run Health to find missing required fields.', 'Analyze the exact public URL.', 'Clear Joomla, template, and server cache.'],
          openLabel: 'Analyzer',
          url: this.route('/analyzers'),
        },
        {
          title: 'Validate before launch',
          icon: 'icon-rocket',
          goal: 'Use this after all settings are saved and content is public.',
          steps: ['Check Health and URL Analyzer.', 'Open sitemap.xml and llms.txt.', 'Run Google and social validators.'],
          openLabel: 'Health',
          url: this.route('/health'),
        },
        {
          title: 'Move or restore settings',
          icon: 'icon-upload',
          goal: 'Use this before a migration, reinstall, or risky configuration change.',
          steps: ['Export current settings.', 'Install or upgrade the package.', 'Import only after checking the target site.'],
          openLabel: 'Import',
          url: this.route('/import'),
        },
      ],
      problems: [
        { title: 'Schema JSON-LD is missing', symptom: 'Rich Results or page source does not show AI Boost schema.', check: 'Schema is enabled, Site Identity is filled, and the page is public.', fix: 'Schema.org', url: this.settings('schema', 'enable_schema') },
        { title: 'Wrong business type in JSON-LD', symptom: 'The frontend still outputs Organization or another old schema type.', check: 'Schema Type value, saved settings, and page cache.', fix: 'Schema Type', url: this.settings('schema', 'schema_type') },
        { title: 'Sitemap is stale or incomplete', symptom: 'sitemap.xml is missing recent articles, categories, or menu URLs.', check: 'Included content, exclusions, aliases, and cache.', fix: 'Sitemap', url: this.settings('sitemap', 'enable_sitemap') },
        { title: 'Robots or crawler rules look wrong', symptom: 'robots.txt, AI crawler rules, or scraper blocks do not match policy.', check: 'Crawlers & Robots settings and generated robots.txt.', fix: 'Crawlers & Robots', url: this.settings('crawlers', 'enable_robots') },
        { title: 'Social preview uses old title or image', symptom: 'Facebook, LinkedIn, WhatsApp, or X shows cached or wrong metadata.', check: 'OpenGraph enabled state, default image, article image, and platform cache.', fix: 'Social Meta', url: this.settings('social', 'enable_opengraph') },
        { title: 'Analytics or Pixel fires twice', symptom: 'GA4, GTM, or Meta Pixel appears duplicated in browser tools.', check: 'Template code, GTM container, other plugins, and AI Boost toggles.', fix: 'Analytics & Tracking', url: this.settings('analytics', 'meta_pixel_id') },
        { title: 'AI visibility files are unavailable', symptom: 'llms.txt, llms-full.txt, markdown pages, or IndexNow do not respond.', check: 'AI Visibility settings, SEF routing, permissions, and cache.', fix: 'AI Visibility', url: this.settings('aeo', 'llmstxt_enabled') },
        { title: 'Redirect or canonical result is unexpected', symptom: 'Analyzer reports redirect chains, status errors, or canonical mismatch.', check: 'Redirects, Technical SEO, menu aliases, HTTPS, and trailing slash policy.', fix: 'URL Checker', url: this.route('/urlchecker') },
      ],
      validationChecklist: [
        { area: 'Site Identity', icon: 'icon-users', expected: 'Organization name, logo, URL, contact, and sameAs links are saved.', url: this.settings('org', 'org_name') },
        { area: 'Schema.org', icon: 'icon-code', expected: 'The selected Schema Type appears in frontend JSON-LD.', url: this.settings('schema', 'schema_type') },
        { area: 'Sitemap', icon: 'icon-list', expected: 'sitemap.xml opens publicly and includes the expected content types.', url: this.settings('sitemap', 'enable_sitemap') },
        { area: 'Social Meta', icon: 'icon-share', expected: 'OpenGraph and X/Twitter tags match the public preview.', url: this.settings('social', 'enable_opengraph') },
        { area: 'Analytics', icon: 'icon-chart', expected: 'GA4, GTM, and Meta Pixel are each emitted from one place only.', url: this.settings('analytics', 'meta_pixel_id') },
        { area: 'AI Visibility', icon: 'icon-comments', expected: 'llms.txt and AI signals are reachable after cache is cleared.', url: this.settings('aeo', 'llmstxt_enabled') },
      ],
      references: [
        { title: 'Documentation', url: 'https://aiboostnow.com/docs', icon: 'icon-book' },
        { title: 'Troubleshooting', url: 'https://aiboostnow.com/docs/troubleshooting', icon: 'icon-wrench' },
        { title: 'Rich Results', url: 'https://search.google.com/test/rich-results', icon: 'icon-search' },
        { title: 'Search Console', url: 'https://search.google.com/search-console', icon: 'icon-google' },
        { title: 'Schema.org Validator', url: 'https://validator.schema.org/', icon: 'icon-code' },
        { title: 'Support', url: 'https://aiboostnow.com/support', icon: 'icon-comments' },
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
.ab-help-page {
  --help-panel: #1f2631;
  --help-panel-2: #252d3a;
  --help-border: #384250;
  --help-border-soft: #303947;
  --help-text: #eef3f8;
  --help-muted: #a8b3c2;
  --help-soft: #151b24;
  --help-accent: #4fb5ff;
  --help-accent-soft: rgba(79, 181, 255, .14);
  max-width: 1080px;
  color: var(--help-text);
}

.ab-panel {
  border: 1px solid var(--help-border);
  border-radius: 6px;
  background: var(--help-panel);
  box-shadow: 0 1px 0 rgba(255,255,255,.03) inset;
}

.ab-help-intro {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 18px;
}

.ab-kicker {
  display: inline-block;
  margin-bottom: 5px;
  color: var(--help-accent);
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
}

.ab-help-intro h2,
.ab-section-head h3 {
  margin: 0;
  color: var(--help-text);
}

.ab-help-intro h2 { font-size: 1.35rem; }

.ab-help-intro p,
.ab-section-head p,
.ab-help-tile p {
  margin: 4px 0 0;
  color: var(--help-muted);
  line-height: 1.45;
}

.ab-help-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.ab-help-action,
.ab-link-btn,
.ab-icon-link,
.ab-reference-link,
.ab-small-link {
  color: var(--help-text);
  text-decoration: none;
}

.ab-help-action {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
  padding: 9px 12px;
  border: 1px solid var(--help-accent);
  border-radius: 5px;
  background: var(--help-accent-soft);
  font-weight: 700;
}

.ab-section-head {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--help-border-soft);
}

.ab-section-head > span {
  color: var(--help-accent);
  margin-top: 3px;
}

.ab-section-head h3 { font-size: 1rem; }

.ab-start-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  padding: 14px;
}

.ab-help-tile,
.ab-problem-row,
.ab-validation-row,
.ab-reference-link,
.ab-support-template {
  border: 1px solid var(--help-border-soft);
  border-radius: 5px;
  background: var(--help-panel-2);
}

.ab-help-tile {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 13px;
}

.ab-tile-top {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ab-tile-icon,
.ab-reference-icon,
.ab-validation-icon { color: var(--help-accent); }

.ab-help-tile strong,
.ab-problem-main strong,
.ab-validation-row strong { color: var(--help-text); }

.ab-help-tile ol {
  margin: 0;
  padding-left: 18px;
  color: var(--help-muted);
  font-size: .84rem;
  line-height: 1.45;
}

.ab-help-tile li + li { margin-top: 5px; }

.ab-link-btn,
.ab-small-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid var(--help-border);
  border-radius: 4px;
  background: var(--help-soft);
  color: var(--help-text);
  font-weight: 700;
}

.ab-link-btn {
  align-self: flex-start;
  margin-top: auto;
  padding: 6px 10px;
  font-size: .8rem;
}

.ab-small-link {
  padding: 5px 8px;
  font-size: .75rem;
}

.ab-link-btn:hover,
.ab-help-action:hover,
.ab-icon-link:hover,
.ab-reference-link:hover,
.ab-small-link:hover {
  border-color: var(--help-accent);
  color: #fff;
}

.ab-problem-list {
  display: grid;
  gap: 8px;
  padding: 14px;
}

.ab-problem-row {
  display: grid;
  grid-template-columns: minmax(210px, 1.25fr) minmax(190px, 1fr) minmax(130px, .7fr) auto;
  gap: 12px;
  align-items: center;
  padding: 12px;
}

.ab-problem-main p,
.ab-problem-check p,
.ab-problem-fix p {
  margin: 4px 0 0;
  color: var(--help-muted);
  font-size: .82rem;
  line-height: 1.4;
}

.ab-problem-check span,
.ab-problem-fix span {
  color: var(--help-accent);
  font-size: .72rem;
  font-weight: 800;
  text-transform: uppercase;
}

.ab-icon-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1px solid var(--help-border);
  border-radius: 4px;
  background: var(--help-soft);
}

.ab-help-columns {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(320px, .8fr);
  gap: 14px;
}

.ab-validation-list,
.ab-support-box,
.ab-reference-row {
  display: grid;
  gap: 8px;
  padding: 14px;
}

.ab-validation-row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: flex-start;
  gap: 10px;
  padding: 10px;
}

.ab-validation-row small {
  display: block;
  margin-top: 2px;
  color: var(--help-muted);
  line-height: 1.4;
}

.ab-support-template {
  width: 100%;
  min-height: 228px;
  padding: 10px;
  color: var(--help-text);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
  font-size: .82rem;
  line-height: 1.45;
  resize: vertical;
}

.ab-support-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.ab-reference-row { grid-template-columns: repeat(6, minmax(0, 1fr)); }

.ab-reference-link {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 42px;
  padding: 9px 10px;
  color: var(--help-muted);
  font-size: .84rem;
}

@media (max-width: 1199.98px) {
  .ab-start-grid,
  .ab-reference-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }

  .ab-problem-row,
  .ab-help-columns { grid-template-columns: 1fr; }

  .ab-icon-link { justify-self: start; }
}

@media (max-width: 767.98px) {
  .ab-help-intro {
    display: grid;
    grid-template-columns: 1fr;
  }

  .ab-help-actions,
  .ab-help-action { justify-content: stretch; }

  .ab-help-action,
  .ab-start-grid,
  .ab-reference-row { grid-template-columns: 1fr; }

  .ab-validation-row { grid-template-columns: auto minmax(0, 1fr); }

  .ab-small-link {
    grid-column: 2;
    justify-self: start;
  }
}
</style>
