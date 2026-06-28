<template>
  <div class="ab-page-redirects">

    <PageHeader title="Redirects" />

    <ul class="ab-tabs mb-3" role="tablist">
      <li>
        <button class="ab-tab" :class="{ 'ab-tab--active': tab === 'rules' }" @click="tab = 'rules'">
          Redirect Rules <span class="ab-badge ms-1">{{ redirects.length }}</span>
        </button>
      </li>
      <li>
        <button class="ab-tab" :class="{ 'ab-tab--active': tab === 'log404' }" @click="tab = 'log404'">
          404 Log <span class="ab-badge ms-1">{{ total404 }}</span>
        </button>
      </li>
      <li>
        <button class="ab-tab" :class="{ 'ab-tab--active': tab === 'import' }" @click="tab = 'import'">
          CSV Import
        </button>
      </li>
    </ul>

    <div v-if="loading" class="ab-help py-3">Loading…</div>
    <div v-else-if="loadError" class="ab-alert ab-alert--danger">{{ loadError }}</div>

    <!-- ─── Rules tab ─────────────────────────────────────────────── -->
    <div v-else-if="tab === 'rules'">
      <div class="ab-section mb-3">
        <div class="ab-section__head">Add new rule</div>
        <div class="ab-section__body">
          <form class="row g-2" @submit.prevent="addRedirect">
            <div class="col-md-4">
              <div class="ab-field">
                <label class="ab-label">From URL (relative or absolute)</label>
                <input v-model="form.from_url" type="text" class="ab-input" placeholder="/old-page" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="ab-field">
                <label class="ab-label">To URL</label>
                <input v-model="form.to_url" type="text" class="ab-input" placeholder="/new-page" required>
              </div>
            </div>
            <div class="col-md-2">
              <div class="ab-field">
                <label class="ab-label">Type</label>
                <select v-model.number="form.redirect_type" class="ab-select">
                  <option v-for="t in [301, 302, 303, 307, 308]" :key="t" :value="t">{{ t }}</option>
                </select>
              </div>
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button type="submit" class="ab-btn ab-btn--primary" :disabled="busy">
                {{ busy ? 'Adding…' : 'Add Rule' }}
              </button>
            </div>
            <div class="col-12">
              <input v-model="form.note" type="text" class="ab-input form-control-sm" placeholder="Optional note">
            </div>
          </form>
          <div v-if="formMsg" class="small mt-2" :style="formMsgOk ? 'color:var(--ab-success)' : 'color:var(--ab-danger)'">{{ formMsg }}</div>
        </div>
      </div>

      <div class="ab-section">
        <div class="ab-section__head">Active rules</div>
        <div class="ab-section__body">
          <div v-if="!redirects.length" class="ab-help">No redirect rules yet.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle" style="color:var(--ab-text);background:var(--ab-surface)">
              <thead style="background:var(--ab-surface-raised)">
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
                    <span class="ab-toggle" :class="{'is-on': Number(r.enabled) === 1}" style="cursor:pointer" @click="toggle(r, Number(r.enabled) !== 1)">
                      <input type="checkbox" class="ab-toggle__input"
                             :checked="Number(r.enabled) === 1"
                             @change.stop>
                      <span class="ab-toggle__track"></span>
                    </span>
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
      <label class="ab-toggle-row mb-3" style="max-width:560px">
        <div>
          <div class="ab-label">Log 404 Errors</div>
          <div class="ab-help">AI Boost records front-end 404 hits below, so you can turn recurring dead URLs into permanent redirects.</div>
        </div>
        <span class="ab-toggle" :class="{'is-on': log404Enabled}">
          <input type="checkbox" class="ab-toggle__input" :checked="log404Enabled" @change="toggle404Log($event.target.checked)">
          <span class="ab-toggle__track"></span>
        </span>
      </label>
      <div class="ab-section">
        <div class="ab-section__head">
          Recent 404 errors
          <button class="ab-btn ab-btn--ghost ab-btn--sm ab-btn--danger-ghost" style="margin-left:auto" @click="clearLog" :disabled="!log404.length || busy">
            Clear 404 log
          </button>
        </div>
        <div class="ab-section__body">
          <div v-if="!log404.length" class="ab-help">No 404 errors logged.</div>
          <div v-else class="table-responsive">
            <table class="table table-sm align-middle" style="color:var(--ab-text);background:var(--ab-surface)">
              <thead style="background:var(--ab-surface-raised)">
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
                  <td class="small" style="color:var(--ab-text-muted)">{{ e.referrer || '—' }}</td>
                  <td class="text-end">{{ e.hits }}</td>
                  <td class="small" style="color:var(--ab-text-muted)">{{ e.last_seen }}</td>
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
      <div class="ab-section">
        <div class="ab-section__head">Bulk import from CSV</div>
        <div class="ab-section__body">
          <p class="ab-help mb-3">
            One rule per line in the format <code>from_url,to_url,type</code>. Lines starting with
            <code>#</code> or the header <code>from_url,…</code> are ignored. Default type is 301.
          </p>
          <textarea v-model="csv" rows="10" class="ab-input font-monospace"
                    placeholder="/old-page,/new-page,301&#10;/another,/elsewhere,302"></textarea>
          <div class="mt-3 d-flex gap-2 align-items-center">
            <button class="ab-btn ab-btn--primary" :disabled="busy || !csv.trim()" @click="importCsv">
              {{ busy ? 'Importing…' : 'Import CSV' }}
            </button>
            <span v-if="csvMsg" class="small" :style="csvMsgOk ? 'color:var(--ab-success)' : 'color:var(--ab-danger)'">{{ csvMsg }}</span>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import { ref, onMounted } from 'vue'
import { postWithCsrf, makeAdminUrl, saveSettings } from './api.js'
import PageHeader from './components/PageHeader.vue'

export default {
  name: 'RedirectsPage',
  components: { PageHeader },
  setup() {
    const tab        = ref('rules')
    const loading    = ref(true)
    const loadError  = ref('')
    const busy       = ref(false)
    const redirects  = ref([])
    const log404     = ref([])
    const total404   = ref(0)
    const log404Enabled = ref(((window.aiBoostSettings && window.aiBoostSettings.redirect_404_log_enabled) ?? '1') === '1')
    async function toggle404Log(on) {
      log404Enabled.value = !!on
      const v = on ? '1' : '0'
      if (window.aiBoostSettings) window.aiBoostSettings.redirect_404_log_enabled = v
      try { await saveSettings({ redirect_404_log_enabled: v }) } catch (_e) { /* fire-and-forget */ }
    }
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
      try {
        const qs = new URLSearchParams(window.location.search)
        const fromUrl = qs.get('from_url')
        if (fromUrl) form.value.from_url = decodeURIComponent(fromUrl)
      } catch (_e) { /* ignore */ }
      load()
    })

    return { tab, loading, loadError, busy, redirects, log404, total404, form, formMsg, formMsgOk,
             csv, csvMsg, csvMsgOk,
             addRedirect, toggle, remove, clearLog, prefillFrom, importCsv,
             log404Enabled, toggle404Log }
  },
}
</script>
