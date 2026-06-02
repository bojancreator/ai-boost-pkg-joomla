<template>
  <div class="ab-page-urlchecker">
    <h2 class="ab-h2 mb-3">URL Checker</h2>
    <p class="text-muted">
      Scan a list of URLs and check HTTP status, redirect chains, canonical tags and thin content.
    </p>

    <div class="ab-card mb-3">
      <div class="ab-card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
          <button class="ab-btn ab-btn--primary" @click="loadSitemap" :disabled="busy">
            <span v-if="loadingSitemap" class="ab-spinner ab-spinner--sm me-1"></span>
            Load URLs from sitemap
          </button>
          <button class="ab-btn ab-btn--ghost" @click="urls = ''" :disabled="busy">Clear</button>
          <span class="text-muted small align-self-center ms-auto">
            {{ urlCount }} URL{{ urlCount === 1 ? '' : 's' }} ready
          </span>
        </div>

        <label class="ab-label small text-muted">URLs (one per line, max 50 per scan)</label>
        <textarea v-model="urls" rows="8" class="ab-input font-monospace" placeholder="https://example.com/page-1"></textarea>

        <div v-if="sitemapMsg" class="small mt-2" :class="sitemapMsgOk ? 'text-success' : 'text-danger'">{{ sitemapMsg }}</div>

        <div class="mt-3 d-flex flex-wrap gap-2">
          <button class="ab-btn ab-btn--success" @click="scan" :disabled="busy || !urlCount">
            <span v-if="scanning" class="ab-spinner ab-spinner--sm me-1"></span>
            {{ scanning ? `Scanning ${progress.done}/${progress.total}…` : 'Start scan' }}
          </button>
          <button v-if="scanning" class="ab-btn ab-btn--ghost ab-btn--danger-ghost" @click="cancel">Cancel</button>
          <button class="ab-btn ab-btn--ghost ms-auto" @click="checkGsc" :disabled="busy || !urlCount">
            <span v-if="gscBusy" class="ab-spinner ab-spinner--sm me-1"></span>
            Compare against Google Search Console
          </button>
        </div>

        <!-- Live progress bar -->
        <div v-if="scanning || (progress.total > 0 && progress.done < progress.total)" class="mt-3">
          <div class="d-flex justify-content-between small text-muted mb-1">
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
            <ul class="mb-0">
              <li v-for="(err, i) in batchErrors" :key="i"><code>{{ err }}</code></li>
            </ul>
          </details>
        </div>
        <div v-if="gscMsg" class="ab-alert mt-3 mb-0" :class="gscMsgOk ? 'alert-info' : 'alert-danger'">
          <div>{{ gscMsg }}</div>
          <details v-if="gscNotIndexed.length" class="mt-2">
            <summary>{{ gscNotIndexed.length }} URL(s) not found in GSC</summary>
            <ul class="mb-0 mt-1 small">
              <li v-for="u in gscNotIndexed.slice(0, 200)" :key="u"><code>{{ u }}</code></li>
            </ul>
            <div v-if="gscNotIndexed.length > 200" class="text-muted small mt-1">… and {{ gscNotIndexed.length - 200 }} more.</div>
          </details>
        </div>
      </div>
    </div>

    <div v-if="results.length || scanning" class="ab-card">
      <div class="ab-card-body">
        <h3 class="ab-h3 mb-3">Results <span class="text-muted small">({{ results.length }} URLs)</span></h3>
        <div class="row g-2 mb-3 small">
          <div class="col-auto"><span class="ab-badge ab-badge--success">200 OK: {{ counts.ok }}</span></div>
          <div class="col-auto"><span class="ab-badge ab-badge--info">3xx redirect: {{ counts.redirect }}</span></div>
          <div class="col-auto"><span class="ab-badge ab-badge--danger">4xx/5xx error: {{ counts.error }}</span></div>
          <div class="col-auto"><span class="ab-badge ab-badge--warning">Noindex: {{ counts.noindex }}</span></div>
          <div class="col-auto"><span class="ab-badge ab-badge--warning">Thin content: {{ counts.thin }}</span></div>
          <div class="col-auto"><span class="ab-badge ab-badge--warning">Canonical issues: {{ counts.canonical }}</span></div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
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
                <td colspan="5" class="text-center text-muted py-4">
                  <span class="ab-spinner ab-spinner--sm me-2"></span>
                  Fetching first batch ({{ BATCH }} URLs)… Results will stream in as each batch completes.
                </td>
              </tr>
              <tr v-for="r in results" :key="r.url">
                <td class="small"><code>{{ r.url }}</code></td>
                <td class="text-center">
                  <span class="ab-badge" :class="statusBadge(r.status)">{{ r.status || '–' }}</span>
                  <div v-if="r.error" class="text-danger small">{{ r.error }}</div>
                </td>
                <td class="small text-muted">
                  <span v-if="!r.redirect_chain || r.redirect_chain.length < 2">—</span>
                  <span v-else>
                    {{ r.redirect_chain.length - 1 }} hop(s) →
                    <code>{{ r.redirect_chain[r.redirect_chain.length - 1].url }}</code>
                  </span>
                </td>
                <td class="text-center">
                  <span class="ab-badge" :class="canonicalBadge(r.canonical_status)">{{ r.canonical_status }}</span>
                  <a
                    v-if="r.canonical_status === 'missing' || r.canonical_status === 'mismatch'"
                    href="index.php?option=com_aiboost&view=settings&tab=sitemap&field=enable_canonical"
                    class="ab-fix-link d-block mt-1"
                    title="Open Settings → Sitemap → Enable Canonical URLs"
                  >⚙️ Fix It →</a>
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

export default {
  name: 'UrlCheckerPage',
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
        if (data.success && Array.isArray(data.not_indexed)) {
          gscNotIndexed.value = data.not_indexed
        }
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
.ab-progress {
  height: 8px;
  background: var(--secondary-bg, #e9ecef);
  border-radius: 4px;
  overflow: hidden;
}
.ab-progress__bar {
  height: 100%;
  background: linear-gradient(90deg, #10b981 0%, #06b6d4 100%);
  transition: width .3s ease-out;
}

/* Solid-colour status badges for the results table — always white text */
.ab-status-ok       { background: #16a34a; color: #fff; }
.ab-status-redirect { background: #0891b2; color: #fff; }
.ab-status-error    { background: #dc2626; color: #fff; }
.ab-status-warn     { background: #d97706; color: #fff; }
.ab-status-unknown  { background: var(--ab-text-muted, #6b7280); color: #fff; }

[data-bs-theme="dark"] .ab-status-ok       { background: #15803d; }
[data-bs-theme="dark"] .ab-status-redirect { background: #0e7490; }
[data-bs-theme="dark"] .ab-status-error    { background: #b91c1c; }
[data-bs-theme="dark"] .ab-status-warn     { background: #b45309; }
[data-bs-theme="dark"] .ab-status-unknown  { background: #4b5563; }

.ab-fix-link {
  font-size: .75rem;
  color: var(--ab-text-muted, #6c757d);
  text-decoration: none;
  white-space: nowrap;
}
.ab-fix-link:hover { text-decoration: underline; color: var(--ab-primary, #ef4444); }
</style>
