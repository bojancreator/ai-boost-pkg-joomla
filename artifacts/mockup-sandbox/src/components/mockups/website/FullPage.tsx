import logoSrc from "@/assets/logo.png";

const PURPLE = "#7B4FFF";
const PURPLE_LIGHT = "#A78BFF";
const PURPLE_SOFT = "#A855F7";

const NavLogo = () => (
  <img src={logoSrc} style={{ height: 38, width: "auto", display: "block" }} alt="AI Boost" />
);

const Check = () => (
  <span style={{ color: PURPLE_SOFT, fontWeight: 900, fontSize: 15, marginTop: 1, flexShrink: 0 }}>✓</span>
);

const plans = [
  { name: "Starter",   price: "€59",  sites: "1 site",         badge: null,           highlight: false, support: "Email support" },
  { name: "Developer", price: "€119", sites: "5 sites",        badge: "Most Popular", highlight: true,  support: "Priority email support" },
  { name: "Agency",    price: "€199", sites: "Unlimited sites", badge: null,           highlight: false, support: "Priority email support" },
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
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#0C0B1D", color: "#fff", minWidth: 1280 }}>

      {/* ─── NAV ─── */}
      <nav style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 64px", height: "72px", borderBottom: "1px solid rgba(255,255,255,0.12)", position: "sticky", top: 0, background: "#0C0B1D", zIndex: 100 }}>
        <NavLogo />
        <div style={{ display: "flex", alignItems: "center", gap: 32 }}>
          <a style={{ color: "#C4C4D8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Features</a>
          <a style={{ color: "#C4C4D8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Docs</a>
          <a style={{ color: "#C4C4D8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Pricing</a>
          <a style={{ background: PURPLE, color: "#fff", fontSize: 14, fontWeight: 600, padding: "10px 20px", borderRadius: 8, textDecoration: "none" }}>Get AI Boost →</a>
        </div>
      </nav>

      {/* ─── HERO ─── */}
      <section style={{ maxWidth: 1100, margin: "0 auto", padding: "110px 64px 80px", textAlign: "center" }}>
        <div style={{ display: "inline-flex", alignItems: "center", gap: 8, border: "1px solid #7B4FFF", borderRadius: 100, padding: "6px 16px", marginBottom: 36 }}>
          <span style={{ width: 7, height: 7, borderRadius: "50%", background: PURPLE_SOFT, display: "inline-block" }} />
          <span style={{ fontSize: 13, color: "#C084FC", fontWeight: 600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
        </div>

        <h1 style={{ fontSize: 72, fontWeight: 900, lineHeight: 1.04, letterSpacing: "-2.5px", marginBottom: 28, color: "#FFFFFF" }}>
          Make your Joomla site<br />
          <span style={{ color: PURPLE_LIGHT }}>visible to AI search</span>
        </h1>
        <p style={{ fontSize: 20, color: "#A0A0C0", lineHeight: 1.7, maxWidth: 640, margin: "0 auto 48px" }}>
          AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
        </p>

        <div style={{ display: "flex", gap: 16, justifyContent: "center", marginBottom: 72 }}>
          <a style={{ background: PURPLE, color: "#fff", fontSize: 17, fontWeight: 700, padding: "18px 36px", borderRadius: 12, textDecoration: "none" }}>
            Buy Developer — €119
          </a>
          <a style={{ background: "transparent", border: "1px solid rgba(255,255,255,0.25)", color: "#E2E2F0", fontSize: 17, fontWeight: 600, padding: "18px 28px", borderRadius: 12, textDecoration: "none" }}>
            View all features ↓
          </a>
        </div>

        <div style={{ display: "flex", gap: 48, justifyContent: "center" }}>
          {[["20+", "Schema.org types"], ["25+", "AI crawler rules"], ["11", "Language packs"], ["5 min", "Setup time"]].map(([v, l]) => (
            <div key={l} style={{ textAlign: "center" }}>
              <div style={{ fontSize: 36, fontWeight: 900, color: PURPLE_LIGHT }}>{v}</div>
              <div style={{ fontSize: 13, color: "#6B6B8A" }}>{l}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ─── FEATURES ─── */}
      <section style={{ background: "#100F24", padding: "96px 64px", borderTop: "1px solid rgba(255,255,255,0.08)", borderBottom: "1px solid rgba(255,255,255,0.08)" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto" }}>
          <div style={{ textAlign: "center", marginBottom: 64 }}>
            <h2 style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-1.5px", marginBottom: 16, color: "#FFFFFF" }}>Everything AI search engines need</h2>
            <p style={{ fontSize: 17, color: "#A0A0C0", maxWidth: 520, margin: "0 auto" }}>One plugin covers all the signals that get your Joomla site recommended by AI engines in 2026.</p>
          </div>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24 }}>
            {feats.map(f => (
              <div key={f.title} style={{ background: "#13122A", border: "1px solid rgba(255,255,255,0.1)", borderRadius: 16, padding: "32px 28px" }}>
                <div style={{ fontSize: 32, marginBottom: 16 }}>{f.icon}</div>
                <div style={{ fontSize: 17, fontWeight: 700, marginBottom: 10, color: "#FFFFFF" }}>{f.title}</div>
                <div style={{ fontSize: 14, color: "#A0A0C0", lineHeight: 1.6 }}>{f.desc}</div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── PRICING ─── */}
      <section style={{ padding: "96px 64px", background: "#0C0B1D" }}>
        <div style={{ maxWidth: 1100, margin: "0 auto" }}>
          <div style={{ textAlign: "center", marginBottom: 64 }}>
            <h2 style={{ fontSize: 44, fontWeight: 900, letterSpacing: "-1.5px", marginBottom: 16, color: "#FFFFFF" }}>Simple pricing. Every feature, every license.</h2>
            <p style={{ fontSize: 17, color: "#A0A0C0" }}>Pay once, use forever. 30-day money-back guarantee.</p>
          </div>

          <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24, alignItems: "start" }}>
            {plans.map(plan => (
              <div key={plan.name} style={{
                background: plan.highlight ? "#1A1435" : "#13122A",
                border: plan.highlight ? `2px solid ${PURPLE}` : "1px solid rgba(255,255,255,0.12)",
                borderRadius: 20, padding: "36px 28px", position: "relative",
                transform: plan.highlight ? "scale(1.04)" : "scale(1)",
              }}>
                {plan.badge && (
                  <div style={{ position: "absolute", top: -14, left: "50%", transform: "translateX(-50%)", background: PURPLE, color: "#fff", fontSize: 11, fontWeight: 700, padding: "4px 16px", borderRadius: 100, whiteSpace: "nowrap" }}>
                    {plan.badge}
                  </div>
                )}
                <div style={{ fontSize: 13, fontWeight: 700, color: "#6B6B8A", textTransform: "uppercase", letterSpacing: 1, marginBottom: 8 }}>{plan.name}</div>
                <div style={{ display: "flex", alignItems: "baseline", gap: 6, marginBottom: 4 }}>
                  <span style={{ fontSize: 48, fontWeight: 900, letterSpacing: "-2px", color: "#FFFFFF" }}>{plan.price}</span>
                  <span style={{ fontSize: 13, color: "#4A4A6A" }}>one-time</span>
                </div>
                <div style={{ fontSize: 12, color: "#4A4A6A", marginBottom: 16 }}>+VAT where applicable</div>
                <div style={{ fontSize: 14, color: "#A0A0C0", marginBottom: 28 }}>{plan.sites} · {plan.support}</div>
                <a style={{ display: "block", textAlign: "center", padding: "13px 0", background: plan.highlight ? PURPLE : "transparent", border: plan.highlight ? "none" : "1px solid rgba(255,255,255,0.2)", color: "#fff", fontWeight: 700, fontSize: 14, borderRadius: 10, textDecoration: "none" }}>
                  Buy {plan.name} — {plan.price}
                </a>

                <div style={{ borderTop: "1px solid rgba(255,255,255,0.08)", marginTop: 24, paddingTop: 24 }}>
                  <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 10 }}>
                    {features.map(f => (
                      <li key={f} style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                        <Check />
                        <span style={{ fontSize: 13, color: "#A0A0C0" }}>{f}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>
            ))}
          </div>

          <div style={{ textAlign: "center", marginTop: 40 }}>
            <div style={{ fontSize: 14, color: "#4A4A6A" }}>🛡️ 30-day money-back guarantee &nbsp;·&nbsp; Payments by Gumroad &nbsp;·&nbsp; EU VAT handled automatically</div>
          </div>
        </div>
      </section>

      {/* ─── FAQ ─── */}
      <section style={{ background: "#100F24", padding: "96px 64px", borderTop: "1px solid rgba(255,255,255,0.08)" }}>
        <div style={{ maxWidth: 720, margin: "0 auto" }}>
          <h2 style={{ fontSize: 40, fontWeight: 900, letterSpacing: "-1.5px", textAlign: "center", marginBottom: 56, color: "#FFFFFF" }}>Frequently asked questions</h2>
          {faqs.map((faq, i) => (
            <div key={i} style={{ padding: "28px 0", borderBottom: "1px solid rgba(255,255,255,0.08)" }}>
              <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 12, color: "#FFFFFF" }}>{faq.q}</div>
              <div style={{ fontSize: 14, color: "#A0A0C0", lineHeight: 1.7 }}>{faq.a}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ─── BOTTOM CTA ─── */}
      <section style={{ padding: "96px 64px", textAlign: "center", background: "#0C0B1D" }}>
        <h2 style={{ fontSize: 52, fontWeight: 900, letterSpacing: "-2px", marginBottom: 20, color: "#FFFFFF" }}>
          Ready to make your Joomla site<br />
          <span style={{ color: PURPLE_LIGHT }}>visible to AI?</span>
        </h2>
        <p style={{ fontSize: 18, color: "#A0A0C0", marginBottom: 48 }}>Install in 5 minutes. No coding. No JSON editing.</p>
        <div style={{ display: "flex", gap: 16, justifyContent: "center" }}>
          <a style={{ background: PURPLE, color: "#fff", fontSize: 17, fontWeight: 700, padding: "18px 36px", borderRadius: 12, textDecoration: "none" }}>
            Buy Developer — €119
          </a>
          <a style={{ color: "#A0A0C0", fontSize: 17, fontWeight: 500, padding: "18px 0", textDecoration: "underline" }}>
            Or start with Starter for €59
          </a>
        </div>
      </section>

      {/* ─── FOOTER ─── */}
      <footer style={{ borderTop: "1px solid rgba(255,255,255,0.1)", padding: "40px 64px", display: "flex", justifyContent: "space-between", alignItems: "center" }}>
        <NavLogo />
        <div style={{ fontSize: 13, color: "#4A4A6A" }}>© 2026 AI Boost · support@aiboostnow.com</div>
        <div style={{ display: "flex", gap: 24 }}>
          <a style={{ fontSize: 13, color: "#6B6B8A", textDecoration: "none" }}>Docs</a>
          <a style={{ fontSize: 13, color: "#6B6B8A", textDecoration: "none" }}>Privacy</a>
          <a style={{ fontSize: 13, color: "#6B6B8A", textDecoration: "none" }}>Terms</a>
        </div>
      </footer>

    </div>
  );
}
