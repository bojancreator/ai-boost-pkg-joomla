/**
 * Render the Designer's interactive `screens.html` mockup to one PNG per screen,
 * in both themes, so we can compare the Designer's intent side-by-side with our
 * live admin screenshots (artifacts/ui-audit/{light,dark}/).
 *
 * Input : artifacts/designer-src/screens.html  (+ ab-tokens.css, ab-components.css)
 * Output: artifacts/designer/{light,dark}/<NN-key>.png
 *
 * Run from aiboost-joomla/:  node scripts/render-designer-screens.mjs
 */
import { createRequire } from 'module'
import { fileURLToPath } from 'url'
import { dirname, resolve } from 'path'
import { mkdirSync, existsSync } from 'fs'

const require = createRequire(import.meta.url)
const { chromium } = require('playwright')

const __dirname = dirname(fileURLToPath(import.meta.url))
const ROOT = resolve(__dirname, '..')
const SRC_FILE = resolve(ROOT, 'artifacts/designer-src/screens.html')
const OUT = resolve(ROOT, 'artifacts/designer')

if (!existsSync(SRC_FILE)) {
  console.error('❌ Missing ' + SRC_FILE + ' — populate artifacts/designer-src/ first.')
  process.exit(1)
}
const SRC = 'file:///' + SRC_FILE.replace(/\\/g, '/')

const browser = await chromium.launch()
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 })
await page.goto(SRC, { waitUntil: 'networkidle' })

const keys = await page.$$eval('#screenSel option', (os) => os.map((o) => o.value))
console.log('Screens found:', keys.length, '→', keys.join(', '))

for (const theme of ['light', 'dark']) {
  await page.evaluate((t) => {
    const b = document.querySelector('[data-theme="' + t + '"]')
    if (b) b.click()
  }, theme)
  mkdirSync(resolve(OUT, theme), { recursive: true })
  let i = 0
  for (const key of keys) {
    i++
    await page.selectOption('#screenSel', key)
    await page.waitForTimeout(300)
    const name = String(i).padStart(2, '0') + '-' + key + '.png'
    await page.screenshot({ path: resolve(OUT, theme, name), fullPage: true })
    console.log(' ', theme, name)
  }
}

await browser.close()
console.log('✅ Done →', OUT)
