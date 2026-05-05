export default function BrandStyleGuide() {

  // Shared wordmark: AI BOOST NOW — all caps, same size, tight
  const Wordmark = ({ fill1, fill2 }: { fill1: string; fill2: string }) => (
    <>
      <text
        fontFamily="'Inter','Segoe UI',sans-serif"
        fontSize="20"
        fontWeight="800"
        letterSpacing="2"
        textAnchor="start"
      >
        <tspan fill={fill1}>AI BOOST</tspan>
        <tspan fill={fill2}> NOW</tspan>
      </text>
    </>
  );

  // ─────────────────────────────────────────────
  // ICON A — Munja (lightning bolt)
  // Boost = energija, brzina, snaga
  // ─────────────────────────────────────────────
  const IconA = ({ id }: { id: string }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 44 56" width="40" height="52">
      <defs>
        <linearGradient id={id} x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" />
          <stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <path
        d="M27 4 L12 30 L21 30 L17 52 L38 22 L28 22 L35 4 Z"
        fill={`url(#${id})`}
      />
    </svg>
  );
  const IconADark = ({ id }: { id: string }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 44 56" width="40" height="52">
      <defs>
        <linearGradient id={id} x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#22D3EE" />
          <stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <path
        d="M27 4 L12 30 L21 30 L17 52 L38 22 L28 22 L35 4 Z"
        fill={`url(#${id})`}
      />
    </svg>
  );

  // ─────────────────────────────────────────────
  // ICON B — Dijamant (diamond / gem)
  // Precision, premium, sharp AI outputs
  // ─────────────────────────────────────────────
  const IconB = ({ id }: { id: string }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 56" width="44" height="52">
      <defs>
        <linearGradient id={id} x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" />
          <stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      {/* top trapezoid */}
      <polygon points="14,4 34,4 44,20 4,20" fill={`url(#${id})`} opacity="0.85" />
      {/* bottom diamond */}
      <polygon points="4,22 44,22 24,52" fill={`url(#${id})`} />
    </svg>
  );
  const IconBDark = ({ id }: { id: string }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 56" width="44" height="52">
      <defs>
        <linearGradient id={id} x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#22D3EE" />
          <stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <polygon points="14,4 34,4 44,20 4,20" fill={`url(#${id})`} opacity="0.85" />
      <polygon points="4,22 44,22 24,52" fill={`url(#${id})`} />
    </svg>
  );

  // ─────────────────────────────────────────────
  // ICON C — Spark / AI zvezdica (4 zraka)
  // AI generative feel, energy point, "now moment"
  // ─────────────────────────────────────────────
  const IconC = ({ id }: { id: string }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 56" width="44" height="52">
      <defs>
        <linearGradient id={id} x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#06B6D4" />
          <stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      {/* 4-point star: two overlapping rhombuses */}
      <polygon points="24,4 29,24 24,44 19,24" fill={`url(#${id})`} />
      <polygon points="4,28 24,23 44,28 24,33" fill={`url(#${id})`} />
      {/* small dot at center */}
      <circle cx="24" cy="28" r="4" fill="white" opacity="0.6" />
    </svg>
  );
  const IconCDark = ({ id }: { id: string }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 56" width="44" height="52">
      <defs>
        <linearGradient id={id} x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#22D3EE" />
          <stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <polygon points="24,4 29,24 24,44 19,24" fill={`url(#${id})`} />
      <polygon points="4,28 24,23 44,28 24,33" fill={`url(#${id})`} />
      <circle cx="24" cy="28" r="4" fill="#0F172A" opacity="0.5" />
    </svg>
  );

  // Full logo SVGs (icon + wordmark)
  const makeLogoLight = (
    icon: React.ReactNode,
    iconW: number,
    _id: string
  ) => {
    const totalW = iconW + 220;
    return (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox={`0 0 ${totalW} 56`} width={totalW} height="56">
        <g transform="translate(0,2)">{icon}</g>
        <text
          x={iconW + 14}
          y="37"
          fontFamily="'Inter','Segoe UI',sans-serif"
          fontSize="20"
          fontWeight="800"
          letterSpacing="2"
        >
          <tspan fill="#1E293B">AI BOOST</tspan>
          <tspan fill="#2563EB"> NOW</tspan>
        </text>
      </svg>
    );
  };

  const makeLogoDark = (
    icon: React.ReactNode,
    iconW: number
  ) => {
    const totalW = iconW + 220;
    return (
      <svg xmlns="http://www.w3.org/2000/svg" viewBox={`0 0 ${totalW} 56`} width={totalW} height="56">
        <g transform="translate(0,2)">{icon}</g>
        <text
          x={iconW + 14}
          y="37"
          fontFamily="'Inter','Segoe UI',sans-serif"
          fontSize="20"
          fontWeight="800"
          letterSpacing="2"
        >
          <tspan fill="#F1F5F9">AI BOOST</tspan>
          <tspan fill="#60A5FA"> NOW</tspan>
        </text>
      </svg>
    );
  };

  const variants = [
    {
      id: "A",
      title: "Munja (Lightning)",
      desc: "Boost = energija, brzina, snaga. Čista, prepoznatljiva forma. Radi na svim veličinama.",
      iconLight: <IconA id="gA-l" />,
      iconDark: <IconADark id="gA-d" />,
      iconW: 52,
    },
    {
      id: "B",
      title: "Dijamant (Gem)",
      desc: "Preciznost, premium kvalitet, oštrina AI output-a. Geometrijski, distinktivan, moderan.",
      iconLight: <IconB id="gB-l" />,
      iconDark: <IconBDark id="gB-d" />,
      iconW: 56,
    },
    {
      id: "C",
      title: "Spark (AI zvezda)",
      desc: "4-krak spark = AI generativni momenat, tačka energije. Now = ovaj trenutak. Tech-forward.",
      iconLight: <IconC id="gC-l" />,
      iconDark: <IconCDark id="gC-d" />,
      iconW: 56,
    },
  ];

  return (
    <div style={{ fontFamily: "'Inter','Segoe UI',sans-serif", background: "#F8FAFC", minHeight: "100vh", color: "#1E293B" }}>
      <div style={{ maxWidth: 960, margin: "0 auto", padding: "40px 32px 80px" }}>

        <div style={{ marginBottom: 40, paddingBottom: 28, borderBottom: "2px solid #E2E8F0" }}>
          <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 6 }}>AI Boost Now — Logo Candidates v2</p>
          <h1 style={{ fontSize: 26, fontWeight: 800, color: "#0F172A" }}>Nova ikonica + AI BOOST NOW caps wordmark</h1>
        </div>

        {variants.map(v => (
          <div key={v.id} style={{ marginBottom: 48, paddingBottom: 48, borderBottom: "1px solid #E2E8F0" }}>
            <div style={{ display: "flex", alignItems: "baseline", gap: 10, marginBottom: 6 }}>
              <span style={{ fontSize: 13, fontWeight: 800, color: "#2563EB" }}>{v.id}</span>
              <span style={{ fontSize: 19, fontWeight: 700, color: "#0F172A" }}>{v.title}</span>
            </div>
            <p style={{ fontSize: 13, color: "#64748B", marginBottom: 20, maxWidth: 600 }}>{v.desc}</p>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 140px", gap: 12, marginBottom: 12 }}>
              {/* Light logo */}
              <Card bg="#fff" border="1px solid #E2E8F0" label="Light">
                {makeLogoLight(v.iconLight, v.iconW, v.id + "-light")}
              </Card>
              {/* Dark logo */}
              <Card bg="#0F172A" label="Dark" labelColor="#475569">
                {makeLogoDark(v.iconDark, v.iconW)}
              </Card>
              {/* Icon only */}
              <Card bg="#F1F5F9" border="1px solid #E2E8F0" label="Icon">
                <div style={{ transform: "scale(1.3)", transformOrigin: "center" }}>{v.iconLight}</div>
              </Card>
            </div>
          </div>
        ))}

        <p style={{ fontSize: 12, color: "#94A3B8" }}>
          Wordmark: "AI BOOST" slate · "NOW" plavo — sve isti font-weight 800, ista veličina, bez razmaka.
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
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center" }}>{children}</div>
      <span style={{ fontSize: 10, fontWeight: 600, letterSpacing: ".08em", textTransform: "uppercase", color: labelColor || "#94A3B8" }}>{label}</span>
    </div>
  );
}
