#!/usr/bin/env node
/** Quick check: Faza 2b "More Details" card renders for a service type (staging+Pro). */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
const require = createRequire(import.meta.url)
const { chromium } = require('playwright')
const U = process.env.STAGING_URL, A = process.env.STAGING_ADMIN_USER, P = process.env.STAGING_ADMIN_PASS
const p = new NodeURL(U), ADMIN = `${p.protocol}//${p.host}/administrator/index.php`
let fail = 0
const ok = m => console.log('  ✓ ' + m), bad = m => { console.log('  ✗ ' + m); fail++ }
const b = await chromium.launch({ headless: true })
const c = await b.newContext({ viewport: { width: 1400, height: 950 } })
const pg = await c.newPage()
await pg.goto(U, { waitUntil: 'domcontentloaded' })
if (!(await pg.locator('input[name="username"]').count())) await pg.goto(ADMIN, { waitUntil: 'domcontentloaded' })
await pg.fill('input[name="username"]', A); await pg.fill('input[name="passwd"]', P)
await Promise.all([pg.waitForLoadState('networkidle'), pg.click('button[type="submit"]')])
await pg.goto(`${ADMIN}?option=com_aiboost&view=app`, { waitUntil: 'networkidle' }); await pg.waitForTimeout(1800)
const set = pg.locator('a,button').filter({ hasText: /Open Settings|^Settings$/ }).first()
if (await set.count()) { await set.click().catch(() => {}); await pg.waitForTimeout(1000) }
const schemaTab = pg.locator('a,button').filter({ hasText: /Schema\.org|Schema/i }).first()
if (await schemaTab.count()) { await schemaTab.click().catch(() => {}); await pg.waitForTimeout(900) }
const typeCard = () => pg.locator('.ab-card').filter({ hasText: 'Business / Organization Type' })
const orig = await typeCard().locator('select').nth(1).inputValue().catch(() => '')
await typeCard().locator('select').nth(0).selectOption({ label: 'Health & Medical' }).catch(() => {})
await pg.waitForTimeout(400)
await typeCard().locator('select').nth(1).selectOption('MedicalClinic').catch(() => {})
await pg.waitForTimeout(700)
const card = pg.locator('.ab-card').filter({ hasText: 'More Details' })
;(await card.count()) ? ok('More Details card shown for MedicalClinic') : bad('More Details card missing')
const accept = pg.locator('label:has-text("Accepting New Patients")')
;(await accept.count()) ? ok('Accepting New Patients field present') : bad('Accepting New Patients missing')
const creds = pg.locator('label:has-text("Credentials")')
;(await creds.count()) ? ok('Credentials field present') : bad('Credentials missing')
const locked = await card.locator('.ab-pg-card').count()
locked === 0 ? ok('Card unlocked (Pro)') : bad('Card still locked')
// restore
await typeCard().locator('select').nth(1).selectOption(orig).catch(() => {})
await pg.waitForTimeout(300)
const save = pg.locator('button[type=submit], .ab-btn--primary').first()
await save.click().catch(() => {}); await pg.waitForTimeout(1500)
console.log(fail === 0 ? '\n✅ 2B CARD PASS' : `\n❌ FAIL (${fail})`)
await b.close()
process.exit(fail === 0 ? 0 : 1)
