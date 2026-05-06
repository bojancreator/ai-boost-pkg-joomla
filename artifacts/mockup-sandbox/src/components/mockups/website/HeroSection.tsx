export function HeroSection() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#0F172A", minHeight: "100vh", color: "#fff" }}>
      <nav style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 48px", height: "72px", borderBottom: "1px solid rgba(255,255,255,0.08)" }}>
        <div>
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 56" width="182" height="40">
            <defs><linearGradient id="hn_g" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" /></linearGradient></defs>
            <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#hn_g)" />
            <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#hn_g)" />
            <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#hn_g)" />
            <text x="54" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="400" letterSpacing="-0.3" fill="#F1F5F9">Joomla</text>
            <text x="130" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="700" letterSpacing="-0.3" fill="#60A5FA">Boost</text>
          </svg>
        </div>
        <div style={{ display: "flex", alignItems: "center", gap: "32px" }}>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Features</a>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Docs</a>
          <a style={{ color: "#94a3b8", fontSize: 14, fontWeight: 500, textDecoration: "none" }}>Pricing</a>
          <a style={{ background: "linear-gradient(135deg, #2563EB, #06B6D4)", color: "#fff", fontSize: 14, fontWeight: 600, padding: "10px 20px", borderRadius: 8, textDecoration: "none" }}>Get JoomlaBoost →</a>
        </div>
      </nav>

      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "100px 48px 80px" }}>
        <div style={{ display: "inline-flex", alignItems: "center", gap: 8, background: "rgba(6,182,212,0.1)", border: "1px solid rgba(6,182,212,0.25)", borderRadius: 100, padding: "6px 16px", marginBottom: 32 }}>
          <span style={{ width: 8, height: 8, borderRadius: "50%", background: "#06B6D4", display: "inline-block" }} />
          <span style={{ fontSize: 13, color: "#06B6D4", fontWeight: 600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
        </div>

        <h1 style={{ fontSize: 68, fontWeight: 900, lineHeight: 1.05, letterSpacing: "-2px", marginBottom: 28, maxWidth: 780 }}>
          Make your Joomla site{" "}
          <span style={{ background: "linear-gradient(135deg, #2563EB, #06B6D4)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>
            visible to AI search
          </span>
        </h1>

        <p style={{ fontSize: 20, color: "#94a3b8", lineHeight: 1.7, maxWidth: 620, marginBottom: 48 }}>
          JoomlaBoost generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
        </p>

        <div style={{ display: "flex", gap: 16, marginBottom: 64 }}>
          <a style={{ background: "linear-gradient(135deg, #2563EB, #06B6D4)", color: "#fff", fontSize: 16, fontWeight: 700, padding: "16px 32px", borderRadius: 10, textDecoration: "none", boxShadow: "0 4px 24px rgba(37,99,235,0.35)" }}>
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
              <span style={{ color: "#06B6D4", fontSize: 18 }}>✓</span>
              <div>
                <div style={{ fontSize: 14, fontWeight: 700, color: "#e2e8f0" }}>{f.n}</div>
                <div style={{ fontSize: 12, color: "#64748b" }}>{f.d}</div>
              </div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ maxWidth: 1100, margin: "0 auto", padding: "0 48px 80px" }}>
        <div style={{ background: "rgba(255,255,255,0.03)", border: "1px solid rgba(255,255,255,0.08)", borderRadius: 16, padding: "40px 48px", display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 32, textAlign: "center" }}>
          {[
            { v: "20+", l: "Schema.org types" },
            { v: "25+", l: "AI crawler rules" },
            { v: "11", l: "Language packs" },
            { v: "5 min", l: "Setup time" },
          ].map(s => (
            <div key={s.l}>
              <div style={{ fontSize: 40, fontWeight: 900, background: "linear-gradient(135deg, #2563EB, #06B6D4)", WebkitBackgroundClip: "text", WebkitTextFillColor: "transparent" }}>{s.v}</div>
              <div style={{ fontSize: 14, color: "#64748b", marginTop: 4 }}>{s.l}</div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
