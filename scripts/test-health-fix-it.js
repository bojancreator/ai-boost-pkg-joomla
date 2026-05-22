#!/usr/bin/env node
/**
 * AI Boost for Joomla — Health "Fix It" button end-to-end test
 *
 * TWO-PHASE verification on staging:
 *
 *   PHASE A — TRUE UI CLICK PATH:
 *     1. Login to Joomla admin
 *     2. Open Health page
 *     3. Enumerate every rendered Fix It <a> link (fix_url + fix_actions[])
 *     4. Capture href, CLICK it (not synthesise), then on the Settings page:
 *          a. Correct tab is active (label matches)
 *          b. Target field has [data-ab-field="<field>"]  (REQUIRED)
 *          c. Element receives .ab-field-highlight class (flash)
 *          d. Element is scrolled into the viewport (block:center)
 *
 *   PHASE B — SYNTHETIC COVERAGE:
 *     For every (tab, field) pair in the AUTO-EXTRACTED registry (parsed
 *     from HealthCheckService.php at runtime) not already covered by Phase A,
 *     navigate directly and run the same assertions. This catches drift on
 *     passing checks whose Fix It button is not currently rendered.
 *
 * GUARD: the registry is built from settingsUrl(...) calls in the PHP
 *        source PLUS the BLOCKABLE_SCRAPERS array. If parsing yields zero
 *        entries (e.g. the file moved or settingsUrl was renamed), the
 *        script aborts so coverage cannot silently shrink.
 *
 * Usage:
 *   node scripts/test-health-fix-it.js [--visible]
 *
 * Required env vars (same as install-to-staging.py):
 *   STAGING_URL          — Joomla admin login URL (may include Admin Tools secret path)
 *   STAGING_ADMIN_USER   — Joomla admin username
 *   STAGING_ADMIN_PASS   — Joomla admin password
 *
 * Options:
 *   --visible   Open a real browser window (default: headless)
 */

import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
import { readFileSync } from 'fs'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'

const require    = createRequire(import.meta.url)
const { chromium } = require('playwright')

const __dirname    = dirname(fileURLToPath(import.meta.url))
const PROJECT_ROOT = resolve(__dirname, '..')
const HEALTH_SVC   = resolve(PROJECT_ROOT, 'component/lib/src/HealthCheckService.php')

// ── Config ───────────────────────────────────────────────────────────────────

const STAGING_URL = process.env.STAGING_URL
const ADMIN_USER  = process.env.STAGING_ADMIN_USER
const ADMIN_PASS  = process.env.STAGING_ADMIN_PASS
const HEADLESS    = !process.argv.includes('--visible')

if (!STAGING_URL || !ADMIN_USER || !ADMIN_PASS) {
  console.error('❌  Missing required env vars: STAGING_URL, STAGING_ADMIN_USER, STAGING_ADMIN_PASS')
  process.exit(1)
}

const parsed = new NodeURL(STAGING_URL)
const BASE   = `${parsed.protocol}//${parsed.host}`
const ADMIN  = `${BASE}/administrator/index.php`

const SETTINGS_HYDRATE_MS   = 800
const HIGHLIGHT_MAX_WAIT_MS = 2_500

// ── Tab label map (matches App.vue tabs array) ───────────────────────────────
const TAB_LABELS = {
  general:   'General',
  org:       'Organization',
  schema:    'Schema.org',
  sitemap:   'Sitemap',
  social:    'Social & Meta',
  analytics: 'Analytics',
  aeo:       'AEO',
  code:      'Custom Code',
  debug:     'Debug',
}

// ── Build canonical registry from PHP source of truth ────────────────────────
/**
 * Parses every settingsUrl('tab-X-btn'|'tab', 'field') call in the file plus
 * the BLOCKABLE_SCRAPERS array used in infoRobotsBlockedScrapers(). Returns a
 * Map keyed by `${tab}::${field}`.
 */
function buildCanonicalRegistry () {
  let src
  try {
    src = readFileSync(HEALTH_SVC, 'utf8')
  } catch (e) {
    throw new Error(`Cannot read HealthCheckService.php at ${HEALTH_SVC}: ${e.message}`)
  }

  const out = new Map()

  // settingsUrl('tab-X-btn'|'X', 'field')
  const reCall = /settingsUrl\(\s*'(tab-([a-z]+)-btn|[a-z]+)'\s*,\s*'([a-z0-9_]+)'\s*\)/g
  let m
  while ((m = reCall.exec(src)) !== null) {
    const tab   = m[2] || m[1]
    const field = m[3]
    if (!field) continue
    out.set(`${tab}::${field}`, { tab, field, source: 'settingsUrl' })
  }

  // BLOCKABLE_SCRAPERS: array of 'scraper_xxx' keys with tab 'aeo'
  const reScrapers = /'(scraper_[a-z0-9]+)'\s*=>/g
  while ((m = reScrapers.exec(src)) !== null) {
    const field = m[1]
    out.set(`aeo::${field}`, { tab: 'aeo', field, source: 'BLOCKABLE_SCRAPERS' })
  }

  if (out.size === 0) {
    throw new Error(
      'Registry parser produced 0 entries from HealthCheckService.php — ' +
      'settingsUrl() or BLOCKABLE_SCRAPERS may have been renamed. ' +
      'Fix the parser before relying on this test for coverage.'
    )
  }
  if (out.size < 25) {
    throw new Error(
      `Registry parser produced only ${out.size} entries (< 25 expected). ` +
      'Coverage may have silently shrunk — investigate HealthCheckService.php.'
    )
  }

  return out
}

// ── Login ─────────────────────────────────────────────────────────────────────

async function login (page) {
  console.log('🔐  Navigating to login page…')
  await page.goto(STAGING_URL, { waitUntil: 'domcontentloaded', timeout: 30_000 })

  const hasForm = await page.locator('input[name="username"]').count() > 0
  if (!hasForm) {
    console.log('    ↪  No login form at STAGING_URL — trying /administrator/index.php')
    await page.goto(ADMIN, { waitUntil: 'domcontentloaded', timeout: 30_000 })
  }

  await page.locator('input[name="username"]').fill(ADMIN_USER)
  await page.locator('input[name="passwd"], input[name="password"]').first().fill(ADMIN_PASS)
  await page.locator('button[type="submit"], input[type="submit"]').first().click()

  await page.waitForURL(
    url => url.hostname === parsed.hostname && url.pathname.includes('/administrator'),
    { timeout: 30_000 },
  )

  const stillOnLogin = await page.locator('input[name="username"]').count() > 0
  if (stillOnLogin) {
    throw new Error('Login failed — still on login page. Check STAGING_ADMIN_USER / STAGING_ADMIN_PASS.')
  }

  console.log('✅  Logged in')
}

// ── Open Health page & enumerate rendered Fix It links ──────────────────────

async function openHealth (page) {
  await page.goto(`${ADMIN}?option=com_aiboost&view=health`, {
    waitUntil: 'networkidle',
    timeout: 45_000,
  })
  await page.waitForSelector('.ab-vue-health', { timeout: 20_000 })
  await page.waitForTimeout(800)
}

async function enumerateRenderedFixItLinks (page) {
  return await page.evaluate(() => {
    const links = Array.from(document.querySelectorAll('.ab-vue-health a.ab-btn.ab-btn--subtle.ab-btn--sm'))
    const out = []
    for (const a of links) {
      const href = a.getAttribute('href') || ''
      if (/^https?:\/\//i.test(href)) continue
      let row = a.closest('.ab-hc-row, [data-check-id]')
      let checkId = '', checkLabel = ''
      if (row) {
        checkId = row.getAttribute('data-check-id') || ''
        const lab = row.querySelector('.ab-hc-row-label, .ab-hc-row__title, strong')
        checkLabel = lab ? lab.textContent.trim().slice(0, 80) : ''
      }
      try {
        const u = new URL(href, window.location.origin)
        const tab = u.searchParams.get('tab')
        const field = u.searchParams.get('field')
        if (tab && field) out.push({ href, tab, field, actionLabel: a.textContent.trim().slice(0, 40), checkId, checkLabel })
      } catch (_) {}
    }
    return out
  })
}

// ── Install highlight MutationObserver as early as possible ──────────────────

async function installHighlightObserver (page) {
  await page.evaluate(() => {
    window.__abHits = []
    function rec (el, src) {
      window.__abHits.push({
        tag: el.tagName,
        id: el.id || null,
        dataAbField: el.getAttribute('data-ab-field') || null,
        name: el.getAttribute('name') || null,
        src,
      })
    }
    document.querySelectorAll('.ab-field-highlight').forEach(el => rec(el, 'initial'))
    const o = new MutationObserver(ms => {
      for (const m of ms) {
        if (m.attributeName === 'class' && m.target?.classList?.contains('ab-field-highlight')) {
          rec(m.target, 'mutation')
        }
      }
    })
    o.observe(document.body, { attributes: true, attributeFilter: ['class'], subtree: true })
    window.__abObs = o
  })
}

async function postHydrationRescanForHighlight (page) {
  await page.evaluate(() => {
    document.querySelectorAll('.ab-field-highlight').forEach(el => {
      window.__abHits.push({
        tag: el.tagName,
        id: el.id || null,
        dataAbField: el.getAttribute('data-ab-field') || null,
        name: el.getAttribute('name') || null,
        src: 'post-rescan',
      })
    })
  })
}

// ── Verify the post-click Settings page ──────────────────────────────────────

async function verifySettingsState (page, tab, field) {
  const errors = []
  const diagnostics = {}

  await page.waitForSelector('.ab-vue-settings', { timeout: 20_000 })
  await page.waitForTimeout(SETTINGS_HYDRATE_MS)
  await postHydrationRescanForHighlight(page)

  // (a) active tab label
  const activeTab = await page.evaluate((expectedTab, tabLabels) => {
    const buttons = Array.from(document.querySelectorAll('.ab-tab-strip__btn'))
    for (const btn of buttons) {
      if (btn.getAttribute('aria-selected') === 'true' || btn.classList.contains('active')) {
        const labelEl = btn.querySelector('.ab-tab-strip__label')
        const renderedLabel = labelEl ? labelEl.textContent.trim() : ''
        return {
          renderedLabel,
          expectedLabel: tabLabels[expectedTab] || expectedTab,
          ok: renderedLabel === (tabLabels[expectedTab] || expectedTab),
        }
      }
    }
    return null
  }, tab, TAB_LABELS)

  if (!activeTab) {
    errors.push(`No active tab button found (expected tab="${tab}")`)
  } else if (!activeTab.ok) {
    errors.push(`Tab mismatch: expected "${activeTab.expectedLabel}", got "${activeTab.renderedLabel}"`)
  }
  diagnostics.activeTab = activeTab

  // (b) field exists — STRICT: REQUIRE [data-ab-field="<field>"] for pass.
  //     Heuristic lookups (id/#field-/name) are reported as DIAGNOSTIC only;
  //     they do NOT count as success — a missing data-ab-field is a real
  //     contract violation because _scrollToField prefers it and downstream
  //     tooling (e.g. Fix It deep-links) relies on it.
  const findResult = await page.evaluate((fieldKey) => {
    const root = document.querySelector('.ab-vue-settings')
    if (!root) return { strict: false, fallback: null, method: 'no-root' }

    const esc = (typeof CSS !== 'undefined' && CSS.escape)
      ? CSS.escape
      : (s => s.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1'))

    // STRICT: data-ab-field is the required marker
    const strictEl = root.querySelector('[data-ab-field="' + esc(fieldKey) + '"]')
    const strict = !!strictEl

    // Diagnostic-only fallback so the failure message is actionable
    let fallback = null
    let el = root.querySelector('#field-' + esc(fieldKey))
    if (el) fallback = { method: 'field-id', tag: el.tagName, id: el.id }
    if (!fallback) {
      el = root.querySelector('[name="' + esc(fieldKey) + '"]')
      if (el) fallback = { method: 'name-attr', tag: el.tagName, id: el.id, name: el.getAttribute('name') }
    }
    if (!fallback) {
      const k = fieldKey.toLowerCase()
      for (const c of root.querySelectorAll('input, select, textarea, button')) {
        const id = (c.id || '').toLowerCase()
        if (id && (id === k || id.includes(k))) {
          fallback = { method: 'id-heuristic', tag: c.tagName, id: c.id }
          break
        }
      }
    }
    return { strict, fallback }
  }, field)

  if (!findResult.strict) {
    if (findResult.fallback) {
      errors.push(`Field "${field}" missing required [data-ab-field] marker (heuristic match via ${findResult.fallback.method} → ${findResult.fallback.tag}#${findResult.fallback.id || '?'} — add data-ab-field="${field}" to that element)`)
    } else {
      errors.push(`Field "${field}" not found at all (no [data-ab-field], no #field-<key>, no [name], no id-heuristic)`)
    }
    diagnostics.findResult = findResult
    return { errors, diagnostics }
  }
  diagnostics.findResult = findResult

  // (c) highlight flash — combine live polling + observer hits + rescans
  const highlightInfo = await page.evaluate(async ({ fieldKey, maxWait }) => {
    const root = document.querySelector('.ab-vue-settings')
    if (!root) return { seen: false, hits: 0 }

    const esc = (typeof CSS !== 'undefined' && CSS.escape)
      ? CSS.escape
      : (s => s.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1'))

    function matchesField (hit) {
      if (hit.dataAbField === fieldKey) return true
      if (hit.name === fieldKey) return true
      const id = (hit.id || '').toLowerCase()
      const k  = fieldKey.toLowerCase()
      if (id && (id === k || id === 'field-' + k || id.includes(k))) return true
      return false
    }

    const start = Date.now()
    let seenLive = false, matchingHit = null
    while (Date.now() - start < maxWait) {
      const el = root.querySelector('[data-ab-field="' + esc(fieldKey) + '"]')
      if (el && el.classList.contains('ab-field-highlight')) { seenLive = true; break }
      const hits = window.__abHits || []
      matchingHit = hits.find(matchesField) || null
      if (matchingHit) break
      await new Promise(r => setTimeout(r, 80))
    }
    if (!matchingHit) matchingHit = (window.__abHits || []).find(matchesField) || null

    try { window.__abObs?.disconnect() } catch (_) {}

    return {
      seen: seenLive || !!matchingHit,
      via: seenLive ? 'live-poll' : (matchingHit ? matchingHit.src : null),
      hits: (window.__abHits || []).length,
    }
  }, { fieldKey: field, maxWait: HIGHLIGHT_MAX_WAIT_MS })

  if (!highlightInfo.seen) {
    errors.push(`Field "${field}" never received .ab-field-highlight class (waited ${HIGHLIGHT_MAX_WAIT_MS}ms; ${highlightInfo.hits} total hits observed on other elements)`)
  }
  diagnostics.highlight = highlightInfo

  // (d) viewport
  const viewportInfo = await page.evaluate((fieldKey) => {
    const esc = (typeof CSS !== 'undefined' && CSS.escape)
      ? CSS.escape
      : (s => s.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1'))
    const el = document.querySelector('.ab-vue-settings [data-ab-field="' + esc(fieldKey) + '"]')
    if (!el) return { inViewport: false, reason: 'no-element' }
    const r = el.getBoundingClientRect()
    const vh = window.innerHeight || document.documentElement.clientHeight
    return { inViewport: r.bottom > 0 && r.top < vh, top: Math.round(r.top), bottom: Math.round(r.bottom), vh }
  }, field)

  if (!viewportInfo.inViewport) {
    errors.push(`Field "${field}" not in viewport after click (top=${viewportInfo.top}, vh=${viewportInfo.vh})`)
  }
  diagnostics.viewport = viewportInfo

  return { errors, diagnostics }
}

// ── Main ──────────────────────────────────────────────────────────────────────

async function main () {
  console.log('═══════════════════════════════════════════════════════════════')
  console.log('  AI Boost — Health Fix It — true E2E click test')
  console.log(`  Staging : ${BASE}`)
  console.log(`  Mode    : ${HEADLESS ? 'headless' : 'visible browser'}`)
  console.log('═══════════════════════════════════════════════════════════════')

  // Build registry from PHP source of truth FIRST so we fail fast on drift.
  const registry = buildCanonicalRegistry()
  console.log(`\n📚  Canonical registry (parsed from HealthCheckService.php): ${registry.size} unique (tab, field) pairs`)

  const browser = await chromium.launch({ headless: HEADLESS })
  const context = await browser.newContext({ ignoreHTTPSErrors: true, viewport: { width: 1440, height: 900 } })

  // Inject the highlight tracker at document-start on EVERY navigation. This
  // eliminates the race where the observer would be installed after Vue's
  // mount+180ms timer has already added (and possibly removed, 4.2s later)
  // the .ab-field-highlight class. We additionally run a 100ms polling
  // interval as a redundant capture path — if MutationObserver misses an
  // attribute mutation for any reason, the interval will still see the
  // class while it's present.
  await context.addInitScript(() => {
    window.__abHits = []
    function rec (el, src) {
      const sig = (el.getAttribute('data-ab-field') || '') + '|' +
                  (el.id || '') + '|' + src
      if (window.__abHits.find(h => h._sig === sig)) return
      window.__abHits.push({
        tag: el.tagName,
        id: el.id || null,
        dataAbField: el.getAttribute('data-ab-field') || null,
        name: el.getAttribute('name') || null,
        src,
        _sig: sig,
      })
    }
    const o = new MutationObserver(ms => {
      for (const m of ms) {
        if (m.attributeName === 'class' && m.target?.classList?.contains('ab-field-highlight')) {
          rec(m.target, 'mutation')
        }
      }
    })
    const start = () => {
      try { o.observe(document.documentElement, { attributes: true, attributeFilter: ['class'], subtree: true }) } catch (_) {}
      document.querySelectorAll('.ab-field-highlight').forEach(el => rec(el, 'initial'))
      const t0 = Date.now()
      const iv = setInterval(() => {
        document.querySelectorAll('.ab-field-highlight').forEach(el => rec(el, 'poll'))
        if (Date.now() - t0 > 8000) clearInterval(iv)
      }, 100)
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', start, { once: true })
    } else {
      start()
    }
  })

  const page = await context.newPage()

  const phaseA = []
  const phaseB = []
  const exercisedKeys = new Set()

  try {
    await login(page)

    // ───── PHASE A: click every rendered Fix It link ───────────────────────
    console.log('\n━━ PHASE A: click every rendered Fix It link ━━')
    await openHealth(page)
    const links = await enumerateRenderedFixItLinks(page)
    console.log(`    Rendered Fix It links found: ${links.length}`)

    for (let i = 0; i < links.length; i++) {
      const link = links[i]
      const tab = link.tab, field = link.field
      const key = `${tab}::${field}`
      exercisedKeys.add(key)

      process.stdout.write(`  [${String(i + 1).padStart(2)}/${links.length}] click "${link.actionLabel}" → tab=${tab} field=${field} `)

      try {
        const navPromise = page.waitForURL(/option=com_aiboost.*view=settings/, { timeout: 20_000 })
        await page.evaluate((href) => {
          const a = Array.from(document.querySelectorAll('.ab-vue-health a.ab-btn.ab-btn--subtle.ab-btn--sm'))
                       .find(x => x.getAttribute('href') === href)
          if (a) a.click()
        }, link.href)
        await navPromise

        await installHighlightObserver(page)
        const result = await verifySettingsState(page, tab, field)

        if (result.errors.length === 0) {
          console.log(`✅  (tab✓ data-ab-field✓ highlight✓ viewport✓)`)
          phaseA.push({ tab, field, status: 'pass', source: link.checkId || 'rendered' })
        } else {
          console.log(`❌`)
          for (const e of result.errors) console.log(`         ✗ ${e}`)
          phaseA.push({ tab, field, status: 'fail', errors: result.errors, diag: result.diagnostics, source: link.checkId || 'rendered' })
        }
      } catch (err) {
        console.log(`💥  ${err.message.split('\n')[0]}`)
        phaseA.push({ tab, field, status: 'error', errors: [err.message], source: link.checkId || 'rendered' })
      }

      if (i < links.length - 1) await openHealth(page)
    }

    // ───── PHASE B: synthetic coverage of the canonical registry ───────────
    console.log('\n━━ PHASE B: synthetic coverage of canonical registry ━━')
    const remaining = [...registry.values()].filter(t => !exercisedKeys.has(`${t.tab}::${t.field}`))
    console.log(`    Registry total: ${registry.size}; not exercised in A: ${remaining.length}`)

    for (let i = 0; i < remaining.length; i++) {
      const { tab, field, source } = remaining[i]
      if (!TAB_LABELS[tab]) {
        console.log(`  [${String(i + 1).padStart(2)}/${remaining.length}] ⚠  SKIP  tab=${tab} field=${field} — unknown tab id`)
        phaseB.push({ tab, field, status: 'skip', source: 'registry', reason: 'unknown tab id' })
        continue
      }

      const url = `${ADMIN}?option=com_aiboost&view=settings&tab=${encodeURIComponent(tab)}&field=${encodeURIComponent(field)}`
      process.stdout.write(`  [${String(i + 1).padStart(2)}/${remaining.length}] direct  tab=${tab.padEnd(10)} field=${field.padEnd(30)} `)

      try {
        await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30_000 })
        await installHighlightObserver(page)
        const result = await verifySettingsState(page, tab, field)
        if (result.errors.length === 0) {
          console.log(`✅  (tab✓ data-ab-field✓ highlight✓ viewport✓)`)
          phaseB.push({ tab, field, status: 'pass', source: `registry/${source}` })
        } else {
          console.log(`❌`)
          for (const e of result.errors) console.log(`         ✗ ${e}`)
          phaseB.push({ tab, field, status: 'fail', errors: result.errors, diag: result.diagnostics, source: `registry/${source}` })
        }
      } catch (err) {
        console.log(`💥  ${err.message.split('\n')[0]}`)
        phaseB.push({ tab, field, status: 'error', errors: [err.message], source: `registry/${source}` })
      }
    }

  } finally {
    await browser.close()
  }

  // ── Summary ─────────────────────────────────────────────────────────────────
  const aPass = phaseA.filter(r => r.status === 'pass').length
  const aFail = phaseA.filter(r => r.status === 'fail' || r.status === 'error').length
  const bPass = phaseB.filter(r => r.status === 'pass').length
  const bFail = phaseB.filter(r => r.status === 'fail' || r.status === 'error').length
  const bSkip = phaseB.filter(r => r.status === 'skip').length

  console.log('\n═══════════════════════════════════════════════════════════════')
  console.log(`  PHASE A (clicks)    : ${aPass} passed, ${aFail} failed  (of ${phaseA.length})`)
  console.log(`  PHASE B (synthetic) : ${bPass} passed, ${bFail} failed, ${bSkip} skipped  (of ${phaseB.length})`)
  console.log('═══════════════════════════════════════════════════════════════')

  const failed = [...phaseA, ...phaseB].filter(r => r.status === 'fail' || r.status === 'error')
  if (failed.length > 0) {
    console.log('\nFailures:')
    for (const r of failed) {
      console.log(`\n  [${r.source}] tab=${r.tab}  field=${r.field}`)
      for (const e of r.errors || []) console.log(`    ✗ ${e}`)
    }
    process.exit(1)
  }

  process.exit(0)
}

main().catch(err => {
  console.error('\n💥  Fatal error:', err.message)
  process.exit(1)
})
