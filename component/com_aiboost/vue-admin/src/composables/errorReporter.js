/**
 * AI Boost — Global frontend error reporter (Task #513).
 *
 * Captures:
 *   - Vue render / lifecycle exceptions (wired in main.js via
 *     app.config.errorHandler)
 *   - Uncaught synchronous errors    (window 'error' event)
 *   - Unhandled promise rejections   (window 'unhandledrejection' event)
 *
 * Each captured event is:
 *   1. Surfaced to the admin via a toast (toast.error)
 *   2. Forwarded to the server via errors.logClientError → Logger →
 *      #__aiboost_error_log (source = "frontend:<tag>")
 *
 * Client-side rate limit: each unique error key is reported at most
 * once per WINDOW_MS. The key is built from severity + source + the
 * first 100 chars of the message + a short hash of the stack so a
 * runaway render loop or repeated AJAX failure does not flood the
 * server log.
 */

import { postWithCsrf, makeAdminUrl } from '../api.js'
import { toast } from './useToast.js'
import { isOffline } from './useOnlineStatus.js'

const WINDOW_MS = 30_000
const MAX_KEYS  = 200

// Hard cap on total network sends per SEND_WINDOW_MS. Even if every
// error has a unique key (e.g. a buggy third-party widget churning
// stack traces), we never emit more than SEND_MAX requests per minute
// to errors.logClientError. Once the cap is hit, subsequent send()
// calls in the window are silently dropped.
const SEND_WINDOW_MS = 60_000
const SEND_MAX       = 20

const seen = new Map() // key -> last-sent timestamp (insertion-ordered)

let sendWindowStart = 0
let sendWindowCount = 0

function djb2 (str) {
  let h = 5381
  for (let i = 0; i < str.length; i++) h = ((h << 5) + h + str.charCodeAt(i)) | 0
  return (h >>> 0).toString(16)
}

function buildKey (source, message, stack) {
  const m = String(message || '').slice(0, 100)
  const s = stack ? djb2(String(stack).slice(0, 500)) : '0'
  return source + '|' + m + '|' + s
}

function rateLimited (key) {
  const now = Date.now()
  // Evict strictly by oldest timestamp first. Map iteration is in
  // insertion order, and seen.set(key, now) on a hit re-inserts the
  // key at the end below — so the first entries we iterate are also
  // the oldest by timestamp. This avoids prematurely resetting hot
  // keys during a large burst (Task #521).
  if (seen.size > MAX_KEYS) {
    const target = Math.floor(MAX_KEYS / 2)
    for (const k of seen.keys()) {
      if (seen.size <= target) break
      seen.delete(k)
    }
  }
  const last = seen.get(key)
  if (last !== undefined && now - last < WINDOW_MS) return true
  // Delete-then-set so the key moves to the end of insertion order
  // and the oldest-first eviction above stays correct.
  seen.delete(key)
  seen.set(key, now)
  return false
}

function currentRoute () {
  try { return String(window.location.hash || '').slice(0, 200) }
  catch (_e) { return '' }
}

/**
 * Send an error event to the server. Best-effort — failures are
 * swallowed so the reporter never causes another error.
 */
async function send (payload) {
  const now = Date.now()
  if (now - sendWindowStart >= SEND_WINDOW_MS) {
    sendWindowStart = now
    sendWindowCount = 0
  }
  if (sendWindowCount >= SEND_MAX) return
  sendWindowCount++
  try {
    const fd = new FormData()
    fd.append('message',   payload.message || '')
    if (payload.stack)   fd.append('stack',   payload.stack)
    if (payload.source)  fd.append('source',  payload.source)
    fd.append('route',     payload.route || currentRoute())
    fd.append('userAgent', (navigator && navigator.userAgent) || '')
    if (payload.context) fd.append('context', JSON.stringify(payload.context))
    await postWithCsrf(makeAdminUrl('errors.logClientError'), fd)
  } catch (_e) { /* swallow — see comment above */ }
}

/**
 * Report a single error. Honours the rate limiter and shows a toast.
 *
 * @param {object} opts
 * @param {string} opts.source        short tag (vue|window|promise|ajax|js)
 * @param {string} opts.message       short user-visible message
 * @param {string} [opts.stack]
 * @param {object} [opts.context]
 * @param {boolean}[opts.toast=true]  set false to suppress the toast
 */
export function reportError (opts) {
  const source  = String(opts.source  || 'js')
  const message = String(opts.message || 'Unknown error')
  const stack   = opts.stack ? String(opts.stack) : ''
  const key     = buildKey(source, message, stack)

  // Always show the toast on first hit, but suppress duplicates while
  // the rate limit is active so the screen doesn't fill with copies.
  const limited = rateLimited(key)
  // While the browser reports offline, suppress per-error toasts so the
  // single sticky "You appear to be offline" notice (see
  // useOnlineStatus.js) isn't drowned out by individual failures.
  if (!limited && opts.toast !== false && !isOffline()) {
    toast.error(message)
  }
  if (limited) return
  send({ source, message, stack, context: opts.context || null, route: opts.route })
}

let installed = false

/**
 * Install the window-level handlers. Idempotent — main.js can call
 * this once at boot.
 */
export function installErrorReporter () {
  if (installed) return
  installed = true

  window.addEventListener('error', (ev) => {
    const err = ev && ev.error
    reportError({
      source:  'window',
      message: (err && err.message) || ev.message || 'Uncaught error',
      stack:   (err && err.stack)   || '',
      context: {
        filename: ev.filename || null,
        lineno:   ev.lineno   || null,
        colno:    ev.colno    || null,
      },
    })
  })

  window.addEventListener('unhandledrejection', (ev) => {
    const reason = ev && ev.reason
    let message  = 'Unhandled promise rejection'
    let stack    = ''
    if (reason instanceof Error) {
      message = reason.message || message
      stack   = reason.stack || ''
    } else if (reason != null) {
      try { message = typeof reason === 'string' ? reason : JSON.stringify(reason).slice(0, 400) }
      catch (_e) { message = String(reason) }
    }
    reportError({ source: 'promise', message, stack })
  })
}

/**
 * Vue.config.errorHandler signature: (err, instance, info)
 */
export function vueErrorHandler (err, instance, info) {
  const componentName =
    (instance && (instance.$options?.name || instance.type?.name)) || 'unknown'
  reportError({
    source:  'vue',
    message: (err && err.message) || String(err) || 'Vue error',
    stack:   (err && err.stack)   || '',
    context: { component: componentName, info: info || '' },
  })
}
