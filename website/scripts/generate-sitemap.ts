/**
 * Generates website/public/sitemap.xml from static pages and blog post data.
 *
 * Called automatically via the `prebuild` npm script.
 * Run manually: tsx ./scripts/generate-sitemap.ts
 */

import { writeFileSync } from 'node:fs'
import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { blogPosts } from '../src/data/blogPosts.js'

const __dirname = dirname(fileURLToPath(import.meta.url))
const OUT_FILE = resolve(__dirname, '../public/sitemap.xml')

const SITE_URL = 'https://aiboostnow.com'

interface SitemapEntry {
  loc: string
  lastmod: string
  changefreq: string
  priority: string
}

const today = new Date().toISOString().slice(0, 10)

const staticPages: SitemapEntry[] = [
  { loc: `${SITE_URL}/`, lastmod: today, changefreq: 'weekly', priority: '1.0' },
  { loc: `${SITE_URL}/features`, lastmod: today, changefreq: 'monthly', priority: '0.9' },
  { loc: `${SITE_URL}/pricing`, lastmod: today, changefreq: 'monthly', priority: '0.9' },
  { loc: `${SITE_URL}/docs`, lastmod: today, changefreq: 'monthly', priority: '0.8' },
  { loc: `${SITE_URL}/faq`, lastmod: today, changefreq: 'monthly', priority: '0.7' },
  { loc: `${SITE_URL}/blog`, lastmod: today, changefreq: 'weekly', priority: '0.8' },
]

const blogEntries: SitemapEntry[] = blogPosts.map((post) => ({
  loc: `${SITE_URL}/blog/${post.slug}`,
  lastmod: post.date,
  changefreq: 'monthly',
  priority: '0.7',
}))

const allEntries = [...staticPages, ...blogEntries]

function renderEntry(entry: SitemapEntry): string {
  return [
    '  <url>',
    `    <loc>${entry.loc}</loc>`,
    `    <lastmod>${entry.lastmod}</lastmod>`,
    `    <changefreq>${entry.changefreq}</changefreq>`,
    `    <priority>${entry.priority}</priority>`,
    '  </url>',
  ].join('\n')
}

const xml = [
  '<?xml version="1.0" encoding="UTF-8"?>',
  '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
  allEntries.map(renderEntry).join('\n'),
  '</urlset>',
  '',
].join('\n')

writeFileSync(OUT_FILE, xml, 'utf-8')
console.log(`[sitemap] ✅ Generated ${allEntries.length} URLs → public/sitemap.xml`)
console.log(`[sitemap]    ${staticPages.length} static pages + ${blogEntries.length} blog posts`)
