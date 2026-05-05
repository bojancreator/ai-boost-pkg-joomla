export default function BrandStyleGuide() {

  // ─────────────────────────────────────────────
  // VARIANT 1 — "AI" u rounded badge-u (square)
  // Ikonica = plavi kvadrat sa zaobljenim ćoškovima, "AI" beli bold
  // ─────────────────────────────────────────────
  const Logo1Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 56" width="240" height="56">
      <defs>
        <linearGradient id="g1l" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="2" y="4" width="48" height="48" rx="11" fill="url(#g1l)" />
      <text x="26" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="900" letterSpacing="-1.5" fill="white" textAnchor="middle">AI</text>
      <text x="62" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#0F172A">BOOST NOW</text>
    </svg>
  );
  const Logo1Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 56" width="240" height="56">
      <defs>
        <linearGradient id="g1d" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <rect x="2" y="4" width="48" height="48" rx="11" fill="url(#g1d)" />
      <text x="26" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="900" letterSpacing="-1.5" fill="#0F172A" textAnchor="middle">AI</text>
      <text x="62" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#F1F5F9">BOOST NOW</text>
    </svg>
  );
  const Logo1Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 56" width="64" height="64">
      <defs>
        <linearGradient id="g1i" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <rect x="2" y="2" width="52" height="52" rx="12" fill="url(#g1i)" />
      <text x="28" y="40" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="28" fontWeight="900" letterSpacing="-2" fill="white" textAnchor="middle">AI</text>
    </svg>
  );

  // ─────────────────────────────────────────────
  // VARIANT 2 — "AI" gradient slova (bez pozadine)
  // Ikonica = "AI" velika bold slova sa gradient fill-om, sloboduna forma
  // ─────────────────────────────────────────────
  const Logo2Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 250 56" width="250" height="56">
      <defs>
        <linearGradient id="g2l" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <text x="0" y="44" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="48" fontWeight="900" letterSpacing="-3" fill="url(#g2l)">AI</text>
      <text x="68" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#0F172A">BOOST NOW</text>
    </svg>
  );
  const Logo2Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 250 56" width="250" height="56">
      <defs>
        <linearGradient id="g2d" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <text x="0" y="44" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="48" fontWeight="900" letterSpacing="-3" fill="url(#g2d)">AI</text>
      <text x="68" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#F1F5F9">BOOST NOW</text>
    </svg>
  );
  const Logo2Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 70 56" width="70" height="56">
      <defs>
        <linearGradient id="g2i" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <text x="0" y="48" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-3" fill="url(#g2i)">AI</text>
    </svg>
  );

  // ─────────────────────────────────────────────
  // VARIANT 3 — "AI" u krugu (circle badge)
  // Ikonica = krug sa gradient-om, "AI" beli bold
  // ─────────────────────────────────────────────
  const Logo3Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 56" width="240" height="56">
      <defs>
        <linearGradient id="g3l" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <circle cx="26" cy="28" r="24" fill="url(#g3l)" />
      <text x="26" y="36" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="900" letterSpacing="-1.5" fill="white" textAnchor="middle">AI</text>
      <text x="60" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#0F172A">BOOST NOW</text>
    </svg>
  );
  const Logo3Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 56" width="240" height="56">
      <defs>
        <linearGradient id="g3d" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <circle cx="26" cy="28" r="24" fill="url(#g3d)" />
      <text x="26" y="36" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="900" letterSpacing="-1.5" fill="#0F172A" textAnchor="middle">AI</text>
      <text x="60" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#F1F5F9">BOOST NOW</text>
    </svg>
  );
  const Logo3Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 56 56" width="64" height="64">
      <defs>
        <linearGradient id="g3i" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <circle cx="28" cy="28" r="26" fill="url(#g3i)" />
      <text x="28" y="38" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="26" fontWeight="900" letterSpacing="-2" fill="white" textAnchor="middle">AI</text>
    </svg>
  );

  const variants = [
    {
      id: "1",
      title: "Rounded Square Badge",
      desc: "\"AI\" u plavom rounded square-u (kao app icon). Najsličnije modernom SaaS/AI brendu — kao OpenAI, Anthropic, Linear style.",
      light: <Logo1Light />,
      dark: <Logo1Dark />,
      icon: <Logo1Icon />,
    },
    {
      id: "2",
      title: "Gradient Letters (no badge)",
      desc: "\"AI\" velika gradient slova bez pozadine, slobodna forma. Lakši, vazdušasti karakter — wordmark sam po sebi.",
      light: <Logo2Light />,
      dark: <Logo2Dark />,
      icon: <Logo2Icon />,
    },
    {
      id: "3",
      title: "Circle Badge",
      desc: "\"AI\" u plavom krugu. Mekši, pristupačniji od kvadrata, dobro radi kao avatar/profile pic.",
      light: <Logo3Light />,
      dark: <Logo3Dark />,
      icon: <Logo3Icon />,
    },
  ];

  return (
    <div style={{ fontFamily: "'Inter','Segoe UI',sans-serif", background: "#F8FAFC", minHeight: "100vh", color: "#1E293B" }}>
      <div style={{ maxWidth: 960, margin: "0 auto", padding: "40px 32px 80px" }}>

        <div style={{ marginBottom: 36, paddingBottom: 24, borderBottom: "2px solid #E2E8F0" }}>
          <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 6 }}>AI Boost Now — Logo v3</p>
          <h1 style={{ fontSize: 26, fontWeight: 800, color: "#0F172A" }}>"AI" kao logotip + BOOST NOW pored</h1>
        </div>

        {variants.map(v => (
          <div key={v.id} style={{ marginBottom: 44, paddingBottom: 44, borderBottom: "1px solid #E2E8F0" }}>
            <div style={{ display: "flex", alignItems: "baseline", gap: 10, marginBottom: 6 }}>
              <span style={{ fontSize: 13, fontWeight: 800, color: "#2563EB" }}>0{v.id}</span>
              <span style={{ fontSize: 19, fontWeight: 700, color: "#0F172A" }}>{v.title}</span>
            </div>
            <p style={{ fontSize: 13, color: "#64748B", marginBottom: 18, maxWidth: 620 }}>{v.desc}</p>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 140px", gap: 12 }}>
              <Card bg="#fff" border="1px solid #E2E8F0" label="Light">{v.light}</Card>
              <Card bg="#0F172A" label="Dark" labelColor="#475569">{v.dark}</Card>
              <Card bg="#F1F5F9" border="1px solid #E2E8F0" label="Icon only">{v.icon}</Card>
            </div>
          </div>
        ))}

        <p style={{ fontSize: 12, color: "#94A3B8" }}>
          Sve tri varijante koriste isti gradient (cyan → plavo) i isti font (Inter Black 900). Pick one → finalize SVGs.
        </p>
      </div>
    </div>
  );
}

function Card({ bg, border, label, labelColor, children }: {
  bg: string; border?: string; label: string; labelColor?: string; children: React.ReactNode
}) {
  return (
    <div style={{ background: bg, border: border || "none", borderRadius: 12, padding: "28px 20px", display: "flex", flexDirection: "column", alignItems: "center", gap: 14 }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center", minHeight: 60 }}>{children}</div>
      <span style={{ fontSize: 10, fontWeight: 600, letterSpacing: ".08em", textTransform: "uppercase", color: labelColor || "#94A3B8" }}>{label}</span>
    </div>
  );
}
