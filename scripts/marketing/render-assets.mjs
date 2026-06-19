#!/usr/bin/env node
/**
 * AI Boost — marketing asset renderer (Lemon Squeezy store).
 *
 * Renders, via headless Chromium (Playwright), from the brand logo:
 *   --inspect   the full logo on white (to eyeball the magnifying-glass region)
 *   --avatar    a round store avatar: the magnifying-glass mark on white
 *   --covers    a 1:1 cover image per Lemon Squeezy product
 *   --all       avatar + covers
 *
 * Output: deliverables/marketing/*.png  (gitignored).
 * Source logo: scripts/fixtures/brand/ai-boost-logo.svg
 *
 * No real/customer data appears here — purely brand + product names.
 */

import { chromium } from 'playwright';
import { readFileSync, mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(__dirname, '..', '..');
const OUT = resolve(REPO, 'deliverables', 'marketing');
const LOGO_PATH = resolve(REPO, 'scripts', 'fixtures', 'brand', 'ai-boost-logo.svg');

const PURPLE = '#7622a8';
const LOGO_VIEWBOX = { x: 0, y: 0, w: 612, h: 239 };

// Magnifying-glass crop region inside the 612×239 viewBox. Tuned after
// inspecting the full logo (see --inspect). Override via env for quick tuning:
//   ABLUPA="x y w h" node scripts/marketing/render-assets.mjs --avatar
const LUPA = (process.env.ABLUPA || '').trim()
  ? Object.fromEntries(['x', 'y', 'w', 'h'].map((k, i) => [k, Number(process.env.ABLUPA.split(/\s+/)[i])]))
  : { x: 18, y: 7, w: 232, h: 228 }; // the magnifying-glass + "Ai" mark, in viewBox units (measured from a clean render)

const rawLogo = readFileSync(LOGO_PATH, 'utf8');

/** Return the logo SVG markup with a custom viewBox (crops to a sub-region). */
function logoWithViewBox({ x, y, w, h }) {
  return rawLogo
    .replace(/<\?xml[^>]*\?>/, '')
    .replace(
      /viewBox="[^"]*"/,
      `viewBox="${x} ${y} ${w} ${h}" preserveAspectRatio="xMidYMid meet"`
    )
    .replace(/<svg /, '<svg width="100%" height="100%" ');
}

/** The five Lemon Squeezy products → cover copy. Prices per OPERATING.md /
 *  docs/license-plans.md (integrations bumped 2026-06-18: Multilang €35, YOOtheme €25). */
const PRODUCTS = [
  { slug: 'pro3',       title: 'AI Boost', sub: 'for Joomla', tier: 'PRO',       note: '3 sites',         price: '€65 / year' },
  { slug: 'pro10',      title: 'AI Boost', sub: 'for Joomla', tier: 'PRO+',      note: '10 sites',        price: '€120 / year' },
  { slug: 'unlimited',  title: 'AI Boost', sub: 'for Joomla', tier: 'UNLIMITED', note: 'Unlimited sites', price: '€180 / year' },
  { slug: 'multilang',  title: 'AI Boost', sub: 'for Joomla', tier: 'MULTILANG', note: 'Add-on',          price: '€35 / year' },
  { slug: 'yootheme',   title: 'AI Boost', sub: 'for Joomla', tier: 'YOOTHEME',  note: 'Add-on',          price: '€25 / year' },
];

function coverHtml(p) {
  const logo = logoWithViewBox(LOGO_VIEWBOX);
  return `<!doctype html><html><head><meta charset="utf-8"><style>
    * { margin: 0; box-sizing: border-box; }
    html, body { width: 1024px; height: 1024px; }
    .card {
      width: 1024px; height: 1024px; position: relative; overflow: hidden;
      background: radial-gradient(120% 120% at 18% 12%, #ffffff 0%, #f6f1fb 55%, #efe5f7 100%);
      font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .bar { position: absolute; top: 0; left: 0; right: 0; height: 14px; background: ${PURPLE}; }
    .logo { width: 620px; height: 242px; display: flex; align-items: center; justify-content: center; margin-bottom: 28px; }
    .logo svg { width: 100%; height: 100%; }
    .sub { font-size: 46px; color: #4b3a5a; font-weight: 600; letter-spacing: .5px; margin-top: 4px; }
    .badge {
      margin-top: 54px; padding: 22px 52px; border-radius: 999px;
      background: ${PURPLE}; color: #fff; font-size: 52px; font-weight: 800; letter-spacing: 2px;
    }
    .note { margin-top: 26px; font-size: 38px; color: ${PURPLE}; font-weight: 700; }
    .price { margin-top: 16px; font-size: 46px; color: #2a1b38; font-weight: 800; letter-spacing: .5px; }
    .foot { position: absolute; bottom: 54px; left: 0; right: 0; text-align: center; font-size: 30px; color: #8a7a99; }
  </style></head><body>
    <div class="card">
      <div class="bar"></div>
      <div class="logo">${logo}</div>
      <div class="sub">SEO &amp; AI Search for Joomla</div>
      <div class="badge">${p.tier}</div>
      <div class="note">${p.note}</div>
      <div class="price">${p.price}</div>
      <div class="foot">aiboostnow.com</div>
    </div>
  </body></html>`;
}

function avatarHtml(size) {
  // The viewBox of this Illustrator export cannot be re-cropped (a group
  // transform overrides it), so render the FULL logo and crop with CSS:
  // an oversized logo positioned inside an overflow:hidden circle so the
  // magnifying-glass + "Ai" mark (LUPA region) lands centred.
  const logo = logoWithViewBox(LOGO_VIEWBOX);
  const pad = Math.round(size * 0.1);
  const visible = size - 2 * pad;
  const k = visible / Math.max(LUPA.w, LUPA.h);    // px per viewBox unit
  const frameW = LUPA.w * k;
  const frameH = LUPA.h * k;
  const logoW = LOGO_VIEWBOX.w * k;
  const logoH = LOGO_VIEWBOX.h * k;
  // The .frame is exactly the mark's bounding box (overflow:hidden clips out the
  // neighbouring "Boost" glyphs); the oversized .logo is shifted so the mark's
  // top-left lands at the frame origin. The frame is then centred in the circle.
  return `<!doctype html><html><head><meta charset="utf-8"><style>
    * { margin: 0; box-sizing: border-box; }
    html, body { width: ${size}px; height: ${size}px; background: transparent; }
    .circle {
      width: ${size}px; height: ${size}px; border-radius: 50%; background: #ffffff;
      display: flex; align-items: center; justify-content: center; overflow: hidden;
    }
    .frame { position: relative; width: ${frameW}px; height: ${frameH}px; overflow: hidden; }
    .logo { position: absolute; left: ${-LUPA.x * k}px; top: ${-LUPA.y * k}px; width: ${logoW}px; height: ${logoH}px; }
    .logo svg { width: 100%; height: 100%; display: block; }
  </style></head><body><div class="circle"><div class="frame"><div class="logo">${logo}</div></div></div></body></html>`;
}

function inspectHtml() {
  const logo = logoWithViewBox(LOGO_VIEWBOX);
  // Show a faint coordinate grid (every ~60 units of the 612×239 viewBox) so the
  // magnifying-glass region can be read off in viewBox coordinates.
  return `<!doctype html><html><head><meta charset="utf-8"><style>
    * { margin: 0; box-sizing: border-box; }
    body { background: #fff; }
    .wrap { width: 1224px; height: 478px; position: relative; }
    .wrap svg { width: 1224px; height: 478px; }
    .grid { position: absolute; inset: 0; pointer-events: none; }
    .v { position: absolute; top: 0; bottom: 0; width: 1px; background: rgba(255,0,0,.25); }
    .h { position: absolute; left: 0; right: 0; height: 1px; background: rgba(0,0,255,.25); }
    .lbl { position: absolute; font: 12px monospace; color: red; }
  </style></head><body>
    <div class="wrap">${logo}
      <div class="grid" id="grid"></div>
    </div>
    <script>
      const W=1224, H=478, vbW=612, vbH=239, g=document.getElementById('grid');
      for (let vx=0; vx<=vbW; vx+=51) { const px=vx/vbW*W;
        const d=document.createElement('div'); d.className='v'; d.style.left=px+'px'; g.appendChild(d);
        const l=document.createElement('div'); l.className='lbl'; l.style.left=(px+2)+'px'; l.style.top='2px'; l.textContent=vx; g.appendChild(l); }
      for (let vy=0; vy<=vbH; vy+=51) { const py=vy/vbH*H;
        const d=document.createElement('div'); d.className='h'; d.style.top=py+'px'; g.appendChild(d);
        const l=document.createElement('div'); l.className='lbl'; l.style.left='2px'; l.style.top=(py+2)+'px'; l.textContent=vy; g.appendChild(l); }
    </script>
  </body></html>`;
}

async function render(page, html, { width, height, omitBackground = false, out }) {
  await page.setViewportSize({ width, height });
  await page.emulateMedia({ media: 'screen' });
  await page.setContent(html, { waitUntil: 'networkidle' });
  const buf = await page.screenshot({ omitBackground, clip: { x: 0, y: 0, width, height } });
  mkdirSync(dirname(out), { recursive: true });
  writeFileSync(out, buf);
  console.log('wrote', out);
}

async function main() {
  const args = new Set(process.argv.slice(2));
  const doAll = args.has('--all');
  const browser = await chromium.launch();

  async function withScale(scale, fn) {
    const ctx = await browser.newContext({ deviceScaleFactor: scale });
    const page = await ctx.newPage();
    await fn(page);
    await ctx.close();
  }

  mkdirSync(OUT, { recursive: true });

  if (args.has('--inspect')) {
    await withScale(1, (page) => render(page, inspectHtml(), { width: 1224, height: 478, out: resolve(OUT, '_inspect-logo.png') }));
  }

  if (args.has('--avatar') || doAll) {
    for (const size of [512, 1024]) {
      await withScale(2, (page) => render(page, avatarHtml(size), { width: size, height: size, omitBackground: false, out: resolve(OUT, `avatar-${size}.png`) }));
    }
  }

  if (args.has('--covers') || doAll) {
    for (const p of PRODUCTS) {
      await withScale(1, (page) => render(page, coverHtml(p), { width: 1024, height: 1024, out: resolve(OUT, `product-${p.slug}.png`) }));
    }
  }

  await browser.close();
}

main().catch((e) => { console.error(e); process.exit(1); });
