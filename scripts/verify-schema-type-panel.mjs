#!/usr/bin/env node
/**
 * AI Boost — Schema Type info-panel verification screenshots.
 *
 * Opens Settings → Schema → Business, selects a set of business types that
 * previously rendered NO info panel (TYPE_META gaps), and screenshots the
 * "Business / Organization Type" section so the new per-type copy can be
 * eyeballed in BOTH themes.
 *
 * Output: artifacts/schema-types/<theme>/<nn-type>.png
 *
 * Usage:  python _creds_run.py scripts/verify-schema-type-panel.mjs
 * Env (via _creds_run.py): STAGING_URL / STAGING_ADMIN_USER / STAGING_ADMIN_PASS
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

const OUT_ROOT  = resolve(__dirname, '..', 'artifacts', 'schema-types')
const OUT_LIGHT = resolve(OUT_ROOT, 'light')
const OUT_DARK  = resolve(OUT_ROOT, 'dark')
;[OUT_ROOT, OUT_LIGHT, OUT_DARK].forEach(d => { if (!existsSync(d)) mkdirSync(d, { recursive: true }) })

// Previously-blank types — one representative per family that had a gap, plus
// one that always had meta (Restaurant) as a control.
const CASES = [
  { name: '01-restaurant-control', cat: 'Food & Drink',          type: 'Restaurant' },
  { name: '02-bakery',             cat: 'Food & Drink',          type: 'Bakery' },
  { name: '03-pharmacy',           cat: 'Health & Medical',      type: 'Pharmacy' },
  { name: '04-resort',             cat: 'Lodging & Travel',      type: 'Resort' },
  { name: '05-hair-salon',         cat: 'Beauty & Fitness',      type: 'HairSalon' },
  { name: '06-accounting',         cat: 'Professional Services', type: 'AccountingService' },
  { name: '07-childcare',          cat: 'Education & Childcare', type: 'ChildCare' },
  { name: '08-insurance',          cat: 'Finance',               type: 'InsuranceAgency' },
]

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
  console.log(`  Logged in → ${page.url().substring(0, 80)}`)
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
  console.log(`\n📸 Schema Type panel verification`)
  console.log(`   Base: ${BASE}`)
  console.log(`   Output: ${OUT_ROOT}\n`)

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] })
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true })
  const page = await ctx.newPage()
  const consoleErrors = []
  page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(msg.text()) })

  try {
    await login(page)

    for (const theme of ['light', 'dark']) {
      const outDir = theme === 'light' ? OUT_LIGHT : OUT_DARK
      console.log(`\n── Theme: ${theme.toUpperCase()}`)

      await page.goto(APP_URL + '#/settings?tab=schema', { waitUntil: 'domcontentloaded', timeout: 45000 })
      await page.waitForSelector('#ab-app', { timeout: 15000 })
      await page.waitForTimeout(1800)
      await setTheme(page, theme)

      // Open the Business sub-tab
      await page.locator('.ab-schema-nav__btn', { hasText: 'Business' }).first().click()
      await page.waitForTimeout(400)

      const catSelect  = page.locator('xpath=//label[normalize-space(.)="Category"]/following-sibling::select')
      const typeSelect = page.locator('xpath=//label[normalize-space(.)="Schema Type"]/following-sibling::select')

      for (const c of CASES) {
        process.stdout.write(`   ${c.name} (${c.type}) … `)
        try {
          await catSelect.selectOption({ label: c.cat })
          await page.waitForTimeout(250)               // let onSchemaCategoryChange settle
          await typeSelect.selectOption({ value: c.type })
          await page.waitForTimeout(350)               // let Vue re-render the panel

          const panelVisible = await page.locator('.ab-schema-type-panel').isVisible().catch(() => false)
          const title = await page.locator('.ab-schema-type-panel__intro strong').first().textContent().catch(() => '(none)')

          const section = page.locator('.ab-section:has(.ab-schema-type-panel)').first()
          await section.screenshot({ path: resolve(outDir, `${c.name}.png`) })
          console.log(`✅  panel=${panelVisible}  title="${(title || '').trim()}"`)
        } catch (err) {
          console.log(`❌  ${err.message.substring(0, 90)}`)
        }
      }
    }

    console.log('\n── Console errors:')
    console.log(consoleErrors.length ? consoleErrors.slice(0, 10).map(e => '   ' + e.substring(0, 100)).join('\n') : '   None')
    console.log(`\n✅ Done → ${OUT_ROOT}`)
  } finally {
    await browser.close()
  }
})()
