#!/usr/bin/env node
/**
 * verify-pro-lock.js — confirm the Free/Pro lock (ProGate) engages.
 *
 * Opens the AI Boost SPA, reads window.aiBoostBootstrap.isPro, then visits the
 * Social and AI Visibility tabs and counts ProGate lock overlays
 * (.ab-pg-overlay) + inline translate chips (.ab-pg-field).
 *
 * Usage (via run-node-test.py so creds are in env):
 *   python _creds_run.py scripts/run-node-test.py scripts/verify-pro-lock.js --target free
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const args = process.argv.slice(2)
const targetIdx = args.indexOf('--target')
const TARGET = targetIdx !== -1 ? args[targetIdx + 1] : 'free'

function env(key) {
  const v = process.env[key]
  if (!v) { console.error(`❌ Missing env var: ${key}`); process.exit(1) }
  return v
}

let ADMIN_URL, ADMIN_USER, ADMIN_PASS
if (TARGET === 'free') {
  ADMIN_URL  = env('FREE_URL'); ADMIN_USER = env('FREE_ADMIN_USER'); ADMIN_PASS = env('FREE_ADMIN_PASS')
} else {
  ADMIN_URL  = env('STAGING_URL'); ADMIN_USER = env('STAGING_ADMIN_USER'); ADMIN_PASS = env('STAGING_ADMIN_PASS')
}

const parsed  = new NodeURL(ADMIN_URL)
const BASE    = `${parsed.protocol}//${parsed.host}`
const APP_URL = `${BASE}/administrator/index.php?option=com_aiboost&view=app`

async function login(page) {
  await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
  try {
    await page.waitForSelector('input[name=username]', { timeout: 8000 })
  } catch { return }
  await page.fill('input[name=username]', ADMIN_USER)
  await page.fill('input[name=passwd]', ADMIN_PASS)
  await page.press('input[name=passwd]', 'Enter')
  await page.waitForLoadState('domcontentloaded', { timeout: 30000 })
}

async function gotoSpa(page, hash) {
  await page.goto(APP_URL + hash, { waitUntil: 'domcontentloaded', timeout: 45000 })
  await page.waitForSelector('#ab-app', { timeout: 15000 })
  await page.waitForTimeout(1800)
}

;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const page = await browser.newPage()
  let failed = false
  try {
    await login(page)
    await gotoSpa(page, '#/settings?tab=social')

    // The UI lock keys on isProInstall (Pro package present), not the licence.
    const isProInstall = await page.evaluate(() => (window.aiBoostBootstrap || {}).isProInstall)
    console.log(`\n  Target: ${TARGET} (${BASE})`)
    console.log(`  window.aiBoostBootstrap.isProInstall = ${JSON.stringify(isProInstall)}`)

    const socialBtns  = await page.locator('.ab-pg-upbtn').count()
    const socialChips = await page.locator('.ab-pg-field').count()
    console.log(`  [social] upgrade buttons: ${socialBtns}, translate chips: ${socialChips}`)

    await gotoSpa(page, '#/settings?tab=aeo')
    const aeoBtns = await page.locator('.ab-pg-upbtn').count()
    console.log(`  [aeo]    upgrade buttons: ${aeoBtns}`)

    if (isProInstall === false) {
      if (socialBtns < 1 || socialChips < 1 || aeoBtns < 1) {
        console.log('  ❌ FAIL: Free install but Pro lock buttons/chips missing')
        failed = true
      } else {
        console.log('  ✅ PASS: Free install shows Pro upgrade buttons + translate chips')
      }
    } else {
      console.log(`  ℹ️  isProInstall=${JSON.stringify(isProInstall)}: Pro package present — UI unlocked, locks not expected.`)
    }
  } catch (e) {
    console.log('  ❌ ERROR: ' + (e && e.message ? e.message : e))
    failed = true
  } finally {
    await browser.close()
  }
  process.exit(failed ? 1 : 0)
})()
