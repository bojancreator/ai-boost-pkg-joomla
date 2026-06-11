#!/usr/bin/env node
/** Faza 2b (rest): new per-type Pro fields render in More Details for the right types (staging+Pro). */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
const require = createRequire(import.meta.url)
const { chromium } = require('playwright')
const U = process.env.STAGING_URL, A = process.env.STAGING_ADMIN_USER, P = process.env.STAGING_ADMIN_PASS
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
if (await st.count()) { await st.click().catch(() => {}); await pg.waitForTimeout(800) }
const typeCard = () => pg.locator('.ab-card').filter({ hasText: 'Business / Organization Type' })
const orig = await typeCard().locator('select').nth(1).inputValue().catch(() => '')

async function pick(cat, type) {
  await typeCard().locator('select').nth(0).selectOption({ label: cat }).catch(() => {})
  await pg.waitForTimeout(350)
  await typeCard().locator('select').nth(1).selectOption(type).catch(() => {})
  await pg.waitForTimeout(600)
}
async function has(label) { return (await pg.locator('.ab-card-body label.ab-label').filter({ hasText: label }).count()) > 0 }

await pick('Local Business', 'LocalBusiness')
;(await has('Number of Employees')) ? ok('LocalBusiness → Number of Employees') : bad('missing employees')
;(await has('Slogan')) ? ok('LocalBusiness → Slogan') : bad('missing slogan')
;(await has('Awards')) ? ok('LocalBusiness → Awards') : bad('missing awards')

await pick('Retail & Automotive', 'AutomotiveBusiness')
;(await has('Brands Serviced')) ? ok('AutomotiveBusiness → Brands') : bad('missing brand')

await pick('Lodging & Travel', 'TouristAttraction')
;(await has('Target Audience')) ? ok('TouristAttraction → Target Audience') : bad('missing audience')
;(await has('Free Admission')) ? ok('TouristAttraction → Free Admission') : bad('missing accessibleFree')

await pick('Food & Drink', 'Restaurant')
;(await has('Smoking Allowed')) ? ok('Restaurant → Smoking Allowed') : bad('missing smoking')
;(await has('Drive-Through')) ? ok('Restaurant → Drive-Through') : bad('missing driveThrough')

// restore
await typeCard().locator('select').nth(1).selectOption(orig).catch(async () => {
  const cats = await typeCard().locator('select').nth(0).locator('option').allTextContents()
  for (const l of cats) { await typeCard().locator('select').nth(0).selectOption({ label: l }).catch(() => {}); await pg.waitForTimeout(150); if (await typeCard().locator('select').nth(1).selectOption(orig).then(() => true).catch(() => false)) break }
})
await pg.waitForTimeout(300)
await pg.locator('button[type=submit], .ab-btn--primary').first().click().catch(() => {}); await pg.waitForTimeout(1200)
console.log(fail === 0 ? '\n✅ 2B-REST PASS' : `\n❌ FAIL (${fail})`)
await b.close()
process.exit(fail === 0 ? 0 : 1)
