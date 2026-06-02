import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

const rules = [
  { id: 1, from: "/old-contact",        to: "/contact",        type: 301, hits: 47, enabled: true  },
  { id: 2, from: "/services/old-page",  to: "/services",       type: 301, hits: 22, enabled: true  },
  { id: 3, from: "/blog/removed-post",  to: "/blog",           type: 302, hits: 13, enabled: true  },
  { id: 4, from: "/about-us-old",       to: "/about",          type: 301, hits: 8,  enabled: false },
  { id: 5, from: "/en/tours",           to: "/tours",          type: 301, hits: 5,  enabled: true  },
]

/* Variant B: split panel — table on left, add-rule panel on right */
export function RedirectsB() {
  const { dark, setDark } = useTheme()
  const [selected, setSelected] = useState<number | null>(null)
  const [tab, setTab] = useState<"rules" | "log404">("rules")

  const sel = rules.find(r => r.id === selected)

  return (
    <AbShell activeNav="redirects" dark={dark} onThemeToggle={setDark}>
      <div style={{ padding: "16px 24px" }}>

        {/* Page header */}
        <div className="ab-row" style={{ marginBottom: 14 }}>
          <div>
            <h1 className="ab-page-title" style={{ margin: 0, fontSize: 16 }}>Redirects</h1>
            <p className="ab-page-sub" style={{ margin: "3px 0 0", fontSize: 12 }}>Manage HTTP redirects and 404 monitoring</p>
          </div>
          <div style={{ marginLeft: "auto", display: "flex", gap: 8 }}>
            <button className="ab-btn ab-btn--primary ab-btn--sm">+ New Rule</button>
            <button className="ab-btn ab-btn--ghost ab-btn--sm">⬇ Export</button>
            <button className="ab-btn ab-btn--ghost ab-btn--sm">⬆ Import CSV</button>
          </div>
        </div>

        {/* Sub-tabs */}
        <div className="ab-tabs" style={{ marginBottom: 14 }}>
          <button className={`ab-tab${tab === "rules" ? " active" : ""}`} onClick={() => setTab("rules")}>
            ↪ Rules <span className="ab-badge ab-badge--muted" style={{ marginLeft: 4 }}>{rules.length}</span>
          </button>
          <button className={`ab-tab${tab === "log404" ? " active" : ""}`} onClick={() => setTab("log404")}>
            ⚠ 404 Log <span className="ab-badge ab-badge--danger" style={{ marginLeft: 4 }}>3</span>
          </button>
        </div>

        <div style={{ display: "grid", gridTemplateColumns: sel ? "1fr 320px" : "1fr", gap: 14 }}>

          {/* Left: table */}
          <div className="ab-card">
            <div className="ab-card__header">
              <input className="ab-input" placeholder="Filter rules…" style={{ width: 220, padding: "4px 8px", fontSize: 12 }} />
              <span className="ab-text-muted ab-small" style={{ marginLeft: 8 }}>{rules.filter(r => r.enabled).length} active, {rules.filter(r => !r.enabled).length} disabled</span>
            </div>
            <table className="ab-table">
              <thead>
                <tr>
                  <th>From → To</th>
                  <th style={{ width: 60, textAlign: "center" }}>Type</th>
                  <th style={{ width: 55, textAlign: "right" }}>Hits</th>
                  <th style={{ width: 70, textAlign: "center" }}>Status</th>
                  <th style={{ width: 70 }}></th>
                </tr>
              </thead>
              <tbody>
                {rules.map(r => (
                  <tr key={r.id}
                    onClick={() => setSelected(selected === r.id ? null : r.id)}
                    style={{ cursor: "pointer", background: selected === r.id ? "rgba(13,110,253,.07)" : undefined }}
                  >
                    <td>
                      <div className="ab-small"><code className="ab-code" style={{ fontSize: 11 }}>{r.from}</code></div>
                      <div className="ab-small ab-text-muted">→ <code className="ab-code" style={{ fontSize: 11 }}>{r.to}</code></div>
                    </td>
                    <td style={{ textAlign: "center" }}>
                      <span className="ab-badge ab-badge--info">{r.type}</span>
                    </td>
                    <td style={{ textAlign: "right" }}>
                      <span className={`ab-badge ab-badge--${r.hits >= 20 ? "danger" : "muted"}`}>{r.hits}</span>
                    </td>
                    <td style={{ textAlign: "center" }}>
                      <span className={`ab-badge ab-badge--${r.enabled ? "success" : "muted"}`}>
                        {r.enabled ? "Active" : "Off"}
                      </span>
                    </td>
                    <td>
                      <button className="ab-btn ab-btn--danger-ghost ab-btn--sm"
                        onClick={e => { e.stopPropagation() }}>Del</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Right: edit/add panel */}
          {sel && (
            <div className="ab-card ab-stack" style={{ height: "fit-content" }}>
              <div className="ab-card__header">
                Edit Rule #{sel.id}
                <button onClick={() => setSelected(null)}
                  style={{ marginLeft: "auto", background: "none", border: "none", cursor: "pointer", color: "var(--ab-text-muted)", fontSize: 16 }}>✕</button>
              </div>
              <div className="ab-card__body ab-stack">
                <div>
                  <label className="ab-label">From URL</label>
                  <input className="ab-input" defaultValue={sel.from} />
                </div>
                <div>
                  <label className="ab-label">To URL</label>
                  <input className="ab-input" defaultValue={sel.to} />
                </div>
                <div>
                  <label className="ab-label">Redirect Type</label>
                  <select className="ab-input" defaultValue={sel.type}>
                    {[301, 302, 303, 307, 308].map(t => <option key={t}>{t}</option>)}
                  </select>
                </div>
                <div>
                  <label className="ab-label">Note (optional)</label>
                  <input className="ab-input" placeholder="Why this redirect?" />
                </div>
                <div style={{ display: "flex", alignItems: "center", gap: 8 }}>
                  <span className="ab-label" style={{ margin: 0 }}>Enabled</span>
                  <div style={{
                    width: 36, height: 20, borderRadius: 10,
                    background: sel.enabled ? "var(--ab-success)" : "var(--ab-border)",
                    position: "relative", cursor: "pointer"
                  }}>
                    <div style={{
                      width: 16, height: 16, borderRadius: "50%", background: "#fff",
                      position: "absolute", top: 2, left: sel.enabled ? 18 : 2, transition: "left .2s"
                    }} />
                  </div>
                </div>
              </div>
              <div className="ab-card__footer">
                <button className="ab-btn ab-btn--primary ab-btn--sm">Save Changes</button>
                <button className="ab-btn ab-btn--danger-ghost ab-btn--sm" style={{ marginLeft: "auto" }}>Delete</button>
              </div>
            </div>
          )}

          {!sel && (
            /* Add new rule inline card */
            <div className="ab-card" style={{ height: "fit-content" }}>
              <div className="ab-card__header">+ Quick Add Rule</div>
              <div className="ab-card__body ab-stack">
                <div>
                  <label className="ab-label">From URL</label>
                  <input className="ab-input" placeholder="/old-page" />
                </div>
                <div>
                  <label className="ab-label">To URL</label>
                  <input className="ab-input" placeholder="/new-page" />
                </div>
                <div style={{ display: "flex", gap: 8 }}>
                  <div style={{ flex: 1 }}>
                    <label className="ab-label">Type</label>
                    <select className="ab-input">
                      {[301, 302, 303, 307, 308].map(t => <option key={t}>{t}</option>)}
                    </select>
                  </div>
                </div>
                <button className="ab-btn ab-btn--primary">Add Rule</button>
                <p className="ab-small ab-text-muted" style={{ margin: 0 }}>Or click a row to edit it.</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </AbShell>
  )
}
