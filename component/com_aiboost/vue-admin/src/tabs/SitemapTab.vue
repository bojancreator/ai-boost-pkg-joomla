<template>
  <div class="ab-settings-tab">

    <!-- 01 XML Sitemap -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">01</span>
        XML Sitemap
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div><div class="ab-label">Enable XML Sitemap</div></div>
          <span class="ab-toggle" :class="{'is-on': s.enable_sitemap === '1'}">
            <input v-model="s.enable_sitemap" data-ab-field="enable_sitemap" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="sm-enable">
            <span class="ab-toggle__track"></span>
          </span>
        </label>

        <div class="ab-eyebrow">Content to Include</div>
        <div class="ab-toggle-cluster">
          <label class="ab-toggle-pair">
            <span class="ab-label">Articles</span>
            <span class="ab-toggle" :class="{'is-on': s.include_articles === '1'}">
              <input v-model="s.include_articles" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="sm-articles">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-pair">
            <span class="ab-label">Categories</span>
            <span class="ab-toggle" :class="{'is-on': s.include_categories === '1'}">
              <input v-model="s.include_categories" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="sm-cats">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-pair">
            <span class="ab-label">Menu Items</span>
            <span class="ab-toggle" :class="{'is-on': s.include_menu_items === '1'}">
              <input v-model="s.include_menu_items" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="sm-menus">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-pair">
            <span class="ab-label">Tags</span>
            <span class="ab-toggle" :class="{'is-on': s.include_tags === '1'}">
              <input v-model="s.include_tags" data-ab-field="include_tags" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="sm-tags">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <div class="ab-field">
              <label class="ab-label">URL Limit</label>
              <input v-model="s.sitemap_limit" type="number" class="ab-input" min="0" max="50000" step="100" placeholder="1000">
              <div class="ab-help">0 = unlimited.</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="ab-field">
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
          </div>
          <div class="col-md-4">
            <div class="ab-field">
              <label class="ab-label">Default Priority</label>
              <div class="d-flex align-items-center gap-2">
                <input v-model="s.default_priority" type="range" class="ab-range" min="0" max="1" step="0.1">
                <span class="ab-muted ab-range-val">{{ (parseFloat(s.default_priority || 0.8)).toFixed(1) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="ab-eyebrow">Per-Type Priority</div>
        <div class="row g-3">
          <div class="col-md-3" v-for="(label, key) in priorityFields" :key="key">
            <div class="ab-field">
              <label class="ab-label">{{ label }}</label>
              <div class="d-flex align-items-center gap-2">
                <input v-model="s[key]" type="range" class="ab-range" min="0" max="1" step="0.1">
                <span class="ab-muted ab-range-val">{{ (parseFloat(s[key] || 0)).toFixed(1) }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="ab-eyebrow">Exclusions</div>
        <div class="row g-3">
          <div class="col-md-6">
            <div class="ab-field">
              <label class="ab-label">Exclude Article Category IDs <span class="ab-muted">(comma-separated)</span></label>
              <input v-model="s.exclude_category_ids" type="text" class="ab-input" placeholder="5, 12, 43">
            </div>
          </div>
          <div class="col-md-6">
            <div class="ab-field">
              <label class="ab-label">Exclude Menu Item IDs <span class="ab-muted">(comma-separated)</span></label>
              <input v-model="s.exclude_menu_ids" type="text" class="ab-input" placeholder="3, 7">
            </div>
          </div>
        </div>

        <ProGate mode="card" label="Advanced sitemap">
          <div class="ab-eyebrow">
            Advanced <span class="ab-tag ab-tag--pro" style="margin-left:.3rem">Pro</span>
          </div>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="ab-toggle-row">
                <div><div class="ab-label">Sitemap Index (multiple sitemaps)</div></div>
                <span class="ab-toggle" :class="{'is-on': s.enable_sitemap_index === '1'}">
                  <input v-model="s.enable_sitemap_index" true-value="1" false-value="0"
                    type="checkbox" class="ab-toggle__input" id="sm-index">
                  <span class="ab-toggle__track"></span>
                </span>
              </label>
            </div>
            <div class="col-md-4">
              <label class="ab-toggle-row">
                <div><div class="ab-label">Include Image Sitemap data</div></div>
                <span class="ab-toggle" :class="{'is-on': s.enable_image_sitemap === '1'}">
                  <input v-model="s.enable_image_sitemap" true-value="1" false-value="0"
                    type="checkbox" class="ab-toggle__input" id="sm-images">
                  <span class="ab-toggle__track"></span>
                </span>
              </label>
            </div>
            <div class="col-md-4">
              <label class="ab-toggle-row">
                <div><div class="ab-label">Add hreflang to sitemap</div></div>
                <span class="ab-toggle" :class="{'is-on': s.enable_hreflang === '1'}">
                  <input v-model="s.enable_hreflang" data-ab-field="enable_hreflang" true-value="1" false-value="0"
                    type="checkbox" class="ab-toggle__input" id="sm-hreflang">
                  <span class="ab-toggle__track"></span>
                </span>
              </label>
            </div>
          </div>
        </ProGate>
      </div>
    </div>

    <!-- 02 Google News Sitemap (Pro) -->
    <ProGate mode="card" label="News Sitemap">
      <div class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">02</span>
          Google News Sitemap
          <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
        </div>
        <div class="ab-section__body">
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable Google News Sitemap</div></div>
            <span class="ab-toggle" :class="{'is-on': s.enable_news_sitemap === '1'}">
              <input v-model="s.enable_news_sitemap" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input" id="sm-news">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <div class="row g-3">
            <div class="col-md-3">
              <div class="ab-field">
                <label class="ab-label">News Category ID</label>
                <input v-model="s.news_category_id" type="number" class="ab-input" placeholder="0" min="0">
                <div class="ab-help">Joomla category containing news articles.</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="ab-field">
                <label class="ab-label">Publication Name</label>
                <input v-model="s.news_publication_name" type="text" class="ab-input"
                  placeholder="Leave empty to use Joomla site name">
                <TranslationExpander field-key="news_publication_name" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </ProGate>

    <!-- 03 Search Engine Ping (Legacy) -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">03</span>
        Search Engine Ping
        <span class="ab-tag ab-tag--neutral" style="margin-left:.4rem" title="Retired by Google (June 2023) and Bing — kept only for niche engines and legacy deployments">Legacy</span>
      </div>
      <div class="ab-section__body">
        <div class="ab-help">
          Google removed sitemap ping support in June 2023 and Bing followed. These toggles are
          retained for niche engines and legacy deployments only — leave them off unless you have a
          specific reason.
        </div>
        <div class="ab-toggle-cluster">
          <label class="ab-toggle-pair">
            <span class="ab-label">Ping Google on sitemap request</span>
            <span class="ab-toggle" :class="{'is-on': s.ping_google === '1'}">
              <input v-model="s.ping_google" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="sm-ping-google">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-pair">
            <span class="ab-label">Ping Bing on sitemap request</span>
            <span class="ab-toggle" :class="{'is-on': s.ping_bing === '1'}">
              <input v-model="s.ping_bing" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="sm-ping-bing">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <label class="ab-toggle-pair">
            <span class="ab-label">Ping on article publish</span>
            <span class="ab-toggle" :class="{'is-on': s.ping_on_publish === '1'}">
              <input v-model="s.ping_on_publish" true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="sm-ping-publish">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
        </div>
      </div>
    </div>

    <!-- 04 Live Preview -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">04</span>
        Live sitemap.xml Preview
        <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" style="margin-left:auto"
          @click="loadPreview" :disabled="preview.loading">
          {{ preview.loading ? 'Loading…' : (preview.xml ? 'Refresh' : 'Preview sitemap.xml') }}
        </button>
      </div>
      <div class="ab-section__body">

        <div class="ab-sitemap-urlbar">
          <input type="text" class="ab-input font-monospace" :value="sitemapUrl" readonly @focus="$event.target.select()">
          <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="copyUrl" :title="copied ? 'Copied!' : 'Copy URL'">
            {{ copied ? '✓ Copied' : 'Copy URL' }}
          </button>
          <a class="ab-btn ab-btn--sm ab-btn--ghost" :href="sitemapUrl" target="_blank" rel="noopener">Open</a>
        </div>

        <div v-if="preview.error" class="ab-alert ab-alert--danger">
          {{ preview.error }}
        </div>

        <div v-if="preview.xml && !preview.error" class="ab-preview">
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

          <div v-if="preview.warnings.length" class="ab-alert ab-alert--warn">
            <strong>Validation notes:</strong>
            <ul style="margin: 6px 0 0 20px; padding: 0;">
              <li v-for="(w, i) in preview.warnings" :key="i">{{ w }}</li>
            </ul>
          </div>
          <div v-else class="ab-alert ab-alert--ok">
            Sitemap parses as valid XML. No structural issues detected.
          </div>

          <div class="row g-3" v-if="Object.keys(preview.stats.top_paths).length || Object.keys(preview.stats.languages).length">
            <div class="col-md-6" v-if="Object.keys(preview.stats.top_paths).length">
              <div class="ab-eyebrow">URLs by top-level path</div>
              <table class="ab-mini-table">
                <tr v-for="(c, p) in preview.stats.top_paths" :key="p">
                  <td><code>{{ p }}</code></td>
                  <td class="text-end">{{ c.toLocaleString() }}</td>
                </tr>
              </table>
            </div>
            <div class="col-md-6" v-if="Object.keys(preview.stats.languages).length">
              <div class="ab-eyebrow">hreflang languages</div>
              <table class="ab-mini-table">
                <tr v-for="(c, l) in preview.stats.languages" :key="l">
                  <td><code>{{ l }}</code></td>
                  <td class="text-end">{{ c.toLocaleString() }}</td>
                </tr>
              </table>
            </div>
          </div>

          <div class="ab-eyebrow">
            XML output
            <span class="ab-sec-meta">first {{ Math.min(xmlPreviewLines, totalXmlLines) }} of {{ totalXmlLines }} lines</span>
          </div>
          <pre class="ab-xml"><code v-html="highlightedXml"></code></pre>
          <div v-if="totalXmlLines > xmlPreviewLines" class="text-center mt-2">
            <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="xmlPreviewLines += 300">
              Show 300 more lines
            </button>
          </div>
        </div>

        <div v-if="!preview.xml && !preview.loading && !preview.error" class="ab-help">
          Click <strong>Preview sitemap.xml</strong> to fetch the current sitemap and see what
          search engines actually receive. The preview includes URL count, latest <code>lastmod</code>,
          image entries, hreflang groups, and a validation summary.
        </div>
      </div>
    </div>

    <!-- 05 Submitting to Search Engines -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">05</span>
        Submitting to Search Engines in 2026
      </div>
      <div class="ab-section__body">
        <div class="ab-alert ab-alert--info ab-alert--compact">
          <p class="ab-alert__lead"><strong>Google retired the sitemap ping endpoint in June 2023.</strong></p>
          <p>The old <code>google.com/ping?sitemap=…</code> URL no longer does anything — Google ignores it and Bing followed suit.</p>
        </div>

        <div class="ab-eyebrow">What actually works today</div>
        <ol class="ab-list">
          <li>
            <strong>Submit the sitemap once in Google Search Console</strong> (Sitemaps section).
            After that, Google re-fetches it on its own schedule.
          </li>
          <li>
            <strong>Reference it from <code>robots.txt</code></strong> with a
            <code>Sitemap:</code> line. AI Boost writes this automatically when robots.txt management is on.
          </li>
          <li>
            <strong>Use IndexNow for real-time URL push</strong> (Bing, Yandex, Seznam, Naver, and DuckDuckGo).
            Configure it in the <em>AI Visibility / GEO</em> tab.
          </li>
          <li>
            <strong>For Bing</strong>, also submit the sitemap once in Bing Webmaster Tools.
          </li>
        </ol>

        <div class="ab-eyebrow">Quick actions</div>
        <div class="ab-action-grid">
          <a class="ab-btn ab-btn--ghost ab-btn--sm" :href="gscSubmitUrl" target="_blank" rel="noopener">
            Open Google Search Console →
          </a>
          <a class="ab-btn ab-btn--ghost ab-btn--sm" href="https://www.bing.com/webmasters/sitemaps" target="_blank" rel="noopener">
            Open Bing Webmaster Tools →
          </a>
          <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm" @click="copyUrl">
            {{ copied ? '✓ Copied' : 'Copy sitemap URL' }}
          </button>
        </div>
        <div class="ab-help">
          The <em>Search Engine Ping</em> toggles above are kept for backward compatibility with
          smaller engines that still accept legacy pings. They are <strong>not required</strong> for
          Google or Bing in 2026 — leave them off unless you specifically need them.
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import TranslationExpander from '../components/TranslationExpander.vue'
import ProGate from '../components/ProGate.vue'
import { makeAdminUrl, getCsrfTokenName } from '../api.js'

export default {
  name: 'SitemapTab',
  components: { TranslationExpander, ProGate },
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
        const token = getCsrfTokenName()
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
.ab-sitemap-urlbar { display: flex; gap: 6px; align-items: center; }
.ab-sitemap-urlbar input { flex: 1; font-size: 12.5px; }

.ab-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
  margin: var(--ab-space-3) 0;
}
.ab-stat {
  background: var(--ab-surface-raised);
  border: 1px solid var(--ab-border);
  border-radius: var(--ab-radius);
  padding: 10px 12px;
}
.ab-stat-num { font-size: 18px; font-weight: 600; color: var(--ab-text); }
.ab-stat-lbl { font-size: 11.5px; color: var(--ab-text-muted); margin-top: 2px; }

.ab-xml {
  background: #0d1117;
  color: #c9d1d9;
  padding: 12px 14px;
  border-radius: var(--ab-radius);
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
.ab-mini-table td { padding: 3px 6px; border-bottom: 1px solid var(--ab-border); }
.ab-mini-table td code { background: var(--ab-surface-raised); padding: 1px 5px; border-radius: 3px; font-size: 11.5px; }

.ab-sec-meta { font-weight: 400; color: var(--ab-text-muted); font-size: 11.5px; margin-left: 8px; }
.ab-preview { display: flex; flex-direction: column; gap: var(--ab-space-3); margin-top: var(--ab-space-3); }

.ab-list { margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.6; }
.ab-list li { margin-bottom: 6px; }
.ab-list code { background: var(--ab-surface-raised); padding: 1px 5px; border-radius: 3px; font-size: 12px; }

.ab-action-grid { display: flex; flex-wrap: wrap; gap: 8px; }

.ab-toggle-cluster { display: flex; flex-wrap: wrap; column-gap: 2rem; row-gap: 1rem; align-items: center; }
.ab-toggle-pair { display: inline-flex; align-items: center; gap: 1rem; margin: 0; cursor: pointer; }
.ab-toggle-pair .ab-label { margin: 0; }
.ab-range { flex: 1; accent-color: var(--ab-primary); height: 4px; }
.ab-range-val { min-width: 28px; font-size: var(--ab-font-size-xs); font-family: var(--ab-font-mono); color: var(--ab-text-muted); }
.ab-alert--compact { font-size: var(--ab-font-size-xs); }
.ab-alert--compact p { margin: 0; }
.ab-alert--compact p + p { margin-top: 6px; }
.ab-alert__lead { font-weight: 600; }
</style>
