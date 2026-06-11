#!/usr/bin/env node
/**
 * Verify the legacy view=settings mount now emits window.aiBoostBootstrap so
 * ProGate unlocks correctly on a Pro install (bug #8 fix).
 *
 * Expectation:
 *   - Pro install (staging):  isProInstall=true  → .ab-pg-card locked wrappers = 0
 *   - Free install (free):    isProInstall=false → .ab-pg-card locked wrappers > 0
 *
 * Run with --target staging AND --target free. The script self-detects which by
 * reading the resulting isProInstall and asserts the matching expectation.
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'
const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const U = process.env.STAGING_URL, A = process.env.STAGING_ADMIN_USER, P = process.env.STAGING_ADMIN_PASS
if (!U || !A || !P) { console.error('Missing creds'); process.exit(1) }
const p = new NodeURL(U), ADMIN = `${p.protocol}//${p.host}/administrator/index.php`

let failures = 0
const ok  = (m) => console.log('  ✓ ' + m)
const bad = (m) => { console.log('  ✗ ' + m); failures++ }

const b = await chromium.launch({ headless: true })
const c = await b.newContext({ viewport: { width: 1400, height: 950 } })
const pg = await c.newPage()

await pg.goto(U, { waitUntil: 'domcontentloaded' })
if (!(await pg.locator('input[name="username"]').count())) await pg.goto(ADMIN, { waitUntil: 'domcontentloaded' })
await pg.fill('input[name="username"]', A); await pg.fill('input[name="passwd"]', P)
await Promise.all([pg.waitForLoadState('networkidle'), pg.click('button[type="submit"]')])

console.log(`→ Open legacy view=settings on ${p.host} ...`)
await pg.goto(`${ADMIN}?option=com_aiboost&view=settings`, { waitUntil: 'networkidle' })
await pg.waitForTimeout(2000)

const state = await pg.evaluate(() => {
  const boot = window.aiBoostBootstrap || {}
  return {
    hasBoot: !!window.aiBoostBootstrap,
    isProInstall: boot.isProInstall,
    legacy: boot.legacy,
    pgCard: document.querySelectorAll('.ab-pg-card').length,   // locked card wrappers
    pgField: document.querySelectorAll('.ab-pg-field').length, // locked field chips
  }
})
console.log('→ ' + JSON.stringify(state))

if (state.hasBoot) ok('window.aiBoostBootstrap is now defined on legacy view=settings')
else bad('aiBoostBootstrap still undefined on legacy view=settings')

if (state.isProInstall === true) {
  ok('isProInstall=true (Pro install)')
  if (state.pgCard === 0) ok('ProGate cards UNLOCKED (no .ab-pg-card locked wrappers) — bug #8 fixed')
  else bad(`Still ${state.pgCard} locked .ab-pg-card wrappers despite Pro install`)
  if (state.pgField === 0) ok('ProGate field chips unlocked too')
  else bad(`Still ${state.pgField} locked field chips`)
} else {
  ok('isProInstall=false (Free install)')
  if (state.pgCard > 0) ok(`ProGate correctly LOCKS on Free (${state.pgCard} locked cards advertise Pro)`)
  else bad('Free install shows no locked cards — Pro features not advertised')
}

console.log('\n' + '═'.repeat(50))
console.log(failures === 0 ? '✅ LEGACY PROGATE PASS' : `❌ FAIL (${failures})`)
console.log('═'.repeat(50))
await b.close()
process.exit(failures === 0 ? 0 : 1)
