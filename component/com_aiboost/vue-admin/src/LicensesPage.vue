<template>
  <div class="ab-licenses-page">

    <PageHeader
      title="License &amp; Updates"
      subtitle="Enter your AI Boost license key to verify your purchase. A license includes updates and support for one year. Activation is perpetual: if the license later expires, every installed feature keeps working — expiry only pauses updates and support until you renew. Integration plugins are licensed separately and appear below once installed."
    />

    <div v-if="loadError" class="ab-alert ab-alert--danger">
      <strong>Failed to load:</strong> {{ loadError }}
    </div>

    <div v-if="proActivated" class="ab-license-active">
      <strong>License is active.</strong> Updates and support are available.<span v-if="proActivatedAt"> Activated {{ formatDate(proActivatedAt) }}.</span>
    </div>

    <!-- Core license -->
    <div v-if="!loadError" class="ab-section">
      <div class="ab-section__head">AI Boost</div>
      <div class="ab-section__body">
        <div class="ab-license-row">
          <div class="ab-license-row__product">
            <strong>AI Boost</strong>
            <div class="ab-help">Updates and support</div>
          </div>
          <div class="ab-license-row__key">
            <input
              type="text"
              class="ab-input ab-input--mono"
              v-model="core.key"
              placeholder="Paste your license key"
              :disabled="core.busy"
            />
          </div>
          <div class="ab-license-row__status">
            <span class="ab-badge" :class="badgeClass(core.status)">{{ statusLabel(core.status) }}</span>
            <div v-if="core.message" class="ab-help">{{ core.message }}</div>
          </div>
          <div class="ab-license-row__expiry">
            <span v-if="core.expires_at" class="small">{{ formatDate(core.expires_at) }}</span>
            <span v-else class="ab-help">—</span>
          </div>
          <div class="ab-license-row__actions">
            <button type="button" class="ab-btn ab-btn--sm ab-btn--primary"
              :disabled="core.busy || !core.key.trim()" @click="verify(core)">
              <span v-if="core.busy">Verifying…</span>
              <span v-else>Verify</span>
            </button>
            <button v-if="core.status === 'active'" type="button" class="ab-btn ab-btn--sm ab-btn--ghost ms-1"
              :disabled="core.busy" @click="deactivate(core)">Release</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Integration plugins -->
    <div v-if="!loadError && integrations.length" class="ab-section">
      <div class="ab-section__head">Integration plugins</div>
      <div class="ab-section__body">
        <p class="ab-help mb-3">Each installed integration (Multilang, YOOtheme, …) is sold separately and has its own license key.</p>
        <div v-for="row in integrations" :key="row.sku" class="ab-license-row">
          <div class="ab-license-row__product">
            <strong>{{ row.label }}</strong>
            <div class="ab-help">{{ row.sku }}</div>
          </div>
          <div class="ab-license-row__key">
            <input type="text" class="ab-input ab-input--mono"
              v-model="row.key" placeholder="Paste your license key" :disabled="row.busy" />
          </div>
          <div class="ab-license-row__status">
            <span class="ab-badge" :class="badgeClass(row.status)">{{ statusLabel(row.status) }}</span>
            <div v-if="row.message" class="ab-help">{{ row.message }}</div>
          </div>
          <div class="ab-license-row__expiry">
            <span v-if="row.expires_at" class="small">{{ formatDate(row.expires_at) }}</span>
            <span v-else class="ab-help">—</span>
          </div>
          <div class="ab-license-row__actions">
            <button type="button" class="ab-btn ab-btn--sm ab-btn--primary"
              :disabled="row.busy || !row.key.trim()" @click="verify(row)">
              <span v-if="row.busy">Verifying…</span>
              <span v-else>Verify</span>
            </button>
            <button v-if="row.status === 'active'" type="button" class="ab-btn ab-btn--sm ab-btn--ghost ms-1"
              :disabled="row.busy" @click="deactivate(row)">Release</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Heartbeat status -->
    <div class="ab-alert" :class="heartbeatAlertClass">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="flex-grow-1">
          <template v-if="heartbeat.last_checked_at">
            <strong>Last verified:</strong> {{ formatRelative(heartbeat.last_checked_at) }}
            <span v-if="heartbeat.last_verdict === 'domain_mismatch' || heartbeat.domain_collision">
              — <strong>Domain collision detected.</strong>
              {{ heartbeat.message || 'This license has been used on another domain.' }}
            </span>
            <span v-else-if="heartbeat.status === 'expired' || (heartbeat.last_verdict && heartbeat.last_verdict !== 'ok')">
              — <strong>License lapsed.</strong> Installed features keep working; renewing restores updates &amp; support.
            </span>
            <span v-else class="ab-help ms-1">
              (next automatic check
              <template v-if="typeof heartbeat.days_until_next_check === 'number' && heartbeat.days_until_next_check > 0">
                in {{ heartbeat.days_until_next_check }} day(s)
              </template>
              <template v-else>due now</template>)
            </span>
          </template>
          <template v-else>
            <strong>Heartbeat has not run yet.</strong>
            Click "Verify now" to run the first phone-home and bind this license to this site.
          </template>
        </div>
        <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost"
          :disabled="heartbeatBusy" @click="runHeartbeatNow">
          <span v-if="heartbeatBusy">Verifying…</span>
          <span v-else>Verify now</span>
        </button>
      </div>
    </div>

    <!-- How updates work -->
    <div class="ab-section">
      <div class="ab-section__head">How updates work</div>
      <div class="ab-section__body">
        <ul class="ab-help mb-0 ps-3">
          <li><strong>Free package:</strong> when a new version is published, Joomla's native update notice appears in System → Update → Extensions.</li>
          <li><strong>Pro package:</strong> new versions are delivered through the Lemon Squeezy "My Orders" portal and you receive an e-mail for each release. Download the new ZIP and install via System → Install → Extensions — settings and license are preserved.</li>
          <li><strong>After expiry:</strong> installed features keep working. An expired license only pauses access to new updates and support until you renew.</li>
        </ul>
        <p class="ab-help mt-3 mb-0">
          Don't have a license yet? Get one from
          <a href="https://aiboostnow.com/pricing" target="_blank" rel="noopener">aiboostnow.com/pricing</a>.
        </p>
      </div>
    </div>

  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'
import PageHeader from './components/PageHeader.vue'

const CORE_SKU = 'bundle'
const CORE_LABEL = 'AI Boost'

const INTEGRATION_LABELS = {
  int_falang: 'AI Boost for Multilang',
  int_yootheme: 'AI Boost for YOOtheme',
}

function emptyCore() {
  return { sku: CORE_SKU, label: CORE_LABEL, key: '', status: 'not_licensed', message: '', expires_at: null, activations_remaining: null, busy: false }
}

export default {
  name: 'LicensesPage',

  components: { PageHeader },

  setup() {
    const core = ref(emptyCore())
    const integrations = ref([])
    const loadError = ref('')

    const boot = window.aiBoostBootstrap || {}
    const heartbeat = ref(boot.licenseHeartbeat || {})
    const heartbeatBusy = ref(false)
    const proActivated = ref(!!(boot.license && boot.license.proActivated))
    const proActivatedAt = ref((boot.license && boot.license.proActivatedAt) || null)
    const ajaxBase = boot.ajaxBase || 'index.php?option=com_aiboost'
    const token = boot.csrfToken || boot.tokenName || ''

    function applyStateToRow(target, s) {
      target.key = s.key || ''
      target.status = s.status || 'not_licensed'
      target.message = s.message || ''
      target.expires_at = s.expires_at || null
      target.activations_remaining = typeof s.activations_remaining === 'number' ? s.activations_remaining : null
      target.busy = false
    }

    async function loadStates() {
      try {
        const url = `${ajaxBase}&task=settings.licenseStateGet&format=json&${token}=1`
        const res = await fetch(url, { credentials: 'same-origin' })
        const data = await res.json()
        if (!data || data.success !== true) { loadError.value = (data && data.message) || 'Could not load license states.'; return }
        const states = data.states || {}
        const next = emptyCore()
        if (states[CORE_SKU]) applyStateToRow(next, states[CORE_SKU])
        core.value = next

        const LEGACY_CORE = new Set([CORE_SKU, 'schema', 'og', 'hreflang', 'code', 'aeo'])
        const rows = new Map()
        const emptyRow = (sku, label) => ({ sku, label: label || INTEGRATION_LABELS[sku] || sku, key: '', status: 'not_licensed', message: '', expires_at: null, activations_remaining: null, busy: false })
        for (const it of (data.integrations || [])) {
          if (!it || !it.sku || LEGACY_CORE.has(it.sku)) continue
          rows.set(it.sku, emptyRow(it.sku, it.label))
        }
        for (const sku of Object.keys(states)) {
          if (LEGACY_CORE.has(sku)) continue
          const s = states[sku] || {}
          const row = rows.get(sku) || emptyRow(sku, s.label)
          applyStateToRow(row, s)
          rows.set(sku, row)
        }
        integrations.value = Array.from(rows.values())
      } catch (e) { loadError.value = String(e) }
    }

    async function verify(row) {
      row.busy = true; row.message = ''
      try {
        const formData = new FormData()
        formData.append('sku', row.sku)
        formData.append('license_key', row.key.trim())
        formData.append(token, '1')
        const url = `${ajaxBase}&task=settings.verifyLicense&format=json`
        const res = await fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
        const data = await res.json()
        if (!data || data.success !== true) { row.status = 'invalid'; row.message = (data && data.message) || 'Verify failed.'; return }
        const s = data.state || {}
        row.status = s.status || 'invalid'
        row.message = s.message || ''
        row.expires_at = s.expires_at || null
        row.activations_remaining = typeof s.activations_remaining === 'number' ? s.activations_remaining : null
        setTimeout(() => { window.location.reload() }, 600)
      } catch (e) { row.status = 'invalid'; row.message = String(e) }
      finally { row.busy = false }
    }

    async function deactivate(row) {
      if (!confirm(`Release the ${row.label} license from this site?`)) return
      row.busy = true
      try {
        const formData = new FormData()
        formData.append('sku', row.sku)
        formData.append(token, '1')
        const url = `${ajaxBase}&task=settings.deactivateLicense&format=json`
        const res = await fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
        const data = await res.json()
        if (data && data.success) {
          row.status = 'deactivated'; row.message = data.message || 'License released.'; row.expires_at = null
          setTimeout(() => { window.location.reload() }, 600)
        } else { row.message = (data && data.message) || 'Deactivate failed.' }
      } catch (e) { row.message = String(e) }
      finally { row.busy = false }
    }

    function badgeClass(status) {
      switch (status) {
        case 'active':        return 'ab-badge--success'
        case 'expired':       return 'ab-badge--warning'
        case 'limit_reached': return 'ab-badge--warning'
        case 'deactivated':   return 'ab-badge--muted'
        case 'invalid':       return 'ab-badge--danger'
        default:              return 'ab-badge--muted'
      }
    }
    function statusLabel(status) {
      switch (status) {
        case 'active':        return 'Active'
        case 'expired':       return 'Expired'
        case 'limit_reached': return 'Limit reached'
        case 'deactivated':   return 'Deactivated'
        case 'invalid':       return 'Invalid'
        default:              return 'Not licensed'
      }
    }
    function formatDate(iso) {
      try { return new Date(iso).toLocaleDateString() } catch { return iso }
    }

    onMounted(loadStates)

    function formatRelative(iso) {
      if (!iso) return ''
      const then = new Date(iso).getTime()
      if (isNaN(then)) return iso
      const diffSec = Math.max(0, Math.floor((Date.now() - then) / 1000))
      if (diffSec < 60) return 'just now'
      if (diffSec < 3600) return `${Math.floor(diffSec / 60)} min ago`
      if (diffSec < 86400) return `${Math.floor(diffSec / 3600)} h ago`
      return `${Math.floor(diffSec / 86400)} days ago`
    }

    const heartbeatAlertClass = computed(() => {
      const v = heartbeat.value.last_verdict
      if (v === 'domain_mismatch' || heartbeat.value.domain_collision) return 'ab-alert--danger'
      if (v && v !== 'ok') return 'ab-alert--warning'
      return 'ab-alert--info'
    })

    async function runHeartbeatNow() {
      heartbeatBusy.value = true
      try {
        const formData = new FormData()
        formData.append(token, '1')
        const url = `${ajaxBase}&task=settings.heartbeatRun&format=json`
        const res = await fetch(url, { method: 'POST', body: formData, credentials: 'same-origin' })
        const data = await res.json()
        if (data && data.success && data.heartbeat) heartbeat.value = data.heartbeat
      } catch (_e) { /* non-fatal */ }
      finally { heartbeatBusy.value = false }
    }

    return {
      core, integrations, loadError,
      verify, deactivate, badgeClass, statusLabel, formatDate,
      heartbeat, heartbeatBusy, heartbeatAlertClass, formatRelative, runHeartbeatNow,
      proActivated, proActivatedAt,
    }
  },
}
</script>

<style scoped>
.ab-licenses-page { }

/* Responsive license row */
.ab-license-row {
  display: grid;
  grid-template-columns: 1fr 280px 130px 100px auto;
  gap: var(--ab-space-3);
  align-items: start;
  padding: var(--ab-space-3) 0;
  border-bottom: 1px solid var(--ab-border);
}
.ab-license-row:last-child { border-bottom: 0; }
.ab-input--mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .82rem; }

@media (max-width: 900px) {
  .ab-license-row { grid-template-columns: 1fr 1fr; }
  .ab-license-row__product { grid-column: 1 / -1; }
}
</style>
