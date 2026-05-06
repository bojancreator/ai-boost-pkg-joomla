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

const plans = [
  {
    name: "Starter",
    price: "€59",
    badge: null,
    tagline: "Perfect for a single Joomla site",
    sites: "1 site license",
    support: "Email support",
    cta: "Buy Starter — €59",
    highlight: false,
  },
  {
    name: "Developer",
    price: "€119",
    badge: "Most Popular",
    tagline: "For freelancers managing multiple client sites",
    sites: "5 site licenses",
    support: "Priority email support",
    cta: "Buy Developer — €119",
    highlight: true,
  },
  {
    name: "Agency",
    price: "€199",
    badge: null,
    tagline: "For agencies building unlimited client sites",
    sites: "Unlimited site licenses",
    support: "Priority email support",
    cta: "Buy Agency — €199",
    highlight: false,
  },
];

export function PricingSection() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#f8fafc", minHeight: "100vh", padding: "80px 48px" }}>
      <div style={{ maxWidth: 1100, margin: "0 auto" }}>
        <div style={{ textAlign: "center", marginBottom: 64 }}>
          <h2 style={{ fontSize: 48, fontWeight: 900, color: "#0F172A", letterSpacing: "-1.5px", marginBottom: 16 }}>
            Simple pricing. Every feature, every license.
          </h2>
          <p style={{ fontSize: 18, color: "#64748b", maxWidth: 560, margin: "0 auto" }}>
            One plugin. Everything you need to make your Joomla site visible to Google, ChatGPT, Perplexity, and Bing AI. Pay once, use forever.
          </p>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24, alignItems: "start" }}>
          {plans.map(plan => (
            <div key={plan.name} style={{
              background: plan.highlight ? "#0F172A" : "#fff",
              border: plan.highlight ? "2px solid #2563EB" : "1.5px solid #e2e8f0",
              borderRadius: 20,
              padding: "36px 32px",
              position: "relative",
              boxShadow: plan.highlight ? "0 8px 40px rgba(37,99,235,0.22)" : "0 2px 12px rgba(0,0,0,0.06)",
              transform: plan.highlight ? "scale(1.03)" : "scale(1)",
            }}>
              {plan.badge && (
                <div style={{ position: "absolute", top: -14, left: "50%", transform: "translateX(-50%)", background: "linear-gradient(135deg, #2563EB, #06B6D4)", color: "#fff", fontSize: 12, fontWeight: 700, padding: "4px 16px", borderRadius: 100 }}>
                  {plan.badge}
                </div>
              )}
              <div style={{ marginBottom: 8 }}>
                <span style={{ fontSize: 14, fontWeight: 700, color: plan.highlight ? "#94a3b8" : "#64748b", textTransform: "uppercase", letterSpacing: 1 }}>{plan.name}</span>
              </div>
              <div style={{ display: "flex", alignItems: "baseline", gap: 6, marginBottom: 6 }}>
                <span style={{ fontSize: 52, fontWeight: 900, color: plan.highlight ? "#fff" : "#0F172A", letterSpacing: "-2px" }}>{plan.price}</span>
                <span style={{ fontSize: 14, color: plan.highlight ? "#64748b" : "#94a3b8" }}>one-time</span>
              </div>
              <div style={{ fontSize: 12, color: plan.highlight ? "#475569" : "#94a3b8", marginBottom: 20 }}>+VAT where applicable</div>
              <p style={{ fontSize: 14, color: plan.highlight ? "#94a3b8" : "#64748b", marginBottom: 28, lineHeight: 1.5 }}>{plan.tagline}</p>

              <a style={{
                display: "block", textAlign: "center", padding: "14px 0",
                background: plan.highlight ? "linear-gradient(135deg, #2563EB, #06B6D4)" : "rgba(37,99,235,0.08)",
                color: plan.highlight ? "#fff" : "#2563EB",
                fontWeight: 700, fontSize: 15, borderRadius: 10, textDecoration: "none",
                marginBottom: 28,
              }}>
                {plan.cta}
              </a>

              <div style={{ borderTop: `1px solid ${plan.highlight ? "rgba(255,255,255,0.08)" : "#f1f5f9"}`, paddingTop: 24 }}>
                <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 12 }}>
                  <li style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                    <span style={{ color: "#06B6D4", fontWeight: 700, marginTop: 1 }}>✓</span>
                    <span style={{ fontSize: 14, color: plan.highlight ? "#e2e8f0" : "#374151", fontWeight: 600 }}>{plan.sites}</span>
                  </li>
                  {features.map(f => (
                    <li key={f} style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                      <span style={{ color: "#06B6D4", fontWeight: 700, marginTop: 1 }}>✓</span>
                      <span style={{ fontSize: 14, color: plan.highlight ? "#cbd5e1" : "#374151" }}>{f}</span>
                    </li>
                  ))}
                  <li style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                    <span style={{ color: "#06B6D4", fontWeight: 700, marginTop: 1 }}>✓</span>
                    <span style={{ fontSize: 14, color: plan.highlight ? "#e2e8f0" : "#374151", fontWeight: 600 }}>{plan.support}</span>
                  </li>
                </ul>
              </div>
            </div>
          ))}
        </div>

        <div style={{ textAlign: "center", marginTop: 48, padding: "32px", background: "#fff", borderRadius: 16, border: "1.5px solid #e2e8f0" }}>
          <span style={{ fontSize: 28 }}>🛡️</span>
          <div style={{ fontSize: 18, fontWeight: 800, color: "#0F172A", marginTop: 8 }}>30-day money-back guarantee</div>
          <div style={{ fontSize: 14, color: "#64748b", marginTop: 6 }}>If JoomlaBoost doesn't work as expected, contact us within 30 days and we'll refund you in full — no questions asked.</div>
        </div>
      </div>
    </div>
  );
}
