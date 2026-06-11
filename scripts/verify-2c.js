#!/usr/bin/env node
/** Faza 2c: per-service-name TranslationExpander renders in the makesOffer card (staging+Pro). */
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
const st = pg.locator('a,button').filter({ hasText: /Schema\.org|Schema/i }).first()
if (await st.count()) { await st.click().catch(() => {}); await pg.waitForTimeout(900) }
const typeCard = () => pg.locator('.ab-card').filter({ hasText: 'Business / Organization Type' })
const orig = await typeCard().locator('select').nth(1).inputValue().catch(() => '')
await typeCard().locator('select').nth(0).selectOption({ label: 'Beauty & Fitness' }).catch(() => {})
await pg.waitForTimeout(400)
await typeCard().locator('select').nth(1).selectOption('BeautySalon').catch(() => {})
await pg.waitForTimeout(700)
const card = pg.locator('.ab-card').filter({ hasText: 'Services & Prices' })
;(await card.count()) ? ok('Services & Prices card present') : bad('card missing')
// add a service
await card.getByRole('button', { name: '+ Add service' }).click(); await pg.waitForTimeout(300)
await card.locator('.ab-svc-name').first().fill('Translatable Service 2c')
await pg.waitForTimeout(400)
// translation section
const trLabel = card.locator('.ab-faq-trans-label', { hasText: /Service #1/ })
;(await trLabel.count()) ? ok('Per-service translation label "Service #1" appears') : bad('translation label missing')
const expander = card.locator('.ab-faq-trans-group')
;(await expander.count()) ? ok(`TranslationExpander present (${await expander.count()})`) : bad('no TranslationExpander')
// multilingual?
const langInputs = await card.locator('.ab-faq-trans-group input, .ab-faq-trans-group textarea, .ab-faq-trans-group [contenteditable]').count()
console.log('  → translation input controls: ' + langInputs + (langInputs ? ' (site appears multilingual)' : ' (single-language site — expander collapses, expected)'))
// cleanup: remove row, restore type
const del = card.locator('.ab-svc-del').first()
if (await del.count()) { await del.click().catch(() => {}); await pg.waitForTimeout(200) }
await typeCard().locator('select').nth(1).selectOption(orig).catch(() => {})
await pg.waitForTimeout(300)
await pg.locator('button[type=submit], .ab-btn--primary').first().click().catch(() => {}); await pg.waitForTimeout(1500)
console.log(fail === 0 ? '\n✅ 2C UI PASS' : `\n❌ FAIL (${fail})`)
await b.close()
process.exit(fail === 0 ? 0 : 1)
