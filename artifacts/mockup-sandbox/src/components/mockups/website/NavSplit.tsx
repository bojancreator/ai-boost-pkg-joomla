import { logoDataUrl as logoSrc } from "@/assets/logo-data";

export function NavSplit() {
  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#fff" }}>
      <nav style={{
        display: "grid",
        gridTemplateColumns: "1fr auto 1fr",
        alignItems: "center",
        padding: "0 64px",
        height: "88px",
        borderBottom: "1px solid #E8E4F4",
      }}>
        {/* Left: nav links */}
        <div style={{ display: "flex", alignItems: "center", gap: 32 }}>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", cursor: "pointer" }}>Features</a>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", cursor: "pointer" }}>Pricing</a>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", cursor: "pointer" }}>Blog</a>
          <a style={{ color: "#5A5A7A", fontSize: 15, fontWeight: 500, textDecoration: "none", cursor: "pointer" }}>FAQ</a>
        </div>

        {/* Center: logo */}
        <img src={logoSrc} style={{ height: 75, width: "auto", display: "block" }} alt="AI Boost" />

        {/* Right: CTA button */}
        <div style={{ display: "flex", justifyContent: "flex-end" }}>
          <a
            href="#"
            style={{
              background: "linear-gradient(135deg, #5B3FE4 0%, #8B5CF6 100%)",
              color: "#fff",
              padding: "10px 22px",
              borderRadius: 8,
              fontWeight: 600,
              fontSize: 14,
              textDecoration: "none",
              whiteSpace: "nowrap",
            }}
          >
            Get AI Boost →
          </a>
        </div>
      </nav>

      {/* Label */}
      <div style={{ padding: "24px 64px", background: "#FAF9FE", borderBottom: "1px solid #E8E4F4" }}>
        <p style={{ margin: 0, color: "#5A5A7A", fontSize: 13 }}>
          <strong>Varijanta A — Split Nav:</strong> linkovi lijevo · logo centar · CTA desno
        </p>
      </div>
    </div>
  );
}
