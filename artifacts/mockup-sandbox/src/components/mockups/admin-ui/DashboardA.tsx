import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

const plugins = [
  { id: "schema",    name: "Schema.org",    desc: "Structured data for AI & search engines", color: "#8b5cf6", icon: "{ }", enabled: true  },
  { id: "sitemap",   name: "Sitemap",        desc: "XML sitemap + hreflang support",          color: "#14b8a6", icon: "≡",   enabled: true  },
  { id: "social",    name: "Social",         desc: "OpenGraph & Twitter Cards",               color: "#ec4899", icon: "⇄",   enabled: true  },
  { id: "analytics", name: "Analytics",      desc: "GA4, GTM, Meta Pixel injection",          color: "#f97316", icon: "▦",   enabled: false },
  { id: "aeo",       name: "AEO",            desc: "llms.txt, IndexNow, AI signals",          color: "#06b6d4", icon: "⚡",  enabled: true  },
  { id: "perf",      name: "Performance",    desc: "Canonical URL management",                color: "#10b981", icon: "↯",   enabled: true  },
]

const top404 = [
  { url: "/old-contact",   hits: 47, last: "2026-05-19" },
  { url: "/services/old",  hits: 22, last: "2026-05-18" },
  { url: "/blog/removed",  hits: 13, last: "2026-05-17" },
]

export function DashboardA() {
  const { dark, setDark } = useTheme()
  const [confirming, setConfirming] = useState<string | null>(null)

  const enabledCount = plugins.filter(p => p.enabled).length

  return (
    <AbShell activeNav="dashboard" dark={dark} onThemeToggle={setDark} version="v0.15.0">
      <div className="ab-page">
        {/* Status bar */}
        <div className="ab-alert ab-alert--success" style={{ marginBottom: 16 }}>
          <span>✓</span>
          <div style={{ flex: 1 }}>
            <strong>Settings active</strong> — {enabledCount}/{plugins.length} plugins enabled, reading from <code className="ab-code">#__aiboost_settings</code>.
          </div>
          <span style={{ fontSize: 12, color: "var(--ab-text-muted)", flexShrink: 0 }}>Last saved: <strong>2026-05-19 14:32</strong></span>
        </div>

        {/* Plugin status grid */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__header">
            <span>🧩</span> Module Status
            <span style={{ marginLeft: "auto", fontSize: 12 }} className="ab-text-muted">{enabledCount}/{plugins.length} active</span>
          </div>
          <div className="ab-card__body">
            <div className="ab-grid-3" style={{ gap: 12 }}>
              {plugins.map(p => (
                <div className="ab-plugin-card" key={p.id}
                  style={{ borderLeft: `3px solid ${p.color}` }}>
                  <div className="ab-plugin-card__top">
                    <div className="ab-plugin-icon-wrap"
                      style={{ background: p.color + "1a", color: p.color }}>
                      <span style={{ fontFamily: "monospace", fontWeight: 700, fontSize: 14 }}>{p.icon}</span>
                    </div>
                    <div style={{ flex: 1, minWidth: 0 }}>
                      <div className="ab-plugin-card__name">{p.name}</div>
                      <div style={{ marginTop: 2 }}>
                        <span className={`ab-badge ab-badge--${p.enabled ? "success" : "danger"}`}>
                          {p.enabled ? "Enabled" : "Disabled"}
                        </span>
                      </div>
                    </div>
                  </div>
                  <div className="ab-plugin-card__desc">{p.desc}</div>
                  <div className="ab-plugin-card__footer">
                    {p.enabled ? (
                      <button
                        className={`ab-btn ab-btn--sm ${confirming === p.id ? "ab-btn--danger" : "ab-btn--ghost"}`}
                        onClick={() => setConfirming(confirming === p.id ? null : p.id)}
                      >
                        {confirming === p.id ? "Confirm Disable" : "Disable"}
                      </button>
                    ) : (
                      <button className="ab-btn ab-btn--sm ab-btn--success">Enable</button>
                    )}
                    <a href="#" className="ab-configure-link">⚙ Configure</a>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Quick actions */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__header"><span>⚡</span> Quick Actions</div>
          <div className="ab-card__body">
            <div className="ab-cluster">
              <button className="ab-btn ab-btn--primary">⚙ Open Settings</button>
              <button className="ab-btn ab-btn--ghost">↪ Redirect Manager <span className="ab-badge ab-badge--muted" style={{ marginLeft: 4 }}>3</span></button>
              <button className="ab-btn ab-btn--ghost">⬆ Import from Old Plugin</button>
              <button className="ab-btn ab-btn--ghost">🧩 Manage Plugins</button>
            </div>
          </div>
        </div>

        {/* Top 404 errors */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__header">
            <span>⚠</span> Top 404 Errors
            <span className="ab-badge ab-badge--danger" style={{ marginLeft: 8 }}>47 unique URLs</span>
            <button className="ab-btn ab-btn--ghost ab-btn--sm" style={{ marginLeft: "auto" }}>View all &amp; manage redirects →</button>
          </div>
          <table className="ab-table">
            <thead>
              <tr>
                <th>404 URL</th>
                <th style={{ width: 70, textAlign: "right" }}>Hits</th>
                <th style={{ width: 110 }}>Last seen</th>
                <th style={{ width: 100 }}></th>
              </tr>
            </thead>
            <tbody>
              {top404.map(r => (
                <tr key={r.url}>
                  <td><code className="ab-code">{r.url}</code></td>
                  <td style={{ textAlign: "right" }}>
                    <span className={`ab-badge ab-badge--${r.hits >= 20 ? "danger" : "warning"}`}>{r.hits}</span>
                  </td>
                  <td className="ab-text-muted ab-small">{r.last}</td>
                  <td>
                    <button className="ab-btn ab-btn--subtle ab-btn--sm">+ Redirect</button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Conflicts — no issues */}
        <div className="ab-card" style={{ marginBottom: 0 }}>
          <div className="ab-card__body" style={{ display: "flex", alignItems: "center", gap: 8, padding: "10px 16px" }}>
            <span style={{ color: "var(--ab-success)", fontSize: 15 }}>✓</span>
            <span className="ab-small ab-text-muted">No plugin conflicts detected. <a href="#" style={{ color: "var(--ab-primary)" }}>View full Health report</a></span>
          </div>
        </div>

        <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
          © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Upgrade license</a>
        </p>
      </div>
    </AbShell>
  )
}
