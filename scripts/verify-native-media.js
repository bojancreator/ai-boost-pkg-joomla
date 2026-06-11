#!/usr/bin/env node
/**
 * Verify the SPA MediaPicker now mounts the NATIVE Joomla media field
 * (joomla-field-media web component) and that clicking Select opens the real
 * Joomla/JCE media manager (folder tree), not our old custom modal.
 *
 * Env: STAGING_URL / STAGING_ADMIN_USER / STAGING_ADMIN_PASS
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const FREE = process.argv.includes('free') || process.argv.includes('--target=free')
const U = FREE ? process.env.FREE_URL : process.env.STAGING_URL
const A = FREE ? process.env.FREE_ADMIN_USER : process.env.STAGING_ADMIN_USER
const P = FREE ? process.env.FREE_ADMIN_PASS : process.env.STAGING_ADMIN_PASS
if (!U || !A || !P) { console.error('Missing creds for target ' + (FREE ? 'free' : 'staging')); process.exit(1) }
const p = new NodeURL(U), ADMIN = `${p.protocol}//${p.host}/administrator/index.php`

let failures = 0
const ok  = (m) => console.log('  ✓ ' + m)
const bad = (m) => { console.log('  ✗ ' + m); failures++ }

const b = await chromium.launch({ headless: true })
const c = await b.newContext({ viewport: { width: 1400, height: 950 } })
const pg = await c.newPage()
const logs = []
pg.on('console', m => { if (m.type() === 'error') logs.push(m.text()) })
pg.on('pageerror', e => logs.push('pageerror: ' + e.message))

await pg.goto(U, { waitUntil: 'domcontentloaded' })
if (!(await pg.locator('input[name="username"]').count())) await pg.goto(ADMIN, { waitUntil: 'domcontentloaded' })
await pg.fill('input[name="username"]', A); await pg.fill('input[name="passwd"]', P)
await Promise.all([pg.waitForLoadState('networkidle'), pg.click('button[type="submit"]')])

console.log('→ Open SPA (view=app) → Settings ...')
await pg.goto(`${ADMIN}?option=com_aiboost&view=app`, { waitUntil: 'networkidle' })
await pg.waitForTimeout(1800)
const openSettings = pg.locator('a, button').filter({ hasText: /Open Settings|^Settings$/ }).first()
if (await openSettings.count()) { await openSettings.click().catch(() => {}); await pg.waitForTimeout(1200) }

// Click a tab likely to contain a MediaPicker (Site Identity / Organization / Social)
for (const re of [/Site Identity|Organization|Identity/, /Social/]) {
  const tab = pg.locator('a, button').filter({ hasText: re }).first()
  if (await tab.count()) { await tab.click().catch(() => {}); await pg.waitForTimeout(900); break }
}

// 1) Native field present?
const nativeSel = '.ab-media-picker__native joomla-field-media'
await pg.waitForTimeout(600)
const nativeCount = await pg.locator(nativeSel).count()
if (nativeCount > 0) ok(`Native joomla-field-media mounted in MediaPicker (${nativeCount} found)`)
else { bad('No native joomla-field-media in any MediaPicker'); }

// 2) Inspect the field: url (native vs JCE) + Select button
const detail = await pg.evaluate((sel) => {
  const el = document.querySelector(sel)
  if (!el) return null
  return {
    url: el.getAttribute('url') || '',
    hasSelect: !!el.querySelector('.button-select, button'),
    customElDefined: !!(window.customElements && customElements.get('joomla-field-media')),
    upgraded: el.constructor && el.constructor.name !== 'HTMLElement',
  }
}, nativeSel)
if (detail) {
  ok(`custom element defined=${detail.customElDefined}, upgraded=${detail.upgraded}`)
  const isJce = /option=com_jce/i.test(detail.url)
  const isMedia = /option=com_media/i.test(detail.url)
  if (isJce) ok('Field URL routes to JCE browser (JCE installed) — exactly the configured default')
  else if (isMedia) ok('Field URL routes to native com_media manager')
  else bad('Field URL is neither com_jce nor com_media: ' + detail.url.slice(0, 120))
  detail.hasSelect ? ok('Select button present') : bad('No Select button in field')
}

// 3) Click Select → does the real media manager modal/dialog open?
if (nativeCount > 0) {
  console.log('→ Clicking Select ...')
  const selBtn = pg.locator(`${nativeSel} .button-select`).first()
  if (await selBtn.count()) {
    const popupP = pg.waitForEvent('popup', { timeout: 3000 }).catch(() => null)
    await selBtn.click().catch(e => console.log('  click err: ' + e.message))
    await pg.waitForTimeout(2500)
    const popup = await popupP
    const modal = await pg.evaluate(() => ({
      dialogs: document.querySelectorAll('joomla-dialog, dialog[open], .modal.show, .joomla-modal').length,
      iframes: [...document.querySelectorAll('iframe')].map(f => f.src).filter(s => /com_jce|com_media|mediafield|browser/i.test(s)),
    }))
    const opened = popup || modal.dialogs > 0 || modal.iframes.length > 0
    if (opened) {
      ok(`Media manager opened (dialogs=${modal.dialogs}, mediaIframes=${modal.iframes.length}${popup ? ', popup' : ''})`)
      if (modal.iframes[0]) console.log('    iframe: ' + modal.iframes[0].slice(0, 110))
    } else {
      bad('Select clicked but no media manager modal/dialog/iframe opened')
    }
  } else bad('Select button not clickable')
}

if (logs.length) { console.log('--- console errors ---'); logs.slice(-8).forEach(l => console.log('   ' + l)) }
console.log('\n' + '═'.repeat(50))
console.log(failures === 0 ? '✅ NATIVE MEDIA PASS' : `❌ FAIL (${failures})`)
console.log('═'.repeat(50))
await b.close()
process.exit(failures === 0 ? 0 : 1)
