const BUY_URL = "https://aiboostnow.gumroad.com/l/joomla";

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
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#F8F7FF", minHeight: "100vh", padding: "80px 48px", color: "#0C0B1D" }}>
      <div style={{ maxWidth: 1100, margin: "0 auto" }}>

        {/* Header */}
        <div style={{ textAlign: "center", marginBottom: 64 }}>
          <h2 style={{ fontSize: 48, fontWeight: 900, color: "#0C0B1D", letterSpacing: "-1.5px", marginBottom: 16 }}>
            Simple pricing. Every feature, every license.
          </h2>
          <p style={{ fontSize: 18, color: "#5A5A7A", maxWidth: 560, margin: "0 auto" }}>
            One plugin. Everything you need to make your Joomla site visible to Google, ChatGPT, Perplexity, and Bing AI. Pay once, use forever.
          </p>
        </div>

        {/* Cards */}
        <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24, alignItems: "start" }}>
          {plans.map(plan => (
            <div key={plan.name} style={{
              background: plan.highlight ? "#7B4FFF" : "#FFFFFF",
              border: plan.highlight ? "none" : "1.5px solid #E8E4F4",
              borderRadius: 20,
              padding: "36px 32px",
              position: "relative",
              transform: plan.highlight ? "scale(1.04)" : "scale(1)",
              boxShadow: plan.highlight ? "0 12px 48px rgba(123,79,255,0.35)" : "0 2px 12px rgba(0,0,0,0.05)",
            }}>
              {plan.badge && (
                <div style={{ position: "absolute", top: -14, left: "50%", transform: "translateX(-50%)", background: "#0C0B1D", color: "#fff", fontSize: 12, fontWeight: 700, padding: "4px 16px", borderRadius: 100, whiteSpace: "nowrap" }}>
                  {plan.badge}
                </div>
              )}

              <div style={{ fontSize: 13, fontWeight: 700, color: plan.highlight ? "rgba(255,255,255,0.7)" : "#9090B0", textTransform: "uppercase", letterSpacing: 1, marginBottom: 10 }}>
                {plan.name}
              </div>
              <div style={{ display: "flex", alignItems: "baseline", gap: 6, marginBottom: 4 }}>
                <span style={{ fontSize: 52, fontWeight: 900, color: plan.highlight ? "#FFFFFF" : "#0C0B1D", letterSpacing: "-2px" }}>{plan.price}</span>
                <span style={{ fontSize: 14, color: plan.highlight ? "rgba(255,255,255,0.6)" : "#9090B0" }}>one-time</span>
              </div>
              <div style={{ fontSize: 12, color: plan.highlight ? "rgba(255,255,255,0.5)" : "#B0B0C8", marginBottom: 20 }}>+VAT where applicable</div>
              <p style={{ fontSize: 14, color: plan.highlight ? "rgba(255,255,255,0.8)" : "#5A5A7A", marginBottom: 28, lineHeight: 1.5 }}>{plan.tagline}</p>

              <a href={BUY_URL} target="_blank" rel="noopener noreferrer" style={{
                display: "block", textAlign: "center", padding: "14px 0",
                background: plan.highlight ? "#FFFFFF" : "#7B4FFF",
                color: plan.highlight ? "#7B4FFF" : "#FFFFFF",
                fontWeight: 700, fontSize: 15, borderRadius: 10, textDecoration: "none",
                marginBottom: 28,
              }}>
                {plan.cta}
              </a>

              <div style={{ borderTop: `1px solid ${plan.highlight ? "rgba(255,255,255,0.2)" : "#F0ECF8"}`, paddingTop: 24 }}>
                <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 12 }}>
                  <li style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                    <span style={{ color: plan.highlight ? "#FFFFFF" : "#7B4FFF", fontWeight: 900, fontSize: 15, marginTop: 1 }}>✓</span>
                    <span style={{ fontSize: 14, color: plan.highlight ? "#FFFFFF" : "#0C0B1D", fontWeight: 600 }}>{plan.sites}</span>
                  </li>
                  {features.map(f => (
                    <li key={f} style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                      <span style={{ color: plan.highlight ? "rgba(255,255,255,0.7)" : "#7B4FFF", fontWeight: 700, fontSize: 14, marginTop: 1 }}>✓</span>
                      <span style={{ fontSize: 14, color: plan.highlight ? "rgba(255,255,255,0.85)" : "#5A5A7A" }}>{f}</span>
                    </li>
                  ))}
                  <li style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                    <span style={{ color: plan.highlight ? "#FFFFFF" : "#7B4FFF", fontWeight: 900, fontSize: 15, marginTop: 1 }}>✓</span>
                    <span style={{ fontSize: 14, color: plan.highlight ? "#FFFFFF" : "#0C0B1D", fontWeight: 600 }}>{plan.support}</span>
                  </li>
                </ul>
              </div>
            </div>
          ))}
        </div>

        {/* Money back */}
        <div style={{ textAlign: "center", marginTop: 48, padding: "28px 32px", background: "#FFFFFF", borderRadius: 16, border: "1.5px solid #E8E4F4" }}>
          <span style={{ fontSize: 26 }}>🛡️</span>
          <div style={{ fontSize: 17, fontWeight: 800, color: "#0C0B1D", marginTop: 8 }}>30-day money-back guarantee</div>
          <div style={{ fontSize: 14, color: "#5A5A7A", marginTop: 6 }}>If AI Boost for Joomla doesn't work as expected, contact us within 30 days and we'll refund you in full — no questions asked.</div>
        </div>

      </div>
    </div>
  );
}
