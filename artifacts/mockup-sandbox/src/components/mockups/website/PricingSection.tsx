const GUMROAD = {
  basic:        "https://aiboostnow.gumroad.com/l/joomlaboost-basic",
  professional: "https://aiboostnow.gumroad.com/l/joomlaboost-professional",
};

const freeFeatures = [
  "Schema.org (Organization, LocalBusiness)",
  "XML Sitemap (basic)",
  "robots.txt (basic rules)",
  "OpenGraph + Twitter Cards",
  "Google Analytics 4",
  "Community support (forum)",
];

const paidFeatures = [
  "All plugin features included",
  "Schema.org JSON-LD (all 20+ types)",
  "Business Hours widget",
  "XML Sitemap + hreflang",
  "OpenGraph + Twitter Cards",
  "robots.txt — 25+ AI crawler rules",
  "llms.txt generator",
  "IndexNow integration",
  "GA4, GTM, Meta Pixel",
  "13 Site Type Presets",
  "11 language packs",
  "Updates & support included",
];

const plans = [
  {
    name: "Free",
    price: "€0",
    period: null,
    badge: null,
    tagline: "Core SEO features to get started",
    sites: "1 site",
    cta: "Download Free →",
    highlight: false,
    url: "/docs/getting-started",
    features: freeFeatures,
  },
  {
    name: "Basic",
    price: "€45",
    period: "/year",
    badge: null,
    tagline: "All features for a single site",
    sites: "1 license",
    cta: "Get Basic →",
    highlight: false,
    url: GUMROAD.basic,
    features: paidFeatures,
  },
  {
    name: "Professional",
    price: "€200",
    period: "/year",
    badge: "Most Popular",
    tagline: "All features for up to 10 sites",
    sites: "10 licenses",
    cta: "Get Professional →",
    highlight: true,
    url: GUMROAD.professional,
    features: paidFeatures,
  },
];

export function PricingSection() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#F8F7FF", minHeight: "100vh", padding: "80px 48px", color: "#0C0B1D" }}>
      <div style={{ maxWidth: 1000, margin: "0 auto" }}>

        <div style={{ textAlign: "center", marginBottom: 64 }}>
          <h2 style={{ fontSize: 48, fontWeight: 900, color: "#0C0B1D", letterSpacing: "-1.5px", marginBottom: 16 }}>
            Simple pricing. Every feature, every license.
          </h2>
          <p style={{ fontSize: 18, color: "#5A5A7A", maxWidth: 560, margin: "0 auto" }}>
            Annual subscription includes the plugin, all updates, and support.
          </p>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 24, alignItems: "start" }}>
          {plans.map(plan => (
            <div key={plan.name} style={{
              background: plan.highlight ? "#7B4FFF" : plan.name === "Free" ? "#F0EEFF" : "#FFFFFF",
              border: plan.highlight ? "none" : "1.5px solid #E8E4F4",
              borderRadius: 20,
              padding: "32px 28px",
              position: "relative",
              transform: plan.highlight ? "scale(1.04)" : "scale(1)",
              boxShadow: plan.highlight ? "0 12px 48px rgba(123,79,255,0.35)" : "0 2px 12px rgba(0,0,0,0.05)",
            }}>
              {plan.badge && (
                <div style={{ position: "absolute", top: -14, left: "50%", transform: "translateX(-50%)", background: "#0C0B1D", color: "#fff", fontSize: 12, fontWeight: 700, padding: "4px 16px", borderRadius: 100, whiteSpace: "nowrap" }}>
                  {plan.badge}
                </div>
              )}

              <div style={{ fontSize: 12, fontWeight: 700, color: plan.highlight ? "rgba(255,255,255,0.7)" : "#9090B0", textTransform: "uppercase", letterSpacing: 1, marginBottom: 10 }}>
                {plan.name}
              </div>
              <div style={{ display: "flex", alignItems: "baseline", gap: 4, marginBottom: 4 }}>
                <span style={{ fontSize: 48, fontWeight: 900, color: plan.highlight ? "#FFFFFF" : "#0C0B1D", letterSpacing: "-2px" }}>{plan.price}</span>
                {plan.period && (
                  <span style={{ fontSize: 14, color: plan.highlight ? "rgba(255,255,255,0.6)" : "#9090B0" }}>{plan.period}</span>
                )}
              </div>
              <div style={{ fontSize: 12, color: plan.highlight ? "rgba(255,255,255,0.5)" : "#B0B0C8", marginBottom: 16 }}>
                {plan.name === "Free" ? "always free" : "+VAT where applicable"}
              </div>
              <div style={{ fontSize: 13, color: plan.highlight ? "rgba(255,255,255,0.75)" : "#5A5A7A", marginBottom: 8 }}>{plan.sites}</div>
              <p style={{ fontSize: 13, color: plan.highlight ? "rgba(255,255,255,0.7)" : "#9090B0", marginBottom: 24, lineHeight: 1.5, marginTop: 0 }}>{plan.tagline}</p>

              <a href={plan.url} target="_blank" rel="noopener noreferrer" style={{
                display: "block", textAlign: "center", padding: "13px 0",
                background: plan.highlight ? "#FFFFFF" : plan.name === "Free" ? "#E8E4F4" : "#7B4FFF",
                color: plan.highlight ? "#7B4FFF" : plan.name === "Free" ? "#5A5A7A" : "#FFFFFF",
                fontWeight: 700, fontSize: 15, borderRadius: 10, textDecoration: "none",
                marginBottom: 24,
              }}>
                {plan.cta}
              </a>

              <div style={{ borderTop: `1px solid ${plan.highlight ? "rgba(255,255,255,0.2)" : "#F0ECF8"}`, paddingTop: 20 }}>
                <ul style={{ listStyle: "none", padding: 0, margin: 0, display: "flex", flexDirection: "column", gap: 10 }}>
                  {plan.features.map(f => (
                    <li key={f} style={{ display: "flex", gap: 10, alignItems: "flex-start" }}>
                      <span style={{ color: plan.highlight ? "rgba(255,255,255,0.85)" : plan.name === "Free" ? "#9090B0" : "#7B4FFF", fontWeight: 700, fontSize: 14, marginTop: 1, flexShrink: 0 }}>✓</span>
                      <span style={{ fontSize: 13, color: plan.highlight ? "rgba(255,255,255,0.85)" : "#5A5A7A" }}>{f}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          ))}
        </div>

        <div style={{ textAlign: "center", marginTop: 40, fontSize: 14, color: "#9090B0" }}>
          EU VAT handled automatically at checkout
        </div>

      </div>
    </div>
  );
}
