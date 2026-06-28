<template>
  <div class="ab-urlchecker">
    <!-- ── Scan controls ───────────────────────────────────────── -->
    <div class="ab-card">
      <div class="ab-card-header">🔍 URL Checker</div>
      <div class="ab-card-body">
        <p class="text-muted small mb-2">
          Scans every URL in your sitemap (or your custom list) for HTTP status,
          redirect chains, canonical issues, noindex tags and thin content. The
          scan runs in the background — you can switch tabs or refresh the page
          and the scan keeps going.
        </p>

        <div class="ab-field-row">
          <label class="ab-label">URL source</label>
          <div>
            <label class="form-check-label me-3">
              <input type="radio" v-model="source" value="sitemap" /> Sitemap
            </label>
            <label class="form-check-label">
              <input type="radio" v-model="source" value="custom" /> Custom list
            </label>
          </div>
        </div>

        <div v-if="source === 'custom'" class="ab-field-row">
          <label class="ab-label" for="ab-url-list">URLs (one per line)</label>
          <textarea
            id="ab-url-list"
            v-model="customUrls"
            class="form-control"
            rows="5"
            placeholder="https://example.com/page-1&#10;https://example.com/page-2"
          ></textarea>
        </div>

        <div class="ab-field-row">
          <label class="ab-label">&nbsp;</label>
          <div class="d-flex gap-2 flex-wrap align-items-center">
            <button
              type="button"
              class="ab-btn ab-btn--primary"
              :disabled="busy || isRunning"
              @click="startScan"
            >
              <span v-if="busy">Preparing…</span>
              <span v-else-if="isRunning">Scan in progress…</span>
              <span v-else>▶ Run scan</span>
            </button>
            <button
              v-if="isRunning"
              type="button"
              class="ab-btn ab-btn--secondary"
              @click="cancelScan"
            >Cancel</button>
            <span v-if="errorMsg" class="text-danger small">{{ errorMsg }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Progress ────────────────────────────────────────────── -->
    <div v-if="job" class="ab-card">
      <div class="ab-card-header">
        Scan #{{ job.id }} — {{ statusLabel }}
      </div>
      <div class="ab-card-body">
        <div class="ab-progress">
          <div class="ab-progress__bar" :style="{ width: progressPct + '%' }"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted mt-1">
          <span>{{ job.done_urls }} / {{ job.total_urls }} URLs</span>
          <span>{{ progressPct }}%</span>
        </div>
        <div v-if="isRunning && job.current_url" class="small text-muted mt-1">
          Currently checking: <code>{{ job.current_url }}</code>
        </div>
        <div class="small text-muted mt-1">
          Started: {{ job.started_at }}
          <span v-if="job.finished_at"> · Finished: {{ job.finished_at }}</span>
        </div>
      </div>
    </div>

    <!-- ── Results table ───────────────────────────────────────── -->
    <div v-if="job && job.results && job.results.length" class="ab-card">
      <div class="ab-card-header">
        Results — {{ summary.total }} URLs
        <span class="ms-2 ab-pill ab-pill--ok">{{ summary.ok }} OK</span>
        <span class="ms-1 ab-pill ab-pill--warn">{{ summary.warn }} Warnings</span>
        <span class="ms-1 ab-pill ab-pill--err">{{ summary.err }} Errors</span>
      </div>
      <div class="ab-card-body" style="overflow-x:auto">
        <table class="ab-results">
          <thead>
            <tr>
              <th style="min-width:240px">URL</th>
              <th>Status</th>
              <th>Canonical</th>
              <th>Index</th>
              <th>Content</th>
              <th>Issues / Fix</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, idx) in job.results" :key="idx" :class="rowClass(r)">
              <td>
                <a :href="r.url" target="_blank" rel="noopener">{{ shortUrl(r.url) }}</a>
                <div v-if="r.redirect_chain && r.redirect_chain.length > 1" class="small text-muted">
                  Chain: {{ r.redirect_chain.length }} hop(s) → {{ shortUrl(r.redirect_chain[r.redirect_chain.length - 1].url) }}
                </div>
              </td>
              <td>
                <span :class="statusBadgeClass(r)">{{ r.status || '—' }}</span>
              </td>
              <td>
                <span :class="canonicalBadge(r)">{{ canonicalLabel(r) }}</span>
              </td>
              <td>
                <span v-if="r.is_noindex" class="ab-pill ab-pill--warn">noindex</span>
                <span v-else class="text-muted small">—</span>
              </td>
              <td>
                <span v-if="r.is_thin_content" class="ab-pill ab-pill--warn">
                  {{ r.content_chars }} chars
                </span>
                <span v-else-if="r.content_chars" class="small text-muted">
                  {{ r.content_chars }} chars
                </span>
                <span v-else class="text-muted small">—</span>
              </td>
              <td>
                <div v-for="(issue, i) in issuesFor(r)" :key="i" class="mb-1">
                  <span class="small">{{ issue.label }}</span>
                  <button
                    v-if="issue.fixable"
                    type="button"
                    class="ab-btn ab-btn--xs ms-2"
                    :disabled="!!fixingMap[r.url]"
                    @click="applyFix(r, issue)"
                  >
                    {{ fixingMap[r.url] === issue.type ? 'Fixing…' : '🔧 Fix It' }}
                  </button>
                  <a
                    v-if="issue.fix_url"
                    :href="issue.fix_url"
                    class="ab-btn ab-btn--xs ms-2"
                    title="Open Settings to enable canonical URLs"
                  >⚙️ Fix It →</a>
                </div>
                <span v-if="!issuesFor(r).length" class="text-success small">No issues</span>
                <div v-if="fixMsg[r.url]" class="small mt-1" :class="fixMsg[r.url].cls">
                  {{ fixMsg[r.url].text }}
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── History ─────────────────────────────────────────────── -->
    <div class="ab-card">
      <div class="ab-card-header">
        Previous scans
        <button type="button" class="ab-btn ab-btn--xs ms-auto" @click="loadHistory">Refresh</button>
      </div>
      <div class="ab-card-body">
        <table v-if="history.length" class="ab-results">
          <thead>
            <tr>
              <th>#</th><th>Status</th><th>URLs</th>
              <th>Started</th><th>Finished</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in history" :key="row.id">
              <td>{{ row.id }}</td>
              <td>{{ row.status }}</td>
              <td>{{ row.done_urls }} / {{ row.total_urls }}</td>
              <td class="small text-muted">{{ row.started_at }}</td>
              <td class="small text-muted">{{ row.finished_at || '—' }}</td>
              <td>
                <button type="button" class="ab-btn ab-btn--xs" @click="loadScan(row.id)">View</button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-else class="text-muted small mb-0">No previous scans yet.</p>
      </div>
    </div>
  </div>
</template>

<script>
import { postWithCsrf, makeAdminUrl } from '../api.js'

/** Append the Joomla CSRF token to a GET URL so checkToken('request') passes. */
function withTok(url) {
  const boot = window.aiBoostBootstrap || {}
  const t = boot.tokenName || window.aiBoostToken || ''
  return t ? url + '&' + encodeURIComponent(t) + '=1' : url
}

export default {
  name: 'UrlCheckerTab',
  props: { s: { type: Object, required: true } },

  data() {
    return {
      source: 'sitemap',
      customUrls: '',
      busy: false,
      errorMsg: '',
      job: null,
      history: [],
      pollTimer: null,
      fixingMap: {},
      fixMsg: {},
    }
  },

  computed: {
    isRunning() { return this.job && this.job.status === 'running' },
    statusLabel() {
      if (!this.job) return ''
      const m = { running: 'Running', finished: 'Finished', cancelled: 'Cancelled', failed: 'Failed' }
      return m[this.job.status] || this.job.status
    },
    progressPct() {
      if (!this.job || !this.job.total_urls) return 0
      return Math.min(100, Math.round((this.job.done_urls / this.job.total_urls) * 100))
    },
    summary() {
      const out = { total: 0, ok: 0, warn: 0, err: 0 }
      if (!this.job || !this.job.results) return out
      for (const r of this.job.results) {
        out.total++
        const issues = this.issuesFor(r)
        const sev = issues.reduce((s, i) => Math.max(s, i.severity), 0)
        if (sev === 0) out.ok++
        else if (sev === 1) out.warn++
        else out.err++
      }
      return out
    },
  },

  mounted() {
    // Resume any in-progress scan automatically
    this.loadLatest()
    this.loadHistory()
  },
  beforeUnmount() {
    this.stopPolling()
  },

  methods: {
    shortUrl(u) {
      if (!u) return ''
      return u.length > 60 ? u.slice(0, 57) + '…' : u
    },
    rowClass(r) {
      const sev = this.issuesFor(r).reduce((s, i) => Math.max(s, i.severity), 0)
      if (sev >= 2) return 'ab-row-err'
      if (sev === 1) return 'ab-row-warn'
      return ''
    },
    statusBadgeClass(r) {
      const s = r.status || 0
      if (s >= 200 && s < 300) return 'ab-pill ab-pill--ok'
      if (s >= 300 && s < 400) return 'ab-pill ab-pill--warn'
      return 'ab-pill ab-pill--err'
    },
    canonicalBadge(r) {
      const map = { ok: 'ab-pill ab-pill--ok', missing: 'ab-pill ab-pill--err',
        mismatch: 'ab-pill ab-pill--warn', skipped: 'text-muted small' }
      return map[r.canonical_status] || 'text-muted small'
    },
    canonicalLabel(r) {
      const m = { ok: '✓', missing: 'missing', mismatch: 'mismatch', skipped: '—' }
      return m[r.canonical_status] || '—'
    },

    /** Build issue list for a result row. Each issue has type/severity/fixable/fix_url. */
    issuesFor(r) {
      const out = []
      const canonicalSettingsUrl = 'index.php?option=com_aiboost&view=app#/settings?tab=titles&field=enable_canonical'
      if (r.error)                           out.push({ type: 'error',              severity: 2, label: r.error, fixable: false })
      if (r.status === 404)                  out.push({ type: 'not_found',          severity: 2, label: '404 Not Found', fixable: true })
      else if (r.status >= 500)              out.push({ type: 'server_error',       severity: 2, label: 'Server error ' + r.status, fixable: false })
      if (r.redirect_chain && r.redirect_chain.length > 2)
                                             out.push({ type: 'redirect_chain',     severity: 1, label: 'Redirect chain (' + r.redirect_chain.length + ' hops)', fixable: true })
      if (r.canonical_status === 'missing' && r.status >= 200 && r.status < 400)
                                             out.push({ type: 'canonical_missing',  severity: 1, label: 'Missing canonical', fixable: false, fix_url: canonicalSettingsUrl })
      if (r.canonical_status === 'mismatch') out.push({ type: 'canonical_mismatch', severity: 1, label: 'Canonical mismatch', fixable: false, fix_url: canonicalSettingsUrl })
      if (r.is_noindex)                      out.push({ type: 'noindex',            severity: 1, label: 'noindex tag', fixable: false })
      if (r.is_thin_content)                 out.push({ type: 'thin_content',       severity: 1, label: 'Thin content', fixable: false })
      return out
    },

    async startScan() {
      this.errorMsg = ''
      this.busy = true
      try {
        let urls = []
        if (this.source === 'sitemap') {
          const sm = await postWithCsrf(makeAdminUrl('urlchecker.getSitemapUrls'))
          if (!sm || !sm.success) throw new Error((sm && sm.message) || 'Sitemap fetch failed')
          urls = sm.urls || []
        } else {
          urls = this.customUrls.split(/\r?\n/).map(s => s.trim()).filter(Boolean)
        }
        if (!urls.length) throw new Error('No URLs to scan.')
        const res = await postWithCsrf(makeAdminUrl('urlchecker.startScan'), { urls: JSON.stringify(urls) })
        if (!res || !res.success) throw new Error((res && res.message) || 'startScan failed')
        await this.loadScan(res.job_id)
        this.startPolling()
      } catch (e) {
        this.errorMsg = e.message || String(e)
      } finally {
        this.busy = false
      }
    },

    async cancelScan() {
      if (!this.job) return
      try {
        await postWithCsrf(makeAdminUrl('urlchecker.cancelScan'), { id: this.job.id })
      } catch (e) { this.errorMsg = e.message }
    },

    async loadLatest() {
      try {
        const res = await fetch(withTok(makeAdminUrl('urlchecker.scanStatus')), { credentials: 'same-origin' })
        const data = await res.json()
        if (data.success && data.job) {
          this.job = data.job
          if (this.job.status === 'running') this.startPolling()
        }
      } catch (e) {}
    },

    async loadScan(id) {
      try {
        const res = await fetch(withTok(makeAdminUrl('urlchecker.scanStatus') + '&id=' + encodeURIComponent(id)), { credentials: 'same-origin' })
        const data = await res.json()
        if (data.success) this.job = data.job
      } catch (e) {}
    },

    async loadHistory() {
      try {
        const res = await fetch(withTok(makeAdminUrl('urlchecker.scanHistory')), { credentials: 'same-origin' })
        const data = await res.json()
        if (data.success) this.history = data.history || []
      } catch (e) {}
    },

    startPolling() {
      this.stopPolling()
      this.pollTimer = setInterval(async () => {
        if (!this.job) return this.stopPolling()
        await this.loadScan(this.job.id)
        if (this.job && this.job.status !== 'running') {
          this.stopPolling()
          this.loadHistory()
        }
      }, 2000)
    },
    stopPolling() {
      if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null }
    },

    async applyFix(r, issue) {
      this.fixingMap = { ...this.fixingMap, [r.url]: issue.type }
      this.fixMsg    = { ...this.fixMsg, [r.url]: null }
      try {
        const payload = { url: r.url, issue: issue.type }
        if (issue.type === 'redirect_chain' && r.redirect_chain && r.redirect_chain.length) {
          payload.expected = r.redirect_chain[r.redirect_chain.length - 1].url
        }
        const res = await postWithCsrf(makeAdminUrl('urlchecker.fixIssue'), payload)
        if (res && res.success) {
          // Replace this row with the re-scan result
          const idx = this.job.results.findIndex(x => x.url === r.url)
          if (idx >= 0 && res.result) {
            this.job.results.splice(idx, 1, res.result)
          }
          this.fixMsg = { ...this.fixMsg, [r.url]: { text: '✓ ' + (res.message || 'Fixed'), cls: 'text-success' } }
        } else {
          this.fixMsg = { ...this.fixMsg, [r.url]: { text: (res && res.message) || 'Fix failed', cls: 'text-danger' } }
        }
      } catch (e) {
        this.fixMsg = { ...this.fixMsg, [r.url]: { text: 'Error: ' + e.message, cls: 'text-danger' } }
      } finally {
        const m = { ...this.fixingMap }; delete m[r.url]; this.fixingMap = m
      }
    },
  },
}
</script>

<style scoped>
.ab-urlchecker .ab-btn--xs {
  font-size: .75rem; padding: 2px 8px; line-height: 1.4;
  border: 1px solid var(--border-color, #ced4da);
  background: var(--secondary-bg, #f8f9fa);
  border-radius: 3px; cursor: pointer; color: var(--body-color, #212529);
}
.ab-urlchecker .ab-btn--xs:hover:not(:disabled) {
  background: color-mix(in srgb, #ef4444 10%, var(--secondary-bg, #f8f9fa));
}
.ab-urlchecker .ab-btn--xs:disabled { opacity: .6; cursor: not-allowed; }

.ab-progress {
  height: 10px; width: 100%;
  background: var(--secondary-bg, #f1f3f5);
  border-radius: 5px; overflow: hidden;
}
.ab-progress__bar {
  height: 100%; background: #ef4444;
  transition: width .25s ease;
}

.ab-results {
  width: 100%; border-collapse: collapse; font-size: .85rem;
}
.ab-results th, .ab-results td {
  padding: 6px 8px; border-bottom: 1px solid var(--border-color, #dee2e6);
  vertical-align: top; text-align: left;
}
.ab-results th { font-weight: 600; background: var(--secondary-bg, #f8f9fa); }
.ab-results .ab-row-warn { background: color-mix(in srgb, #f59e0b 6%, transparent); }
.ab-results .ab-row-err  { background: color-mix(in srgb, #ef4444 8%, transparent); }

.ab-pill {
  display: inline-block; padding: 1px 8px; border-radius: 10px;
  font-size: .72rem; font-weight: 600; line-height: 1.4;
}
.ab-pill--ok   { background: #16a34a; color: #fff; }
.ab-pill--warn { background: #d97706; color: #fff; }
.ab-pill--err  { background: #dc2626; color: #fff; }

[data-bs-theme="dark"] .ab-pill--ok   { background: #15803d; color: #fff; }
[data-bs-theme="dark"] .ab-pill--warn { background: #b45309; color: #fff; }
[data-bs-theme="dark"] .ab-pill--err  { background: #b91c1c; color: #fff; }
</style>
