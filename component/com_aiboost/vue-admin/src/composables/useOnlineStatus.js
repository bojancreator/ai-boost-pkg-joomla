/**
 * AI Boost — Offline / online detector (Task #519).
 *
 * Task #513 auto-toasts every failed AJAX call. When the admin's
 * connection drops, every poll (Errors auto-refresh, Health rerun,
 * Settings save, etc.) raises its own "Failed to fetch" toast and
 * floods the screen.
 *
 * This module collapses that noise into one sticky "You appear to be
 * offline" toast driven by `navigator.onLine` + the window
 * 'offline'/'online' events. While offline:
 *
 *   - Per-request error toasts from api.js / errorReporter.js are
 *     suppressed (callers check `isOffline()` before calling
 *     `toast.error`).
 *   - The sticky offline toast stays on screen until connectivity
 *     returns; clicking the dismiss button hides it but the flag
 *     stays set so transient toasts remain quiet.
 *
 * When the browser fires 'online' we dismiss the sticky toast and
 * pop a brief "Back online" success toast.
 *
 * Per-page error banners are unaffected — they read their component
 * state, not the toast stack.
 */

import { toast } from './useToast.js'

let installed = false
let offlineToastId = null

function navigatorOnline () {
  try {
    return typeof navigator === 'undefined' || navigator.onLine !== false
  } catch (_e) {
    return true
  }
}

let offlineFlag = !navigatorOnline()

/**
 * @returns {boolean} true while the browser reports no connectivity.
 */
export function isOffline () {
  return offlineFlag === true
}

function showOfflineToast () {
  if (offlineToastId !== null) return
  offlineToastId = toast.warning(
    'You appear to be offline. Changes will resume when the connection returns.',
    { timeoutMs: 0 }
  )
}

function clearOfflineToast () {
  if (offlineToastId !== null) {
    toast.dismiss(offlineToastId)
    offlineToastId = null
  }
}

function handleOffline () {
  offlineFlag = true
  showOfflineToast()
}

function handleOnline () {
  const wasOffline = offlineFlag
  offlineFlag = false
  clearOfflineToast()
  if (wasOffline) {
    toast.success('Back online.', { timeoutMs: 3000 })
  }
}

/**
 * Install window-level 'online' / 'offline' listeners. Idempotent —
 * safe to call once per page-load from main.js.
 */
export function installOnlineStatus () {
  if (installed) return
  installed = true
  if (typeof window === 'undefined') return

  window.addEventListener('offline', handleOffline)
  window.addEventListener('online',  handleOnline)

  // If the page loads while already offline, surface the sticky toast
  // on next tick so ToastStack is mounted first.
  if (!navigatorOnline()) {
    offlineFlag = true
    setTimeout(showOfflineToast, 0)
  }
}
