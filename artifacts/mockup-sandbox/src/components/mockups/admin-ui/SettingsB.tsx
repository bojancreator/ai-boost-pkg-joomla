import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

/* Variant B: left sidebar navigation for settings tabs */
const GROUPS = [
  {
    label: "Site",
    items: [
      { id: "general",  label: "General",       icon: "⊞", desc: "Site name, URL, language" },
      { id: "org",      label: "Organisation",  icon: "🏢", desc: "Name, address, logo, contact" },
    ]
  },
  {
    label: "SEO & Schema",
    items: [
      { id: "schema",   label: "Schema.org",    icon: "{ }", desc: "Structured data, site type" },
      { id: "sitemap",  label: "Sitemap",        icon: "≡",  desc: "XML sitemap, hreflang" },
      { id: "social",   label: "Social",         icon: "⇄",  desc: "OpenGraph, Twitter Cards" },
    ]
  },
  {
    label: "Tracking & AI",
    items: [
      { id: "analytics", label: "Analytics",    icon: "▦",  desc: "GA4, GTM, Meta Pixel" },
      { id: "aeo",       label: "AEO",           icon: "⚡", desc: "llms.txt, IndexNow, AI signals" },
    ]
  },
  {
    label: "Advanced",
    items: [
      { id: "debug",    label: "Debug",          icon: "🔧", desc: "Dev bypass, error logging" },
      { id: "license",  label: "License",        icon: "🔑", desc: "License key, tier" },
    ]
  },
]

function FieldRow({ label, children, hint }: { label: string; children: React.ReactNode; hint?: string }) {
  return (
    <div style={{ padding: "12px 0", borderBottom: "1px solid var(--ab-border)" }}>
      <div style={{ display: "flex", gap: 16, alignItems: "flex-start" }}>
        <div style={{ minWidth: 200 }}>
          <div style={{ fontWeight: 500, fontSize: 13 }}>{label}</div>
          {hint && <div style={{ fontSize: 11.5, color: "var(--ab-text-muted)", marginTop: 2, lineHeight: 1.4 }}>{hint}</div>}
        </div>
        <div style={{ flex: 1 }}>{children}</div>
      </div>
    </div>
  )
}

const allItems = GROUPS.flatMap(g => g.items)

export function SettingsB() {
  const { dark, setDark } = useTheme()
  const [active, setActive] = useState("schema")
  const [saved, setSaved] = useState(false)

  const current = allItems.find(i => i.id === active)

  return (
    <AbShell activeNav="settings" dark={dark} onThemeToggle={setDark}>
      <div style={{ display: "flex", flexDirection: "column", minHeight: "calc(100vh - 48px)" }}>

        {/* Top bar */}
        <div style={{
          position: "sticky", top: 48, zIndex: 90,
          borderBottom: "1px solid var(--ab-border)",
          background: "var(--ab-surface)",
          padding: "10px 24px",
          display: "flex", alignItems: "center", gap: 10,
        }}>
          <h1 style={{ fontSize: 15, fontWeight: 700, margin: 0 }}>Settings</h1>
          <span style={{ color: "var(--ab-text-muted)" }}>›</span>
          <span style={{ fontSize: 14, color: "var(--ab-text-muted)" }}>{current?.label}</span>
          <div style={{ marginLeft: "auto", display: "flex", gap: 8, alignItems: "center" }}>
            {saved && <span style={{ fontSize: 12, color: "var(--ab-success)" }}>✓ Saved</span>}
            <button className="ab-btn ab-btn--ghost ab-btn--sm">Discard</button>
            <button className="ab-btn ab-btn--primary ab-btn--sm" onClick={() => setSaved(true)}>💾 Save All</button>
          </div>
        </div>

        <div style={{ display: "flex", flex: 1 }}>
          {/* Left sidebar */}
          <div style={{
            width: 210, flexShrink: 0,
            borderRight: "1px solid var(--ab-border)",
            background: "var(--ab-surface-raised)",
            padding: "16px 0",
            overflowY: "auto",
          }}>
            {GROUPS.map(g => (
              <div key={g.label} style={{ marginBottom: 8 }}>
                <div style={{
                  fontSize: 10.5, fontWeight: 700, letterSpacing: ".6px",
                  textTransform: "uppercase", color: "var(--ab-text-subtle)",
                  padding: "4px 16px 6px"
                }}>{g.label}</div>
                {g.items.map(item => (
                  <button
                    key={item.id}
                    onClick={() => setActive(item.id)}
                    style={{
                      display: "flex", alignItems: "center", gap: 9,
                      padding: "8px 16px",
                      width: "100%", border: "none", textAlign: "left",
                      cursor: "pointer",
                      borderLeft: `3px solid ${active === item.id ? "var(--ab-primary)" : "transparent"}`,
                      color: active === item.id ? "var(--ab-primary)" : "var(--ab-text-muted)",
                      background: active === item.id ? "rgba(13,110,253,.07)" : "transparent",
                      transition: "all .1s",
                      fontSize: 13, fontWeight: active === item.id ? 600 : 400,
                    }}
                  >
                    <span style={{ width: 18, textAlign: "center" }}>{item.icon}</span>
                    {item.label}
                  </button>
                ))}
              </div>
            ))}
          </div>

          {/* Content */}
          <div style={{ flex: 1, padding: 24, overflowY: "auto" }}>
            <div style={{ maxWidth: 720 }}>
              <div style={{ marginBottom: 20 }}>
                <h2 style={{ fontSize: 16, fontWeight: 700, margin: "0 0 4px" }}>
                  {current?.icon} {current?.label}
                </h2>
                <p style={{ fontSize: 13, color: "var(--ab-text-muted)", margin: 0 }}>{current?.desc}</p>
              </div>

              {active === "schema" && (
                <>
                  <FieldRow label="Site Type" hint="Determines which Schema.org type is generated">
                    <select className="ab-input" style={{ maxWidth: 280 }}>
                      <option>LocalBusiness</option>
                      <option>TouristAttraction (current)</option>
                      <option>Restaurant</option>
                      <option>Hotel</option>
                    </select>
                  </FieldRow>
                  <FieldRow label="FAQ Schema" hint="Auto-inject FAQ structured data">
                    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                      <div style={{ width: 40, height: 22, borderRadius: 11, background: "var(--ab-success)", position: "relative", cursor: "pointer" }}>
                        <div style={{ width: 18, height: 18, borderRadius: "50%", background: "#fff", position: "absolute", top: 2, left: 20 }} />
                      </div>
                      <span className="ab-small ab-text-muted">Enabled</span>
                    </div>
                  </FieldRow>
                  <FieldRow label="Events Schema" hint="Event schema on Joomla articles with dates">
                    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                      <div style={{ width: 40, height: 22, borderRadius: 11, background: "var(--ab-border)", position: "relative", cursor: "pointer" }}>
                        <div style={{ width: 18, height: 18, borderRadius: "50%", background: "#fff", position: "absolute", top: 2, left: 2 }} />
                      </div>
                      <span className="ab-small ab-text-muted">Disabled</span>
                      <span className="ab-badge ab-badge--primary">Pro</span>
                    </div>
                  </FieldRow>
                  <FieldRow label="BreadcrumbList" hint="Generate BreadcrumbList schema">
                    <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                      <div style={{ width: 40, height: 22, borderRadius: 11, background: "var(--ab-success)", position: "relative", cursor: "pointer" }}>
                        <div style={{ width: 18, height: 18, borderRadius: "50%", background: "#fff", position: "absolute", top: 2, left: 20 }} />
                      </div>
                      <span className="ab-small ab-text-muted">Enabled</span>
                    </div>
                  </FieldRow>
                  <FieldRow label="Output Mode" hint="How markup is injected into pages">
                    <div style={{ display: "flex", gap: 16 }}>
                      {["Inline JSON-LD (recommended)", "Microdata", "Disabled"].map(opt => (
                        <label key={opt} style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 13, cursor: "pointer" }}>
                          <input type="radio" name="mode" defaultChecked={opt.includes("JSON-LD")} /> {opt}
                        </label>
                      ))}
                    </div>
                  </FieldRow>
                </>
              )}

              {active === "org" && (
                <>
                  <FieldRow label="Organisation Name" hint="Used across all Schema.org types">
                    <input className="ab-input" defaultValue="Offroad Serbia Adventure Tours" />
                  </FieldRow>
                  <FieldRow label="Logo URL" hint="Full URL to organisation logo (PNG or SVG, min 112px)">
                    <input className="ab-input" defaultValue="https://staging.offroadserbia.com/images/logo.png" />
                  </FieldRow>
                  <FieldRow label="Phone" hint="+CountryCode format">
                    <input className="ab-input" defaultValue="+381 11 123 4567" style={{ maxWidth: 240 }} />
                  </FieldRow>
                  <FieldRow label="Street Address">
                    <input className="ab-input" defaultValue="Knez Mihajlova 36, Belgrade" />
                  </FieldRow>
                </>
              )}

              {active !== "schema" && active !== "org" && (
                <div style={{ padding: "40px 0", textAlign: "center", color: "var(--ab-text-muted)" }}>
                  <div style={{ fontSize: 36, marginBottom: 8 }}>{current?.icon}</div>
                  <div style={{ fontWeight: 600, fontSize: 15, marginBottom: 4 }}>{current?.label}</div>
                  <div className="ab-small">Configuration fields for this section would appear here.</div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </AbShell>
  )
}
