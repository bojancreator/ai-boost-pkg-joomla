#!/usr/bin/env node
/**
 * verify-markdown.js — confirm Markdown page serving works (now a Free feature).
 *
 * Enables "Serve pages as Markdown" in the AEO tab + saves, then fetches the
 * homepage with ?markdown=1 and checks the response Content-Type is
 * text/markdown and the body looks like Markdown.
 *
 *   python _creds_run.py scripts/run-node-test.py scripts/verify-markdown.js --target free
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const args = process.argv.slice(2)
const ti = args.indexOf('--target')
const TARGET = ti !== -1 ? args[ti + 1] : 'free'

function env(k) { const v = process.env[k]; if (!v) { console.error('Missing env ' + k); process.exit(1) } return v }
let ADMIN_URL, USER, PASS
if (TARGET === 'free') { ADMIN_URL = env('FREE_URL'); USER = env('FREE_ADMIN_USER'); PASS = env('FREE_ADMIN_PASS') }
else { ADMIN_URL = env('STAGING_URL'); USER = env('STAGING_ADMIN_USER'); PASS = env('STAGING_ADMIN_PASS') }

const parsed = new NodeURL(ADMIN_URL)
const BASE = `${parsed.protocol}//${parsed.host}`
const APP_URL = `${BASE}/administrator/index.php?option=com_aiboost&view=app`

async function login(page) {
  await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
  try { await page.waitForSelector('input[name=username]', { timeout: 8000 }) } catch { return }
  await page.fill('input[name=username]', USER)
  await page.fill('input[name=passwd]', PASS)
  await page.press('input[name=passwd]', 'Enter')
  await page.waitForLoadState('domcontentloaded', { timeout: 30000 })
}

;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const page = await browser.newPage()
  let failed = false
  try {
    await login(page)
    await page.goto(APP_URL + '#/settings?tab=aeo', { waitUntil: 'domcontentloaded', timeout: 45000 })
    await page.waitForSelector('#ab-app', { timeout: 15000 })
    await page.waitForTimeout(1800)

    // Enable the Markdown toggle if it isn't already on.
    const md = page.locator('#aeo-markdown-enabled')
    if (await md.count() === 0) { console.log('  ❌ Markdown toggle not found (still locked / not rendered?)'); failed = true }
    else {
      if (!(await md.isChecked())) await md.check({ force: true })
      // Save.
      await page.locator('button', { hasText: /Save All Settings/ }).first().click()
      await page.waitForTimeout(2500)
      console.log(`\n  Target: ${TARGET} — enabled Markdown + saved`)

      // Fetch the homepage as Markdown.
      const res = await page.request.get(BASE + '/?markdown=1', { timeout: 30000 })
      const ct = (res.headers()['content-type'] || '').toLowerCase()
      const body = await res.text()
      console.log(`  GET /?markdown=1 → HTTP ${res.status()}, Content-Type: ${ct}`)
      console.log(`  body length: ${body.length}, first line: ${body.split('\n')[0].slice(0, 60)}`)

      const isMd = ct.includes('text/markdown')
      const looksMd = /^#/m.test(body) || body.includes('](')
      if (isMd) console.log('  ✅ PASS: page served as text/markdown')
      else if (looksMd) console.log('  ⚠️  Content-Type not text/markdown but body looks like Markdown — check headers')
      else { console.log('  ❌ FAIL: not served as Markdown'); failed = true }
    }
  } catch (e) {
    console.log('  ❌ ERROR: ' + (e && e.message ? e.message : e)); failed = true
  } finally { await browser.close() }
  process.exit(failed ? 1 : 0)
})()
