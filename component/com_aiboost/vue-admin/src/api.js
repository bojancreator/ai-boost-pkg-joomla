/**
 * AI Boost Vue Admin — API helpers
 *
 * CSRF rules:
 *   * Joomla's CSRF token name is a random string per session, exposed as
 *     window.aiBoostToken (legacy) or window.aiBoostBootstrap.tokenName (SPA).
 *   * For POST/PUT/DELETE we always append `<tokenName>=1` to the body.
 *
 * Toast wiring (Task #513): postWithCsrf surfaces transport failures and
 * `success:false` AJAX responses to the global ToastStack so admins see
 * the error even after navigating to another tab. The exception path
 * (HTTP failure, network error) is also forwarded to the server error
 * log with source = "frontend:ajax". The original error is re-thrown so
 * existing per-page error banners keep working unchanged.
 */

import { toast } from './composables/useToast.js'
import { isOffline } from './composables/useOnlineStatus.js'

function getCsrfTokenName() {
  if (typeof window === 'undefined') return ''
  const boot = window.aiBoostBootstrap || {}
  return boot.tokenName || window.aiBoostToken || ''
}

/**
 * POST helper that automatically attaches the Joomla CSRF token.
 *
 * @param {string} url
 * @param {object|FormData|URLSearchParams|null} body
 * @param {{headers?:object}} [opts]
 * @returns {Promise<any>} parsed JSON response
 */
export async function postWithCsrf(url, body = null, opts = {}) {
  const token = getCsrfTokenName()
  let payload

  if (body instanceof FormData) {
    payload = body
    if (token && !payload.has(token)) payload.append(token, '1')
  } else if (body instanceof URLSearchParams) {
    payload = body
    if (token && !payload.has(token)) payload.append(token, '1')
  } else if (body && typeof body === 'object') {
    payload = new FormData()
    for (const [k, v] of Object.entries(body)) {
      const val = typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v ?? '')
      payload.append(k, val)
    }
    if (token) payload.append(token, '1')
  } else {
    payload = new FormData()
    if (token) payload.append(token, '1')
  }

  const headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, opts.headers || {})

  // Suppress toast/server-report for noisy or self-referential endpoints
  // (the errors.logClientError endpoint must never feed itself, and
  // errors.* polling shouldn't toast on transient failures).
  const silent = opts.silent === true || /task=errors\.logClientError/.test(url)

  let res
  try {
    res = await fetch(url, { method: 'POST', body: payload, headers, credentials: 'same-origin' })
  } catch (e) {
    if (!silent) reportAjaxFailure(url, e && e.message ? e.message : String(e))
    throw e
  }
  if (!res.ok) {
    const err = new Error(`HTTP ${res.status}`)
    if (!silent) reportAjaxFailure(url, err.message)
    throw err
  }
  // Joomla sometimes returns valid JSON with Content-Type: text/html (when
  // the controller's setHeader() is too late). Try to parse as JSON first
  // regardless of the header, then fall back to raw text.
  const text = await res.text()
  const trimmed = text.trim()
  let parsed = null
  if (trimmed.startsWith('{') || trimmed.startsWith('[')) {
    try { parsed = JSON.parse(trimmed) } catch (_e) { /* fall through */ }
  }
  if (parsed !== null) {
    // Auto-surface controllers that signal failure via success:false so
    // tab-changes don't swallow the message.
    if (!silent && parsed && typeof parsed === 'object'
        && Object.prototype.hasOwnProperty.call(parsed, 'success')
        && parsed.success === false) {
      const msg = (parsed.message || 'Action failed.').toString()
      toast.error(msg)
    }
    return parsed
  }
  return text
}

/**
 * Lazy-import the reporter to avoid a circular module dependency
 * (errorReporter.js imports api.js). Best-effort — failures are
 * swallowed so a logging failure never becomes a second error.
 */
function reportAjaxFailure (url, message) {
  try {
    // While offline, the single sticky offline toast covers all failed
    // requests — don't pile per-request toasts on top of it.
    if (!isOffline()) {
      toast.error(message + ' — ' + shortenUrl(url))
    }
    import('./composables/errorReporter.js').then(m => {
      m.reportError({
        source:  'ajax',
        message: message + ' [' + shortenUrl(url) + ']',
        toast:   false,
      })
    }).catch(() => { /* ignore */ })
  } catch (_e) { /* ignore */ }
}

function shortenUrl (url) {
  try {
    const m = /[?&]task=([^&]+)/.exec(String(url))
    return m ? m[1] : String(url).slice(0, 60)
  } catch (_e) { return '' }
}

export async function saveSettings(partialSettings, translations = null) {
  const token   = getCsrfTokenName()
  const saveUrl = window.aiBoostSaveUrl || (window.aiBoostBootstrap && window.aiBoostBootstrap.urls && window.aiBoostBootstrap.urls.settingsSave)

  if (!token || !saveUrl) {
    throw new Error('Missing save configuration. Check that HtmlView injects the CSRF token and the settings save URL.')
  }

  const all = { ...(window.aiBoostSettings || {}), ...partialSettings }

  const formData = new FormData()
  formData.append(token, '1')

  for (const [key, value] of Object.entries(all)) {
    const v =
      typeof value === 'object' && value !== null
        ? JSON.stringify(value)
        : String(value ?? '')
    formData.append(key, v)
  }

  if (translations && typeof translations === 'object') {
    formData.append('translations', JSON.stringify(translations))
  }

  const res = await fetch(saveUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
  if (!res.ok) throw new Error(`HTTP ${res.status}`)

  const data = await res.json()

  if (data.success) {
    window.aiBoostSettings = { ...(window.aiBoostSettings || {}), ...partialSettings }
  }

  return data
}

export function getSettings() {
  return window.aiBoostSettings || {}
}

export function isPro() {
  // v0.55.2 — primary source of truth is the server-computed
  // `window.aiBoostBootstrap.isPro` flag (HtmlView::buildBootstrap walks
  // `license_state[*]` and applies `dev_license_preview`). It's always
  // populated on first page load, regardless of which route is mounted.
  //
  // The legacy `window.aiBoostSettings` fallback is only populated by
  // routes that declare `meta.legacyUrl` (Dashboard, Settings, Health).
  // The new page-level <ProGate> wrappers mount on routes like
  // /redirects, /urlchecker, /licenses, /analyzers, /integrations which
  // do NOT load aiBoostSettings — so reading license_tier from there
  // always returned undefined → every page rendered LOCKED even with a
  // verified Pro license. That was the v0.55.0/v0.55.1 regression.
  const boot = window.aiBoostBootstrap || {}
  if (boot.isPro === true) return true
  if (boot.license && boot.license.isPro === true) return true

  const s    = window.aiBoostSettings || {}
  const tier = (s.license_tier || '').toLowerCase()
  if (tier === 'pro' || tier === 'developer' || tier === 'agency') return true
  return String(s.dev_license_preview) === '1'
}

/**
 * Build a Joomla admin AJAX URL for the given controller task.
 * e.g. makeAdminUrl('settings.getLanguages') → 'index.php?option=com_aiboost&task=settings.getLanguages&format=json'
 */
export function makeAdminUrl(task) {
  return 'index.php?option=com_aiboost&task=' + task + '&format=json'
}
