/**
 * useLegacyGlobals — lazy-load PHP-injected window globals for SPA routes.
 *
 * During the transition from per-view PHP templates to a real Vue SPA, each
 * existing Vue component (DashboardApp, HealthApp, etc.) still reads its data
 * from a window.aiBoost{X} global that its PHP template inlines.
 *
 * When the SPA renders that component from a hash route (e.g. #/health), the
 * legacy template is *not* loaded, so the global is absent. This helper
 * fetches the legacy view in tmpl=component mode, extracts every
 * `window.aiBoost*` assignment from <script> blocks, and runs them in the
 * current page so the component finds its data on mount.
 *
 * Once route-specific AJAX endpoints exist (task #337), this can be replaced
 * with proper JSON fetches.
 */

const cache = new Map()
const resolved = new Set()

const GLOBAL_RE = /window\.aiBoost\w+\s*=/

/**
 * True once ensureLegacyGlobals() has fully materialised the globals for this
 * URL (an in-flight fetch does not count). AppShell uses this to skip its
 * loading state on cache hits, so a route change between already-loaded views
 * never unmounts <router-view> — which would discard the routed component's
 * local state (e.g. unsaved Settings edits).
 */
export function isLegacyGlobalsReady(legacyUrl) {
  return !legacyUrl || resolved.has(legacyUrl)
}

/**
 * Drop the cached globals for a route so the next visit re-fetches fresh data
 * (AppShell sees a cache miss → reloads + remounts the routed component). Used
 * after a plugin/integration toggle so other SPA screens (Dashboard
 * notifications, the Integrations cards) reflect the new state WITHOUT a full
 * page reload. With no URL, clears every cached view.
 *
 * NB: never invalidate the Settings view this way — remounting it would discard
 * unsaved edits (see isLegacyGlobalsReady). Pass the specific URLs to refresh.
 */
export function invalidateLegacyGlobals(legacyUrl) {
  if (legacyUrl) {
    cache.delete(legacyUrl)
    resolved.delete(legacyUrl)
  } else {
    cache.clear()
    resolved.clear()
  }
}

function runScript(text) {
  // Wrap in IIFE to keep scope clean; assignment to window.* still leaks out.
  // eslint-disable-next-line no-new-func
  new Function(text)()
}

export async function ensureLegacyGlobals(legacyUrl, opts = {}) {
  if (!legacyUrl) return
  if (cache.has(legacyUrl) && !opts.force) return cache.get(legacyUrl)

  const url = legacyUrl + (legacyUrl.includes('?') ? '&' : '?') + 'tmpl=component'

  const promise = fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status + ' loading ' + legacyUrl)
      return r.text()
    })
    .then(html => {
      const doc = new DOMParser().parseFromString(html, 'text/html')
      doc.querySelectorAll('script').forEach(s => {
        if (s.src) return
        const txt = s.textContent || ''
        if (GLOBAL_RE.test(txt)) {
          try { runScript(txt) }
          catch (e) { console.warn('[AiBoost SPA] Failed running injected script:', e) }
        }
      })
      resolved.add(legacyUrl)
      return true
    })
    .catch(err => {
      cache.delete(legacyUrl)
      throw err
    })

  cache.set(legacyUrl, promise)
  return promise
}
