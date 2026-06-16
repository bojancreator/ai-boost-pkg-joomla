#!/usr/bin/env node
/**
 * AI Boost for Joomla — marketing / Lemon Squeezy store screenshots.
 *
 * Captures the admin SPA pages for the storefront, with NEUTRAL US demo data
 * (Acme Corporation / example.com / 555 numbers) instead of the real staging
 * site's content. The demo data is applied as a NETWORK OVERLAY on the
 * settings.getSettings response — the database is never modified, so this is
 * safe to run against a live staging site.
 *
 * Run against a Pro staging site so Pro features render unlocked.
 *
 * Output: deliverables/screenshots/<slug>.png  (gitignored)
 *
 * Usage (via the creds wrapper so STAGING_* env vars are loaded):
 *   python _creds_run.py scripts/marketing-screenshots.mjs
 *   python _creds_run.py scripts/marketing-screenshots.mjs --target staging --visible
 *
 * Required env (same names as scripts/ui-audit-screenshots.js):
 *   STAGING_URL / STAGING_ADMIN_USER / STAGING_ADMIN_PASS  (default)
 *   FREE_URL / FREE_ADMIN_USER / FREE_ADMIN_PASS            (--target free)
 */

import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'
import { existsSync, mkdirSync, readFileSync } from 'fs'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')
const __dirname = dirname(fileURLToPath(import.meta.url))
const REPO = resolve(__dirname, '..')

// ── CLI args ────────────────────────────────────────────────────────────────
const args = process.argv.slice(2)
const VISIBLE = args.includes('--visible')
const targetIdx = args.indexOf('--target')
const TARGET = targetIdx !== -1 ? args[targetIdx + 1] : 'staging'

function env(key) {
  const v = process.env[key]
  if (!v) { console.error(`❌ Missing env var: ${key} (run via: python _creds_run.py scripts/marketing-screenshots.mjs)`); process.exit(1) }
  return v
}

let ADMIN_URL, ADMIN_USER, ADMIN_PASS
if (TARGET === 'free') {
  ADMIN_URL = env('FREE_URL'); ADMIN_USER = env('FREE_ADMIN_USER'); ADMIN_PASS = env('FREE_ADMIN_PASS')
} else {
  ADMIN_URL = env('STAGING_URL'); ADMIN_USER = env('STAGING_ADMIN_USER'); ADMIN_PASS = env('STAGING_ADMIN_PASS')
}

const parsed = new NodeURL(ADMIN_URL)
const BASE = `${parsed.protocol}//${parsed.host}`
const APP_URL = `${BASE}/administrator/index.php?option=com_aiboost&view=app`

const OUT = resolve(REPO, 'deliverables', 'screenshots')
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true })

const DEMO = JSON.parse(readFileSync(resolve(__dirname, 'fixtures', 'screenshot-demo-data.json'), 'utf8'))

// ── Marketing shot list (curated subset; light theme, clean) ─────────────────
const PAGES = [
  { slug: '01-dashboard',   hash: '#/dashboard',              label: 'Dashboard' },
  { slug: '02-autopilot',   hash: '#/autopilot',              label: 'Autopilot' },
  { slug: '03-site-identity', hash: '#/settings?tab=org',     label: 'Site Identity' },
  { slug: '04-schema',      hash: '#/settings?tab=schema',    label: 'Schema.org' },
  { slug: '05-social',      hash: '#/settings?tab=social',    label: 'Social Meta / OpenGraph' },
  { slug: '06-analytics',   hash: '#/settings?tab=analytics', label: 'Analytics & Tracking' },
  { slug: '07-aeo',         hash: '#/settings?tab=aeo',       label: 'AI Visibility (AEO)' },
  { slug: '08-crawlers',    hash: '#/settings?tab=crawlers',  label: 'Crawlers & Robots' },
  { slug: '09-health',      hash: '#/health',                 label: 'Health Check' },
  { slug: '10-integrations',hash: '#/integrations',           label: 'Integrations' },
  { slug: '11-licenses',    hash: '#/licenses',               label: 'License & Updates' },
  { slug: '12-conflicts',   hash: '#/conflicts',              label: 'Conflict Manager' },
  { slug: '13-analyzers',   hash: '#/analyzers',              label: 'Analyzers' },
  { slug: '14-redirects',   hash: '#/redirects',              label: 'Redirects' },
]

// ── Helpers (login + goto pattern reused from ui-audit-screenshots.js) ───────
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
  await page.goto(APP_URL + (hash || ''), { waitUntil: 'domcontentloaded', timeout: 45000 })
  await page.waitForSelector('#ab-app', { timeout: 15000 })
  await page.waitForTimeout(2200) // Vue router + initial data fetches
}

// Overlay neutral demo data so no real customer data is captured. The settings
// form hydrates from the inline `window.aiBoostSettings` global (api.js
// getSettings()), NOT an AJAX call — so we intercept the assignment of that
// global via a property descriptor installed before any page script runs, and
// merge the demo values over whatever the PHP shell injected. Read-only: the
// database is never written.
async function installDemoOverlay(page) {
  await page.addInitScript((demo) => {
    try {
      let _store
      Object.defineProperty(window, 'aiBoostSettings', {
        configurable: true,
        get() { return _store },
        set(val) {
          _store = (val && typeof val === 'object') ? Object.assign({}, val, demo) : val
        },
      })
    } catch (e) { /* non-fatal: fall back to real data */ }
  }, DEMO.settings)
}

// ── Main ─────────────────────────────────────────────────────────────────────
;(async () => {
  console.log(`\n📸 AI Boost marketing screenshots  [target: ${TARGET}  visible: ${VISIBLE}]`)
  console.log(`   Base: ${BASE}`)
  console.log(`   Demo data: neutral US placeholders (DB untouched)`)
  console.log(`   Output: ${OUT}\n`)

  const browser = await chromium.launch({ headless: !VISIBLE, args: ['--no-sandbox'] })
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true })
  const page = await ctx.newPage()
  await installDemoOverlay(page)

  try {
    console.log('── Login')
    await login(page)
    console.log('── Capturing pages')
    for (const p of PAGES) {
      process.stdout.write(`   ${p.slug} — ${p.label} … `)
      try {
        await gotoPage(page, p.hash)
        await page.screenshot({ path: resolve(OUT, `${p.slug}.png`), fullPage: true })
        console.log('✅')
      } catch (err) {
        console.log(`❌  ${String(err.message).substring(0, 80)}`)
      }
    }
    console.log(`\n✅ Done → ${OUT}`)
  } finally {
    await browser.close()
  }
})()
