/**
 * IndexNow ping — submits the sitemap to Bing/Yandex/Seznam on every deploy.
 *
 * Key file: https://aiboostnow.com/a3f2d1e7b4c9f5e8d2a1b7c4f9e3d8a2.txt
 * Docs: https://www.indexnow.org/documentation
 *
 * Called automatically via the `postbuild` npm script.
 * Safe to run locally — skips the ping if INDEXNOW_SKIP=1.
 *
 * URLs are read from the generated sitemap.xml so new blog posts are
 * automatically included without any manual update here.
 */

import { readFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))

const INDEXNOW_KEY = 'a3f2d1e7b4c9f5e8d2a1b7c4f9e3d8a2'
const SITE_URL = 'https://aiboostnow.com'
const SITEMAP_URL = `${SITE_URL}/sitemap.xml`
const SITEMAP_PATH = resolve(__dirname, '../public/sitemap.xml')

function readUrlsFromSitemap(): string[] {
  const xml = readFileSync(SITEMAP_PATH, 'utf-8')
  const matches = xml.matchAll(/<loc>(.*?)<\/loc>/g)
  return Array.from(matches, (m) => m[1])
}

async function pingIndexNow() {
  const inCI = process.env['CI'] === 'true'
  const skip = process.env['INDEXNOW_SKIP'] === '1'
  const force = process.env['INDEXNOW_FORCE'] === '1'

  if (skip || (!inCI && !force)) {
    const reason = skip ? 'INDEXNOW_SKIP=1' : 'not in CI (set INDEXNOW_FORCE=1 to override)'
    console.log(`[indexnow] Skipped (${reason})`)
    return
  }

  const URLS = readUrlsFromSitemap()
  console.log(`[indexnow] Pinging with ${URLS.length} URLs…`)

  const payload = {
    host: 'aiboostnow.com',
    key: INDEXNOW_KEY,
    keyLocation: `${SITE_URL}/${INDEXNOW_KEY}.txt`,
    urlList: URLS,
  }

  try {
    const res = await fetch('https://api.indexnow.org/indexnow', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json; charset=utf-8' },
      body: JSON.stringify(payload),
    })

    if (res.ok || res.status === 202) {
      console.log(`[indexnow] ✅ Accepted (HTTP ${res.status})`)
    } else {
      const text = await res.text()
      console.warn(`[indexnow] ⚠️  HTTP ${res.status}: ${text}`)
    }
  } catch (err) {
    console.warn('[indexnow] ⚠️  Network error (non-fatal):', (err as Error).message)
  }

  console.log(`[indexnow] Sitemap: ${SITEMAP_URL}`)
}

await pingIndexNow()

export {}
