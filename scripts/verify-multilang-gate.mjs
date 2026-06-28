#!/usr/bin/env node
/**
 * AI Boost — verification screenshots for the Multilingual integration work.
 *
 *  1) Integrations card — relabelled "Multilingual" / provider "Native Joomla & Falang".
 *  2) A settings tab with translation expanders while Multilingual is ACTIVE (normal dropdowns).
 *  3) The SAME tab with the settings.getLanguages response intercepted to report
 *     multilang_active=false — so the OFF state (the "Turn on Multilingual to translate"
 *     hint) is verified WITHOUT changing the live site's real setting/DB.
 *
 * Output: artifacts/multilang-gate/<theme>/{integrations,settings-on,settings-off}.png
 * Usage:  python _creds_run.py scripts/verify-multilang-gate.mjs
 */

import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'
import { existsSync, mkdirSync } from 'fs'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')
const __dirname = dirname(fileURLToPath(import.meta.url))

function env(key) {
  const v = process.env[key]
  if (!v) { console.error(`❌ Missing env var: ${key}`); process.exit(1) }
  return v
}

const ADMIN_URL  = env('STAGING_URL')
const ADMIN_USER = env('STAGING_ADMIN_USER')
const ADMIN_PASS = env('STAGING_ADMIN_PASS')

const parsed  = new NodeURL(ADMIN_URL)
const BASE    = `${parsed.protocol}//${parsed.host}`
const APP_URL = `${BASE}/administrator/index.php?option=com_aiboost&view=app`

const OUT_ROOT = resolve(__dirname, '..', 'artifacts', 'multilang-gate')
for (const t of ['light', 'dark']) {
  const d = resolve(OUT_ROOT, t)
  if (!existsSync(d)) mkdirSync(d, { recursive: true })
}

async function login(page) {
  await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
  try { await page.waitForSelector('input[name=username]', { timeout: 8000 }) } catch { return }
  await page.fill('input[name=username]', ADMIN_USER)
  await page.fill('input[name=passwd]', ADMIN_PASS)
  await page.press('input[name=passwd]', 'Enter')
  await page.waitForLoadState('domcontentloaded', { timeout: 30000 })
}

async function setTheme(page, theme) {
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-bs-theme', t)
    document.body.setAttribute('data-bs-theme', t)
    if (t === 'dark') document.body.classList.add('dark-mode')
    else document.body.classList.remove('dark-mode')
  }, theme)
  await page.waitForTimeout(400)
}

;(async () => {
  console.log(`\n📸 Multilingual gate verification — ${BASE}\n   Output: ${OUT_ROOT}\n`)
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] })
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 1000 }, ignoreHTTPSErrors: true })
  const page = await ctx.newPage()

  // Intercept the languages endpoint; `forceOff` flips multilang_active to false.
  let forceOff = false
  await page.route('**/*task=settings.getLanguages*', async (route) => {
    if (!forceOff) return route.continue()
    try {
      const resp = await route.fetch()
      const data = await resp.json()
      data.multilang_active = false
      await route.fulfill({ status: 200, contentType: 'application/json; charset=utf-8', body: JSON.stringify(data) })
    } catch {
      await route.continue()
    }
  })

  try {
    await login(page)
    for (const theme of ['light', 'dark']) {
      const outDir = resolve(OUT_ROOT, theme)
      console.log(`── ${theme}`)

      // 1) Integrations card. Warm up via dashboard first so AppShell has
      //    materialised the legacy integrations globals (loading /integrations as
      //    the very first SPA route races the fetch and renders an empty list).
      forceOff = false
      await page.goto(APP_URL + '#/dashboard', { waitUntil: 'domcontentloaded', timeout: 45000 })
      await page.waitForSelector('#ab-app', { timeout: 15000 })
      await page.waitForTimeout(1500)
      await page.goto(APP_URL + '#/integrations', { waitUntil: 'domcontentloaded', timeout: 45000 })
      await page.waitForTimeout(2500)
      const intCount = await page.evaluate(() => (window.aiBoostIntegrations || []).length)
      const falang = await page.evaluate(() => {
        const f = (window.aiBoostIntegrations || []).find(i => i.key === 'falang') || {}
        return { name: f.name, vendor: f.vendor, master_enabled: f.master_enabled }
      })
      await setTheme(page, theme)
      await page.screenshot({ path: resolve(outDir, 'integrations.png'), fullPage: true })
      console.log('   integrations ✅  count=' + intCount + '  falang=' + JSON.stringify(falang))

      // 2) Site Identity with Multilingual ACTIVE (real response). Full reload so
      //    useTranslations re-fetches (a hash nav alone keeps the cached singleton).
      forceOff = false
      await page.goto(APP_URL + '#/settings?tab=org', { waitUntil: 'domcontentloaded', timeout: 45000 })
      await page.reload({ waitUntil: 'domcontentloaded' })
      await page.waitForSelector('#ab-app', { timeout: 15000 })
      await page.waitForTimeout(2200)
      await setTheme(page, theme)
      const onState = await page.evaluate(() => ({
        hints:   document.querySelectorAll('.ab-trans-toggle--hint').length,
        toggles: document.querySelectorAll('.ab-trans-toggle:not(.ab-trans-toggle--hint)').length,
      }))
      await page.screenshot({ path: resolve(outDir, 'settings-on.png'), fullPage: true })
      console.log('   settings-on ✅  ' + JSON.stringify(onState))

      // 3) Force multilang_active=false and FULL reload → the cached singleton
      //    resets and re-fetches the intercepted response, so the hint replaces
      //    the dropdowns.
      forceOff = true
      await page.reload({ waitUntil: 'domcontentloaded' })
      await page.waitForSelector('#ab-app', { timeout: 15000 })
      await page.waitForTimeout(2200)
      await setTheme(page, theme)
      const offState = await page.evaluate(() => ({
        hints:    document.querySelectorAll('.ab-trans-toggle--hint').length,
        toggles:  document.querySelectorAll('.ab-trans-toggle:not(.ab-trans-toggle--hint)').length,
        firstHint: (document.querySelector('.ab-trans-toggle--hint')?.textContent || '').trim(),
      }))
      await page.screenshot({ path: resolve(outDir, 'settings-off.png'), fullPage: true })
      console.log('   settings-off ✅  ' + JSON.stringify(offState))
    }
    console.log(`\n✅ Done → ${OUT_ROOT}`)
  } finally {
    await browser.close()
  }
})()
