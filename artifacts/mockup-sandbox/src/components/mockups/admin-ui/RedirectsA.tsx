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

const log404 = [
  { url: "/missing-page",   referrer: "google.com",  hits: 31, last: "2026-05-19" },
  { url: "/old/path",        referrer: "facebook.com", hits: 14, last: "2026-05-18" },
  { url: "/404-url",         referrer: "—",            hits: 7,  last: "2026-05-17" },
]

export function RedirectsA() {
  const { dark, setDark } = useTheme()
  const [tab, setTab] = useState("rules")
  const [form, setForm] = useState({ from: "", to: "", type: "301", note: "" })

  return (
    <AbShell activeNav="redirects" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page">
        <div className="ab-row" style={{ marginBottom: 16 }}>
          <h1 className="ab-page-title" style={{ margin: 0 }}>Redirects</h1>
        </div>

        {/* Tabs */}
        <div className="ab-tabs">
          <button className={`ab-tab${tab === "rules" ? " active" : ""}`} onClick={() => setTab("rules")}>
            ↪ Redirect Rules <span className="ab-badge ab-badge--muted" style={{ marginLeft: 4 }}>{rules.length}</span>
          </button>
          <button className={`ab-tab${tab === "log404" ? " active" : ""}`} onClick={() => setTab("log404")}>
            ⚠ 404 Log <span className="ab-badge ab-badge--danger" style={{ marginLeft: 4 }}>{log404.length}</span>
          </button>
          <button className={`ab-tab${tab === "import" ? " active" : ""}`} onClick={() => setTab("import")}>
            ⬆ CSV Import
          </button>
        </div>

        {tab === "rules" && (
          <>
            {/* Add rule form */}
            <div className="ab-card" style={{ marginBottom: 14 }}>
              <div className="ab-card__header">+ Add new rule</div>
              <div className="ab-card__body">
                <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr 120px 120px", gap: 10, marginBottom: 10 }}>
                  <div>
                    <label className="ab-label">From URL</label>
                    <input className="ab-input" placeholder="/old-page" value={form.from}
                      onChange={e => setForm(f => ({ ...f, from: e.target.value }))} />
                  </div>
                  <div>
                    <label className="ab-label">To URL</label>
                    <input className="ab-input" placeholder="/new-page" value={form.to}
                      onChange={e => setForm(f => ({ ...f, to: e.target.value }))} />
                  </div>
                  <div>
                    <label className="ab-label">Type</label>
                    <select className="ab-input" value={form.type}
                      onChange={e => setForm(f => ({ ...f, type: e.target.value }))}>
                      {["301", "302", "303", "307", "308"].map(t => <option key={t}>{t}</option>)}
                    </select>
                  </div>
                  <div style={{ display: "flex", alignItems: "flex-end" }}>
                    <button className="ab-btn ab-btn--primary" style={{ width: "100%" }}>Add Rule</button>
                  </div>
                </div>
                <input className="ab-input" placeholder="Optional note…" value={form.note}
                  onChange={e => setForm(f => ({ ...f, note: e.target.value }))} />
              </div>
            </div>

            {/* Rules table */}
            <div className="ab-card">
              <div className="ab-card__header">
                Active Rules
                <span className="ab-badge ab-badge--muted" style={{ marginLeft: 8 }}>{rules.filter(r => r.enabled).length} active</span>
                <div style={{ marginLeft: "auto", display: "flex", gap: 6 }}>
                  <input className="ab-input" placeholder="Search rules…" style={{ width: 200 }} />
                  <button className="ab-btn ab-btn--ghost ab-btn--sm">⬇ Export CSV</button>
                </div>
              </div>
              <table className="ab-table">
                <thead>
                  <tr>
                    <th>From</th>
                    <th>To</th>
                    <th style={{ width: 70, textAlign: "center" }}>Type</th>
                    <th style={{ width: 60, textAlign: "right" }}>Hits</th>
                    <th style={{ width: 80, textAlign: "center" }}>Active</th>
                    <th style={{ width: 80 }}></th>
                  </tr>
                </thead>
                <tbody>
                  {rules.map(r => (
                    <tr key={r.id}>
                      <td><code className="ab-code">{r.from}</code></td>
                      <td><code className="ab-code">{r.to}</code></td>
                      <td style={{ textAlign: "center" }}>
                        <span className="ab-badge ab-badge--info">{r.type}</span>
                      </td>
                      <td style={{ textAlign: "right" }}>
                        <span className={`ab-badge ab-badge--${r.hits >= 20 ? "danger" : "muted"}`}>{r.hits}</span>
                      </td>
                      <td style={{ textAlign: "center" }}>
                        <div style={{
                          width: 32, height: 18, borderRadius: 9,
                          background: r.enabled ? "var(--ab-success)" : "var(--ab-border)",
                          position: "relative", cursor: "pointer", transition: "background .2s"
                        }}>
                          <div style={{
                            width: 14, height: 14, borderRadius: "50%", background: "#fff",
                            position: "absolute", top: 2,
                            left: r.enabled ? 16 : 2,
                            transition: "left .2s"
                          }} />
                        </div>
                      </td>
                      <td style={{ textAlign: "right" }}>
                        <button className="ab-btn ab-btn--danger-ghost ab-btn--sm">Delete</button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}

        {tab === "log404" && (
          <div className="ab-card">
            <div className="ab-card__header">
              Recent 404 errors
              <button className="ab-btn ab-btn--danger-ghost ab-btn--sm" style={{ marginLeft: "auto" }}>Clear 404 Log</button>
            </div>
            <table className="ab-table">
              <thead>
                <tr>
                  <th>URL</th>
                  <th>Referrer</th>
                  <th style={{ width: 60, textAlign: "right" }}>Hits</th>
                  <th style={{ width: 100 }}>Last seen</th>
                  <th style={{ width: 110 }}></th>
                </tr>
              </thead>
              <tbody>
                {log404.map(r => (
                  <tr key={r.url}>
                    <td><code className="ab-code">{r.url}</code></td>
                    <td className="ab-small ab-text-muted">{r.referrer}</td>
                    <td style={{ textAlign: "right" }}>
                      <span className="ab-badge ab-badge--warning">{r.hits}</span>
                    </td>
                    <td className="ab-small ab-text-muted">{r.last}</td>
                    <td style={{ textAlign: "right" }}>
                      <button className="ab-btn ab-btn--subtle ab-btn--sm">+ Add Redirect</button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {tab === "import" && (
          <div className="ab-card">
            <div className="ab-card__header">⬆ CSV Import</div>
            <div className="ab-card__body">
              <p className="ab-text-muted ab-small" style={{ marginBottom: 14 }}>
                Import redirect rules from a CSV file. Format: <code className="ab-code">from_url,to_url,type</code>
              </p>
              <div style={{
                border: "2px dashed var(--ab-border)", borderRadius: 8,
                padding: "32px 24px", textAlign: "center",
                color: "var(--ab-text-muted)", fontSize: 13
              }}>
                <div style={{ fontSize: 28, marginBottom: 8 }}>⬆</div>
                Drop CSV file here or <button className="ab-btn ab-btn--subtle ab-btn--sm">Browse</button>
              </div>
              <div style={{ marginTop: 14, display: "flex", gap: 8 }}>
                <button className="ab-btn ab-btn--primary" disabled>Import</button>
                <button className="ab-btn ab-btn--ghost">⬇ Download Template</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </AbShell>
  )
}
