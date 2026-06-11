import { Link } from 'react-router-dom'
import { Helmet } from 'react-helmet-async'
import logoSrc from '../assets/ai-boost-logo.svg'
import Breadcrumb from './Breadcrumb'
import { SiteHeader } from './SiteHeader'

const PURPLE   = '#7B4FFF'
const SITE_URL = 'https://aiboostnow.com'
const BUY_BASE = 'https://aiboost.lemonsqueezy.com/checkout/buy'

// ── Pricing tiers (decided 2026-06-08) ───────────────────────────────────────
const tiers: Array<{
  id: string; name: string; sites: string; price: number; highlight: boolean;
  badge: string | null; url: string; features: string[];
}> = [
  {
    id:        'pro',
    name:      'AI Boost PRO',
    sites:     '3 websites',
    price:     65,
    highlight: false,
    badge:     null,
    url:       `${BUY_BASE}/aiboost-pro-3`,
    features:  [
      'All 6 Pro plugins — every feature included',
      'Schema.org (20+ types, 13 site presets)',
      'AEO: llms.txt, IndexNow, AI signals',
      'OpenGraph + Twitter Cards',
      'XML Sitemap + Hreflang multilingual',
      'GA4, GTM, Meta Pixel, site verification',
      'Joomla 4 / 5 / 6 · PHP 8.1–8.5',
      'Annual updates + support',
    ],
  },
  {
    id:        'proplus',
    name:      'AI Boost Pro+',
    sites:     '10 websites',
    price:     120,
    highlight: true,
    badge:     'Most Popular',
    url:       `${BUY_BASE}/aiboost-pro-10`,
    features:  [
      'Everything in AI Boost PRO',
      'Up to 10 website licenses',
      'Priority support',
      'Same-day security patches',
    ],
  },
  {
    id:        'unlimited',
    name:      'AI Boost Unlimited',
    sites:     'Unlimited websites',
    price:     180,
    highlight: false,
    badge:     null,
    url:       `${BUY_BASE}/aiboost-unlimited`,
    features:  [
      'Everything in AI Boost Pro+',
      'Unlimited website licenses',
      'Agency / developer use',
      'White-label ready',
    ],
  },
]

const allFeatures = [
  { f: 'Schema.org JSON-LD (Organization, Article, FAQ…)',    free: true,  pro: true },
  { f: '20+ schema types + 13 site-type presets',             free: false, pro: true },
  { f: 'AEO: llms.txt + llms-full.txt + Markdown export',    free: true,  pro: true },
  { f: 'IndexNow automatic ping on publish',                  free: false, pro: true },
  { f: 'Speakable JSON-LD for AI assistants',                 free: false, pro: true },
  { f: '25+ AI crawler rules in robots.txt',                  free: true,  pro: true },
  { f: 'XML sitemap.xml auto-generated',                      free: true,  pro: true },
  { f: 'Image sitemap + news sitemap',                        free: false, pro: true },
  { f: 'Hreflang link tags (multilingual)',                   free: true,  pro: true },
  { f: 'Canonical URL management',                            free: false, pro: true },
  { f: 'OpenGraph + Twitter Cards',                           free: true,  pro: true },
  { f: 'Per-article OG image override',                       free: false, pro: true },
  { f: 'GA4, GTM, Meta Pixel',                               free: true,  pro: true },
  { f: 'Custom head / body / footer code injection',          free: false, pro: true },
  { f: 'Business Hours, HowTo, Event rich results',           free: false, pro: true },
  { f: 'FAQ schema — auto-detect + manual entry',             free: true,  pro: true },
  { f: 'Falang multilingual schema + social support',         free: false, pro: true },
]

const faqs = [
  { q: 'What does the license cover?',
    a: 'One license covers all 6 AI Boost Pro plugins on the number of websites shown in the tier (3, 10, or unlimited). Install on any Joomla 4, 5, or 6 site.' },
  { q: 'Is there a free version?',
    a: 'Yes. Every plugin ships with a free tier that covers the core features — Schema, sitemap, OG tags, llms.txt, GA4, hreflang basics. Pro features (advanced schema types, IndexNow, Speakable, Business Hours, etc.) require a paid license.' },
  { q: 'What happens when my license expires?',
    a: 'The plugins continue to work — Pro features stay active forever once unlocked. You only need to renew to receive new updates and priority support after the year is up.' },
  { q: 'Can I upgrade from PRO to Pro+ later?',
    a: 'Yes. Contact support and we will issue a pro-rated upgrade link. You never lose access.' },
  { q: 'Is EU VAT included in the price?',
    a: 'VAT is added at checkout where applicable and remitted automatically — you do not need to worry about tax compliance.' },
  { q: 'Is it compatible with Joomla 4, 5, and 6?',
    a: 'Yes. All AI Boost plugins support Joomla 4.0 through 6.x with PHP 8.1 through 8.5.' },
]

const css = `
.ab-tier-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; align-items:start; }
.ab-tier-card { background:#fff; border:1.5px solid #E8E4F4; border-radius:20px; padding:32px 28px; display:flex; flex-direction:column; gap:0; position:relative; }
.ab-tier-card--highlight { border-color:${PURPLE}; box-shadow:0 0 0 3px rgba(123,79,255,.12); }
.ab-tier-price { font-size:48px; font-weight:900; letter-spacing:-2px; color:#0C0B1D; line-height:1; }
.ab-tier-btn { display:block; text-align:center; padding:14px 0; font-weight:700; font-size:15px; border-radius:12px; text-decoration:none; margin-top:auto; }
.ab-feat-list { list-style:none; padding:0; margin:0 0 24px; display:flex; flex-direction:column; gap:10px; }
.ab-feat-item { display:flex; gap:8px; align-items:flex-start; font-size:13px; color:#5A5A7A; }
.ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
@media (max-width:960px) {
  .ab-tier-grid { grid-template-columns:1fr; max-width:480px; margin:0 auto; }
  .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; }
}
`

export function PricingPage() {
  const canonicalUrl = `${SITE_URL}/pricing`
  const pageTitle    = 'Pricing — AI Boost for Joomla'
  const pageDesc     = 'AI Boost PRO for Joomla — from €65/year. All 6 Pro plugins: Schema.org, AEO/llms.txt, OpenGraph, Sitemap, Hreflang, Analytics. 3, 10, or unlimited websites. Joomla 4/5/6.'

  return (
    <div className="ab-wrap">
      <Helmet>
        <title>{pageTitle}</title>
        <meta name="description" content={pageDesc} />
        <link rel="canonical" href={canonicalUrl} />
        <meta property="og:type"         content="website" />
        <meta property="og:title"        content={pageTitle} />
        <meta property="og:description"  content={pageDesc} />
        <meta property="og:url"          content={canonicalUrl} />
        <meta property="og:site_name"    content="AI Boost" />
        <meta property="og:image"        content={`${SITE_URL}/og/pricing.png`} />
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
        name: 'AI Boost for Joomla — pricing tiers',
        itemListElement: tiers.map((t, i) => ({
          '@type': 'ListItem',
          position: i + 1,
          item: {
            '@type': 'Product',
            name: t.name,
            description: `${t.sites} — all 6 AI Boost Pro plugins`,
            offers: {
              '@type': 'Offer',
              price: t.price,
              priceCurrency: 'EUR',
              priceValidUntil: '2027-01-01',
              availability: 'https://schema.org/InStock',
            },
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
            All 6 Pro plugins.<br />One simple price.
          </h1>
          <p style={{ fontSize: 18, color: '#5A5A7A', margin: '0 0 8px' }}>
            Pick the tier that fits your number of websites.
          </p>
          <p style={{ fontSize: 14, color: '#9090B0', margin: 0 }}>
            Annual subscription · Plugins keep working after expiry · Joomla 4 / 5 / 6 · PHP 8.1–8.5
          </p>
        </div>

        {/* Tier cards */}
        <div className="ab-tier-grid">
          {tiers.map(t => (
            <div key={t.id} className={`ab-tier-card${t.highlight ? ' ab-tier-card--highlight' : ''}`}>
              {t.badge && (
                <div style={{ position: 'absolute', top: -13, left: '50%', transform: 'translateX(-50%)', background: PURPLE, color: '#fff', fontSize: 11, fontWeight: 800, padding: '4px 14px', borderRadius: 100, whiteSpace: 'nowrap', letterSpacing: .5 }}>
                  {t.badge}
                </div>
              )}
              <div style={{ fontSize: 13, fontWeight: 700, color: PURPLE, textTransform: 'uppercase', letterSpacing: 1, marginBottom: 6 }}>{t.sites}</div>
              <div style={{ fontSize: 20, fontWeight: 800, color: '#0C0B1D', marginBottom: 16 }}>{t.name}</div>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 6, marginBottom: 4 }}>
                <span className="ab-tier-price">€{t.price}</span>
                <span style={{ fontSize: 13, color: '#B0B0C8' }}>/year</span>
              </div>
              <div style={{ fontSize: 12, color: '#B0B0C8', marginBottom: 24 }}>+VAT where applicable</div>
              <ul className="ab-feat-list">
                {t.features.map(f => (
                  <li key={f} className="ab-feat-item">
                    <span style={{ color: PURPLE, fontWeight: 900, fontSize: 13, flexShrink: 0, marginTop: 1 }}>✓</span>
                    <span>{f}</span>
                  </li>
                ))}
              </ul>
              <a
                href={t.url}
                target="_blank"
                rel="noopener noreferrer"
                className="ab-tier-btn"
                style={t.highlight
                  ? { background: PURPLE, color: '#fff' }
                  : { background: '#F3F0FF', color: PURPLE }
                }
              >
                Get {t.name.replace('AI Boost ', '')} →
              </a>
            </div>
          ))}
        </div>

        {/* Feature comparison */}
        <div style={{ marginTop: 80, borderTop: '1px solid #E8E4F4', paddingTop: 64 }}>
          <h2 style={{ fontSize: 32, fontWeight: 900, letterSpacing: '-1px', margin: '0 0 8px', textAlign: 'center', color: '#0C0B1D' }}>Free vs Pro — what's included</h2>
          <p style={{ fontSize: 16, color: '#5A5A7A', textAlign: 'center', margin: '0 0 40px' }}>Every Pro tier includes all features below.</p>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ borderCollapse: 'collapse', width: '100%', fontSize: 13 }}>
              <thead>
                <tr>
                  <th style={{ padding: '12px 16px', textAlign: 'left', background: '#F8F7FF', fontWeight: 700, color: '#0C0B1D', borderBottom: '2px solid #E8E4F4', minWidth: 260 }}>Feature</th>
                  <th style={{ padding: '12px 16px', textAlign: 'center', background: '#F8F7FF', fontWeight: 700, color: '#9090B0', borderBottom: '2px solid #E8E4F4' }}>Free</th>
                  <th style={{ padding: '12px 16px', textAlign: 'center', background: '#F3F0FF', fontWeight: 700, color: PURPLE, borderBottom: '2px solid #D4C9FF' }}>Pro (all tiers)</th>
                </tr>
              </thead>
              <tbody>
                {allFeatures.map((row, i) => (
                  <tr key={i} style={{ background: i % 2 === 0 ? '#fff' : '#FAFAF9' }}>
                    <td style={{ padding: '11px 16px', color: '#0C0B1D', fontWeight: 500, borderBottom: '1px solid #F0ECF8' }}>{row.f}</td>
                    <td style={{ padding: '11px 16px', textAlign: 'center', borderBottom: '1px solid #F0ECF8' }}>
                      {row.free ? <span style={{ color: '#9090B0', fontSize: 15 }}>✓</span> : <span style={{ color: '#D4C9FF' }}>—</span>}
                    </td>
                    <td style={{ padding: '11px 16px', textAlign: 'center', borderBottom: '1px solid #E8E4F4', background: i % 2 === 0 ? '#F8F5FF' : '#F3F0FF' }}>
                      <span style={{ color: PURPLE, fontWeight: 900, fontSize: 15 }}>✓</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div style={{ textAlign: 'center', marginTop: 20, fontSize: 13, color: '#9090B0' }}>
            EU VAT handled automatically at checkout · Annual license · Plugins continue working after expiry
          </div>
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
          <Link to="/plugins"   style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Plugins</Link>
          <Link to="/features"  style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Features</Link>
          <Link to="/docs"      style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Docs</Link>
          <Link to="/blog"      style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Blog</Link>
          <Link to="/faq"       style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>FAQ</Link>
          <Link to="/pricing"   style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Pricing</Link>
        </div>
      </footer>
    </div>
  )
}
