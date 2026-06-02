import { Helmet } from 'react-helmet-async'
import { Link } from 'react-router-dom'
import logoSrc from '../assets/ai-boost-logo.svg'
import { SiteHeader } from './SiteHeader'
import Breadcrumb from './Breadcrumb'
import type { PluginDef, MockupPanel } from '../data/pluginsData'

const PURPLE = '#7B4FFF'
const SITE_URL = 'https://aiboostnow.com'

const css = `
.ps-hero { background:linear-gradient(135deg,#0C0B1D 0%,#1A1540 100%); padding:96px 32px 88px; text-align:center; }
.ps-hero-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(123,79,255,.18); border:1px solid rgba(123,79,255,.4); border-radius:100px; padding:5px 16px; margin-bottom:24px; font-size:12px; font-weight:700; color:#C4AFFF; text-transform:uppercase; letter-spacing:.5px; }
.ps-hero-h1 { font-size:52px; font-weight:900; line-height:1.08; letter-spacing:-2px; color:#fff; margin:0 0 20px; max-width:820px; margin-left:auto; margin-right:auto; }
.ps-hero-sub { font-size:19px; color:rgba(255,255,255,.65); max-width:580px; margin:0 auto 40px; line-height:1.65; }
.ps-hero-cta { display:inline-block; background:#7B4FFF; color:#fff; font-size:17px; font-weight:700; padding:16px 36px; border-radius:12px; text-decoration:none; box-shadow:0 4px 32px rgba(123,79,255,.45); }
.ps-hero-sub-cta { display:inline-block; color:rgba(255,255,255,.45); font-size:14px; margin-top:14px; }
.ps-section { padding:80px 32px; }
.ps-section-inner { max-width:1100px; margin:0 auto; }
.ps-h2 { font-size:38px; font-weight:900; letter-spacing:-1.2px; color:#0C0B1D; margin:0 0 16px; text-align:center; }
.ps-h2-sub { font-size:17px; color:#5A5A7A; text-align:center; margin:0 0 56px; max-width:540px; margin-left:auto; margin-right:auto; line-height:1.6; }
.ps-feat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
.ps-feat-card { background:#F8F7FF; border:1.5px solid #E8E4F4; border-radius:16px; padding:28px 24px; }
.ps-feat-icon { font-size:28px; margin-bottom:12px; }
.ps-feat-title { font-size:15px; font-weight:800; color:#0C0B1D; margin-bottom:8px; }
.ps-feat-desc { font-size:13px; color:#5A5A7A; line-height:1.65; }
.ps-compare { border-collapse:collapse; width:100%; font-size:14px; }
.ps-compare th { padding:14px 20px; text-align:left; background:#F8F7FF; font-weight:700; border-bottom:2px solid #E8E4F4; }
.ps-compare td { padding:14px 20px; border-bottom:1px solid #F0ECF8; color:#0C0B1D; }
.ps-compare tr:last-child td { border-bottom:none; }
.ps-compare .pro-col { background:#F3F0FF; }
.ps-check-y { color:#7B4FFF; font-weight:900; font-size:16px; }
.ps-check-n { color:#C0BCDA; font-size:16px; }
.ps-price-card { background:linear-gradient(135deg,#7B4FFF 0%,#5B2FE0 100%); border-radius:24px; padding:48px 44px; max-width:480px; margin:0 auto; text-align:center; }
.ps-faq-item { padding:28px 0; border-bottom:1px solid #E8E4F4; }
.ps-faq-q { font-size:16px; font-weight:700; color:#0C0B1D; margin-bottom:12px; }
.ps-faq-a { font-size:14px; color:#5A5A7A; line-height:1.7; }
.ps-social-proof { background:#F8F7FF; border:1.5px solid #E8E4F4; border-radius:20px; padding:40px 44px; text-align:center; }
.ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
@media(max-width:960px){
  .ps-hero-h1 { font-size:36px; }
  .ps-feat-grid { grid-template-columns:1fr 1fr; }
}
@media(max-width:600px){
  .ps-hero { padding:64px 20px 56px; }
  .ps-hero-h1 { font-size:28px; letter-spacing:-1px; }
  .ps-hero-sub { font-size:16px; }
  .ps-feat-grid { grid-template-columns:1fr; }
  .ps-section { padding:56px 20px; }
  .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; }
  .ps-price-card { padding:32px 24px; }
}
`

function MockupPanelBlock({ panel }: { panel: MockupPanel }) {
  if (panel.type === 'ping' && panel.pingRows) {
    return (
      <div style={{ background: '#1A1540', border: '1px solid rgba(123,79,255,.3)', borderRadius: 16, overflow: 'hidden' }}>
        <div style={{ background: 'rgba(123,79,255,.15)', borderBottom: '1px solid rgba(123,79,255,.2)', padding: '12px 20px', display: 'flex', alignItems: 'center', gap: 12 }}>
          <span style={{ fontSize: 14, fontWeight: 700, color: '#C4AFFF' }}>{panel.icon} {panel.label}</span>
          <span style={{ fontSize: 11, color: '#4ADE80', marginLeft: 'auto' }}>● {panel.subLabel}</span>
        </div>
        <div style={{ padding: '20px' }}>
          {panel.pingRows.map(row => (
            <div key={row.engine} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 0', borderBottom: '1px solid rgba(255,255,255,.06)', fontSize: 13, color: '#A0B0FF' }}>
              <span>{row.engine}</span>
              <span style={{ color: '#4ADE80', fontWeight: 700 }}>{row.status}</span>
              <span style={{ color: 'rgba(255,255,255,.3)' }}>{row.ms}</span>
            </div>
          ))}
          <div style={{ marginTop: 16, fontSize: 12, color: 'rgba(255,255,255,.35)' }}>
            URL submitted: /blog/your-latest-article
          </div>
        </div>
      </div>
    )
  }

  return (
    <div style={{ background: '#1A1540', border: '1px solid rgba(123,79,255,.3)', borderRadius: 16, overflow: 'hidden' }}>
      <div style={{ background: 'rgba(123,79,255,.15)', borderBottom: '1px solid rgba(123,79,255,.2)', padding: '12px 20px', display: 'flex', alignItems: 'center', gap: 12 }}>
        <span style={{ fontSize: 14, fontWeight: 700, color: '#C4AFFF' }}>{panel.icon} {panel.label}</span>
        <span style={{ fontSize: 11, color: 'rgba(255,255,255,.3)', marginLeft: 'auto' }}>{panel.subLabel}</span>
      </div>
      <pre style={{ margin: 0, padding: '20px', fontSize: 12, color: '#A0B0FF', lineHeight: 1.7, fontFamily: 'monospace', overflowX: 'auto' }}>{panel.content}</pre>
    </div>
  )
}

interface Props {
  plugin: PluginDef
}

export function PluginSalesPage({ plugin }: Props) {
  const canonicalUrl = `${SITE_URL}/plugins/${plugin.slug}`
  const pageTitle    = `${plugin.name} — ${plugin.tagline} for Joomla`
  const pageDesc     = `${plugin.desc} Free & Pro tiers. Joomla 4/5/6 · PHP 8.1–8.5.`

  const featureDetails = plugin.featureDetails ?? []
  const compareRows    = plugin.compareRows    ?? []
  const faqs           = plugin.faqs           ?? []
  const pricingFeatures = plugin.pricingFeatures ?? []
  const mockups        = plugin.mockups        ?? []
  const socialProof    = plugin.socialProof

  const featuresTitle = plugin.featuresTitle ?? 'Everything your site needs'
  const featuresSub   = plugin.featuresSub   ?? 'Powerful features built for Joomla — zero configuration required.'
  const mockupsTitle  = plugin.mockupsTitle  ?? 'See what it generates'
  const mockupsSub    = plugin.mockupsSub    ?? 'One click in Joomla admin — the result appears on your site instantly.'

  const schema = {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: plugin.name,
    applicationCategory: 'WebApplication',
    operatingSystem: 'Joomla 4, Joomla 5, Joomla 6',
    description: plugin.desc,
    url: canonicalUrl,
    offers: [
      { '@type': 'Offer', price: '0', priceCurrency: 'EUR', name: 'Free' },
      { '@type': 'Offer', price: plugin.price.replace('€', ''), priceCurrency: 'EUR', name: 'Pro', description: 'Annual license' },
    ],
    publisher: {
      '@type': 'Organization',
      name: 'AI Boost',
      url: SITE_URL,
    },
  }

  const faqSchema = faqs.length > 0 ? {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faqs.map(f => ({
      '@type': 'Question',
      name: f.q,
      acceptedAnswer: { '@type': 'Answer', text: f.a },
    })),
  } : null

  const breadcrumbSchema = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'Home',    item: SITE_URL },
      { '@type': 'ListItem', position: 2, name: 'Plugins', item: `${SITE_URL}/plugins` },
      { '@type': 'ListItem', position: 3, name: plugin.name, item: canonicalUrl },
    ],
  }

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
        <meta property="og:image"       content={`${SITE_URL}/og/plugin-${plugin.slug}.png`} />
        <meta property="og:image:width"  content="1200" />
        <meta property="og:image:height" content="630" />
        <meta name="twitter:card"        content="summary_large_image" />
        <meta name="twitter:title"       content={pageTitle} />
        <meta name="twitter:description" content={pageDesc} />
        <meta name="twitter:image"       content={`${SITE_URL}/og/plugin-${plugin.slug}.png`} />
      </Helmet>

      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(schema) }} />
      {faqSchema && <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(faqSchema) }} />}
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(breadcrumbSchema) }} />

      <style>{css}</style>
      <SiteHeader />
      <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: 'Plugins', to: '/plugins' }, { label: plugin.shortName }]} />

      {/* Hero */}
      <section className="ps-hero">
        <div className="ps-hero-badge">{plugin.icon} {plugin.shortName}</div>
        <h1 className="ps-hero-h1">{plugin.heroHeadline}</h1>
        <p className="ps-hero-sub">{plugin.heroSub}</p>
        <a href={plugin.buyUrl} target="_blank" rel="noopener noreferrer" className="ps-hero-cta">
          Buy {plugin.shortName} — {plugin.price}/year →
        </a>
        <div className="ps-hero-sub-cta">Free tier available · Joomla 4 / 5 / 6 · PHP 8.1–8.5</div>
      </section>

      {/* Features grid */}
      {featureDetails.length > 0 && (
        <section className="ps-section">
          <div className="ps-section-inner">
            <h2 className="ps-h2">{featuresTitle}</h2>
            <p className="ps-h2-sub">{featuresSub}</p>
            <div className="ps-feat-grid">
              {featureDetails.map(f => (
                <div key={f.title} className="ps-feat-card">
                  <div className="ps-feat-icon">{f.icon}</div>
                  <div className="ps-feat-title">{f.title}</div>
                  <div className="ps-feat-desc">{f.desc}</div>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* Live output illustration */}
      {mockups.length > 0 && (
        <section className="ps-section" style={{ background: '#0C0B1D' }}>
          <div className="ps-section-inner">
            <h2 className="ps-h2" style={{ color: '#fff' }}>{mockupsTitle}</h2>
            <p className="ps-h2-sub" style={{ color: 'rgba(255,255,255,.55)' }}>{mockupsSub}</p>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 24 }}>
              {mockups.map((panel, i) => (
                <MockupPanelBlock key={i} panel={panel} />
              ))}
            </div>
          </div>
        </section>
      )}

      {/* Free vs Pro comparison */}
      {compareRows.length > 0 && (
        <section className="ps-section" style={{ background: '#F8F7FF', borderTop: '1px solid #E8E4F4', borderBottom: '1px solid #E8E4F4' }}>
          <div className="ps-section-inner">
            <h2 className="ps-h2">Free vs Pro</h2>
            <p className="ps-h2-sub">Start free. Upgrade when you need the full feature set.</p>
            <div style={{ overflowX: 'auto' }}>
              <table className="ps-compare">
                <thead>
                  <tr>
                    <th style={{ width: '55%' }}>Feature</th>
                    <th style={{ width: '22%', textAlign: 'center' }}>Free</th>
                    <th className="pro-col" style={{ width: '23%', textAlign: 'center', color: PURPLE }}>Pro — {plugin.price}/year</th>
                  </tr>
                </thead>
                <tbody>
                  {compareRows.map(row => (
                    <tr key={row.feature}>
                      <td style={{ fontWeight: 500 }}>{row.feature}</td>
                      <td style={{ textAlign: 'center' }}>
                        {row.free ? <span className="ps-check-y">✓</span> : <span className="ps-check-n">—</span>}
                      </td>
                      <td className="pro-col" style={{ textAlign: 'center' }}>
                        {row.pro ? <span className="ps-check-y">✓</span> : <span className="ps-check-n">—</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </section>
      )}

      {/* Pricing card */}
      <section className="ps-section">
        <div className="ps-section-inner">
          <h2 className="ps-h2">Simple annual pricing</h2>
          <p className="ps-h2-sub">One license, one site, one year — renewable or not. Plugin keeps working after expiry.</p>
          <div className="ps-price-card">
            <div style={{ fontSize: 14, fontWeight: 700, color: 'rgba(255,255,255,.55)', textTransform: 'uppercase', letterSpacing: 1, marginBottom: 8 }}>{plugin.name} — Pro</div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: 8, justifyContent: 'center', marginBottom: 4 }}>
              <span style={{ fontSize: 64, fontWeight: 900, letterSpacing: '-3px', color: '#fff', lineHeight: 1 }}>{plugin.price}</span>
              <span style={{ fontSize: 16, color: 'rgba(255,255,255,.45)' }}>{plugin.priceNote}</span>
            </div>
            <div style={{ fontSize: 13, color: 'rgba(255,255,255,.35)', marginBottom: 32 }}>+VAT where applicable · 1 site license</div>
            {pricingFeatures.length > 0 && (
              <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 32px', display: 'flex', flexDirection: 'column', gap: 10, textAlign: 'left' }}>
                {pricingFeatures.map(f => (
                  <li key={f} style={{ display: 'flex', gap: 10, fontSize: 14, color: 'rgba(255,255,255,.82)' }}>
                    <span style={{ color: '#C4AFFF', fontWeight: 900, flexShrink: 0 }}>✓</span>
                    {f}
                  </li>
                ))}
              </ul>
            )}
            <a
              href={plugin.buyUrl}
              target="_blank"
              rel="noopener noreferrer"
              style={{ display: 'block', background: '#fff', color: PURPLE, fontWeight: 800, fontSize: 16, padding: '16px 0', borderRadius: 12, textDecoration: 'none', textAlign: 'center' }}
            >
              Buy Now — {plugin.price}/year →
            </a>
            <div style={{ fontSize: 12, color: 'rgba(255,255,255,.3)', marginTop: 16 }}>
              Secure checkout via Lemon Squeezy · EU VAT included automatically
            </div>
          </div>
        </div>
      </section>

      {/* Social proof */}
      {socialProof && (
        <section className="ps-section" style={{ background: '#F8F7FF', borderTop: '1px solid #E8E4F4', borderBottom: '1px solid #E8E4F4' }}>
          <div className="ps-section-inner">
            <div className="ps-social-proof" style={{ maxWidth: 720, margin: '0 auto' }}>
              <div style={{ fontSize: 40, marginBottom: 16 }}>{socialProof.emoji}</div>
              <p style={{ fontSize: 18, fontWeight: 700, color: '#0C0B1D', margin: '0 0 12px', lineHeight: 1.5 }}>
                &ldquo;{socialProof.quote}&rdquo;
              </p>
              <p style={{ fontSize: 14, color: '#9090B0', margin: 0 }}>{socialProof.attribution}</p>
            </div>
          </div>
        </section>
      )}

      {/* FAQ */}
      {faqs.length > 0 && (
        <section className="ps-section">
          <div className="ps-section-inner">
            <h2 className="ps-h2">Frequently Asked Questions</h2>
            <div style={{ maxWidth: 720, margin: '0 auto' }}>
              {faqs.map((faq, i) => (
                <div key={i} className="ps-faq-item">
                  <div className="ps-faq-q">{faq.q}</div>
                  <div className="ps-faq-a">{faq.a}</div>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* CTA bottom — always shown on live pages */}
      <section className="ps-section" style={{ paddingTop: faqs.length > 0 ? 0 : 80 }}>
        <div className="ps-section-inner">
          <div style={{ background: '#F3F0FF', border: '1.5px solid #D4C9FF', borderRadius: 20, padding: '40px 36px', textAlign: 'center', maxWidth: 720, marginLeft: 'auto', marginRight: 'auto' }}>
            <h3 style={{ fontSize: 22, fontWeight: 800, margin: '0 0 12px', color: '#0C0B1D' }}>Ready to get started?</h3>
            <p style={{ fontSize: 16, color: '#5A5A7A', margin: '0 0 28px' }}>Start with the free tier or go Pro — install takes 5 minutes.</p>
            <div style={{ display: 'flex', gap: 16, justifyContent: 'center', flexWrap: 'wrap' }}>
              <a href={plugin.buyUrl} target="_blank" rel="noopener noreferrer" style={{ background: PURPLE, color: '#fff', fontWeight: 700, fontSize: 15, padding: '13px 28px', borderRadius: 10, textDecoration: 'none', display: 'inline-block' }}>
                Buy Pro — {plugin.price}/year →
              </a>
              <Link to="/docs" style={{ background: '#fff', border: '1.5px solid #D4C9FF', color: PURPLE, fontWeight: 700, fontSize: 15, padding: '13px 28px', borderRadius: 10, textDecoration: 'none', display: 'inline-block' }}>
                Read the Docs
              </Link>
            </div>
          </div>
        </div>
      </section>

      <footer className="ab-footer">
        <Link to="/"><img src={logoSrc} style={{ height: 54, width: 'auto' }} alt="AI Boost" /></Link>
        <div style={{ fontSize: 13, color: '#9090B0' }}>© 2026 AI Boost · support@aiboostnow.com</div>
        <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
          <Link to="/plugins/aeo-ai-signals" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>AEO</Link>
          <Link to="/plugins/schema"        style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Schema</Link>
          <Link to="/plugins/opengraph"     style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>OpenGraph</Link>
          <Link to="/plugins/sitemap-xml"   style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Sitemap</Link>
          <Link to="/plugins/seo"           style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Hreflang</Link>
          <Link to="/plugins/code-manager"  style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Code Manager</Link>
          <Link to="/pricing"               style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Pricing</Link>
          <Link to="/docs"                  style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Docs</Link>
        </div>
      </footer>
    </div>
  )
}
