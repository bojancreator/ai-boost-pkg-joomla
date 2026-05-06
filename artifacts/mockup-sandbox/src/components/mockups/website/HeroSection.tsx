import logoSrc from "@/assets/logo.png";

export function HeroSection() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#0C0B1D", minHeight: "100vh", color: "#fff" }}>

      {/* NAV */}
      <nav style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 48px", height: "72px", borderBottom: "1px solid rgba(255,255,255,0.12)" }}>
        <img src={logoSrc} style={{ height: 40, width: "auto", display: "block" }} alt="AI Boost" />
        <div style={{ display: "flex", alignItems: "center", gap: "32px" }}>
          <a style={{ color: "#C4C4D8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Features</a>
          <a style={{ color: "#C4C4D8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Docs</a>
          <a style={{ color: "#C4C4D8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Pricing</a>
          <a style={{ background: "#7B4FFF", color: "#fff", fontSize: 14, fontWeight: 600, padding: "10px 20px", borderRadius: 8, textDecoration: "none" }}>
            Get AI Boost →
          </a>
        </div>
      </nav>

      {/* HERO */}
      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "96px 48px 72px" }}>

        {/* Badge */}
        <div style={{ display: "inline-flex", alignItems: "center", gap: 8, border: "1px solid #7B4FFF", borderRadius: 100, padding: "6px 16px", marginBottom: 36 }}>
          <span style={{ width: 7, height: 7, borderRadius: "50%", background: "#A855F7", display: "inline-block" }} />
          <span style={{ fontSize: 13, color: "#C084FC", fontWeight: 600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
        </div>

        <h1 style={{ fontSize: 68, fontWeight: 900, lineHeight: 1.05, letterSpacing: "-2px", marginBottom: 28, maxWidth: 780, color: "#FFFFFF" }}>
          Make your Joomla site{" "}
          <span style={{ color: "#A78BFF" }}>visible to AI search</span>
        </h1>

        <p style={{ fontSize: 20, color: "#A0A0C0", lineHeight: 1.7, maxWidth: 620, marginBottom: 48 }}>
          AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
        </p>

        {/* CTA buttons */}
        <div style={{ display: "flex", gap: 16, marginBottom: 72 }}>
          <a style={{ background: "#7B4FFF", color: "#fff", fontSize: 16, fontWeight: 700, padding: "16px 32px", borderRadius: 10, textDecoration: "none" }}>
            Buy Developer — €119
          </a>
          <a style={{ background: "transparent", border: "1px solid rgba(255,255,255,0.25)", color: "#E2E2F0", fontSize: 16, fontWeight: 600, padding: "16px 28px", borderRadius: 10, textDecoration: "none" }}>
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
              <span style={{ color: "#A855F7", fontSize: 16, fontWeight: 900 }}>✓</span>
              <div>
                <div style={{ fontSize: 14, fontWeight: 700, color: "#E2E2F0" }}>{f.n}</div>
                <div style={{ fontSize: 12, color: "#6B6B8A" }}>{f.d}</div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Stats bar */}
      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "0 48px 80px" }}>
        <div style={{ border: "1px solid rgba(255,255,255,0.12)", borderRadius: 16, padding: "36px 48px", display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 32, textAlign: "center" }}>
          {[
            { v: "20+", l: "Schema.org types" },
            { v: "25+", l: "AI crawler rules" },
            { v: "11", l: "Language packs" },
            { v: "5 min", l: "Setup time" },
          ].map(s => (
            <div key={s.l}>
              <div style={{ fontSize: 40, fontWeight: 900, color: "#A78BFF" }}>{s.v}</div>
              <div style={{ fontSize: 13, color: "#6B6B8A", marginTop: 4 }}>{s.l}</div>
            </div>
          ))}
        </div>
      </div>

    </div>
  );
}
