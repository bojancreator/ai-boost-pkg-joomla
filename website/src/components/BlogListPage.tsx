import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Helmet } from 'react-helmet-async'
import { blogPosts, getAllTags } from '../data/blogPosts'
import Breadcrumb from './Breadcrumb'
import { SiteHeader } from './SiteHeader'
import logoSrc from '../assets/ai-boost-logo.svg'

const PURPLE = '#7B4FFF'
const CHECKOUT_URL = '/pricing'
const SITE_URL = 'https://aiboostnow.com'

const css = `
.ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
@media (max-width:900px) {
  .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; }
}
`

export function BlogListPage() {
  const [activeTag, setActiveTag] = useState<string | null>(null)
  const allTags = getAllTags()
  const filtered = activeTag ? blogPosts.filter(p => p.tags.includes(activeTag)) : blogPosts

  const canonicalUrl = `${SITE_URL}/blog`
  const pageTitle = 'Blog — AI Boost'
  const pageDescription = 'Guides on Schema.org, AI search, structured data, and everything that makes Joomla sites visible in 2026. Written by the team behind AI Boost for Joomla.'

  return (
    <div className="ab-wrap">
      <Helmet>
        <title>{pageTitle}</title>
        <meta name="description" content={pageDescription} />
        <link rel="canonical" href={canonicalUrl} />
        <meta property="og:type" content="website" />
        <meta property="og:title" content={pageTitle} />
        <meta property="og:description" content={pageDescription} />
        <meta property="og:url" content={canonicalUrl} />
        <meta property="og:site_name" content="AI Boost" />
        <meta property="og:image" content={`${SITE_URL}/og/blog.png`} />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content={pageTitle} />
        <meta name="twitter:description" content={pageDescription} />
        <meta name="twitter:image" content={`${SITE_URL}/og/blog.png`} />
      </Helmet>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home', item: SITE_URL },
          { '@type': 'ListItem', position: 2, name: 'Blog', item: `${SITE_URL}/blog` },
        ],
      }) }} />
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({ '@context': 'https://schema.org', '@type': 'Organization', name: 'AI Boost', url: SITE_URL, contactPoint: { '@type': 'ContactPoint', email: 'support@aiboostnow.com', contactType: 'customer support' }, sameAs: [] }) }} />

      <SiteHeader />
      <style>{css}</style>

      <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: 'Blog' }]} maxWidth={1100} />

      <main style={{ maxWidth: 1100, margin: '0 auto', padding: '72px 32px 96px' }}>
        <div style={{ marginBottom: 56 }}>
          <h1 style={{ fontSize: 44, fontWeight: 900, letterSpacing: '-1.5px', margin: '0 0 16px', color: '#0C0B1D' }}>
            Blog
          </h1>
          <p style={{ fontSize: 18, color: '#5A5A7A', margin: 0 }}>
            Guides on Schema.org, AI search, structured data, and everything that makes Joomla sites visible in 2026.
          </p>
        </div>

        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginBottom: 48 }}>
          <button
            onClick={() => setActiveTag(null)}
            style={{
              background: activeTag === null ? PURPLE : '#F3F0FF',
              color: activeTag === null ? '#fff' : PURPLE,
              border: 'none', borderRadius: 100, padding: '6px 16px',
              fontSize: 13, fontWeight: 600, cursor: 'pointer',
            }}
          >
            All
          </button>
          {allTags.map(tag => (
            <button
              key={tag}
              onClick={() => setActiveTag(tag === activeTag ? null : tag)}
              style={{
                background: activeTag === tag ? PURPLE : '#F3F0FF',
                color: activeTag === tag ? '#fff' : PURPLE,
                border: 'none', borderRadius: 100, padding: '6px 16px',
                fontSize: 13, fontWeight: 600, cursor: 'pointer',
              }}
            >
              {tag}
            </button>
          ))}
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: 28 }}>
          {filtered.map(post => (
            <Link
              key={post.slug}
              to={`/blog/${post.slug}`}
              style={{ textDecoration: 'none', color: 'inherit' }}
            >
              <article style={{
                border: '1.5px solid #E8E4F4', borderRadius: 16, padding: '28px 24px',
                height: '100%', display: 'flex', flexDirection: 'column', gap: 12,
                transition: 'border-color .2s, box-shadow .2s',
              }}
                onMouseEnter={e => { (e.currentTarget as HTMLElement).style.borderColor = PURPLE; (e.currentTarget as HTMLElement).style.boxShadow = '0 4px 20px rgba(123,79,255,.12)' }}
                onMouseLeave={e => { (e.currentTarget as HTMLElement).style.borderColor = '#E8E4F4'; (e.currentTarget as HTMLElement).style.boxShadow = 'none' }}
              >
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  {post.tags.map(tag => (
                    <span key={tag} style={{ background: '#F3F0FF', color: PURPLE, fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 100 }}>{tag}</span>
                  ))}
                </div>
                <h2 style={{ fontSize: 17, fontWeight: 700, margin: 0, lineHeight: 1.45, color: '#0C0B1D' }}>{post.title}</h2>
                <p style={{ fontSize: 14, color: '#5A5A7A', lineHeight: 1.65, margin: 0, flexGrow: 1 }}>{post.description}</p>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 4 }}>
                  <span style={{ fontSize: 12, color: '#9090B0' }}>{new Date(post.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                  <span style={{ fontSize: 12, color: '#9090B0' }}>{post.readTime}</span>
                </div>
              </article>
            </Link>
          ))}
        </div>
      </main>

      <footer className="ab-footer">
        <Link to="/"><img src={logoSrc} style={{ height: 54, width: 'auto' }} alt="AI Boost" /></Link>
        <div style={{ fontSize: 13, color: '#9090B0' }}>© 2026 AI Boost · support@aiboostnow.com</div>
        <div style={{ display: 'flex', gap: 24 }}>
          <Link to="/features" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Features</Link>
          <Link to="/pricing" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Pricing</Link>
          <Link to="/docs" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Docs</Link>
          <Link to="/blog" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>Blog</Link>
          <Link to="/faq" style={{ fontSize: 13, color: '#9090B0', textDecoration: 'none' }}>FAQ</Link>
        </div>
      </footer>
    </div>
  )
}
