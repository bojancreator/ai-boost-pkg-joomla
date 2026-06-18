#!/usr/bin/env node
/**
 * verify-health-deadhost.js — confirm the Health page no longer surfaces the
 * dead "updates.aiboostnow.com" host, and that the admin Health view renders.
 *
 *   python _creds_run.py scripts/run-node-test.py scripts/verify-health-deadhost.js --target staging
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
    await page.goto(APP_URL + '#/health', { waitUntil: 'domcontentloaded', timeout: 45000 })
    await page.waitForSelector('#ab-app', { timeout: 15000 })
    // Let the health checks run/render.
    await page.waitForTimeout(5000)
    const text = await page.locator('#ab-app').innerText()
    const dead = /updates\.aiboostnow\.com/i.test(text)
    const card = /Pro & add-on updates|Auto-update server/i.test(text)
    console.log(`\n  Target: ${TARGET}`)
    console.log(`  Health rendered (text length ${text.length}): ${text.length > 200}`)
    console.log(`  mentions 'updates.aiboostnow.com': ${dead}`)
    console.log(`  update card present: ${card}`)
    if (text.length < 200) { console.log('  ❌ FAIL: Health did not render'); failed = true }
    else if (dead) { console.log('  ❌ FAIL: dead host still shown on Health'); failed = true }
    else { console.log('  ✅ PASS: Health renders, no dead updates.aiboostnow.com host shown') }
  } catch (e) {
    console.log('  ❌ ERROR: ' + (e && e.message ? e.message : e)); failed = true
  } finally { await browser.close() }
  process.exit(failed ? 1 : 0)
})()
