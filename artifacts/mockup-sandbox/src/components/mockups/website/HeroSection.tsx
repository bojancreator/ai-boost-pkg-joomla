import logoSrc from "@/assets/logo.png";
import videoSrc from "@/assets/hero-video.mp4";

const GUMROAD = {
  starter:   "https://aiboostnow.gumroad.com/l/joomlaboost-starter",
  developer: "https://aiboostnow.gumroad.com/l/joomlaboost",
  agency:    "https://aiboostnow.gumroad.com/l/joomlaboost-agency",
};

export function HeroSection() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#FFFFFF", minHeight: "100vh", color: "#0C0B1D" }}>

      {/* NAV */}
      <nav style={{ display: "flex", alignItems: "center", justifyContent: "space-between", padding: "0 48px", height: "88px", borderBottom: "1px solid #E8E4F4" }}>
        <img src={logoSrc} style={{ height: 72, width: "auto", display: "block" }} alt="AI Boost" />
        <div style={{ display: "flex", alignItems: "center", gap: "32px" }}>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Features</a>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Docs</a>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none" }}>Pricing</a>
          <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" style={{ background: "#7B4FFF", color: "#fff", fontSize: 14, fontWeight: 600, padding: "11px 22px", borderRadius: 8, textDecoration: "none" }}>
            Get AI Boost →
          </a>
        </div>
      </nav>

      {/* HERO — split layout */}
      <div style={{ maxWidth: 1200, margin: "0 auto", padding: "80px 48px 72px", display: "flex", alignItems: "center", gap: 64 }}>

        {/* LEFT — text */}
        <div style={{ flex: "0 0 50%" }}>
          <div style={{ display: "inline-flex", alignItems: "center", gap: 8, background: "#F3F0FF", border: "1px solid #D4C9FF", borderRadius: 100, padding: "6px 16px", marginBottom: 32 }}>
            <span style={{ width: 7, height: 7, borderRadius: "50%", background: "#7B4FFF", display: "inline-block" }} />
            <span style={{ fontSize: 13, color: "#7B4FFF", fontWeight: 600 }}>Joomla 4 · 5 · 6 — PHP 8.1 – 8.5</span>
          </div>

          <h1 style={{ fontSize: 58, fontWeight: 900, lineHeight: 1.06, letterSpacing: "-2px", marginBottom: 24, color: "#0C0B1D" }}>
            Make your Joomla site{" "}
            <span style={{ color: "#7B4FFF" }}>visible to AI search</span>
          </h1>

          <p style={{ fontSize: 18, color: "#5A5A7A", lineHeight: 1.7, marginBottom: 40 }}>
            AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — so ChatGPT, Perplexity, and Google AI Overview recommend your site. Install in 5 minutes. No coding.
          </p>

          <div style={{ display: "flex", gap: 14, marginBottom: 48 }}>
            <a href={GUMROAD.developer} target="_blank" rel="noopener noreferrer" style={{ background: "#7B4FFF", color: "#fff", fontSize: 16, fontWeight: 700, padding: "15px 28px", borderRadius: 10, textDecoration: "none", boxShadow: "0 4px 20px rgba(123,79,255,0.3)" }}>
              Buy Developer — €119
            </a>
            <a href="#features" style={{ background: "transparent", border: "1.5px solid #D4C9FF", color: "#5A5A7A", fontSize: 16, fontWeight: 600, padding: "15px 24px", borderRadius: 10, textDecoration: "none" }}>
              View all features ↓
            </a>
          </div>

          <div style={{ display: "flex", flexWrap: "wrap", gap: "16px 32px" }}>
            {[
              { n: "Schema.org", d: "JSON-LD" },
              { n: "llms.txt", d: "AI crawlers" },
              { n: "IndexNow", d: "Instant submit" },
              { n: "11 languages", d: "Multilingual" },
            ].map(f => (
              <div key={f.n} style={{ display: "flex", alignItems: "center", gap: 8 }}>
                <span style={{ color: "#7B4FFF", fontSize: 15, fontWeight: 900 }}>✓</span>
                <div>
                  <span style={{ fontSize: 14, fontWeight: 700, color: "#0C0B1D" }}>{f.n}</span>
                  <span style={{ fontSize: 13, color: "#9090B0", marginLeft: 4 }}>{f.d}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* RIGHT — video */}
        <div style={{ flex: "0 0 calc(50% - 32px)", position: "relative" }}>
          {/* Browser chrome frame */}
          <div style={{ background: "#F8F7FF", borderRadius: 16, border: "1.5px solid #E8E4F4", overflow: "hidden", boxShadow: "0 24px 64px rgba(123,79,255,0.15), 0 4px 16px rgba(0,0,0,0.08)" }}>
            {/* Browser top bar */}
            <div style={{ background: "#F0ECF8", padding: "10px 16px", display: "flex", alignItems: "center", gap: 8, borderBottom: "1px solid #E8E4F4" }}>
              <span style={{ width: 10, height: 10, borderRadius: "50%", background: "#FF6B6B", display: "inline-block" }} />
              <span style={{ width: 10, height: 10, borderRadius: "50%", background: "#FFD93D", display: "inline-block" }} />
              <span style={{ width: 10, height: 10, borderRadius: "50%", background: "#6BCB77", display: "inline-block" }} />
              <div style={{ flex: 1, background: "#fff", borderRadius: 6, padding: "4px 12px", fontSize: 11, color: "#9090B0", marginLeft: 8, border: "1px solid #E8E4F4" }}>
                aiboostnow.com
              </div>
            </div>
            {/* Video */}
            <video
              src={videoSrc}
              autoPlay
              muted
              loop
              playsInline
              style={{ width: "100%", display: "block" }}
            />
          </div>
        </div>

      </div>

      {/* Stats bar */}
      <div style={{ maxWidth: 1200, margin: "0 auto", padding: "0 48px 80px" }}>
        <div style={{ background: "#F3F0FF", border: "1px solid #E0D8FF", borderRadius: 16, padding: "32px 48px", display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 32, textAlign: "center" }}>
          {[
            { v: "20+", l: "Schema.org types" },
            { v: "25+", l: "AI crawler rules" },
            { v: "11", l: "Language packs" },
            { v: "5 min", l: "Setup time" },
          ].map(s => (
            <div key={s.l}>
              <div style={{ fontSize: 38, fontWeight: 900, color: "#7B4FFF" }}>{s.v}</div>
              <div style={{ fontSize: 13, color: "#9090B0", marginTop: 4 }}>{s.l}</div>
            </div>
          ))}
        </div>
      </div>

    </div>
  );
}
