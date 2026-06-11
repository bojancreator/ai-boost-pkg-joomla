#!/usr/bin/env node
/**
 * Faza 3: HowTo/Event/Author cards are Pro-locked (dimmed on Free, open on Pro);
 * advanced day-by-day Opening Hours are NOT locked (free) on either.
 * Run with --target staging (Pro) and `free` (no Pro).
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
let fail = 0
const ok = m => console.log('  ✓ ' + m), bad = m => { console.log('  ✗ ' + m); fail++ }
const b = await chromium.launch({ headless: true })
const c = await b.newContext({ viewport: { width: 1400, height: 1000 } })
const pg = await c.newPage()
await pg.goto(U, { waitUntil: 'domcontentloaded' })
if (!(await pg.locator('input[name="username"]').count())) await pg.goto(ADMIN, { waitUntil: 'domcontentloaded' })
await pg.fill('input[name="username"]', A); await pg.fill('input[name="passwd"]', P)
await Promise.all([pg.waitForLoadState('networkidle'), pg.click('button[type="submit"]')])
await pg.goto(`${ADMIN}?option=com_aiboost&view=app`, { waitUntil: 'networkidle' }); await pg.waitForTimeout(1800)
const set = pg.locator('a,button').filter({ hasText: /Open Settings|^Settings$/ }).first()
if (await set.count()) { await set.click().catch(() => {}); await pg.waitForTimeout(1000) }
const st = pg.locator('a,button').filter({ hasText: /Schema\.org|Schema/i }).first()
if (await st.count()) { await st.click().catch(() => {}); await pg.waitForTimeout(900) }

const isPro = !FREE
console.log(`→ ${p.host} (expect ${isPro ? 'Pro: unlocked' : 'Free: locked'})`)

// For each of the 3 cards, is it wrapped by a locked ProGate (.ab-pg-card)?
for (const name of ['Author Entity', 'HowTo Schema', 'Event Schema']) {
  // header with the Pro tag
  const hdr = pg.locator('.ab-card-header').filter({ hasText: name }).first()
  if (!(await hdr.count())) { bad(`${name}: card header not found`); continue }
  const proTag = await hdr.locator('.ab-pro-tag').count()
  proTag ? ok(`${name}: header has Pro tag`) : bad(`${name}: missing Pro tag`)
  // is the card inside a locked .ab-pg-card?
  const lockedAncestor = await hdr.evaluate(el => !!el.closest('.ab-pg-card'))
  if (isPro) lockedAncestor ? bad(`${name}: still LOCKED on Pro install`) : ok(`${name}: unlocked (Pro install)`)
  else lockedAncestor ? ok(`${name}: locked on Free (advertises Pro)`) : bad(`${name}: NOT locked on Free`)
}

// Advanced day-by-day Opening Hours must NOT be locked on either tier.
const hoursHdr = pg.locator('.ab-card-header').filter({ hasText: /Opening Hours/i }).first()
if (await hoursHdr.count()) {
  const hoursLocked = await hoursHdr.evaluate(el => !!el.closest('.ab-pg-card'))
  hoursLocked ? bad('Opening Hours card is LOCKED (should be free)') : ok('Opening Hours card is NOT locked (free)')
} else {
  console.log('  (Opening Hours card not visible for current type — skipped)')
}

console.log(fail === 0 ? '\n✅ FAZA 3 PASS' : `\n❌ FAIL (${fail})`)
await b.close()
process.exit(fail === 0 ? 0 : 1)
