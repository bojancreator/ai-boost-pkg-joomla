import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

const DOCS = [
  { id: "getting-started", label: "Getting Started",    icon: "🚀" },
  { id: "schema",          label: "Schema.org Guide",   icon: "{ }" },
  { id: "sitemap",         label: "Sitemap",             icon: "≡" },
  { id: "social",          label: "Social / OG Tags",   icon: "⇄" },
  { id: "analytics",       label: "Analytics",           icon: "▦" },
  { id: "aeo",             label: "AEO & llms.txt",      icon: "⚡" },
  { id: "redirects",       label: "Redirects",           icon: "↪" },
  { id: "faq",             label: "FAQ",                 icon: "❓" },
  { id: "changelog",       label: "Changelog",           icon: "📋" },
]

const STEPS = [
  { n: 1, title: "Install the package", text: "Install pkg_aiboost via Joomla Extension Manager — installs the component and all 6 plugins in one step." },
  { n: 2, title: "Enable the plugins",  text: "Go to Extensions > Plugins, search for AI Boost, and enable the ones you need. Start with aiboost_schema and aiboost_social." },
  { n: 3, title: "Configure Organisation", text: "In Settings → Organisation, fill in your site name, URL, logo, and contact details. This data is shared across all schema types." },
  { n: 4, title: "Set your Site Type",  text: "In Settings → General, select the site type that best describes your business (Restaurant, Hotel, Doctor, etc.)." },
  { n: 5, title: "Run a Health Check",  text: "Visit the Health tab to see your score and list of issues to resolve." },
  { n: 6, title: "Run the Analyzers",   text: "Use the Analyzers tab to check SEO, JSON-LD, and AI Visibility scores for your site." },
]

export function HelpA() {
  const { dark, setDark } = useTheme()
  const [activeDoc, setActiveDoc] = useState<string | null>(null)

  return (
    <AbShell activeNav="help" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page">

        {/* Quick Start */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__header">🚀 Quick Start</div>
          <div className="ab-card__body">
            <p className="ab-text-muted ab-small" style={{ marginBottom: 16 }}>
              New to AI Boost? Follow these steps to get up and running in minutes.
            </p>
            <div className="ab-stack" style={{ gap: 12 }}>
              {STEPS.map(s => (
                <div key={s.n} style={{ display: "flex", gap: 12 }}>
                  <div style={{
                    width: 26, height: 26, borderRadius: "50%",
                    background: "var(--ab-primary)", color: "#fff",
                    display: "flex", alignItems: "center", justifyContent: "center",
                    fontWeight: 700, fontSize: 12, flexShrink: 0, marginTop: 1
                  }}>{s.n}</div>
                  <div>
                    <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 2 }}>{s.title}</div>
                    <div className="ab-small ab-text-muted">{s.text}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Video placeholder */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__header">▶ Video Walkthrough</div>
          <div className="ab-card__body">
            <div style={{
              background: "var(--ab-surface-raised)", border: "1px solid var(--ab-border)",
              borderRadius: 7, padding: "36px 24px",
              display: "flex", flexDirection: "column", alignItems: "center", gap: 12,
              textAlign: "center"
            }}>
              <div style={{
                width: 56, height: 56, borderRadius: "50%",
                background: "#ff0000", display: "flex", alignItems: "center", justifyContent: "center"
              }}>
                <div style={{
                  width: 0, height: 0,
                  borderTop: "10px solid transparent",
                  borderBottom: "10px solid transparent",
                  borderLeft: "18px solid #fff",
                  marginLeft: 4
                }} />
              </div>
              <div>
                <div style={{ fontWeight: 600, fontSize: 14, marginBottom: 4 }}>Video walkthrough coming soon</div>
                <div className="ab-small ab-text-muted" style={{ maxWidth: 380 }}>
                  Subscribe to the AI Boost YouTube channel to be notified when the setup tutorial is published.
                </div>
              </div>
              <div style={{ display: "flex", gap: 8 }}>
                <a href="#" className="ab-btn ab-btn--danger ab-btn--sm">Subscribe on YouTube</a>
                <a href="#" className="ab-btn ab-btn--ghost ab-btn--sm">📖 Read Getting Started Guide</a>
              </div>
            </div>
          </div>
        </div>

        {/* Documentation grid */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__header">📚 Plugin Documentation</div>
          <div className="ab-card__body">
            <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 8 }}>
              {DOCS.map(d => (
                <button
                  key={d.id}
                  onClick={() => setActiveDoc(activeDoc === d.id ? null : d.id)}
                  style={{
                    display: "flex", alignItems: "center", gap: 8,
                    padding: "10px 12px",
                    border: `1px solid ${activeDoc === d.id ? "var(--ab-primary)" : "var(--ab-border)"}`,
                    borderRadius: 6, background: activeDoc === d.id ? "rgba(13,110,253,.06)" : "var(--ab-surface-raised)",
                    cursor: "pointer",
                    color: activeDoc === d.id ? "var(--ab-primary)" : "var(--ab-text)",
                    fontSize: 13, fontWeight: 500,
                    textAlign: "left",
                    transition: "all .12s"
                  }}
                >
                  <span style={{ fontSize: 16 }}>{d.icon}</span>
                  {d.label}
                </button>
              ))}
            </div>
            {activeDoc && (
              <div style={{
                marginTop: 14, padding: "14px 16px",
                background: "var(--ab-surface-raised)", borderRadius: 7,
                border: "1px solid var(--ab-border)"
              }}>
                <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 6 }}>
                  {DOCS.find(d => d.id === activeDoc)?.icon} {DOCS.find(d => d.id === activeDoc)?.label}
                </div>
                <p className="ab-small ab-text-muted" style={{ marginBottom: 10 }}>
                  Full documentation is available on the AI Boost website. Click below to open in a new tab.
                </p>
                <a href="#" className="ab-btn ab-btn--ghost ab-btn--sm">↗ Open on aiboostnow.com/docs</a>
              </div>
            )}
          </div>
        </div>

        {/* Support + links */}
        <div className="ab-grid-2" style={{ gap: 14 }}>
          <div className="ab-card">
            <div className="ab-card__header">💬 Support</div>
            <div className="ab-card__body ab-stack" style={{ gap: 8 }}>
              {[
                { icon: "📖", label: "Full documentation",  sub: "aiboostnow.com/docs",    href: "#" },
                { icon: "💬", label: "Joomla forum thread", sub: "forum.joomla.org",        href: "#" },
                { icon: "📧", label: "Email support",       sub: "support@aiboostnow.com", href: "#" },
              ].map(l => (
                <a key={l.label} href={l.href} style={{ display: "flex", gap: 10, alignItems: "center", textDecoration: "none", padding: "8px", borderRadius: 6, border: "1px solid var(--ab-border)" }}>
                  <span style={{ fontSize: 18 }}>{l.icon}</span>
                  <div>
                    <div style={{ fontSize: 13, fontWeight: 500, color: "var(--ab-text)" }}>{l.label}</div>
                    <div className="ab-small" style={{ color: "var(--ab-primary)" }}>{l.sub}</div>
                  </div>
                </a>
              ))}
            </div>
          </div>

          <div className="ab-card">
            <div className="ab-card__header">ℹ System Info</div>
            <div className="ab-card__body">
              {[
                ["AI Boost version",  "v0.15.0"],
                ["Joomla version",    "6.1.2"],
                ["PHP version",       "8.3.6"],
                ["License tier",      "Pro"],
                ["License expires",   "2027-05-01"],
                ["Plugins installed", "6/6"],
              ].map(([k, v]) => (
                <div key={k} style={{ display: "flex", justifyContent: "space-between", padding: "7px 0", borderBottom: "1px solid var(--ab-border)", fontSize: 13 }}>
                  <span className="ab-text-muted">{k}</span>
                  <strong style={{ color: "var(--ab-text)" }}>{v}</strong>
                </div>
              ))}
            </div>
          </div>
        </div>

        <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
          © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a>
        </p>
      </div>
    </AbShell>
  )
}
