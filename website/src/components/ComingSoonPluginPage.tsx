import { useState } from 'react'
import { Helmet } from 'react-helmet-async'
import { Link } from 'react-router-dom'
import logoSrc from '../assets/ai-boost-logo.svg'
import { SiteHeader } from './SiteHeader'
import Breadcrumb from './Breadcrumb'
import { pluginsData } from '../data/pluginsData'
import type { PluginDef } from '../data/pluginsData'

const PURPLE = '#7B4FFF'
const SITE_URL = 'https://aiboostnow.com'

const css = `
.cs-hero { background:linear-gradient(135deg,#F8F7FF 0%,#F0ECF8 100%); border-bottom:1px solid #E8E4F4; padding:80px 32px 72px; text-align:center; }
.cs-badge { display:inline-flex; align-items:center; gap:8px; background:#FFF3E0; border:1px solid #FFD180; border-radius:100px; padding:5px 16px; margin-bottom:24px; font-size:12px; font-weight:700; color:#E65100; text-transform:uppercase; letter-spacing:.5px; }
.cs-h1 { font-size:48px; font-weight:900; line-height:1.08; letter-spacing:-2px; color:#0C0B1D; margin:0 0 20px; max-width:720px; margin-left:auto; margin-right:auto; }
.cs-sub { font-size:18px; color:#5A5A7A; max-width:520px; margin:0 auto 48px; line-height:1.65; }
.cs-notify-form { display:flex; gap:10px; justify-content:center; max-width:440px; margin:0 auto; flex-wrap:wrap; }
.cs-notify-input { flex:1; min-width:200px; padding:13px 16px; border:1.5px solid #D4C9FF; border-radius:10px; font-size:14px; outline:none; }
.cs-notify-input:focus { border-color:#7B4FFF; }
.cs-notify-btn { background:#7B4FFF; color:#fff; font-weight:700; font-size:14px; padding:13px 22px; border-radius:10px; border:none; cursor:pointer; white-space:nowrap; }
.cs-teaser { padding:80px 32px; }
.cs-teaser-inner { max-width:960px; margin:0 auto; }
.cs-teaser-h2 { font-size:32px; font-weight:900; letter-spacing:-1px; color:#0C0B1D; margin:0 0 48px; text-align:center; }
.cs-teaser-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
.cs-teaser-card { background:#fff; border:1.5px solid #E8E4F4; border-radius:16px; padding:28px 24px; }
.cs-teaser-num { width:32px; height:32px; background:#F3F0FF; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:900; color:#7B4FFF; margin-bottom:14px; }
.cs-teaser-text { font-size:14px; color:#5A5A7A; line-height:1.65; }
.cs-other { padding:0 32px 80px; }
.cs-other-inner { max-width:960px; margin:0 auto; }
.cs-other-h2 { font-size:24px; font-weight:800; color:#0C0B1D; margin:0 0 28px; }
.cs-other-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
.cs-other-card { background:#F8F7FF; border:1.5px solid #E8E4F4; border-radius:14px; padding:20px; display:flex; gap:12px; align-items:flex-start; text-decoration:none; }
.cs-other-card:hover { border-color:#D4C9FF; background:#F3F0FF; }
.cs-other-icon { font-size:20px; flex-shrink:0; margin-top:2px; }
.cs-other-name { font-size:13px; font-weight:700; color:#0C0B1D; margin-bottom:3px; }
.cs-other-tag { font-size:11px; color:#9090B0; }
.cs-other-live { display:inline-block; background:#7B4FFF; color:#fff; font-size:10px; font-weight:700; padding:2px 7px; border-radius:100px; margin-left:6px; vertical-align:middle; }
.ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
@media(max-width:768px){
  .cs-h1 { font-size:32px; }
  .cs-teaser-grid { grid-template-columns:1fr; }
  .cs-other-grid { grid-template-columns:1fr 1fr; }
  .cs-hero { padding:56px 20px 48px; }
}
@media(max-width:520px){
  .cs-other-grid { grid-template-columns:1fr; }
  .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; }
}
`

interface Props {
  plugin: PluginDef
}

export function ComingSoonPluginPage({ plugin }: Props) {
  const [email, setEmail]     = useState('')
  const [submitted, setSubmitted] = useState(false)

  const canonicalUrl = `${SITE_URL}/plugins/${plugin.slug}`
  const pageTitle    = `${plugin.name} — Coming Soon`
  const pageDesc     = `${plugin.name} is coming Summer 2026. ${plugin.desc}`

  const schema = {
    '@context': 'https://schema.org',
    '@type': 'SoftwareApplication',
    name: plugin.name,
    applicationCategory: 'WebApplication',
    operatingSystem: 'Joomla 4, Joomla 5, Joomla 6',
    description: plugin.desc,
    url: canonicalUrl,
    offers: {
      '@type': 'Offer',
      price: plugin.price.replace('€', ''),
      priceCurrency: 'EUR',
      availability: 'https://schema.org/PreOrder',
    },
    publisher: {
      '@type': 'Organization',
      name: 'AI Boost',
      url: SITE_URL,
    },
  }

  const handleNotify = () => {
    if (!email.trim()) return
    const mailto = `mailto:support@aiboostnow.com?subject=${encodeURIComponent(`Notify me: ${plugin.name}`)}&body=${encodeURIComponent(`Please notify me when ${plugin.name} launches.\n\nEmail: ${email}`)}`
    window.location.href = mailto
    setSubmitted(true)
  }

  const otherPlugins = pluginsData.filter(p => p.slug !== plugin.slug)

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
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home',    item: SITE_URL },
          { '@type': 'ListItem', position: 2, name: 'Plugins', item: `${SITE_URL}/plugins` },
          { '@type': 'ListItem', position: 3, name: plugin.name, item: canonicalUrl },
        ],
      }) }} />

      <style>{css}</style>
      <SiteHeader />
      <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: 'Plugins', to: '/plugins' }, { label: plugin.shortName }]} />

      {/* Hero */}
      <section className="cs-hero">
        <div style={{ fontSize: 48, marginBottom: 16 }}>{plugin.icon}</div>
        <div className="cs-badge">⏳ Coming Summer 2026</div>
        <h1 className="cs-h1">{plugin.name}</h1>
        <p className="cs-sub">{plugin.desc}</p>

        {!submitted ? (
          <>
            <p style={{ fontSize: 14, color: '#9090B0', marginBottom: 16 }}>Get notified the moment it launches:</p>
            <div className="cs-notify-form">
              <input
                type="email"
                className="cs-notify-input"
                placeholder="your@email.com"
                value={email}
                onChange={e => setEmail(e.target.value)}
                onKeyDown={e => e.key === 'Enter' && handleNotify()}
              />
              <button className="cs-notify-btn" onClick={handleNotify}>Notify me →</button>
            </div>
            <p style={{ fontSize: 12, color: '#B0B0C8', marginTop: 10 }}>No spam. One email when it launches.</p>
          </>
        ) : (
          <div style={{ background: '#F3F0FF', border: '1.5px solid #D4C9FF', borderRadius: 12, padding: '16px 28px', display: 'inline-block', marginTop: 8 }}>
            <span style={{ fontSize: 15, fontWeight: 700, color: PURPLE }}>✓ You&apos;re on the list — we&apos;ll email you when it launches!</span>
          </div>
        )}

        <div style={{ marginTop: 32, display: 'flex', gap: 12, justifyContent: 'center', flexWrap: 'wrap' }}>
          <span style={{ fontSize: 13, color: '#9090B0', background: '#F0ECF8', padding: '5px 14px', borderRadius: 100 }}>Joomla 4 / 5 / 6</span>
          <span style={{ fontSize: 13, color: '#9090B0', background: '#F0ECF8', padding: '5px 14px', borderRadius: 100 }}>PHP 8.1–8.5</span>
          <span style={{ fontSize: 13, color: '#9090B0', background: '#F0ECF8', padding: '5px 14px', borderRadius: 100 }}>{plugin.price}/year</span>
        </div>
      </section>

      {/* Feature teaser */}
      {plugin.comingSoonTeaser && plugin.comingSoonTeaser.length > 0 && (
        <section className="cs-teaser">
          <div className="cs-teaser-inner">
            <h2 className="cs-teaser-h2">What {plugin.name} will do</h2>
            <div className="cs-teaser-grid">
              {plugin.comingSoonTeaser.map((item, i) => (
                <div key={i} className="cs-teaser-card">
                  <div className="cs-teaser-num">{i + 1}</div>
                  <div className="cs-teaser-text">{item}</div>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* Other plugins */}
      <section className="cs-other">
        <div className="cs-other-inner">
          <h2 className="cs-other-h2">While you wait — explore other AI Boost plugins</h2>
          <div className="cs-other-grid">
            {otherPlugins.map(p => (
              <Link key={p.slug} to={`/plugins/${p.slug}`} className="cs-other-card">
                <span className="cs-other-icon">{p.icon}</span>
                <div>
                  <div className="cs-other-name">
                    {p.name}
                    {p.status === 'live' && <span className="cs-other-live">Live</span>}
                  </div>
                  <div className="cs-other-tag">{p.tagline} · {p.price}/year</div>
                </div>
              </Link>
            ))}
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
