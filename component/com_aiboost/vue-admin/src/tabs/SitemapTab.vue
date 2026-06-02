<template>
  <div class="ab-sitemap-tab">

    <!-- ── Live Preview ─────────────────────────────────────────────── -->
    <div class="ab-card">
      <div class="ab-card-header">
        🔍 Live sitemap.xml preview
        <span class="ab-card-header-actions">
          <button type="button" class="btn btn-sm btn-primary" @click="loadPreview" :disabled="preview.loading">
            {{ preview.loading ? 'Loading…' : (preview.xml ? 'Refresh' : 'Preview sitemap.xml') }}
          </button>
        </span>
      </div>
      <div class="ab-card-body">

        <div class="ab-sitemap-urlbar">
          <input type="text" class="ab-input font-monospace" :value="sitemapUrl" readonly @focus="$event.target.select()">
          <button type="button" class="btn btn-sm btn-outline-secondary" @click="copyUrl" :title="copied ? 'Copied!' : 'Copy URL'">
            {{ copied ? '✓ Copied' : 'Copy URL' }}
          </button>
          <a class="btn btn-sm btn-outline-secondary" :href="sitemapUrl" target="_blank" rel="noopener">Open</a>
        </div>

        <div v-if="preview.error" class="ab-alert ab-alert--danger mt-3">
          {{ preview.error }}
        </div>

        <div v-if="preview.xml && !preview.error" class="ab-preview mt-3">

          <!-- Stats grid -->
          <div class="ab-stats">
            <div class="ab-stat">
              <div class="ab-stat-num">{{ preview.stats.url_count.toLocaleString() }}</div>
              <div class="ab-stat-lbl">URLs listed</div>
            </div>
            <div class="ab-stat" v-if="preview.stats.image_count > 0">
              <div class="ab-stat-num">{{ preview.stats.image_count.toLocaleString() }}</div>
              <div class="ab-stat-lbl">Image entries</div>
            </div>
            <div class="ab-stat" v-if="preview.stats.hreflang_groups > 0">
              <div class="ab-stat-num">{{ preview.stats.hreflang_groups.toLocaleString() }}</div>
              <div class="ab-stat-lbl">URLs with hreflang ({{ Object.keys(preview.stats.languages).length }} langs)</div>
            </div>
            <div class="ab-stat">
              <div class="ab-stat-num">{{ preview.stats.latest_lastmod || '—' }}</div>
              <div class="ab-stat-lbl">Latest lastmod</div>
            </div>
            <div class="ab-stat">
              <div class="ab-stat-num">{{ preview.size_kb }} KB</div>
              <div class="ab-stat-lbl">File size</div>
            </div>
          </div>

          <!-- Warnings -->
          <div v-if="preview.warnings.length" class="ab-alert ab-alert--warn mt-3">
            <strong>Validation notes:</strong>
            <ul style="margin: 6px 0 0 20px; padding: 0;">
              <li v-for="(w, i) in preview.warnings" :key="i">{{ w }}</li>
            </ul>
          </div>
          <div v-else class="ab-alert ab-alert--ok mt-3">
            ✅ Sitemap parses as valid XML. No structural issues detected.
          </div>

          <!-- Top paths / langs breakdown -->
          <div class="row g-3 mt-2" v-if="Object.keys(preview.stats.top_paths).length || Object.keys(preview.stats.languages).length">
            <div class="col-md-6" v-if="Object.keys(preview.stats.top_paths).length">
              <div class="ab-sec">URLs by top-level path</div>
              <table class="ab-mini-table">
                <tr v-for="(c, p) in preview.stats.top_paths" :key="p">
                  <td><code>{{ p }}</code></td>
                  <td class="text-end">{{ c.toLocaleString() }}</td>
                </tr>
              </table>
            </div>
            <div class="col-md-6" v-if="Object.keys(preview.stats.languages).length">
              <div class="ab-sec">hreflang languages</div>
              <table class="ab-mini-table">
                <tr v-for="(c, l) in preview.stats.languages" :key="l">
                  <td><code>{{ l }}</code></td>
                  <td class="text-end">{{ c.toLocaleString() }}</td>
                </tr>
              </table>
            </div>
          </div>

          <!-- XML body -->
          <div class="ab-sec mt-3">
            XML output
            <span class="ab-sec-meta">first {{ Math.min(xmlPreviewLines, totalXmlLines) }} of {{ totalXmlLines }} lines</span>
          </div>
          <pre class="ab-xml"><code v-html="highlightedXml"></code></pre>
          <div v-if="totalXmlLines > xmlPreviewLines" class="text-center mt-2">
            <button type="button" class="btn btn-sm btn-link" @click="xmlPreviewLines += 300">
              Show 300 more lines
            </button>
          </div>
        </div>

        <div v-if="!preview.xml && !preview.loading && !preview.error" class="ab-help mt-2">
          Click <strong>Preview sitemap.xml</strong> to fetch the current sitemap and see what
          search engines actually receive. The preview includes URL count, latest <code>lastmod</code>,
          image entries, hreflang groups, and a validation summary.
        </div>
      </div>
    </div>

    <!-- ── Google Search Console guidance ──────────────────────────── -->
    <div class="ab-card">
      <div class="ab-card-header">📡 Submitting to Google in 2026</div>
      <div class="ab-card-body">
        <div class="ab-alert ab-alert--info">
          <strong>Google retired the sitemap ping endpoint in June 2023.</strong> The old
          <code>google.com/ping?sitemap=…</code> URL no longer does anything — Google ignores it
          and Bing followed suit. <em>Calling it on every publish today is harmless but useless.</em>
        </div>

        <div class="ab-sec mt-3">What actually works today</div>
        <ol class="ab-list">
          <li>
            <strong>Submit the sitemap once in Google Search Console</strong> (Sitemaps section).
            After that, Google re-fetches it on its own schedule based on your site's update frequency
            and crawl budget — typically every few hours for active sites.
          </li>
          <li>
            <strong>Reference it from <code>robots.txt</code></strong> with a
            <code>Sitemap:</code> line. Both Google and Bing pick it up during their next robots fetch.
            AI Boost writes this automatically when "Enable robots.txt management" is on.
          </li>
          <li>
            <strong>Use IndexNow for real-time URL push</strong> (Bing, Yandex, Seznam, Naver, and DuckDuckGo).
            Google does <em>not</em> participate in IndexNow yet, but for the other engines this is the
            actual sub-minute submission mechanism. Configure it in the <em>AEO</em> tab.
          </li>
          <li>
            <strong>For Bing</strong>, also submit the sitemap once in Bing Webmaster Tools.
            Bing still honors IndexNow pushes for new URLs in addition to the periodic crawl.
          </li>
        </ol>

        <div class="ab-sec mt-3">Quick actions</div>
        <div class="ab-action-grid">
          <a class="btn btn-sm btn-outline-primary" :href="gscSubmitUrl" target="_blank" rel="noopener">
            Open Google Search Console →
          </a>
          <a class="btn btn-sm btn-outline-primary" href="https://www.bing.com/webmasters/sitemaps" target="_blank" rel="noopener">
            Open Bing Webmaster Tools →
          </a>
          <button type="button" class="btn btn-sm btn-outline-secondary" @click="copyUrl">
            {{ copied ? '✓ Copied' : 'Copy sitemap URL' }}
          </button>
        </div>
        <p class="ab-help mt-2">
          The <em>Search Engine Ping</em> toggles below are kept for backward compatibility with
          smaller engines that still accept legacy pings. They are <strong>not required</strong> for
          Google or Bing in 2026 — leave them off unless you specifically need them.
        </p>
      </div>
    </div>

    <!-- XML Sitemap -->
    <div class="ab-card">
      <div class="ab-card-header">🗺 XML Sitemap</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-2">
          <input v-model="s.enable_sitemap" data-ab-field="enable_sitemap" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sm-enable">
          <label class="ab-check__label" for="sm-enable">Enable XML Sitemap</label>
        </div>

        <div class="ab-sec mt-3">Content to Include</div>
        <div class="row g-2 mb-3">
          <div class="col-md-3">
            <div class="ab-check ab-toggle">
              <input v-model="s.include_articles" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sm-articles">
              <label class="ab-check__label" for="sm-articles">Articles</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="ab-check ab-toggle">
              <input v-model="s.include_categories" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sm-cats">
              <label class="ab-check__label" for="sm-cats">Categories</label>
            </div>
          </div>
          <div class="col-md-3">
            <div class="ab-check ab-toggle">
              <input v-model="s.include_menu_items" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sm-menus">
              <label class="ab-check__label" for="sm-menus">Menu Items</label>
            </div>
          </div>
          <div class="col-md-3">
            <ProGate gate-key="include_tags">
              <div class="ab-check ab-toggle">
                <input v-model="s.include_tags" data-ab-field="include_tags" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="sm-tags">
                <label class="ab-check__label" for="sm-tags">Tags</label>
              </div>
            </ProGate>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="ab-label">URL Limit</label>
            <input v-model="s.sitemap_limit" type="number" class="ab-input" min="0" max="50000" step="100" placeholder="1000">
            <div class="ab-help">0 = unlimited.</div>
          </div>
          <div class="col-md-4">
            <label class="ab-label">Default changefreq</label>
            <select v-model="s.default_changefreq" class="ab-select">
              <option value="always">Always</option>
              <option value="hourly">Hourly</option>
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
              <option value="yearly">Yearly</option>
              <option value="never">Never</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="ab-label">Default Priority</label>
            <div class="d-flex align-items-center gap-2">
              <input v-model="s.default_priority" type="range" class="form-range" min="0" max="1" step="0.1" style="flex:1">
              <span class="text-muted" style="min-width:28px;font-size:.85rem">{{ (parseFloat(s.default_priority || 0.8)).toFixed(1) }}</span>
            </div>
          </div>
        </div>

        <ProGate gate-key="section:sitemap.priority_pertype" mode="section">
          <div class="ab-sec">Per-Type Priority</div>
          <div class="row g-3 mb-3">
            <div class="col-md-3" v-for="(label, key) in priorityFields" :key="key">
              <label class="ab-label">{{ label }}</label>
              <div class="d-flex align-items-center gap-2">
                <input v-model="s[key]" type="range" class="form-range" min="0" max="1" step="0.1" style="flex:1">
                <span class="text-muted" style="min-width:28px;font-size:.85rem">{{ (parseFloat(s[key] || 0)).toFixed(1) }}</span>
              </div>
            </div>
          </div>
        </ProGate>

        <div class="ab-sec">Exclusions</div>
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="ab-label">Exclude Article Category IDs <span style="opacity:.5;font-weight:400;">(comma-separated)</span></label>
            <input v-model="s.exclude_category_ids" type="text" class="ab-input" placeholder="5, 12, 43">
          </div>
          <div class="col-md-6">
            <label class="ab-label">Exclude Menu Item IDs <span style="opacity:.5;font-weight:400;">(comma-separated)</span></label>
            <input v-model="s.exclude_menu_ids" type="text" class="ab-input" placeholder="3, 7">
          </div>
        </div>

        <ProGate gate-key="section:sitemap.advanced" mode="section">
          <div class="ab-sec">Advanced</div>
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <div class="ab-check ab-toggle">
                <input v-model="s.enable_sitemap_index" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="sm-index">
                <label class="ab-check__label" for="sm-index">Sitemap Index (multiple sitemaps)</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="ab-check ab-toggle">
                <input v-model="s.enable_image_sitemap" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="sm-images">
                <label class="ab-check__label" for="sm-images">Include Image Sitemap data</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="ab-check ab-toggle">
                <input v-model="s.enable_hreflang" data-ab-field="enable_hreflang" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="sm-hreflang">
                <label class="ab-check__label" for="sm-hreflang">Add hreflang to sitemap</label>
              </div>
            </div>
          </div>
        </ProGate>
      </div>
    </div>

    <!-- News Sitemap -->
    <ProGate gate-key="section:sitemap.news" mode="section">
    <div class="ab-card">
      <div class="ab-card-header">📰 Google News Sitemap</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_news_sitemap" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sm-news">
          <label class="ab-check__label" for="sm-news">Enable Google News Sitemap</label>
        </div>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="ab-label">News Category ID</label>
            <input v-model="s.news_category_id" type="number" class="ab-input" placeholder="0" min="0">
            <div class="ab-help">Joomla category containing news articles.</div>
          </div>
          <div class="col-md-6">
            <label class="ab-label">Publication Name</label>
            <input v-model="s.news_publication_name" type="text" class="ab-input"
              placeholder="Leave empty to use Joomla site name">
            <TranslationExpander field-key="news_publication_name" />
          </div>
        </div>
      </div>
    </div>
    </ProGate>

    <!-- Ping (legacy, demoted) -->
    <div class="ab-card">
      <div class="ab-card-header">📡 Search Engine Ping <span class="ab-badge-legacy ms-1">Legacy</span></div>
      <div class="ab-card-body">
        <p class="ab-help" style="margin-top:0;">
          Google removed sitemap ping support in June 2023 and Bing followed. These toggles are
          retained for niche engines and legacy deployments only — leave them off unless you have a
          specific reason. See the <em>Submitting to Google in 2026</em> card above for the
          recommended workflow.
        </p>
        <div class="row g-2 mb-3">
          <div class="col-md-4">
            <div class="ab-check ab-toggle">
              <input v-model="s.ping_google" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sm-ping-google">
              <label class="ab-check__label" for="sm-ping-google">Ping Google on sitemap request</label>
            </div>
          </div>
          <div class="col-md-4">
            <div class="ab-check ab-toggle">
              <input v-model="s.ping_bing" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sm-ping-bing">
              <label class="ab-check__label" for="sm-ping-bing">Ping Bing on sitemap request</label>
            </div>
          </div>
          <div class="col-md-4">
            <ProGate gate-key="ping_on_publish">
              <div class="ab-check ab-toggle">
                <input v-model="s.ping_on_publish" true-value="1" false-value="0"
                  type="checkbox" class="ab-toggle__input" id="sm-ping-publish">
                <label class="ab-check__label" for="sm-ping-publish">Ping on article publish</label>
              </div>
            </ProGate>
          </div>
        </div>
      </div>
    </div>

    <!-- 404 Monitoring -->
    <div class="ab-card">
      <div class="ab-card-header">🚧 404 Monitoring</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-2">
          <input v-model="s.redirect_404_log_enabled" data-ab-field="redirect_404_log_enabled"
            true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sm-404-log">
          <label class="ab-check__label" for="sm-404-log">Log 404 Errors</label>
        </div>
        <div class="ab-help">
          When enabled, AI Boost records every 404 (Page Not Found) hit on the front-end into
          <code>#__aiboost_404_log</code>. View the list under <strong>Redirects → Recent 404 errors</strong>
          and use it to create permanent redirects so AI engines and search bots don't hit dead URLs.
        </div>
      </div>
    </div>

    <!-- Canonical URL -->
    <div class="ab-card">
      <div class="ab-card-header">🔗 Canonical URL</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.enable_canonical" data-ab-field="enable_canonical" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="sm-canonical">
          <label class="ab-check__label" for="sm-canonical">Enable canonical URL management</label>
        </div>
        <label class="ab-label">Canonical URL Map <span style="opacity:.5;font-weight:400;">(one override per line)</span></label>
        <textarea v-model="s.canonical_url_map" class="ab-input font-monospace" rows="5"
          placeholder="/old-path → /new-path&#10;/shop → /products"></textarea>
        <div class="ab-help mt-1">Format: <code>/source-path → /canonical-path</code> or full URLs.</div>
      </div>
    </div>

  </div>
</template>

<script>
import TranslationExpander from '../components/TranslationExpander.vue'
import { makeAdminUrl } from '../api.js'

export default {
  name: 'SitemapTab',
  components: { TranslationExpander },
  props: { s: { type: Object, required: true } },

  data() {
    return {
      priorityFields: {
        priority_homepage:   'Homepage',
        priority_articles:   'Articles',
        priority_categories: 'Categories',
        priority_tags:       'Tags',
      },
      preview: {
        loading: false,
        error:   '',
        xml:     '',
        size_kb: 0,
        stats:   { url_count: 0, image_count: 0, hreflang_groups: 0, hreflang_links: 0,
                   latest_lastmod: null, oldest_lastmod: null, is_index: false,
                   sitemap_count: 0, languages: {}, changefreq_dist: {}, top_paths: {} },
        warnings: [],
      },
      xmlPreviewLines: 200,
      copied: false,
      _copiedTimer: null,
    }
  },

  computed: {
    sitemapUrl() {
      // Joomla admin lives under /administrator — strip that to get the site root.
      const origin = window.location.origin
      const path   = window.location.pathname.replace(/\/administrator(\/.*)?$/, '/')
      return origin + path.replace(/\/+$/, '') + '/sitemap.xml'
    },
    gscSubmitUrl() {
      try {
        const u    = new URL(this.sitemapUrl)
        const host = u.hostname.replace(/^www\./, '')
        return `https://search.google.com/search-console/sitemaps?resource_id=${encodeURIComponent('sc-domain:' + host)}`
      } catch (e) {
        return 'https://search.google.com/search-console/sitemaps'
      }
    },
    totalXmlLines() {
      return this.preview.xml ? this.preview.xml.split('\n').length : 0
    },
    highlightedXml() {
      if (!this.preview.xml) return ''
      const lines = this.preview.xml.split('\n').slice(0, this.xmlPreviewLines).join('\n')
      const esc = lines
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
      // Light syntax tint: tag names + attribute names
      return esc
        .replace(/(&lt;\/?)([a-zA-Z][\w:-]*)/g, '$1<span class="ab-xml-tag">$2</span>')
        .replace(/\s([a-zA-Z][\w:-]*)=&quot;/g, ' <span class="ab-xml-attr">$1</span>=&quot;')
    },
  },

  methods: {
    async loadPreview() {
      this.preview.loading = true
      this.preview.error   = ''
      try {
        const token = window.aiBoostToken
        const url   = makeAdminUrl('settings.previewSitemap') + (token ? '&' + token + '=1' : '')
        const res   = await fetch(url, { credentials: 'same-origin' })
        const data  = await res.json()
        if (!data.success) {
          this.preview.error = data.message || 'Preview failed.'
          this.preview.xml   = ''
          return
        }
        this.preview.xml      = data.xml || ''
        this.preview.size_kb  = data.size_kb || 0
        this.preview.stats    = Object.assign(this.preview.stats, data.stats || {})
        this.preview.warnings = data.warnings || []
        this.xmlPreviewLines  = 200
      } catch (e) {
        this.preview.error = 'Network error: ' + (e && e.message ? e.message : e)
      } finally {
        this.preview.loading = false
      }
    },
    async copyUrl() {
      try {
        await navigator.clipboard.writeText(this.sitemapUrl)
      } catch (e) {
        // Fallback for non-https or older browsers
        const ta = document.createElement('textarea')
        ta.value = this.sitemapUrl
        document.body.appendChild(ta)
        ta.select()
        try { document.execCommand('copy') } catch (_) { /* ignore */ }
        document.body.removeChild(ta)
      }
      this.copied = true
      clearTimeout(this._copiedTimer)
      this._copiedTimer = setTimeout(() => { this.copied = false }, 1800)
    },
  },
}
</script>

<style scoped>
.ab-sitemap-tab { max-width: 860px; }

.ab-card-header-actions { margin-left: auto; }
.ab-card-header { display: flex; align-items: center; }

.ab-sitemap-urlbar { display: flex; gap: 6px; align-items: center; }
.ab-sitemap-urlbar input { flex: 1; font-size: 12.5px; }

.ab-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
}
.ab-stat {
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 6px;
  padding: 10px 12px;
}
.ab-stat-num { font-size: 18px; font-weight: 600; color: #212529; }
.ab-stat-lbl { font-size: 11.5px; color: #6c757d; margin-top: 2px; }

/* Task #473 — dark-mode polish for sitemap surfaces. */
[data-bs-theme=dark] .ab-stat {
  background: #1a1d21; border-color: #2d3338;
}
[data-bs-theme=dark] .ab-stat-num { color: #e6edf3; }
[data-bs-theme=dark] .ab-stat-lbl { color: #8b949e; }
[data-bs-theme=dark] .ab-mini-table td { border-bottom-color: #2d3338; }
[data-bs-theme=dark] .ab-mini-table td code,
[data-bs-theme=dark] .ab-list code { background: #21262d; color: #c9d1d9; }
[data-bs-theme=dark] .ab-badge-legacy { background: #2d3338; color: #c9d1d9; }
[data-bs-theme=dark] .ab-alert--info  { background: #0d1f3a; border-color: #1f3a6b; color: #79b8ff; }
[data-bs-theme=dark] .ab-alert--warn  { background: #2a2000; border-color: #4a3800; color: #f1c44a; }
[data-bs-theme=dark] .ab-alert--ok    { background: #0d2818; border-color: #1f4a32; color: #56d364; }
[data-bs-theme=dark] .ab-alert--danger{ background: #2a0d12; border-color: #4a1f28; color: #f85149; }

/* v0.54.1 — Quick actions buttons (Bootstrap btn-outline-*) need explicit
   dark-mode rules because Joomla's data-bs-theme=dark variant doesn't
   always reach btn-outline classes inside our action grid. */
[data-bs-theme=dark] .ab-action-grid .btn-outline-primary {
  color: #58a6ff; border-color: #388bfd;
}
[data-bs-theme=dark] .ab-action-grid .btn-outline-primary:hover {
  color: #fff; background-color: #1f6feb; border-color: #1f6feb;
}
[data-bs-theme=dark] .ab-action-grid .btn-outline-secondary {
  color: #c9d1d9; border-color: #484f58;
}
[data-bs-theme=dark] .ab-action-grid .btn-outline-secondary:hover {
  color: #fff; background-color: #484f58; border-color: #6e7681;
}

.ab-alert--info {
  background: #e7f1ff; border: 1px solid #b6d4fe; color: #084298;
  padding: 10px 14px; border-radius: 6px; font-size: 13px; line-height: 1.5;
}
.ab-alert--warn {
  background: #fff3cd; border: 1px solid #ffe69c; color: #664d03;
  padding: 10px 14px; border-radius: 6px; font-size: 13px;
}
.ab-alert--ok {
  background: #d1e7dd; border: 1px solid #a3cfbb; color: #0a3622;
  padding: 8px 14px; border-radius: 6px; font-size: 13px;
}
.ab-alert--danger {
  background: #f8d7da; border: 1px solid #f1aeb5; color: #58151c;
  padding: 10px 14px; border-radius: 6px; font-size: 13px;
}

.ab-xml {
  background: #0d1117;
  color: #c9d1d9;
  padding: 12px 14px;
  border-radius: 6px;
  font-size: 11.5px;
  line-height: 1.5;
  max-height: 460px;
  overflow: auto;
  margin: 0;
}
.ab-xml code { color: inherit; background: transparent; padding: 0; }
:deep(.ab-xml-tag)  { color: #79c0ff; }
:deep(.ab-xml-attr) { color: #ffa657; }

.ab-mini-table { width: 100%; font-size: 12.5px; }
.ab-mini-table td { padding: 3px 6px; border-bottom: 1px solid #f1f3f5; }
.ab-mini-table td code { background: #f1f3f5; padding: 1px 5px; border-radius: 3px; font-size: 11.5px; }

.ab-sec-meta { font-weight: 400; color: #6c757d; font-size: 11.5px; margin-left: 8px; }

.ab-list { margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.6; }
.ab-list li { margin-bottom: 6px; }
.ab-list code { background: #f1f3f5; padding: 1px 5px; border-radius: 3px; font-size: 12px; }

.ab-action-grid { display: flex; flex-wrap: wrap; gap: 8px; }

.ab-badge-legacy {
  background: #e9ecef; color: #495057;
  padding: 2px 8px; border-radius: 10px;
  font-size: 10.5px; font-weight: 600; text-transform: uppercase;
}
</style>
