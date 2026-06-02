import { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import logoSrc from '../assets/ai-boost-logo.svg'
import { pluginsData } from '../data/pluginsData'

const PURPLE = '#7B4FFF'

export const siteHeaderCss = `
* { box-sizing: border-box; }
body { margin: 0; }
.ab-wrap { font-family: 'Inter', system-ui, sans-serif; background: #fff; color: #0C0B1D; overflow-x: hidden; }
.ab-nav-bar { border-bottom:1px solid #E8E4F4; position:sticky; top:0; background:#fff; z-index:200; }
.ab-nav { max-width:1200px; margin:0 auto; padding:0 24px; height:80px; display:grid; grid-template-columns:1fr auto 1fr; align-items:center; }
.ab-nav-links { display:flex; align-items:center; justify-content:center; gap:28px; }
.ab-nav-cta { display:flex; align-items:center; justify-content:flex-end; gap:12px; }
.ab-nav-link { color:#5A5A7A; font-size:15px; font-weight:500; text-decoration:none; }
.ab-logo { height:75px; width:auto; display:block; }
.ab-btn-primary { background:#7B4FFF; color:#fff; font-size:14px; font-weight:600; padding:11px 22px; border-radius:8px; text-decoration:none; white-space:nowrap; }
.ab-hamburger { display:none; background:none; border:none; cursor:pointer; padding:8px; color:#0C0B1D; line-height:1; }
.ab-mobile-menu { display:none; position:fixed; top:80px; left:0; right:0; background:#fff; border-bottom:1px solid #E8E4F4; box-shadow:0 8px 32px rgba(0,0,0,.1); z-index:199; padding:16px 24px 24px; flex-direction:column; gap:4px; }
.ab-mobile-menu.open { display:flex; }
.ab-mobile-link { color:#0C0B1D; font-size:17px; font-weight:600; text-decoration:none; padding:14px 0; border-bottom:1px solid #F0ECF8; }
.ab-mobile-link-sub { color:#5A5A7A; font-size:15px; font-weight:500; text-decoration:none; padding:10px 0 10px 16px; border-bottom:1px solid #F9F7FF; display:flex; align-items:center; gap:8px; }
.ab-mobile-cta { display:block; background:#7B4FFF; color:#fff; font-size:16px; font-weight:700; padding:15px; border-radius:10px; text-decoration:none; text-align:center; margin-top:12px; }
.ab-nav-dropdown { position:relative; }
.ab-nav-dropdown-btn { color:#5A5A7A; font-size:15px; font-weight:500; background:none; border:none; cursor:pointer; display:flex; align-items:center; gap:4px; padding:0; font-family:inherit; }
.ab-nav-dropdown-btn:hover { color:${PURPLE}; }
.ab-nav-dropdown-btn svg { transition:transform .2s; }
.ab-nav-dropdown:hover .ab-nav-dropdown-btn svg { transform:rotate(180deg); }
.ab-dropdown-panel { display:none; position:absolute; top:calc(100% + 12px); left:50%; transform:translateX(-50%); background:#fff; border:1.5px solid #E8E4F4; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,.1); padding:12px; min-width:260px; z-index:300; }
.ab-nav-dropdown:hover .ab-dropdown-panel { display:block; }
.ab-dropdown-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; text-decoration:none; }
.ab-dropdown-item:hover { background:#F8F7FF; }
.ab-dropdown-icon { font-size:18px; flex-shrink:0; width:28px; text-align:center; }
.ab-dropdown-name { font-size:13px; font-weight:700; color:#0C0B1D; }
.ab-dropdown-tag { font-size:11px; color:#9090B0; margin-top:1px; }
.ab-badge-live { display:inline-block; background:${PURPLE}; color:#fff; font-size:9px; font-weight:800; padding:2px 6px; border-radius:100px; margin-left:5px; vertical-align:middle; }
.ab-badge-soon { display:inline-block; background:#FFF3E0; color:#E65100; font-size:9px; font-weight:800; padding:2px 6px; border-radius:100px; margin-left:5px; vertical-align:middle; border:1px solid #FFD180; }
.ab-dropdown-divider { height:1px; background:#F0ECF8; margin:6px 0; }
@media (max-width:900px) {
  .ab-nav { padding:0 16px; height:64px; grid-template-columns:1fr auto; }
  .ab-nav-links { display:none; }
  .ab-hamburger { display:flex; align-items:center; justify-content:center; }
  .ab-nav-cta .ab-btn-primary { display:none; }
  .ab-logo { height:54px; }
  .ab-mobile-menu { top:64px; }
}
`

const navLinks = [
  { to: '/features', label: 'Features' },
  { to: '/pricing',  label: 'Pricing' },
  { to: '/docs',     label: 'Docs' },
  { to: '/blog',     label: 'Blog' },
  { to: '/faq',      label: 'FAQ' },
]

export function SiteHeader() {
  const [menuOpen, setMenuOpen]     = useState(false)
  const [pluginsOpen, setPluginsOpen] = useState(false)
  const close = () => { setMenuOpen(false); setPluginsOpen(false) }
  const { pathname } = useLocation()

  const isActive = (to: string) => pathname === to || pathname.startsWith(to + '/')
  const pluginsActive = pathname.startsWith('/plugins')

  return (
    <>
      <style>{siteHeaderCss}</style>

      <nav className="ab-nav-bar">
        <div className="ab-nav">
          <Link to="/"><img src={logoSrc} className="ab-logo" alt="AI Boost" /></Link>

          <div className="ab-nav-links">
            {/* Plugins dropdown */}
            <div className="ab-nav-dropdown">
              <button
                className="ab-nav-dropdown-btn"
                style={pluginsActive ? { color: PURPLE, fontWeight: 700 } : undefined}
                aria-haspopup="true"
              >
                Plugins
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                  <path d="M2 4l4 4 4-4" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/>
                </svg>
              </button>
              <div className="ab-dropdown-panel">
                {pluginsData.map(p => (
                  <Link key={p.slug} to={`/plugins/${p.slug}`} className="ab-dropdown-item">
                    <span className="ab-dropdown-icon">{p.icon}</span>
                    <div>
                      <div className="ab-dropdown-name">
                        {p.name}
                        {p.status === 'live'
                          ? <span className="ab-badge-live">Live</span>
                          : <span className="ab-badge-soon">Soon</span>}
                      </div>
                      <div className="ab-dropdown-tag">{p.tagline}</div>
                    </div>
                  </Link>
                ))}
                <div className="ab-dropdown-divider" />
                <Link to="/plugins" className="ab-dropdown-item" style={{ justifyContent: 'center' }}>
                  <span style={{ fontSize: 13, fontWeight: 700, color: PURPLE }}>View all plugins →</span>
                </Link>
              </div>
            </div>

            {navLinks.map(({ to, label }) => (
              <Link
                key={to}
                to={to}
                className="ab-nav-link"
                style={isActive(to) ? { color: PURPLE, fontWeight: 700 } : undefined}
              >
                {label}
              </Link>
            ))}
          </div>

          <div className="ab-nav-cta">
            <Link to="/pricing" className="ab-btn-primary">Get AI Boost →</Link>
            <button className="ab-hamburger" onClick={() => setMenuOpen(o => !o)} aria-label="Menu">
              {menuOpen
                ? <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                : <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
              }
            </button>
          </div>
        </div>
      </nav>

      <div className={`ab-mobile-menu${menuOpen ? ' open' : ''}`}>
        {/* Plugins section in mobile */}
        <div
          className="ab-mobile-link"
          style={{ cursor: 'pointer', display: 'flex', justifyContent: 'space-between', alignItems: 'center', ...(pluginsActive ? { color: PURPLE } : {}) }}
          onClick={() => setPluginsOpen(o => !o)}
        >
          <span>Plugins</span>
          <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style={{ transform: pluginsOpen ? 'rotate(180deg)' : 'none', transition: 'transform .2s' }}>
            <path d="M2 5l5 5 5-5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/>
          </svg>
        </div>
        {pluginsOpen && pluginsData.map(p => (
          <Link
            key={p.slug}
            to={`/plugins/${p.slug}`}
            className="ab-mobile-link-sub"
            onClick={close}
            style={isActive(`/plugins/${p.slug}`) ? { color: PURPLE } : undefined}
          >
            <span>{p.icon}</span>
            <span>{p.shortName}</span>
            {p.status === 'live'
              ? <span className="ab-badge-live">Live</span>
              : <span className="ab-badge-soon">Soon</span>}
          </Link>
        ))}

        {navLinks.map(({ to, label }) => (
          <Link
            key={to}
            to={to}
            className="ab-mobile-link"
            onClick={close}
            style={isActive(to) ? { color: PURPLE } : undefined}
          >
            {label}
          </Link>
        ))}
        <Link to="/pricing" className="ab-mobile-cta" onClick={close}>Get AI Boost →</Link>
      </div>
    </>
  )
}
