<template>
  <div class="ab-licenses-page">
    <header class="ab-page-header">
      <h2>License &amp; Updates</h2>
      <p class="text-muted">
        Enter your AI Boost license key to verify your purchase. A license includes
        updates and support for one year. Activation is perpetual: if the license later
        expires, every installed feature keeps working — expiry only pauses updates and support
        until you renew.
        Integration plugins (third-party bridges) are licensed separately and appear below once installed.
      </p>
    </header>

    <div v-if="loadError" class="ab-alert ab-alert--danger">
      <strong>Failed to load:</strong> {{ loadError }}
    </div>

    <!-- ───── Perpetual activation status ───── -->
    <div v-if="proActivated" class="ab-alert ab-alert--success" style="margin-top: 12px;">
      <strong>License activated&nbsp;✓</strong>
      <span v-if="proActivatedAt">on {{ formatDate(proActivatedAt) }}</span>.
      If your license later expires, installed features keep working; renewal restores updates &amp; support.
    </div>

    <!-- ───── Core: AI Boost (single key) ───── -->
    <section v-if="!loadError" class="ab-license-section">
      <h3 class="ab-section-title">AI Boost</h3>
      <table class="ab-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>License key</th>
            <th>Status</th>
            <th>Expires</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <strong>AI Boost</strong>
              <div class="small text-muted">Updates and support</div>
            </td>
            <td>
              <input
                type="text"
                class="ab-input ab-input--mono"
                v-model="core.key"
                placeholder="Paste your license key"
                :disabled="core.busy"
                style="min-width: 260px;"
              />
            </td>
            <td>
              <span class="ab-badge" :class="badgeClass(core.status)">
                {{ statusLabel(core.status) }}
              </span>
              <div v-if="core.message" class="small text-muted">{{ core.message }}</div>
            </td>
            <td>
              <span v-if="core.expires_at" class="small">{{ formatDate(core.expires_at) }}</span>
              <span v-else class="small text-muted">—</span>
            </td>
            <td>
              <button
                type="button"
                class="ab-btn ab-btn--sm ab-btn--primary"
                :disabled="core.busy || !core.key.trim()"
                @click="verify(core)"
              >
                <span v-if="core.busy">Verifying…</span>
                <span v-else>Verify</span>
              </button>
              <button
                v-if="core.status === 'active'"
                type="button"
                class="ab-btn ab-btn--sm ab-btn--ghost"
                :disabled="core.busy"
                @click="deactivate(core)"
                style="margin-left: 6px;"
              >
                Release
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- ───── Integration plugins (only if registered) ───── -->
    <section v-if="!loadError && integrations.length" class="ab-license-section" style="margin-top: 32px;">
      <h3 class="ab-section-title">Integration plugins</h3>
      <p class="small text-muted" style="margin-top: -4px;">
        Each installed integration (Multilang, YOOtheme, …) is sold separately and has its own license key.
      </p>
      <table class="ab-table">
        <thead>
          <tr>
            <th>Integration</th>
            <th>License key</th>
            <th>Status</th>
            <th>Expires</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in integrations" :key="row.sku">
            <td>
              <strong>{{ row.label }}</strong>
              <div class="small text-muted">{{ row.sku }}</div>
            </td>
            <td>
              <input
                type="text"
                class="ab-input ab-input--mono"
                v-model="row.key"
                placeholder="Paste your license key"
                :disabled="row.busy"
                style="min-width: 240px;"
              />
            </td>
            <td>
              <span class="ab-badge" :class="badgeClass(row.status)">
                {{ statusLabel(row.status) }}
              </span>
              <div v-if="row.message" class="small text-muted">{{ row.message }}</div>
            </td>
            <td>
              <span v-if="row.expires_at" class="small">{{ formatDate(row.expires_at) }}</span>
              <span v-else class="small text-muted">—</span>
            </td>
            <td>
              <button
                type="button"
                class="ab-btn ab-btn--sm ab-btn--primary"
                :disabled="row.busy || !row.key.trim()"
                @click="verify(row)"
              >
                <span v-if="row.busy">Verifying…</span>
                <span v-else>Verify</span>
              </button>
              <button
                v-if="row.status === 'active'"
                type="button"
                class="ab-btn ab-btn--sm ab-btn--ghost"
                :disabled="row.busy"
                @click="deactivate(row)"
                style="margin-left: 6px;"
              >
                Release
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- ───── Heartbeat status ───── -->
    <div class="ab-alert" :class="heartbeatAlertClass" style="margin-top: 24px;">
      <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 240px;">
          <template v-if="heartbeat.last_checked_at">
            <strong>Last verified:</strong> {{ formatRelative(heartbeat.last_checked_at) }}
            <span v-if="heartbeat.last_verdict === 'domain_mismatch' || heartbeat.domain_collision">
              — <strong>Domain collision detected.</strong>
              {{ heartbeat.message || 'This license has been used on another domain.' }}
            </span>
            <span v-else-if="heartbeat.status === 'expired' || (heartbeat.last_verdict && heartbeat.last_verdict !== 'ok')">
              — <strong>License lapsed.</strong> Installed features keep working; renewing restores updates &amp; support.
            </span>
            <span v-else class="small text-muted" style="margin-left: 6px;">
              (next automatic check
              <template v-if="typeof heartbeat.days_until_next_check === 'number' && heartbeat.days_until_next_check > 0">
                in {{ heartbeat.days_until_next_check }} day(s)
              </template>
              <template v-else>
                due now
              </template>)
            </span>
          </template>
          <template v-else>
            <strong>Heartbeat has not run yet.</strong>
            Click "Verify now" to run the first phone-home and bind this license to this site.
          </template>
        </div>
        <button
          type="button"
          class="ab-btn ab-btn--sm ab-btn--ghost"
          :disabled="heartbeatBusy"
          @click="runHeartbeatNow"
        >
          <span v-if="heartbeatBusy">Verifying…</span>
          <span v-else>Verify now</span>
        </button>
      </div>
    </div>

    <!-- ───── How updates work ───── -->
    <section class="ab-license-section" style="margin-top: 32px;">
      <h3 class="ab-section-title">How updates work</h3>
      <ul class="small text-muted" style="margin: 0; padding-left: 18px;">
        <li>
          <strong>Free package:</strong> when a new version is published, Joomla's native
          update notice appears in System&nbsp;&rarr;&nbsp;Update&nbsp;&rarr;&nbsp;Extensions.
        </li>
        <li>
          <strong>Pro package:</strong> new versions are delivered through the Lemon Squeezy
          "My Orders" portal, and you receive an e-mail notification for each release.
          Download the new ZIP and install it in Joomla's Extension manager
          (System&nbsp;&rarr;&nbsp;Install&nbsp;&rarr;&nbsp;Extensions) — your settings and
          license are preserved on update.
        </li>
        <li>
          <strong>After expiry:</strong> installed features keep working. An expired license
          only pauses access to new updates and support until you renew.
        </li>
      </ul>
    </section>

    <div class="ab-footer-note">
      <p class="small text-muted">
        Don't have a license yet? Get one from
        <a href="https://aiboostnow.com/pricing" target="_blank" rel="noopener">aiboostnow.com/pricing</a>.
        A license includes one year of updates and support for AI Boost. Integration plugins are sold separately.
      </p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue'

const CORE_SKU = 'bundle'
const CORE_LABEL = 'AI Boost'

// Fallback labels if the server omits one; licenseStateGet normally supplies them.
const INTEGRATION_LABELS = {
  int_falang: 'AI Boost for Multilang',
  int_yootheme: 'AI Boost for YOOtheme',
}

function emptyCore() {
  return {
    sku: CORE_SKU,
    label: CORE_LABEL,
    key: '',
    status: 'not_licensed',
    message: '',
    expires_at: null,
    activations_remaining: null,
    busy: false,
  }
}

export default {
  name: 'LicensesPage',

  setup() {
    const core = ref(emptyCore())
    const integrations = ref([])
    const loadError = ref('')

    const boot = window.aiBoostBootstrap || {}
    const heartbeat = ref(boot.licenseHeartbeat || {})
    const heartbeatBusy = ref(false)
    // Task #565 — perpetual activation status (set once, never cleared).
    const proActivated = ref(!!(boot.license && boot.license.proActivated))
    const proActivatedAt = ref((boot.license && boot.license.proActivatedAt) || null)
    const ajaxBase = boot.ajaxBase
      || 'index.php?option=com_aiboost'
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
        if (!data || data.success !== true) {
          loadError.value = (data && data.message) || 'Could not load license states.'
          return
        }
        const states = data.states || {}
        // Core
        const next = emptyCore()
        if (states[CORE_SKU]) applyStateToRow(next, states[CORE_SKU])
        core.value = next

        // Integration rows, merged by SKU from two sources:
        //  1) installed sellable integrations reported by the server, so a row
        //     appears for entering the key BEFORE any verification; and
        //  2) any stored license_state for a non-core SKU, so an already-licensed
        //     integration still shows even if dependency detection misses it.
        // Legacy per-feature SKUs (schema/og/hreflang/code/aeo) are part of the
        // single Pro license and must never appear as separate rows.
        const LEGACY_CORE = new Set([CORE_SKU, 'schema', 'og', 'hreflang', 'code', 'aeo'])
        const rows = new Map()
        const emptyRow = (sku, label) => ({
          sku,
          label: label || INTEGRATION_LABELS[sku] || sku,
          key: '',
          status: 'not_licensed',
          message: '',
          expires_at: null,
          activations_remaining: null,
          busy: false,
        })
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
      } catch (e) {
        loadError.value = String(e)
      }
    }

    async function verify(row) {
      row.busy = true
      row.message = ''
      try {
        const formData = new FormData()
        formData.append('sku', row.sku)
        formData.append('license_key', row.key.trim())
        formData.append(token, '1')
        const url = `${ajaxBase}&task=settings.verifyLicense&format=json`
        const res = await fetch(url, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        })
        const data = await res.json()
        if (!data || data.success !== true) {
          row.status = 'invalid'
          row.message = (data && data.message) || 'Verify failed.'
          return
        }
        const s = data.state || {}
        row.status = s.status || 'invalid'
        row.message = s.message || ''
        row.expires_at = s.expires_at || null
        row.activations_remaining = typeof s.activations_remaining === 'number' ? s.activations_remaining : null
        // Reload after verification so the updated license and heartbeat state
        // is reflected everywhere without the user having to refresh manually.
        setTimeout(() => { window.location.reload() }, 600)
      } catch (e) {
        row.status = 'invalid'
        row.message = String(e)
      } finally {
        row.busy = false
      }
    }

    async function deactivate(row) {
      if (!confirm(`Release the ${row.label} license from this site?`)) return
      row.busy = true
      try {
        const formData = new FormData()
        formData.append('sku', row.sku)
        formData.append(token, '1')
        const url = `${ajaxBase}&task=settings.deactivateLicense&format=json`
        const res = await fetch(url, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        })
        const data = await res.json()
        if (data && data.success) {
          row.status = 'deactivated'
          row.message = data.message || 'License released.'
          row.expires_at = null
          // Reload after release so license and heartbeat state refreshes
          // without requiring a manual page refresh.
          setTimeout(() => { window.location.reload() }, 600)
        } else {
          row.message = (data && data.message) || 'Deactivate failed.'
        }
      } catch (e) {
        row.message = String(e)
      } finally {
        row.busy = false
      }
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
      try { return new Date(iso).toLocaleDateString() }
      catch { return iso }
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
      // Task #565 — Pro is never relocked, so a lapsed licence is at most a
      // (warning) renewal nudge, never a danger. Domain collision stays danger.
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
        if (data && data.success && data.heartbeat) {
          heartbeat.value = data.heartbeat
        }
      } catch (e) {
        // Non-fatal — alert stays on previous state.
      } finally {
        heartbeatBusy.value = false
      }
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
.ab-licenses-page { padding: 16px 0; }
.ab-page-header { margin-bottom: 20px; }
.ab-page-header h2 { margin: 0 0 8px; }
.ab-license-section { margin-top: 8px; }
.ab-section-title { margin: 0 0 8px; font-size: 16px; font-weight: 600; }
.ab-table { width: 100%; border-collapse: collapse; }
.ab-table th, .ab-table td {
  padding: 12px 8px;
  border-bottom: 1px solid var(--border-color, #e5e7eb);
  text-align: left;
  vertical-align: top;
}
.ab-table th { font-weight: 600; }
.ab-input--mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; }
.ab-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}
.ab-badge--success { background: #dcfce7; color: #15803d; }
.ab-badge--warning { background: #fef3c7; color: #92400e; }
.ab-badge--danger  { background: #fee2e2; color: #991b1b; }
.ab-badge--muted   { background: #e5e7eb; color: #4b5563; }
.ab-footer-note { margin-top: 24px; }
code { background: #f3f4f6; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
</style>
