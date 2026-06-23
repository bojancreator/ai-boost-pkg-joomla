#!/usr/bin/env node
/**
 * AI Boost for Joomla — UI/UX Audit Screenshots
 *
 * Captures every SPA page in light AND dark theme for the Plan 2 UI/UX audit.
 * Reuses the login pattern from test-all-settings.js.
 *
 * Output: artifacts/ui-audit/<theme>/<slug>.png
 *
 * Usage:
 *   node scripts/ui-audit-screenshots.js [--target staging|free] [--visible]
 *
 * Required env vars (via _creds_run.py):
 *   STAGING_URL / STAGING_ADMIN_USER / STAGING_ADMIN_PASS  (default)
 *   FREE_URL / FREE_ADMIN_USER / FREE_ADMIN_PASS            (--target free)
 */

import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'
import { existsSync, mkdirSync } from 'fs'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')
const __dirname = dirname(fileURLToPath(import.meta.url))

// ── CLI args ────────────────────────────────────────────────────────────────
const args = process.argv.slice(2)
const VISIBLE = args.includes('--visible')
const targetIdx = args.indexOf('--target')
const TARGET = targetIdx !== -1 ? args[targetIdx + 1] : 'staging'

function env(key) {
  const v = process.env[key]
  if (!v) { console.error(`❌ Missing env var: ${key}`); process.exit(1) }
  return v
}

let ADMIN_URL, ADMIN_USER, ADMIN_PASS
if (TARGET === 'free') {
  ADMIN_URL  = env('FREE_URL')
  ADMIN_USER = env('FREE_ADMIN_USER')
  ADMIN_PASS = env('FREE_ADMIN_PASS')
} else {
  ADMIN_URL  = env('STAGING_URL')
  ADMIN_USER = env('STAGING_ADMIN_USER')
  ADMIN_PASS = env('STAGING_ADMIN_PASS')
}

const parsed  = new NodeURL(ADMIN_URL)
const BASE    = `${parsed.protocol}//${parsed.host}`
const APP_URL = `${BASE}/administrator/index.php?option=com_aiboost&view=app`

// Output dirs
const OUT_ROOT = resolve(__dirname, '..', 'artifacts', 'ui-audit')
const OUT_LIGHT = resolve(OUT_ROOT, 'light')
const OUT_DARK  = resolve(OUT_ROOT, 'dark')
;[OUT_ROOT, OUT_LIGHT, OUT_DARK].forEach(d => { if (!existsSync(d)) mkdirSync(d, { recursive: true }) })

// ── Pages to capture ────────────────────────────────────────────────────────
// Each entry: { slug, hash, label }
// hash = the #/… part appended to APP_URL
const PAGES = [
  // Standalone pages
  { slug: '01-dashboard',    hash: '#/dashboard',            label: 'Dashboard' },
  { slug: '02-health',       hash: '#/health',               label: 'Health' },
  { slug: '03-health-errors',hash: '#/health/errors',        label: 'Health – Errors filter' },
  { slug: '04-autopilot',    hash: '#/autopilot',            label: 'Autopilot' },
  { slug: '05-integrations', hash: '#/integrations',         label: 'Integrations' },
  { slug: '06-licenses',     hash: '#/licenses',             label: 'License & Updates' },
  { slug: '07-redirects',    hash: '#/redirects',            label: 'Redirects' },
  { slug: '08-analyzers',    hash: '#/analyzers',            label: 'Analyzers' },
  { slug: '09-urlchecker',   hash: '#/urlchecker',           label: 'URL Checker' },
  { slug: '10-import',       hash: '#/import',               label: 'Import' },
  { slug: '11-help',         hash: '#/help',                 label: 'Help' },
  // Settings tabs
  { slug: '12-settings-technical', hash: '#/settings',            label: 'Settings – Technical SEO' },
  { slug: '13-settings-org',       hash: '#/settings?tab=org',    label: 'Settings – Site Identity' },
  { slug: '14-settings-schema',    hash: '#/settings?tab=schema', label: 'Settings – Schema.org' },
  { slug: '15-settings-sitemap',   hash: '#/settings?tab=sitemap',label: 'Settings – Sitemap' },
  { slug: '16-settings-social',    hash: '#/settings?tab=social', label: 'Settings – Social Meta/OG' },
  { slug: '17-settings-analytics', hash: '#/settings?tab=analytics', label: 'Settings – Analytics' },
  { slug: '18-settings-aeo',       hash: '#/settings?tab=aeo',    label: 'Settings – AI Visibility' },
  { slug: '19-settings-crawlers',  hash: '#/settings?tab=crawlers',label: 'Settings – Crawlers & Robots' },
  { slug: '20-settings-code',      hash: '#/settings?tab=code',   label: 'Settings – Custom Code' },
  { slug: '21-settings-debug',     hash: '#/settings?tab=debug',  label: 'Settings – Debug' },
  // Styleguide
  { slug: '22-styleguide',   hash: '#/_styleguide',          label: '/_styleguide' },
  // What's New (changelog) — captured last; opening it marks the version seen.
  { slug: '23-changelog',    hash: '#/changelog',            label: "What's New" },
]

// ── Helpers ─────────────────────────────────────────────────────────────────
async function login(page) {
  await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
  try {
    await page.waitForSelector('input[name=username]', { timeout: 8000 })
  } catch {
    return // already logged in
  }
  await page.fill('input[name=username]', ADMIN_USER)
  await page.fill('input[name=passwd]', ADMIN_PASS)
  await page.press('input[name=passwd]', 'Enter')
  await page.waitForLoadState('domcontentloaded', { timeout: 30000 })
  console.log(`  Logged in → ${page.url().substring(0, 80)}`)
}

async function gotoPage(page, hash) {
  const url = APP_URL + (hash ? hash : '')
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 })
  await page.waitForSelector('#ab-app', { timeout: 15000 })
  // Wait for Vue router + any initial data fetches
  await page.waitForTimeout(2000)
}

async function setTheme(page, theme) {
  // Flip Joomla's data-bs-theme attribute on the <html> element
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-bs-theme', t)
    document.body.setAttribute('data-bs-theme', t)
    // Atum also uses a class on body for its own dark mode
    if (t === 'dark') {
      document.body.classList.add('dark-mode')
    } else {
      document.body.classList.remove('dark-mode')
    }
  }, theme)
  // Let CSS transitions settle
  await page.waitForTimeout(400)
}

// ── Main ────────────────────────────────────────────────────────────────────
;(async () => {
  console.log(`\n📸 AI Boost UI Audit Screenshots  [target: ${TARGET}  visible: ${VISIBLE}]`)
  console.log(`   Base: ${BASE}`)
  console.log(`   Output: ${OUT_ROOT}`)
  console.log(`   Pages: ${PAGES.length} × 2 themes = ${PAGES.length * 2} screenshots\n`)

  const browser = await chromium.launch({ headless: !VISIBLE, args: ['--no-sandbox'] })
  const ctx = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    // Disable SSL errors for staging (same as AIBOOST_NO_SSL_VERIFY pattern)
    ignoreHTTPSErrors: true,
  })
  const page = await ctx.newPage()

  const consoleErrors = []
  page.on('console', msg => {
    if (msg.type() === 'error') consoleErrors.push({ url: page.url(), text: msg.text() })
  })

  try {
    console.log('── 1. Login')
    await login(page)

    for (const theme of ['light', 'dark']) {
      const outDir = theme === 'light' ? OUT_LIGHT : OUT_DARK
      console.log(`\n── Theme: ${theme.toUpperCase()}`)

      for (const p of PAGES) {
        process.stdout.write(`   ${p.slug} — ${p.label} … `)
        try {
          await gotoPage(page, p.hash)
          await setTheme(page, theme)

          const outPath = resolve(outDir, `${p.slug}.png`)
          await page.screenshot({ path: outPath, fullPage: true })
          console.log(`✅`)
        } catch (err) {
          console.log(`❌  ${err.message.substring(0, 80)}`)
        }
      }
    }

    // Summary
    console.log('\n── Console errors captured during run:')
    if (consoleErrors.length === 0) {
      console.log('   None')
    } else {
      consoleErrors.slice(0, 20).forEach(e => console.log(`   [${e.url.substring(0, 60)}] ${e.text.substring(0, 100)}`))
      if (consoleErrors.length > 20) console.log(`   … and ${consoleErrors.length - 20} more`)
    }

    console.log(`\n✅ Done. Screenshots saved to: ${OUT_ROOT}`)
    console.log('   Open artifacts/ui-audit/light/ and artifacts/ui-audit/dark/ to view.')

  } finally {
    await browser.close()
  }
})()
