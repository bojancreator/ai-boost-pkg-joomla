#!/usr/bin/env node
/**
 * verify-perf-baseline.js — capture the AI Boost front-end perf baseline.
 *
 * Logs in (a logged-in session makes LiteSpeed bypass its full-page cache),
 * enables the plugin debug_mode (so aiboost_core::onAfterRender emits the
 * X-AiBoost-Perf header), then loads a few front-end pages and reads
 * finalize-time / peak-memory from the header. Restores debug_mode=off at the
 * end so staging is left as found.
 *
 *   python _creds_run.py scripts/run-node-test.py scripts/verify-perf-baseline.js --target staging
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const args = process.argv.slice(2)
const ti = args.indexOf('--target')
const TARGET = ti !== -1 ? args[ti + 1] : 'staging'

function env(k) { const v = process.env[k]; if (!v) { console.error('Missing env ' + k); process.exit(1) } return v }
let ADMIN_URL, USER, PASS
if (TARGET === 'free') { ADMIN_URL = env('FREE_URL'); USER = env('FREE_ADMIN_USER'); PASS = env('FREE_ADMIN_PASS') }
else { ADMIN_URL = env('STAGING_URL'); USER = env('STAGING_ADMIN_USER'); PASS = env('STAGING_ADMIN_PASS') }

const parsed = new NodeURL(ADMIN_URL)
const BASE = `${parsed.protocol}//${parsed.host}`
const APP_URL = `${BASE}/administrator/index.php?option=com_aiboost&view=app`

// Front-end pages to sample (resolved from the sitemap at runtime, with a few fallbacks).
const FALLBACK_PAGES = ['/', '/test-proba', '/clanstvo', '/our-story']

async function login(page) {
  await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
  try { await page.waitForSelector('input[name=username]', { timeout: 8000 }) } catch { return }
  await page.fill('input[name=username]', USER)
  await page.fill('input[name=passwd]', PASS)
  await page.press('input[name=passwd]', 'Enter')
  await page.waitForLoadState('domcontentloaded', { timeout: 30000 })
}

async function setDebug(page, on) {
  await page.goto(APP_URL + '#/debug', { waitUntil: 'domcontentloaded', timeout: 45000 })
  await page.waitForSelector('#ab-app', { timeout: 15000 })
  // The toggle input is visually hidden (styled toggle), so wait for it to be
  // present in the DOM rather than "visible".
  const toggle = '[data-ab-field="debug_mode"]'
  await page.waitForSelector(toggle, { state: 'attached', timeout: 15000 })
  if (on) await page.locator(toggle).check({ force: true })
  else await page.locator(toggle).uncheck({ force: true })
  await page.waitForTimeout(300)
  const saveBtn = page.locator('button', { hasText: /Save All Settings/ }).first()
  await saveBtn.click()
  // Wait until the button leaves the "Saving…" state (best-effort).
  try { await page.locator('button', { hasText: /Saving…/ }).first().waitFor({ state: 'detached', timeout: 15000 }) } catch {}
  await page.waitForTimeout(1500)
}

function parsePerf(h) {
  if (!h) return null
  const fin  = /finalize=([\d.]+)ms/.exec(h)
  const peak = /peak=([\d.]+)MB/.exec(h)
  const req  = /request=([\d.]+)ms/.exec(h)
  return {
    finalize: fin ? parseFloat(fin[1]) : null,
    peak: peak ? parseFloat(peak[1]) : null,
    request: req ? parseFloat(req[1]) : null,
    raw: h,
  }
}

;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const page = await browser.newPage()
  let failed = false
  try {
    await login(page)

    // Resolve some content URLs from the sitemap (logged-in, so fresh).
    let pages = FALLBACK_PAGES.slice()
    try {
      const sm = await page.evaluate(async (base) => (await (await fetch(base + '/sitemap.xml')).text()), BASE)
      const locs = [...sm.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1])
        .filter(u => !/\/sitemap/i.test(u) && u !== BASE + '/')
      if (locs.length) pages = ['/', ...locs.slice(0, 5).map(u => u.replace(BASE, ''))]
    } catch {}

    console.log(`\n  Enabling debug_mode on ${TARGET} …`)
    await setDebug(page, true)

    console.log('  Sampling front-end pages (logged-in → LiteSpeed cache bypassed):')
    const readings = []
    for (const p of pages) {
      for (let pass = 0; pass < 2; pass++) {
        const url = BASE + p + (p.includes('?') ? '&' : '?') + '_abperf=' + pass + '_' + p.length
        try {
          const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 40000 })
          const perf = parsePerf(resp.headers()['x-aiboost-perf'])
          if (perf) { readings.push(perf); console.log(`    ${p}  finalize=${perf.finalize}ms peak=${perf.peak}MB request=${perf.request}ms`) }
          else if (pass === 0) console.log(`    ${p}  (no X-AiBoost-Perf header)`)
        } catch (e) { console.log(`    ${p}  ERR ${e.message}`) }
      }
    }

    console.log('\n  Restoring debug_mode=off …')
    await setDebug(page, false)

    if (readings.length === 0) {
      console.log('\n  ❌ No perf headers captured (page may be cached, or debug not applied).')
      failed = true
    } else {
      const avg = (k) => (readings.reduce((a, r) => a + (r[k] || 0), 0) / readings.length)
      const max = (k) => Math.max(...readings.map(r => r[k] || 0))
      console.log(`\n  === Baseline over ${readings.length} samples ===`)
      console.log(`  finalize: avg ${avg('finalize').toFixed(2)} ms  (max ${max('finalize').toFixed(2)} ms)`)
      console.log(`  peak mem: avg ${avg('peak').toFixed(1)} MB  (max ${max('peak').toFixed(1)} MB)`)
      console.log(`  request : avg ${avg('request').toFixed(0)} ms  (max ${max('request').toFixed(0)} ms)`)
      console.log('  ✅ PASS: perf baseline captured')
    }
  } catch (e) {
    console.log('  ❌ ERROR: ' + (e && e.message ? e.message : e)); failed = true
  } finally { await browser.close() }
  process.exit(failed ? 1 : 0)
})()
