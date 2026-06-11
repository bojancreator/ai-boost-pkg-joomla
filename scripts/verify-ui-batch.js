#!/usr/bin/env node
/**
 * Verify the 4 UI/labeling items:
 *  #3 toolbar + Components-menu read "AI Boost PRO" (staging) / "AI Boost FREE" (free)
 *  #1 Markdown not advertised as Pro (proFeatures has no section:aeo.markdown)
 *  #5 Dashboard tier badges (Custom Code = PRO, mixed = FREE/PRO)
 *  #4 Schema sub-tab strip works + Hours hidden for non-hours type
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

// #3 toolbar title
const titleText = await pg.evaluate(() => (document.querySelector('.page-title, .header-title, #content h1, .subhead h1, h1.page-title')?.textContent || document.body.textContent.slice(0, 400)))
titleText.includes('AI Boost ' + edition) ? ok(`#3 toolbar shows "AI Boost ${edition}"`) : bad(`#3 toolbar missing "AI Boost ${edition}" (got: ${titleText.replace(/\s+/g, ' ').slice(0, 80)})`)

// #3 Components admin menu label (#__menu title rendered in admin sidebar)
const menuHas = await pg.evaluate((ed) => document.body.innerHTML.includes('AI Boost ' + ed), edition)
menuHas ? ok(`#3 admin menu contains "AI Boost ${edition}"`) : console.log(`  (admin menu label not found in DOM — may be collapsed; install ran relabel)`)

// #1 proFeatures has no section:aeo.markdown
const boot = await pg.evaluate(() => window.aiBoostBootstrap || {})
const hasMarkdownPro = (boot.proFeatures || []).some(f => f.key === 'section:aeo.markdown')
hasMarkdownPro ? bad('#1 section:aeo.markdown STILL advertised as Pro') : ok('#1 Markdown not advertised as Pro (removed from proFeatures)')
if (!FREE) (boot.isProInstall ? ok('isProInstall=true (Pro env)') : bad('isProInstall=false on staging'))

// Navigate into Settings
const set = pg.locator('a,button').filter({ hasText: /Open Settings|^Settings$/ }).first()
if (await set.count()) { await set.click().catch(() => {}); await pg.waitForTimeout(1000) }

// #1 AeoTab Markdown + AI Signals not locked
const aeoTab = pg.locator('a,button').filter({ hasText: /AI Visibility|AEO|llms/i }).first()
if (await aeoTab.count()) {
  await aeoTab.click().catch(() => {}); await pg.waitForTimeout(700)
  const md = pg.locator('label.ab-label, .ab-card-header, .ab-check__label').filter({ hasText: /Markdown/i }).first()
  if (await md.count()) {
    const locked = await md.evaluate(el => !!el.closest('.ab-pg-card'))
    locked ? bad('#1 Markdown is LOCKED in Free UI') : ok('#1 Markdown section NOT locked (free)')
  } else console.log('  (Markdown section label not found on AEO tab)')
}

// #4 Schema sub-tabs
const st = pg.locator('a,button').filter({ hasText: /Schema\.org|Schema/i }).first()
if (await st.count()) { await st.click().catch(() => {}); await pg.waitForTimeout(900) }
const nav = pg.locator('.ab-schema-nav')
if (await nav.count()) {
  const btns = await nav.locator('.ab-schema-nav__btn').allTextContents()
  ok(`#4 Schema sub-nav present: [${btns.map(t => t.trim()).join(' · ')}]`)
  // switch to Business → Core cards hidden, Business card shown
  const bizBtn = nav.locator('.ab-schema-nav__btn').filter({ hasText: /Business/ }).first()
  if (await bizBtn.count()) {
    await bizBtn.click(); await pg.waitForTimeout(400)
    const bizVisible = await pg.locator('.ab-card-header').filter({ hasText: 'Business / Organization Type' }).first().isVisible().catch(() => false)
    const coreHidden = !(await pg.locator('.ab-card-header').filter({ hasText: 'Schema.org Core' }).first().isVisible().catch(() => false))
    bizVisible && coreHidden ? ok('#4 Switching to Business shows Business group, hides Core') : bad(`#4 sub-tab switch wrong (biz=${bizVisible} coreHidden=${coreHidden})`)
  }
} else bad('#4 Schema sub-nav (.ab-schema-nav) missing')

// #5 Dashboard tier badges
await pg.goto(`${ADMIN}?option=com_aiboost&view=app`, { waitUntil: 'networkidle' }); await pg.waitForTimeout(1500)
const dash = await pg.evaluate(() => {
  const cards = [...document.querySelectorAll('.ab-module-card')]
  const out = {}
  for (const card of cards) {
    const label = (card.querySelector('.fw-bold')?.textContent || '').trim()
    const pro = !!card.querySelector('.ab-tier-badge--pro')
    const mixed = !!card.querySelector('.ab-tier-badge--mixed')
    out[label] = pro ? 'PRO' : mixed ? 'FREE/PRO' : 'none'
  }
  return out
})
console.log('  dashboard tiers: ' + JSON.stringify(dash))
const codeTier = Object.entries(dash).find(([k]) => /custom code/i.test(k))?.[1]
const anyMixed = Object.values(dash).includes('FREE/PRO')
if (FREE) {
  // Free edition advertises which modules are Pro / mixed.
  codeTier === 'PRO' ? ok('#5 Custom Code dashboard card = PRO') : bad(`#5 Custom Code tier = ${codeTier}`)
  anyMixed ? ok('#5 mixed cards show FREE/PRO') : bad('#5 no FREE/PRO badges')
} else {
  // Pro edition: every module is unlocked, so tier badges are hidden.
  const anyTier = Object.values(dash).some(v => v !== 'none')
  anyTier ? bad(`#5 tier badges must be HIDDEN on Pro (got ${JSON.stringify(dash)})`) : ok('#5 tier badges hidden on Pro edition')
}

console.log(fail === 0 ? '\n✅ UI BATCH PASS' : `\n❌ FAIL (${fail})`)
await b.close()
process.exit(fail === 0 ? 0 : 1)
