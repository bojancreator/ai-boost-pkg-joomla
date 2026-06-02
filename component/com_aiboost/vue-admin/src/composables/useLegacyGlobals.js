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

const GLOBAL_RE = /window\.aiBoost\w+\s*=/

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
      return true
    })
    .catch(err => {
      cache.delete(legacyUrl)
      throw err
    })

  cache.set(legacyUrl, promise)
  return promise
}
