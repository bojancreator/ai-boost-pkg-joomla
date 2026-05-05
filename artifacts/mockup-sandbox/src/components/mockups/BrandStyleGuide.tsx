export default function BrandStyleGuide() {

  // ─────────────────────────────────────────────────────────────
  // OPTION 1 — Classic Wordmark
  // "AI" regular slate · "Boost" extra-bold blue · "Now" regular slate
  // Feel: professional B2B SaaS, trustworthy, clear hierarchy
  // ─────────────────────────────────────────────────────────────
  const Logo1Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 228 56" width="228" height="56">
      <defs>
        <linearGradient id="g1L" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="2"  y="32" width="9" height="16" rx="4.5" fill="url(#g1L)" />
      <rect x="15" y="22" width="9" height="26" rx="4.5" fill="url(#g1L)" />
      <rect x="28" y="10" width="9" height="38" rx="4.5" fill="url(#g1L)" />
      <text x="50" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="23" fontWeight="400" fill="#1E293B">AI</text>
      <text x="77" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="23" fontWeight="800" letterSpacing="-0.4" fill="#2563EB"> Boost</text>
      <text x="174" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="23" fontWeight="400" fill="#1E293B"> Now</text>
    </svg>
  );
  const Logo1Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 228 56" width="228" height="56">
      <defs>
        <linearGradient id="g1D" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <rect x="2"  y="32" width="9" height="16" rx="4.5" fill="url(#g1D)" />
      <rect x="15" y="22" width="9" height="26" rx="4.5" fill="url(#g1D)" />
      <rect x="28" y="10" width="9" height="38" rx="4.5" fill="url(#g1D)" />
      <text x="50" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="23" fontWeight="400" fill="#F1F5F9">AI</text>
      <text x="77" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="23" fontWeight="800" letterSpacing="-0.4" fill="#60A5FA"> Boost</text>
      <text x="174" y="37" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="23" fontWeight="400" fill="#F1F5F9"> Now</text>
    </svg>
  );

  // ─────────────────────────────────────────────────────────────
  // OPTION 2 — Gradient Wordmark
  // "aiboostnow" single lowercase word with full gradient fill
  // Feel: modern tech/SaaS, matches domain, youthful, confident
  // ─────────────────────────────────────────────────────────────
  const Logo2Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 248 56" width="248" height="56">
      <defs>
        <linearGradient id="g2L" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
        <linearGradient id="g2Li" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="2"  y="32" width="9" height="16" rx="4.5" fill="url(#g2Li)" />
      <rect x="15" y="22" width="9" height="26" rx="4.5" fill="url(#g2Li)" />
      <rect x="28" y="10" width="9" height="38" rx="4.5" fill="url(#g2Li)" />
      <text x="50" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="700" letterSpacing="-0.6" fill="url(#g2L)">aiboostnow</text>
    </svg>
  );
  const Logo2Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 248 56" width="248" height="56">
      <defs>
        <linearGradient id="g2D" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
        <linearGradient id="g2Di" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <rect x="2"  y="32" width="9" height="16" rx="4.5" fill="url(#g2Di)" />
      <rect x="15" y="22" width="9" height="26" rx="4.5" fill="url(#g2Di)" />
      <rect x="28" y="10" width="9" height="38" rx="4.5" fill="url(#g2Di)" />
      <text x="50" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="700" letterSpacing="-0.6" fill="url(#g2D)">aiboostnow</text>
    </svg>
  );

  // ─────────────────────────────────────────────────────────────
  // OPTION 3 — Stacked Badge
  // Icon left · "AI BOOST" top line bold blue · "NOW" bottom cyan
  // Feel: compact, badge-quality, distinctive, works at small sizes
  // ─────────────────────────────────────────────────────────────
  const Logo3Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 178 52" width="178" height="52">
      <defs>
        <linearGradient id="g3L" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="2"  y="28" width="11" height="16" rx="5" fill="url(#g3L)" />
      <rect x="17" y="17" width="11" height="27" rx="5" fill="url(#g3L)" />
      <rect x="32" y="4"  width="11" height="40" rx="5" fill="url(#g3L)" />
      <line x1="56" y1="8" x2="56" y2="44" stroke="#E2E8F0" strokeWidth="1.5" />
      <text x="65" y="27" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="16" fontWeight="800" letterSpacing="1.5" fill="#2563EB">AI BOOST</text>
      <text x="65" y="44" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="13" fontWeight="600" letterSpacing="3" fill="#06B6D4">NOW</text>
    </svg>
  );
  const Logo3Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 178 52" width="178" height="52">
      <defs>
        <linearGradient id="g3D" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <rect x="2"  y="28" width="11" height="16" rx="5" fill="url(#g3D)" />
      <rect x="17" y="17" width="11" height="27" rx="5" fill="url(#g3D)" />
      <rect x="32" y="4"  width="11" height="40" rx="5" fill="url(#g3D)" />
      <line x1="56" y1="8" x2="56" y2="44" stroke="#334155" strokeWidth="1.5" />
      <text x="65" y="27" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="16" fontWeight="800" letterSpacing="1.5" fill="#60A5FA">AI BOOST</text>
      <text x="65" y="44" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="13" fontWeight="600" letterSpacing="3" fill="#22D3EE">NOW</text>
    </svg>
  );

  const LogoIcon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="56" height="56">
      <defs>
        <linearGradient id="gIcon" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="3"  y="27" width="12" height="18" rx="5" fill="url(#gIcon)" />
      <rect x="18" y="15" width="12" height="30" rx="5" fill="url(#gIcon)" />
      <rect x="33" y="3"  width="12" height="42" rx="5" fill="url(#gIcon)" />
    </svg>
  );

  const variants = [
    {
      id: "1",
      title: "Classic Wordmark",
      desc: "\"AI\" + \"Boost\" + \"Now\" — tri reči, jasna hijerarhija. Boost uvek bold plavo. Profesionalan, B2B, čitljiv na svim veličinama.",
      best: "Navigacija sajta, e-mail potpis, dokumentacija",
      light: <Logo1Light />,
      dark: <Logo1Dark />,
    },
    {
      id: "2",
      title: "Gradient Wordmark",
      desc: "\"aiboostnow\" — jedno malo slovo, ceo wordmark u gradient-u (cyan→plavo). Moderno, tech-forward, poklapa se sa domenom.",
      best: "Hero sekcija sajta, social media avatar, dark tema",
      light: <Logo2Light />,
      dark: <Logo2Dark />,
    },
    {
      id: "3",
      title: "Stacked Badge",
      desc: "Ikonica levo + vertikalna linija + \"AI BOOST\" / \"NOW\" — dva reda, spaced caps. Kompaktan, badge-like, snažan vizualni identitet.",
      best: "Favicon companion, plugin dashboard header, tiskani materijali",
      light: <Logo3Light />,
      dark: <Logo3Dark />,
    },
  ];

  return (
    <div style={{ fontFamily: "'Inter','Segoe UI',sans-serif", background: "#F8FAFC", minHeight: "100vh", color: "#1E293B" }}>
      <div style={{ maxWidth: 960, margin: "0 auto", padding: "48px 32px 80px" }}>

        {/* Header */}
        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", flexWrap: "wrap", gap: 16, marginBottom: 48, paddingBottom: 32, borderBottom: "2px solid #E2E8F0" }}>
          <div>
            <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 6 }}>AI Boost Now — Logo Exploration</p>
            <h1 style={{ fontSize: 28, fontWeight: 800, color: "#0F172A" }}>3 Logo Candidates</h1>
            <p style={{ fontSize: 14, color: "#64748B", marginTop: 4 }}>aiboostnow.com · May 2026</p>
          </div>
          <div style={{ background: "#EFF6FF", border: "1px solid #BFDBFE", borderRadius: 8, padding: "12px 16px", fontSize: 13, color: "#1D4ED8", maxWidth: 240 }}>
            Icon concept unchanged: three ascending signal bars = AI signals sent to search engines.
          </div>
        </div>

        {/* Variants */}
        {variants.map(v => (
          <div key={v.id} style={{ marginBottom: 52, paddingBottom: 52, borderBottom: "1px solid #E2E8F0" }}>
            <div style={{ display: "flex", alignItems: "baseline", gap: 12, marginBottom: 6 }}>
              <span style={{ fontSize: 13, fontWeight: 800, color: "#2563EB", letterSpacing: ".05em" }}>0{v.id}</span>
              <span style={{ fontSize: 20, fontWeight: 700, color: "#0F172A" }}>{v.title}</span>
            </div>
            <p style={{ fontSize: 14, color: "#475569", marginBottom: 4, maxWidth: 680 }}>{v.desc}</p>
            <p style={{ fontSize: 12, color: "#94A3B8", marginBottom: 20 }}>
              <strong style={{ color: "#64748B" }}>Best for:</strong> {v.best}
            </p>
            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 140px", gap: 12 }}>
              <LogoCard bg="#fff" border="1px solid #E2E8F0" label="Light background">
                {v.light}
              </LogoCard>
              <LogoCard bg="#0F172A" label="Dark background" labelColor="#475569">
                {v.dark}
              </LogoCard>
              <LogoCard bg="#F1F5F9" border="1px solid #E2E8F0" label="Icon">
                <LogoIcon />
              </LogoCard>
            </div>
          </div>
        ))}

        {/* Footer */}
        <div style={{ paddingTop: 8, fontSize: 12, color: "#94A3B8" }}>
          All three variants share the same icon. Pick a variant → we finalize light/dark/icon SVGs and move to the website.
        </div>
      </div>
    </div>
  );
}

function LogoCard({ bg, border, label, labelColor, children }: {
  bg: string; border?: string; label: string; labelColor?: string; children: React.ReactNode
}) {
  return (
    <div style={{ background: bg, border: border || "none", borderRadius: 12, padding: "28px 20px", display: "flex", flexDirection: "column", alignItems: "center", gap: 14 }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center" }}>{children}</div>
      <span style={{ fontSize: 10, fontWeight: 600, letterSpacing: ".08em", textTransform: "uppercase", color: labelColor || "#94A3B8" }}>{label}</span>
    </div>
  );
}
