#!/usr/bin/env node
/**
 * AI Boost — verify that toggling the Multilingual integration switch updates the
 * per-field Translation UI across the SPA WITHOUT a manual page reload.
 *
 * SAFETY: the integrations.saveToggle POST is intercepted and faked (success),
 * so the LIVE site's real integration state is NEVER changed — only the client-side
 * reactivity (applyToggle → shared multilangActive ref) is exercised.
 *
 * Flow per theme: Integrations → click the "Multilingual" card switch OFF →
 * hash-navigate (NO reload) to Site Identity → expect the dropdowns replaced by hints.
 *
 * Output: artifacts/multilang-reactive/<theme>/{before,after}.png
 * Usage:  python _creds_run.py scripts/verify-multilang-reactive.mjs
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

const OUT_ROOT = resolve(__dirname, '..', 'artifacts', 'multilang-reactive')
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

const countState = (page) => page.evaluate(() => ({
  hints:   document.querySelectorAll('.ab-trans-toggle--hint').length,
  toggles: document.querySelectorAll('.ab-trans-toggle:not(.ab-trans-toggle--hint)').length,
}))

;(async () => {
  console.log(`\n📸 Multilingual reactive toggle (no-reload) — ${BASE}\n`)
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] })
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 1000 }, ignoreHTTPSErrors: true })
  const page = await ctx.newPage()

  // SAFETY: never let the real integration toggle persist on the live site.
  await page.route('**/*task=integrations.saveToggle*', async (route) => {
    await route.fulfill({ status: 200, contentType: 'application/json; charset=utf-8', body: JSON.stringify({ success: true }) })
  })

  try {
    await login(page)
    for (const theme of ['light', 'dark']) {
      const outDir = resolve(OUT_ROOT, theme)
      console.log(`── ${theme}`)

      // Warm up so the SPA materialises the translation singleton + integrations.
      await page.goto(APP_URL + '#/settings?tab=org', { waitUntil: 'domcontentloaded', timeout: 45000 })
      await page.waitForSelector('#ab-app', { timeout: 15000 })
      await page.waitForTimeout(2200)
      await setTheme(page, theme)
      const before = await countState(page)
      await page.screenshot({ path: resolve(outDir, 'before.png'), fullPage: true })

      // Integrations → click the Multilingual card switch OFF (save is faked).
      await page.goto(APP_URL + '#/integrations', { waitUntil: 'domcontentloaded', timeout: 45000 })
      await page.waitForTimeout(2200)
      const sw = page.locator('.ab-int-card:has(.ab-int-card__name-text:text-is("Multilingual")) .ab-toggle--onoff').first()
      await sw.click()
      await page.waitForTimeout(600)

      // Hash-navigate back to Site Identity WITHOUT a reload.
      await page.goto(APP_URL + '#/settings?tab=org', { waitUntil: 'domcontentloaded', timeout: 45000 })
      await page.waitForTimeout(1500)
      await setTheme(page, theme)
      const after = await countState(page)
      await page.screenshot({ path: resolve(outDir, 'after.png'), fullPage: true })

      console.log('   before(ON)=' + JSON.stringify(before) + '  after-toggle-OFF(no reload)=' + JSON.stringify(after))
    }
    console.log(`\n✅ Done → ${OUT_ROOT}`)
  } finally {
    await browser.close()
  }
})()
