import { useState } from "react"
import "./_shared/tokens.css"

/* mod_aiboost_health — Variant B: compact score tile + expandable issues */

function ScoreArc({ score }: { score: number }) {
  const r = 30
  const circ = 2 * Math.PI * r
  const offset = circ * (1 - score / 100)
  const c = score >= 80 ? "var(--ab-score-good)" : score >= 50 ? "var(--ab-score-ok)" : "var(--ab-score-poor)"
  return (
    <svg width="76" height="76" viewBox="0 0 76 76" style={{ flexShrink: 0 }}>
      <circle cx="38" cy="38" r={r} fill="none" stroke="var(--ab-border)" strokeWidth="5" />
      <circle cx="38" cy="38" r={r} fill="none" stroke={c} strokeWidth="5"
        strokeDasharray={circ} strokeDashoffset={offset}
        strokeLinecap="round" transform="rotate(-90 38 38)"
        style={{ transition: "stroke-dashoffset .6s ease-out" }}
      />
      <text x="38" y="38" textAnchor="middle" dominantBaseline="central"
        style={{ fontSize: "14px", fontWeight: 800, fill: c, fontFamily: "system-ui" }}>
        {score}
      </text>
    </svg>
  )
}

const issueData = [
  { label: "Analytics disabled",      severity: "critical", fix: "Settings → Plugins" },
  { label: "Sitemap not in robots",    severity: "warning",  fix: "Settings → Sitemap"  },
  { label: "OG image fallback missing", severity: "warning", fix: "Settings → Social"   },
]

const pluginStates = [
  { name: "Schema",    ok: true  },
  { name: "Sitemap",   ok: true  },
  { name: "Social",    ok: true  },
  { name: "Analytics", ok: false },
  { name: "AEO",       ok: true  },
  { name: "Perf",      ok: true  },
]

export function HealthModuleB() {
  const [dark, setDark] = useState(false)
  const [expanded, setExpanded] = useState(false)
  const score = 72

  const crit = issueData.filter(i => i.severity === "critical").length
  const warn = issueData.filter(i => i.severity === "warning").length

  return (
    <div className={`ab-admin-root${dark ? " dark" : ""}`}
      style={{ padding: "14px", fontFamily: "var(--ab-font)", minHeight: "unset", background: "var(--ab-bg)" }}>

      {/* Header row */}
      <div style={{ display: "flex", alignItems: "center", gap: 8, marginBottom: 14 }}>
        <span style={{ fontSize: 13, fontWeight: 700, color: "var(--ab-text)", flex: 1 }}>
          ● AI Boost Health
        </span>
        <span className="ab-badge ab-badge--primary" style={{ fontSize: 10 }}>Pro</span>
        <button onClick={() => setDark(d => !d)}
          style={{ background: "none", border: "none", cursor: "pointer", color: "var(--ab-text-muted)", fontSize: 13 }}>
          {dark ? "☀" : "☾"}
        </button>
      </div>

      {/* Main tile */}
      <div style={{
        display: "flex", alignItems: "center", gap: 14,
        padding: "12px 14px", borderRadius: 8,
        background: "var(--ab-surface)",
        border: "1px solid var(--ab-card-border)",
        boxShadow: "var(--ab-card-shadow)",
        marginBottom: 10
      }}>
        <ScoreArc score={score} />
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontWeight: 700, fontSize: 15, marginBottom: 3, color: "var(--ab-text)" }}>Needs Work</div>
          <div style={{ display: "flex", gap: 5, marginBottom: 10, flexWrap: "wrap" }}>
            {crit > 0 && <span className="ab-badge ab-badge--danger">{crit} critical</span>}
            {warn > 0 && <span className="ab-badge ab-badge--warning">{warn} warnings</span>}
          </div>
          <div style={{ display: "flex", gap: 6 }}>
            <button className="ab-btn ab-btn--primary ab-btn--sm" style={{ fontSize: 11.5 }}>↻ Re-run</button>
            <button className="ab-btn ab-btn--ghost ab-btn--sm" style={{ fontSize: 11.5 }}
              onClick={() => setExpanded(e => !e)}>
              {expanded ? "Hide issues ▴" : "Show issues ▾"}
            </button>
          </div>
        </div>
      </div>

      {/* Expandable issues */}
      {expanded && (
        <div style={{
          border: "1px solid var(--ab-card-border)", borderRadius: 6,
          background: "var(--ab-surface)", marginBottom: 10, overflow: "hidden"
        }}>
          {issueData.map((iss, i) => (
            <div key={i} style={{
              display: "flex", alignItems: "center", gap: 8,
              padding: "8px 12px",
              borderBottom: i < issueData.length - 1 ? "1px solid var(--ab-border)" : "none",
              fontSize: 12
            }}>
              <span style={{
                color: iss.severity === "critical" ? "var(--ab-danger)" : "var(--ab-warning)",
                flexShrink: 0, fontSize: 13
              }}>{iss.severity === "critical" ? "✗" : "⚠"}</span>
              <span style={{ color: "var(--ab-text)", flex: 1 }}>{iss.label}</span>
              <a href="#" style={{
                fontSize: 11, color: "var(--ab-primary)", textDecoration: "none",
                padding: "2px 7px", borderRadius: 4,
                border: "1px solid rgba(13,110,253,.3)",
                whiteSpace: "nowrap"
              }}>{iss.fix}</a>
            </div>
          ))}
        </div>
      )}

      {/* Plugin dot row */}
      <div style={{
        display: "flex", gap: 8, flexWrap: "wrap",
        padding: "10px 0",
        borderTop: "1px solid var(--ab-border)", borderBottom: "1px solid var(--ab-border)",
        marginBottom: 10
      }}>
        {pluginStates.map(p => (
          <div key={p.name} style={{
            display: "flex", alignItems: "center", gap: 4, fontSize: 11.5,
            color: p.ok ? "var(--ab-text)" : "var(--ab-text-muted)"
          }}>
            <span style={{
              width: 6, height: 6, borderRadius: "50%",
              background: p.ok ? "var(--ab-success)" : "var(--ab-danger)"
            }} />
            {p.name}
          </div>
        ))}
      </div>

      {/* Quick links */}
      <div style={{ display: "flex", gap: 5, flexWrap: "wrap" }}>
        {[
          { label: "⚙ Settings",  href: "#" },
          { label: "↪ Redirects", href: "#" },
          { label: "🔍 Analyzers", href: "#" },
        ].map(l => (
          <a key={l.label} href={l.href} className="ab-btn ab-btn--subtle ab-btn--sm"
            style={{ fontSize: 11, padding: "3px 9px" }}>{l.label}</a>
        ))}
        <a href="#" style={{ marginLeft: "auto", fontSize: 11, color: "var(--ab-primary)", alignSelf: "center" }}>
          Full report →
        </a>
      </div>

      <div style={{ marginTop: 10, fontSize: 10.5, color: "var(--ab-text-subtle)", textAlign: "right" }}>
        v0.15.0 · <a href="#" style={{ color: "var(--ab-primary)", textDecoration: "none" }}>aiboostnow.com</a>
      </div>
    </div>
  )
}
