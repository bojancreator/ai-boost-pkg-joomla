#!/usr/bin/env node
/**
 * Verify the unified MediaPicker (no duplicate native chrome; preview syncs on
 * JCE write-back via the bounded poll; URL field + Select + clear all work).
 * Env: STAGING_URL / STAGING_ADMIN_USER / STAGING_ADMIN_PASS
 */
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

console.log('→ SPA → Settings → Social ...')
await pg.goto(`${ADMIN}?option=com_aiboost&view=app`, { waitUntil: 'networkidle' }); await pg.waitForTimeout(1800)
const set = pg.locator('a,button').filter({ hasText: /Open Settings|^Settings$/ }).first()
if (await set.count()) { await set.click().catch(() => {}); await pg.waitForTimeout(1000) }
const soc = pg.locator('a,button').filter({ hasText: /Social/ }).first()
if (await soc.count()) { await soc.click().catch(() => {}); await pg.waitForTimeout(900) }

const mp = pg.locator('.ab-media-picker:visible').first()
if (!(await mp.count())) { bad('No visible MediaPicker on Social tab'); await finish() }

// 1) Exactly one visible control: our box + our Select; native chrome hidden
;(await mp.locator('.ab-media-picker__preview').count()) ? ok('Preview box present') : bad('no preview box')
;(await mp.locator('.ab-media-picker__select-btn').count()) ? ok('Our Select button present') : bad('no Select button')
// The native host is clipped to 1×1 with overflow:hidden, so NOTHING inside it
// (its Select/preview/input chrome) is visible → no duplicate. (Playwright's
// isVisible() ignores ancestor clip, so we assert on the host's clipped box.)
const nativeBox = await mp.locator('.ab-media-picker__native-hidden').boundingBox().catch(() => null)
if (!nativeBox || (nativeBox.height <= 2 && nativeBox.width <= 2)) ok('Native field host clipped to ~1px (its Select/preview chrome is not visible → no duplicate)')
else bad(`Native host visible: ${JSON.stringify(nativeBox)}`)

// A 1×1 PNG data URI always loads, so the preview <img> renders regardless of
// whether any real image path exists on the target site.
const DATA_IMG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='

// 2) URL field sync → preview shows it
console.log('→ Type a URL → preview updates ...')
const urlInput = mp.locator('.ab-media-picker__input-row input[type="url"]').first()
await urlInput.fill(DATA_IMG)
await pg.waitForTimeout(600)
const imgSrc = await mp.locator('.ab-media-picker__preview img').first().getAttribute('src').catch(() => null)
imgSrc && imgSrc.startsWith('data:image') ? ok('Preview shows typed URL') : bad(`Preview did not update from URL (src=${(imgSrc || '').slice(0, 40)})`)

// 3) Clear (X)
const clr = mp.locator('.ab-media-picker__clear').first()
if (await clr.count()) {
  await clr.click(); await pg.waitForTimeout(300)
  const after = await mp.locator('.ab-media-picker__preview img').count()
  after === 0 ? ok('X clear empties the preview') : bad('X clear did not empty preview')
} else bad('No X clear button after setting image')

// 4) Select opens JCE dialog + poll picks up a write-back set WITHOUT a DOM event
console.log('→ Select opens media manager + poll-sync on silent write-back ...')
const selBtn = mp.locator('.ab-media-picker__select-btn').first()
await selBtn.click().catch(e => console.log('  click err ' + e.message))
await pg.waitForTimeout(2000)
const dlg = await pg.evaluate(() => ({
  dialogs: document.querySelectorAll('joomla-dialog, dialog[open], .modal.show').length,
  iframes: [...document.querySelectorAll('iframe')].map(f => f.src).filter(s => /com_jce|com_media|mediafield/i.test(s)).length,
}))
;(dlg.dialogs > 0 || dlg.iframes > 0) ? ok(`Media manager opened (dialogs=${dlg.dialogs}, iframes=${dlg.iframes})`) : bad('Media manager did not open from our Select')
// Simulate JCE writing a ROOT path WITHOUT the leading slash (the real bug:
// "images/…" instead of "/images/…") to the hidden input, no DOM event.
await pg.evaluate(() => {
  const picker = [...document.querySelectorAll('.ab-media-picker')].find(el => el.offsetParent !== null)
  const i = picker && picker.querySelector('.field-media-input')
  if (i) { i.value = 'images/jce-writeback-test.png' }
})
await pg.waitForTimeout(1000) // > 300ms poll
const polled = await mp.locator('.ab-media-picker__input-row input[type="url"]').first().inputValue().catch(() => '')
polled === '/images/jce-writeback-test.png'
  ? ok('Bounded poll caught silent write-back AND normalised missing leading "/" → /images/… (the preview bug fix)')
  : bad(`Poll/normalise failed (val=${(polled || '').slice(0, 60)})`)

// With a REAL existing image (bare path, no slash) the preview must render.
console.log('→ Real image (bare path) → preview shows ...')
const realUrl = await pg.evaluate(async () => {
  try {
    const r = await fetch('index.php?option=com_aiboost&format=json&task=media.list&folder=images&type=images', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    const j = await r.json()
    const f = (j.files || []).find(x => x.is_image)
    return f ? f.url : ''   // e.g. "/images/foo.png"
  } catch (e) { return '' }
})
if (realUrl) {
  const bare = realUrl.replace(/^\//, '') // drop leading slash → simulate JCE bare path
  await pg.evaluate((v) => {
    const picker = [...document.querySelectorAll('.ab-media-picker')].find(el => el.offsetParent !== null)
    const i = picker && picker.querySelector('.field-media-input')
    if (i) { i.value = v }
  }, bare)
  // restart the poll by clicking Select again would reopen the dialog; instead
  // dispatch input so the change-listener path (also normalised) fires.
  await pg.evaluate(() => {
    const picker = [...document.querySelectorAll('.ab-media-picker')].find(el => el.offsetParent !== null)
    const i = picker && picker.querySelector('.field-media-input')
    if (i) i.dispatchEvent(new Event('input', { bubbles: true }))
  })
  await pg.waitForTimeout(900)
  const imgVisible = await mp.locator('.ab-media-picker__preview img').first().isVisible().catch(() => false)
  const bgToggle = await mp.locator('.ab-media-picker__bg-toggle').first().isVisible().catch(() => false)
  imgVisible ? ok(`Preview image renders for real path ${realUrl}`) : bad('Preview image still hidden for real path')
  bgToggle ? ok('Dark/light background toggle is now visible') : bad('Bg toggle still hidden')
} else {
  console.log('  (no image in /images on this site — skipped real-image preview check)')
}

await finish()
async function finish() {
  console.log('\n' + '═'.repeat(50))
  console.log(fail === 0 ? '✅ UNIFIED MEDIA PASS' : `❌ FAIL (${fail})`)
  console.log('═'.repeat(50))
  await b.close()
  process.exit(fail === 0 ? 0 : 1)
}
