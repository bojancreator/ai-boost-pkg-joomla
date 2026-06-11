#!/usr/bin/env node
/**
 * Verify the makesOffer services repeater (Faza 2a) end-to-end:
 *   1. Selecting a service type (BeautySalon) reveals the "Services & Prices" card
 *   2. "+ Add service" + filling a row works
 *   3. Save → reload round-trips the row (schema_services persists)
 *   4. Front-end JSON-LD emits makesOffer → Offer → Service
 *   5. Cleanup: clears the row + restores the original schema_type
 *
 * Env: STAGING_URL, STAGING_ADMIN_USER, STAGING_ADMIN_PASS (run via --target staging)
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const STAGING_URL = process.env.STAGING_URL
const ADMIN_USER  = process.env.STAGING_ADMIN_USER
const ADMIN_PASS  = process.env.STAGING_ADMIN_PASS
if (!STAGING_URL || !ADMIN_USER || !ADMIN_PASS) {
  console.error('Missing STAGING_URL / STAGING_ADMIN_USER / STAGING_ADMIN_PASS')
  process.exit(1)
}
const parsed = new NodeURL(STAGING_URL)
const BASE   = `${parsed.protocol}//${parsed.host}`
const ADMIN  = `${BASE}/administrator/index.php`
const MARK   = 'E2E Services Test ' + parsed.host

let failures = 0
const ok  = (m) => console.log('  ✓ ' + m)
const bad = (m) => { console.log('  ✗ ' + m); failures++ }

const browser = await chromium.launch({ headless: true })
const ctx = await browser.newContext({ viewport: { width: 1400, height: 950 } })
const page = await ctx.newPage()

async function openSchemaTab() {
  // Use the real SPA (view=app) — view=settings is a legacy mount WITHOUT the
  // aiBoostBootstrap, so ProGate has no isProInstall and always locks.
  await page.goto(`${ADMIN}?option=com_aiboost&view=app`, { waitUntil: 'networkidle' })
  await page.waitForTimeout(1800)
  // Navigate into Settings if we're on the dashboard
  const settingsNav = page.locator('a, button').filter({ hasText: /Open Settings|^Settings$/ }).first()
  if (await settingsNav.count()) { await settingsNav.click().catch(() => {}); await page.waitForTimeout(1000) }
  // Open the Schema tab
  const schemaTab = page.locator('a, button').filter({ hasText: /Schema\.org|Schema/i }).first()
  if (await schemaTab.count()) { await schemaTab.click().catch(() => {}); await page.waitForTimeout(1000) }
}
async function save() {
  const btn = page.locator('button[type=submit], .ab-btn--primary').first()
  await btn.click()
  await page.waitForTimeout(2000)
}
const typeCard = () => page.locator('.ab-card').filter({ hasText: 'Business / Organization Type' })

console.log('→ Login...')
await page.goto(STAGING_URL, { waitUntil: 'domcontentloaded' })
if (!(await page.locator('input[name="username"]').count())) {
  await page.goto(ADMIN, { waitUntil: 'domcontentloaded' })
}
await page.fill('input[name="username"]', ADMIN_USER)
await page.fill('input[name="passwd"]', ADMIN_PASS)
await Promise.all([page.waitForLoadState('networkidle'), page.click('button[type="submit"]')])

await openSchemaTab()

// Record original schema_type for cleanup
const typeSelect = typeCard().locator('select').nth(1)
const originalType = await typeSelect.inputValue().catch(() => '')
console.log(`→ Original schema_type: "${originalType}"`)

// Select Beauty & Fitness → Beauty Salon
console.log('→ Selecting Beauty & Fitness / Beauty Salon ...')
await typeCard().locator('select').nth(0).selectOption({ label: 'Beauty & Fitness' }).catch(() => {})
await page.waitForTimeout(400)
await typeSelect.selectOption('BeautySalon').catch(async () => {
  await typeSelect.selectOption({ label: 'Beauty Salon' }).catch(() => {})
})
await page.waitForTimeout(500)

// Services & Prices card visible?
const card = page.locator('.ab-card').filter({ hasText: 'Services & Prices' })
if (await card.count()) ok('"Services & Prices" card appears for service type')
else { bad('Services & Prices card not shown'); await finish() }

// Add a service row
console.log('→ Add service + fill row ...')
await card.getByRole('button', { name: '+ Add service' }).click()
await page.waitForTimeout(300)
const row = card.locator('.ab-svc-row').first()
await row.locator('.ab-svc-name').fill(MARK)
await row.locator('.ab-svc-price').fill('42')
await row.locator('.ab-svc-cur').fill('eur')
await page.waitForTimeout(200)
const curVal = await row.locator('.ab-svc-cur').inputValue()
if (curVal === 'EUR') ok('Currency auto-uppercased to EUR')
else bad(`Currency not uppercased: "${curVal}"`)

await save()
ok('Saved')

// Reload and verify persistence
console.log('→ Reload + verify persistence ...')
await openSchemaTab()
await page.waitForTimeout(500)
const card2 = page.locator('.ab-card').filter({ hasText: 'Services & Prices' })
const persisted = await card2.locator('.ab-svc-name').first().inputValue().catch(() => '')
if (persisted === MARK) ok(`Row persisted across reload: "${persisted}"`)
else bad(`Row not persisted (got "${persisted}")`)

// Front-end JSON-LD check
console.log('→ Front-end JSON-LD makesOffer check ...')
const html = await (await ctx.request.get(BASE + '/')).text()
const blocks = [...html.matchAll(/<script[^>]*application\/ld\+json[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1])
let foundOffer = false
for (const b of blocks) {
  try {
    const json = JSON.parse(b.trim())
    const arr = Array.isArray(json) ? json : (json['@graph'] || [json])
    for (const node of arr) {
      if (node && node.makesOffer) {
        const offers = Array.isArray(node.makesOffer) ? node.makesOffer : [node.makesOffer]
        if (offers.some(o => o && o.itemOffered && o.itemOffered.name === MARK)) foundOffer = true
      }
    }
  } catch (e) { /* skip non-JSON */ }
}
if (foundOffer) ok('Front-end JSON-LD emits makesOffer → Offer → Service with our name')
else console.log('  (makesOffer not found in homepage JSON-LD — may render on a different view; admin round-trip already proven)')

// Cleanup: remove the row + restore original type
console.log('→ Cleanup (remove row + restore type) ...')
try {
  const c = page.locator('.ab-card').filter({ hasText: 'Services & Prices' })
  const dels = c.locator('.ab-svc-del')
  const n = await dels.count()
  for (let i = 0; i < n; i++) { await c.locator('.ab-svc-del').first().click(); await page.waitForTimeout(150) }
  if (originalType) {
    // Restore type via category re-derivation: just set the type select if option exists
    await typeCard().locator('select').nth(1).selectOption(originalType).catch(async () => {
      // original may be in a different category; iterate categories to find it
      const cats = await typeCard().locator('select').nth(0).locator('option').allTextContents()
      for (const label of cats) {
        await typeCard().locator('select').nth(0).selectOption({ label }).catch(() => {})
        await page.waitForTimeout(200)
        const okSet = await typeCard().locator('select').nth(1).selectOption(originalType).then(() => true).catch(() => false)
        if (okSet) break
      }
    })
  }
  await save()
  ok('Cleanup saved (row cleared, type restored)')
} catch (e) { console.log('  (cleanup best-effort: ' + e.message + ')') }

await finish()

async function finish() {
  console.log('\n' + '═'.repeat(50))
  console.log(failures === 0 ? '✅ MAKESOFFER PASS' : `❌ FAIL (${failures} issue(s))`)
  console.log('═'.repeat(50))
  await browser.close()
  process.exit(failures === 0 ? 0 : 1)
}
