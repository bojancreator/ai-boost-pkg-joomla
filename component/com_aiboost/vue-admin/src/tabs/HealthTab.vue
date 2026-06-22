<template>
  <div class="ab-health-tab">

    <!-- Intro / Controls -->
    <div class="ab-card">
      <div class="ab-card-header">🩺 Site Health — Runtime Check</div>
      <div class="ab-card-body">
        <p style="margin:0 0 10px;">
          Verifies that every feature you have enabled in Settings actually appears in the
          public site output. Click <strong>Run Live Scan</strong> to fetch your pages
          server-side and compare the rendered HTML &amp; HTTP headers against expected
          artifacts.
        </p>

        <div class="ab-field-row" style="grid-template-columns: 200px 1fr; align-items:center; row-gap:8px;">
          <label class="ab-label">Homepage URL</label>
          <input v-model="homeUrl" type="url" class="ab-input"
            placeholder="https://example.com/" />
          <label class="ab-label">Sample article URL <span class="ab-help" style="font-weight:normal;">(optional but recommended)</span></label>
          <input v-model="articleUrl" type="url" class="ab-input"
            placeholder="https://example.com/news/sample-article.html" />
        </div>

        <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
          <button type="button" class="ab-btn ab-btn--primary"
            :disabled="scanning" @click="runScan">
            <span v-if="scanning" class="ab-spinner ab-spinner--sm me-2" aria-hidden="true"></span>
            <span v-if="scanning">Scanning…</span>
            <span v-else>🔍 Run Live Scan</span>
          </button>
          <button type="button" class="ab-btn ab-btn--secondary"
            :disabled="scanning" @click="resetScan">Reset</button>
        </div>

        <div v-if="scanError" class="ab-help" style="color:var(--ab-danger); margin-top:8px;">
          Scan failed: {{ scanError }}
        </div>
        <div v-else-if="lastScanAt" class="ab-help" style="margin-top:8px;">
          Last scan: {{ lastScanAt }} —
          <span v-for="(p, i) in pages" :key="i">
            <code>{{ shortUrl(p.url) }}</code> HTTP {{ p.status }} ({{ p.bytes || 0 }} B)<span v-if="i < pages.length - 1">, </span>
          </span>
        </div>
      </div>
    </div>

    <!-- Summary counters -->
    <div class="ab-card">
      <div class="ab-card-header">📊 Summary</div>
      <div class="ab-card-body" style="display:flex; gap:14px; flex-wrap:wrap;">
        <span class="ab-stat ab-stat--ok">✅ OK {{ counts.ok }}</span>
        <span class="ab-stat ab-stat--broken">❌ Broken {{ counts.broken }}</span>
        <span class="ab-stat ab-stat--disabled">⚠️ Disabled {{ counts.disabled }}</span>
        <span class="ab-stat ab-stat--invalid">🚫 Invalid setting {{ counts.invalid }}</span>
        <span class="ab-stat ab-stat--unknown">❓ Not scanned {{ counts.unknown }}</span>
      </div>
    </div>

    <!-- Groups -->
    <div v-for="group in grouped" :key="group.plugin" class="ab-card">
      <div class="ab-card-header">{{ group.icon }} {{ group.plugin }}</div>
      <div class="ab-card-body" style="padding:0;">
        <table class="ab-health-table">
          <thead>
            <tr>
              <th style="width:130px;">Status</th>
              <th>Check</th>
              <th style="width:230px;">Expected</th>
              <th style="width:110px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in group.rows" :key="r.id" :class="'ab-row-' + r.status">
              <td>
                <span class="ab-status" :class="'ab-status--' + r.status">
                  <span v-if="r.status === 'ok'">✅ OK</span>
                  <span v-else-if="r.status === 'broken'">❌ Broken</span>
                  <span v-else-if="r.status === 'disabled'">⚠️ Disabled</span>
                  <span v-else-if="r.status === 'invalid'">🚫 Invalid</span>
                  <span v-else>❓ Unknown</span>
                </span>
              </td>
              <td>
                <div class="ab-check-title">{{ r.title }}</div>
                <div class="ab-check-detail" v-if="r.detail">{{ r.detail }}</div>
              </td>
              <td>
                <code v-if="r.expectedLabel" class="ab-expected">{{ r.expectedLabel }}</code>
                <span v-else class="ab-help">—</span>
              </td>
              <td>
                <button type="button"
                  class="ab-btn ab-btn--secondary ab-btn--xs"
                  :disabled="!r.target"
                  @click="fixIt(r)">🛠 Fix It</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Debug log -->
    <div class="ab-card">
      <div class="ab-card-header">
        <button type="button" class="ab-link-btn" @click="showDebug = !showDebug">
          🐛 Debug log ({{ debugLog.length }}) — {{ showDebug ? 'hide' : 'show' }}
        </button>
      </div>
      <div v-if="showDebug" class="ab-card-body">
        <pre class="ab-debug-pre">{{ debugLogText }}</pre>
      </div>
    </div>

  </div>
</template>

<script>
/*
 * HealthTab — runtime analyzer for AI Boost.
 *
 * Architecture:
 *   1. Settings parity layer
 *      Each check declares `settingsKey` and an optional `validate(value, s)`
 *      that runs format checks (GA4 / GTM IDs, non-empty strings, etc.).
 *   2. Live scan layer (backend)
 *      Calls com_aiboost task `health.scan` with the homepage URL + optional
 *      sample article URL. Backend fetches via HttpFactory (SSRF-protected,
 *      same-origin only), returns parsed HTML strings + headers + file probes.
 *      We then DOM-parse client-side and evaluate each check against
 *      whichever scanned page yields a positive match.
 *
 * Status order: disabled → invalid → unknown → broken → ok.
 *
 * Fix It dispatches `aiboost:goto-field` event AND updates the URL so the
 * deep link is shareable: ?tab=<tab>&field=<field>.
 */

import { postWithCsrf, makeAdminUrl } from '../api.js'

const TRUTHY = (v) => v === '1' || v === 1 || v === true || v === 'true'

const isGa4    = (v) => /^G-[A-Z0-9]{6,}$/.test(String(v || '').trim())
const isGtm    = (v) => /^GTM-[A-Z0-9]{4,}$/.test(String(v || '').trim())
const isNumStr = (v) => /^\d{6,}$/.test(String(v || '').trim())
const nonEmpty = (v) => String(v == null ? '' : v).trim().length > 0

/* eslint-disable max-len */
const HEALTH_CHECKS = [
  /* ── Schema.org ───────────────────────────────────────────── */
  {
    id: 'schema_jsonld',
    plugin: 'Schema',
    title: 'Organization JSON-LD block',
    settingsKey: 'enable_schema',
    expected: { type: 'jsonld', regex: /"@type"\s*:\s*"(Organization|LocalBusiness|Hotel|Restaurant|MedicalBusiness|LegalService|EducationalOrganization|SportsActivityLocation|Dentist|RealEstateAgent|NewsMediaOrganization)"/i },
    expectedLabel: 'application/ld+json @type=Organization',
    target: { tab: 'org', field: 'org_name' },
  },
  {
    id: 'schema_search_action',
    plugin: 'Schema',
    title: 'WebSite + SearchAction (sitelinks search box)',
    settingsKey: 'enable_search_action',
    expected: { type: 'jsonld', regex: /"@type"\s*:\s*"SearchAction"/ },
    expectedLabel: '@type: SearchAction',
    target: { tab: 'schema', field: 'enable_search_action' },
  },
  {
    id: 'schema_breadcrumb',
    plugin: 'Schema',
    title: 'BreadcrumbList JSON-LD (article pages)',
    settingsKey: 'enable_schema',
    expected: { type: 'jsonld', regex: /"@type"\s*:\s*"BreadcrumbList"/ },
    expectedLabel: '@type: BreadcrumbList',
    target: { tab: 'schema', field: 'enable_schema' },
    needsArticle: true,
  },
  {
    id: 'schema_faqpage',
    plugin: 'Schema',
    title: 'FAQ schema (manual FAQs enabled)',
    settingsKey: 'enable_manual_faqs',
    expected: { type: 'jsonld', regex: /"@type"\s*:\s*"FAQPage"/ },
    expectedLabel: '@type: FAQPage',
    target: { tab: 'schema', field: 'enable_manual_faqs' },
    needsArticle: true,
  },

  /* ── OpenGraph / Twitter ──────────────────────────────────── */
  {
    id: 'og_title',
    plugin: 'OpenGraph & Social',
    title: 'og:title meta tag',
    settingsKey: 'enable_opengraph',
    expected: { type: 'selector', selector: 'meta[property="og:title"]' },
    expectedLabel: 'meta[property="og:title"]',
    target: { tab: 'social', field: 'enable_opengraph' },
  },
  {
    id: 'og_description',
    plugin: 'OpenGraph & Social',
    title: 'og:description meta tag',
    settingsKey: 'enable_opengraph',
    expected: { type: 'selector', selector: 'meta[property="og:description"]' },
    expectedLabel: 'meta[property="og:description"]',
    target: { tab: 'social', field: 'enable_opengraph' },
  },
  {
    id: 'og_image',
    plugin: 'OpenGraph & Social',
    title: 'og:image meta tag',
    settingsKey: 'enable_opengraph',
    expected: { type: 'selector', selector: 'meta[property="og:image"]' },
    expectedLabel: 'meta[property="og:image"]',
    target: { tab: 'social', field: 'default_og_image' },
  },
  {
    id: 'og_site_name',
    plugin: 'OpenGraph & Social',
    title: 'og:site_name meta tag',
    settingsKey: 'enable_opengraph',
    expected: { type: 'selector', selector: 'meta[property="og:site_name"]' },
    expectedLabel: 'meta[property="og:site_name"]',
    target: { tab: 'social', field: 'site_name' },
  },
  {
    id: 'twitter_card',
    plugin: 'OpenGraph & Social',
    title: 'twitter:card meta tag',
    settingsKey: 'enable_twitter_cards',
    expected: { type: 'selector', selector: 'meta[name="twitter:card"]' },
    expectedLabel: 'meta[name="twitter:card"]',
    target: { tab: 'social', field: 'enable_twitter_cards' },
  },
  {
    id: 'fb_domain',
    plugin: 'OpenGraph & Social',
    title: 'Facebook domain verification meta',
    settingsKey: 'fb_domain_verification',
    settingsCheck: (s) => nonEmpty(s.fb_domain_verification),
    expected: { type: 'selector', selector: 'meta[name="facebook-domain-verification"]' },
    expectedLabel: 'meta[name="facebook-domain-verification"]',
    target: { tab: 'analytics', field: 'fb_domain_verification' },
  },

  /* ── Analytics ────────────────────────────────────────────── */
  {
    id: 'ga4',
    plugin: 'Analytics',
    title: 'Google Analytics 4 (gtag) script',
    settingsKey: 'enable_ga4',
    validate: (_v, s) => isGa4(s.ga4_measurement_id) ? null : 'GA4 Measurement ID must look like "G-XXXXXXXX".',
    expected: { type: 'regex', regex: /googletagmanager\.com\/gtag\/js\?id=G-[A-Z0-9]+/ },
    expectedLabel: 'gtag.js?id=G-...',
    target: { tab: 'analytics', field: 'ga4_measurement_id' },
  },
  {
    id: 'gtm',
    plugin: 'Analytics',
    title: 'Google Tag Manager container',
    settingsKey: 'enable_gtm',
    validate: (_v, s) => isGtm(s.gtm_container_id) ? null : 'GTM Container ID must look like "GTM-XXXXXX".',
    expected: { type: 'regex', regex: /googletagmanager\.com\/gtm\.js\?id=GTM-[A-Z0-9]+/ },
    expectedLabel: 'gtm.js?id=GTM-...',
    target: { tab: 'analytics', field: 'gtm_container_id' },
  },
  {
    id: 'meta_pixel',
    plugin: 'Analytics',
    title: 'Meta (Facebook) Pixel',
    settingsKey: 'enable_meta_pixel',
    validate: (_v, s) => isNumStr(s.meta_pixel_id) ? null : 'Meta Pixel ID must be a numeric string (6+ digits).',
    expected: { type: 'regex', regex: /connect\.facebook\.net\/[^/]+\/fbevents\.js|fbq\s*\(\s*['"]init['"]/ },
    expectedLabel: 'fbevents.js / fbq("init", …)',
    target: { tab: 'analytics', field: 'enable_meta_pixel' },
  },
  {
    id: 'gsc_verification',
    plugin: 'Analytics',
    title: 'Google Search Console verification meta',
    settingsKey: 'enable_google_verification',
    validate: (_v, s) => nonEmpty(s.gsc_verification_code) || nonEmpty(s.gsc_codes) ? null : 'GSC verification code is empty.',
    expected: { type: 'selector', selector: 'meta[name="google-site-verification"]' },
    expectedLabel: 'meta[name="google-site-verification"]',
    target: { tab: 'analytics', field: 'enable_google_verification' },
  },

  /* ── AI Visibility ────────────────────────────────────────── */
  {
    id: 'aeo_meta_verified',
    plugin: 'AI Visibility',
    title: 'ai-content-verified meta tag',
    settingsKey: 'aeo_ai_meta_enabled',
    expected: { type: 'selector', selector: 'meta[name="ai-content-verified"]' },
    expectedLabel: 'meta[name="ai-content-verified"]',
    target: { tab: 'aeo', field: 'aeo_ai_meta_enabled' },
  },
  {
    id: 'aeo_meta_optimized',
    plugin: 'AI Visibility',
    title: 'ai-content-optimized meta tag',
    settingsKey: 'aeo_ai_meta_enabled',
    expected: { type: 'selector', selector: 'meta[name="ai-content-optimized"]' },
    expectedLabel: 'meta[name="ai-content-optimized"]',
    target: { tab: 'aeo', field: 'aeo_ai_meta_enabled' },
  },
  {
    id: 'aeo_llms_meta',
    plugin: 'AI Visibility',
    title: 'llms-txt discovery meta tag',
    settingsKey: 'aeo_ai_meta_enabled',
    expected: { type: 'selector', selector: 'meta[name="llms-txt"]' },
    expectedLabel: 'meta[name="llms-txt"]',
    target: { tab: 'aeo', field: 'aeo_ai_meta_enabled' },
  },
  {
    id: 'aeo_xrobots',
    plugin: 'AI Visibility',
    title: 'X-Robots-Tag HTTP header',
    settingsKey: 'aeo_ai_meta_enabled',
    expected: { type: 'header', name: 'x-robots-tag', regex: /index/i },
    expectedLabel: 'X-Robots-Tag: index, follow',
    target: { tab: 'aeo', field: 'aeo_ai_meta_enabled' },
    optional: true,
  },
  {
    id: 'aeo_markdown_alternate',
    plugin: 'AI Visibility',
    title: '<link rel="alternate" type="text/markdown">',
    settingsKey: 'markdown_pages_enabled',
    expected: { type: 'selector', selector: 'link[rel="alternate"][type="text/markdown"]' },
    expectedLabel: 'link[type="text/markdown"]',
    target: { tab: 'aeo', field: 'markdown_pages_enabled' },
    needsArticle: true,
  },
  {
    id: 'aeo_llms_txt_file',
    plugin: 'AI Visibility',
    title: 'Public /llms.txt file responds',
    settingsKey: 'llmstxt_enabled',
    expected: { type: 'urlReachable', path: '/llms.txt' },
    expectedLabel: 'HTTP 200 on /llms.txt',
    target: { tab: 'aeo', field: 'llmstxt_enabled' },
  },
  {
    id: 'aeo_llms_full_file',
    plugin: 'AI Visibility',
    title: 'Public /llms-full.txt file responds',
    settingsKey: 'llms_full_txt_enabled',
    expected: { type: 'urlReachable', path: '/llms-full.txt' },
    expectedLabel: 'HTTP 200 on /llms-full.txt',
    target: { tab: 'aeo', field: 'llms_full_txt_enabled' },
  },
  {
    id: 'aeo_robots_file',
    plugin: 'AI Visibility',
    title: 'Public /robots.txt file responds',
    settingsKey: 'enable_robots',
    expected: { type: 'urlReachable', path: '/robots.txt' },
    expectedLabel: 'HTTP 200 on /robots.txt',
    target: { tab: 'crawlers', field: 'enable_robots' },
  },

  /* ── Sitemap ──────────────────────────────────────────────── */
  {
    id: 'sitemap_file',
    plugin: 'Sitemap',
    title: 'Public /sitemap.xml file responds',
    settingsKey: 'enable_sitemap',
    expected: { type: 'urlReachable', path: '/sitemap.xml', contentType: /xml/i },
    expectedLabel: 'HTTP 200 + XML on /sitemap.xml',
    target: { tab: 'sitemap', field: 'enable_sitemap' },
  },
  {
    id: 'sitemap_hreflang',
    plugin: 'Sitemap',
    title: 'hreflang annotations on multilingual sites',
    settingsKey: 'enable_hreflang',
    expected: { type: 'selector', selector: 'link[rel="alternate"][hreflang]' },
    expectedLabel: 'link[rel="alternate"][hreflang]',
    target: { tab: 'sitemap', field: 'enable_hreflang' },
  },

  /* ── Perf / Canonical ─────────────────────────────────────── */
  {
    id: 'canonical_link',
    plugin: 'Canonical & Perf',
    title: '<link rel="canonical"> tag',
    settingsKey: 'enable_canonical',
    expected: { type: 'selector', selector: 'link[rel="canonical"]' },
    expectedLabel: 'link[rel="canonical"]',
    target: { tab: 'titles', field: 'enable_canonical' },
  },

  /* ── Title / Meta description ─────────────────────────────── */
  {
    id: 'title_tag',
    plugin: 'Title & Meta',
    title: 'Page <title> tag rendered',
    settingsKey: null,
    expected: { type: 'selector', selector: 'title', nonEmpty: true },
    expectedLabel: '<title> non-empty',
    target: { tab: 'general' },
  },
  {
    id: 'meta_description',
    plugin: 'Title & Meta',
    title: 'meta[name="description"] tag rendered',
    settingsKey: null,
    expected: { type: 'selector', selector: 'meta[name="description"]', nonEmpty: true },
    expectedLabel: 'meta[name="description"]',
    target: { tab: 'general' },
  },
]
/* eslint-enable max-len */

const GROUP_ICONS = {
  'Schema':              '🧩',
  'OpenGraph & Social':  '📱',
  'Analytics':           '📈',
  'AEO':                 '🤖',
  'Sitemap':             '🗺️',
  'Canonical & Perf':    '⚡',
  'Title & Meta':        '🏷️',
}

export default {
  name: 'HealthTab',
  props: { s: { type: Object, required: true } },

  data() {
    return {
      homeUrl: '',
      articleUrl: '',
      scanning: false,
      scanError: '',
      lastScanAt: '',
      pages: [],          // [{ url, ok, status, bytes, doc, headers (Map) }]
      reachable: {},      // { path: { ok, status, contentType } }
      debugLog: [],
      showDebug: false,
    }
  },

  mounted() {
    // Default homepage URL: derive from current admin URL (handles subfolder).
    try {
      const adminPath = window.location.pathname.toLowerCase()
      const idx       = adminPath.indexOf('/administrator')
      const basePath  = idx >= 0 ? adminPath.substring(0, idx) : ''
      this.homeUrl    = window.location.origin + (basePath || '') + '/'
    } catch (e) {
      this.homeUrl = window.location.origin + '/'
    }
  },

  computed: {
    rows() {
      return HEALTH_CHECKS.map(c => this._evaluate(c))
    },
    counts() {
      const c = { ok: 0, broken: 0, disabled: 0, invalid: 0, unknown: 0 }
      this.rows.forEach(r => { c[r.status] = (c[r.status] || 0) + 1 })
      return c
    },
    grouped() {
      const map = new Map()
      for (const r of this.rows) {
        if (!map.has(r.plugin)) map.set(r.plugin, [])
        map.get(r.plugin).push(r)
      }
      return [...map.entries()].map(([plugin, rows]) => ({
        plugin,
        icon: GROUP_ICONS[plugin] || '•',
        rows,
      }))
    },
    debugLogText() {
      return this.debugLog.join('\n')
    },
    scannedDocs() {
      return this.pages.filter(p => p.doc).map(p => ({ doc: p.doc, headers: p.headers, url: p.url }))
    },
  },

  methods: {
    _enabled(check) {
      if (check.settingsCheck) return !!check.settingsCheck(this.s)
      if (!check.settingsKey)  return true
      return TRUTHY(this.s[check.settingsKey])
    },

    _validate(check) {
      if (typeof check.validate !== 'function') return null
      try { return check.validate(this.s[check.settingsKey], this.s) }
      catch (e) { return 'Validator error: ' + e.message }
    },

    _evaluate(check) {
      const base = {
        id:            check.id,
        plugin:        check.plugin,
        title:         check.title,
        target:        check.target,
        expectedLabel: check.expectedLabel,
        detail:        '',
        status:        'unknown',
      }
      if (!this._enabled(check)) {
        base.status = 'disabled'
        base.detail = check.settingsKey
          ? 'Toggle "' + check.settingsKey + '" is off in Settings.'
          : 'Disabled.'
        return base
      }
      const vErr = this._validate(check)
      if (vErr) {
        base.status = 'invalid'
        base.detail = vErr
        return base
      }

      const exp = check.expected || {}
      // urlReachable checks don't need a scanned doc — only the probe map
      if (exp.type === 'urlReachable') {
        const r = this.reachable[exp.path]
        if (!r) { base.status = 'unknown'; base.detail = 'Run scan to verify.'; return base }
        const ctOk = exp.contentType ? exp.contentType.test(r.contentType || '') : true
        if (r.ok && ctOk) { base.status = 'ok';     base.detail = 'HTTP ' + r.status + ' (' + (r.contentType || 'no content-type') + ')' }
        else              { base.status = 'broken'; base.detail = 'HTTP ' + r.status + ' (' + (r.contentType || 'no content-type') + ')' }
        return base
      }

      if (!this.scannedDocs.length) { base.status = 'unknown'; base.detail = 'Run scan to verify.'; return base }

      // Try across all scanned pages — match against the first that yields a hit.
      let found = false
      let detail = ''
      for (const p of this.scannedDocs) {
        const r = this._evalAgainst(p, check)
        if (r.found) { found = true; detail = r.detail + ' [on ' + this.shortUrl(p.url) + ']'; break }
        if (!detail)  detail = r.detail
      }
      if (!found && check.needsArticle && !this.articleUrl) {
        base.status = 'unknown'
        base.detail = 'Add a sample article URL above and re-scan — this artifact typically only renders on article pages.'
        return base
      }
      base.status = found ? 'ok' : 'broken'
      base.detail = detail || (found ? 'Found.' : 'Artifact not found on scanned pages.')
      return base
    },

    _evalAgainst(page, check) {
      const exp = check.expected || {}
      try {
        if (exp.type === 'selector') {
          const el = page.doc.querySelector(exp.selector)
          if (!el) return { found: false, detail: 'Selector not found in HTML.' }
          if (exp.nonEmpty) {
            const txt = (el.textContent || el.getAttribute('content') || '').trim()
            if (!txt) return { found: false, detail: 'Element present but empty.' }
            return { found: true, detail: 'Found: "' + txt.substring(0, 60) + '"' }
          }
          const v = el.getAttribute('content') || el.getAttribute('href') || el.getAttribute('src') || ''
          return { found: true, detail: v ? 'Found, value: "' + v.substring(0, 80) + '"' : 'Found.' }
        }
        if (exp.type === 'jsonld') {
          const scripts = page.doc.querySelectorAll('script[type="application/ld+json"]')
          let combined = ''
          scripts.forEach(s => { combined += (s.textContent || '') + '\n' })
          return exp.regex.test(combined)
            ? { found: true,  detail: 'Pattern matched in JSON-LD.' }
            : { found: false, detail: 'Pattern not found in any JSON-LD block.' }
        }
        if (exp.type === 'regex') {
          const html = page.doc.documentElement ? page.doc.documentElement.outerHTML : ''
          return exp.regex.test(html)
            ? { found: true,  detail: 'Pattern matched in HTML.' }
            : { found: false, detail: 'Pattern not found in HTML.' }
        }
        if (exp.type === 'header') {
          const v = page.headers ? page.headers.get(exp.name) : null
          if (v && (!exp.regex || exp.regex.test(v))) return { found: true, detail: exp.name + ': ' + v }
          return { found: false, detail: 'Header "' + exp.name + '" missing or did not match.' }
        }
      } catch (err) {
        return { found: false, detail: 'Error: ' + err.message }
      }
      return { found: false, detail: 'Unknown check type.' }
    },

    async runScan() {
      this.scanError = ''
      this.scanning  = true
      this.debugLog  = []
      this.pages     = []
      this.reachable = {}
      const urls = []
      if ((this.homeUrl || '').trim())    urls.push(this.homeUrl.trim())
      if ((this.articleUrl || '').trim()) urls.push(this.articleUrl.trim())
      if (urls.length === 0) { this.scanError = 'Please enter at least the Homepage URL.'; this.scanning = false; return }

      try {
        this._log('Requesting backend scan for ' + urls.length + ' URL(s)…')
        const form = new FormData()
        urls.forEach(u => form.append('urls[]', u))
        const data = await postWithCsrf(makeAdminUrl('health.scan'), form)

        if (!data || data.success !== true) {
          throw new Error((data && data.message) || 'Backend returned no success flag.')
        }

        const parser = new DOMParser()
        this.pages = (data.pages || []).map(p => {
          const hMap = new Map()
          if (p.headers && typeof p.headers === 'object') {
            for (const [k, v] of Object.entries(p.headers)) hMap.set(k.toLowerCase(), String(v))
          }
          const out = { ...p, headers: hMap, doc: null }
          if (p.ok && p.html) {
            try { out.doc = parser.parseFromString(p.html, 'text/html') }
            catch (e) { out.docError = e.message }
          }
          this._log('Page ' + this.shortUrl(p.url) + ' → HTTP ' + p.status + ' (' + (p.bytes || 0) + ' B, ' + hMap.size + ' headers)')
          if (p.error) this._log('  ! ' + p.error)
          return out
        })
        this.reachable = data.reachable || {}
        for (const [path, r] of Object.entries(this.reachable)) {
          this._log('Probe ' + path + ' → HTTP ' + r.status + ' ' + (r.contentType || ''))
        }
        this.lastScanAt = new Date().toLocaleString()
        this._log('Scan complete.')
      } catch (e) {
        this.scanError = e && e.message ? e.message : String(e)
        this._log('Scan failed: ' + this.scanError)
      } finally {
        this.scanning = false
      }
    },

    resetScan() {
      this.pages = []
      this.reachable = {}
      this.lastScanAt = ''
      this.scanError = ''
      this.debugLog = []
    },

    fixIt(row) {
      if (!row.target) return
      // Update the URL so the deep link is shareable / bookmarkable.
      try {
        const url = new URL(window.location.href)
        url.searchParams.set('tab', row.target.tab)
        url.searchParams.set('field', row.target.field)
        window.history.replaceState({}, '', url.toString())
      } catch (e) { /* non-fatal */ }
      window.dispatchEvent(new CustomEvent('aiboost:goto-field', { detail: row.target }))
    },

    shortUrl(u) {
      try { const p = new URL(u); return p.pathname + p.search || '/' }
      catch (e) { return u }
    },

    _log(line) {
      const ts = new Date().toISOString().substring(11, 19)
      this.debugLog.push('[' + ts + '] ' + line)
    },
  },
}
</script>

<style>
.ab-health-tab .ab-health-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .88rem;
}
.ab-health-tab .ab-health-table th,
.ab-health-tab .ab-health-table td {
  padding: 8px 12px;
  border-bottom: 1px solid var(--border-color, #dee2e6);
  vertical-align: top;
}
.ab-health-tab .ab-health-table th {
  background: var(--secondary-bg, #f8f9fa);
  text-align: left;
  font-weight: 600;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .03em;
  color: var(--secondary-color, #6c757d);
}
.ab-health-tab .ab-health-table tbody tr:hover {
  background: color-mix(in srgb, var(--ab-tab-color, #6366f1) 4%, transparent);
}
.ab-health-tab .ab-status {
  display: inline-block;
  padding: 3px 8px;
  border-radius: 4px;
  font-size: .75rem;
  font-weight: 600;
  white-space: nowrap;
}
.ab-health-tab .ab-status--ok        { background: #d1e7dd; color: #0f5132; }
.ab-health-tab .ab-status--broken    { background: #f8d7da; color: #842029; }
.ab-health-tab .ab-status--disabled  { background: #fff3cd; color: #664d03; }
.ab-health-tab .ab-status--invalid   { background: #ffe5d0; color: #983b00; }
.ab-health-tab .ab-status--unknown   { background: #e9ecef; color: #495057; }
[data-bs-theme=dark] .ab-health-tab .ab-status--ok        { background: #0f5132; color: #d1e7dd; }
[data-bs-theme=dark] .ab-health-tab .ab-status--broken    { background: #842029; color: #f8d7da; }
[data-bs-theme=dark] .ab-health-tab .ab-status--disabled  { background: #664d03; color: #fff3cd; }
[data-bs-theme=dark] .ab-health-tab .ab-status--invalid   { background: #6a2900; color: #ffe5d0; }
[data-bs-theme=dark] .ab-health-tab .ab-status--unknown   { background: #343a40; color: #adb5bd; }

.ab-health-tab .ab-check-title  { font-weight: 500; }
.ab-health-tab .ab-check-detail { font-size: .78rem; color: var(--secondary-color, #6c757d); margin-top: 2px; }
.ab-health-tab .ab-expected     { font-size: .76rem; word-break: break-all; }

.ab-health-tab .ab-stat {
  padding: 4px 10px;
  border-radius: 4px;
  font-size: .82rem;
  font-weight: 500;
}
.ab-health-tab .ab-stat--ok       { background: #d1e7dd; color: #0f5132; }
.ab-health-tab .ab-stat--broken   { background: #f8d7da; color: #842029; }
.ab-health-tab .ab-stat--disabled { background: #fff3cd; color: #664d03; }
.ab-health-tab .ab-stat--invalid  { background: #ffe5d0; color: #983b00; }
.ab-health-tab .ab-stat--unknown  { background: #e9ecef; color: #495057; }

.ab-health-tab .ab-btn--xs { padding: 3px 9px; font-size: .75rem; }
.ab-health-tab .ab-link-btn {
  background: none; border: none; padding: 0;
  color: inherit; font-weight: 600; cursor: pointer; font-size: inherit;
}
.ab-health-tab .ab-debug-pre { max-height: 280px; overflow: auto; font-size: .72rem; line-height: 1.4; }
</style>
