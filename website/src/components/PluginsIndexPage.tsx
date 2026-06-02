import { Helmet } from 'react-helmet-async'
import { Link } from 'react-router-dom'
import logoSrc from '../assets/ai-boost-logo.svg'
import { SiteHeader } from './SiteHeader'
import Breadcrumb from './Breadcrumb'
import { pluginsData } from '../data/pluginsData'

const PURPLE = '#7B4FFF'
const SITE_URL = 'https://aiboostnow.com'

const css = `
.pi-hero { background:linear-gradient(135deg,#F8F7FF 0%,#F0ECF8 100%); border-bottom:1px solid #E8E4F4; padding:80px 32px 72px; text-align:center; }
.pi-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:28px; }
.pi-card { background:#fff; border:1.5px solid #E8E4F4; border-radius:20px; padding:32px 28px; display:flex; flex-direction:column; text-decoration:none; transition:border-color .15s; }
.pi-card:hover { border-color:#D4C9FF; }
.pi-badge-live { display:inline-block; background:#7B4FFF; color:#fff; font-size:10px; font-weight:800; padding:3px 9px; border-radius:100px; letter-spacing:.3px; margin-left:8px; vertical-align:middle; }
.pi-badge-soon { display:inline-block; background:#FFF3E0; color:#E65100; font-size:10px; font-weight:800; padding:3px 9px; border-radius:100px; letter-spacing:.3px; margin-left:8px; vertical-align:middle; border:1px solid #FFD180; }
.ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
@media(max-width:900px){ .pi-grid { grid-template-columns:1fr 1fr; } }
@media(max-width:560px){ .pi-grid { grid-template-columns:1fr; } .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; } }
`

export function PluginsIndexPage() {
  const canonicalUrl = `${SITE_URL}/plugins`
  const pageTitle    = 'Plugins — AI Boost for Joomla'
  const pageDesc     = '6 standalone Joomla plugins for Schema.org, OpenGraph, Hreflang, XML Sitemap, Analytics, and AI signals (llms.txt + IndexNow). Buy only what you need.'

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
        <meta property="og:image"       content={`${SITE_URL}/og/plugins.png`} />
        <meta property="og:image:width"  content="1200" />
        <meta property="og:image:height" content="630" />
        <meta name="twitter:card"        content="summary_large_image" />
        <meta name="twitter:title"       content={pageTitle} />
        <meta name="twitter:description" content={pageDesc} />
        <meta name="twitter:image"       content={`${SITE_URL}/og/plugins.png`} />
      </Helmet>

      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home',    item: SITE_URL },
          { '@type': 'ListItem', position: 2, name: 'Plugins', item: canonicalUrl },
        ],
      }) }} />

      <style>{css}</style>
      <SiteHeader />
      <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: 'Plugins' }]} />

      <section className="pi-hero">
        <div style={{ fontSize: 11, fontWeight: 700, color: PURPLE, textTransform: 'uppercase', letterSpacing: 1, marginBottom: 16 }}>AI Boost for Joomla</div>
        <h1 style={{ fontSize: 48, fontWeight: 900, letterSpacing: '-2px', color: '#0C0B1D', margin: '0 0 20px' }}>6 standalone plugins.<br />Buy only what you need.</h1>
        <p style={{ fontSize: 18, color: '#5A5A7A', maxWidth: 540, margin: '0 auto', lineHeight: 1.65 }}>
          Each plugin works independently. No dependencies. No bloat. Install one or grab the full bundle at a discount.
        </p>
      </section>

      <main style={{ maxWidth: 1100, margin: '0 auto', padding: '64px 32px 96px' }}>
        <div className="pi-grid">
          {pluginsData.map(plugin => (
            <Link key={plugin.slug} to={`/plugins/${plugin.slug}`} className="pi-card">
              <div style={{ fontSize: 36, marginBottom: 14 }}>{plugin.icon}</div>
              <div style={{ fontSize: 11, fontWeight: 700, color: '#9090B0', textTransform: 'uppercase', letterSpacing: .8, marginBottom: 4 }}>
                {plugin.tagline}
              </div>
              <div style={{ fontSize: 17, fontWeight: 800, color: '#0C0B1D', marginBottom: 8 }}>
                {plugin.name}
                {plugin.status === 'live'
                  ? <span className="pi-badge-live">Live</span>
                  : <span className="pi-badge-soon">Soon</span>}
              </div>
              <p style={{ fontSize: 13, color: '#5A5A7A', lineHeight: 1.65, margin: '0 0 20px', flexGrow: 1 }}>{plugin.desc}</p>
              <div style={{ display: 'flex', alignItems: 'baseline', gap: 4 }}>
                <span style={{ fontSize: 22, fontWeight: 900, color: '#0C0B1D' }}>{plugin.price}</span>
                <span style={{ fontSize: 12, color: '#B0B0C8' }}>{plugin.priceNote}</span>
              </div>
            </Link>
          ))}
        </div>

        <div style={{ background: PURPLE, borderRadius: 20, padding: '40px 44px', display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 48, flexWrap: 'wrap', gap: 24 }}>
          <div>
            <div style={{ fontSize: 13, fontWeight: 700, color: 'rgba(255,255,255,.5)', textTransform: 'uppercase', letterSpacing: .5, marginBottom: 8 }}>Best Value</div>
            <div style={{ fontSize: 28, fontWeight: 900, color: '#fff', letterSpacing: '-1px' }}>All 6 Plugins Bundle</div>
            <div style={{ fontSize: 15, color: 'rgba(255,255,255,.65)', marginTop: 6 }}>€45/year — saves €75 vs. buying separately</div>
          </div>
          <Link to="/pricing" style={{ background: '#fff', color: PURPLE, fontWeight: 800, fontSize: 15, padding: '14px 28px', borderRadius: 12, textDecoration: 'none', whiteSpace: 'nowrap' }}>
            See Bundle Pricing →
          </Link>
        </div>
      </main>

      <footer className="ab-footer">
        <Link to="/"><img src={logoSrc} style={{ height: 54, width: 'auto' }} alt="AI Boost" /></Link>
        <div style={{ fontSize: 13, color: '#9090B0' }}>© 2026 AI Boost · support@aiboostnow.com</div>
        <div style={{ display: 'flex', gap: 20, flexWrap: 'wrap' }}>
          {pluginsData.map(p => (
            <Link key={p.slug} to={`/plugins/${p.slug}`} style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>{p.shortName}</Link>
          ))}
          <Link to="/pricing" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Pricing</Link>
          <Link to="/docs"    style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Docs</Link>
        </div>
      </footer>
    </div>
  )
}
