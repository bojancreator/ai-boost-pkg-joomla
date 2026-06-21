<template>
  <div class="ab-page-errors">
    <div class="ab-page-intro">
      <h2 class="ab-page-title">Error Log</h2>
      <p class="ab-page-desc">Events written by AI Boost components into <code>#__aiboost_error_log</code>.
        Retention: 1000 rows or 30 days, whichever comes first.
        Severity floor and on/off switch live in
        <a href="#" @click.prevent="goToErrorLogging">Settings → Debug → Error logging</a>.
      </p>
    </div>

    <!-- Toolbar -->
    <div class="ab-section mb-3">
      <div class="ab-section__body">
        <div class="d-flex flex-wrap align-items-end" style="gap:.75rem;">
          <div>
            <label class="ab-label small text-muted d-block mb-1">Severity</label>
            <div class="ab-cluster">
              <label v-for="s in allSeverities" :key="s" class="ab-chip">
                <input type="checkbox" :value="s" v-model="filters.severity"
                       @change="onFilterChange" />
                <span :class="['ab-chip-dot', 'ab-chip-dot--' + s]"></span>
                {{ s }}
              </label>
            </div>
          </div>

          <div style="min-width:160px;">
            <label class="ab-label small text-muted d-block mb-1">Origin</label>
            <select v-model="filters.origin" class="ab-select" @change="onFilterChange">
              <option value="all">All</option>
              <option value="backend">Backend only</option>
              <option value="frontend">Frontend only</option>
            </select>
          </div>

          <div style="min-width:200px;">
            <label class="ab-label small text-muted d-block mb-1">Source</label>
            <select v-model="filters.source" class="ab-select" @change="onFilterChange">
              <option value="">All sources</option>
              <option v-for="src in filteredSources" :key="src" :value="src">{{ src }}</option>
            </select>
          </div>

          <div class="flex-grow-1" style="min-width:200px;">
            <label class="ab-label small text-muted d-block mb-1">Search message / context</label>
            <input v-model="filters.q" type="text" class="ab-input"
                   placeholder="substring search…"
                   @input="onSearchDebounced" />
          </div>

          <div class="d-flex align-items-end" style="gap:.5rem;">
            <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm"
                    :disabled="loading" @click="refresh">
              <span class="icon-refresh me-1" aria-hidden="true"></span>
              Refresh
            </button>

            <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm"
                    :disabled="!rows.length" @click="copyVisible">
              <span class="icon-copy me-1" aria-hidden="true"></span>
              Copy
            </button>

            <button type="button" class="ab-btn ab-btn--danger ab-btn--sm"
                    data-ab-field="errors_clear_all"
                    :disabled="busyClear || !total" @click="clearAll">
              <span class="icon-trash me-1" aria-hidden="true"></span>
              {{ busyClear ? 'Clearing…' : 'Clear All' }}
            </button>

            <label class="ab-chip" :title="'Refresh every ' + AUTO_REFRESH_SEC + ' seconds'">
              <input type="checkbox" class="ab-toggle__input" v-model="autoRefresh" @change="onAutoRefreshChange" />
              Auto-refresh ({{ AUTO_REFRESH_SEC }}s)
            </label>
          </div>
        </div>

        <div v-if="actionMsg" class="small mt-2"
             :class="actionMsgType === 'error' ? 'text-danger'
                    : actionMsgType === 'success' ? 'text-success' : 'text-muted'">
          {{ actionMsg }}
        </div>
      </div>
    </div>

    <!-- Summary strip -->
    <div class="d-flex flex-wrap mb-3" style="gap:.5rem;">
      <span class="ab-badge">Total in log: <strong>{{ total }}</strong></span>
      <span :class="['ab-badge', summary.errors_24h > 0 ? 'ab-badge--danger' : 'ab-badge--success']">
        Errors (24h): {{ summary.errors_24h }}
      </span>
      <span :class="['ab-badge', summary.warnings_24h > 0 ? 'ab-badge--warning' : 'ab-badge--success']">
        Warnings (24h): {{ summary.warnings_24h }}
      </span>
      <span v-if="summary.last_at" class="ab-badge">
        Last event: {{ formatDate(summary.last_at) }}
      </span>
    </div>

    <!-- Rows -->
    <div class="ab-section">
      <div class="ab-section__body" style="padding:0;">
        <div v-if="loading && !rows.length" class="text-muted small p-3">Loading…</div>
        <div v-else-if="loadError" class="ab-alert ab-alert--danger m-3">{{ loadError }}</div>
        <div v-else-if="!rows.length" class="text-muted small p-3">
          No events match your filters. With logging on and a severity floor of
          <code>{{ minSeverity }}</code>, AI Boost components write here whenever
          they hit a recoverable issue.
        </div>

        <table v-else class="table table-sm align-middle ab-errors-table mb-0">
          <thead>
            <tr>
              <th style="width:30px;"></th>
              <th style="width:180px;">When (UTC)</th>
              <th style="width:90px;">Severity</th>
              <th style="width:180px;">Source</th>
              <th>Message</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in rows" :key="row.id">
              <tr :class="['ab-err-row', 'ab-err-row--' + row.severity]"
                  @click="toggleExpand(row.id)" style="cursor:pointer;">
                <td class="text-center">
                  <span :class="expanded[row.id] ? 'icon-chevron-down' : 'icon-chevron-right'"
                        aria-hidden="true"></span>
                </td>
                <td><code class="small">{{ row.created_at }}</code></td>
                <td>
                  <span :class="['ab-badge', severityBadgeClass(row.severity)]">
                    {{ row.severity }}
                  </span>
                </td>
                <td>
                  <span :class="['ab-source-tag', isFrontendSource(row.source) ? 'ab-source-tag--fe' : 'ab-source-tag--be']">
                    {{ isFrontendSource(row.source) ? 'FE' : 'BE' }}
                  </span>
                  <code class="small text-muted">{{ row.source || '—' }}</code>
                </td>
                <td class="ab-err-msg">
                  <div class="ab-err-msg-row">
                    <span class="ab-err-msg-text">{{ row.message }}</span>
                    <button v-if="isFrontendSource(row.source)"
                            type="button" class="ab-btn ab-btn--ghost ab-btn--sm ab-err-bug-btn"
                            data-ab-field="errors_copy_bug_report"
                            title="Copy a Markdown bug report ready to paste into GitHub or email"
                            @click.stop="copyBugReport(row)">
                      <span class="icon-copy me-1" aria-hidden="true"></span>Copy bug report
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="expanded[row.id]" class="ab-err-detail-row">
                <td></td>
                <td colspan="4">
                  <div class="ab-err-detail">
                    <div class="row g-2 small mb-2">
                      <div class="col-md-6"><strong>Request ID:</strong>
                        <code>{{ row.request_id || '—' }}</code>
                      </div>
                      <div class="col-md-6"><strong>Row ID:</strong> #{{ row.id }}</div>
                    </div>
                    <div v-if="row.context" class="mb-2">
                      <strong class="small">Context</strong>
                      <pre class="ab-err-context">{{ formatContext(row.context) }}</pre>
                    </div>
                    <div v-else class="small text-muted">No context attached.</div>
                    <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm"
                            @click.stop="copyRow(row)">
                      <span class="icon-copy me-1" aria-hidden="true"></span>Copy row
                    </button>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>

        <div v-if="rows.length" class="d-flex justify-content-between align-items-center px-3 py-2 small text-muted border-top">
          <div>
            Showing <strong>{{ rangeStart }}–{{ rangeEnd }}</strong> of <strong>{{ total }}</strong>
          </div>
          <div class="d-flex" style="gap:.5rem;">
            <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm"
                    :disabled="offset === 0 || loading" @click="prevPage">
              ‹ Prev
            </button>
            <button type="button" class="ab-btn ab-btn--ghost ab-btn--sm"
                    :disabled="!hasMore || loading" @click="nextPage">
              Next ›
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { postWithCsrf, makeAdminUrl } from './api.js'

const AUTO_REFRESH_SEC = 30
const ALL_SEVERITIES = ['debug', 'info', 'warning', 'error']

export default {
  name: 'ErrorsPage',

  setup () {
    const boot = window.aiBoostBootstrap || {}
    const router = useRouter()

    // Seed from bootstrap (server pre-computes errorsSummary in HtmlView)
    // so the first paint already reflects current counts without waiting
    // for the AJAX round-trip. fetchSummary() refreshes on mount/refresh.
    const bootSummary = (boot.errorsSummary && typeof boot.errorsSummary === 'object') ? boot.errorsSummary : {}

    const rows        = ref([])
    const total       = ref(Number(bootSummary.total) || 0)
    const sources     = ref([])
    const summary     = reactive({
      total:        Number(bootSummary.total)        || 0,
      errors_24h:   Number(bootSummary.errors_24h)   || 0,
      warnings_24h: Number(bootSummary.warnings_24h) || 0,
      last_at:      bootSummary.last_at || null,
    })
    const loading     = ref(false)
    const loadError   = ref('')
    const actionMsg   = ref('')
    const actionMsgType = ref('')
    const busyClear   = ref(false)
    // Auto-refresh defaults ON while the Errors tab is open (every
    // AUTO_REFRESH_SEC seconds). Toggle persists for the current session.
    const autoRefresh = ref(true)
    const expanded    = reactive({})
    const limit       = ref(50)
    const offset      = ref(0)

    // Seed severity from settings floor if available — start with warning+error
    // so the page is useful by default and not flooded with debug noise.
    const filters = reactive({
      severity: ['warning', 'error'],
      origin:   'all',   // all | backend | frontend  (Task #513)
      source:   '',
      q:        '',
    })

    // Source dropdown is narrowed by the Origin filter so admins can pick
    // a frontend-only or backend-only source without scrolling past the
    // unrelated half.
    const filteredSources = computed(() => {
      const all = sources.value || []
      if (filters.origin === 'frontend') return all.filter(isFrontendSource)
      if (filters.origin === 'backend')  return all.filter(s => !isFrontendSource(s))
      return all
    })

    function isFrontendSource (src) {
      return typeof src === 'string' && src.indexOf('frontend:') === 0
    }

    const minSeverity = computed(() => {
      const s = window.aiBoostSettings || {}
      return (s.error_log_min_severity || 'warning')
    })

    // Navigate to Settings → Debug → Error logging WITHOUT leaving the SPA.
    // The old implementation used a legacy `view=settings#tab-debug-btn`
    // href which (a) full-reloaded out of the SPA so the TopNav vanished and
    // (b) used a hash format App.vue's deep-link handler doesn't understand,
    // landing on the default General tab. We now stay in the router, seed the
    // ?tab=&field= query params App.vue reads on mount, and also fire the
    // aiboost:goto-field event as a fallback for an already-mounted instance.
    function goToErrorLogging () {
      const target = { tab: 'debug', field: 'error_log_enabled' }
      try {
        const url = new URL(window.location.href)
        url.searchParams.set('tab', target.tab)
        url.searchParams.set('field', target.field)
        window.history.replaceState({}, '', url.toString())
      } catch (e) { /* non-fatal */ }
      router.push('/settings')
      setTimeout(() => {
        window.dispatchEvent(new CustomEvent('aiboost:goto-field', { detail: target }))
      }, 250)
    }

    const rangeStart = computed(() => total.value === 0 ? 0 : offset.value + 1)
    const rangeEnd   = computed(() => Math.min(offset.value + rows.value.length, total.value))
    const hasMore    = computed(() => offset.value + rows.value.length < total.value)

    let actionTimer = null
    function setMsg (msg, type = 'info') {
      clearTimeout(actionTimer)
      actionMsg.value     = msg
      actionMsgType.value = type
      if (msg) actionTimer = setTimeout(() => { actionMsg.value = '' }, 4000)
    }

    async function fetchRows () {
      loading.value   = true
      loadError.value = ''
      try {
        // Origin filter is enforced client-side via a "frontend:" or
        // non-"frontend:" `q` hint when no explicit source is picked.
        // (Server-side `q` does LIKE on message+context_json, which is
        // a superset, so we additionally filter the returned rows.)
        const fd = new FormData()
        fd.append('severity', filters.severity.join(','))
        fd.append('source',   filters.source || '')
        fd.append('q',        filters.q || '')
        fd.append('limit',    String(limit.value))
        fd.append('offset',   String(offset.value))

        const data = await postWithCsrf(makeAdminUrl('errors.getErrors'), fd)
        if (!data || !data.success) {
          throw new Error((data && data.message) || 'Unable to load events.')
        }
        let serverRows = Array.isArray(data.rows) ? data.rows : []
        if (filters.origin === 'frontend') {
          serverRows = serverRows.filter(r => isFrontendSource(r.source))
        } else if (filters.origin === 'backend') {
          serverRows = serverRows.filter(r => !isFrontendSource(r.source))
        }
        rows.value    = serverRows
        total.value   = data.total | 0
        sources.value = Array.isArray(data.sources) ? data.sources : []
      } catch (e) {
        loadError.value = e && e.message ? e.message : String(e)
        rows.value      = []
        total.value     = 0
      } finally {
        loading.value = false
      }
    }

    async function fetchSummary () {
      try {
        const data = await postWithCsrf(makeAdminUrl('errors.getErrorsSummary'), {})
        if (data && data.success) {
          summary.total        = data.total | 0
          summary.errors_24h   = data.errors_24h | 0
          summary.warnings_24h = data.warnings_24h | 0
          summary.last_at      = data.last_at || null
        }
      } catch (_e) { /* non-blocking */ }
    }

    async function refresh () {
      await Promise.all([fetchRows(), fetchSummary()])
    }

    function onFilterChange () {
      offset.value = 0
      fetchRows()
    }

    let searchTimer = null
    function onSearchDebounced () {
      clearTimeout(searchTimer)
      searchTimer = setTimeout(() => onFilterChange(), 250)
    }

    function nextPage () {
      if (!hasMore.value) return
      offset.value += limit.value
      fetchRows()
    }
    function prevPage () {
      offset.value = Math.max(0, offset.value - limit.value)
      fetchRows()
    }

    function toggleExpand (id) {
      expanded[id] = !expanded[id]
    }

    async function clearAll () {
      if (!confirm('Clear all error log rows? This cannot be undone.')) return
      busyClear.value = true
      try {
        const data = await postWithCsrf(makeAdminUrl('errors.clearErrors'), {})
        if (!data || !data.success) {
          throw new Error((data && data.message) || 'Failed to clear log.')
        }
        offset.value = 0
        await refresh()
        setMsg('Error log cleared.', 'success')
      } catch (e) {
        setMsg('Clear failed: ' + (e && e.message ? e.message : String(e)), 'error')
      } finally {
        busyClear.value = false
      }
    }

    function formatContext (ctx) {
      if (ctx == null) return ''
      if (typeof ctx === 'string') return ctx
      try { return JSON.stringify(ctx, null, 2) } catch (_e) { return String(ctx) }
    }

    function rowAsJson (row) {
      return {
        id:         row.id,
        created_at: row.created_at,
        severity:   row.severity,
        source:     row.source || null,
        message:    row.message || '',
        request_id: row.request_id || null,
        context:    row.context ?? null,
      }
    }

    async function copyToClipboard (text) {
      try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
          await navigator.clipboard.writeText(text)
        } else {
          const ta = document.createElement('textarea')
          ta.value = text
          ta.style.position = 'fixed'
          ta.style.opacity = '0'
          document.body.appendChild(ta)
          ta.select()
          document.execCommand('copy')
          document.body.removeChild(ta)
        }
        setMsg('Copied to clipboard.', 'success')
      } catch (e) {
        setMsg('Copy failed: ' + (e && e.message ? e.message : String(e)), 'error')
      }
    }

    // Copy payloads are strict JSON so admins can paste them straight
    // into a bug report or support ticket without manual cleanup.
    function copyRow (row) {
      copyToClipboard(JSON.stringify(rowAsJson(row), null, 2))
    }

    function copyVisible () {
      if (!rows.value.length) return
      copyToClipboard(JSON.stringify(rows.value.map(rowAsJson), null, 2))
    }

    // Build a Markdown bug report for a frontend-source row. Bundles the
    // human-readable bits (message, route, UA, stack) up top, then the
    // remaining context as a fenced JSON block, then up to PRECEDING_MAX
    // older rows from the currently-loaded page so the maintainer can see
    // what happened just before the error. Rows are sorted DESC by id on
    // the server, so "preceding" entries are the ones that appear AFTER
    // the target row in rows.value.
    const PRECEDING_MAX = 5
    function buildBugReport (row) {
      const ctx       = (row.context && typeof row.context === 'object') ? row.context : {}
      const route     = ctx.route || ''
      const userAgent = ctx.user_agent || ''
      const stack     = ctx.stack || ''

      // Strip the fields we surfaced above so the "Other context" block
      // does not repeat them.
      const rest = {}
      for (const k in ctx) {
        if (k === 'route' || k === 'user_agent' || k === 'stack') continue
        rest[k] = ctx[k]
      }

      const lines = []
      lines.push('## Frontend error: ' + (row.message || '(no message)'))
      lines.push('')
      lines.push('- **When (UTC):** ' + (row.created_at || '—'))
      lines.push('- **Severity:** ' + (row.severity || '—'))
      lines.push('- **Source:** `' + (row.source || '—') + '`')
      lines.push('- **Route:** ' + (route ? '`' + route + '`' : '—'))
      lines.push('- **User agent:** ' + (userAgent || '—'))
      lines.push('- **Request ID:** ' + (row.request_id ? '`' + row.request_id + '`' : '—'))
      lines.push('- **Row ID:** #' + row.id)
      lines.push('')

      if (stack) {
        lines.push('### Stack')
        lines.push('```')
        lines.push(String(stack))
        lines.push('```')
        lines.push('')
      }

      if (Object.keys(rest).length) {
        lines.push('### Other context')
        lines.push('```json')
        lines.push(JSON.stringify(rest, null, 2))
        lines.push('```')
        lines.push('')
      }

      // Preceding log lines (older entries — rows are id-DESC).
      const idx = rows.value.findIndex(r => r.id === row.id)
      const preceding = idx >= 0 ? rows.value.slice(idx + 1, idx + 1 + PRECEDING_MAX) : []
      if (preceding.length) {
        lines.push('### Preceding log lines (' + preceding.length + ', newest first)')
        for (const r of preceding) {
          const msg = String(r.message || '').replace(/\s+/g, ' ').slice(0, 240)
          lines.push('- `' + (r.created_at || '—') + '` **' + r.severity + '** `'
            + (r.source || '—') + '` — ' + msg)
        }
        lines.push('')
      }

      return lines.join('\n')
    }

    function copyBugReport (row) {
      copyToClipboard(buildBugReport(row))
    }

    function severityBadgeClass (s) {
      if (s === 'error')   return 'ab-badge--danger'
      if (s === 'warning') return 'ab-badge--warning'
      if (s === 'info')    return 'ab-badge--info'
      return ''
    }

    function formatDate (s) {
      if (!s) return ''
      try {
        const d = new Date(s.replace(' ', 'T') + 'Z')
        if (Number.isNaN(d.getTime())) return s
        return d.toLocaleString()
      } catch (_e) { return s }
    }

    let timer = null
    function startAutoRefresh () {
      if (timer) return
      timer = setInterval(refresh, AUTO_REFRESH_SEC * 1000)
    }
    function stopAutoRefresh () {
      if (timer) { clearInterval(timer); timer = null }
    }
    function onAutoRefreshChange () {
      if (autoRefresh.value) startAutoRefresh()
      else stopAutoRefresh()
    }

    // After the first paint, if the URL carries a ?field=<key> hint
    // (used by Health "Fix It" deep-links — see HealthCheckService
    // ::infoErrorLogging) scroll to and briefly highlight the matching
    // data-ab-field control so the admin lands directly on the action.
    function focusTargetField () {
      try {
        const qs = new URLSearchParams(window.location.search)
        const field = qs.get('field')
        if (!field) return
        const el = document.querySelector('[data-ab-field="' + field + '"]')
        if (!el) return
        el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        el.classList.add('ab-field-highlight')
        setTimeout(() => el.classList.remove('ab-field-highlight'), 2500)
      } catch (_e) { /* no-op */ }
    }

    onMounted(() => {
      refresh()
      if (autoRefresh.value) startAutoRefresh()
      // Defer one tick so the toolbar (incl. Clear All) is in the DOM.
      setTimeout(focusTargetField, 50)
    })
    onBeforeUnmount(() => {
      if (timer) clearInterval(timer)
      clearTimeout(actionTimer)
      clearTimeout(searchTimer)
    })

    return {
      AUTO_REFRESH_SEC, allSeverities: ALL_SEVERITIES,
      rows, total, sources, filteredSources, summary, loading, loadError,
      actionMsg, actionMsgType, busyClear, autoRefresh,
      expanded, filters, limit, offset, minSeverity, goToErrorLogging,
      rangeStart, rangeEnd, hasMore,
      refresh, onFilterChange, onSearchDebounced,
      nextPage, prevPage, toggleExpand,
      clearAll, copyRow, copyVisible, copyBugReport,
      formatContext, severityBadgeClass, formatDate,
      onAutoRefreshChange, isFrontendSource,
    }
  },
}
</script>

<style>
.ab-page-errors .ab-chip {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  padding: .25rem .55rem;
  border: 1px solid var(--ab-border);
  border-radius: 999px;
  font-size: .8125rem;
  cursor: pointer;
  user-select: none;
}
.ab-page-errors .ab-chip input { margin: 0; }

.ab-chip-dot {
  display: inline-block;
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--ab-text-muted);
}
.ab-chip-dot--debug   { background: #6c757d; }
.ab-chip-dot--info    { background: #2db7e6; }
.ab-chip-dot--warning { background: #f4a73a; }
.ab-chip-dot--error   { background: #d24a4a; }

/* Origin badge next to source — distinguishes frontend (Task #513)
   from backend rows at a glance. */
.ab-source-tag {
  display: inline-block;
  font-size: .65rem;
  font-weight: 600;
  letter-spacing: .03em;
  padding: 1px 5px;
  border-radius: 3px;
  margin-right: .35rem;
  vertical-align: middle;
}
.ab-source-tag--be { background: var(--ab-surface-raised); color: var(--ab-text-muted); }
.ab-source-tag--fe { background: #efe1fb; color: #6a3aa6; }

.ab-errors-table .ab-err-row--error   { background: rgba(210, 74, 74, .06); }
.ab-errors-table .ab-err-row--warning { background: rgba(244, 167, 58, .05); }
.ab-errors-table .ab-err-row:hover    { filter: brightness(.97); }
.ab-errors-table .ab-err-msg {
  white-space: pre-wrap;
  word-break: break-word;
  font-size: .875rem;
}
.ab-err-msg-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .75rem;
}
.ab-err-msg-text { flex: 1 1 auto; min-width: 0; }
.ab-err-bug-btn  { flex: 0 0 auto; white-space: nowrap; }
.ab-err-detail-row > td { background: var(--ab-surface-raised); }
.ab-err-detail { padding: .5rem .25rem; }
.ab-err-context {
  margin: 0;
  padding: .5rem .75rem;
  background: var(--ab-surface);
  border: 1px solid var(--ab-border);
  border-radius: 4px;
  font-size: .8125rem;
  max-height: 320px;
  overflow: auto;
}

/* Brief highlight applied by focusTargetField() when arriving via
   a Health "Fix It" deep-link (?field=<key>). */
.ab-field-highlight {
  animation: ab-field-pulse 2.5s ease-out;
  box-shadow: 0 0 0 3px rgba(220, 53, 69, .55);
  border-radius: 4px;
}
@keyframes ab-field-pulse {
  0%   { box-shadow: 0 0 0 0   rgba(220, 53, 69, .85); }
  40%  { box-shadow: 0 0 0 6px rgba(220, 53, 69, .35); }
  100% { box-shadow: 0 0 0 0   rgba(220, 53, 69, 0);   }
}
</style>
