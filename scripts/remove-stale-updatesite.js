#!/usr/bin/env node
/**
 * remove-stale-updatesite.js — delete the stale "updates.aiboostnow.com" update
 * site left over from an old Pro build (the subdomain is NXDOMAIN, so Joomla
 * shows "Could not open update site #<id>"). Targets the row by its exact id
 * via the cid[] checkbox value, so the valid Free update site is never touched.
 *
 *   python _creds_run.py scripts/run-node-test.py scripts/remove-stale-updatesite.js --target staging [--id 314]
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const args = process.argv.slice(2)
const ti = args.indexOf('--target')
const TARGET = ti !== -1 ? args[ti + 1] : 'staging'
const ii = args.indexOf('--id')
const IDS = ii !== -1 ? [args[ii + 1]] : ['314', '317'] // known stale ids; harmless if absent

function env(k) { const v = process.env[k]; if (!v) { console.error('Missing env ' + k); process.exit(1) } return v }
let ADMIN_URL, USER, PASS
if (TARGET === 'free') { ADMIN_URL = env('FREE_URL'); USER = env('FREE_ADMIN_USER'); PASS = env('FREE_ADMIN_PASS') }
else { ADMIN_URL = env('STAGING_URL'); USER = env('STAGING_ADMIN_USER'); PASS = env('STAGING_ADMIN_PASS') }

const parsed = new NodeURL(ADMIN_URL)
const BASE = `${parsed.protocol}//${parsed.host}`
const SITES_URL = `${BASE}/administrator/index.php?option=com_installer&view=updatesites&list[limit]=100`

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
  page.on('dialog', d => d.accept().catch(() => {}))
  let failed = false
  try {
    await login(page)
    await page.goto(SITES_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
    await page.waitForLoadState('networkidle', { timeout: 20000 }).catch(() => {})

    // Inventory: dump every AI Boost update site row (id + name) for the record.
    const rows = await page.evaluate(() => {
      const out = []
      document.querySelectorAll('input[name="cid[]"]').forEach(cb => {
        const tr = cb.closest('tr')
        const name = tr ? tr.innerText.replace(/\s+/g, ' ').trim().slice(0, 120) : ''
        out.push({ id: cb.value, name })
      })
      return out
    })
    console.log('\n  Update sites on ' + TARGET + ':')
    rows.forEach(r => console.log(`    #${r.id}  ${r.name}`))

    let deletedAny = false
    for (const id of IDS) {
      const cb = page.locator(`input[name="cid[]"][value="${id}"]`)
      if (await cb.count() === 0) { console.log(`\n  #${id}: not present (already gone) — ok`); continue }
      const rowText = await cb.evaluate(el => (el.closest('tr')?.innerText || '').replace(/\s+/g, ' ').trim())
      console.log(`\n  Deleting #${id}: ${rowText.slice(0, 100)}`)
      await cb.check({ force: true })
      // Trigger the Delete toolbar action.
      const ok = await page.evaluate(() => {
        if (window.Joomla && typeof Joomla.submitbutton === 'function') { Joomla.submitbutton('updatesites.delete'); return true }
        const btn = document.querySelector('#toolbar-delete button, button.button-delete, #toolbar-delete')
        if (btn) { btn.click(); return true }
        return false
      })
      if (!ok) { console.log('  ❌ could not trigger Delete toolbar'); failed = true; continue }
      await page.waitForLoadState('domcontentloaded', { timeout: 30000 }).catch(() => {})
      await page.waitForTimeout(1500)
      deletedAny = true
    }

    // Verify the targeted ids are gone.
    await page.goto(SITES_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
    await page.waitForTimeout(800)
    for (const id of IDS) {
      const still = await page.locator(`input[name="cid[]"][value="${id}"]`).count()
      console.log(`  verify #${id}: ${still === 0 ? '✅ gone' : '❌ STILL PRESENT'}`)
      if (still > 0) failed = true
    }
    if (!deletedAny && !failed) console.log('\n  Nothing to delete — staging already clean.')
    else if (!failed) console.log('\n  ✅ Stale update site(s) removed.')
  } catch (e) {
    console.log('  ❌ ERROR: ' + (e && e.message ? e.message : e)); failed = true
  } finally { await browser.close() }
  process.exit(failed ? 1 : 0)
})()
