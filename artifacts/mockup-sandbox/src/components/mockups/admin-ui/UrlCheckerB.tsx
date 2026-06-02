import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

/* Variant B: Compact — URL bar + inline result rows (no full table), GSC compare prominent */

const RESULTS = [
  { url: "/",         status: 200, flags: [],                 canonical: true,  gsc: true  },
  { url: "/tours/",   status: 200, flags: ["thin-content"],   canonical: true,  gsc: true  },
  { url: "/contact/", status: 200, flags: [],                 canonical: true,  gsc: false },
  { url: "/old-page", status: 301, flags: ["redirect"],       canonical: false, gsc: false },
  { url: "/404-url",  status: 404, flags: ["error"],          canonical: false, gsc: false },
  { url: "/noindex",  status: 200, flags: ["noindex"],        canonical: true,  gsc: true  },
]

type FilterT = "all" | "issues" | "ok"

function StatusDot({ status }: { status: number }) {
  const c = status === 200 ? "var(--ab-success)" : status < 400 ? "var(--ab-info)" : "var(--ab-danger)"
  return <span style={{ width: 8, height: 8, borderRadius: "50%", background: c, display: "inline-block", flexShrink: 0 }} />
}

export function UrlCheckerB() {
  const { dark, setDark } = useTheme()
  const [filter, setFilter] = useState<FilterT>("all")
  const [expanded, setExpanded] = useState<number | null>(null)
  const [urlInput, setUrlInput] = useState("https://staging.offroadserbia.com/")
  const [scanning, setScanning] = useState(false)

  const shown = filter === "all" ? RESULTS
    : filter === "ok" ? RESULTS.filter(r => r.flags.length === 0 && r.status === 200)
    : RESULTS.filter(r => r.flags.length > 0 || r.status !== 200)

  const issueCount = RESULTS.filter(r => r.flags.length > 0 || r.status !== 200).length

  return (
    <AbShell activeNav="urlchecker" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page">
        <div className="ab-row" style={{ marginBottom: 14 }}>
          <div>
            <h1 className="ab-page-title" style={{ margin: 0 }}>URL Checker</h1>
            <p className="ab-page-sub" style={{ margin: "3px 0 0", fontSize: 12 }}>
              Scan up to 50 URLs per batch — status, redirects, canonical, thin content
            </p>
          </div>
          <div style={{ marginLeft: "auto", display: "flex", gap: 8 }}>
            <button className="ab-btn ab-btn--ghost ab-btn--sm">📄 Load Sitemap</button>
            <button className="ab-btn ab-btn--ghost ab-btn--sm">Compare GSC</button>
          </div>
        </div>

        {/* Compact URL input row */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__body" style={{ padding: "12px 16px" }}>
            <div style={{ display: "flex", gap: 0 }}>
              <textarea
                className="ab-input ab-input--mono"
                rows={3}
                value={urlInput}
                onChange={e => setUrlInput(e.target.value)}
                placeholder={"https://example.com/page-1\nhttps://example.com/page-2"}
                style={{ flex: 1, borderRadius: "6px 0 0 6px", resize: "none", borderRight: "none" }}
              />
              <button
                className={`ab-btn ${scanning ? "ab-btn--ghost" : "ab-btn--success"}`}
                style={{ borderRadius: "0 6px 6px 0", padding: "0 16px", alignSelf: "stretch" }}
                onClick={() => setScanning(s => !s)}
              >
                {scanning ? "↻ Scanning…" : "▶ Scan"}
              </button>
            </div>
            <div style={{ marginTop: 8, display: "flex", gap: 6, alignItems: "center" }}>
              <span className="ab-small ab-text-muted">{RESULTS.length} URLs queued</span>
              <div style={{ marginLeft: "auto", display: "flex", gap: 4 }}>
                {(["all", "issues", "ok"] as FilterT[]).map(f => (
                  <button
                    key={f}
                    onClick={() => setFilter(f)}
                    className={`ab-btn ab-btn--sm ${filter === f ? "ab-btn--subtle" : "ab-btn--ghost"}`}
                    style={{ fontSize: 11.5, padding: "2px 9px" }}
                  >
                    {f === "issues" ? `Issues (${issueCount})` : f === "ok" ? `OK (${RESULTS.length - issueCount})` : "All"}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Summary badges */}
        <div style={{ display: "flex", gap: 8, flexWrap: "wrap", marginBottom: 14 }}>
          {[
            { label: "200 OK",      count: RESULTS.filter(r => r.status === 200 && !r.flags.includes("noindex") && !r.flags.includes("thin-content")).length, variant: "success" },
            { label: "3xx Redirect", count: RESULTS.filter(r => r.flags.includes("redirect")).length,      variant: "info"    },
            { label: "4xx/5xx",      count: RESULTS.filter(r => r.flags.includes("error")).length,         variant: "danger"  },
            { label: "Noindex",      count: RESULTS.filter(r => r.flags.includes("noindex")).length,       variant: "warning" },
            { label: "Thin content", count: RESULTS.filter(r => r.flags.includes("thin-content")).length,  variant: "warning" },
          ].map(s => (
            <div key={s.label} style={{
              display: "flex", alignItems: "center", gap: 8,
              padding: "8px 14px", borderRadius: 7,
              border: "1px solid var(--ab-card-border)",
              background: "var(--ab-surface)",
              fontSize: 12
            }}>
              <span style={{ fontWeight: 700, fontSize: 18, color: `var(--ab-${s.variant === "success" ? "success" : s.variant === "danger" ? "danger" : s.variant === "info" ? "info" : "warning"})` }}>
                {s.count}
              </span>
              <span className="ab-text-muted">{s.label}</span>
            </div>
          ))}
        </div>

        {/* Compact result rows */}
        <div className="ab-card">
          <div className="ab-card__header">
            Results
            <span className="ab-text-muted ab-small" style={{ marginLeft: 6 }}>
              {shown.length} of {RESULTS.length} shown
            </span>
            <button className="ab-btn ab-btn--ghost ab-btn--sm" style={{ marginLeft: "auto" }}>⬇ Export CSV</button>
          </div>
          {shown.map((r, i) => (
            <div key={i}>
              {/* Main row */}
              <div
                style={{
                  display: "flex", alignItems: "center", gap: 10,
                  padding: "9px 16px",
                  borderBottom: "1px solid var(--ab-border)",
                  cursor: "pointer",
                  background: expanded === i ? "var(--ab-surface-raised)" : undefined
                }}
                onClick={() => setExpanded(expanded === i ? null : i)}
              >
                <StatusDot status={r.status} />
                <code className="ab-code" style={{ flex: 1, fontSize: 12 }}>
                  {r.url}
                </code>
                <span className={`ab-badge ab-badge--${r.status === 200 ? "success" : r.status < 400 ? "info" : "danger"}`}>
                  {r.status}
                </span>
                {r.flags.map(f => (
                  <span key={f} className={`ab-badge ab-badge--${f === "error" ? "danger" : f === "redirect" ? "info" : "warning"}`}
                    style={{ fontSize: 10 }}>
                    {f}
                  </span>
                ))}
                {r.flags.length === 0 && r.status === 200 && (
                  <span className="ab-badge ab-badge--success" style={{ fontSize: 10 }}>clean</span>
                )}
                <span style={{ color: "var(--ab-text-muted)", fontSize: 12, marginLeft: 4 }}>
                  {expanded === i ? "▴" : "▾"}
                </span>
              </div>

              {/* Expanded detail */}
              {expanded === i && (
                <div style={{
                  padding: "10px 16px 12px 34px",
                  background: "var(--ab-surface-raised)",
                  borderBottom: "1px solid var(--ab-border)",
                  fontSize: 12
                }}>
                  <div style={{ display: "grid", gridTemplateColumns: "repeat(4, 1fr)", gap: 12 }}>
                    <div>
                      <div className="ab-text-muted" style={{ marginBottom: 3 }}>HTTP Status</div>
                      <strong style={{ color: r.status === 200 ? "var(--ab-success)" : "var(--ab-danger)" }}>{r.status}</strong>
                    </div>
                    <div>
                      <div className="ab-text-muted" style={{ marginBottom: 3 }}>Canonical</div>
                      <strong>{r.canonical ? "✓ Self" : "✗ Missing"}</strong>
                    </div>
                    <div>
                      <div className="ab-text-muted" style={{ marginBottom: 3 }}>In Google SC</div>
                      <strong style={{ color: r.gsc ? "var(--ab-success)" : "var(--ab-warning)" }}>
                        {r.gsc ? "✓ Indexed" : "⚠ Not found"}
                      </strong>
                    </div>
                    <div>
                      <div className="ab-text-muted" style={{ marginBottom: 3 }}>Redirect chain</div>
                      <strong>{r.flags.includes("redirect") ? "1 hop" : "—"}</strong>
                    </div>
                  </div>
                  {r.flags.includes("error") && (
                    <div className="ab-alert ab-alert--danger" style={{ marginTop: 8, padding: "6px 10px" }}>
                      This URL returned a 404 — consider adding a redirect from this path.
                    </div>
                  )}
                  {r.flags.includes("thin-content") && (
                    <div className="ab-alert ab-alert--warning" style={{ marginTop: 8, padding: "6px 10px" }}>
                      Thin content detected (&lt;300 words) — consider expanding this page.
                    </div>
                  )}
                </div>
              )}
            </div>
          ))}
        </div>

        <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
          © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a>
        </p>
      </div>
    </AbShell>
  )
}
