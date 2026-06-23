#!/usr/bin/env node
/**
 * AI Boost — verification screenshots for the Manual FAQ repeater.
 *
 * Opens Settings → Schema → FAQ, ensures at least one FAQ row is visible
 * (adds + fills a sample row in-memory if the site has none — never saved),
 * expands the per-row Translations panel, and screenshots the FAQ section in
 * BOTH themes so the new repeater UI can be eyeballed.
 *
 * Output: artifacts/faq-repeater/<theme>/faq.png
 * Usage:  python _creds_run.py scripts/verify-faq-repeater.mjs
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

const OUT_ROOT  = resolve(__dirname, '..', 'artifacts', 'faq-repeater')
const OUT_LIGHT = resolve(OUT_ROOT, 'light')
const OUT_DARK  = resolve(OUT_ROOT, 'dark')
;[OUT_ROOT, OUT_LIGHT, OUT_DARK].forEach(d => { if (!existsSync(d)) mkdirSync(d, { recursive: true }) })

async function login(page) {
  await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
  try {
    await page.waitForSelector('input[name=username]', { timeout: 8000 })
  } catch {
    return
  }
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
  console.log(`\n📸 FAQ repeater verification — ${BASE}\n   Output: ${OUT_ROOT}\n`)
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] })
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 1000 }, ignoreHTTPSErrors: true })
  const page = await ctx.newPage()
  const consoleErrors = []
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()) })

  try {
    await login(page)
    for (const theme of ['light', 'dark']) {
      const outDir = theme === 'light' ? OUT_LIGHT : OUT_DARK
      process.stdout.write(`   ${theme} … `)
      await page.goto(APP_URL + '#/settings?tab=schema', { waitUntil: 'domcontentloaded', timeout: 45000 })
      await page.waitForSelector('#ab-app', { timeout: 15000 })
      await page.waitForTimeout(1800)
      await setTheme(page, theme)

      // Open the FAQ sub-tab.
      await page.locator('.ab-schema-nav__btn', { hasText: 'FAQ' }).first().click()
      await page.waitForTimeout(500)

      // Ensure at least one row is visible (in-memory only — not saved).
      const rows = await page.locator('.ab-faq-item').count()
      if (rows === 0) {
        await page.locator('.ab-faq-add').first().click()
        await page.waitForTimeout(250)
        await page.locator('.ab-faq-item__q').first().fill('Do you offer free delivery?').catch(() => {})
        await page.locator('.ab-faq-item__a').first().fill('Yes — on orders over €50 within the city, next-day.').catch(() => {})
      }
      // Expand the first row's Translations panel.
      await page.locator('.ab-faq-item .ab-trans-toggle').first().click().catch(() => {})
      await page.waitForTimeout(400)

      const seen = await page.locator('.ab-faq-item').count()
      const section = page.locator('.ab-section:has([data-ab-field="faq_items"])').first()
      await section.screenshot({ path: resolve(outDir, 'faq.png') })
      console.log(`✅  rows visible: ${seen}`)
    }
    console.log('\n── Console errors:', consoleErrors.length ? consoleErrors.slice(0, 6).join(' | ') : 'none')
    console.log(`✅ Done → ${OUT_ROOT}`)
  } finally {
    await browser.close()
  }
})()
