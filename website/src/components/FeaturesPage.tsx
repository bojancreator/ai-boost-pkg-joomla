import { Link } from 'react-router-dom'
import { Helmet } from 'react-helmet-async'
import logoSrc from '../assets/ai-boost-logo.svg'
import { SiteHeader } from './SiteHeader'

const PURPLE = '#7B4FFF'
const SITE_URL = 'https://aiboostnow.com'

const css = `
.ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
.ab-footer-link { font-size:13px; color:#9090B0; text-decoration:none; }
.ft-hero { background:linear-gradient(135deg,#F8F7FF 0%,#F0ECF8 100%); border-bottom:1px solid #E8E4F4; padding:80px 64px 72px; text-align:center; }
.ft-hero-inner { max-width:820px; margin:0 auto; }
.ft-h1 { font-size:52px; font-weight:900; line-height:1.08; letter-spacing:-2px; margin:0 0 24px; color:#0C0B1D; }
.ft-hero-p { font-size:19px; color:#5A5A7A; line-height:1.7; margin:0 0 40px; max-width:620px; margin-left:auto; margin-right:auto; }
.ft-stats { display:flex; justify-content:center; gap:56px; flex-wrap:wrap; margin-top:48px; padding-top:40px; border-top:1px solid #E0D8FF; }
.ft-stat-val { font-size:40px; font-weight:900; color:${PURPLE}; line-height:1; }
.ft-stat-lbl { font-size:13px; color:#9090B0; margin-top:6px; }
.ft-pill { display:inline-flex; align-items:center; gap:8px; background:#F3F0FF; border:1px solid #D4C9FF; border-radius:100px; padding:5px 14px; margin-bottom:20px; font-size:12px; font-weight:700; color:${PURPLE}; text-transform:uppercase; letter-spacing:.5px; }
.ft-section { padding:88px 64px; }
.ft-section-alt { background:#F8F7FF; border-top:1px solid #E8E4F4; border-bottom:1px solid #E8E4F4; }
.ft-section-inner { max-width:1200px; margin:0 auto; }
.ft-section-header { text-align:center; margin-bottom:56px; }
.ft-h2 { font-size:40px; font-weight:900; letter-spacing:-1.2px; margin:0 0 16px; color:#0C0B1D; }
.ft-h2-sub { font-size:17px; color:#5A5A7A; max-width:560px; margin:0 auto; line-height:1.6; }
.ft-plugin-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:32px; }
.ft-plugin-card { background:#fff; border:1.5px solid #E8E4F4; border-radius:20px; padding:32px 28px; }
.ft-plugin-icon { font-size:36px; margin-bottom:16px; }
.ft-plugin-name { font-size:19px; font-weight:800; color:#0C0B1D; margin-bottom:6px; }
.ft-plugin-tag { font-size:11px; font-weight:700; color:${PURPLE}; background:#F3F0FF; border:1px solid #D4C9FF; border-radius:100px; padding:3px 10px; display:inline-block; margin-bottom:14px; text-transform:uppercase; letter-spacing:.4px; }
.ft-plugin-desc { font-size:14px; color:#5A5A7A; line-height:1.65; margin-bottom:18px; }
.ft-feat-list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px; }
.ft-feat-item { display:flex; gap:8px; align-items:flex-start; font-size:13px; color:#5A5A7A; }
.ft-feat-check { color:${PURPLE}; font-weight:900; flex-shrink:0; margin-top:1px; }
.ft-lang-grid { display:flex; flex-wrap:wrap; gap:12px; justify-content:center; }
.ft-lang-tag { background:#fff; border:1.5px solid #E8E4F4; border-radius:10px; padding:10px 18px; font-size:14px; font-weight:600; color:#0C0B1D; display:flex; align-items:center; gap:8px; }
.ft-compat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
.ft-compat-card { background:#fff; border:1.5px solid #E8E4F4; border-radius:12px; padding:20px 16px; text-align:center; }
.ft-compat-val { font-size:22px; font-weight:900; color:#0C0B1D; }
.ft-compat-lbl { font-size:12px; color:#9090B0; margin-top:4px; }
.ft-cta { padding:96px 64px; text-align:center; background:linear-gradient(135deg,#F3F0FF 0%,#E8E4F4 100%); border-top:1px solid #D4C9FF; }
@media (max-width:960px) {
  .ft-hero { padding:56px 24px 48px; }
  .ft-h1 { font-size:34px; letter-spacing:-1px; }
  .ft-hero-p { font-size:16px; }
  .ft-stats { gap:32px; }
  .ft-section { padding:56px 24px; }
  .ft-h2 { font-size:28px; }
  .ft-plugin-grid { grid-template-columns:1fr 1fr; }
  .ft-compat-grid { grid-template-columns:repeat(2,1fr); }
  .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; }
}
@media (max-width:600px) {
  .ft-plugin-grid { grid-template-columns:1fr; }
}
`

const standalonePlugins = [
  {
    icon: '🧠',
    name: 'AI Boost Schema',
    tag: 'Schema.org JSON-LD',
    desc: 'Structured data for every Joomla page. Covers Organization, LocalBusiness, Article, FAQ, BreadcrumbList, and Speakable — injected automatically, no manual JSON needed.',
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
    icon: '📣',
    name: 'AI Boost OpenGraph',
    tag: 'Social tags',
    desc: 'Generates correct og:title, og:description, og:image, and Twitter Card tags on every page. Per-article and per-category image overrides via Joomla custom fields.',
    features: [
      'og:title, og:description, og:image on every page',
      'Twitter Card (summary_large_image)',
      'og:type set correctly (article / website)',
      'og:article:published_time, modified_time, author',
      'Per-article image from Joomla media field',
      'Per-category custom field overrides',
      'Falang multilingual OG support',
    ],
  },
  {
    icon: '🌍',
    name: 'AI Boost Hreflang',
    tag: 'Multilingual + Sitemap',
    desc: 'Hreflang link tags on every page, x-default fallback, and a dynamic multilingual XML sitemap endpoint. Supports Joomla Associations and Falang.',
    features: [
      'hreflang link tags on every page',
      'x-default fallback language',
      'Multilingual XML sitemap at /sitemap-hreflang.xml',
      'Auto-detect Joomla language setup',
      'Manual language config for headless/custom setups',
      'Joomla Associations lookup',
      'Falang alias map support',
    ],
  },
  {
    icon: '📊',
    name: 'AI Boost Code Manager',
    tag: 'Analytics + Custom code',
    desc: 'Paste tracking IDs — GA4, GTM, Meta Pixel, and site verification tags are injected automatically. Custom head/body/footer code with per-page opt-out.',
    features: [
      'Google Analytics 4 (G-XXXXXXX)',
      'Google Tag Manager (GTM-XXXXXXX)',
      'Meta Pixel',
      'Google Search Console, Bing, Yandex, Pinterest verification',
      'Custom code in <head>, after <body>, before </body>',
      'async / defer control for custom scripts',
      'Per-page opt-out via Joomla custom field',
      'Staging mode — suppresses all injection on dev/staging',
    ],
  },
  {
    icon: '🤖',
    name: 'AI Boost AEO',
    tag: 'AI Engine Optimisation',
    desc: 'Makes your site visible to AI engines. Generates llms.txt, llms-full.txt (full Markdown article export), Speakable JSON-LD, ai:description meta, IndexNow ping, and robots.txt AI rules.',
    features: [
      'llms.txt endpoint (site summary for AI crawlers)',
      'llms-full.txt — full Markdown article export',
      'ai:description meta tag on article pages',
      'Speakable JSON-LD (highlights key content)',
      'IndexNow automatic ping on publish/update',
      '25+ AI crawler rules in robots.txt',
      'Accept: text/markdown alias for article URLs',
    ],
  },
]

const langPacks = [
  { flag: '🇬🇧', code: 'EN', name: 'English' },
  { flag: '🇩🇪', code: 'DE', name: 'Deutsch' },
  { flag: '🇫🇷', code: 'FR', name: 'Français' },
  { flag: '🇪🇸', code: 'ES', name: 'Español' },
  { flag: '🇮🇹', code: 'IT', name: 'Italiano' },
  { flag: '🇧🇷', code: 'PT', name: 'Português' },
  { flag: '🇷🇸', code: 'SR', name: 'Srpski' },
]

export function FeaturesPage() {
  const canonicalUrl  = `${SITE_URL}/features`
  const pageTitle     = 'Features — AI Boost for Joomla'
  const pageDesc      = '5 standalone AI Boost plugins for Joomla: Schema JSON-LD, OpenGraph, Hreflang, Code Manager, and AEO. Install only what you need. Joomla 4/5/6, PHP 8.1–8.5.'

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
        <meta property="og:image"       content={`${SITE_URL}/og/features.png`} />
        <meta property="og:image:width"  content="1200" />
        <meta property="og:image:height" content="630" />
        <meta name="twitter:card"        content="summary_large_image" />
        <meta name="twitter:title"       content={pageTitle} />
        <meta name="twitter:description" content={pageDesc} />
        <meta name="twitter:image"       content={`${SITE_URL}/og/features.png`} />
      </Helmet>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home',     item: SITE_URL },
          { '@type': 'ListItem', position: 2, name: 'Features', item: canonicalUrl },
        ],
      }) }} />

      <SiteHeader />
      <style>{css}</style>

      {/* HERO */}
      <section className="ft-hero">
        <div className="ft-hero-inner">
          <div className="ft-pill">5 standalone plugins — complete feature overview</div>
          <h1 className="ft-h1">
            Every SEO &amp; AEO signal.<br />
            <span style={{ color: PURPLE }}>5 modular plugins.</span>
          </h1>
          <p className="ft-hero-p">
            AI Boost for Joomla ships as five independent system plugins — install only what you need. Together they cover every signal that Google, ChatGPT, Perplexity, and Bing Copilot use to understand and recommend your site.
          </p>
          <div style={{ display: 'flex', gap: 14, justifyContent: 'center', flexWrap: 'wrap' }}>
            <Link to="/pricing" className="ab-btn-primary" style={{ fontSize: 15, padding: '13px 28px', borderRadius: 10 }}>
              See pricing →
            </Link>
            <Link to="/docs" style={{ background: 'transparent', border: '1.5px solid #D4C9FF', color: '#5A5A7A', fontSize: 15, fontWeight: 600, padding: '13px 24px', borderRadius: 10, textDecoration: 'none' }}>
              View documentation
            </Link>
          </div>
          <div className="ft-stats">
            {[['5', 'Standalone plugins'], ['7', 'Admin UI languages'], ['J4–J6', 'Joomla versions'], ['PHP 8.1–8.5', 'PHP support'], ['26', 'AI crawler rules']].map(([v, l]) => (
              <div key={l} style={{ textAlign: 'center' }}>
                <div className="ft-stat-val">{v}</div>
                <div className="ft-stat-lbl">{l}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* 5 PLUGIN CARDS */}
      <section className="ft-section">
        <div className="ft-section-inner">
          <div className="ft-section-header">
            <div className="ft-pill">Plugin catalogue</div>
            <h2 className="ft-h2">What each plugin does</h2>
            <p className="ft-h2-sub">Each plugin is a self-contained Joomla system plugin with its own params UI and language files. No shared dependency on com_aiboost.</p>
          </div>
          <div className="ft-plugin-grid">
            {standalonePlugins.map(p => (
              <div key={p.name} className="ft-plugin-card">
                <div className="ft-plugin-icon">{p.icon}</div>
                <div className="ft-plugin-name">{p.name}</div>
                <div className="ft-plugin-tag">{p.tag}</div>
                <div className="ft-plugin-desc">{p.desc}</div>
                <ul className="ft-feat-list">
                  {p.features.map(f => (
                    <li key={f} className="ft-feat-item">
                      <span className="ft-feat-check">✓</span>
                      <span>{f}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ADMIN UI LANGUAGES */}
      <section className="ft-section ft-section-alt">
        <div className="ft-section-inner">
          <div className="ft-section-header">
            <div className="ft-pill">🌍 Admin UI languages</div>
            <h2 className="ft-h2">7 admin language packs</h2>
            <p className="ft-h2-sub">Every plugin ships with 7 built-in admin UI language packs. Each plugin carries its own language files — no separate installation needed.</p>
          </div>
          <div className="ft-lang-grid">
            {langPacks.map(l => (
              <div key={l.code} className="ft-lang-tag">
                <span style={{ fontSize: 20 }}>{l.flag}</span>
                <span style={{ fontWeight: 700 }}>{l.code}</span>
                <span style={{ color: '#9090B0', fontWeight: 400, fontSize: 13 }}>{l.name}</span>
              </div>
            ))}
          </div>
          <div style={{ marginTop: 32, textAlign: 'center', fontSize: 13, color: '#9090B0' }}>
            All label, tooltip, and validation strings translated — switch your Joomla admin language and the plugin UI follows automatically.
          </div>
        </div>
      </section>

      {/* COMPATIBILITY */}
      <section className="ft-section">
        <div className="ft-section-inner">
          <div className="ft-section-header">
            <div className="ft-pill">✅ Compatibility</div>
            <h2 className="ft-h2">Tested on every supported version</h2>
            <p className="ft-h2-sub">All 5 plugins are automatically tested via CI across PHP 8.1–8.5 and Joomla 4, 5, and 6 on every push.</p>
          </div>
          <div className="ft-compat-grid">
            {[
              { val: 'Joomla 4', lbl: 'Supported' },
              { val: 'Joomla 5', lbl: 'Supported' },
              { val: 'Joomla 6', lbl: 'Supported' },
              { val: 'PHP 8.1–8.5', lbl: 'Supported' },
            ].map(c => (
              <div key={c.val} className="ft-compat-card">
                <div className="ft-compat-val">{c.val}</div>
                <div className="ft-compat-lbl" style={{ color: '#1A9C50', fontWeight: 700 }}>✓ {c.lbl}</div>
              </div>
            ))}
          </div>
          <div style={{ marginTop: 32, display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: 20 }}>
            {[
              ['🔌', 'No shared dependency', 'Each plugin works independently. Install one, three, or all five — no monolithic base plugin required.'],
              ['🗂️', 'Joomla native params', 'All settings live in standard Joomla plugin params — no separate admin component or custom DB tables needed.'],
              ['🔄', 'Non-destructive', 'Disable any plugin and your site returns to its previous state. No data is left behind.'],
            ].map(([icon, title, desc]) => (
              <div key={title as string} style={{ background: '#F8F7FF', border: '1.5px solid #E8E4F4', borderRadius: 14, padding: '24px 20px' }}>
                <div style={{ fontSize: 28, marginBottom: 12 }}>{icon}</div>
                <div style={{ fontSize: 16, fontWeight: 800, color: '#0C0B1D', marginBottom: 8 }}>{title as string}</div>
                <div style={{ fontSize: 14, color: '#5A5A7A', lineHeight: 1.6 }}>{desc as string}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="ft-cta">
        <div style={{ maxWidth: 640, margin: '0 auto' }}>
          <h2 style={{ fontSize: 40, fontWeight: 900, letterSpacing: '-1.2px', margin: '0 0 20px', color: '#0C0B1D' }}>
            Pick your plugins.<br />
            <span style={{ color: PURPLE }}>Pay only for what you use.</span>
          </h2>
          <p style={{ fontSize: 18, color: '#5A5A7A', margin: '0 0 36px', lineHeight: 1.65 }}>
            Buy individual plugins from €20/year, or grab all 6 in the bundle for €45. Annual license — plugins keep working after expiry.
          </p>
          <div style={{ display: 'flex', gap: 14, justifyContent: 'center', flexWrap: 'wrap', marginBottom: 28 }}>
            <Link to="/pricing" className="ab-btn-primary" style={{ fontSize: 16, padding: '14px 32px', borderRadius: 10 }}>
              View pricing →
            </Link>
            <Link to="/docs" style={{ background: '#fff', border: '1.5px solid #D4C9FF', color: '#5A5A7A', fontSize: 15, fontWeight: 600, padding: '14px 24px', borderRadius: 10, textDecoration: 'none' }}>
              Read docs
            </Link>
          </div>
          <div style={{ fontSize: 13, color: '#9090B0' }}>Joomla 4 · 5 · 6 · PHP 8.1–8.5 · 7 admin UI languages</div>
        </div>
      </section>

      <footer className="ab-footer">
        <Link to="/"><img src={logoSrc} style={{ height: 52, width: 'auto' }} alt="AI Boost" /></Link>
        <div style={{ fontSize: 13, color: '#9090B0' }}>© 2026 AI Boost · aiboostnow.com</div>
        <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap' }}>
          <Link to="/plugins"                  className="ab-footer-link">Plugins</Link>
          <Link to="/plugins/aeo-ai-signals"   className="ab-footer-link">AEO</Link>
          <Link to="/plugins/schema"           className="ab-footer-link">Schema</Link>
          <Link to="/plugins/opengraph"        className="ab-footer-link">OpenGraph</Link>
          <Link to="/plugins/sitemap-xml"      className="ab-footer-link">Sitemap</Link>
          <Link to="/plugins/seo"              className="ab-footer-link">Hreflang</Link>
          <Link to="/plugins/code-manager"     className="ab-footer-link">Code Manager</Link>
          <Link to="/features"                 className="ab-footer-link">Features</Link>
          <Link to="/pricing"                  className="ab-footer-link">Pricing</Link>
          <Link to="/docs"                     className="ab-footer-link">Docs</Link>
          <Link to="/blog"                     className="ab-footer-link">Blog</Link>
          <Link to="/faq"                      className="ab-footer-link">FAQ</Link>
          <a href="mailto:support@aiboostnow.com" className="ab-footer-link">Contact</a>
        </div>
      </footer>
    </div>
  )
}
