/**
 * SPA regression tests — runtime-template ScopeSelector + unsaved-edits loss.
 *
 * Run from component/com_aiboost/vue-admin/:
 *   node src/__tests__/spa-regressions.test.mjs
 *
 * Uses only deps already installed (vite, vue, node:test — no new packages).
 * SFCs are compiled through vite's ssrLoadModule, i.e. the same @vitejs/plugin-vue
 * pipeline the production build uses, then rendered with vue/server-renderer.
 *
 * Covers:
 *   Bug 1 — the Custom Code scope selector was an inline component with a
 *           runtime `template:` string; the shipped bundle uses the
 *           runtime-only Vue build, so it rendered NOTHING in production.
 *           Now a compiled SFC (components/ScopeSelector.vue) that must
 *           produce real markup and keep the same v-model contract.
 *   Bug 2 — switching settings sub-tabs remounted App.vue and discarded
 *           unsaved edits: AppShell flipped its loading v-if on every route
 *           change even on legacy-globals cache hits, and App.vue rebuilds
 *           `s` from window.aiBoostSettings on mount. Now AppShell skips the
 *           loader when isLegacyGlobalsReady(), and App.vue carries a
 *           beforeRouteLeave confirm guard for dirty state.
 */

import { test, after } from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

import { createServer } from 'vite'
import { createSSRApp } from 'vue'
import { renderToString } from 'vue/server-renderer'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

const server = await createServer({
  root,
  logLevel: 'error',
  server: { middlewareMode: true },
  appType: 'custom',
  // SSR-only module loading — skip the client dependency pre-bundling scan
  // (it walks the monorepo and trips over unrelated workspace tsconfigs).
  optimizeDeps: { entries: [], noDiscovery: true },
})
after(() => server.close())

/* ── Bug 1 — ScopeSelector must be a compiled SFC that renders markup ── */

test('ScopeSelector SFC renders the scope radios (all-pages mode)', async () => {
  const { default: ScopeSelector } = await server.ssrLoadModule('/src/components/ScopeSelector.vue')

  // A compiled SFC ships a render function (ssrRender under vite's SSR
  // transform); a runtime template string would be dead under the
  // runtime-only Vue build (the original bug).
  assert.equal(typeof (ScopeSelector.render || ScopeSelector.ssrRender), 'function',
    'SFC must ship a compiled render function')
  assert.equal(ScopeSelector.template, undefined, 'no runtime template string allowed')

  const html = await renderToString(createSSRApp(ScopeSelector, {
    field: 'head',
    s: {},
    menuGroups: [],
    selectedIds: [],
  }))

  assert.match(html, /ab-scope-block/, 'root block class present')
  assert.match(html, /Apply head code to/, 'per-field label present')
  assert.match(html, /All pages/, 'all-pages radio label present')
  assert.match(html, /Specific menu items only/, 'specific radio label present')
  assert.match(html, /id="cc-head-scope-all"[^>]*checked/, 'defaults to all-pages checked')
  assert.doesNotMatch(html, /ab-menu-select/, 'menu select hidden while scope=all')
})

test('ScopeSelector SFC renders the menu multi-select (specific mode)', async () => {
  const { default: ScopeSelector } = await server.ssrLoadModule('/src/components/ScopeSelector.vue')

  const html = await renderToString(createSSRApp(ScopeSelector, {
    field: 'body',
    s: { custom_code_body_scope: 'specific' },
    menuGroups: [
      { type: 'mainmenu', items: [
        { id: 101, title: 'Home', level: 1 },
        { id: 205, title: 'About', level: 2 },
      ] },
    ],
    selectedIds: [101],
  }))

  assert.match(html, /ab-menu-select/, 'multi-select rendered')
  assert.match(html, /label="mainmenu"/, 'optgroup per menutype')
  assert.match(html, /value="101"[^>]*selected/, 'pre-selected menu item keeps selected attr')
  assert.match(html, /About/, 'nested item title rendered')
  assert.match(html, /<strong[^>]*>1<\/strong> item selected\./, 'selection count rendered')
})

test('ScopeSelector writes scope + menu_ids keys and emits update:selectedIds', async () => {
  const { default: ScopeSelector } = await server.ssrLoadModule('/src/components/ScopeSelector.vue')

  // Drive the option methods with a minimal `this` (computed keys precomputed).
  const emitted = []
  const self = { field: 'footer', s: {}, $emit: (...args) => emitted.push(args) }
  self.scopeKey   = ScopeSelector.computed.scopeKey.call(self)
  self.menuIdsKey = ScopeSelector.computed.menuIdsKey.call(self)

  assert.equal(self.scopeKey, 'custom_code_footer_scope')
  assert.equal(self.menuIdsKey, 'custom_code_footer_menu_ids')

  ScopeSelector.methods.onScopeChange.call(self, 'specific')
  assert.equal(self.s.custom_code_footer_scope, 'specific', 'scope written to settings object')

  ScopeSelector.methods.onSelectChange.call(self, {
    target: { selectedOptions: [{ value: '101' }, { value: '205' }] },
  })
  assert.deepEqual(emitted, [['update:selectedIds', [101, 205]]], 'v-model emit fired')
  assert.equal(self.s.custom_code_footer_menu_ids, '[101,205]', 'menu_ids JSON written to settings object')
})

test('CodeTab no longer embeds a runtime template: component', () => {
  const src = readFileSync(path.join(root, 'src/tabs/CodeTab.vue'), 'utf8')
  assert.doesNotMatch(src, /template:\s*`/, 'runtime template string must not return')
  // The per-menu ScopeSelector was removed (Custom Code applies to all pages);
  // CodeTab is still a compiled SFC — it imports ProGate for the Pro gate.
  assert.match(src, /import ProGate from '\.\.\/components\/ProGate\.vue'/, 'uses the compiled SFC')
})

/* ── Bug 2 — cache hits must not unmount; dirty settings must be guarded ── */

test('isLegacyGlobalsReady flips only after the fetch fully resolves', async () => {
  const mod = await server.ssrLoadModule('/src/composables/useLegacyGlobals.js')
  const URL_OK = 'index.php?option=com_aiboost&view=settings'

  // No legacy URL → nothing to load → always "ready" (route mounts directly).
  assert.equal(mod.isLegacyGlobalsReady(''), true)
  assert.equal(mod.isLegacyGlobalsReady(URL_OK), false, 'unknown URL starts not-ready')

  // Stub the browser APIs ensureLegacyGlobals touches.
  const fetchCalls = []
  let resolveFetch
  globalThis.DOMParser = class { parseFromString() { return { querySelectorAll: () => [] } } }
  globalThis.fetch = (url) => {
    fetchCalls.push(url)
    return new Promise((res) => { resolveFetch = res })
  }

  try {
    const p = mod.ensureLegacyGlobals(URL_OK)
    assert.equal(mod.isLegacyGlobalsReady(URL_OK), false, 'in-flight fetch is NOT ready (loader must still show)')

    resolveFetch({ ok: true, text: async () => '<html><head></head><body></body></html>' })
    await p
    assert.equal(mod.isLegacyGlobalsReady(URL_OK), true, 'resolved entry is ready (no loader, no unmount)')

    // Second call is a cache hit — no further network round-trip.
    await mod.ensureLegacyGlobals(URL_OK)
    assert.equal(fetchCalls.length, 1, 'cached entry must not refetch')

    // Failure path: never marked ready, cache cleared so a retry refetches.
    const URL_BAD = 'index.php?option=com_aiboost&view=broken'
    globalThis.fetch = (url) => { fetchCalls.push(url); return Promise.resolve({ ok: false, status: 500 }) }
    await assert.rejects(() => mod.ensureLegacyGlobals(URL_BAD), /HTTP 500/)
    assert.equal(mod.isLegacyGlobalsReady(URL_BAD), false, 'failed load stays not-ready')
    await assert.rejects(() => mod.ensureLegacyGlobals(URL_BAD), /HTTP 500/)
    assert.equal(fetchCalls.filter(u => u.includes('view=broken')).length, 2, 'failure is retryable')
  } finally {
    delete globalThis.fetch
    delete globalThis.DOMParser
  }
})

test('AppShell skips the loading state on legacy-globals cache hits', () => {
  // Tripwire: the loading v-if unmounts <router-view>, so the cache-hit
  // early-return in loadGlobalsForRoute must stay ahead of loading=true.
  const src = readFileSync(path.join(root, 'src/AppShell.vue'), 'utf8')
  const readyCheck = src.indexOf('isLegacyGlobalsReady(meta.legacyUrl)')
  const setLoading = src.indexOf('loading.value = true')
  assert.ok(readyCheck !== -1, 'AppShell must consult isLegacyGlobalsReady')
  assert.ok(setLoading !== -1, 'AppShell still shows the loader on cache misses')
  assert.ok(readyCheck < setLoading, 'cache-hit early return must come before loading=true')
})

test('Settings beforeRouteLeave guards dirty state with a confirm', async () => {
  const { default: SettingsApp } = await server.ssrLoadModule('/src/App.vue')
  const guard = SettingsApp.beforeRouteLeave
  assert.equal(typeof guard, 'function', 'App.vue must define beforeRouteLeave')

  const run = (dirty, confirmAnswer) => {
    const confirms = []
    const nextCalls = []
    globalThis.confirm = (msg) => { confirms.push(msg); return confirmAnswer }
    try {
      guard.call({ dirty }, {}, {}, (...args) => nextCalls.push(args))
    } finally {
      delete globalThis.confirm
    }
    return { confirms, nextCalls }
  }

  // Clean form → navigate freely, no dialog.
  let r = run(false, false)
  assert.equal(r.confirms.length, 0, 'no confirm when not dirty')
  assert.deepEqual(r.nextCalls, [[]], 'navigation allowed')

  // Dirty + user declines → navigation cancelled (edits survive).
  r = run(true, false)
  assert.equal(r.confirms.length, 1, 'confirm shown when dirty')
  assert.match(r.confirms[0], /unsaved changes/i)
  assert.deepEqual(r.nextCalls, [[false]], 'navigation cancelled')

  // Dirty + user accepts → navigation proceeds.
  r = run(true, true)
  assert.deepEqual(r.nextCalls, [[]], 'navigation allowed after explicit discard')
})
