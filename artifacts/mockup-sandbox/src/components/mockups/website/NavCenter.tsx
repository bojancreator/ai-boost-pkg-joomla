import { logoDataUrl as logoSrc } from "@/assets/logo-data";

export function NavCenter() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#fff" }}>
      <div style={{ borderBottom: "1px solid #E8E4F4" }}>
        <div style={{
          maxWidth: 1200,
          margin: "0 auto",
          padding: "0 24px",
          height: 80,
          display: "grid",
          gridTemplateColumns: "1fr auto 1fr",
          alignItems: "center",
        }}>
          {/* Left: Logo */}
          <div style={{ display: "flex", alignItems: "center", justifyContent: "flex-start" }}>
            <img src={logoSrc} style={{ height: 75, width: "auto", display: "block" }} alt="AI Boost" />
          </div>

          {/* Center: Nav links — geometrically centred */}
          <div style={{ display: "flex", alignItems: "center", gap: 36 }}>
            <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", whiteSpace: "nowrap", cursor: "pointer" }}>Features</a>
            <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", whiteSpace: "nowrap", cursor: "pointer" }}>Pricing</a>
            <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", whiteSpace: "nowrap", cursor: "pointer" }}>Docs</a>
            <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", whiteSpace: "nowrap", cursor: "pointer" }}>Blog</a>
            <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", whiteSpace: "nowrap", cursor: "pointer" }}>FAQ</a>
          </div>

          {/* Right: CTA */}
          <div style={{ display: "flex", alignItems: "center", justifyContent: "flex-end" }}>
            <a href="#" style={{
              background: "linear-gradient(135deg, #5B3FE4 0%, #8B5CF6 100%)",
              color: "#fff",
              padding: "10px 22px",
              borderRadius: 8,
              fontWeight: 600,
              fontSize: 14,
              textDecoration: "none",
              whiteSpace: "nowrap",
            }}>
              Get AI Boost →
            </a>
          </div>
        </div>
      </div>

      {/* Label */}
      <div style={{ maxWidth: 1200, margin: "0 auto", padding: "16px 24px" }}>
        <p style={{ margin: 0, color: "#5A5A7A", fontSize: 13 }}>
          <strong>Header:</strong> Logo lijevo · Meni centar · Get AI Boost → desno · max 1200px
        </p>
      </div>
    </div>
  );
}
