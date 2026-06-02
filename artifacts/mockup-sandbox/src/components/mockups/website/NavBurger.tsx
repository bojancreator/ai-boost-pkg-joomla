import { logoDataUrl as logoSrc } from "@/assets/logo-data";
import { useState } from "react";

export function NavBurger() {
  const [open, setOpen] = useState(false);

  return (
    <div style={{ fontFamily: "'Inter', system-ui, sans-serif", background: "#fff" }}>
      <nav style={{
        display: "grid",
        gridTemplateColumns: "1fr auto 1fr",
        alignItems: "center",
        padding: "0 64px",
        height: "88px",
        borderBottom: "1px solid #E8E4F4",
        position: "relative",
      }}>
        {/* Left: empty placeholder */}
        <div />

        {/* Center: logo */}
        <img src={logoSrc} style={{ height: 75, width: "auto", display: "block" }} alt="AI Boost" />

        {/* Right: hamburger */}
        <div style={{ display: "flex", justifyContent: "flex-end" }}>
          <button
            onClick={() => setOpen(o => !o)}
            style={{
              background: "none",
              border: "none",
              cursor: "pointer",
              padding: 8,
              display: "flex",
              flexDirection: "column",
              gap: 5,
              alignItems: "flex-end",
            }}
            aria-label="Menu"
          >
            <span style={{ display: "block", width: 24, height: 2, background: "#0C0B1D", borderRadius: 2, transition: "all .2s", transform: open ? "rotate(45deg) translate(5px,5px)" : "none" }} />
            <span style={{ display: "block", width: 18, height: 2, background: "#0C0B1D", borderRadius: 2, opacity: open ? 0 : 1, transition: "all .2s" }} />
            <span style={{ display: "block", width: 24, height: 2, background: "#0C0B1D", borderRadius: 2, transition: "all .2s", transform: open ? "rotate(-45deg) translate(5px,-5px)" : "none" }} />
          </button>
        </div>
      </nav>

      {/* Dropdown menu */}
      {open && (
        <div style={{
          background: "#fff",
          borderBottom: "1px solid #E8E4F4",
          boxShadow: "0 8px 32px rgba(0,0,0,.08)",
          padding: "16px 64px 24px",
          display: "flex",
          flexDirection: "column",
          gap: 0,
        }}>
          {["Features", "Pricing", "Blog", "FAQ"].map(item => (
            <a key={item} style={{
              color: "#0C0B1D",
              fontSize: 16,
              fontWeight: 500,
              textDecoration: "none",
              padding: "12px 0",
              borderBottom: "1px solid #F0EDF8",
              cursor: "pointer",
            }}>{item}</a>
          ))}
          <a style={{
            marginTop: 16,
            background: "linear-gradient(135deg, #5B3FE4 0%, #8B5CF6 100%)",
            color: "#fff",
            padding: "12px 24px",
            borderRadius: 8,
            fontWeight: 600,
            fontSize: 15,
            textDecoration: "none",
            textAlign: "center",
            cursor: "pointer",
          }}>Get AI Boost →</a>
        </div>
      )}

      {/* Label */}
      <div style={{ padding: "24px 64px", background: "#FAF9FE", borderBottom: "1px solid #E8E4F4" }}>
        <p style={{ margin: 0, color: "#5A5A7A", fontSize: 13 }}>
          <strong>Varijanta B — Hamburger Nav:</strong> logo centar · hamburger meni desno (klikni ☰)
        </p>
      </div>
    </div>
  );
}
