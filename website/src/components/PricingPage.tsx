import { Link } from 'react-router-dom'
import { Helmet } from 'react-helmet-async'
import logoSrc from '../assets/ai-boost-logo.svg'
import Breadcrumb from './Breadcrumb'
import { SiteHeader } from './SiteHeader'

type PluginStatus = 'live' | 'coming-soon'

const PURPLE = '#7B4FFF'
const SITE_URL = 'https://aiboostnow.com'
const BUY_BASE = 'https://aiboost.lemonsqueezy.com/checkout/buy'

// ── Per-plugin SKUs ───────────────────────────────────────────────────────────
const plugins: Array<{
  slug: string; icon: string; name: string; tagline: string; price: string;
  desc: string; url: string; badge: string | null; status: PluginStatus; features: string[];
}> = [
  {
    slug:    'aeo-ai-signals',
    icon:    '🤖',
    name:    'AI Boost AEO',
    tagline: 'llms.txt + AI signals',
    price:   '€20',
    desc:    'llms.txt, llms-full.txt, IndexNow, robots.txt AI rules, ai:description meta.',
    url:     `${BUY_BASE}/aiboost-aeo`,
    badge:   'Live now',
    status:  'live' as PluginStatus,
    features: [
      'llms.txt endpoint',
      'llms-full.txt (full Markdown article export)',
      'Speakable JSON-LD on articles',
      'ai:description meta tag',
      'IndexNow automatic ping',
      '25+ AI crawler rules in robots.txt',
    ],
  },
  {
    slug:    'schema',
    icon:    '🧠',
    name:    'AI Boost Schema',
    tagline: 'Schema.org JSON-LD',
    price:   '€20',
    desc:    'Schema.org JSON-LD on every page: Organization, LocalBusiness, Article, FAQ, Speakable.',
    url:     `${BUY_BASE}/aiboost-schema`,
    badge:   null,
    status:  'coming-soon' as PluginStatus,
    features: [
      'Organization, LocalBusiness, Person identity',
      'Article + BreadcrumbList on article pages',
      'FAQ schema — auto-detect + manual entry',
      'Speakable JSON-LD for AI assistants',
      'Falang multilingual schema support',
      'Staging mode — suppresses all output',
    ],
  },
  {
    slug:    'opengraph',
    icon:    '📣',
    name:    'AI Boost OpenGraph',
    tagline: 'Social tags',
    price:   '€20',
    desc:    'OG + Twitter Cards on every page, per-article images, per-category overrides.',
    url:     `${BUY_BASE}/aiboost-opengraph`,
    badge:   null,
    status:  'coming-soon' as PluginStatus,
    features: [
      'og:title, og:description, og:image',
      'Twitter Card (summary_large_image)',
      'Per-article image from Joomla media field',
      'Per-category custom field overrides',
      'Falang multilingual OG support',
    ],
  },
  {
    slug:    'sitemap-xml',
    icon:    '🗺️',
    name:    'AI Boost Sitemap',
    tagline: 'XML Sitemap + Hreflang',
    price:   '€20',
    desc:    'Dynamic sitemap.xml auto-generated. Image sitemap, priority, changefreq, hreflang links.',
    url:     `${BUY_BASE}/aiboost-sitemap`,
    badge:   null,
    status:  'coming-soon' as PluginStatus,
    features: [
      'Auto-generated sitemap.xml endpoint',
      'Image sitemap extension',
      'Priority + changefreq per content type',
      'Hreflang link tags in sitemap',
      'Include/exclude tags per article',
      'Ping on publish',
    ],
  },
  {
    slug:    'seo',
    icon:    '🌍',
    name:    'AI Boost Hreflang',
    tagline: 'Multilingual SEO',
    price:   '€20',
    desc:    'Hreflang tags, x-default, canonical URLs, Falang & Associations support.',
    url:     `${BUY_BASE}/aiboost-hreflang`,
    badge:   null,
    status:  'coming-soon' as PluginStatus,
    features: [
      'hreflang link tags on every page',
      'x-default fallback',
      'Canonical URL management',
      'Auto-detect Joomla language setup',
      'Manual language config for headless',
      'Falang alias map support',
    ],
  },
  {
    slug:    'code-manager',
    icon:    '📊',
    name:    'AI Boost Code Manager',
    tagline: 'Analytics + Custom code',
    price:   '€20',
    desc:    'GA4, GTM, Meta Pixel, Search Console, custom head/body/footer code injection.',
    url:     `${BUY_BASE}/aiboost-codemanager`,
    badge:   null,
    status:  'coming-soon' as PluginStatus,
    features: [
      'Google Analytics 4 (G-XXXXXXX)',
      'Google Tag Manager (GTM-XXXXXXX)',
      'Meta Pixel',
      'Search Console, Bing, Yandex verification',
      'Custom head / body / footer code',
      'Per-page opt-out via custom field',
      'Staging mode — suppresses all injection',
    ],
  },
]

// ── Bundle ────────────────────────────────────────────────────────────────────
const bundleFeatures = [
  'All 6 plugins — every feature included',
  'AEO: llms.txt, IndexNow, AI signals',
  'Schema.org (20+ types, 13 site presets)',
  'OpenGraph + Twitter Cards',
  'XML Sitemap + Hreflang multilingual',
  'GA4, GTM, Meta Pixel, site verification',
  'Annual updates + support',
  'Joomla 4 / 5 / 6 · PHP 8.1–8.5',
]

const faqs = [
  { q: 'Can I buy just one plugin?',                a: 'Yes — each of the 6 plugins is sold individually. Buy only what you need. The bundle gives you all 6 at a discount.' },
  { q: 'What does the Bundle include?',             a: 'The €45 bundle includes all 6 plugins: AEO, Schema, OpenGraph, Sitemap, Hreflang, and Code Manager. That\'s €120 worth of plugins for €45 — you save €75.' },
  { q: 'Is it compatible with Joomla 4, 5, and 6?', a: 'Yes. All AI Boost plugins support Joomla 4.0 through 6.x with PHP 8.1 through 8.5.' },
  { q: 'Are there free versions?',                  a: 'Every plugin has a free tier with core features. Pro features (advanced schema types, IndexNow, Speakable, Business Hours, etc.) require a paid license.' },
  { q: 'What happens when my license expires?',     a: 'The plugins continue to work. You only need to renew if you want new updates and continued support after the year is up.' },
  { q: 'Is EU VAT included in the price?',          a: 'VAT is added at checkout where applicable and remitted automatically — you don\'t need to worry about tax compliance.' },
]

const css = `
.ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
.ab-plugin-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
.ab-plugin-card { background:#fff; border:1.5px solid #E8E4F4; border-radius:20px; padding:28px 24px; display:flex; flex-direction:column; gap:0; }
.ab-plugin-price { font-size:40px; font-weight:900; letter-spacing:-1.5px; color:#0C0B1D; line-height:1; }
.ab-plugin-btn { display:block; text-align:center; padding:12px 0; font-weight:700; font-size:14px; border-radius:10px; text-decoration:none; background:${PURPLE}; color:#fff; margin-top:auto; }
.ab-feat-list { list-style:none; padding:0; margin:0 0 20px; display:flex; flex-direction:column; gap:8px; }
.ab-feat-item { display:flex; gap:8px; align-items:flex-start; font-size:13px; color:#5A5A7A; }
.ab-bundle { background:#7B4FFF; border-radius:24px; padding:40px 44px; display:grid; grid-template-columns:1fr 1fr; gap:40px; align-items:center; margin-top:40px; }
@media (max-width:960px) {
  .ab-plugin-grid { grid-template-columns:1fr 1fr; }
  .ab-bundle { grid-template-columns:1fr; }
  .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; }
}
@media (max-width:600px) {
  .ab-plugin-grid { grid-template-columns:1fr; }
}
`

export function PricingPage() {
  const canonicalUrl  = `${SITE_URL}/pricing`
  const pageTitle     = 'Pricing — AI Boost for Joomla'
  const pageDesc      = 'Buy individual AI Boost plugins (€20/year each) or get all 6 in the bundle for €45 — saves €75. Schema, OpenGraph, Hreflang, Sitemap, Code Manager, AEO. Joomla 4/5/6.'

  return (
    <div className="ab-wrap">
      <Helmet>
        <title>{pageTitle}</title>
        <meta name="description" content={pageDesc} />
        <link rel="canonical" href={canonicalUrl} />
        <meta property="og:type"        content="website" />
        <meta property="og:title"       content={pageTitle} />
        <meta property="og:description" content={pageDesc} />
        <meta property="og:url"         content={canonicalUrl} />
        <meta property="og:site_name"   content="AI Boost" />
        <meta property="og:image"       content={`${SITE_URL}/og/pricing.png`} />
        <meta property="og:image:width"  content="1200" />
        <meta property="og:image:height" content="630" />
        <meta name="twitter:card"        content="summary_large_image" />
        <meta name="twitter:title"       content={pageTitle} />
        <meta name="twitter:description" content={pageDesc} />
        <meta name="twitter:image"       content={`${SITE_URL}/og/pricing.png`} />
      </Helmet>

      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home',    item: SITE_URL },
          { '@type': 'ListItem', position: 2, name: 'Pricing', item: canonicalUrl },
        ],
      }) }} />
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'ItemList',
        name: 'AI Boost for Joomla — plugin pricing',
        itemListElement: plugins.map((p, i) => ({
          '@type': 'ListItem',
          position: i + 1,
          item: {
            '@type': 'Product',
            name: p.name,
            description: p.desc,
            offers: { '@type': 'Offer', price: p.price.replace('€', ''), priceCurrency: 'EUR', availability: 'https://schema.org/InStock' },
          },
        })),
      }) }} />

      <SiteHeader />
      <style>{css}</style>
      <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: 'Pricing' }]} />

      <main style={{ maxWidth: 1200, margin: '0 auto', padding: '48px 32px 96px' }}>

        {/* Hero */}
        <div style={{ textAlign: 'center', marginBottom: 64 }}>
          <h1 style={{ fontSize: 48, fontWeight: 900, letterSpacing: '-2px', margin: '0 0 16px', color: '#0C0B1D' }}>
            Buy only what you need.
          </h1>
          <p style={{ fontSize: 18, color: '#5A5A7A', margin: '0 0 8px' }}>
            6 standalone plugins — pick one or grab the full bundle at a discount.
          </p>
          <p style={{ fontSize: 14, color: '#9090B0', margin: 0 }}>
            All plugins work independently. Joomla 4 / 5 / 6 · PHP 8.1–8.5
          </p>
        </div>

        {/* Per-plugin grid */}
        <div className="ab-plugin-grid">
          {plugins.map(p => (
            <div key={p.slug} className="ab-plugin-card">
              <div style={{ fontSize: 32, marginBottom: 12 }}>{p.icon}</div>
              <div style={{ fontSize: 11, fontWeight: 700, color: '#9090B0', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 4 }}>{p.tagline}</div>
              <div style={{ fontSize: 17, fontWeight: 800, color: '#0C0B1D', marginBottom: 8 }}>
                {p.name}
                {p.badge && <span style={{ display: 'inline-block', background: PURPLE, color: '#fff', fontSize: 10, fontWeight: 800, padding: '2px 8px', borderRadius: 100, marginLeft: 8, verticalAlign: 'middle' }}>{p.badge}</span>}
              </div>
              <div style={{ fontSize: 13, color: '#5A5A7A', lineHeight: 1.6, marginBottom: 16 }}>{p.desc}</div>
              <ul className="ab-feat-list">
                {p.features.map(f => (
                  <li key={f} className="ab-feat-item">
                    <span style={{ color: PURPLE, fontWeight: 900, fontSize: 13, flexShrink: 0, marginTop: 1 }}>✓</span>
                    <span>{f}</span>
                  </li>
                ))}
              </ul>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 6, marginBottom: 16 }}>
                <span className="ab-plugin-price">{p.price}</span>
                <span style={{ fontSize: 13, color: '#B0B0C8' }}>/year</span>
              </div>
              {p.status === 'live' ? (
                <a href={p.url} target="_blank" rel="noopener noreferrer" className="ab-plugin-btn">
                  Buy {p.name.replace('AI Boost ', '')} →
                </a>
              ) : (
                <Link
                  to={`/plugins/${p.slug}`}
                  className="ab-plugin-btn"
                  style={{ background: '#E8E4F4', color: '#7B4FFF', textDecoration: 'none', display: 'block' }}
                >
                  Coming Soon — Notify me →
                </Link>
              )}
            </div>
          ))}
        </div>

        {/* Bundle */}
        <div className="ab-bundle">
          <div>
            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 8, background: 'rgba(255,255,255,.15)', border: '1px solid rgba(255,255,255,.3)', borderRadius: 100, padding: '4px 14px', marginBottom: 16, fontSize: 12, fontWeight: 700, color: '#fff', textTransform: 'uppercase', letterSpacing: .5 }}>
              Best Value
            </div>
            <h2 style={{ fontSize: 36, fontWeight: 900, letterSpacing: '-1.2px', color: '#fff', margin: '0 0 12px', lineHeight: 1.1 }}>
              All 6 Plugins Bundle
            </h2>
            <p style={{ fontSize: 16, color: 'rgba(255,255,255,.75)', margin: '0 0 28px', lineHeight: 1.65 }}>
              Everything in one license. Save €75 vs. buying each plugin separately.
            </p>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, marginBottom: 8 }}>
              <span style={{ fontSize: 56, fontWeight: 900, letterSpacing: '-2px', color: '#fff', lineHeight: 1 }}>€45</span>
              <span style={{ fontSize: 14, color: 'rgba(255,255,255,.5)' }}>/year</span>
            </div>
            <div style={{ fontSize: 13, color: 'rgba(255,255,255,.45)', marginBottom: 28 }}>
              +VAT where applicable · was €120
            </div>
            <a
              href={`${BUY_BASE}/aiboost-bundle`}
              target="_blank"
              rel="noopener noreferrer"
              style={{ display: 'inline-block', background: '#fff', color: PURPLE, fontWeight: 800, fontSize: 16, padding: '14px 32px', borderRadius: 12, textDecoration: 'none' }}
            >
              Get the Bundle →
            </a>
          </div>
          <div>
            <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 12 }}>
              {bundleFeatures.map(f => (
                <li key={f} style={{ display: 'flex', gap: 10, alignItems: 'flex-start', fontSize: 14, color: 'rgba(255,255,255,.85)' }}>
                  <span style={{ color: '#C4AFFF', fontWeight: 900, fontSize: 14, flexShrink: 0, marginTop: 1 }}>✓</span>
                  <span>{f}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>

        {/* Comparison table */}
        <div style={{ marginTop: 72, borderTop: '1px solid #E8E4F4', paddingTop: 64 }}>
          <h2 style={{ fontSize: 32, fontWeight: 900, letterSpacing: '-1px', margin: '0 0 8px', textAlign: 'center', color: '#0C0B1D' }}>What's in each plugin</h2>
          <p style={{ fontSize: 16, color: '#5A5A7A', textAlign: 'center', margin: '0 0 40px' }}>Free tier vs Pro — available now or coming soon.</p>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ borderCollapse: 'collapse', width: '100%', fontSize: 13 }}>
              <thead>
                <tr>
                  <th style={{ padding: '12px 16px', textAlign: 'left', background: '#F8F7FF', fontWeight: 700, color: '#0C0B1D', borderBottom: '2px solid #E8E4F4', minWidth: 200 }}>Feature</th>
                  <th style={{ padding: '12px 16px', textAlign: 'center', background: '#F8F7FF', fontWeight: 700, color: '#9090B0', borderBottom: '2px solid #E8E4F4' }}>Free</th>
                  <th style={{ padding: '12px 16px', textAlign: 'center', background: '#F3F0FF', fontWeight: 700, color: PURPLE, borderBottom: '2px solid #D4C9FF' }}>Pro — €20/year</th>
                  <th style={{ padding: '12px 16px', textAlign: 'center', background: '#F8F7FF', fontWeight: 700, color: '#9090B0', borderBottom: '2px solid #E8E4F4', whiteSpace: 'nowrap' }}>Plugin</th>
                </tr>
              </thead>
              <tbody>
                {[
                  { f: 'llms.txt endpoint',                free: true,  pro: true,  plugin: 'AEO',          live: true },
                  { f: 'IndexNow auto-ping on publish',    free: false, pro: true,  plugin: 'AEO',          live: true },
                  { f: 'llms-full.txt (Markdown export)',  free: false, pro: true,  plugin: 'AEO',          live: true },
                  { f: 'Speakable JSON-LD',                free: false, pro: true,  plugin: 'AEO',          live: true },
                  { f: '25+ AI crawler rules (robots.txt)',free: true,  pro: true,  plugin: 'AEO',          live: true },
                  { f: 'Schema.org Organization/Local',    free: true,  pro: true,  plugin: 'Schema',       live: false },
                  { f: '20+ schema types (Article, FAQ…)', free: false, pro: true,  plugin: 'Schema',       live: false },
                  { f: '13 site-type presets',             free: false, pro: true,  plugin: 'Schema',       live: false },
                  { f: 'OpenGraph + Twitter Cards',        free: true,  pro: true,  plugin: 'OpenGraph',    live: false },
                  { f: 'Per-article OG image override',    free: false, pro: true,  plugin: 'OpenGraph',    live: false },
                  { f: 'Auto-generated sitemap.xml',       free: true,  pro: true,  plugin: 'Sitemap',      live: false },
                  { f: 'Image sitemap + hreflang in XML',  free: false, pro: true,  plugin: 'Sitemap',      live: false },
                  { f: 'Hreflang link tags',               free: true,  pro: true,  plugin: 'Hreflang',     live: false },
                  { f: 'Canonical URL management',         free: false, pro: true,  plugin: 'Hreflang',     live: false },
                  { f: 'GA4 + GTM + Meta Pixel',           free: true,  pro: true,  plugin: 'Code Manager', live: false },
                  { f: 'Custom head/body/footer injection', free: false, pro: true,  plugin: 'Code Manager', live: false },
                ].map((row, i) => (
                  <tr key={i} style={{ background: i % 2 === 0 ? '#fff' : '#FAFAF9' }}>
                    <td style={{ padding: '11px 16px', color: '#0C0B1D', fontWeight: 500, borderBottom: '1px solid #F0ECF8' }}>{row.f}</td>
                    <td style={{ padding: '11px 16px', textAlign: 'center', borderBottom: '1px solid #F0ECF8' }}>
                      {row.free ? <span style={{ color: '#9090B0', fontSize: 15 }}>✓</span> : <span style={{ color: '#D4C9FF' }}>—</span>}
                    </td>
                    <td style={{ padding: '11px 16px', textAlign: 'center', borderBottom: '1px solid #E8E4F4', background: i % 2 === 0 ? '#F8F5FF' : '#F3F0FF' }}>
                      {row.pro ? <span style={{ color: PURPLE, fontWeight: 900, fontSize: 15 }}>✓</span> : <span style={{ color: '#D4C9FF' }}>—</span>}
                    </td>
                    <td style={{ padding: '11px 16px', textAlign: 'center', borderBottom: '1px solid #F0ECF8' }}>
                      <Link to={`/plugins/${row.plugin === 'AEO' ? 'aeo-ai-signals' : row.plugin === 'Schema' ? 'schema' : row.plugin === 'OpenGraph' ? 'opengraph' : row.plugin === 'Sitemap' ? 'sitemap-xml' : row.plugin === 'Hreflang' ? 'seo' : 'code-manager'}`}
                        style={{ fontSize: 12, color: row.live ? PURPLE : '#9090B0', fontWeight: 600, textDecoration: 'none', whiteSpace: 'nowrap' }}>
                        {row.plugin}{row.live ? ' 🟢' : ' 🔜'}
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>

        <div style={{ textAlign: 'center', marginTop: 24, fontSize: 13, color: '#9090B0' }}>
          EU VAT handled automatically at checkout · Annual license · Plugins continue working after expiry
        </div>

        {/* FAQ */}
        <div style={{ borderTop: '1px solid #E8E4F4', marginTop: 80, paddingTop: 72 }}>
          <h2 style={{ fontSize: 32, fontWeight: 900, letterSpacing: '-1px', margin: '0 0 48px', textAlign: 'center', color: '#0C0B1D' }}>Pricing FAQ</h2>
          <div style={{ maxWidth: 720, margin: '0 auto' }}>
            {faqs.map((faq, i) => (
              <div key={i} style={{ padding: '28px 0', borderBottom: '1px solid #E8E4F4' }}>
                <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 12, color: '#0C0B1D' }}>{faq.q}</div>
                <div style={{ fontSize: 14, color: '#5A5A7A', lineHeight: 1.7 }}>{faq.a}</div>
              </div>
            ))}
          </div>
        </div>

        {/* CTA */}
        <div style={{ background: '#F3F0FF', border: '1.5px solid #D4C9FF', borderRadius: 20, padding: '40px 36px', textAlign: 'center', marginTop: 72 }}>
          <h3 style={{ fontSize: 22, fontWeight: 800, margin: '0 0 12px', color: '#0C0B1D' }}>Questions before you buy?</h3>
          <p style={{ fontSize: 16, color: '#5A5A7A', margin: '0 0 28px' }}>Browse the full FAQ or send us a message.</p>
          <div style={{ display: 'flex', gap: 16, justifyContent: 'center', flexWrap: 'wrap' }}>
            <a href="mailto:support@aiboostnow.com" style={{ background: PURPLE, color: '#fff', fontWeight: 700, fontSize: 15, padding: '13px 28px', borderRadius: 10, textDecoration: 'none', display: 'inline-block' }}>
              Contact us →
            </a>
            <Link to="/faq" style={{ background: '#fff', border: '1.5px solid #D4C9FF', color: PURPLE, fontWeight: 700, fontSize: 15, padding: '13px 28px', borderRadius: 10, textDecoration: 'none', display: 'inline-block' }}>
              Browse FAQ
            </Link>
          </div>
        </div>
      </main>

      <footer className="ab-footer">
        <Link to="/"><img src={logoSrc} style={{ height: 54, width: 'auto' }} alt="AI Boost" /></Link>
        <div style={{ fontSize: 13, color: '#9090B0' }}>© 2026 AI Boost · support@aiboostnow.com</div>
        <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
          <Link to="/plugins"              style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Plugins</Link>
          <Link to="/plugins/aeo-ai-signals" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>AEO</Link>
          <Link to="/plugins/schema"       style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Schema</Link>
          <Link to="/plugins/opengraph"    style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>OpenGraph</Link>
          <Link to="/plugins/sitemap-xml"  style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Sitemap</Link>
          <Link to="/plugins/seo"          style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Hreflang</Link>
          <Link to="/plugins/code-manager" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Code Manager</Link>
          <Link to="/features"             style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Features</Link>
          <Link to="/docs"                 style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Docs</Link>
          <Link to="/blog"                 style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Blog</Link>
          <Link to="/faq"                  style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>FAQ</Link>
        </div>
      </footer>
    </div>
  )
}
