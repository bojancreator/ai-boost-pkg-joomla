import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

const plugins = [
  { id: "schema",    name: "Schema.org",  color: "#8b5cf6", icon: "{ }", enabled: true,  tab: "schema"    },
  { id: "sitemap",   name: "Sitemap",     color: "#14b8a6", icon: "≡",   enabled: true,  tab: "sitemap"   },
  { id: "social",    name: "Social",      color: "#ec4899", icon: "⇄",   enabled: true,  tab: "social"    },
  { id: "analytics", name: "Analytics",   color: "#f97316", icon: "▦",   enabled: false, tab: "analytics" },
  { id: "aeo",       name: "AEO",         color: "#06b6d4", icon: "⚡",  enabled: true,  tab: "aeo"       },
  { id: "perf",      name: "Perf",        color: "#10b981", icon: "↯",   enabled: true,  tab: "general"   },
]

export function DashboardB() {
  const { dark, setDark } = useTheme()
  const enabledCount = plugins.filter(p => p.enabled).length

  return (
    <AbShell activeNav="dashboard" dark={dark} onThemeToggle={setDark} version="v0.15.0">
      <div className="ab-page">

        {/* Command-centre summary strip */}
        <div style={{
          display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 12, marginBottom: 20
        }}>
          {[
            { label: "Plugins Active",   value: `${enabledCount}/6`,  sub: "1 disabled",  color: "var(--ab-success)",  icon: "🧩" },
            { label: "Health Score",     value: "82",                  sub: "Good",         color: "var(--ab-success)",  icon: "♥"  },
            { label: "Redirect Rules",   value: "12",                  sub: "3 hits today", color: "var(--ab-primary)",  icon: "↪"  },
            { label: "404 Errors",       value: "47",                  sub: "Needs action", color: "var(--ab-danger)",   icon: "⚠"  },
          ].map(s => (
            <div key={s.label} className="ab-card">
              <div className="ab-card__body" style={{ padding: "14px 16px" }}>
                <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                  <div style={{
                    width: 38, height: 38, borderRadius: 8,
                    background: s.color + "18", display: "flex",
                    alignItems: "center", justifyContent: "center",
                    fontSize: 17, flexShrink: 0
                  }}>{s.icon}</div>
                  <div>
                    <div style={{ fontSize: 22, fontWeight: 800, color: s.color, lineHeight: 1 }}>{s.value}</div>
                    <div style={{ fontSize: 11, color: "var(--ab-text-muted)", marginTop: 2 }}>{s.label}</div>
                  </div>
                </div>
                <div style={{ fontSize: 11, color: "var(--ab-text-subtle)", marginTop: 8 }}>{s.sub}</div>
              </div>
            </div>
          ))}
        </div>

        <div style={{ display: "grid", gridTemplateColumns: "1fr 280px", gap: 16 }}>
          {/* Left column — Plugin grid */}
          <div>
            <div className="ab-card" style={{ marginBottom: 16 }}>
              <div className="ab-card__header">🧩 Module Status</div>
              <div className="ab-card__body">
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: 10 }}>
                  {plugins.map(p => (
                    <div key={p.id} style={{
                      display: "flex", alignItems: "center", gap: 10,
                      padding: "10px 12px",
                      borderRadius: 7,
                      border: `1px solid var(--ab-card-border)`,
                      background: "var(--ab-surface-raised)",
                    }}>
                      <div style={{
                        width: 32, height: 32, borderRadius: 7,
                        background: p.color + "22", color: p.color,
                        display: "flex", alignItems: "center", justifyContent: "center",
                        fontWeight: 700, fontSize: 13, fontFamily: "monospace",
                        flexShrink: 0
                      }}>{p.icon}</div>
                      <div style={{ flex: 1, minWidth: 0 }}>
                        <div style={{ fontWeight: 600, fontSize: 13, color: "var(--ab-text)" }}>{p.name}</div>
                        <div style={{ fontSize: 11, color: "var(--ab-text-muted)", marginTop: 1 }}>
                          {p.enabled ? (
                            <span style={{ color: "var(--ab-success)" }}>● Enabled</span>
                          ) : (
                            <span style={{ color: "var(--ab-danger)" }}>● Disabled</span>
                          )}
                        </div>
                      </div>
                      <div style={{ display: "flex", flexDirection: "column", gap: 4, flexShrink: 0 }}>
                        {p.enabled ? (
                          <button className="ab-btn ab-btn--ghost ab-btn--sm">Disable</button>
                        ) : (
                          <button className="ab-btn ab-btn--success ab-btn--sm">Enable</button>
                        )}
                        <a href="#" className="ab-configure-link" style={{ justifyContent: "center" }}>⚙ Config</a>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* 404 table */}
            <div className="ab-card">
              <div className="ab-card__header">
                ⚠ Top 404 Errors
                <span className="ab-badge ab-badge--danger" style={{ marginLeft: 8 }}>47 unique</span>
                <button className="ab-btn ab-btn--ghost ab-btn--sm" style={{ marginLeft: "auto" }}>Manage all →</button>
              </div>
              <table className="ab-table">
                <thead>
                  <tr>
                    <th>URL</th>
                    <th style={{ width: 60, textAlign: "right" }}>Hits</th>
                    <th style={{ width: 90 }}>Last seen</th>
                    <th style={{ width: 90 }}></th>
                  </tr>
                </thead>
                <tbody>
                  {[
                    { url: "/old-contact", hits: 47, last: "2026-05-19" },
                    { url: "/services/old", hits: 22, last: "2026-05-18" },
                    { url: "/blog/removed", hits: 13, last: "2026-05-17" },
                  ].map(r => (
                    <tr key={r.url}>
                      <td><code className="ab-code">{r.url}</code></td>
                      <td style={{ textAlign: "right" }}>
                        <span className={`ab-badge ab-badge--${r.hits >= 20 ? "danger" : "warning"}`}>{r.hits}</span>
                      </td>
                      <td className="ab-small ab-text-muted">{r.last}</td>
                      <td><button className="ab-btn ab-btn--subtle ab-btn--sm">+ Redirect</button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* Right sidebar */}
          <div className="ab-stack">
            {/* Quick actions */}
            <div className="ab-card">
              <div className="ab-card__header">⚡ Quick Actions</div>
              <div className="ab-card__body" style={{ padding: "12px" }}>
                <div className="ab-stack" style={{ gap: 6 }}>
                  {[
                    { icon: "⚙", label: "Open Settings",        cls: "ab-btn--primary" },
                    { icon: "♥", label: "Run Health Check",      cls: "ab-btn--ghost"   },
                    { icon: "↪", label: "Redirect Manager",      cls: "ab-btn--ghost"   },
                    { icon: "⬆", label: "Import from Old Plugin", cls: "ab-btn--ghost"   },
                    { icon: "🧩", label: "Manage Plugins",        cls: "ab-btn--ghost"   },
                  ].map(a => (
                    <button key={a.label} className={`ab-btn ${a.cls}`} style={{ justifyContent: "flex-start", width: "100%" }}>
                      <span>{a.icon}</span> {a.label}
                    </button>
                  ))}
                </div>
              </div>
            </div>

            {/* Settings status */}
            <div className="ab-card ab-card--success">
              <div className="ab-card__body" style={{ padding: "12px 14px" }}>
                <div style={{ fontWeight: 600, fontSize: 13, marginBottom: 6, color: "var(--ab-success)" }}>
                  ✓ Settings active
                </div>
                <div className="ab-small ab-text-muted">
                  All 6 plugins reading from <code className="ab-code">#__aiboost_settings</code>
                </div>
                <div className="ab-sep" style={{ margin: "10px 0" }} />
                <div className="ab-small ab-text-muted">Last saved: <strong>2026-05-19 14:32</strong></div>
                <div className="ab-small" style={{ marginTop: 4 }}>
                  <span className="ab-badge ab-badge--primary">🌐 Multilingual · 3 languages</span>
                </div>
              </div>
            </div>

            {/* Conflicts clear */}
            <div className="ab-card ab-card--success">
              <div className="ab-card__body" style={{ padding: "10px 14px", display: "flex", gap: 8 }}>
                <span style={{ color: "var(--ab-success)" }}>✓</span>
                <span className="ab-small ab-text-muted">No plugin conflicts detected.</span>
              </div>
            </div>
          </div>
        </div>

        <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
          © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Upgrade</a>
        </p>
      </div>
    </AbShell>
  )
}
