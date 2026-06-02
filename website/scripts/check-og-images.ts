import { existsSync, readdirSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { blogPosts } from '../src/data/blogPosts.js'

const __dirname = dirname(fileURLToPath(import.meta.url))
const OG_DIR = resolve(__dirname, '../public/og')

function check() {
  const missing: string[] = []
  const found: string[] = []

  for (const post of blogPosts) {
    const imgPath = resolve(OG_DIR, `${post.slug}.png`)
    if (existsSync(imgPath)) {
      found.push(post.slug)
    } else {
      missing.push(post.slug)
    }
  }

  const existingFiles = existsSync(OG_DIR)
    ? readdirSync(OG_DIR).filter(f => f.endsWith('.png'))
    : []

  const knownSlugs = new Set(blogPosts.map(p => `${p.slug}.png`))
  const orphaned = existingFiles.filter(f => !knownSlugs.has(f))

  console.log(`\nOG image check — website/public/og/`)
  console.log(`  Blog posts : ${blogPosts.length}`)
  console.log(`  Found      : ${found.length}`)

  if (orphaned.length > 0) {
    console.log(`\n⚠  Orphaned OG images (no matching blog post):`)
    for (const f of orphaned) {
      console.log(`   - ${f}`)
    }
  }

  if (missing.length > 0) {
    console.error(`\n✗ Missing OG images for ${missing.length} blog post(s):`)
    for (const slug of missing) {
      console.error(`   - ${slug}.png`)
    }
    console.error(`\nRun: pnpm --filter aiboostnow generate-og\n`)
    process.exit(1)
  }

  console.log(`  ✓ All ${found.length} OG images present\n`)
}

check()
