export default function BrandStyleGuide() {

  // VARIANT A: "AI Boost Now" — AI + Now slate, Boost bold blue
  const LogoA_Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 56" width="260" height="48">
      <defs>
        <linearGradient id="gAL" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" />
          <stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#gAL)" />
      <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#gAL)" />
      <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#gAL)" />
      <text x="52" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="400" letterSpacing="-0.3" fill="#1E293B">AI </text>
      <text x="82" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="800" letterSpacing="-0.5" fill="#2563EB">Boost</text>
      <text x="201" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="400" letterSpacing="-0.3" fill="#1E293B"> Now</text>
    </svg>
  );

  const LogoA_Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 56" width="260" height="48">
      <defs>
        <linearGradient id="gAD" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" />
          <stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#gAD)" />
      <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#gAD)" />
      <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#gAD)" />
      <text x="52" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="400" letterSpacing="-0.3" fill="#F1F5F9">AI </text>
      <text x="82" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="800" letterSpacing="-0.5" fill="#60A5FA">Boost</text>
      <text x="201" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="400" letterSpacing="-0.3" fill="#F1F5F9"> Now</text>
    </svg>
  );

  // VARIANT B: "aiboost now" — all lowercase, modern/tech, "now" in cyan
  const LogoB_Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 56" width="260" height="48">
      <defs>
        <linearGradient id="gBL" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" />
          <stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#gBL)" />
      <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#gBL)" />
      <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#gBL)" />
      <text x="52" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="700" letterSpacing="-0.8" fill="#1E293B">aiboost</text>
      <text x="198" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="700" letterSpacing="-0.8" fill="#06B6D4"> now</text>
    </svg>
  );

  const LogoB_Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 56" width="260" height="48">
      <defs>
        <linearGradient id="gBD" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" />
          <stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#gBD)" />
      <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#gBD)" />
      <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#gBD)" />
      <text x="52" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="700" letterSpacing="-0.8" fill="#F1F5F9">aiboost</text>
      <text x="198" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="700" letterSpacing="-0.8" fill="#22D3EE"> now</text>
    </svg>
  );

  // VARIANT C: "AI boost now" — AI uppercase bold blue, rest lowercase slate
  const LogoC_Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 56" width="260" height="48">
      <defs>
        <linearGradient id="gCL" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" />
          <stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#gCL)" />
      <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#gCL)" />
      <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#gCL)" />
      <text x="52" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="800" letterSpacing="-0.5" fill="#2563EB">AI</text>
      <text x="86" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="500" letterSpacing="-0.5" fill="#475569"> boost now</text>
    </svg>
  );

  const LogoC_Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 310 56" width="260" height="48">
      <defs>
        <linearGradient id="gCD" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" />
          <stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <rect x="4"  y="30" width="9" height="18" rx="4.5" fill="url(#gCD)" />
      <rect x="17" y="20" width="9" height="28" rx="4.5" fill="url(#gCD)" />
      <rect x="30" y="8"  width="9" height="40" rx="4.5" fill="url(#gCD)" />
      <text x="52" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="800" letterSpacing="-0.5" fill="#60A5FA">AI</text>
      <text x="86" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="500" letterSpacing="-0.5" fill="#94A3B8"> boost now</text>
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
    { name: "Brand Blue",    hex: "#2563EB", role: "Primary / CTA / links" },
    { name: "Signal Cyan",   hex: "#06B6D4", role: "Accent / highlights" },
    { name: "Deep Blue",     hex: "#1D4ED8", role: "Hover states / depth" },
    { name: "Blue 400",      hex: "#60A5FA", role: "Dark-mode primary" },
    { name: "Slate 900",     hex: "#0F172A", role: "Dark backgrounds" },
    { name: "Slate 800",     hex: "#1E293B", role: "Body text (light bg)" },
    { name: "Slate 600",     hex: "#475569", role: "Secondary text" },
    { name: "Slate 200",     hex: "#E2E8F0", role: "Borders / dividers" },
    { name: "Slate 50",      hex: "#F8FAFC", role: "Page backgrounds", border: true },
    { name: "Success",       hex: "#10B981", role: "Status / positive" },
    { name: "Warning",       hex: "#F59E0B", role: "Alerts / caution" },
    { name: "Error",         hex: "#EF4444", role: "Errors / destructive" },
  ];

  return (
    <div style={{ fontFamily: "'Inter','Segoe UI',sans-serif", background: "#F8FAFC", minHeight: "100vh", color: "#1E293B" }}>
      <div style={{ maxWidth: 1000, margin: "0 auto", padding: "48px 32px 80px" }}>

        {/* Header */}
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", flexWrap: "wrap", gap: 16, marginBottom: 48, paddingBottom: 32, borderBottom: "2px solid #E2E8F0" }}>
          <div>
            <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 6 }}>Brand Style Guide</p>
            <h1 style={{ fontSize: 32, fontWeight: 800, color: "#0F172A", lineHeight: 1.1 }}>
              AI <span style={{ color: "#2563EB" }}>Boost</span> Now
            </h1>
          </div>
          <div style={{ fontSize: 13, color: "#94A3B8", textAlign: "right" }}>
            <strong style={{ color: "#475569" }}>Version 1.0</strong><br />
            May 2026<br />
            aiboostnow.com
          </div>
        </div>

        {/* 01 Logo Variants */}
        <section style={{ marginBottom: 60 }}>
          <SectionTitle number="01" label="Logo — 3 Variants (pick one)" />

          {/* Variant A */}
          <div style={{ marginBottom: 32 }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: "#475569", marginBottom: 10, letterSpacing: ".04em" }}>
              VARIANT A — "AI <strong style={{color:"#2563EB"}}>Boost</strong> Now" &nbsp;
              <span style={{ fontWeight: 400, color: "#94A3B8" }}>Classic wordmark. "Boost" bold blue, AI + Now slate.</span>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <LogoCard bg="#fff" border="1px solid #E2E8F0" label="Light background"><LogoA_Light /></LogoCard>
              <LogoCard bg="#0F172A" label="Dark background" labelColor="#64748B"><LogoA_Dark /></LogoCard>
            </div>
          </div>

          {/* Variant B */}
          <div style={{ marginBottom: 32 }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: "#475569", marginBottom: 10, letterSpacing: ".04em" }}>
              VARIANT B — "<strong>aiboost</strong> <span style={{color:"#06B6D4"}}>now</span>" &nbsp;
              <span style={{ fontWeight: 400, color: "#94A3B8" }}>Lowercase, modern/tech. "aiboost" dark bold, "now" cyan.</span>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <LogoCard bg="#fff" border="1px solid #E2E8F0" label="Light background"><LogoB_Light /></LogoCard>
              <LogoCard bg="#0F172A" label="Dark background" labelColor="#64748B"><LogoB_Dark /></LogoCard>
            </div>
          </div>

          {/* Variant C */}
          <div style={{ marginBottom: 20 }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: "#475569", marginBottom: 10, letterSpacing: ".04em" }}>
              VARIANT C — "<span style={{color:"#2563EB", fontWeight:800}}>AI</span> boost now" &nbsp;
              <span style={{ fontWeight: 400, color: "#94A3B8" }}>AI uppercase bold blue, rest lowercase medium slate. Minimal.</span>
            </div>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 12 }}>
              <LogoCard bg="#fff" border="1px solid #E2E8F0" label="Light background"><LogoC_Light /></LogoCard>
              <LogoCard bg="#0F172A" label="Dark background" labelColor="#64748B"><LogoC_Dark /></LogoCard>
            </div>
          </div>

          {/* Icon */}
          <div style={{ marginBottom: 8 }}>
            <div style={{ fontSize: 13, fontWeight: 700, color: "#475569", marginBottom: 10 }}>ICON / FAVICON — same across all variants</div>
            <div style={{ display: "inline-block" }}>
              <LogoCard bg="#F1F5F9" border="1px solid #E2E8F0" label="Icon / favicon"><LogoIcon /></LogoCard>
            </div>
          </div>

          <p style={{ marginTop: 16, fontSize: 13, color: "#64748B" }}>
            Icon concept: three ascending rounded bars = signal strength + AI boost. Gradient cyan→blue. All three variants share the same icon.
            Minimum logo width: <strong>160px</strong>.
          </p>
        </section>

        {/* 02 Colors */}
        <section style={{ marginBottom: 52 }}>
          <SectionTitle number="02" label="Color Palette" />
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill,minmax(140px,1fr))", gap: 12 }}>
            {swatches.map((s: any) => (
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
                AI Boost Now automatically generates Schema.org structured data, OpenGraph tags, and llms.txt — so AI engines can find, understand, and recommend your clients' sites.
              </div>
              <div style={{ marginTop: 16, paddingTop: 16, borderTop: "1px solid #F1F5F9" }}>
                <div style={{ fontSize: 13, fontWeight: 700, color: "#1E293B" }}>Inter</div>
                <div style={{ fontSize: 12, color: "#94A3B8" }}>Google Fonts · Free · SIL OFL</div>
              </div>
            </div>
          </div>
        </section>

        {/* Footer */}
        <div style={{ paddingTop: 24, borderTop: "1px solid #E2E8F0", display: "flex", justifyContent: "space-between", flexWrap: "wrap", gap: 8, fontSize: 12, color: "#94A3B8" }}>
          <span>AI Boost Now — Brand Style Guide v1.0 — May 2026</span>
          <span>aiboostnow.com · Internal use</span>
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
