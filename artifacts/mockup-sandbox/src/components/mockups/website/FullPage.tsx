const plans = [
  { name: "Starter", price: "€59", sites: "1 site", badge: null, highlight: false, support: "Email support" },
  { name: "Developer", price: "€119", sites: "5 sites", badge: "Most Popular", highlight: true, support: "Priority email support" },
  { name: "Agency", price: "€199", sites: "Unlimited sites", badge: null, highlight: false, support: "Priority email support" },
];

const feats = [
  { icon: "🧠", title: "Schema.org JSON-LD", desc: "All 20+ types: LocalBusiness, Hotel, Event, FAQPage, Article, Person, Product, BreadcrumbList and more." },
  { icon: "🗺️", title: "XML Sitemap + Hreflang", desc: "Dynamic sitemap auto-generated. Multilingual hreflang tags for all installed Joomla languages." },
  { icon: "🤖", title: "robots.txt + llms.txt", desc: "Block or allow 25+ AI crawlers. Generate llms.txt so ChatGPT and Perplexity can index your content." },
  { icon: "⚡", title: "IndexNow", desc: "Instant URL submission to Bing, Yandex, and Seznam the moment you publish new content." },
  { icon: "📊", title: "Analytics Suite", desc: "GA4, Google Tag Manager, Google Search Console verification, Meta Pixel — all from one panel." },
  { icon: "🌍", title: "11 Language Packs", desc: "Full admin UI in EN, DE, FR, ES, IT, RU, PT, ZH, AR, JA, SR. Multilingual custom fields too." },
];

const faqs = [
  { q: "What does \"one-time payment\" mean?", a: "You pay once and own the plugin forever. Updates are included for 1 year. After that, the plugin keeps working — renewal is optional." },
  { q: "Is it compatible with Joomla 4, 5, and 6?", a: "Yes. JoomlaBoost supports Joomla 4.0 through 6.x with PHP 8.1, 8.2, and 8.3." },
  { q: "Is there a free trial?", a: "No free trial, but every purchase has a 30-day money-back guarantee. If it doesn't work, we refund you in full." },
  { q: "Can I upgrade my license later?", a: "Yes. Contact support@aiboostnow.com and we'll arrange an upgrade at the price difference." },
];

export function FullPage() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#0F172A", color: "#fff", minWidth: 1280 }}>

      {/* NAV */}
      <nav style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 64px", height: "72px", borderBottom: "1px solid rgba(255,255,255,0.07)", position: "sticky", top: 0, background: "rgba(15,23,42,0.95)", backdropFilter: "blur(12px)", zIndex: 100 }}>
        <div>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" width="160" height="40">
            <defs><linearGradient id="fp_g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" /></linearGradient></defs>
            <path d="M 8 12 Q 8 4, 16 4 L 56 4 Q 64 4, 64 12 L 64 44 Q 64 52, 56 52 L 32 52 L 24 64 L 24 52 L 16 52 Q 8 52, 8 44 Z" fill="url(#fp_g)" />
            <path d="M 36 16 L 39 26 L 49 28 L 39 30 L 36 40 L 33 30 L 23 28 L 33 26 Z" fill="#0F172A" />
            <circle cx="50" cy="18" r="2" fill="#0F172A" opacity="0.7" />
            <text x="84" y="36" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="900" letterSpacing="-0.3" fill="#F1F5F9">AI Boost</text>
            <text x="84" y="58" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="13" fontWeight="700" letterSpacing="3" fill="#22D3EE">N O W</text>
          </svg>
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: 32 }}>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Features</a>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Docs</a>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Pricing</a>
          <a style={{ background: "linear-gradient(135deg, #2563EB, #06B6D4)", color: "#fff", fontSize: 14, fontWeight: 600, padding: "10px 20px", borderRadius: 8, textDecoration: "none" }}>Get JoomlaBoost →</a>
        </div>
      </nav>

      {/* HERO */}
      <section style={{ maxWidth: 1100, margin: "0 auto", padding: "110px 64px 80px", textAlign: "center" }}>
        <div style={{ display: "inline-flex", alignItems: "center", gap: 8, background: "rgba(6,182,212,0.1)", border: "1px solid rgba(6,182,212,0.25)", borderRadius: 100, padding: "6px 16px", marginBottom: 36 }}>
          <span style={{ width: 8, height: 8, borderRadius: "50%", background: "#06B6D4", display: "inline-block" }} />
          <span style={{ fontSize: 13, color: "#06B6D4", fontWeight: 600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
        </div>
        <h1 style={{ fontSize: 72, fontWeight: 900, lineHeight: 1.04, letterSpacing: "-2.5px", marginBottom: 28 }}>
          Make your Joomla site<br />
          <span style={{ background: "linear-gradient(135deg, #2563EB, #06B6D4)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>
            visible to AI search
          </span>
        </h1>
        <p style={{ fontSize: 20, color: "#94a3b8", lineHeight: 1.7, maxWidth: 640, margin: "0 auto 48px" }}>
          JoomlaBoost generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
        </p>
        <div style={{ display: "flex", gap: 16, justifyContent: "center", marginBottom: 72 }}>
          <a style={{ background: "linear-gradient(135deg, #2563EB, #06B6D4)", color: "#fff", fontSize: 17, fontWeight: 700, padding: "18px 36px", borderRadius: 12, textDecoration: "none", boxShadow: "0 4px 28px rgba(37,99,235,0.4)" }}>
            Buy Developer — €119
          </a>
          <a style={{ background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.12)", color: "#e2e8f0", fontSize: 17, fontWeight: 600, padding: "18px 28px", borderRadius: 12, textDecoration: "none" }}>
            View all features ↓
          </a>
        </div>
        <div style={{ display: "flex", gap: 48, justifyContent: "center" }}>
          {[["20+", "Schema.org types"], ["25+", "AI crawler rules"], ["11", "Language packs"], ["5 min", "Setup time"]].map(([v, l]) => (
            <div key={l} style={{ textAlign: "center" }}>
              <div style={{ fontSize: 36, fontWeight: 900, background: "linear-gradient(135deg, #2563EB, #06B6D4)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>{v}</div>
              <div style={{ fontSize: 13, color: "#64748b" }}>{l}</div>
            </div>
          ))}
        </div>
      </section>

      {/* FEATURES */}
      <section style={{ background: "#0B1120", padding: "96px 64px" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto" }}>
          <div style={{ textAlign: "center", marginBottom: 64 }}>
            <h2 style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-1.5px", marginBottom: 16 }}>Everything AI search engines need</h2>
            <p style={{ fontSize: 17, color: "#64748b", maxWidth: 520, margin: "0 auto" }}>One plugin covers all the signals that get your Joomla site recommended by AI engines in 2026.</p>
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24 }}>
            {feats.map(f => (
              <div key={f.title} style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.07)", borderRadius: 16, padding: "32px 28px" }}>
                <div style={{ fontSize: 32, marginBottom: 16 }}>{f.icon}</div>
                <div style={{ fontSize: 17, fontWeight: 700, marginBottom: 10 }}>{f.title}</div>
                <div style={{ fontSize: 14, color: "#64748b", lineHeight: 1.6 }}>{f.desc}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* PRICING */}
      <section style={{ padding: "96px 64px", background: "#0F172A" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto" }}>
          <div style={{ textAlign: "center", marginBottom: 64 }}>
            <h2 style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-1.5px", marginBottom: 16 }}>Simple pricing. Every feature, every license.</h2>
            <p style={{ fontSize: 17, color: "#64748b" }}>Pay once, use forever. 30-day money-back guarantee.</p>
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24, alignItems: "start" }}>
            {plans.map(plan => (
              <div key={plan.name} style={{
                background: plan.highlight ? "rgba(37,99,235,0.12)" : "rgba(255,255,255,0.03)",
                border: plan.highlight ? "1.5px solid #2563EB" : "1px solid rgba(255,255,255,0.08)",
                borderRadius: 20, padding: "36px 28px", position: "relative",
                transform: plan.highlight ? "scale(1.04)" : "scale(1)",
              }}>
                {plan.badge && (
                  <div style={{ position: "absolute", top: -14, left: "50%", transform: "translateX(-50%)", background: "linear-gradient(135deg, #2563EB, #06B6D4)", color: "#fff", fontSize: 11, fontWeight: 700, padding: "4px 16px", borderRadius: 100, whiteSpace: "nowrap" }}>
                    {plan.badge}
                  </div>
                )}
                <div style={{ fontSize: 13, fontWeight: 700, color: "#64748b", textTransform: "uppercase", letterSpacing: 1, marginBottom: 8 }}>{plan.name}</div>
                <div style={{ display: "flex", alignItems: "baseline", gap: 6, marginBottom: 4 }}>
                  <span style={{ fontSize: 48, fontWeight: 900, letterSpacing: "-2px" }}>{plan.price}</span>
                  <span style={{ fontSize: 13, color: "#475569" }}>one-time</span>
                </div>
                <div style={{ fontSize: 12, color: "#475569", marginBottom: 16 }}>+VAT where applicable</div>
                <div style={{ fontSize: 14, color: "#94a3b8", marginBottom: 28 }}>{plan.sites} · {plan.support}</div>
                <a style={{ display: "block", textAlign: "center", padding: "13px 0", background: plan.highlight ? "linear-gradient(135deg, #2563EB, #06B6D4)" : "rgba(255,255,255,0.06)", color: "#fff", fontWeight: 700, fontSize: 14, borderRadius: 10, textDecoration: "none" }}>
                  Buy {plan.name} — {plan.price}
                </a>
              </div>
            ))}
          </div>
          <div style={{ textAlign: "center", marginTop: 40 }}>
            <div style={{ fontSize: 14, color: "#475569" }}>🛡️ 30-day money-back guarantee &nbsp;·&nbsp; Payments by Gumroad &nbsp;·&nbsp; EU VAT handled automatically</div>
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section style={{ background: "#0B1120", padding: "96px 64px" }}>
        <div style={{ maxWidth: 720, margin: "0 auto" }}>
          <h2 style={{ fontSize: 40, fontWeight: 900, letterSpacing: "-1.5px", textAlign: "center", marginBottom: 56 }}>Frequently asked questions</h2>
          <div style={{ display: "flex", flexDirection: "column", gap: 0 }}>
            {faqs.map((faq, i) => (
              <div key={i} style={{ padding: "28px 0", borderBottom: "1px solid rgba(255,255,255,0.07)" }}>
                <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 12 }}>{faq.q}</div>
                <div style={{ fontSize: 14, color: "#64748b", lineHeight: 1.7 }}>{faq.a}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* BOTTOM CTA */}
      <section style={{ padding: "96px 64px", textAlign: "center", background: "linear-gradient(180deg, #0F172A 0%, #0B1120 100%)" }}>
        <h2 style={{ fontSize: 52, fontWeight: 900, letterSpacing: "-2px", marginBottom: 20 }}>
          Ready to make your Joomla site<br />
          <span style={{ background: "linear-gradient(135deg, #2563EB, #06B6D4)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>visible to AI?</span>
        </h2>
        <p style={{ fontSize: 18, color: "#64748b", marginBottom: 48 }}>Install in 5 minutes. No coding. No JSON editing.</p>
        <div style={{ display: "flex", gap: 16, justifyContent: "center" }}>
          <a style={{ background: "linear-gradient(135deg, #2563EB, #06B6D4)", color: "#fff", fontSize: 17, fontWeight: 700, padding: "18px 36px", borderRadius: 12, textDecoration: "none", boxShadow: "0 4px 28px rgba(37,99,235,0.4)" }}>
            Buy Developer — €119
          </a>
          <a style={{ color: "#94a3b8", fontSize: 17, fontWeight: 500, padding: "18px 0", textDecoration: "underline" }}>
            Or start with Starter for €59
          </a>
        </div>
      </section>

      {/* FOOTER */}
      <footer style={{ borderTop: "1px solid rgba(255,255,255,0.07)", padding: "40px 64px", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <div>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" width="130" height="32">
            <defs><linearGradient id="ft_g" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" /></linearGradient></defs>
            <path d="M 8 12 Q 8 4, 16 4 L 56 4 Q 64 4, 64 12 L 64 44 Q 64 52, 56 52 L 32 52 L 24 64 L 24 52 L 16 52 Q 8 52, 8 44 Z" fill="url(#ft_g)" />
            <path d="M 36 16 L 39 26 L 49 28 L 39 30 L 36 40 L 33 30 L 23 28 L 33 26 Z" fill="#0F172A" />
            <circle cx="50" cy="18" r="2" fill="#0F172A" opacity="0.7" />
            <text x="84" y="36" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="900" letterSpacing="-0.3" fill="#F1F5F9">AI Boost</text>
            <text x="84" y="58" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="13" fontWeight="700" letterSpacing="3" fill="#22D3EE">N O W</text>
          </svg>
        </div>
        <div style={{ fontSize: 13, color: "#475569" }}>© 2026 AI Boost Now · support@aiboostnow.com</div>
        <div style={{ display: "flex", gap: 24 }}>
          <a style={{ fontSize: 13, color: "#475569", textDecoration: "none" }}>Docs</a>
          <a style={{ fontSize: 13, color: "#475569", textDecoration: "none" }}>Privacy</a>
          <a style={{ fontSize: 13, color: "#475569", textDecoration: "none" }}>Terms</a>
        </div>
      </footer>
    </div>
  );
}
