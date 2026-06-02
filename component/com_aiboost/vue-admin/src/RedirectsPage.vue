<template>
  <div class="ab-page-redirects">
    <h2 class="ab-h2 mb-3">Redirects</h2>

    <ul class="ab-tabs mb-3" role="tablist">
      <li class="nav-item">
        <button class="ab-tab" :class="{ 'ab-tab--active': tab === 'rules' }" @click="tab = 'rules'">
          Redirect Rules <span class="ab-badge ms-1">{{ redirects.length }}</span>
        </button>
      </li>
      <li class="nav-item">
        <button class="ab-tab" :class="{ 'ab-tab--active': tab === 'log404' }" @click="tab = 'log404'">
          404 Log <span class="ab-badge ms-1">{{ total404 }}</span>
        </button>
      </li>
      <li class="nav-item">
        <button class="ab-tab" :class="{ 'ab-tab--active': tab === 'import' }" @click="tab = 'import'">
          CSV Import
        </button>
      </li>
    </ul>

    <div v-if="loading" class="text-muted small py-3">Loading…</div>
    <div v-else-if="loadError" class="ab-alert ab-alert--danger">{{ loadError }}</div>

    <!-- ─── Rules tab ─────────────────────────────────────────────── -->
    <div v-else-if="tab === 'rules'">
      <div class="ab-card mb-3">
        <div class="ab-card-body">
          <h3 class="ab-h3 mb-3">Add new rule</h3>
          <form class="row g-2" @submit.prevent="addRedirect">
            <div class="col-md-4">
              <label class="ab-label small text-muted">From URL (relative or absolute)</label>
              <input v-model="form.from_url" type="text" class="ab-input" placeholder="/old-page" required>
            </div>
            <div class="col-md-4">
              <label class="ab-label small text-muted">To URL</label>
              <input v-model="form.to_url" type="text" class="ab-input" placeholder="/new-page" required>
            </div>
            <div class="col-md-2">
              <label class="ab-label small text-muted">Type</label>
              <select v-model.number="form.redirect_type" class="ab-select">
                <option v-for="t in [301, 302, 303, 307, 308]" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="ab-btn ab-btn--primary w-100" :disabled="busy">
                {{ busy ? 'Adding…' : 'Add Rule' }}
              </button>
            </div>
            <div class="col-12">
              <input v-model="form.note" type="text" class="ab-input form-control-sm" placeholder="Optional note">
            </div>
          </form>
          <div v-if="formMsg" class="small mt-2" :class="formMsgOk ? 'text-success' : 'text-danger'">{{ formMsg }}</div>
        </div>
      </div>

      <div class="ab-card">
        <div class="ab-card-body">
          <h3 class="ab-h3 mb-3">Active rules</h3>
          <div v-if="!redirects.length" class="text-muted small">No redirect rules yet.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>From</th>
                  <th>To</th>
                  <th class="text-center">Type</th>
                  <th class="text-end">Hits</th>
                  <th class="text-center">Enabled</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in redirects" :key="r.id">
                  <td><code class="small">{{ r.from_url }}</code></td>
                  <td><code class="small">{{ r.to_url }}</code></td>
                  <td class="text-center"><span class="ab-badge ab-badge--info">{{ r.redirect_type }}</span></td>
                  <td class="text-end">{{ r.hits }}</td>
                  <td class="text-center">
                    <div class="ab-check ab-toggle d-inline-block">
                      <input class="ab-toggle__input" type="checkbox"
                             :checked="Number(r.enabled) === 1"
                             @change="toggle(r, $event.target.checked)">
                    </div>
                  </td>
                  <td class="text-end">
                    <button class="ab-btn ab-btn--ghost ab-btn--sm ab-btn--danger-ghost" @click="remove(r)">Delete</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── 404 log tab ───────────────────────────────────────────── -->
    <div v-else-if="tab === 'log404'">
      <div class="ab-card">
        <div class="ab-card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="ab-h3 mb-0">Recent 404 errors</h3>
            <button class="ab-btn ab-btn--ghost ab-btn--sm ab-btn--danger-ghost" @click="clearLog" :disabled="!log404.length || busy">
              Clear 404 log
            </button>
          </div>
          <div v-if="!log404.length" class="text-muted small">No 404 errors logged.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>URL</th>
                  <th>Referrer</th>
                  <th class="text-end">Hits</th>
                  <th>Last seen</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="e in log404" :key="e.id">
                  <td><code class="small">{{ e.request_url }}</code></td>
                  <td class="text-muted small">{{ e.referrer || '—' }}</td>
                  <td class="text-end">{{ e.hits }}</td>
                  <td class="text-muted small">{{ e.last_seen }}</td>
                  <td class="text-end">
                    <button class="ab-btn ab-btn--ghost ab-btn--sm" @click="prefillFrom(e)">Create rule</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── CSV import tab ────────────────────────────────────────── -->
    <div v-else>
      <div class="ab-card">
        <div class="ab-card-body">
          <h3 class="ab-h3 mb-3">Bulk import from CSV</h3>
          <p class="text-muted small">
            One rule per line in the format <code>from_url,to_url,type</code>. Lines starting with
            <code>#</code> or the header <code>from_url,…</code> are ignored. Default type is 301.
          </p>
          <textarea v-model="csv" rows="10" class="ab-input font-monospace"
                    placeholder="/old-page,/new-page,301
/another,/elsewhere,302"></textarea>
          <div class="mt-3 d-flex gap-2 align-items-center">
            <button class="ab-btn ab-btn--primary" :disabled="busy || !csv.trim()" @click="importCsv">
              {{ busy ? 'Importing…' : 'Import CSV' }}
            </button>
            <span v-if="csvMsg" class="small" :class="csvMsgOk ? 'text-success' : 'text-danger'">{{ csvMsg }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { postWithCsrf, makeAdminUrl } from './api.js'

export default {
  name: 'RedirectsPage',
  setup() {
    const tab        = ref('rules')
    const loading    = ref(true)
    const loadError  = ref('')
    const busy       = ref(false)
    const redirects  = ref([])
    const log404     = ref([])
    const total404   = ref(0)
    const form       = ref({ from_url: '', to_url: '', redirect_type: 301, note: '' })
    const formMsg    = ref('')
    const formMsgOk  = ref(false)
    const csv        = ref('')
    const csvMsg     = ref('')
    const csvMsgOk   = ref(false)

    async function load() {
      loading.value = true
      loadError.value = ''
      try {
        const data = await postWithCsrf(makeAdminUrl('redirects.listJson'))
        if (!data.success) throw new Error(data.message || 'Load failed')
        redirects.value = data.redirects || []
        log404.value    = data.log404 || []
        total404.value  = data.total404 || 0
      } catch (e) {
        loadError.value = e.message || String(e)
      } finally {
        loading.value = false
      }
    }

    async function addRedirect() {
      busy.value = true
      formMsg.value = ''
      try {
        const data = await postWithCsrf(makeAdminUrl('redirects.add'), { ...form.value })
        formMsgOk.value = !!data.success
        formMsg.value   = data.message || (data.success ? 'Added.' : 'Failed.')
        if (data.success) {
          form.value = { from_url: '', to_url: '', redirect_type: 301, note: '' }
          await load()
        }
      } catch (e) {
        formMsgOk.value = false
        formMsg.value   = e.message || String(e)
      } finally {
        busy.value = false
      }
    }

    async function toggle(r, enabled) {
      const prev = Number(r.enabled)
      try {
        const data = await postWithCsrf(makeAdminUrl('redirects.toggle'), { id: r.id, enabled: enabled ? 1 : 0 })
        if (data && data.success === false) throw new Error(data.message || 'Toggle failed')
        r.enabled = enabled ? 1 : 0
      } catch (e) {
        r.enabled = prev
        alert('Toggle failed: ' + (e.message || e))
      }
    }

    async function remove(r) {
      if (!confirm('Delete this redirect?')) return
      try {
        const data = await postWithCsrf(makeAdminUrl('redirects.delete'), { id: r.id })
        if (data && data.success === false) throw new Error(data.message || 'Delete failed')
        redirects.value = redirects.value.filter(x => x.id !== r.id)
      } catch (e) { alert('Delete failed: ' + (e.message || e)) }
    }

    async function clearLog() {
      if (!confirm('Clear ALL logged 404 errors?')) return
      busy.value = true
      try {
        const data = await postWithCsrf(makeAdminUrl('redirects.clear404'))
        if (data && data.success === false) throw new Error(data.message || 'Clear failed')
        log404.value = []
        total404.value = 0
      } catch (e) { alert('Clear failed: ' + (e.message || e)) }
      finally { busy.value = false }
    }

    async function importCsv() {
      busy.value = true
      csvMsg.value = ''
      try {
        const data = await postWithCsrf(makeAdminUrl('redirects.importCsv'), { csv: csv.value })
        csvMsgOk.value = !!data.success
        csvMsg.value   = data.success
          ? `Imported ${data.imported} rule(s), skipped ${data.skipped}.`
          : (data.message || 'Import failed.')
        if (data.success) {
          csv.value = ''
          await load()
        }
      } catch (e) {
        csvMsgOk.value = false
        csvMsg.value   = e.message || String(e)
      } finally {
        busy.value = false
      }
    }

    function prefillFrom(entry) {
      form.value.from_url = entry.request_url || ''
      tab.value = 'rules'
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }

    onMounted(() => {
      // Honour ?from_url=… deep link from the dashboard "+ Redirect" shortcut
      try {
        const qs = new URLSearchParams(window.location.search)
        const fromUrl = qs.get('from_url')
        if (fromUrl) form.value.from_url = decodeURIComponent(fromUrl)
      } catch (_e) { /* ignore */ }
      load()
    })

    return { tab, loading, loadError, busy, redirects, log404, total404, form, formMsg, formMsgOk,
             csv, csvMsg, csvMsgOk,
             addRedirect, toggle, remove, clearLog, prefillFrom, importCsv }
  },
}
</script>
