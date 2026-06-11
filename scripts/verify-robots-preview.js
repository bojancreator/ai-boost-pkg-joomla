#!/usr/bin/env node
/**
 * verify-robots-preview.js — confirm robots.txt Preview shows the file CONTENT.
 *
 * Opens Crawlers & Robots, clicks "Preview robots.txt", waits for the fetch,
 * and checks the .ab-robots-body <pre> renders non-empty content.
 *
 *   python _creds_run.py scripts/run-node-test.py scripts/verify-robots-preview.js --target staging
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
    await page.goto(APP_URL + '#/settings?tab=crawlers', { waitUntil: 'domcontentloaded', timeout: 45000 })
    await page.waitForSelector('#ab-app', { timeout: 15000 })
    await page.waitForTimeout(1800)

    // Click the preview button (text "Preview robots.txt").
    const btn = page.locator('button', { hasText: /Preview robots\.txt|Refresh/ }).first()
    if (await btn.count() === 0) { console.log('  ❌ Preview button not found'); failed = true }
    else {
      await btn.click()
      // Wait for the fetch + render.
      await page.waitForTimeout(3500)
      const body = await page.locator('.ab-robots-body').first()
      const present = await body.count() > 0
      const text = present ? (await body.innerText()).trim() : ''
      console.log(`\n  Target: ${TARGET}`)
      console.log(`  .ab-robots-body present: ${present}, length: ${text.length}`)
      if (text) console.log('  first line: ' + text.split('\n')[0].slice(0, 60))
      if (present && text.length > 10) console.log('  ✅ PASS: robots.txt content is shown in the preview')
      else { console.log('  ❌ FAIL: preview content (.ab-robots-body) empty/missing'); failed = true }
    }
  } catch (e) {
    console.log('  ❌ ERROR: ' + (e && e.message ? e.message : e)); failed = true
  } finally { await browser.close() }
  process.exit(failed ? 1 : 0)
})()
