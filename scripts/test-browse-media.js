#!/usr/bin/env node
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

const browser = await chromium.launch({ headless: true })
const ctx = await browser.newContext({ viewport: { width: 1400, height: 900 } })
const page = await ctx.newPage()
const consoleLogs = []
page.on('console', m => consoleLogs.push(`[${m.type()}] ${m.text()}`))
page.on('pageerror', e => consoleLogs.push(`[pageerror] ${e.message}`))

console.log('→ Login...')
await page.goto(STAGING_URL, { waitUntil: 'domcontentloaded' })
if (!(await page.locator('input[name="username"]').count())) {
  await page.goto(ADMIN, { waitUntil: 'domcontentloaded' })
}
await page.fill('input[name="username"]', ADMIN_USER)
await page.fill('input[name="passwd"]', ADMIN_PASS)
await Promise.all([
  page.waitForLoadState('networkidle'),
  page.click('button[type="submit"]'),
])

console.log('→ Open AI Boost component (Organization tab) ...')
await page.goto(`${ADMIN}?option=com_aiboost&view=settings#tab-org`, { waitUntil: 'networkidle' })
await page.waitForTimeout(2000)

// Try clicking the Organization tab explicitly
const orgTabBtn = page.locator('#tab-org-btn, [data-ab-tab="org"], button:has-text("Organization")').first()
if (await orgTabBtn.count()) {
  await orgTabBtn.click().catch(() => {})
  await page.waitForTimeout(500)
}

// Find Browse Media button
const browseBtn = page.locator('button:has-text("Browse Media")').first()
const btnCount = await page.locator('button:has-text("Browse Media")').count()
console.log(`→ Found ${btnCount} "Browse Media" button(s)`)

if (!btnCount) {
  console.log('✗ No Browse Media button found on Organization tab')
  await page.screenshot({ path: '/tmp/bm-no-button.png', fullPage: true })
  await browser.close()
  process.exit(1)
}

// Set up popup listener BEFORE clicking
const popupPromise = page.waitForEvent('popup', { timeout: 8000 }).catch(() => null)
await browseBtn.click()
const popup = await popupPromise

if (!popup) {
  console.log('✗ Popup did NOT open after clicking Browse Media')
  await page.screenshot({ path: '/tmp/bm-no-popup.png', fullPage: true })
  console.log('--- console logs ---')
  consoleLogs.slice(-30).forEach(l => console.log(l))
  await browser.close()
  process.exit(2)
}

console.log(`→ Popup opened: ${popup.url()}`)
await popup.waitForLoadState('networkidle').catch(() => {})
await popup.waitForTimeout(2000)

// Check popup body for media browser
const bodyHasMedia = await popup.locator('.media-browser, .media-toolbar, [class*="media-"]').count()
console.log(`→ Media browser elements in popup: ${bodyHasMedia}`)

// Look for images
const imgCount = await popup.locator('.media-browser-image, .media-browser-item, [data-src], [data-url]').count()
console.log(`→ Image items in popup: ${imgCount}`)

// Check our injected toolbar
const ourBar = await popup.locator('#aiboost-mp-bar').count()
const insertBtn = await popup.locator('#aiboost-mp-insert').count()
const cancelBtn = await popup.locator('#aiboost-mp-cancel').count()
const statusText = ourBar ? await popup.locator('#aiboost-mp-status').textContent() : '(no bar)'
console.log(`→ Injected toolbar: bar=${ourBar} insert=${insertBtn} cancel=${cancelBtn}`)
console.log(`→ Status text: "${statusText}"`)

// Try to find the popup body content
const bodyText = (await popup.locator('body').textContent() || '').slice(0, 300).replace(/\s+/g, ' ')
console.log(`→ Popup body preview: "${bodyText}"`)

// Take screenshots
await popup.screenshot({ path: '/tmp/bm-popup.png', fullPage: true })
await page.screenshot({ path: '/tmp/bm-parent.png', fullPage: true })
console.log('→ Screenshots: /tmp/bm-popup.png, /tmp/bm-parent.png')

// Try clicking the first image if available
if (imgCount > 0) {
  console.log('→ Clicking first image...')
  await popup.locator('.media-browser-image, .media-browser-item, [data-src], [data-url]').first().click().catch(e => console.log(`  click err: ${e.message}`))
  await popup.waitForTimeout(800)
  const statusAfter = ourBar ? await popup.locator('#aiboost-mp-status').textContent() : '(no bar)'
  const insertDisabled = ourBar ? await popup.locator('#aiboost-mp-insert').isDisabled() : true
  console.log(`→ After click: status="${statusAfter}" insertBtnDisabled=${insertDisabled}`)

  if (!insertDisabled) {
    console.log('→ Clicking Insert selected image...')
    await popup.locator('#aiboost-mp-insert').click()
    await page.waitForTimeout(1500)
    const inputVal = await page.locator('input[type="url"]').first().inputValue()
    console.log(`→ Input value after Insert: "${inputVal}"`)
  }
}

console.log('\n--- Last 20 parent-page console logs ---')
consoleLogs.slice(-20).forEach(l => console.log(l))

await browser.close()
