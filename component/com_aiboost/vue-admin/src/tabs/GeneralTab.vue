<template>
  <div class="ab-general-tab">

    <!-- Task #473 — License section removed from GeneralTab; license
         management now lives entirely on the dedicated Licenses page
         (Pro-only TopNav item). -->

    <!-- Domain -->
    <div class="ab-card">
      <div class="ab-card-header">🌐 Domain &amp; Environment</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.auto_domain_detection" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="g-auto-domain">
          <label class="ab-check__label" for="g-auto-domain">Auto-detect domain <span style="color:#6c757d;">(recommended)</span></label>
        </div>
        <div class="mb-0">
          <label class="ab-label">Manual Domain <span style="opacity:.5;font-weight:400;">(if auto-detect is off)</span></label>
          <input v-model="s.manual_domain" type="url" class="ab-input"
            placeholder="https://example.com" style="max-width:340px">
        </div>
      </div>
    </div>

    <!-- Conflict Resolution Mode -->
    <div class="ab-card">
      <div class="ab-card-header">🛡️ Conflict Resolution Mode</div>
      <div class="ab-card-body">
        <p class="ab-help mb-2">
          Controls how AI Boost behaves when another SEO/Analytics extension (Joomla Core OG, 4SEO, Sh404SEF,
          EFSEO, Google Analytics extension, etc.) is already producing the same meta tag, JSON-LD block,
          or analytics snippet. Detected duplicates appear in <em>Health → Conflicts</em>.
        </p>
        <div class="mb-2">
          <label class="ab-label">Mode</label>
          <select v-model="s.conflict_mode" data-ab-field="conflict_mode" class="ab-select" style="max-width:340px">
            <option value="cooperative">Cooperative — skip our tag when one already exists (recommended)</option>
            <option value="aggressive">Aggressive — always emit our tag (may produce duplicates)</option>
            <option value="off">Off — disable conflict handling entirely</option>
          </select>
        </div>
        <ul class="ab-help" style="margin:0 0 0 1.1rem;padding:0;line-height:1.6">
          <li><strong>Cooperative</strong> — aiboost_social skips OG when another extension set og:title; aiboost_schema skips Organization JSON-LD; aiboost_analytics skips GA4/GTM/Meta Pixel when their loaders are already present; aiboost_aeo skips AI meta tags when present. No duplicates.</li>
          <li><strong>Aggressive</strong> — we always inject our tags. Use this only if you trust AI Boost as the single source of truth and have disabled the other extension's tag output.</li>
          <li><strong>Off</strong> — same behaviour as Aggressive, plus we never check for conflicts. For debugging only.</li>
        </ul>
      </div>
    </div>

    <!-- robots.txt -->
    <div class="ab-card">
      <div class="ab-card-header">🤖 robots.txt</div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-2">
          <input v-model="s.enable_robots" data-ab-field="enable_robots" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="g-robots">
          <label class="ab-check__label" for="g-robots">Enable robots.txt management</label>
        </div>
        <div class="ab-check ab-toggle mb-3">
          <input v-model="s.robots_auto_sync" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="g-robots-sync">
          <label class="ab-check__label" for="g-robots-sync">Auto-sync physical robots.txt file</label>
        </div>

        <!-- Live preview -->
        <div class="ab-subcard">
          <div class="ab-subcard-head">
            <strong>🔍 Live robots.txt preview</strong>
            <span class="ab-card-header-actions">
              <button type="button" class="btn btn-sm btn-primary"
                @click="loadRobots" :disabled="robots.loading">
                {{ robots.loading ? 'Loading…' : (robots.body ? 'Refresh' : 'Preview robots.txt') }}
              </button>
            </span>
          </div>

          <div class="ab-robots-urlbar">
            <input type="text" class="ab-input font-monospace" :value="robotsUrl" readonly @focus="$event.target.select()">
            <a class="btn btn-sm btn-outline-secondary" :href="robotsUrl" target="_blank" rel="noopener">Open</a>
          </div>

          <div v-if="robots.error" class="ab-alert ab-alert--danger mt-3">{{ robots.error }}</div>

          <div v-if="robots.body && !robots.error" class="mt-3">

            <!-- Summary chips -->
            <div class="ab-chip-row">
              <span class="ab-chip">{{ robots.summary.line_count }} lines</span>
              <span class="ab-chip">{{ robots.size_bytes }} bytes</span>
              <span class="ab-chip">
                {{ robots.summary.user_agents.length }} User-agent block{{ robots.summary.user_agents.length === 1 ? '' : 's' }}
              </span>
              <span class="ab-chip">
                {{ robots.summary.sitemaps.length }} Sitemap line{{ robots.summary.sitemaps.length === 1 ? '' : 's' }}
              </span>
              <span class="ab-chip" :class="{ 'ab-chip--info': robots.source === 'disk' }">
                source: {{ robots.source === 'disk' ? 'on-disk file' : 'live HTTP' }}
              </span>
            </div>

            <!-- Issues + Fix It -->
            <div v-if="robots.issues.length" class="mt-3">
              <div v-for="iss in robots.issues" :key="iss.id"
                class="ab-alert" :class="'ab-alert--' + (iss.level === 'danger' ? 'danger' : iss.level === 'warn' ? 'warn' : 'info')">
                <div class="d-flex align-items-start gap-2">
                  <div style="flex:1">
                    <strong>{{ iss.title }}</strong>
                    <div style="margin-top:2px;">{{ iss.detail }}</div>
                  </div>
                  <button v-if="iss.fix" type="button" class="btn btn-sm btn-outline-dark"
                    @click="applyFix(iss.fix)">
                    {{ fixApplied[iss.fix] ? '✓ Applied' : 'Fix it' }}
                  </button>
                </div>
              </div>
              <p class="ab-help mt-2">
                Fixes update the settings on this page. Click <strong>Save All Settings</strong> at the
                top, then re-run <em>Preview robots.txt</em> to confirm.
              </p>
            </div>
            <div v-else class="ab-alert ab-alert--ok mt-3">✅ No structural issues detected.</div>

            <!-- Line-by-line viewer -->
            <div class="ab-sec mt-3">Line-by-line annotation</div>
            <div class="ab-robots-viewer">
              <div v-for="ln in robots.lines" :key="ln.n"
                class="ab-robots-row" :class="'ab-row--' + ln.kind + ' ab-lvl--' + ln.level">
                <span class="ab-row-num">{{ ln.n }}</span>
                <code class="ab-row-text">{{ ln.text || ' ' }}</code>
                <span class="ab-row-note" v-if="ln.note">{{ ln.note }}</span>
              </div>
            </div>
          </div>

          <div v-if="!robots.body && !robots.loading && !robots.error" class="ab-help mt-2">
            Click <strong>Preview robots.txt</strong> to fetch the live file and see a line-by-line
            explanation plus automated fixes for the most common problems.
          </div>
        </div>

        <!-- Educational text -->
        <div class="ab-alert ab-alert--info mt-3">
          <strong>What robots.txt does and does <em>not</em> do</strong>
          <ul style="margin: 6px 0 0 20px; padding: 0; font-size:12.5px; line-height:1.55;">
            <li><strong>Does:</strong> tell well-behaved crawlers which URLs to skip and where to find your sitemap.</li>
            <li><strong>Does not:</strong> prevent a page from appearing in search results — Google can still index a Disallowed URL if it is linked from elsewhere. Use a <code>noindex</code> meta tag or HTTP header for true exclusion.</li>
            <li><strong>Does not:</strong> protect private content — robots.txt is publicly readable and ignored by malicious bots. Use authentication for anything sensitive.</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- AI Crawler Rules card moved to the AEO tab in Task #463 — one card, one payload. -->

  </div>
</template>

<script>
import { makeAdminUrl } from '../api.js'

// Task #463 — the AI Crawler Rules UI lives in AeoTab.vue now (one card, one
// payload). Legacy `crawler_disabled_bots` JSON is migrated into
// `crawler_bot_rules` on package install/upgrade via pkg_script.php.

export default {
  name: 'GeneralTab',
  props: { s: { type: Object, required: true } },

  data() {
    return {
      robots: {
        loading: false,
        error:   '',
        body:    '',
        source:  '',
        size_bytes: 0,
        lines:   [],
        summary: { line_count: 0, user_agents: [], sitemaps: [], rule_count: 0, block_all_agents: [], has_crawl_delay: false },
        issues:  [],
      },
      fixApplied: {},
    }
  },

  computed: {
    robotsUrl() {
      const origin = window.location.origin
      const path   = window.location.pathname.replace(/\/administrator(\/.*)?$/, '/')
      return origin + path.replace(/\/+$/, '') + '/robots.txt'
    },
    sitemapUrl() {
      const origin = window.location.origin
      const path   = window.location.pathname.replace(/\/administrator(\/.*)?$/, '/')
      return origin + path.replace(/\/+$/, '') + '/sitemap.xml'
    },
    // Task #456 — Show user-facing label "professional" for any Pro-tier
    // enum (`pro`, `developer`, `agency`) while the underlying stored
    // value stays unchanged (Lemon Squeezy + migration both depend on it).
    licenseTierLabel() {
      const raw = String(this.s.license_tier || 'free').toLowerCase()
      if (['pro', 'developer', 'agency'].includes(raw)) return 'professional'
      return raw || 'free'
    },
  },

  methods: {
    async loadRobots() {
      this.robots.loading = true
      this.robots.error   = ''
      this.fixApplied     = {}
      try {
        const token = window.aiBoostToken
        const url   = makeAdminUrl('settings.previewRobots') + (token ? '&' + token + '=1' : '')
        const res   = await fetch(url, { credentials: 'same-origin' })
        const data  = await res.json()
        if (!data.success) {
          this.robots.error = data.message || 'Preview failed.'
          this.robots.body  = ''
          return
        }
        this.robots.body       = data.body || ''
        this.robots.source     = data.source || 'http'
        this.robots.size_bytes = data.size_bytes || 0
        this.robots.lines      = data.lines || []
        this.robots.summary    = Object.assign(this.robots.summary, data.summary || {})
        this.robots.issues     = data.issues || []
      } catch (e) {
        this.robots.error = 'Network error: ' + (e && e.message ? e.message : e)
      } finally {
        this.robots.loading = false
      }
    },

    applyFix(fix) {
      // All fixes mutate this.s.* — admin then clicks Save All Settings.
      switch (fix) {
        case 'add-sitemap': {
          const line    = 'Sitemap: ' + this.sitemapUrl
          const current = (this.s.crawler_rules || '').trim()
          if (!current.split(/\r?\n/).some(l => l.trim().toLowerCase() === line.toLowerCase())) {
            this.s.crawler_rules = (current ? current + '\n' : '') + line + '\n'
          }
          this.s.enable_robots = '1'
          break
        }
        case 'add-user-agent-star': {
          const block   = 'User-agent: *\nDisallow:'
          const current = (this.s.crawler_rules || '').trim()
          if (!/user-agent\s*:\s*\*/i.test(current)) {
            this.s.crawler_rules = block + (current ? '\n\n' + current : '') + '\n'
          }
          this.s.enable_robots = '1'
          break
        }
        case 'remove-block-all': {
          // Strip "Disallow: /" lines that follow "User-agent: *" globally.
          const lines = (this.s.crawler_rules || '').split(/\r?\n/)
          const out   = []
          let inStar  = false
          for (const ln of lines) {
            const tr = ln.trim()
            if (/^user-agent\s*:\s*\*/i.test(tr)) { inStar = true; out.push(ln); continue }
            if (/^user-agent\s*:/i.test(tr))      { inStar = false; out.push(ln); continue }
            if (inStar && /^disallow\s*:\s*\/\s*$/i.test(tr)) {
              out.push('Disallow:') // empty value = allow all
              continue
            }
            out.push(ln)
          }
          this.s.crawler_rules = out.join('\n')
          this.s.enable_robots = '1'
          break
        }
      }
      this.fixApplied = Object.assign({}, this.fixApplied, { [fix]: true })
    },
  },
}
</script>

<style scoped>
.ab-general-tab { max-width: 860px; }

/* Task #473 — dark-mode aware sub-card background. */
[data-bs-theme=dark] .ab-subcard {
  background: #1a1d21 !important;
  border-color: #2d3338 !important;
}
.ab-subcard {
  border: 1px solid #e9ecef; border-radius: 8px;
  padding: 12px 14px; background: #fbfcfd; margin-top: 6px;
}
.ab-subcard-head { display: flex; align-items: center; margin-bottom: 8px; }
.ab-card-header-actions { margin-left: auto; }

.ab-robots-urlbar { display: flex; gap: 6px; align-items: center; }
.ab-robots-urlbar input { flex: 1; font-size: 12.5px; }

.ab-chip-row { display: flex; flex-wrap: wrap; gap: 6px; }
.ab-chip {
  background: #f1f3f5; color: #495057;
  font-size: 11.5px; padding: 3px 9px; border-radius: 10px;
  font-weight: 500;
}
.ab-chip--info { background: #cfe2ff; color: #084298; }

.ab-alert {
  padding: 9px 13px; border-radius: 6px; font-size: 12.5px;
  line-height: 1.5; margin-top: 8px;
}
.ab-alert--info   { background: #e7f1ff; border: 1px solid #b6d4fe; color: #084298; }
.ab-alert--warn   { background: #fff3cd; border: 1px solid #ffe69c; color: #664d03; }
.ab-alert--ok     { background: #d1e7dd; border: 1px solid #a3cfbb; color: #0a3622; }
.ab-alert--danger { background: #f8d7da; border: 1px solid #f1aeb5; color: #58151c; }
.ab-alert + .ab-alert { margin-top: 6px; }
.ab-alert code { background: rgba(0,0,0,.06); padding: 1px 5px; border-radius: 3px; font-size: 11.5px; }

.ab-robots-viewer {
  background: #0d1117; color: #c9d1d9;
  border-radius: 6px; padding: 10px 0;
  font-size: 12px; line-height: 1.55;
  max-height: 440px; overflow: auto;
}
.ab-robots-row {
  display: grid;
  grid-template-columns: 38px minmax(160px, 260px) 1fr;
  gap: 10px;
  padding: 2px 12px;
  border-left: 3px solid transparent;
}
.ab-robots-row:hover { background: rgba(255,255,255,.04); }
.ab-row-num  { color: #6e7681; text-align: right; user-select: none; font-size: 11px; }
.ab-row-text { color: #c9d1d9; white-space: pre-wrap; word-break: break-all; }
.ab-row-note { color: #8b949e; font-size: 11.5px; font-style: italic; }

.ab-row--user-agent .ab-row-text { color: #79c0ff; font-weight: 600; }
.ab-row--sitemap    .ab-row-text { color: #d2a8ff; font-weight: 600; }
.ab-row--allow      .ab-row-text { color: #7ee787; }
.ab-row--disallow   .ab-row-text { color: #ffa657; }
.ab-row--comment    .ab-row-text { color: #6e7681; font-style: italic; }
.ab-row--invalid    { border-left-color: #f85149; }
.ab-row--invalid    .ab-row-text { color: #ff7b72; }

.ab-lvl--warn   { border-left-color: #d29922; }
.ab-lvl--danger { border-left-color: #f85149; background: rgba(248,81,73,.06); }
</style>
