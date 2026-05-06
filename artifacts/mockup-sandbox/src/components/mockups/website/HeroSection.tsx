import logoSrc from "@/assets/logo.png";

export function HeroSection() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#FFFFFF", minHeight: "100vh", color: "#0C0B1D" }}>

      {/* NAV */}
      <nav style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 48px", height: "80px", borderBottom: "1px solid #E8E4F4" }}>
        <img src={logoSrc} style={{ height: 56, width: "auto", display: "block" }} alt="AI Boost" />
        <div style={{ display: "flex", alignItems: "center", gap: "32px" }}>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Features</a>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Docs</a>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Pricing</a>
          <a style={{ background: "#7B4FFF", color: "#fff", fontSize: 14, fontWeight: 600, padding: "11px 22px", borderRadius: 8, textDecoration: "none" }}>
            Get AI Boost →
          </a>
        </div>
      </nav>

      {/* HERO */}
      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "96px 48px 72px" }}>

        {/* Badge */}
        <div style={{ display: "inline-flex", alignItems: "center", gap: 8, background: "#F3F0FF", border: "1px solid #D4C9FF", borderRadius: 100, padding: "6px 16px", marginBottom: 36 }}>
          <span style={{ width: 7, height: 7, borderRadius: "50%", background: "#7B4FFF", display: "inline-block" }} />
          <span style={{ fontSize: 13, color: "#7B4FFF", fontWeight: 600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
        </div>

        <h1 style={{ fontSize: 68, fontWeight: 900, lineHeight: 1.05, letterSpacing: "-2px", marginBottom: 28, maxWidth: 780, color: "#0C0B1D" }}>
          Make your Joomla site{" "}
          <span style={{ color: "#7B4FFF" }}>visible to AI search</span>
        </h1>

        <p style={{ fontSize: 20, color: "#5A5A7A", lineHeight: 1.7, maxWidth: 620, marginBottom: 48 }}>
          AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
        </p>

        {/* CTA buttons */}
        <div style={{ display: "flex", gap: 16, marginBottom: 72 }}>
          <a style={{ background: "#7B4FFF", color: "#fff", fontSize: 16, fontWeight: 700, padding: "16px 32px", borderRadius: 10, textDecoration: "none", boxShadow: "0 4px 20px rgba(123,79,255,0.3)" }}>
            Buy Developer — €119
          </a>
          <a style={{ background: "transparent", border: "1.5px solid #D4C9FF", color: "#5A5A7A", fontSize: 16, fontWeight: 600, padding: "16px 28px", borderRadius: 10, textDecoration: "none" }}>
            View all features ↓
          </a>
        </div>

        {/* Feature pills */}
        <div style={{ display: "flex", gap: 36 }}>
          {[
            { n: "Schema.org", d: "JSON-LD structured data" },
            { n: "llms.txt", d: "AI crawler permission file" },
            { n: "IndexNow", d: "Instant URL submission" },
            { n: "11 languages", d: "Full multilingual support" },
          ].map(f => (
            <div key={f.n} style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <span style={{ color: "#7B4FFF", fontSize: 16, fontWeight: 900 }}>✓</span>
              <div>
                <div style={{ fontSize: 14, fontWeight: 700, color: "#0C0B1D" }}>{f.n}</div>
                <div style={{ fontSize: 12, color: "#9090B0" }}>{f.d}</div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Stats bar */}
      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "0 48px 80px" }}>
        <div style={{ background: "#F3F0FF", border: "1px solid #E0D8FF", borderRadius: 16, padding: "36px 48px", display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 32, textAlign: "center" }}>
          {[
            { v: "20+", l: "Schema.org types" },
            { v: "25+", l: "AI crawler rules" },
            { v: "11", l: "Language packs" },
            { v: "5 min", l: "Setup time" },
          ].map(s => (
            <div key={s.l}>
              <div style={{ fontSize: 40, fontWeight: 900, color: "#7B4FFF" }}>{s.v}</div>
              <div style={{ fontSize: 13, color: "#9090B0", marginTop: 4 }}>{s.l}</div>
            </div>
          ))}
        </div>
      </div>

    </div>
  );
}
