export default function BrandStyleGuide() {

  // ═══════════════════════════════════════════════
  // CONCEPT A — PICTORIAL: Search Bubble + Sparkle
  // ═══════════════════════════════════════════════
  // Filozofija: Plugin čini sajtove vidljivim AI search engine-ima.
  // Ikonica = AI answer / search bubble sa sparkle iznutra.
  // Direktno komunicira PROIZVOD. Memorabilan, on-message.
  const ConceptA_Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" width="320" height="80">
      <defs>
        <linearGradient id="A_g" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      {/* Search/answer bubble */}
      <path d="M 8 12 Q 8 4, 16 4 L 56 4 Q 64 4, 64 12 L 64 44 Q 64 52, 56 52 L 32 52 L 24 64 L 24 52 L 16 52 Q 8 52, 8 44 Z" fill="url(#A_g)" />
      {/* Sparkle inside bubble */}
      <path d="M 36 16 L 39 26 L 49 28 L 39 30 L 36 40 L 33 30 L 23 28 L 33 26 Z" fill="white" />
      {/* Mini sparkle */}
      <circle cx="50" cy="18" r="2" fill="white" opacity="0.8" />
      {/* Wordmark */}
      <text x="84" y="36" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="900" letterSpacing="-0.3" fill="#0F172A">AI Boost</text>
      <text x="84" y="58" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="13" fontWeight="700" letterSpacing="3" fill="#06B6D4">N O W</text>
    </svg>
  );
  const ConceptA_Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" width="320" height="80">
      <defs>
        <linearGradient id="A_gd" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <path d="M 8 12 Q 8 4, 16 4 L 56 4 Q 64 4, 64 12 L 64 44 Q 64 52, 56 52 L 32 52 L 24 64 L 24 52 L 16 52 Q 8 52, 8 44 Z" fill="url(#A_gd)" />
      <path d="M 36 16 L 39 26 L 49 28 L 39 30 L 36 40 L 33 30 L 23 28 L 33 26 Z" fill="#0F172A" />
      <circle cx="50" cy="18" r="2" fill="#0F172A" opacity="0.7" />
      <text x="84" y="36" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="900" letterSpacing="-0.3" fill="#F1F5F9">AI Boost</text>
      <text x="84" y="58" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="13" fontWeight="700" letterSpacing="3" fill="#22D3EE">N O W</text>
    </svg>
  );
  const ConceptA_Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="80" height="80">
      <defs>
        <linearGradient id="A_gi" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <path d="M 8 18 Q 8 6, 20 6 L 60 6 Q 72 6, 72 18 L 72 50 Q 72 60, 62 60 L 36 60 L 26 74 L 26 60 L 20 60 Q 8 60, 8 50 Z" fill="url(#A_gi)" />
      <path d="M 40 20 L 44 32 L 56 35 L 44 38 L 40 50 L 36 38 L 24 35 L 36 32 Z" fill="white" />
      <circle cx="58" cy="22" r="3" fill="white" opacity="0.85" />
    </svg>
  );

  // ═══════════════════════════════════════════════
  // CONCEPT B — ABSTRACT: Sunrise / Arc + Burst
  // ═══════════════════════════════════════════════
  // Filozofija: Boost = uspon, rising, dawn nove ere AI search-a.
  // Ikonica = polukrug (horizont) + sunrise rays + accent dot.
  // Optimistično, premium, evocira "rising tide / new era".
  const ConceptB_Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" width="320" height="80">
      <defs>
        <linearGradient id="B_g" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      {/* Horizon line */}
      <line x1="4" y1="56" x2="68" y2="56" stroke="#0F172A" strokeWidth="2.5" strokeLinecap="round" />
      {/* Half sun */}
      <path d="M 18 56 A 18 18 0 0 1 54 56 Z" fill="url(#B_g)" />
      {/* Rays */}
      <line x1="36" y1="14" x2="36" y2="22" stroke="url(#B_g)" strokeWidth="3" strokeLinecap="round" />
      <line x1="14" y1="32" x2="20" y2="36" stroke="url(#B_g)" strokeWidth="3" strokeLinecap="round" />
      <line x1="58" y1="32" x2="52" y2="36" stroke="url(#B_g)" strokeWidth="3" strokeLinecap="round" />
      <line x1="6" y1="46" x2="12" y2="48" stroke="url(#B_g)" strokeWidth="3" strokeLinecap="round" />
      <line x1="66" y1="46" x2="60" y2="48" stroke="url(#B_g)" strokeWidth="3" strokeLinecap="round" />
      {/* Wordmark */}
      <text x="84" y="40" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="900" letterSpacing="-0.5" fill="#0F172A">AI Boost</text>
      <text x="84" y="60" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="13" fontWeight="600" letterSpacing="4" fill="#475569">NOW</text>
    </svg>
  );
  const ConceptB_Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" width="320" height="80">
      <defs>
        <linearGradient id="B_gd" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <line x1="4" y1="56" x2="68" y2="56" stroke="#F1F5F9" strokeWidth="2.5" strokeLinecap="round" />
      <path d="M 18 56 A 18 18 0 0 1 54 56 Z" fill="url(#B_gd)" />
      <line x1="36" y1="14" x2="36" y2="22" stroke="url(#B_gd)" strokeWidth="3" strokeLinecap="round" />
      <line x1="14" y1="32" x2="20" y2="36" stroke="url(#B_gd)" strokeWidth="3" strokeLinecap="round" />
      <line x1="58" y1="32" x2="52" y2="36" stroke="url(#B_gd)" strokeWidth="3" strokeLinecap="round" />
      <line x1="6" y1="46" x2="12" y2="48" stroke="url(#B_gd)" strokeWidth="3" strokeLinecap="round" />
      <line x1="66" y1="46" x2="60" y2="48" stroke="url(#B_gd)" strokeWidth="3" strokeLinecap="round" />
      <text x="84" y="40" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="24" fontWeight="900" letterSpacing="-0.5" fill="#F1F5F9">AI Boost</text>
      <text x="84" y="60" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="13" fontWeight="600" letterSpacing="4" fill="#94A3B8">NOW</text>
    </svg>
  );
  const ConceptB_Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="80" height="80">
      <defs>
        <linearGradient id="B_gi" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <line x1="6" y1="60" x2="74" y2="60" stroke="#0F172A" strokeWidth="3" strokeLinecap="round" />
      <path d="M 20 60 A 20 20 0 0 1 60 60 Z" fill="url(#B_gi)" />
      <line x1="40" y1="14" x2="40" y2="24" stroke="url(#B_gi)" strokeWidth="3.5" strokeLinecap="round" />
      <line x1="14" y1="32" x2="22" y2="38" stroke="url(#B_gi)" strokeWidth="3.5" strokeLinecap="round" />
      <line x1="66" y1="32" x2="58" y2="38" stroke="url(#B_gi)" strokeWidth="3.5" strokeLinecap="round" />
      <line x1="6" y1="48" x2="14" y2="52" stroke="url(#B_gi)" strokeWidth="3.5" strokeLinecap="round" />
      <line x1="74" y1="48" x2="66" y2="52" stroke="url(#B_gi)" strokeWidth="3.5" strokeLinecap="round" />
    </svg>
  );

  // ═══════════════════════════════════════════════
  // CONCEPT C — WORDMARK ONLY (No icon — FedEx / Stripe stil)
  // ═══════════════════════════════════════════════
  // Filozofija: Najsnažniji brendovi NEMAJU ikonu — imaju karakterističnu tipografiju.
  // "AI•BOOST•NOW" — bullet separators + custom letterforms + "OO" u BOOST kao orbits.
  // Tipografska solucija. Ime JE logo. Skalabilan, distinktivan.
  const ConceptC_Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 380 80" width="380" height="80">
      <defs>
        <linearGradient id="C_g" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      {/* AI */}
      <text x="0" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="38" fontWeight="900" letterSpacing="-2" fill="#0F172A">AI</text>
      {/* Bullet separator */}
      <circle cx="62" cy="38" r="3.5" fill="url(#C_g)" />
      {/* BOOST — with the two O's stylized as orbit rings */}
      <text x="76" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="38" fontWeight="900" letterSpacing="-1.5" fill="#0F172A">B</text>
      <circle cx="124" cy="32" r="11" fill="none" stroke="url(#C_g)" strokeWidth="5" />
      <circle cx="148" cy="32" r="11" fill="none" stroke="url(#C_g)" strokeWidth="5" />
      <text x="160" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="38" fontWeight="900" letterSpacing="-1.5" fill="#0F172A">ST</text>
      {/* Bullet */}
      <circle cx="220" cy="38" r="3.5" fill="url(#C_g)" />
      {/* NOW */}
      <text x="234" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="38" fontWeight="900" letterSpacing="-1.5" fill="url(#C_g)">NOW</text>
    </svg>
  );
  const ConceptC_Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 380 80" width="380" height="80">
      <defs>
        <linearGradient id="C_gd" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <text x="0" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="38" fontWeight="900" letterSpacing="-2" fill="#F1F5F9">AI</text>
      <circle cx="62" cy="38" r="3.5" fill="url(#C_gd)" />
      <text x="76" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="38" fontWeight="900" letterSpacing="-1.5" fill="#F1F5F9">B</text>
      <circle cx="124" cy="32" r="11" fill="none" stroke="url(#C_gd)" strokeWidth="5" />
      <circle cx="148" cy="32" r="11" fill="none" stroke="url(#C_gd)" strokeWidth="5" />
      <text x="160" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="38" fontWeight="900" letterSpacing="-1.5" fill="#F1F5F9">ST</text>
      <circle cx="220" cy="38" r="3.5" fill="url(#C_gd)" />
      <text x="234" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="38" fontWeight="900" letterSpacing="-1.5" fill="url(#C_gd)">NOW</text>
    </svg>
  );
  const ConceptC_Icon = () => (
    // For favicon: show just the "BOO" with orbit-O treatment as the brand mark
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="80" height="80">
      <defs>
        <linearGradient id="C_gi" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      {/* Two orbit rings overlapping */}
      <circle cx="30" cy="40" r="22" fill="none" stroke="url(#C_gi)" strokeWidth="6" />
      <circle cx="50" cy="40" r="22" fill="none" stroke="url(#C_gi)" strokeWidth="6" opacity="0.8" />
    </svg>
  );

  const variants = [
    {
      id: "A",
      title: "Pictorial — Search Bubble + Sparkle",
      desc: "Ikonica = AI answer bubble sa sparkle-om iznutra. Direktno prikazuje ŠTA proizvod radi: čini sajtove vidljivim u AI search rezultatima. Memorabilan i on-message.",
      vibe: "Friendly · Product-focused · Recognizable",
      light: <ConceptA_Light />,
      dark: <ConceptA_Dark />,
      icon: <ConceptA_Icon />,
    },
    {
      id: "B",
      title: "Abstract — Sunrise / Rising",
      desc: "Polukrug + horizon + zraci = rising sun, dawn nove AI ere. Boost = uspon. Optimistično, evocira početak, premium feel. Nema ničega doslovnog — apstraktno i vremenski izdrživo.",
      vibe: "Optimistic · Timeless · Premium",
      light: <ConceptB_Light />,
      dark: <ConceptB_Dark />,
      icon: <ConceptB_Icon />,
    },
    {
      id: "C",
      title: "Wordmark Only — Orbit O's (FedEx stil)",
      desc: "BEZ ikonice. Ime samo je logo, sa skrivenim detaljem: dva slova \"O\" u BOOST su orbit prstenovi (planeta/AI cycle). Najjači brendovi često nemaju ikonu — tipografija je dovoljna.",
      vibe: "Bold · Distinctive · Type-driven",
      light: <ConceptC_Light />,
      dark: <ConceptC_Dark />,
      icon: <ConceptC_Icon />,
    },
  ];

  return (
    <div style={{ fontFamily: "'Inter','Segoe UI',sans-serif", background: "#F8FAFC", minHeight: "100vh", color: "#1E293B" }}>
      <div style={{ maxWidth: 1000, margin: "0 auto", padding: "40px 32px 80px" }}>

        <div style={{ marginBottom: 36, paddingBottom: 24, borderBottom: "2px solid #E2E8F0" }}>
          <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 6 }}>AI Boost Now — Logo v5</p>
          <h1 style={{ fontSize: 26, fontWeight: 800, color: "#0F172A" }}>3 potpuno različita pravca</h1>
          <p style={{ fontSize: 14, color: "#64748B", marginTop: 8 }}>
            Pictorial (govori šta proizvod radi) · Abstract (simbolično, premium) · Wordmark-only (tipografija JE logo)
          </p>
        </div>

        {variants.map(v => (
          <div key={v.id} style={{ marginBottom: 44, paddingBottom: 44, borderBottom: "1px solid #E2E8F0" }}>
            <div style={{ display: "flex", alignItems: "baseline", gap: 12, marginBottom: 6 }}>
              <span style={{ fontSize: 14, fontWeight: 800, color: "#2563EB" }}>{v.id}</span>
              <span style={{ fontSize: 19, fontWeight: 700, color: "#0F172A" }}>{v.title}</span>
            </div>
            <p style={{ fontSize: 13, color: "#475569", marginBottom: 4, maxWidth: 720, lineHeight: 1.55 }}>{v.desc}</p>
            <p style={{ fontSize: 11, color: "#06B6D4", fontWeight: 700, letterSpacing: ".06em", textTransform: "uppercase", marginBottom: 18 }}>{v.vibe}</p>

            <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1.2fr 140px", gap: 12 }}>
              <Card bg="#fff" border="1px solid #E2E8F0" label="Light">{v.light}</Card>
              <Card bg="#0F172A" label="Dark" labelColor="#475569">{v.dark}</Card>
              <Card bg="#F1F5F9" border="1px solid #E2E8F0" label="Icon / Favicon">{v.icon}</Card>
            </div>
          </div>
        ))}

      </div>
    </div>
  );
}

function Card({ bg, border, label, labelColor, children }: {
  bg: string; border?: string; label: string; labelColor?: string; children: React.ReactNode
}) {
  return (
    <div style={{ background: bg, border: border || "none", borderRadius: 12, padding: "24px 16px", display: "flex", flexDirection: "column", alignItems: "center", gap: 14 }}>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center", minHeight: 80 }}>{children}</div>
      <span style={{ fontSize: 10, fontWeight: 600, letterSpacing: ".08em", textTransform: "uppercase", color: labelColor || "#94A3B8" }}>{label}</span>
    </div>
  );
}
