<template>
  <div class="ab-page-urlchecker">

    <PageHeader title="URL Checker" subtitle="Scan a list of URLs and check HTTP status, redirect chains, canonical tags and thin content." />

    <div class="ab-card mb-3">
      <div class="ab-card__header" style="justify-content:space-between">
        <span>Scan URLs</span>
        <span class="ab-help">{{ urlCount }} URL{{ urlCount === 1 ? '' : 's' }} ready</span>
      </div>
      <div class="ab-card__body">
        <div class="ab-row" style="flex-wrap:wrap;margin-bottom:.7rem">
          <button class="ab-btn ab-btn--primary ab-btn--sm" @click="loadSitemap" :disabled="busy">
            <span v-if="loadingSitemap" class="ab-spinner ab-spinner--sm me-1"></span>
            Load URLs from sitemap
          </button>
          <button class="ab-btn ab-btn--ghost ab-btn--sm" @click="urls = ''" :disabled="busy">Clear</button>
        </div>

        <div class="ab-field">
          <label class="ab-label">URLs (one per line, max 50 per scan)</label>
          <textarea v-model="urls" rows="8" class="ab-textarea codearea" placeholder="https://example.com/page-1"></textarea>
        </div>

        <div v-if="sitemapMsg" class="small mt-2" :style="sitemapMsgOk ? 'color:var(--ab-success)' : 'color:var(--ab-danger)'">{{ sitemapMsg }}</div>

        <div class="ab-row" style="flex-wrap:wrap;justify-content:space-between;margin-top:.8rem">
          <div class="ab-row">
            <button class="ab-btn ab-btn--success ab-btn--sm" @click="scan" :disabled="busy || !urlCount">
              <span v-if="scanning" class="ab-spinner ab-spinner--sm me-1"></span>
              {{ scanning ? `Scanning ${progress.done}/${progress.total}…` : 'Start scan' }}
            </button>
            <button v-if="scanning" class="ab-btn ab-btn--ghost ab-btn--sm ab-btn--danger-ghost" @click="cancel">Cancel</button>
          </div>
          <button class="ab-btn ab-btn--ghost ab-btn--sm" @click="checkGsc" :disabled="busy || !urlCount">
            <span v-if="gscBusy" class="ab-spinner ab-spinner--sm me-1"></span>
            Compare against Google Search Console
          </button>
        </div>

        <div v-if="scanning || (progress.total > 0 && progress.done < progress.total)" class="mt-3">
          <div class="d-flex justify-content-between ab-help mb-1">
            <span>Progress: {{ progress.done }} / {{ progress.total }} URLs ({{ progressPct }}%)</span>
            <span v-if="scanning">Batch size: {{ BATCH }}</span>
          </div>
          <div class="ab-progress" role="progressbar" :aria-valuenow="progressPct" aria-valuemin="0" aria-valuemax="100">
            <div class="ab-progress__bar" :style="{ width: progressPct + '%' }"></div>
          </div>
        </div>

        <div v-if="batchErrors.length" class="ab-alert ab-alert--warning small mt-3 mb-0">
          {{ batchErrors.length }} batch(es) failed during the last scan — results may be incomplete.
          <details class="mt-1">
            <summary>Details</summary>
            <ul class="mb-0"><li v-for="(err, i) in batchErrors" :key="i"><code>{{ err }}</code></li></ul>
          </details>
        </div>

        <div v-if="gscMsg" class="ab-alert mt-3 mb-0" :class="gscMsgOk ? 'ab-alert--info' : 'ab-alert--danger'">
          <div>{{ gscMsg }}</div>
          <details v-if="gscNotIndexed.length" class="mt-2">
            <summary>{{ gscNotIndexed.length }} URL(s) not found in GSC</summary>
            <ul class="mb-0 mt-1 small">
              <li v-for="u in gscNotIndexed.slice(0, 200)" :key="u"><code>{{ u }}</code></li>
            </ul>
            <div v-if="gscNotIndexed.length > 200" class="ab-help mt-1">… and {{ gscNotIndexed.length - 200 }} more.</div>
          </details>
        </div>
      </div>
    </div>

    <div v-if="results.length || scanning" class="ab-card">
      <div class="ab-card__header" style="justify-content:space-between">
        <span>Results</span>
        <span class="ab-help">{{ results.length }} URLs</span>
      </div>
      <div class="ab-card__body">
        <div class="d-flex flex-wrap gap-2 mb-3 small">
          <span class="ab-badge ab-badge--success">200 OK: {{ counts.ok }}</span>
          <span class="ab-badge ab-badge--info">3xx redirect: {{ counts.redirect }}</span>
          <span class="ab-badge ab-badge--danger">4xx/5xx error: {{ counts.error }}</span>
          <span class="ab-badge ab-badge--warning">Noindex: {{ counts.noindex }}</span>
          <span class="ab-badge ab-badge--warning">Thin content: {{ counts.thin }}</span>
          <span class="ab-badge ab-badge--warning">Canonical issues: {{ counts.canonical }}</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle" style="color:var(--ab-text);background:var(--ab-surface)">
            <thead style="background:var(--ab-surface-raised)">
              <tr>
                <th>URL</th>
                <th class="text-center">Status</th>
                <th>Redirect chain</th>
                <th class="text-center">Canonical</th>
                <th class="text-center">Flags</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="scanning && !results.length">
                <td colspan="5" class="text-center py-4" style="color:var(--ab-text-muted)">
                  <span class="ab-spinner ab-spinner--sm me-2"></span>
                  Fetching first batch ({{ BATCH }} URLs)… Results will stream in as each batch completes.
                </td>
              </tr>
              <tr v-for="r in results" :key="r.url">
                <td class="small"><code>{{ r.url }}</code></td>
                <td class="text-center">
                  <span class="ab-badge" :class="statusBadge(r.status)">{{ r.status || '–' }}</span>
                  <div v-if="r.error" class="small" style="color:var(--ab-danger)">{{ r.error }}</div>
                </td>
                <td class="small" style="color:var(--ab-text-muted)">
                  <span v-if="!r.redirect_chain || r.redirect_chain.length < 2">—</span>
                  <span v-else>{{ r.redirect_chain.length - 1 }} hop(s) → <code>{{ r.redirect_chain[r.redirect_chain.length - 1].url }}</code></span>
                </td>
                <td class="text-center">
                  <span class="ab-badge" :class="canonicalBadge(r.canonical_status)">{{ r.canonical_status }}</span>
                  <a
                    v-if="r.canonical_status === 'missing' || r.canonical_status === 'mismatch'"
                    href="index.php?option=com_aiboost&view=app#/settings?tab=titles&field=enable_canonical"
                    class="ab-fix-link d-block mt-1"
                    title="Open Settings → Technical SEO → Enable Canonical URLs"
                  >Fix it →</a>
                </td>
                <td class="text-center small">
                  <span v-if="r.is_noindex" class="ab-badge ab-badge--warning me-1">noindex</span>
                  <span v-if="r.is_thin_content" class="ab-badge ab-badge--warning">thin ({{ r.content_chars }}c)</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import { ref, computed } from 'vue'
import { postWithCsrf, makeAdminUrl } from './api.js'
import PageHeader from './components/PageHeader.vue'

export default {
  name: 'UrlCheckerPage',
  components: { PageHeader },
  setup() {
    const urls           = ref('')
    const results        = ref([])
    const loadingSitemap = ref(false)
    const scanning       = ref(false)
    const cancelled      = ref(false)
    const sitemapMsg     = ref('')
    const sitemapMsgOk   = ref(false)
    const progress       = ref({ done: 0, total: 0 })
    const batchErrors    = ref([])
    const gscBusy        = ref(false)
    const gscMsg         = ref('')
    const gscMsgOk       = ref(false)
    const gscNotIndexed  = ref([])

    const urlList = computed(() =>
      urls.value.split('\n').map(s => s.trim()).filter(Boolean)
    )
    const urlCount = computed(() => urlList.value.length)
    const busy = computed(() => scanning.value || loadingSitemap.value || gscBusy.value)

    const BATCH = 5
    const progressPct = computed(() =>
      progress.value.total > 0 ? Math.round((progress.value.done / progress.value.total) * 100) : 0
    )

    const counts = computed(() => {
      const c = { ok: 0, redirect: 0, error: 0, noindex: 0, thin: 0, canonical: 0 }
      for (const r of results.value) {
        if (r.status >= 200 && r.status < 300) c.ok++
        else if (r.status >= 300 && r.status < 400) c.redirect++
        else if (r.status >= 400 || r.status === 0) c.error++
        if (r.is_noindex) c.noindex++
        if (r.is_thin_content) c.thin++
        if (r.canonical_status === 'missing' || r.canonical_status === 'mismatch') c.canonical++
      }
      return c
    })

    async function loadSitemap() {
      loadingSitemap.value = true
      sitemapMsg.value = ''
      try {
        const data = await postWithCsrf(makeAdminUrl('urlchecker.getSitemapUrls'))
        if (!data.success) throw new Error(data.message || 'Failed.')
        urls.value = (data.urls || []).join('\n')
        sitemapMsgOk.value = true
        sitemapMsg.value   = `Loaded ${data.count} URL(s) from ${data.sitemapUrl}.`
      } catch (e) {
        sitemapMsgOk.value = false
        sitemapMsg.value   = e.message || String(e)
      } finally {
        loadingSitemap.value = false
      }
    }

    async function scan() {
      const list = urlList.value
      if (!list.length) return
      results.value = []
      batchErrors.value = []
      scanning.value = true
      cancelled.value = false
      progress.value = { done: 0, total: list.length }

      const BATCH = 5
      for (let i = 0; i < list.length; i += BATCH) {
        if (cancelled.value) break
        const batch = list.slice(i, i + BATCH)
        try {
          const data = await postWithCsrf(makeAdminUrl('urlchecker.checkBatch'), { urls: JSON.stringify(batch) })
          if (data.success && Array.isArray(data.results)) {
            results.value = [...results.value, ...data.results]
          } else {
            batchErrors.value.push(`Batch ${i / BATCH + 1}: ${data.message || 'unknown error'}`)
          }
        } catch (e) {
          batchErrors.value.push(`Batch ${i / BATCH + 1}: ${e.message || e}`)
        }
        progress.value.done = Math.min(i + BATCH, list.length)
      }
      scanning.value = false
    }

    function cancel() { cancelled.value = true }

    async function checkGsc() {
      const list = urlList.value
      if (!list.length) return
      gscBusy.value = true
      gscMsg.value = ''
      gscNotIndexed.value = []
      try {
        const data = await postWithCsrf(
          makeAdminUrl('urlchecker.checkGscIndexation'),
          { urls: JSON.stringify(list) }
        )
        gscMsgOk.value = !!data.success
        gscMsg.value   = data.message || (data.success ? 'GSC comparison complete.' : 'GSC comparison failed.')
        if (data.success && Array.isArray(data.not_indexed)) gscNotIndexed.value = data.not_indexed
      } catch (e) {
        gscMsgOk.value = false
        gscMsg.value   = e.message || String(e)
      } finally {
        gscBusy.value = false
      }
    }

    function statusBadge(s) {
      if (!s) return 'ab-status-unknown'
      if (s >= 200 && s < 300) return 'ab-status-ok'
      if (s >= 300 && s < 400) return 'ab-status-redirect'
      return 'ab-status-error'
    }
    function canonicalBadge(s) {
      if (s === 'ok') return 'ab-status-ok'
      if (s === 'missing' || s === 'mismatch') return 'ab-status-warn'
      return 'ab-status-unknown'
    }

    return { urls, urlCount, results, loadingSitemap, scanning, busy, sitemapMsg, sitemapMsgOk,
             progress, progressPct, BATCH, counts, batchErrors,
             gscBusy, gscMsg, gscMsgOk, gscNotIndexed,
             loadSitemap, scan, cancel, checkGsc, statusBadge, canonicalBadge }
  },
}
</script>

<style scoped>
.codearea { font-family: var(--ab-font-mono); font-size: var(--ab-font-size-sm); }
.ab-progress { height: 8px; background: var(--ab-surface-raised); border-radius: 4px; overflow: hidden; }
.ab-progress__bar { height: 100%; background: linear-gradient(90deg, var(--ab-success) 0%, var(--ab-primary) 100%); transition: width .3s ease-out; }

.ab-status-ok       { background: var(--ab-success); color: #fff; }
.ab-status-redirect { background: var(--ab-primary); color: #fff; }
.ab-status-error    { background: var(--ab-danger); color: #fff; }
.ab-status-warn     { background: var(--ab-warning); color: #fff; }
.ab-status-unknown  { background: var(--ab-text-muted); color: #fff; }

.ab-fix-link {
  font-size: .75rem;
  color: var(--ab-text-muted);
  text-decoration: none;
  white-space: nowrap;
}
.ab-fix-link:hover { text-decoration: underline; color: var(--ab-primary); }
</style>
