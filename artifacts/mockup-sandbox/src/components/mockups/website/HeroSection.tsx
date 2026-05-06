import logoSrc from "@/assets/logo.png";

const NAV_LOGO_HEIGHT = 44;

export function HeroSection() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#0D0D1A", minHeight: "100vh", color: "#fff" }}>
      <nav style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 48px", height: "72px", borderBottom: "1px solid rgba(255,255,255,0.07)" }}>
        <img src={logoSrc} style={{ height: 44, width: "auto", display: "block" }} alt="AI Boost" />
        <div style={{ display: "flex", alignItems: "center", gap: "32px" }}>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Features</a>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Docs</a>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Pricing</a>
          <a style={{ background: "linear-gradient(135deg, #1A3FF0, #BB00FF)", color: "#fff", fontSize: 14, fontWeight: 600, padding: "10px 20px", borderRadius: 8, textDecoration: "none" }}>Get AI Boost →</a>
        </div>
      </nav>

      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "100px 48px 80px" }}>
        <div style={{ display: "inline-flex", alignItems: "center", gap: 8, background: "rgba(187,0,255,0.1)", border: "1px solid rgba(187,0,255,0.25)", borderRadius: 100, padding: "6px 16px", marginBottom: 32 }}>
          <span style={{ width: 8, height: 8, borderRadius: "50%", background: "#BB00FF", display: "inline-block" }} />
          <span style={{ fontSize: 13, color: "#BB00FF", fontWeight: 600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
        </div>

        <h1 style={{ fontSize: 68, fontWeight: 900, lineHeight: 1.05, letterSpacing: "-2px", marginBottom: 28, maxWidth: 780 }}>
          Make your Joomla site{" "}
          <span style={{ background: "linear-gradient(135deg, #1A3FF0, #BB00FF)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>
            visible to AI search
          </span>
        </h1>

        <p style={{ fontSize: 20, color: "#94a3b8", lineHeight: 1.7, maxWidth: 620, marginBottom: 48 }}>
          AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
        </p>

        <div style={{ display: "flex", gap: 16, marginBottom: 64 }}>
          <a style={{ background: "linear-gradient(135deg, #1A3FF0, #BB00FF)", color: "#fff", fontSize: 16, fontWeight: 700, padding: "16px 32px", borderRadius: 10, textDecoration: "none", boxShadow: "0 4px 28px rgba(26,63,240,0.4)" }}>
            Buy Developer — €119
          </a>
          <a style={{ background: "rgba(255,255,255,0.05)", border: "1px solid rgba(255,255,255,0.12)", color: "#e2e8f0", fontSize: 16, fontWeight: 600, padding: "16px 28px", borderRadius: 10, textDecoration: "none" }}>
            View all features ↓
          </a>
        </div>

        <div style={{ display: "flex", gap: 40 }}>
          {[
            { n: "Schema.org", d: "JSON-LD structured data" },
            { n: "llms.txt", d: "AI crawler permission file" },
            { n: "IndexNow", d: "Instant URL submission" },
            { n: "11 languages", d: "Full multilingual support" },
          ].map(f => (
            <div key={f.n} style={{ display: "flex", alignItems: "center", gap: 10 }}>
              <span style={{ background: "linear-gradient(135deg, #1A3FF0, #BB00FF)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent", fontSize: 18, fontWeight: 900 }}>✓</span>
              <div>
                <div style={{ fontSize: 14, fontWeight: 700, color: "#e2e8f0" }}>{f.n}</div>
                <div style={{ fontSize: 12, color: "#64748b" }}>{f.d}</div>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "0 48px 80px" }}>
        <div style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.07)", borderRadius: 16, padding: "40px 48px", display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 32, textAlign: "center" }}>
          {[
            { v: "20+", l: "Schema.org types" },
            { v: "25+", l: "AI crawler rules" },
            { v: "11", l: "Language packs" },
            { v: "5 min", l: "Setup time" },
          ].map(s => (
            <div key={s.l}>
              <div style={{ fontSize: 40, fontWeight: 900, background: "linear-gradient(135deg, #1A3FF0, #BB00FF)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>{s.v}</div>
              <div style={{ fontSize: 14, color: "#64748b", marginTop: 4 }}>{s.l}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
