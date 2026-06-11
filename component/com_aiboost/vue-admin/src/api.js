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

export function getCsrfTokenName() {
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

/**
 * True when this install has working Pro (perpetual-activation gate).
 *
 * Reads the canonical signal the PHP shell already injects into the bootstrap
 * blob (HtmlView::buildBootstrap → PluginRegistry::isProActive). The admin UI,
 * the settings-save endpoint and the front-end emitters therefore all derive
 * Pro from the SAME source and can never drift. Defaults to false (locked)
 * when the flag is absent, so Free never accidentally shows Pro UI unlocked.
 */
export function isPro() {
  if (typeof window === 'undefined') return false
  const boot = window.aiBoostBootstrap || {}
  return boot.isPro === true
}

/**
 * True when the Pro PACKAGE is installed (pkg_aiboost_pro / any *_pro plugin),
 * regardless of whether a licence key has been activated yet.
 *
 * The admin UI lock (ProGate) keys on THIS, not on isPro(): on a Pro install
 * the user should be able to see and configure Pro features immediately, even
 * before entering a licence — the licence is still enforced at RUNTIME (the
 * front-end emitters check PluginRegistry::hasPro). So a Free build shows the
 * upgrade lock; a Pro build shows the real controls.
 */
export function isProInstalled() {
  if (typeof window === 'undefined') return false
  const boot = window.aiBoostBootstrap || {}
  return boot.isProInstall === true
}

/**
 * Marketing URL the Pro lock / upgrade prompts link to. Matches the
 * "Upgrade license" link used in the Dashboard footer and Licenses page.
 */
export function proUpgradeUrl() {
  return 'https://aiboostnow.com/pricing'
}

/**
 * Build a Joomla admin AJAX URL for the given controller task.
 * e.g. makeAdminUrl('settings.getLanguages') → 'index.php?option=com_aiboost&task=settings.getLanguages&format=json'
 */
export function makeAdminUrl(task) {
  return 'index.php?option=com_aiboost&task=' + task + '&format=json'
}
