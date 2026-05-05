export default function BrandStyleGuide() {

  // ─────────────────────────────────────────────
  // VARIANT 1 — "ai" + sparkle ✦ (Apple Intelligence / Gemini stil)
  // Lowercase, mekano, sa AI sparkle akcentom
  // ─────────────────────────────────────────────
  const Logo1Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 64" width="280" height="64">
      <defs>
        <linearGradient id="g1l" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
        <linearGradient id="g1ls" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      {/* "ai" lowercase letters */}
      <text x="0" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-4" fill="url(#g1l)">ai</text>
      {/* Sparkle ✦ — replaces dot of "i" */}
      <g transform="translate(58,12)">
        <path d="M 8 0 L 10 6 L 16 8 L 10 10 L 8 16 L 6 10 L 0 8 L 6 6 Z" fill="url(#g1ls)" />
      </g>
      {/* Mini sparkle */}
      <g transform="translate(75,4)">
        <path d="M 4 0 L 5 3 L 8 4 L 5 5 L 4 8 L 3 5 L 0 4 L 3 3 Z" fill="#06B6D4" opacity="0.7" />
      </g>
      {/* "BOOST NOW" wordmark */}
      <text x="100" y="42" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#0F172A">BOOST NOW</text>
    </svg>
  );
  const Logo1Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 280 64" width="280" height="64">
      <defs>
        <linearGradient id="g1d" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <text x="0" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-4" fill="url(#g1d)">ai</text>
      <g transform="translate(58,12)">
        <path d="M 8 0 L 10 6 L 16 8 L 10 10 L 8 16 L 6 10 L 0 8 L 6 6 Z" fill="#22D3EE" />
      </g>
      <g transform="translate(75,4)">
        <path d="M 4 0 L 5 3 L 8 4 L 5 5 L 4 8 L 3 5 L 0 4 L 3 3 Z" fill="#60A5FA" opacity="0.8" />
      </g>
      <text x="100" y="42" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#F1F5F9">BOOST NOW</text>
    </svg>
  );
  const Logo1Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 80" width="96" height="80">
      <defs>
        <linearGradient id="g1i" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <text x="0" y="68" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="76" fontWeight="900" letterSpacing="-5" fill="url(#g1i)">ai</text>
      <g transform="translate(74,14)">
        <path d="M 11 0 L 14 8 L 22 11 L 14 14 L 11 22 L 8 14 L 0 11 L 8 8 Z" fill="url(#g1i)" />
      </g>
    </svg>
  );

  // ─────────────────────────────────────────────
  // VARIANT 2 — "AI" + boost arrow / chevron
  // Slovo "I" se transformiše u upward arrow (boost smer)
  // ─────────────────────────────────────────────
  const Logo2Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 290 64" width="290" height="64">
      <defs>
        <linearGradient id="g2l" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      {/* Letter A */}
      <text x="0" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-3" fill="url(#g2l)">A</text>
      {/* Custom "I" as upward arrow */}
      <g transform="translate(46,8)">
        <polygon points="14,0 28,16 20,16 20,46 8,46 8,16 0,16" fill="url(#g2l)" />
      </g>
      <text x="100" y="42" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#0F172A">BOOST NOW</text>
    </svg>
  );
  const Logo2Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 290 64" width="290" height="64">
      <defs>
        <linearGradient id="g2d" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#22D3EE" /><stop offset="100%" stopColor="#60A5FA" />
        </linearGradient>
      </defs>
      <text x="0" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-3" fill="url(#g2d)">A</text>
      <g transform="translate(46,8)">
        <polygon points="14,0 28,16 20,16 20,46 8,46 8,16 0,16" fill="url(#g2d)" />
      </g>
      <text x="100" y="42" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#F1F5F9">BOOST NOW</text>
    </svg>
  );
  const Logo2Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 80" width="100" height="80">
      <defs>
        <linearGradient id="g2i" x1="0%" y1="100%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="#06B6D4" /><stop offset="100%" stopColor="#2563EB" />
        </linearGradient>
      </defs>
      <text x="0" y="68" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="76" fontWeight="900" letterSpacing="-3" fill="url(#g2i)">A</text>
      <g transform="translate(60,12)">
        <polygon points="18,0 36,20 26,20 26,62 10,62 10,20 0,20" fill="url(#g2i)" />
      </g>
    </svg>
  );

  // ─────────────────────────────────────────────
  // VARIANT 3 — "AI" depth/layered (glitch effect)
  // Cyan offset duplikat ispod plavog glavnog teksta = depth, motion
  // ─────────────────────────────────────────────
  const Logo3Light = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 290 64" width="290" height="64">
      {/* Cyan shadow layer */}
      <text x="4" y="54" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-4" fill="#06B6D4" opacity="0.85">AI</text>
      {/* Main blue layer */}
      <text x="0" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-4" fill="#2563EB">AI</text>
      <text x="92" y="42" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#0F172A">BOOST NOW</text>
    </svg>
  );
  const Logo3Dark = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 290 64" width="290" height="64">
      <text x="4" y="54" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-4" fill="#22D3EE" opacity="0.85">AI</text>
      <text x="0" y="50" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="56" fontWeight="900" letterSpacing="-4" fill="#60A5FA">AI</text>
      <text x="92" y="42" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="22" fontWeight="800" letterSpacing="2" fill="#F1F5F9">BOOST NOW</text>
    </svg>
  );
  const Logo3Icon = () => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 80" width="100" height="80">
      <text x="6" y="74" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="76" fontWeight="900" letterSpacing="-5" fill="#06B6D4" opacity="0.85">AI</text>
      <text x="0" y="68" fontFamily="'Inter','Segoe UI',sans-serif" fontSize="76" fontWeight="900" letterSpacing="-5" fill="#2563EB">AI</text>
    </svg>
  );

  const variants = [
    {
      id: "1",
      title: "ai ✦ Sparkle (Apple Intelligence stil)",
      desc: "Lowercase \"ai\" gradient slova + AI sparkle ✦ akcent gde bi bila tačka na \"i\". Direktna referenca na moderne AI brendove (Gemini, Apple Intelligence, Notion AI). Mekano, prepoznatljivo, AI-native.",
      light: <Logo1Light />,
      dark: <Logo1Dark />,
      icon: <Logo1Icon />,
    },
    {
      id: "2",
      title: "A↑ Boost Arrow",
      desc: "Slovo \"I\" se transformiše u upward arrow / boost shape — vizualna reprezentacija \"AI + boost\" u jednom potezu. Smelo, conceptual, neuobičajeno za SEO niše.",
      light: <Logo2Light />,
      dark: <Logo2Dark />,
      icon: <Logo2Icon />,
    },
    {
      id: "3",
      title: "AI Depth / Glitch Layer",
      desc: "Cyan offset duplikat ispod plavog glavnog teksta = depth, motion, glitch-art aesthetic. Vrlo moderan AI/cyber feel — koristi se često u techno/AI brendiranju 2024-2026.",
      light: <Logo3Light />,
      dark: <Logo3Dark />,
      icon: <Logo3Icon />,
    },
  ];

  return (
    <div style={{ fontFamily: "'Inter','Segoe UI',sans-serif", background: "#F8FAFC", minHeight: "100vh", color: "#1E293B" }}>
      <div style={{ maxWidth: 960, margin: "0 auto", padding: "40px 32px 80px" }}>

        <div style={{ marginBottom: 36, paddingBottom: 24, borderBottom: "2px solid #E2E8F0" }}>
          <p style={{ fontSize: 11, fontWeight: 700, letterSpacing: ".1em", textTransform: "uppercase", color: "#94A3B8", marginBottom: 6 }}>AI Boost Now — Logo v4 (creative)</p>
          <h1 style={{ fontSize: 26, fontWeight: 800, color: "#0F172A" }}>3 distinktivna pravca</h1>
        </div>

        {variants.map(v => (
          <div key={v.id} style={{ marginBottom: 44, paddingBottom: 44, borderBottom: "1px solid #E2E8F0" }}>
            <div style={{ display: "flex", alignItems: "baseline", gap: 10, marginBottom: 6 }}>
              <span style={{ fontSize: 13, fontWeight: 800, color: "#2563EB" }}>0{v.id}</span>
              <span style={{ fontSize: 19, fontWeight: 700, color: "#0F172A" }}>{v.title}</span>
            </div>
            <p style={{ fontSize: 13, color: "#64748B", marginBottom: 18, maxWidth: 660, lineHeight: 1.55 }}>{v.desc}</p>

            <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 160px", gap: 12 }}>
              <Card bg="#fff" border="1px solid #E2E8F0" label="Light">{v.light}</Card>
              <Card bg="#0F172A" label="Dark" labelColor="#475569">{v.dark}</Card>
              <Card bg="#F1F5F9" border="1px solid #E2E8F0" label="Icon only">{v.icon}</Card>
            </div>
          </div>
        ))}

        <p style={{ fontSize: 12, color: "#94A3B8" }}>
          Sve tri varijante: gradient cyan→plavo, Inter Black, "BOOST NOW" pored kao stabilan wordmark.
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
      <div style={{ display: "flex", alignItems: "center", justifyContent: "center", minHeight: 80 }}>{children}</div>
      <span style={{ fontSize: 10, fontWeight: 600, letterSpacing: ".08em", textTransform: "uppercase", color: labelColor || "#94A3B8" }}>{label}</span>
    </div>
  );
}
