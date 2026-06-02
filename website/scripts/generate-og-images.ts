import { Resvg } from '@resvg/resvg-js'
import { writeFileSync, mkdirSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { blogPosts } from '../src/data/blogPosts.js'

const __dirname = dirname(fileURLToPath(import.meta.url))
const OUT_DIR = resolve(__dirname, '../public/og')

const PURPLE = '#7B4FFF'
const DARK_BG = '#0C0B1D'
const DARK_BG2 = '#13103A'
const WHITE = '#FFFFFF'
const MUTED = '#9090B0'
const TAG_BG = '#1E1A4A'

const WIDTH = 1200
const HEIGHT = 630

function wrapText(text: string, maxCharsPerLine: number): string[] {
  const words = text.split(' ')
  const lines: string[] = []
  let current = ''
  for (const word of words) {
    const test = current ? `${current} ${word}` : word
    if (test.length > maxCharsPerLine && current) {
      lines.push(current)
      current = word
    } else {
      current = test
    }
  }
  if (current) lines.push(current)
  return lines
}

function escapeXml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&apos;')
}

function buildSvg(title: string, tags: string[]): string {
  const TITLE_FONT_SIZE = 58
  const MAX_CHARS = 38
  const MAX_LINES = 3

  let titleLines = wrapText(title, MAX_CHARS)
  if (titleLines.length > MAX_LINES) {
    titleLines = titleLines.slice(0, MAX_LINES)
    const last = titleLines[MAX_LINES - 1]
    if (last.length > MAX_CHARS - 3) {
      titleLines[MAX_LINES - 1] = last.slice(0, MAX_CHARS - 3).trimEnd() + '...'
    } else {
      titleLines[MAX_LINES - 1] = last + '...'
    }
  }

  const LINE_HEIGHT = TITLE_FONT_SIZE * 1.25
  const totalTitleHeight = titleLines.length * LINE_HEIGHT
  const titleStartY = HEIGHT / 2 - totalTitleHeight / 2 + 20

  const titleSvg = titleLines
    .map(
      (line, i) =>
        `<text x="72" y="${titleStartY + i * LINE_HEIGHT}" font-family="DejaVu Sans" font-weight="bold" font-size="${TITLE_FONT_SIZE}" fill="${WHITE}" letter-spacing="-1">${escapeXml(line)}</text>`,
    )
    .join('\n  ')

  const MAX_TAGS = 4
  const displayTags = tags.slice(0, MAX_TAGS)
  let tagX = 72
  const tagY = HEIGHT - 72
  const tagSvg = displayTags
    .map(tag => {
      const tagWidth = tag.length * 8.5 + 28
      const el = `
    <rect x="${tagX}" y="${tagY - 22}" width="${tagWidth}" height="30" rx="15" fill="${TAG_BG}"/>
    <text x="${tagX + tagWidth / 2}" y="${tagY - 2}" font-family="DejaVu Sans" font-size="13" font-weight="bold" fill="${PURPLE}" text-anchor="middle">${escapeXml(tag)}</text>`
      tagX += tagWidth + 10
      return el
    })
    .join('\n')

  return `<?xml version="1.0" encoding="UTF-8"?>
<svg width="${WIDTH}" height="${HEIGHT}" viewBox="0 0 ${WIDTH} ${HEIGHT}" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="${DARK_BG}"/>
      <stop offset="100%" stop-color="${DARK_BG2}"/>
    </linearGradient>
    <linearGradient id="accent" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0%" stop-color="${PURPLE}"/>
      <stop offset="100%" stop-color="#A07BFF"/>
    </linearGradient>
    <linearGradient id="glow" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="${PURPLE}" stop-opacity="0.18"/>
      <stop offset="100%" stop-color="${DARK_BG}" stop-opacity="0"/>
    </linearGradient>
  </defs>
  <rect width="${WIDTH}" height="${HEIGHT}" fill="url(#bg)"/>
  <rect x="0" y="0" width="${WIDTH}" height="320" fill="url(#glow)"/>
  <line x1="0" y1="200" x2="${WIDTH}" y2="200" stroke="${PURPLE}" stroke-opacity="0.06" stroke-width="1"/>
  <line x1="0" y1="400" x2="${WIDTH}" y2="400" stroke="${PURPLE}" stroke-opacity="0.06" stroke-width="1"/>
  <line x1="400" y1="0" x2="400" y2="${HEIGHT}" stroke="${PURPLE}" stroke-opacity="0.06" stroke-width="1"/>
  <line x1="800" y1="0" x2="800" y2="${HEIGHT}" stroke="${PURPLE}" stroke-opacity="0.06" stroke-width="1"/>
  <rect x="0" y="0" width="${WIDTH}" height="6" fill="url(#accent)"/>
  <rect x="72" y="44" width="10" height="28" rx="5" fill="${PURPLE}"/>
  <text x="92" y="67" font-family="DejaVu Sans" font-weight="bold" font-size="18" fill="${PURPLE}" letter-spacing="2">AI BOOST BLOG</text>
  <text x="${WIDTH - 72}" y="67" font-family="DejaVu Sans" font-size="16" fill="${MUTED}" text-anchor="end">aiboostnow.com</text>
  ${titleSvg}
  <line x1="72" y1="${HEIGHT - 108}" x2="${WIDTH - 72}" y2="${HEIGHT - 108}" stroke="${PURPLE}" stroke-opacity="0.25" stroke-width="1"/>
  ${tagSvg}
  <text x="${WIDTH - 72}" y="${HEIGHT - 52}" font-family="DejaVu Sans" font-size="15" fill="${MUTED}" text-anchor="end">AI Boost for Joomla</text>
</svg>`
}

const mainPages = [
  { slug: 'home',     title: 'AI Boost for Joomla — Visible to AI Search',  tags: ['Schema.org', 'AEO', 'Joomla'] },
  { slug: 'features', title: 'Features — AI Boost for Joomla',               tags: ['Schema.org', 'XML Sitemap', 'Analytics'] },
  { slug: 'pricing',  title: 'Pricing — AI Boost for Joomla',                tags: ['€15–€20/plugin', 'Bundle €45', 'Free tier'] },
  { slug: 'docs',     title: 'Documentation — AI Boost for Joomla',          tags: ['Getting Started', 'Schema.org', 'Sitemap'] },
  { slug: 'faq',      title: 'FAQ — AI Boost for Joomla',                    tags: ['FAQ', 'Pricing', 'Licensing'] },
  { slug: 'blog',     title: 'Blog — AI Boost',                              tags: ['Schema.org', 'AI Search', 'SEO'] },
  { slug: 'plugins',  title: 'Plugins — AI Boost for Joomla',                tags: ['6 Plugins', 'Buy per-plugin', 'Joomla 4/5/6'] },
]

const pluginPages = [
  { slug: 'plugin-aeo-ai-signals', title: 'AI Boost AEO — AI Signals for Joomla',         tags: ['llms.txt', 'IndexNow', 'AI signals'] },
  { slug: 'plugin-schema',         title: 'AI Boost Schema — Schema.org JSON-LD',          tags: ['Schema.org', '20+ types', 'JSON-LD'] },
  { slug: 'plugin-opengraph',      title: 'AI Boost OpenGraph — Social Sharing Tags',      tags: ['OG tags', 'Twitter Cards', 'Social'] },
  { slug: 'plugin-sitemap-xml',    title: 'AI Boost Sitemap — XML Sitemap + Hreflang',     tags: ['Sitemap XML', 'Hreflang', 'Images'] },
  { slug: 'plugin-seo',            title: 'AI Boost Hreflang — Multilingual SEO',          tags: ['Hreflang', 'Canonical', 'Multilingual'] },
  { slug: 'plugin-code-manager',   title: 'AI Boost Code Manager — Analytics & Code',     tags: ['GA4', 'GTM', 'Meta Pixel'] },
]

function run() {
  mkdirSync(OUT_DIR, { recursive: true })

  let generated = 0

  for (const page of mainPages) {
    const svg = buildSvg(page.title, page.tags)
    const resvg = new Resvg(svg, {
      font: { loadSystemFonts: true, defaultFontFamily: 'DejaVu Sans' },
    })
    const pngData = resvg.render()
    const pngBuffer = pngData.asPng()
    const outPath = resolve(OUT_DIR, `${page.slug}.png`)
    writeFileSync(outPath, pngBuffer)
    console.log(`  ✓ ${page.slug}.png`)
    generated++
  }

  for (const post of blogPosts) {
    const svg = buildSvg(post.title, post.tags)
    const resvg = new Resvg(svg, {
      font: { loadSystemFonts: true, defaultFontFamily: 'DejaVu Sans' },
    })
    const pngData = resvg.render()
    const pngBuffer = pngData.asPng()
    const outPath = resolve(OUT_DIR, `${post.slug}.png`)
    writeFileSync(outPath, pngBuffer)
    console.log(`  ✓ ${post.slug}.png`)
    generated++
  }

  for (const page of pluginPages) {
    const svg = buildSvg(page.title, page.tags)
    const resvg = new Resvg(svg, {
      font: { loadSystemFonts: true, defaultFontFamily: 'DejaVu Sans' },
    })
    const pngData = resvg.render()
    const pngBuffer = pngData.asPng()
    const outPath = resolve(OUT_DIR, `${page.slug}.png`)
    writeFileSync(outPath, pngBuffer)
    console.log(`  ✓ ${page.slug}.png`)
    generated++
  }

  console.log(`\nGenerated ${generated} OG image(s) → website/public/og/`)
}

run()
