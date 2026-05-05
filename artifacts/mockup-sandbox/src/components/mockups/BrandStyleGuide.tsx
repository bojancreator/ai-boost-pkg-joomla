export default function BrandStyleGuide() {
  const LogoLight = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 56" width="220" height="48">
      <defs>
        <linearGradient id="gL" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" />
          <stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#gL)" />
      <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#gL)" />
      <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#gL)" />
      <text x="54" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="400" letterSpacing="-0.3" fill="#1E293B">Joomla</text>
      <text x="130" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="700" letterSpacing="-0.3" fill="#2563EB">Boost</text>
    </svg>
  );

  const LogoDark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 260 56" width="220" height="48">
      <defs>
        <linearGradient id="gD" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" />
          <stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#gD)" />
      <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#gD)" />
      <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#gD)" />
      <text x="54" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="400" letterSpacing="-0.3" fill="#F1F5F9">Joomla</text>
      <text x="130" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="700" letterSpacing="-0.3" fill="#60A5FA">Boost</text>
    </svg>
  );

  const LogoIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="56" height="56">
      <defs>
        <linearGradient id="gI" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" />
          <stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="3"  y="27" width="12" height="18" rx="5" fill="url(#gI)" />
      <rect x="18" y="15" width="12" height="30" rx="5" fill="url(#gI)" />
      <rect x="33" y="3"  width="12" height="42" rx="5" fill="url(#gI)" />
    </svg>
  );

  const swatches = [
    { name: "Brand Blue",    hex: "#2563EB", role: "Primary / CTA / links",    text: "#fff" },
    { name: "Signal Cyan",   hex: "#06B6D4", role: "Accent / highlights",       text: "#fff" },
    { name: "Deep Blue",     hex: "#1D4ED8", role: "Hover states / depth",      text: "#fff" },
    { name: "Blue 400",      hex: "#60A5FA", role: "Dark-mode primary",         text: "#1E293B" },
    { name: "Slate 900",     hex: "#0F172A", role: "Dark backgrounds",          text: "#fff" },
    { name: "Slate 800",     hex: "#1E293B", role: "Body text (light bg)",      text: "#fff" },
    { name: "Slate 600",     hex: "#475569", role: "Secondary text",            text: "#fff" },
    { name: "Slate 200",     hex: "#E2E8F0", role: "Borders / dividers",        text: "#1E293B" },
    { name: "Slate 50",      hex: "#F8FAFC", role: "Page backgrounds",          text: "#1E293B", border: true },
    { name: "Success",       hex: "#10B981", role: "Status / positive",         text: "#fff" },
    { name: "Warning",       hex: "#F59E0B", role: "Alerts / caution",          text: "#fff" },
    { name: "Error",         hex: "#EF4444", role: "Errors / destructive",      text: "#fff" },
  ];

  return (
    <div style={{ fontFamily: "'Inter','Segoe UI',sans-serif", background: "#F8FAFC", minHeight: "100vh", color: "#1E293B" }}>
      <div style={{ maxWidth: 960, margin: "0 auto", padding: "48px 32px 80px" }}>

        {/* Header */}
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", flexWrap: "wrap", gap: 16, marginBottom: 48, paddingBottom: 32, borderBottom: "2px solid #E2E8F0" }}>
          <div>
            <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 6 }}>Brand Style Guide</p>
            <h1 style={{ fontSize: 32, fontWeight: 800, color: "#0F172A", lineHeight: 1.1 }}>
              Joomla<span style={{ color: "#2563EB" }}>Boost</span>
            </h1>
          </div>
          <div style={{ fontSize: 13, color: "#94A3B8", textAlign: "right" }}>
            <strong style={{ color: "#475569" }}>Version 1.0</strong><br />
            May 2026<br />
            by AI Boost Now · aiboostnow.com
          </div>
        </div>

        {/* 01 Logo */}
        <section style={{ marginBottom: 52 }}>
          <SectionTitle number="01" label="Logo" />
          <div style={{ display: "grid", gridTemplateColumns: "repeat(3,1fr)", gap: 16 }}>
            <LogoCard bg="#fff" border="1px solid #E2E8F0" label="Light background">
              <LogoLight />
            </LogoCard>
            <LogoCard bg="#0F172A" label="Dark background" labelColor="#64748B">
              <LogoDark />
            </LogoCard>
            <LogoCard bg="#F1F5F9" border="1px solid #E2E8F0" label="Icon / favicon">
              <LogoIcon />
            </LogoCard>
          </div>
          <p style={{ marginTop: 14, fontSize: 13, color: "#64748B" }}>
            The icon — three ascending signal bars — represents structured data signals (Schema.org, OpenGraph, sitemap) sent to AI search engines.
            "Joomla" is set at regular weight; "<strong>Boost</strong>" is bold to anchor the brand promise. Minimum logo width: <strong>120px</strong>.
          </p>
        </section>

        {/* 02 Colors */}
        <section style={{ marginBottom: 52 }}>
          <SectionTitle number="02" label="Color Palette" />
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill,minmax(140px,1fr))", gap: 12 }}>
            {swatches.map(s => (
              <div key={s.hex} style={{ borderRadius: 8, overflow: "hidden", border: s.border ? "1px solid #CBD5E1" : "1px solid rgba(0,0,0,.06)" }}>
                <div style={{ height: 56, background: s.hex }} />
                <div style={{ padding: "10px 12px", background: "#fff" }}>
                  <div style={{ fontSize: 12, fontWeight: 700, color: "#1E293B" }}>{s.name}</div>
                  <div style={{ fontSize: 11, fontFamily: "monospace", color: "#64748B" }}>{s.hex}</div>
                  <div style={{ fontSize: 10, color: "#94A3B8", marginTop: 2 }}>{s.role}</div>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* 03 Typography */}
        <section style={{ marginBottom: 52 }}>
          <SectionTitle number="03" label="Typography" />
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
            <div style={{ background: "#fff", border: "1px solid #E2E8F0", borderRadius: 12, padding: "28px 24px" }}>
              <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 12 }}>Display &amp; Headings</div>
              <div style={{ fontSize: 26, fontWeight: 800, color: "#0F172A", lineHeight: 1.2, marginBottom: 8 }}>The AI-era SEO plugin for Joomla.</div>
              <div style={{ marginTop: 16, paddingTop: 16, borderTop: "1px solid #F1F5F9" }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: "#1E293B" }}>Plus Jakarta Sans</div>
                <div style={{ fontSize: 12, color: "#94A3B8" }}>Google Fonts · Free · SIL OFL</div>
                <div style={{ marginTop: 12, display: "flex", flexDirection: "column", gap: 4 }}>
                  {[["H1","48px / 800 / -1px"],["H2","36px / 700 / -0.5px"],["H3","24px / 700 / -0.3px"],["H4","18px / 600 / 0"]].map(([n,v]) => (
                    <div key={n} style={{ display: "flex", justifyContent: "space-between", fontSize: 12, color: "#64748B" }}>
                      <span style={{ fontWeight: 600, color: "#475569" }}>{n}</span><span>{v}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
            <div style={{ background: "#fff", border: "1px solid #E2E8F0", borderRadius: 12, padding: "28px 24px" }}>
              <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 12 }}>Body &amp; UI</div>
              <div style={{ fontSize: 15, color: "#475569", lineHeight: 1.7 }}>
                JoomlaBoost automatically generates Schema.org structured data, OpenGraph tags, and llms.txt — so AI engines can find, understand, and recommend your clients' sites.
              </div>
              <div style={{ marginTop: 16, paddingTop: 16, borderTop: "1px solid #F1F5F9" }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: "#1E293B" }}>Inter</div>
                <div style={{ fontSize: 12, color: "#94A3B8" }}>Google Fonts · Free · SIL OFL</div>
                <div style={{ marginTop: 12, display: "flex", flexDirection: "column", gap: 4 }}>
                  {[["Body L","16px / 400 / 1.7"],["Body M","14px / 400 / 1.65"],["Caption","12px / 500 / 1.5"],["Code","13px / Fira Code / mono"]].map(([n,v]) => (
                    <div key={n} style={{ display: "flex", justifyContent: "space-between", fontSize: 12, color: "#64748B" }}>
                      <span style={{ fontWeight: 600, color: "#475569" }}>{n}</span><span>{v}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* 04 Voice */}
        <section style={{ marginBottom: 52 }}>
          <SectionTitle number="04" label="Voice & Tagline" />
          <div style={{ background: "linear-gradient(135deg,#0F172A 0%,#1e3a6e 100%)", borderRadius: 12, padding: "32px 36px", color: "#fff" }}>
            <div style={{ fontSize: 22, fontWeight: 700, marginBottom: 8 }}>"The AI-era SEO plugin for Joomla."</div>
            <div style={{ fontSize: 14, color: "#94A3B8" }}>Official plugin tagline — use verbatim in JED listing, website hero, and README.</div>
            <div style={{ marginTop: 20, display: "flex", flexWrap: "wrap", gap: 8 }}>
              {["Reliable","Sharp","Modern","Not startup-y","Feels solid","Professional"].map(b => (
                <span key={b} style={{ background: "rgba(255,255,255,.08)", border: "1px solid rgba(255,255,255,.12)", borderRadius: 999, padding: "4px 14px", fontSize: 12, color: "#CBD5E1" }}>{b}</span>
              ))}
            </div>
          </div>
          <p style={{ marginTop: 14, fontSize: 13, color: "#64748B" }}>
            Write like a senior developer explaining a tool to a peer — confident, specific, no fluff.
            Avoid: "revolutionary", "game-changer". Prefer: "generates", "handles", "outputs", "saves X hours per project".
          </p>
        </section>

        {/* 05 Rules */}
        <section style={{ marginBottom: 52 }}>
          <SectionTitle number="05" label="Usage Rules" />
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 16 }}>
            <div style={{ background: "#ECFDF5", border: "1px solid #6EE7B7", borderRadius: 12, padding: "20px 22px", color: "#064E3B", fontSize: 13.5, lineHeight: 1.6 }}>
              <strong style={{ display: "block", fontSize: 11, letterSpacing: ".08em", textTransform: "uppercase", color: "#059669", marginBottom: 8 }}>✓ Do</strong>
              <ul style={{ paddingLeft: 16 }}>
                {["Use the SVG logo — never rasterize below 2×","Maintain clear space equal to icon height on all sides","Use light logo on white / light-gray backgrounds","Use dark logo on backgrounds #1E293B or darker","Scale proportionally — never stretch or squish","Use Brand Blue (#2563EB) for primary CTA buttons","Pair Plus Jakarta Sans headings with Inter body text"].map(t => (
                  <li key={t} style={{ marginBottom: 4 }}>{t}</li>
                ))}
              </ul>
            </div>
            <div style={{ background: "#FEF2F2", border: "1px solid #FCA5A5", borderRadius: 12, padding: "20px 22px", color: "#7F1D1D", fontSize: 13.5, lineHeight: 1.6 }}>
              <strong style={{ display: "block", fontSize: 11, letterSpacing: ".08em", textTransform: "uppercase", color: "#DC2626", marginBottom: 8 }}>✗ Don't</strong>
              <ul style={{ paddingLeft: 16 }}>
                {["Don't place the logo on busy photographs without a backdrop","Don't recolor the icon outside the defined palette","Don't use the icon alone where brand name isn't established","Don't mix with other wordmark fonts or change letter-spacing","Don't use light logo on dark backgrounds (use dark variant)","Don't use Signal Cyan (#06B6D4) as a standalone CTA — contrast too low"].map(t => (
                  <li key={t} style={{ marginBottom: 4 }}>{t}</li>
                ))}
              </ul>
            </div>
          </div>
        </section>

        {/* Footer */}
        <div style={{ paddingTop: 24, borderTop: "1px solid #E2E8F0", display: "flex", justifyContent: "space-between", flexWrap: "wrap", gap: 8, fontSize: 12, color: "#94A3B8" }}>
          <span>JoomlaBoost Brand Style Guide v1.0 — May 2026</span>
          <span>AI Boost Now · aiboostnow.com · Internal use</span>
        </div>
      </div>
    </div>
  );
}

function SectionTitle({ number, label }: { number: string; label: string }) {
  return (
    <div style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".12em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 20, paddingLeft: 12, borderLeft: "3px solid #2563EB" }}>
      {number} — {label}
    </div>
  );
}

function LogoCard({ bg, border, label, labelColor, children }: { bg: string; border?: string; label: string; labelColor?: string; children: React.ReactNode }) {
  return (
    <div style={{ background: bg, border: border || "none", borderRadius: 12, padding: "32px 24px", display: "flex", flexDirection: "column", alignItems: "center", gap: 16 }}>
      <div style={{ display: "flex", alignItems: "center" }}>{children}</div>
      <span style={{ fontSize: 11, fontWeight: 600, letterSpacing: ".06em", textTransform: "uppercase", color: labelColor || "#94A3B8" }}>{label}</span>
    </div>
  );
}
