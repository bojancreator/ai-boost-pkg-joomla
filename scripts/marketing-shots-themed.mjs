#!/usr/bin/env node
/**
 * AI Boost — THEME-MATCHED marketing screenshots for the website frames.
 *
 * Produces a LIGHT and a DARK shot of the same 7 panels so the site can swap the
 * image with its theme toggle. Fixes the "dark chrome + white panels" problem by
 * forcing a COHERENT theme: Playwright color-scheme emulation (prefers-color-scheme)
 * AND Joomla's `data-bs-theme` flip together, so the AI Boost cards and the chrome
 * agree. Component-only (tmpl=component + #ab-app), neutral Acme demo data, DB untouched.
 *
 * Output: deliverables/screenshots/shot-<panel>-<light|dark>.png
 *
 * Usage:  python _creds_run.py scripts/marketing-shots-themed.mjs [--target staging|free]
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

const args = process.argv.slice(2)
const VISIBLE = args.includes('--visible')
const targetIdx = args.indexOf('--target')
const TARGET = targetIdx !== -1 ? args[targetIdx + 1] : 'staging'

function env(key) {
  const v = process.env[key]
  if (!v) { console.error(`❌ Missing env var: ${key} (run via: python _creds_run.py scripts/marketing-shots-themed.mjs)`); process.exit(1) }
  return v
}

let ADMIN_URL, ADMIN_USER, ADMIN_PASS
if (TARGET === 'free') {
  ADMIN_URL = env('FREE_URL'); ADMIN_USER = env('FREE_ADMIN_USER'); ADMIN_PASS = env('FREE_ADMIN_PASS')
} else {
  ADMIN_URL = env('STAGING_URL'); ADMIN_USER = env('STAGING_ADMIN_USER'); ADMIN_PASS = env('STAGING_ADMIN_PASS')
}

const parsed  = new NodeURL(ADMIN_URL)
const BASE    = `${parsed.protocol}//${parsed.host}`
// Load the FULL admin page so ALL CSS (Atum template + Bootstrap + component) is
// present — `tmpl=component` would strip the template CSS and break toggle colours
// and some cards. We remove the Joomla chrome by screenshotting only the #ab-app
// element (the Joomla admin menu lives outside it).
const APP_URL = `${BASE}/administrator/index.php?option=com_aiboost&view=app`

const OUT = resolve(REPO, 'deliverables', 'screenshots')
if (!existsSync(OUT)) mkdirSync(OUT, { recursive: true })

const DEMO = JSON.parse(readFileSync(resolve(__dirname, 'fixtures', 'screenshot-demo-data.json'), 'utf8'))

// The 7 panels the website renders inside frames (names per Claude Design's spec).
const PANELS = [
  { name: 'dashboard', hash: '#/dashboard' },
  { name: 'aeo',       hash: '#/settings?tab=aeo' },
  { name: 'crawlers',  hash: '#/settings?tab=crawlers' },
  { name: 'schema',    hash: '#/settings?tab=schema' },
  { name: 'social',    hash: '#/settings?tab=social' },
  { name: 'analytics', hash: '#/settings?tab=analytics' },
  { name: 'conflicts', hash: '#/conflicts' },
  { name: 'integrations', hash: '#/integrations' },
]

async function login(page) {
  await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
  try {
    await page.waitForSelector('input[name=username]', { timeout: 8000 })
  } catch { return }
  await page.fill('input[name=username]', ADMIN_USER)
  await page.fill('input[name=passwd]', ADMIN_PASS)
  await page.press('input[name=passwd]', 'Enter')
  await page.waitForLoadState('domcontentloaded', { timeout: 30000 })
  console.log(`  Logged in → ${page.url().substring(0, 80)}`)
}

async function installDemoOverlay(page) {
  await page.addInitScript((demo) => {
    try {
      let _store
      Object.defineProperty(window, 'aiBoostSettings', {
        configurable: true,
        get() { return _store },
        set(val) { _store = (val && typeof val === 'object') ? Object.assign({}, val, demo) : val },
      })
    } catch (e) { /* fall back to real data */ }
  }, DEMO.settings)
}

async function setTheme(page, theme) {
  await page.evaluate((t) => {
    for (const el of [document.documentElement, document.body]) {
      el.setAttribute('data-bs-theme', t)
    }
    document.body.classList.toggle('dark-mode', t === 'dark')
    // If the SPA persists its own theme, nudge common keys too (harmless if unused).
    try { localStorage.setItem('aiboost.theme', t); localStorage.setItem('ab-theme', t) } catch (e) {}
  }, theme)
  await page.waitForTimeout(450) // let CSS transitions settle
}

async function gotoPanel(page, hash) {
  await page.goto(APP_URL + hash, { waitUntil: 'domcontentloaded', timeout: 45000 })
  await page.waitForSelector('#ab-app', { timeout: 15000 })
  await page.waitForTimeout(2200) // Vue router + initial data
}

;(async () => {
  console.log(`\n📸 AI Boost themed marketing shots  [target: ${TARGET}]`)
  console.log(`   Base: ${BASE}`)
  console.log(`   ${PANELS.length} panels × light/dark = ${PANELS.length * 2} shots → ${OUT}\n`)

  const browser = await chromium.launch({ headless: !VISIBLE, args: ['--no-sandbox'] })
  try {
    for (const theme of ['light', 'dark']) {
      console.log(`── Theme: ${theme.toUpperCase()}`)
      // A fresh context per theme so prefers-color-scheme is coherent from first paint.
      const ctx = await browser.newContext({
        // Wide enough that the AI Boost component (#ab-app) lands ~1400px after the
        // Joomla admin menu (~260px) — full page keeps ALL CSS loaded.
        viewport: { width: 1680, height: 1100 },
        ignoreHTTPSErrors: true,
        colorScheme: theme, // emulates prefers-color-scheme — the key fix
      })
      const page = await ctx.newPage()
      await installDemoOverlay(page)
      await login(page)

      for (const p of PANELS) {
        process.stdout.write(`   shot-${p.name}-${theme} … `)
        try {
          await gotoPanel(page, p.hash)
          await setTheme(page, theme)
          const el = await page.$('#ab-app')
          const out = resolve(OUT, `shot-${p.name}-${theme}.png`)
          if (el) {
            await el.screenshot({ path: out })
          } else {
            await page.screenshot({ path: out, fullPage: true })
          }
          console.log('✅')
        } catch (err) {
          console.log(`❌  ${String(err.message).substring(0, 80)}`)
        }
      }
      await ctx.close()
    }
    console.log(`\n✅ Done → ${OUT}  (shot-*-light.png / shot-*-dark.png)`)
  } finally {
    await browser.close()
  }
})()
