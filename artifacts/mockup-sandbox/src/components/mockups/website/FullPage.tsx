import { useState } from "react";
import { logoDataUrl as logoSrc } from "@/assets/logo-data";

const videoSrc = import.meta.env.BASE_URL + "hero-video.mp4";

const PURPLE = "#7B4FFF";

const GUMROAD = {
  starter:   "https://aiboostnow.gumroad.com/l/joomlaboost-starter",
  developer: "https://aiboostnow.gumroad.com/l/joomlaboost",
  agency:    "https://aiboostnow.gumroad.com/l/joomlaboost-agency",
};

const plans = [
  { name: "Starter",   price: "€59",  sites: "1 site",         badge: null,           highlight: false, support: "Email support",          url: GUMROAD.starter },
  { name: "Developer", price: "€119", sites: "5 sites",        badge: "Most Popular", highlight: true,  support: "Priority email support", url: GUMROAD.developer },
  { name: "Agency",    price: "€199", sites: "Unlimited sites", badge: null,           highlight: false, support: "Priority email support", url: GUMROAD.agency },
];

const feats = [
  { icon: "🧠", title: "Schema.org JSON-LD",    desc: "All 20+ types: LocalBusiness, Hotel, Event, FAQPage, Article, Person, Product, BreadcrumbList and more." },
  { icon: "🗺️", title: "XML Sitemap + Hreflang", desc: "Dynamic sitemap auto-generated. Multilingual hreflang tags for all installed Joomla languages." },
  { icon: "🤖", title: "robots.txt + llms.txt",  desc: "Block or allow 25+ AI crawlers. Generate llms.txt so ChatGPT and Perplexity can index your content." },
  { icon: "⚡", title: "IndexNow",               desc: "Instant URL submission to Bing, Yandex, and Seznam the moment you publish new content." },
  { icon: "📊", title: "Analytics Suite",        desc: "GA4, Google Tag Manager, Google Search Console verification, Meta Pixel — all from one panel." },
  { icon: "🌍", title: "11 Language Packs",      desc: "Full admin UI in EN, DE, FR, ES, IT, RU, PT, ZH, AR, JA, SR. Multilingual custom fields too." },
];

const faqs = [
  { q: 'What does "one-time payment" mean?',        a: 'You pay once and own the plugin forever. Updates are included for 1 year. After that, the plugin keeps working — renewal is optional.' },
  { q: "Is it compatible with Joomla 4, 5, and 6?", a: "Yes. AI Boost for Joomla supports Joomla 4.0 through 6.x with PHP 8.1 through 8.5." },
  { q: "Is there a free trial?",                    a: "No free trial, but every purchase has a 30-day money-back guarantee. If it doesn't work, we refund you in full." },
  { q: "Can I upgrade my license later?",           a: "Yes. Contact support@aiboostnow.com and we'll arrange an upgrade at the price difference." },
];

const featureList = [
  "All plugin features included",
  "Schema.org JSON-LD (all types)",
  "XML Sitemap + hreflang",
  "OpenGraph + Twitter Cards",
  "robots.txt with AI crawler rules",
  "llms.txt generator",
  "IndexNow integration",
  "GA4, GTM, Meta Pixel",
  "5 Vertical Presets",
  "11 language packs",
  "1 year of updates & bug fixes",
];

const css = `
  * { box-sizing: border-box; }
  .ab-wrap { font-family: 'Inter', system-ui, sans-serif; background: #fff; color: #0C0B1D; }

  /* NAV */
  .ab-nav { display:flex; align-items:center; justify-content:space-between; padding:0 64px; height:88px; border-bottom:1px solid #E8E4F4; position:sticky; top:0; background:#fff; z-index:200; }
  .ab-nav-links { display:flex; align-items:center; gap:32px; }
  .ab-nav-link { color:#5A5A7A; font-size:15px; font-weight:500; text-decoration:none; }
  .ab-logo { height:68px; width:auto; display:block; }
  .ab-btn-primary { background:${PURPLE}; color:#fff; font-size:14px; font-weight:600; padding:11px 22px; border-radius:8px; text-decoration:none; white-space:nowrap; }
  .ab-hamburger { display:none; background:none; border:none; cursor:pointer; padding:8px; color:#0C0B1D; line-height:1; }

  /* MOBILE MENU OVERLAY */
  .ab-mobile-menu { display:none; position:fixed; top:72px; left:0; right:0; background:#fff; border-bottom:1px solid #E8E4F4; box-shadow:0 8px 32px rgba(0,0,0,.1); z-index:199; padding:16px 24px 24px; flex-direction:column; gap:4px; }
  .ab-mobile-menu.open { display:flex; }
  .ab-mobile-link { color:#0C0B1D; font-size:17px; font-weight:600; text-decoration:none; padding:14px 0; border-bottom:1px solid #F0ECF8; }
  .ab-mobile-cta { display:block; background:${PURPLE}; color:#fff; font-size:16px; font-weight:700; padding:15px; border-radius:10px; text-decoration:none; text-align:center; margin-top:12px; }

  /* HERO */
  .ab-hero { max-width:1200px; margin:0 auto; padding:90px 64px 72px; display:flex; align-items:center; gap:64px; }
  .ab-hero-text { flex:0 0 48%; }
  .ab-hero-video { flex:1; }
  .ab-badge { display:inline-flex; align-items:center; gap:8px; background:#F3F0FF; border:1px solid #D4C9FF; border-radius:100px; padding:6px 16px; margin-bottom:32px; }
  .ab-h1 { font-size:58px; font-weight:900; line-height:1.06; letter-spacing:-2px; margin:0 0 24px; color:#0C0B1D; }
  .ab-hero-p { font-size:18px; color:#5A5A7A; line-height:1.7; margin:0 0 40px; }
  .ab-hero-btns { display:flex; gap:14px; margin-bottom:44px; flex-wrap:wrap; }
  .ab-btn-hero { background:${PURPLE}; color:#fff; font-size:16px; font-weight:700; padding:15px 28px; border-radius:10px; text-decoration:none; box-shadow:0 4px 20px rgba(123,79,255,.3); }
  .ab-btn-outline { background:transparent; border:1.5px solid #D4C9FF; color:#5A5A7A; font-size:16px; font-weight:600; padding:15px 24px; border-radius:10px; text-decoration:none; }
  .ab-browser { background:#F8F7FF; border-radius:16px; border:1.5px solid #E8E4F4; overflow:hidden; box-shadow:0 24px 64px rgba(123,79,255,.15),0 4px 16px rgba(0,0,0,.08); }
  .ab-browser-bar { background:#F0ECF8; padding:10px 16px; display:flex; align-items:center; gap:8px; border-bottom:1px solid #E8E4F4; }
  .ab-url-bar { flex:1; background:#fff; border-radius:6px; padding:4px 12px; font-size:11px; color:#9090B0; margin-left:8px; border:1px solid #E8E4F4; }

  /* STATS */
  .ab-stats-wrap { max-width:1200px; margin:0 auto; padding:0 64px 80px; }
  .ab-stats { background:#F3F0FF; border:1px solid #E0D8FF; border-radius:16px; padding:32px 48px; display:grid; grid-template-columns:repeat(4,1fr); gap:32px; text-align:center; }

  /* FEATURES */
  .ab-features { background:#F8F7FF; padding:96px 64px; border-top:1px solid #E8E4F4; border-bottom:1px solid #E8E4F4; }
  .ab-h2 { font-size:44px; font-weight:900; letter-spacing:-1.5px; margin:0 0 16px; color:#0C0B1D; }
  .ab-feat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
  .ab-feat-card { background:#fff; border:1.5px solid #E8E4F4; border-radius:16px; padding:32px 28px; }

  /* PRICING */
  .ab-pricing { padding:96px 64px; background:#fff; }
  .ab-plan-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; align-items:start; }
  .ab-plan { border-radius:20px; padding:36px 28px; position:relative; }
  .ab-plan-normal { background:#fff; border:1.5px solid #E8E4F4; box-shadow:0 2px 12px rgba(0,0,0,.05); }
  .ab-plan-highlight { background:${PURPLE}; box-shadow:0 12px 48px rgba(123,79,255,.35); transform:scale(1.04); }
  .ab-plan-btn { display:block; text-align:center; padding:13px 0; font-weight:700; font-size:14px; border-radius:10px; text-decoration:none; }
  .ab-feat-list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; }
  .ab-feat-item { display:flex; gap:10px; align-items:flex-start; }

  /* FAQ */
  .ab-faq { background:#F8F7FF; padding:96px 64px; border-top:1px solid #E8E4F4; }

  /* CTA */
  .ab-cta { padding:96px 64px; text-align:center; background:#fff; }

  /* FOOTER */
  .ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
  .ab-footer-link { font-size:13px; color:#9090B0; text-decoration:none; }

  /* ─── RESPONSIVE ─── */
  @media (max-width: 900px) {
    .ab-nav { padding:0 20px; height:72px; }
    .ab-nav-links { gap:0; }
    .ab-nav-links a.ab-nav-link { display:none; }
    .ab-hamburger { display:flex; align-items:center; justify-content:center; }
    .ab-logo { height:52px; }

    .ab-hero { flex-direction:column; padding:48px 20px 40px; gap:32px; }
    .ab-hero-text { flex:none; width:100%; }
    .ab-h1 { font-size:36px; letter-spacing:-1px; }
    .ab-hero-p { font-size:16px; }
    .ab-btn-hero, .ab-btn-outline { font-size:15px; padding:13px 20px; }

    .ab-stats-wrap { padding:0 20px 48px; }
    .ab-stats { grid-template-columns:repeat(2,1fr); padding:24px; gap:20px; }

    .ab-features { padding:64px 20px; }
    .ab-feat-grid { grid-template-columns:1fr; }
    .ab-h2 { font-size:30px; letter-spacing:-1px; }

    .ab-pricing { padding:64px 20px; }
    .ab-plan-grid { grid-template-columns:1fr; max-width:400px; margin:0 auto; }
    .ab-plan-highlight { transform:scale(1); }

    .ab-faq { padding:64px 20px; }
    .ab-cta { padding:64px 20px; }

    .ab-footer { padding:32px 20px; flex-direction:column; align-items:flex-start; }
  }

  @media (max-width: 480px) {
    .ab-h1 { font-size:30px; }
    .ab-hero-btns { flex-direction:column; }
    .ab-btn-hero, .ab-btn-outline { width:100%; text-align:center; }
    .ab-stats { grid-template-columns:repeat(2,1fr); }
  }
`;

export function FullPage() {
  const [menuOpen, setMenuOpen] = useState(false);
  const close = () => setMenuOpen(false);

  return (
    <div className="ab-wrap">
      <style>{css}</style>

      {/* NAV */}
      <nav className="ab-nav">
        <img src={logoSrc} className="ab-logo" alt="AI Boost" />
        <div className="ab-nav-links">
          <a href="#features" className="ab-nav-link">Features</a>
          <a href="#docs"     className="ab-nav-link">Docs</a>
          <a href="#pricing"  className="ab-nav-link">Pricing</a>
          <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" className="ab-btn-primary">Get AI Boost →</a>
        </div>
        <button className="ab-hamburger" onClick={() => setMenuOpen(o => !o)} aria-label="Menu">
          {menuOpen
            ? <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            : <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
          }
        </button>
      </nav>

      {/* MOBILE DROPDOWN MENU */}
      <div className={`ab-mobile-menu${menuOpen ? " open" : ""}`}>
        <a href="#features" className="ab-mobile-link" onClick={close}>Features</a>
        <a href="#docs"     className="ab-mobile-link" onClick={close}>Docs</a>
        <a href="#pricing"  className="ab-mobile-link" onClick={close}>Pricing</a>
        <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" className="ab-mobile-cta" onClick={close}>Get AI Boost →</a>
      </div>

      {/* HERO */}
      <section className="ab-hero">
        <div className="ab-hero-text">
          <div className="ab-badge">
            <span style={{ width:7, height:7, borderRadius:"50%", background:PURPLE, display:"inline-block" }} />
            <span style={{ fontSize:13, color:PURPLE, fontWeight:600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
          </div>
          <h1 className="ab-h1">
            Make your Joomla site{" "}
            <span style={{ color:PURPLE }}>visible to AI search</span>
          </h1>
          <p className="ab-hero-p">
            AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
          </p>
          <div className="ab-hero-btns">
            <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" className="ab-btn-hero">Buy Developer — €119</a>
            <a href="#features" className="ab-btn-outline">View all features ↓</a>
          </div>
          <div style={{ display:"flex", flexWrap:"wrap", gap:"12px 28px" }}>
            {[["Schema.org","JSON-LD"],["llms.txt","AI crawlers"],["IndexNow","Instant submit"],["11 languages","Multilingual"]].map(([n,d]) => (
              <div key={n} style={{ display:"flex", alignItems:"center", gap:8 }}>
                <span style={{ color:PURPLE, fontWeight:900 }}>✓</span>
                <span style={{ fontSize:14, fontWeight:700 }}>{n}</span>
                <span style={{ fontSize:13, color:"#9090B0" }}>{d}</span>
              </div>
            ))}
          </div>
        </div>
        <div className="ab-hero-video">
          <div className="ab-browser">
            <div className="ab-browser-bar">
              <span style={{ width:10, height:10, borderRadius:"50%", background:"#FF6B6B", display:"inline-block" }} />
              <span style={{ width:10, height:10, borderRadius:"50%", background:"#FFD93D", display:"inline-block" }} />
              <span style={{ width:10, height:10, borderRadius:"50%", background:"#6BCB77", display:"inline-block" }} />
              <div className="ab-url-bar">aiboostnow.com</div>
            </div>
            <video src={videoSrc} autoPlay muted loop playsInline style={{ width:"100%", display:"block" }} />
          </div>
        </div>
      </section>

      {/* STATS */}
      <div className="ab-stats-wrap">
        <div className="ab-stats">
          {[["20+","Schema.org types"],["25+","AI crawler rules"],["11","Language packs"],["5 min","Setup time"]].map(([v,l]) => (
            <div key={l}>
              <div style={{ fontSize:38, fontWeight:900, color:PURPLE }}>{v}</div>
              <div style={{ fontSize:13, color:"#9090B0", marginTop:4 }}>{l}</div>
            </div>
          ))}
        </div>
      </div>

      {/* FEATURES */}
      <section id="features" className="ab-features">
        <div style={{ maxWidth:1200, margin:"0 auto" }}>
          <div style={{ textAlign:"center", marginBottom:64 }}>
            <h2 className="ab-h2">Everything AI search engines need</h2>
            <p style={{ fontSize:17, color:"#5A5A7A", maxWidth:520, margin:"0 auto" }}>One plugin covers all the signals that get your Joomla site recommended by AI engines in 2026.</p>
          </div>
          <div className="ab-feat-grid">
            {feats.map(f => (
              <div key={f.title} className="ab-feat-card">
                <div style={{ fontSize:32, marginBottom:16 }}>{f.icon}</div>
                <div style={{ fontSize:17, fontWeight:700, marginBottom:10, color:"#0C0B1D" }}>{f.title}</div>
                <div style={{ fontSize:14, color:"#5A5A7A", lineHeight:1.6 }}>{f.desc}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* PRICING */}
      <section id="pricing" className="ab-pricing">
        <div style={{ maxWidth:1200, margin:"0 auto" }}>
          <div style={{ textAlign:"center", marginBottom:64 }}>
            <h2 className="ab-h2">Simple pricing. Every feature, every license.</h2>
            <p style={{ fontSize:17, color:"#5A5A7A" }}>Pay once, use forever. 30-day money-back guarantee.</p>
          </div>
          <div className="ab-plan-grid">
            {plans.map(plan => (
              <div key={plan.name} className={`ab-plan ${plan.highlight ? "ab-plan-highlight" : "ab-plan-normal"}`}>
                {plan.badge && (
                  <div style={{ position:"absolute", top:-14, left:"50%", transform:"translateX(-50%)", background:"#0C0B1D", color:"#fff", fontSize:11, fontWeight:700, padding:"4px 16px", borderRadius:100, whiteSpace:"nowrap" }}>{plan.badge}</div>
                )}
                <div style={{ fontSize:13, fontWeight:700, color:plan.highlight ? "rgba(255,255,255,.65)" : "#9090B0", textTransform:"uppercase", letterSpacing:1, marginBottom:8 }}>{plan.name}</div>
                <div style={{ display:"flex", alignItems:"baseline", gap:6, marginBottom:4 }}>
                  <span style={{ fontSize:48, fontWeight:900, letterSpacing:"-2px", color:plan.highlight ? "#fff" : "#0C0B1D" }}>{plan.price}</span>
                  <span style={{ fontSize:13, color:plan.highlight ? "rgba(255,255,255,.5)" : "#B0B0C8" }}>one-time</span>
                </div>
                <div style={{ fontSize:12, color:plan.highlight ? "rgba(255,255,255,.4)" : "#B0B0C8", marginBottom:16 }}>+VAT where applicable</div>
                <div style={{ fontSize:14, color:plan.highlight ? "rgba(255,255,255,.75)" : "#5A5A7A", marginBottom:28 }}>{plan.sites} · {plan.support}</div>
                <a href={plan.url} target="_blank" rel="noopener noreferrer" className="ab-plan-btn" style={{ background:plan.highlight ? "#fff" : PURPLE, color:plan.highlight ? PURPLE : "#fff" }}>
                  Buy {plan.name} — {plan.price}
                </a>
                <div style={{ borderTop:`1px solid ${plan.highlight ? "rgba(255,255,255,.2)" : "#F0ECF8"}`, marginTop:24, paddingTop:24 }}>
                  <ul className="ab-feat-list">
                    {featureList.map(f => (
                      <li key={f} className="ab-feat-item">
                        <span style={{ color:plan.highlight ? "rgba(255,255,255,.85)" : PURPLE, fontWeight:900, fontSize:14, marginTop:1, flexShrink:0 }}>✓</span>
                        <span style={{ fontSize:13, color:plan.highlight ? "rgba(255,255,255,.8)" : "#5A5A7A" }}>{f}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            ))}
          </div>
          <p style={{ textAlign:"center", marginTop:40, fontSize:14, color:"#9090B0" }}>
            🛡️ 30-day money-back guarantee &nbsp;·&nbsp; Payments by Gumroad &nbsp;·&nbsp; EU VAT handled automatically
          </p>
        </div>
      </section>

      {/* FAQ */}
      <section id="docs" className="ab-faq">
        <div style={{ maxWidth:720, margin:"0 auto" }}>
          <h2 className="ab-h2" style={{ textAlign:"center", marginBottom:56 }}>Frequently asked questions</h2>
          {faqs.map((faq, i) => (
            <div key={i} style={{ padding:"28px 0", borderBottom:"1px solid #E8E4F4" }}>
              <div style={{ fontSize:16, fontWeight:700, marginBottom:12, color:"#0C0B1D" }}>{faq.q}</div>
              <div style={{ fontSize:14, color:"#5A5A7A", lineHeight:1.7 }}>{faq.a}</div>
            </div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="ab-cta">
        <h2 style={{ fontSize:52, fontWeight:900, letterSpacing:"-2px", margin:"0 0 20px", color:"#0C0B1D" }}>
          Ready to make your Joomla site<br />
          <span style={{ color:PURPLE }}>visible to AI?</span>
        </h2>
        <p style={{ fontSize:18, color:"#5A5A7A", marginBottom:48 }}>Install in 5 minutes. No coding. No JSON editing.</p>
        <div style={{ display:"flex", gap:16, justifyContent:"center", flexWrap:"wrap" }}>
          <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" style={{ background:PURPLE, color:"#fff", fontSize:17, fontWeight:700, padding:"18px 36px", borderRadius:12, textDecoration:"none", boxShadow:"0 4px 24px rgba(123,79,255,.35)" }}>Buy Developer — €119</a>
          <a href={GUMROAD.starter}   target="_blank" rel="noopener noreferrer" style={{ color:"#9090B0", fontSize:17, fontWeight:500, padding:"18px 0", textDecoration:"underline" }}>Or start with Starter for €59</a>
        </div>
      </section>

      {/* FOOTER */}
      <footer className="ab-footer">
        <img src={logoSrc} style={{ height:48, width:"auto" }} alt="AI Boost" />
        <span style={{ fontSize:13, color:"#9090B0" }}>© 2026 AI Boost · support@aiboostnow.com</span>
        <div style={{ display:"flex", gap:24 }}>
          <a href="#docs"    className="ab-footer-link">Docs</a>
          <a href="#privacy" className="ab-footer-link">Privacy</a>
          <a href="#terms"   className="ab-footer-link">Terms</a>
        </div>
      </footer>
    </div>
  );
}
