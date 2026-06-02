import { useParams, Link, Navigate } from 'react-router-dom'
import { Helmet } from 'react-helmet-async'
import { getBlogPost, blogPosts } from '../data/blogPosts'
import { SiteHeader } from './SiteHeader'
import Breadcrumb from './Breadcrumb'
import logoSrc from '../assets/ai-boost-logo.svg'

const PURPLE = '#7B4FFF'
const CHECKOUT_URL = '/pricing'
const SITE_URL = 'https://aiboostnow.com'

const css = `
.ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
.post-body h2 { font-size:24px; font-weight:800; margin:48px 0 16px; letter-spacing:-.5px; color:#0C0B1D; }
.post-body h3 { font-size:20px; font-weight:700; margin:36px 0 12px; color:#0C0B1D; }
.post-body p { font-size:16px; line-height:1.8; color:#3A3A5A; margin:0 0 20px; }
.post-body ul,.post-body ol { padding-left:24px; margin:0 0 20px; }
.post-body li { font-size:16px; line-height:1.75; color:#3A3A5A; margin-bottom:8px; }
.post-body pre { background:#F3F0FF; border:1.5px solid #E0D8FF; border-radius:10px; padding:20px 24px; overflow-x:auto; margin:24px 0; }
.post-body code { font-family:monospace; font-size:14px; color:#5A2FCC; background:#F3F0FF; padding:2px 6px; border-radius:4px; }
.post-body pre code { background:none; padding:0; color:#1A1A3A; font-size:13px; line-height:1.6; }
.post-body table { width:100%; border-collapse:collapse; margin:24px 0; }
.post-body th { background:#F3F0FF; font-size:13px; font-weight:700; padding:10px 14px; text-align:left; border:1px solid #E0D8FF; }
.post-body td { font-size:14px; padding:10px 14px; border:1px solid #E8E4F4; color:#3A3A5A; }
.post-body strong { color:#0C0B1D; font-weight:700; }
.post-body a { color:${PURPLE}; }
@media (max-width:900px) {
  .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; }
}
`

function renderMarkdown(md: string): string {
  return md
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/```(\w*)\n([\s\S]*?)```/g, (_m, _lang, code) =>
      `<pre><code>${code.trim()}</code></pre>`)
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/\*([^*]+)\*/g, '<em>$1</em>')
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>')
    .replace(/^### (.+)$/gm, '<h3>$1</h3>')
    .replace(/^## (.+)$/gm, '<h2>$1</h2>')
    .replace(/^\| (.+) \|$/gm, (_m, row) => {
      const cells: string[] = row.split(' | ')
      const tag = cells.some((c: string) => /^[-:]+$/.test(c.trim())) ? null : 'td'
      if (!tag) return ''
      return `<tr>${cells.map((c: string) => `<${tag}>${c.trim()}</${tag}>`).join('')}</tr>`
    })
    .replace(/(<tr>.*<\/tr>\n)+/g, m => `<table>${m}</table>`)
    .replace(/^- (.+)$/gm, '<li>$1</li>')
    .replace(/(<li>.*<\/li>\n)+/g, m => `<ul>${m}</ul>`)
    .split('\n\n')
    .map(block => {
      if (block.startsWith('<h') || block.startsWith('<ul') || block.startsWith('<ol') ||
          block.startsWith('<pre') || block.startsWith('<table')) return block
      const trimmed = block.trim()
      return trimmed ? `<p>${trimmed.replace(/\n/g, ' ')}</p>` : ''
    })
    .join('\n')
}

export function BlogPostPage() {
  const { slug } = useParams<{ slug: string }>()
  const post = slug ? getBlogPost(slug) : undefined

  if (!post) return <Navigate to="/blog" replace />

  const canonicalUrl = `${SITE_URL}/blog/${post.slug}`
  const pageTitle = `${post.title} | AI Boost Blog`

  const articleJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Article',
    headline: post.title,
    description: post.description,
    datePublished: post.date,
    dateModified: post.date,
    author: { '@type': 'Organization', name: 'AI Boost', url: SITE_URL },
    publisher: { '@type': 'Organization', name: 'AI Boost', url: SITE_URL },
  }

  const breadcrumbJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'Home', item: SITE_URL },
      { '@type': 'ListItem', position: 2, name: 'Blog', item: `${SITE_URL}/blog` },
      { '@type': 'ListItem', position: 3, name: post.title, item: canonicalUrl },
    ],
  }

  const related = blogPosts.filter(p => p.slug !== post.slug && p.tags.some(t => post.tags.includes(t))).slice(0, 3)

  return (
    <div className="ab-wrap">
      <Helmet>
        <title>{pageTitle}</title>
        <meta name="description" content={post.description} />
        <link rel="canonical" href={canonicalUrl} />
        <meta property="og:type" content="article" />
        <meta property="og:title" content={pageTitle} />
        <meta property="og:description" content={post.description} />
        <meta property="og:url" content={canonicalUrl} />
        <meta property="og:site_name" content="AI Boost" />
        <meta property="og:image" content={`${SITE_URL}/og/${post.slug}.png`} />
        <meta property="og:image:width" content="1200" />
        <meta property="og:image:height" content="630" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content={pageTitle} />
        <meta name="twitter:description" content={post.description} />
        <meta name="twitter:image" content={`${SITE_URL}/og/${post.slug}.png`} />
      </Helmet>

      <SiteHeader />
      <style>{css}</style>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(breadcrumbJsonLd) }} />
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(articleJsonLd) }} />
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'Organization',
        name: 'AI Boost',
        url: SITE_URL,
        contactPoint: { '@type': 'ContactPoint', email: 'support@aiboostnow.com', contactType: 'customer support' },
        sameAs: [],
      }) }} />

      <Breadcrumb items={[
        { label: 'Home', to: '/' },
        { label: 'Blog', to: '/blog' },
        { label: post.title },
      ]} maxWidth={740} />

      <main style={{ maxWidth: 740, margin: '0 auto', padding: '40px 32px 96px' }}>

        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 20 }}>
          {post.tags.map(tag => (
            <span key={tag} style={{ background: '#F3F0FF', color: PURPLE, fontSize: 11, fontWeight: 700, padding: '3px 10px', borderRadius: 100 }}>{tag}</span>
          ))}
        </div>

        <h1 style={{ fontSize: 36, fontWeight: 900, letterSpacing: '-1px', margin: '0 0 16px', lineHeight: 1.2, color: '#0C0B1D' }}>
          {post.title}
        </h1>

        <p style={{ fontSize: 17, color: '#5A5A7A', lineHeight: 1.65, margin: '0 0 12px' }}>{post.description}</p>

        <div style={{ display: 'flex', gap: 20, fontSize: 13, color: '#9090B0', marginBottom: 48, paddingBottom: 32, borderBottom: '1px solid #E8E4F4' }}>
          <span>{new Date(post.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}</span>
          <span>·</span>
          <span>{post.readTime}</span>
        </div>

        <div
          className="post-body"
          dangerouslySetInnerHTML={{ __html: renderMarkdown(post.content) }}
        />

        <div style={{ background: '#F3F0FF', border: '1.5px solid #D4C9FF', borderRadius: 20, padding: '36px', marginTop: 64 }}>
          <div style={{ fontSize: 13, fontWeight: 700, color: PURPLE, textTransform: 'uppercase', letterSpacing: 1, marginBottom: 12 }}>Ready to implement this?</div>
          <h3 style={{ fontSize: 22, fontWeight: 800, margin: '0 0 12px', color: '#0C0B1D' }}>AI Boost for Joomla handles it automatically</h3>
          <p style={{ fontSize: 15, color: '#5A5A7A', margin: '0 0 24px', lineHeight: 1.65 }}>
            Everything described in this article — and more — is built into AI Boost for Joomla. Install in 5 minutes, no coding required.
          </p>
          <Link to="/pricing"
            style={{ background: PURPLE, color: '#fff', fontWeight: 700, fontSize: 15, padding: '13px 28px', borderRadius: 10, textDecoration: 'none', display: 'inline-block' }}>
            Get AI Boost for Joomla →
          </Link>
        </div>

        {related.length > 0 && (
          <div style={{ marginTop: 64 }}>
            <h3 style={{ fontSize: 20, fontWeight: 800, margin: '0 0 24px', color: '#0C0B1D' }}>Related articles</h3>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
              {related.map(r => (
                <Link key={r.slug} to={`/blog/${r.slug}`}
                  style={{ display: 'block', padding: '20px 24px', border: '1.5px solid #E8E4F4', borderRadius: 12, textDecoration: 'none', color: 'inherit' }}>
                  <div style={{ fontSize: 15, fontWeight: 700, color: '#0C0B1D', marginBottom: 6 }}>{r.title}</div>
                  <div style={{ fontSize: 13, color: '#9090B0' }}>{r.readTime}</div>
                </Link>
              ))}
            </div>
          </div>
        )}
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
