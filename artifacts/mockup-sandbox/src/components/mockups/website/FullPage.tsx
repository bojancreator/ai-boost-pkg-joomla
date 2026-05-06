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
  .ab-nav { display: flex; align-items: center; justify-content: space-between; padding: 0 64px; height: 88px; border-bottom: 1px solid #E8E4F4; position: sticky; top: 0; background: #fff; z-index: 100; }
  .ab-nav-links { display: flex; align-items: center; gap: 32px; }
  .ab-nav-link { color: #5A5A7A; font-size: 15px; font-weight: 500; text-decoration: none; }
  .ab-btn-primary { background: ${PURPLE}; color: #fff; font-size: 14px; font-weight: 600; padding: 11px 22px; border-radius: 8px; text-decoration: none; white-space: nowrap; }
  .ab-btn-hero { background: ${PURPLE}; color: #fff; font-size: 16px; font-weight: 700; padding: 15px 28px; border-radius: 10px; text-decoration: none; box-shadow: 0 4px 20px rgba(123,79,255,.3); white-space: nowrap; }
  .ab-btn-outline { background: transparent; border: 1.5px solid #D4C9FF; color: #5A5A7A; font-size: 16px; font-weight: 600; padding: 15px 24px; border-radius: 10px; text-decoration: none; white-space: nowrap; }
  .ab-logo { height: 68px; width: auto; display: block; }

  /* HERO */
  .ab-hero { max-width: 1200px; margin: 0 auto; padding: 90px 64px 72px; display: flex; align-items: center; gap: 64px; }
  .ab-hero-text { flex: 0 0 48%; }
  .ab-hero-video { flex: 1; }
  .ab-badge { display: inline-flex; align-items: center; gap: 8px; background: #F3F0FF; border: 1px solid #D4C9FF; border-radius: 100px; padding: 6px 16px; margin-bottom: 32px; }
  .ab-badge-dot { width: 7px; height: 7px; border-radius: 50%; background: ${PURPLE}; display: inline-block; }
  .ab-badge-text { font-size: 13px; color: ${PURPLE}; font-weight: 600; }
  .ab-h1 { font-size: 58px; font-weight: 900; line-height: 1.06; letter-spacing: -2px; margin: 0 0 24px; color: #0C0B1D; }
  .ab-h1-purple { color: ${PURPLE}; }
  .ab-hero-p { font-size: 18px; color: #5A5A7A; line-height: 1.7; margin: 0 0 40px; }
  .ab-hero-btns { display: flex; gap: 14px; margin-bottom: 44px; flex-wrap: wrap; }
  .ab-checks { display: flex; flex-wrap: wrap; gap: 12px 28px; }
  .ab-check { display: flex; align-items: center; gap: 8px; }
  .ab-browser { background: #F8F7FF; border-radius: 16px; border: 1.5px solid #E8E4F4; overflow: hidden; box-shadow: 0 24px 64px rgba(123,79,255,.15), 0 4px 16px rgba(0,0,0,.08); }
  .ab-browser-bar { background: #F0ECF8; padding: 10px 16px; display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #E8E4F4; }
  .ab-dot-r { width:10px; height:10px; border-radius:50%; background:#FF6B6B; display:inline-block; }
  .ab-dot-y { width:10px; height:10px; border-radius:50%; background:#FFD93D; display:inline-block; }
  .ab-dot-g { width:10px; height:10px; border-radius:50%; background:#6BCB77; display:inline-block; }
  .ab-url-bar { flex:1; background:#fff; border-radius:6px; padding:4px 12px; font-size:11px; color:#9090B0; margin-left:8px; border:1px solid #E8E4F4; }
  video.ab-video { width:100%; display:block; }

  /* STATS */
  .ab-stats-wrap { max-width:1200px; margin:0 auto; padding:0 64px 80px; }
  .ab-stats { background:#F3F0FF; border:1px solid #E0D8FF; border-radius:16px; padding:32px 48px; display:grid; grid-template-columns:repeat(4,1fr); gap:32px; text-align:center; }
  .ab-stat-val { font-size:38px; font-weight:900; color:${PURPLE}; }
  .ab-stat-lbl { font-size:13px; color:#9090B0; margin-top:4px; }

  /* FEATURES */
  .ab-features { background:#F8F7FF; padding:96px 64px; border-top:1px solid #E8E4F4; border-bottom:1px solid #E8E4F4; }
  .ab-features-inner { max-width:1200px; margin:0 auto; }
  .ab-section-head { text-align:center; margin-bottom:64px; }
  .ab-h2 { font-size:44px; font-weight:900; letter-spacing:-1.5px; margin:0 0 16px; color:#0C0B1D; }
  .ab-section-sub { font-size:17px; color:#5A5A7A; max-width:520px; margin:0 auto; }
  .ab-feat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }
  .ab-feat-card { background:#fff; border:1.5px solid #E8E4F4; border-radius:16px; padding:32px 28px; }
  .ab-feat-icon { font-size:32px; margin-bottom:16px; }
  .ab-feat-title { font-size:17px; font-weight:700; margin-bottom:10px; color:#0C0B1D; }
  .ab-feat-desc { font-size:14px; color:#5A5A7A; line-height:1.6; }

  /* PRICING */
  .ab-pricing { padding:96px 64px; background:#fff; }
  .ab-pricing-inner { max-width:1200px; margin:0 auto; }
  .ab-plan-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; align-items:start; }
  .ab-plan { border-radius:20px; padding:36px 28px; position:relative; transition:transform .2s; }
  .ab-plan-normal { background:#fff; border:1.5px solid #E8E4F4; box-shadow:0 2px 12px rgba(0,0,0,.05); }
  .ab-plan-highlight { background:${PURPLE}; box-shadow:0 12px 48px rgba(123,79,255,.35); transform:scale(1.04); }
  .ab-plan-badge { position:absolute; top:-14px; left:50%; transform:translateX(-50%); background:#0C0B1D; color:#fff; font-size:11px; font-weight:700; padding:4px 16px; border-radius:100px; white-space:nowrap; }
  .ab-plan-name { font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; }
  .ab-plan-price { font-size:48px; font-weight:900; letter-spacing:-2px; }
  .ab-plan-once { font-size:13px; }
  .ab-plan-vat { font-size:12px; margin-bottom:16px; }
  .ab-plan-meta { font-size:14px; margin-bottom:28px; }
  .ab-plan-btn { display:block; text-align:center; padding:13px 0; font-weight:700; font-size:14px; border-radius:10px; text-decoration:none; }
  .ab-plan-btn-white { background:#fff; color:${PURPLE}; }
  .ab-plan-btn-purple { background:${PURPLE}; color:#fff; }
  .ab-plan-divider { border-top:1px solid #F0ECF8; margin-top:24px; padding-top:24px; }
  .ab-plan-divider-white { border-top:1px solid rgba(255,255,255,.2); margin-top:24px; padding-top:24px; }
  .ab-feat-list { list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px; }
  .ab-feat-item { display:flex; gap:10px; align-items:flex-start; }
  .ab-check-icon { font-weight:900; font-size:14px; margin-top:1px; flex-shrink:0; }
  .ab-feat-item-text { font-size:13px; }
  .ab-guarantee { text-align:center; margin-top:40px; font-size:14px; color:#9090B0; }

  /* FAQ */
  .ab-faq { background:#F8F7FF; padding:96px 64px; border-top:1px solid #E8E4F4; }
  .ab-faq-inner { max-width:720px; margin:0 auto; }
  .ab-faq-item { padding:28px 0; border-bottom:1px solid #E8E4F4; }
  .ab-faq-q { font-size:16px; font-weight:700; margin-bottom:12px; color:#0C0B1D; }
  .ab-faq-a { font-size:14px; color:#5A5A7A; line-height:1.7; }

  /* CTA */
  .ab-cta { padding:96px 64px; text-align:center; background:#fff; }
  .ab-cta-h2 { font-size:52px; font-weight:900; letter-spacing:-2px; margin:0 0 20px; color:#0C0B1D; }
  .ab-cta-btns { display:flex; gap:16px; justify-content:center; flex-wrap:wrap; margin-top:48px; }
  .ab-btn-cta { background:${PURPLE}; color:#fff; font-size:17px; font-weight:700; padding:18px 36px; border-radius:12px; text-decoration:none; box-shadow:0 4px 24px rgba(123,79,255,.35); }
  .ab-btn-ghost { color:#9090B0; font-size:17px; font-weight:500; padding:18px 0; text-decoration:underline; }

  /* FOOTER */
  .ab-footer { border-top:1px solid #E8E4F4; padding:40px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:16px; }
  .ab-footer-copy { font-size:13px; color:#9090B0; }
  .ab-footer-links { display:flex; gap:24px; }
  .ab-footer-link { font-size:13px; color:#9090B0; text-decoration:none; }

  /* ─── RESPONSIVE ─── */
  @media (max-width: 900px) {
    .ab-nav { padding: 0 24px; height: 72px; }
    .ab-nav-links a.ab-nav-link { display: none; }
    .ab-logo { height: 52px; }

    .ab-hero { flex-direction: column; padding: 48px 24px 40px; gap: 32px; }
    .ab-hero-text { flex: none; width: 100%; }
    .ab-hero-video { width: 100%; }
    .ab-h1 { font-size: 36px; letter-spacing: -1px; }
    .ab-hero-p { font-size: 16px; }
    .ab-btn-hero, .ab-btn-outline { font-size: 15px; padding: 13px 20px; }

    .ab-stats-wrap { padding: 0 24px 48px; }
    .ab-stats { grid-template-columns: repeat(2,1fr); padding: 24px; gap: 20px; }
    .ab-stat-val { font-size: 28px; }

    .ab-features { padding: 64px 24px; }
    .ab-feat-grid { grid-template-columns: 1fr; }
    .ab-h2 { font-size: 30px; letter-spacing: -1px; }

    .ab-pricing { padding: 64px 24px; }
    .ab-plan-grid { grid-template-columns: 1fr; max-width: 400px; margin: 0 auto; }
    .ab-plan-highlight { transform: scale(1); }

    .ab-faq { padding: 64px 24px; }
    .ab-faq-q { font-size: 15px; }

    .ab-cta { padding: 64px 24px; }
    .ab-cta-h2 { font-size: 32px; letter-spacing: -1px; }
    .ab-btn-cta { font-size: 15px; padding: 14px 24px; }

    .ab-footer { padding: 32px 24px; flex-direction: column; align-items: flex-start; }
  }

  @media (max-width: 480px) {
    .ab-h1 { font-size: 30px; }
    .ab-hero-btns { flex-direction: column; }
    .ab-btn-hero, .ab-btn-outline { width: 100%; text-align: center; }
    .ab-stats { grid-template-columns: repeat(2,1fr); }
    .ab-cta-btns { flex-direction: column; align-items: center; }
  }
`;

export function FullPage() {
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
      </nav>

      {/* HERO */}
      <section className="ab-hero">
        <div className="ab-hero-text">
          <div className="ab-badge">
            <span className="ab-badge-dot" />
            <span className="ab-badge-text">Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
          </div>
          <h1 className="ab-h1">
            Make your Joomla site{" "}
            <span className="ab-h1-purple">visible to AI search</span>
          </h1>
          <p className="ab-hero-p">
            AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
          </p>
          <div className="ab-hero-btns">
            <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" className="ab-btn-hero">Buy Developer — €119</a>
            <a href="#features" className="ab-btn-outline">View all features ↓</a>
          </div>
          <div className="ab-checks">
            {[["Schema.org","JSON-LD"],["llms.txt","AI crawlers"],["IndexNow","Instant submit"],["11 languages","Multilingual"]].map(([n,d]) => (
              <div key={n} className="ab-check">
                <span style={{ color: PURPLE, fontWeight: 900 }}>✓</span>
                <span style={{ fontSize: 14, fontWeight: 700 }}>{n}</span>
                <span style={{ fontSize: 13, color: "#9090B0" }}>{d}</span>
              </div>
            ))}
          </div>
        </div>
        <div className="ab-hero-video">
          <div className="ab-browser">
            <div className="ab-browser-bar">
              <span className="ab-dot-r" /><span className="ab-dot-y" /><span className="ab-dot-g" />
              <div className="ab-url-bar">aiboostnow.com</div>
            </div>
            <video src={videoSrc} autoPlay muted loop playsInline className="ab-video" />
          </div>
        </div>
      </section>

      {/* STATS */}
      <div className="ab-stats-wrap">
        <div className="ab-stats">
          {[["20+","Schema.org types"],["25+","AI crawler rules"],["11","Language packs"],["5 min","Setup time"]].map(([v,l]) => (
            <div key={l}>
              <div className="ab-stat-val">{v}</div>
              <div className="ab-stat-lbl">{l}</div>
            </div>
          ))}
        </div>
      </div>

      {/* FEATURES */}
      <section id="features" className="ab-features">
        <div className="ab-features-inner">
          <div className="ab-section-head">
            <h2 className="ab-h2">Everything AI search engines need</h2>
            <p className="ab-section-sub">One plugin covers all the signals that get your Joomla site recommended by AI engines in 2026.</p>
          </div>
          <div className="ab-feat-grid">
            {feats.map(f => (
              <div key={f.title} className="ab-feat-card">
                <div className="ab-feat-icon">{f.icon}</div>
                <div className="ab-feat-title">{f.title}</div>
                <div className="ab-feat-desc">{f.desc}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* PRICING */}
      <section id="pricing" className="ab-pricing">
        <div className="ab-pricing-inner">
          <div className="ab-section-head">
            <h2 className="ab-h2">Simple pricing. Every feature, every license.</h2>
            <p className="ab-section-sub">Pay once, use forever. 30-day money-back guarantee.</p>
          </div>
          <div className="ab-plan-grid">
            {plans.map(plan => (
              <div key={plan.name} className={`ab-plan ${plan.highlight ? "ab-plan-highlight" : "ab-plan-normal"}`}>
                {plan.badge && <div className="ab-plan-badge">{plan.badge}</div>}
                <div className="ab-plan-name" style={{ color: plan.highlight ? "rgba(255,255,255,.65)" : "#9090B0" }}>{plan.name}</div>
                <div style={{ display:"flex", alignItems:"baseline", gap:6, marginBottom:4 }}>
                  <span className="ab-plan-price" style={{ color: plan.highlight ? "#fff" : "#0C0B1D" }}>{plan.price}</span>
                  <span className="ab-plan-once" style={{ color: plan.highlight ? "rgba(255,255,255,.5)" : "#B0B0C8" }}>one-time</span>
                </div>
                <div className="ab-plan-vat" style={{ color: plan.highlight ? "rgba(255,255,255,.4)" : "#B0B0C8" }}>+VAT where applicable</div>
                <div className="ab-plan-meta" style={{ color: plan.highlight ? "rgba(255,255,255,.75)" : "#5A5A7A" }}>{plan.sites} · {plan.support}</div>
                <a href={plan.url} target="_blank" rel="noopener noreferrer" className={`ab-plan-btn ${plan.highlight ? "ab-plan-btn-white" : "ab-plan-btn-purple"}`}>
                  Buy {plan.name} — {plan.price}
                </a>
                <div className={plan.highlight ? "ab-plan-divider-white" : "ab-plan-divider"}>
                  <ul className="ab-feat-list">
                    {featureList.map(f => (
                      <li key={f} className="ab-feat-item">
                        <span className="ab-check-icon" style={{ color: plan.highlight ? "rgba(255,255,255,.85)" : PURPLE }}>✓</span>
                        <span className="ab-feat-item-text" style={{ color: plan.highlight ? "rgba(255,255,255,.8)" : "#5A5A7A" }}>{f}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            ))}
          </div>
          <p className="ab-guarantee">🛡️ 30-day money-back guarantee &nbsp;·&nbsp; Payments by Gumroad &nbsp;·&nbsp; EU VAT handled automatically</p>
        </div>
      </section>

      {/* FAQ */}
      <section id="docs" className="ab-faq">
        <div className="ab-faq-inner">
          <h2 className="ab-h2" style={{ textAlign:"center", marginBottom:56 }}>Frequently asked questions</h2>
          {faqs.map((faq, i) => (
            <div key={i} className="ab-faq-item">
              <div className="ab-faq-q">{faq.q}</div>
              <div className="ab-faq-a">{faq.a}</div>
            </div>
          ))}
        </div>
      </section>

      {/* CTA */}
      <section className="ab-cta">
        <h2 className="ab-cta-h2">
          Ready to make your Joomla site<br />
          <span style={{ color: PURPLE }}>visible to AI?</span>
        </h2>
        <p style={{ fontSize:18, color:"#5A5A7A" }}>Install in 5 minutes. No coding. No JSON editing.</p>
        <div className="ab-cta-btns">
          <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" className="ab-btn-cta">Buy Developer — €119</a>
          <a href={GUMROAD.starter}   target="_blank" rel="noopener noreferrer" className="ab-btn-ghost">Or start with Starter for €59</a>
        </div>
      </section>

      {/* FOOTER */}
      <footer className="ab-footer">
        <img src={logoSrc} style={{ height:48, width:"auto" }} alt="AI Boost" />
        <span className="ab-footer-copy">© 2026 AI Boost · support@aiboostnow.com</span>
        <div className="ab-footer-links">
          <a href="#docs"    className="ab-footer-link">Docs</a>
          <a href="#privacy" className="ab-footer-link">Privacy</a>
          <a href="#terms"   className="ab-footer-link">Terms</a>
        </div>
      </footer>
    </div>
  );
}
