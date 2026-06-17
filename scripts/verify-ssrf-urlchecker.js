#!/usr/bin/env node
/**
 * verify-ssrf-urlchecker.js — confirm the URL Checker SSRF guard.
 *
 * Logs in, boots the SPA (to obtain the CSRF token), then calls the
 * urlchecker.checkBatch endpoint with a mix of URLs:
 *   - the site's OWN homepage  → must NOT be blocked (real check runs)
 *   - 127.0.0.1 / 169.254.169.254 / 192.168.0.1 / 10.0.0.1 / [::1]
 *                              → must be blocked as "non-public URL"
 *
 *   python _creds_run.py scripts/run-node-test.py scripts/verify-ssrf-urlchecker.js --target staging
 */
import { createRequire } from 'module'
import { URL as NodeURL } from 'url'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const args = process.argv.slice(2)
const ti = args.indexOf('--target')
const TARGET = ti !== -1 ? args[ti + 1] : 'staging'

function env(k) { const v = process.env[k]; if (!v) { console.error('Missing env ' + k); process.exit(1) } return v }
let ADMIN_URL, USER, PASS
if (TARGET === 'free') { ADMIN_URL = env('FREE_URL'); USER = env('FREE_ADMIN_USER'); PASS = env('FREE_ADMIN_PASS') }
else { ADMIN_URL = env('STAGING_URL'); USER = env('STAGING_ADMIN_USER'); PASS = env('STAGING_ADMIN_PASS') }

const parsed = new NodeURL(ADMIN_URL)
const BASE = `${parsed.protocol}//${parsed.host}`
const APP_URL = `${BASE}/administrator/index.php?option=com_aiboost&view=app`

async function login(page) {
  await page.goto(ADMIN_URL, { waitUntil: 'domcontentloaded', timeout: 45000 })
  try { await page.waitForSelector('input[name=username]', { timeout: 8000 }) } catch { return }
  await page.fill('input[name=username]', USER)
  await page.fill('input[name=passwd]', PASS)
  await page.press('input[name=passwd]', 'Enter')
  await page.waitForLoadState('domcontentloaded', { timeout: 30000 })
}

const INTERNAL = [
  'http://127.0.0.1/',
  'http://169.254.169.254/latest/meta-data/',
  'http://192.168.0.1/',
  'http://10.0.0.1/',
  'http://[::1]/',
]

;(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox'] })
  const page = await browser.newPage()
  let failed = false
  try {
    await login(page)
    await page.goto(APP_URL + '#/', { waitUntil: 'domcontentloaded', timeout: 45000 })
    await page.waitForSelector('#ab-app', { timeout: 15000 })
    await page.waitForTimeout(1200)

    const own = BASE + '/'
    const result = await page.evaluate(async ({ own, internal }) => {
      const tn = (window.aiBoostBootstrap && window.aiBoostBootstrap.tokenName) || window.aiBoostToken || ''
      const urls = [own, ...internal]
      const body = new URLSearchParams()
      body.append('urls', JSON.stringify(urls))
      if (tn) body.append(tn, '1')
      const resp = await fetch('index.php?option=com_aiboost&task=urlchecker.checkBatch', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      })
      return { status: resp.status, tokenPresent: !!tn, text: await resp.text() }
    }, { own, internal: INTERNAL })

    console.log(`\n  Target: ${TARGET}  (own host: ${parsed.host})`)
    console.log(`  HTTP ${result.status}, token present: ${result.tokenPresent}`)

    // Be robust to any leading PHP notice (staging runs display_errors=On);
    // the body should otherwise be clean JSON now that curl_close() is gone.
    const jsonStart = result.text.indexOf('{')
    const leadingJunk = jsonStart > 0 ? result.text.slice(0, jsonStart).trim() : ''
    if (leadingJunk) console.log('  ⚠️  leading non-JSON before body (len ' + leadingJunk.length + '): ' + leadingJunk.slice(0, 80))
    let parsed2
    try { parsed2 = jsonStart >= 0 ? JSON.parse(result.text.slice(jsonStart)) : null } catch { parsed2 = null }
    if (!parsed2 || !parsed2.success || !Array.isArray(parsed2.results)) {
      console.log('  ❌ FAIL: unexpected response: ' + result.text.slice(0, 200))
      failed = true
    } else {
      const byUrl = {}
      for (const r of parsed2.results) byUrl[r.url] = r

      // 1) Own homepage must NOT be blocked.
      const ownR = byUrl[own]
      const ownBlocked = ownR && ownR.error === 'Invalid or non-public URL'
      if (!ownR) { console.log('  ❌ FAIL: own URL missing from results'); failed = true }
      else if (ownBlocked) { console.log(`  ❌ FAIL: own URL was wrongly blocked (over-blocking)`); failed = true }
      else { console.log(`  ✅ own homepage allowed (status=${ownR.status}, error=${ownR.error ?? 'none'})`) }

      // 2) Every internal URL must be blocked.
      let blockedCount = 0
      for (const u of INTERNAL) {
        const r = byUrl[u]
        const blocked = r && r.status === 0 && /non-public/i.test(String(r.error || ''))
        if (blocked) { blockedCount++ }
        else { console.log(`  ❌ FAIL: internal URL NOT blocked: ${u} → ${JSON.stringify(r)}`); failed = true }
      }
      console.log(`  ✅ internal URLs blocked: ${blockedCount}/${INTERNAL.length}`)

      if (!failed) console.log('\n  ✅ PASS: SSRF guard allows own host, blocks internal/private/link-local targets')
    }
  } catch (e) {
    console.log('  ❌ ERROR: ' + (e && e.message ? e.message : e)); failed = true
  } finally { await browser.close() }
  process.exit(failed ? 1 : 0)
})()
