import { logoDataUrl as logoSrc } from "@/assets/logo-data";

const videoSrc = import.meta.env.BASE_URL + "hero-video.mp4";

const PURPLE = "#7B4FFF";

const GUMROAD = {
  starter:   "https://aiboostnow.gumroad.com/l/joomlaboost-starter",
  developer: "https://aiboostnow.gumroad.com/l/joomlaboost",
  agency:    "https://aiboostnow.gumroad.com/l/joomlaboost-agency",
};

const NavLogo = () => (
  <img src={logoSrc} style={{ height: 68, width: "auto", display: "block" }} alt="AI Boost" />
);

const Check = ({ onPurple = false }) => (
  <span style={{ color: onPurple ? "rgba(255,255,255,0.85)" : PURPLE, fontWeight: 900, fontSize: 14, marginTop: 1, flexShrink: 0 }}>✓</span>
);

const plans = [
  { name: "Starter",   price: "€59",  sites: "1 site",          badge: null,           highlight: false, support: "Email support",          url: GUMROAD.starter },
  { name: "Developer", price: "€119", sites: "5 sites",         badge: "Most Popular", highlight: true,  support: "Priority email support", url: GUMROAD.developer },
  { name: "Agency",    price: "€199", sites: "Unlimited sites",  badge: null,           highlight: false, support: "Priority email support", url: GUMROAD.agency },
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
  { q: 'What does "one-time payment" mean?',         a: 'You pay once and own the plugin forever. Updates are included for 1 year. After that, the plugin keeps working — renewal is optional.' },
  { q: "Is it compatible with Joomla 4, 5, and 6?", a: "Yes. AI Boost for Joomla supports Joomla 4.0 through 6.x with PHP 8.1 through 8.5." },
  { q: "Is there a free trial?",                     a: "No free trial, but every purchase has a 30-day money-back guarantee. If it doesn't work, we refund you in full." },
  { q: "Can I upgrade my license later?",            a: "Yes. Contact support@aiboostnow.com and we'll arrange an upgrade at the price difference." },
];

const features = [
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

export function FullPage() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#FFFFFF", color: "#0C0B1D", minWidth: 1280 }}>

      {/* ─── NAV ─── */}
      <nav style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 64px", height: "92px", borderBottom: "1px solid #E8E4F4", position: "sticky", top: 0, background: "#FFFFFF", zIndex: 100 }}>
        <NavLogo />
        <div style={{ display: "flex", alignItems: "center", gap: 32 }}>
          <a href="#features" style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Features</a>
          <a href="#docs"     style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Docs</a>
          <a href="#pricing"  style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Pricing</a>
          <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" style={{ background: PURPLE, color: "#fff", fontSize: 14, fontWeight: 600, padding: "11px 22px", borderRadius: 8, textDecoration: "none" }}>Get AI Boost →</a>
        </div>
      </nav>

      {/* ─── HERO (split) ─── */}
      <section style={{ maxWidth: 1200, margin: "0 auto", padding: "90px 64px 72px", display: "flex", alignItems: "center", gap: 64 }}>
        <div style={{ flex: "0 0 48%" }}>
          <div style={{ display: "inline-flex", alignItems: "center", gap: 8, background: "#F3F0FF", border: "1px solid #D4C9FF", borderRadius: 100, padding: "6px 16px", marginBottom: 32 }}>
            <span style={{ width: 7, height: 7, borderRadius: "50%", background: PURPLE, display: "inline-block" }} />
            <span style={{ fontSize: 13, color: PURPLE, fontWeight: 600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
          </div>
          <h1 style={{ fontSize: 58, fontWeight: 900, lineHeight: 1.06, letterSpacing: "-2px", marginBottom: 24, color: "#0C0B1D" }}>
            Make your Joomla site{" "}
            <span style={{ color: PURPLE }}>visible to AI search</span>
          </h1>
          <p style={{ fontSize: 18, color: "#5A5A7A", lineHeight: 1.7, marginBottom: 40 }}>
            AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
          </p>
          <div style={{ display: "flex", gap: 14, marginBottom: 44 }}>
            <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" style={{ background: PURPLE, color: "#fff", fontSize: 16, fontWeight: 700, padding: "15px 28px", borderRadius: 10, textDecoration: "none", boxShadow: "0 4px 20px rgba(123,79,255,0.3)" }}>
              Buy Developer — €119
            </a>
            <a href="#features" style={{ background: "transparent", border: "1.5px solid #D4C9FF", color: "#5A5A7A", fontSize: 16, fontWeight: 600, padding: "15px 24px", borderRadius: 10, textDecoration: "none" }}>
              View all features ↓
            </a>
          </div>
          <div style={{ display: "flex", flexWrap: "wrap", gap: "12px 28px" }}>
            {[["Schema.org","JSON-LD"],["llms.txt","AI crawlers"],["IndexNow","Instant submit"],["11 languages","Multilingual"]].map(([n,d]) => (
              <div key={n} style={{ display: "flex", alignItems: "center", gap: 8 }}>
                <span style={{ color: PURPLE, fontWeight: 900 }}>✓</span>
                <span style={{ fontSize: 14, fontWeight: 700, color: "#0C0B1D" }}>{n}</span>
                <span style={{ fontSize: 13, color: "#9090B0" }}>{d}</span>
              </div>
            ))}
          </div>
        </div>

        <div style={{ flex: 1 }}>
          <div style={{ background: "#F8F7FF", borderRadius: 16, border: "1.5px solid #E8E4F4", overflow: "hidden", boxShadow: "0 24px 64px rgba(123,79,255,0.15), 0 4px 16px rgba(0,0,0,0.08)" }}>
            <div style={{ background: "#F0ECF8", padding: "10px 16px", display: "flex", alignItems: "center", gap: 8, borderBottom: "1px solid #E8E4F4" }}>
              <span style={{ width: 10, height: 10, borderRadius: "50%", background: "#FF6B6B", display: "inline-block" }} />
              <span style={{ width: 10, height: 10, borderRadius: "50%", background: "#FFD93D", display: "inline-block" }} />
              <span style={{ width: 10, height: 10, borderRadius: "50%", background: "#6BCB77", display: "inline-block" }} />
              <div style={{ flex: 1, background: "#fff", borderRadius: 6, padding: "4px 12px", fontSize: 11, color: "#9090B0", marginLeft: 8, border: "1px solid #E8E4F4" }}>aiboostnow.com</div>
            </div>
            <video src={videoSrc} autoPlay muted loop playsInline style={{ width: "100%", display: "block" }} />
          </div>
        </div>
      </section>

      {/* Stats */}
      <div style={{ maxWidth: 1200, margin: "0 auto", padding: "0 64px 80px" }}>
        <div style={{ background: "#F3F0FF", border: "1px solid #E0D8FF", borderRadius: 16, padding: "32px 48px", display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 32, textAlign: "center" }}>
          {[["20+","Schema.org types"],["25+","AI crawler rules"],["11","Language packs"],["5 min","Setup time"]].map(([v,l]) => (
            <div key={l}>
              <div style={{ fontSize: 38, fontWeight: 900, color: PURPLE }}>{v}</div>
              <div style={{ fontSize: 13, color: "#9090B0", marginTop: 4 }}>{l}</div>
            </div>
          ))}
        </div>
      </div>

      {/* ─── FEATURES ─── */}
      <section id="features" style={{ background: "#F8F7FF", padding: "96px 64px", borderTop: "1px solid #E8E4F4", borderBottom: "1px solid #E8E4F4" }}>
        <div style={{ maxWidth: 1200, margin: "0 auto" }}>
          <div style={{ textAlign: "center", marginBottom: 64 }}>
            <h2 style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-1.5px", marginBottom: 16, color: "#0C0B1D" }}>Everything AI search engines need</h2>
            <p style={{ fontSize: 17, color: "#5A5A7A", maxWidth: 520, margin: "0 auto" }}>One plugin covers all the signals that get your Joomla site recommended by AI engines in 2026.</p>
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24 }}>
            {feats.map(f => (
              <div key={f.title} style={{ background: "#FFFFFF", border: "1.5px solid #E8E4F4", borderRadius: 16, padding: "32px 28px" }}>
                <div style={{ fontSize: 32, marginBottom: 16 }}>{f.icon}</div>
                <div style={{ fontSize: 17, fontWeight: 700, marginBottom: 10, color: "#0C0B1D" }}>{f.title}</div>
                <div style={{ fontSize: 14, color: "#5A5A7A", lineHeight: 1.6 }}>{f.desc}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── PRICING ─── */}
      <section id="pricing" style={{ padding: "96px 64px", background: "#FFFFFF" }}>
        <div style={{ maxWidth: 1200, margin: "0 auto" }}>
          <div style={{ textAlign: "center", marginBottom: 64 }}>
            <h2 style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-1.5px", marginBottom: 16, color: "#0C0B1D" }}>Simple pricing. Every feature, every license.</h2>
            <p style={{ fontSize: 17, color: "#5A5A7A" }}>Pay once, use forever. 30-day money-back guarantee.</p>
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24, alignItems: "start" }}>
            {plans.map(plan => (
              <div key={plan.name} style={{
                background: plan.highlight ? PURPLE : "#FFFFFF",
                border: plan.highlight ? "none" : "1.5px solid #E8E4F4",
                borderRadius: 20, padding: "36px 28px", position: "relative",
                transform: plan.highlight ? "scale(1.04)" : "scale(1)",
                boxShadow: plan.highlight ? "0 12px 48px rgba(123,79,255,0.35)" : "0 2px 12px rgba(0,0,0,0.05)",
              }}>
                {plan.badge && (
                  <div style={{ position: "absolute", top: -14, left: "50%", transform: "translateX(-50%)", background: "#0C0B1D", color: "#fff", fontSize: 11, fontWeight: 700, padding: "4px 16px", borderRadius: 100, whiteSpace: "nowrap" }}>
                    {plan.badge}
                  </div>
                )}
                <div style={{ fontSize: 13, fontWeight: 700, color: plan.highlight ? "rgba(255,255,255,0.65)" : "#9090B0", textTransform: "uppercase", letterSpacing: 1, marginBottom: 8 }}>{plan.name}</div>
                <div style={{ display: "flex", alignItems: "baseline", gap: 6, marginBottom: 4 }}>
                  <span style={{ fontSize: 48, fontWeight: 900, letterSpacing: "-2px", color: plan.highlight ? "#FFFFFF" : "#0C0B1D" }}>{plan.price}</span>
                  <span style={{ fontSize: 13, color: plan.highlight ? "rgba(255,255,255,0.5)" : "#B0B0C8" }}>one-time</span>
                </div>
                <div style={{ fontSize: 12, color: plan.highlight ? "rgba(255,255,255,0.4)" : "#B0B0C8", marginBottom: 16 }}>+VAT where applicable</div>
                <div style={{ fontSize: 14, color: plan.highlight ? "rgba(255,255,255,0.75)" : "#5A5A7A", marginBottom: 28 }}>{plan.sites} · {plan.support}</div>
                <a href={plan.url} target="_blank" rel="noopener noreferrer" style={{ display: "block", textAlign: "center", padding: "13px 0", background: plan.highlight ? "#FFFFFF" : PURPLE, color: plan.highlight ? PURPLE : "#fff", fontWeight: 700, fontSize: 14, borderRadius: 10, textDecoration: "none" }}>
                  Buy {plan.name} — {plan.price}
                </a>
                <div style={{ borderTop: `1px solid ${plan.highlight ? "rgba(255,255,255,0.2)" : "#F0ECF8"}`, marginTop: 24, paddingTop: 24 }}>
                  <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 10 }}>
                    {features.map(f => (
                      <li key={f} style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                        <Check onPurple={plan.highlight} />
                        <span style={{ fontSize: 13, color: plan.highlight ? "rgba(255,255,255,0.8)" : "#5A5A7A" }}>{f}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            ))}
          </div>
          <div style={{ textAlign: "center", marginTop: 40 }}>
            <div style={{ fontSize: 14, color: "#9090B0" }}>🛡️ 30-day money-back guarantee &nbsp;·&nbsp; Payments by Gumroad &nbsp;·&nbsp; EU VAT handled automatically</div>
          </div>
        </div>
      </section>

      {/* ─── FAQ ─── */}
      <section id="docs" style={{ background: "#F8F7FF", padding: "96px 64px", borderTop: "1px solid #E8E4F4" }}>
        <div style={{ maxWidth: 720, margin: "0 auto" }}>
          <h2 style={{ fontSize: 40, fontWeight: 900, letterSpacing: "-1.5px", textAlign: "center", marginBottom: 56, color: "#0C0B1D" }}>Frequently asked questions</h2>
          {faqs.map((faq, i) => (
            <div key={i} style={{ padding: "28px 0", borderBottom: "1px solid #E8E4F4" }}>
              <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 12, color: "#0C0B1D" }}>{faq.q}</div>
              <div style={{ fontSize: 14, color: "#5A5A7A", lineHeight: 1.7 }}>{faq.a}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ─── BOTTOM CTA ─── */}
      <section style={{ padding: "96px 64px", textAlign: "center", background: "#FFFFFF" }}>
        <h2 style={{ fontSize: 52, fontWeight: 900, letterSpacing: "-2px", marginBottom: 20, color: "#0C0B1D" }}>
          Ready to make your Joomla site<br />
          <span style={{ color: PURPLE }}>visible to AI?</span>
        </h2>
        <p style={{ fontSize: 18, color: "#5A5A7A", marginBottom: 48 }}>Install in 5 minutes. No coding. No JSON editing.</p>
        <div style={{ display: "flex", gap: 16, justifyContent: "center" }}>
          <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" style={{ background: PURPLE, color: "#fff", fontSize: 17, fontWeight: 700, padding: "18px 36px", borderRadius: 12, textDecoration: "none", boxShadow: "0 4px 24px rgba(123,79,255,0.35)" }}>
            Buy Developer — €119
          </a>
          <a href={GUMROAD.starter} target="_blank" rel="noopener noreferrer" style={{ color: "#9090B0", fontSize: 17, fontWeight: 500, padding: "18px 0", textDecoration: "underline" }}>
            Or start with Starter for €59
          </a>
        </div>
      </section>

      {/* ─── FOOTER ─── */}
      <footer style={{ borderTop: "1px solid #E8E4F4", padding: "40px 64px", display: "flex", justifyContent: "space-between", alignItems: "center", background: "#F8F7FF" }}>
        <NavLogo />
        <div style={{ fontSize: 13, color: "#9090B0" }}>© 2026 AI Boost · support@aiboostnow.com</div>
        <div style={{ display: "flex", gap: 24 }}>
          <a href="#docs"    style={{ fontSize: 13, color: "#9090B0", textDecoration: "none" }}>Docs</a>
          <a href="#privacy" style={{ fontSize: 13, color: "#9090B0", textDecoration: "none" }}>Privacy</a>
          <a href="#terms"   style={{ fontSize: 13, color: "#9090B0", textDecoration: "none" }}>Terms</a>
        </div>
      </footer>

    </div>
  );
}
