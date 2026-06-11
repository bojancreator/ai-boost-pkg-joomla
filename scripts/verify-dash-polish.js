#!/usr/bin/env node
/**
 * Verify dashboard polish (v0.73.44):
 *  - Tier badges (PRO / FREE/PRO) HIDDEN on Pro edition, PRESENT on Free.
 *  - Card header row uses flex-wrap so the status badge ("Enabled") never clips.
 *  - "prazan prostor": report the empty band above the SPA shell (margin-top).
 *  - Capture dashboard screenshots (full + top strip) for visual confirmation.
 * Run with --target staging (Pro) or `free` (no Pro).
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
const require = createRequire(import.meta.url)
const { chromium } = require('playwright')
const FREE = process.argv.includes('free') || process.argv.includes('--target=free')
const U = FREE ? process.env.FREE_URL : process.env.STAGING_URL
const A = FREE ? process.env.FREE_ADMIN_USER : process.env.STAGING_ADMIN_USER
const P = FREE ? process.env.FREE_ADMIN_PASS : process.env.STAGING_ADMIN_PASS
if (!U || !A || !P) { console.error('Missing creds'); process.exit(1) }
const p = new NodeURL(U), ADMIN = `${p.protocol}//${p.host}/administrator/index.php`
const edition = FREE ? 'FREE' : 'PRO'
let fail = 0
const ok = m => console.log('  ✓ ' + m), bad = m => { console.log('  ✗ ' + m); fail++ }
const b = await chromium.launch({ headless: true })
const c = await b.newContext({ viewport: { width: 1500, height: 1000 } })
const pg = await c.newPage()
await pg.goto(U, { waitUntil: 'domcontentloaded' })
if (!(await pg.locator('input[name="username"]').count())) await pg.goto(ADMIN, { waitUntil: 'domcontentloaded' })
await pg.fill('input[name="username"]', A); await pg.fill('input[name="passwd"]', P)
await Promise.all([pg.waitForLoadState('networkidle'), pg.click('button[type="submit"]')])

console.log(`→ ${p.host} (expect ${edition})`)
await pg.goto(`${ADMIN}?option=com_aiboost&view=app`, { waitUntil: 'networkidle' }); await pg.waitForTimeout(1800)

const boot = await pg.evaluate(() => window.aiBoostBootstrap || {})
if (!FREE) (boot.isProInstall ? ok('isProInstall=true (Pro env)') : bad('isProInstall=false on staging — pro pkg missing'))

// ── Dashboard card inspection ───────────────────────────────────────────────
const info = await pg.evaluate(() => {
  const cards = [...document.querySelectorAll('.ab-module-card')]
  const out = []
  for (const card of cards) {
    const label = (card.querySelector('.fw-bold')?.textContent || '').trim()
    const tier = card.querySelector('.ab-tier-badge--pro') ? 'PRO'
               : card.querySelector('.ab-tier-badge--mixed') ? 'FREE/PRO' : 'none'
    const statusEl = card.querySelector('.ab-badge')
    const statusText = (statusEl?.textContent || '').trim()
    // Does the status badge overflow its row (visual clip)?
    let clipped = false
    if (statusEl) {
      const row = statusEl.parentElement
      const rb = row.getBoundingClientRect(), sb = statusEl.getBoundingClientRect()
      clipped = sb.right > rb.right + 1
    }
    // The header row is the icon's nearest flex container (the card body is
    // also .d-flex but flex-column, so select by the icon to avoid matching it).
    const head = card.querySelector('.ab-plugin-icon')?.closest('.d-flex')
    const wraps = head ? getComputedStyle(head).flexWrap : ''
    out.push({ label, tier, statusText, clipped, wraps })
  }
  return out
})
console.log('  cards: ' + JSON.stringify(info))

// flex-wrap present on header rows (anti-clip structural fix)
const allWrap = info.length > 0 && info.every(c => c.wraps === 'wrap')
allWrap ? ok('card header rows use flex-wrap (status badge cannot clip)')
        : bad('some card header rows are not flex-wrap: ' + JSON.stringify(info.map(c => c.wraps)))

// no status badge overflows its row
const anyClip = info.some(c => c.clipped)
anyClip ? bad('a status badge still overflows/clips its row') : ok('no status badge clips ("Enabled" fully visible)')

// tier badge visibility per edition
const tiers = info.map(c => c.tier)
if (FREE) {
  const code = info.find(c => /custom code/i.test(c.label))?.tier
  code === 'PRO' ? ok('Custom Code card = PRO (free edition)') : bad(`Custom Code tier = ${code}`)
  tiers.includes('FREE/PRO') ? ok('mixed cards show FREE/PRO (free edition)') : bad('no FREE/PRO badges on free')
} else {
  tiers.every(t => t === 'none')
    ? ok('tier badges hidden on Pro edition (everything implied Pro)')
    : bad(`tier badges should be HIDDEN on Pro (got ${JSON.stringify(tiers)})`)
}

// ── "prazan prostor" — empty band above the SPA shell ───────────────────────
const gap = await pg.evaluate(() => {
  const shell = document.querySelector('.ab-spa-shell--sidebar') || document.querySelector('#ab-app')
  if (!shell) return null
  const cs = getComputedStyle(shell)
  return { marginTop: cs.marginTop, top: Math.round(shell.getBoundingClientRect().top) }
})
console.log(`  SPA shell: margin-top=${gap?.marginTop}, viewport-top=${gap?.top}px`)
if (gap && parseFloat(gap.marginTop) <= 6) ok(`top empty band trimmed (margin-top ${gap.marginTop})`)
else bad(`top empty band still large (margin-top ${gap?.marginTop})`)

// ── Screenshots ─────────────────────────────────────────────────────────────
const tag = edition.toLowerCase()
await pg.screenshot({ path: `dash-polish-${tag}-full.png`, fullPage: false })
await pg.screenshot({ path: `dash-polish-${tag}-top.png`, clip: { x: 0, y: 0, width: 1500, height: 360 } })
// Zoom on the module-card grid (badge layout close-up)
const grid = await pg.locator('.ab-module-card').first().boundingBox().catch(() => null)
if (grid) {
  await pg.screenshot({ path: `dash-polish-${tag}-cards.png`,
    clip: { x: Math.max(0, grid.x - 8), y: Math.max(0, grid.y - 8), width: 1180, height: 230 } })
  console.log(`  screenshots: dash-polish-${tag}-full.png, -top.png, -cards.png`)
} else {
  console.log(`  screenshots: dash-polish-${tag}-full.png, -top.png`)
}

console.log(fail === 0 ? '\n✅ DASH POLISH PASS' : `\n❌ FAIL (${fail})`)
await b.close()
process.exit(fail === 0 ? 0 : 1)
