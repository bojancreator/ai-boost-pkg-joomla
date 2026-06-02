import { useState } from "react"
import "./_shared/tokens.css"

/* mod_aiboost_health — admin dashboard module widget
   Variant A: Improved current — score circle + top issues + quick actions */

function ScoreRing({ score }: { score: number }) {
  const pct = `${score * 3.6}deg`
  const c = score >= 80 ? "var(--ab-score-good)" : score >= 50 ? "var(--ab-score-ok)" : "var(--ab-score-poor)"
  return (
    <div style={{
      width: 60, height: 60, borderRadius: "50%", flexShrink: 0,
      background: `conic-gradient(${c} ${pct}, var(--ab-border) ${pct})`,
      display: "flex", alignItems: "center", justifyContent: "center",
    }}>
      <div style={{
        width: 50, height: 50, borderRadius: "50%",
        background: "var(--ab-surface)",
        display: "flex", alignItems: "center", justifyContent: "center",
        fontWeight: 800, fontSize: 16, color: c
      }}>{score}</div>
    </div>
  )
}

const issues = [
  { label: "Analytics plugin disabled",     severity: "critical" },
  { label: "Sitemap not in robots.txt",      severity: "warning"  },
  { label: "OG image fallback missing",      severity: "warning"  },
]

const plugins = [
  { name: "Schema",    color: "#8b5cf6", enabled: true  },
  { name: "Sitemap",   color: "#14b8a6", enabled: true  },
  { name: "Social",    color: "#ec4899", enabled: true  },
  { name: "Analytics", color: "#f97316", enabled: false },
  { name: "AEO",       color: "#06b6d4", enabled: true  },
  { name: "Perf",      color: "#10b981", enabled: true  },
]

export function HealthModuleA() {
  const [dark, setDark] = useState(false)
  const score = 72

  return (
    <div className={`ab-admin-root${dark ? " dark" : ""}`}
      style={{ padding: "16px", fontFamily: "var(--ab-font)", minHeight: "unset", background: "var(--ab-bg)" }}>

      {/* Module header */}
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: 12 }}>
        <div style={{ fontSize: 13, fontWeight: 700, color: "var(--ab-text)", display: "flex", alignItems: "center", gap: 6 }}>
          <span style={{
            width: 6, height: 6, borderRadius: "50%",
            background: "var(--ab-primary)", display: "inline-block"
          }} />
          AI Boost Health
        </div>
        <div style={{ display: "flex", gap: 6, alignItems: "center" }}>
          <span style={{
            fontSize: 10.5, padding: "1px 7px", borderRadius: 10,
            background: "rgba(13,110,253,.1)", color: "var(--ab-primary)", fontWeight: 600
          }}>Pro</span>
          <button className="ab-theme-btn"
            style={{ width: 22, height: 22, fontSize: 11, border: "none", background: "var(--ab-surface-raised)", borderRadius: 4, cursor: "pointer", color: "var(--ab-text-muted)" }}
            onClick={() => setDark(d => !d)}>{dark ? "☀" : "☾"}</button>
        </div>
      </div>

      {/* Score row */}
      <div style={{ display: "flex", alignItems: "center", gap: 12, marginBottom: 12 }}>
        <ScoreRing score={score} />
        <div>
          <div style={{ fontWeight: 700, fontSize: 14, color: "var(--ab-text)", marginBottom: 2 }}>
            {score >= 80 ? "Good" : score >= 50 ? "Needs Work" : "Critical"}
          </div>
          <div style={{ fontSize: 12, color: "var(--ab-text-muted)", marginBottom: 6 }}>
            {issues.filter(i => i.severity === "critical").length} critical · {issues.filter(i => i.severity === "warning").length} warnings
          </div>
          <div style={{ display: "flex", gap: 6 }}>
            <button className="ab-btn ab-btn--primary ab-btn--sm" style={{ fontSize: 11, padding: "3px 8px" }}>↻ Re-run</button>
            <a href="#" className="ab-btn ab-btn--ghost ab-btn--sm" style={{ fontSize: 11, padding: "3px 8px" }}>Full report →</a>
          </div>
        </div>
      </div>

      {/* Plugin status row */}
      <div style={{ display: "flex", gap: 5, flexWrap: "wrap", marginBottom: 10 }}>
        {plugins.map(p => (
          <div key={p.name} style={{
            display: "flex", alignItems: "center", gap: 4,
            padding: "3px 8px", borderRadius: 4,
            border: `1px solid ${p.enabled ? p.color + "44" : "var(--ab-border)"}`,
            background: p.enabled ? p.color + "12" : "var(--ab-surface-raised)",
            fontSize: 11.5, fontWeight: 500,
            color: p.enabled ? p.color : "var(--ab-text-muted)"
          }}>
            <span style={{
              width: 5, height: 5, borderRadius: "50%",
              background: p.enabled ? p.color : "var(--ab-text-subtle)"
            }} />
            {p.name}
          </div>
        ))}
      </div>

      {/* Top issues */}
      <div style={{ borderTop: "1px solid var(--ab-border)", paddingTop: 10, marginBottom: 10 }}>
        <div style={{ fontSize: 11, fontWeight: 700, textTransform: "uppercase", letterSpacing: ".5px", color: "var(--ab-text-muted)", marginBottom: 6 }}>Top Issues</div>
        {issues.map((issue, i) => (
          <div key={i} style={{
            display: "flex", alignItems: "center", gap: 7,
            padding: "5px 0",
            borderBottom: i < issues.length - 1 ? "1px solid var(--ab-border)" : "none",
            fontSize: 12
          }}>
            <span style={{
              color: issue.severity === "critical" ? "var(--ab-danger)" : "var(--ab-warning)",
              fontSize: 12, flexShrink: 0
            }}>{issue.severity === "critical" ? "✗" : "⚠"}</span>
            <span style={{ color: "var(--ab-text)", flex: 1 }}>{issue.label}</span>
            <span className={`ab-badge ab-badge--${issue.severity === "critical" ? "danger" : "warning"}`}
              style={{ fontSize: 10 }}>{issue.severity}</span>
          </div>
        ))}
      </div>

      {/* Quick actions */}
      <div style={{ display: "flex", gap: 6, flexWrap: "wrap", borderTop: "1px solid var(--ab-border)", paddingTop: 10 }}>
        {[
          { label: "Settings", icon: "⚙" },
          { label: "Redirects", icon: "↪" },
          { label: "Analyzers", icon: "🔍" },
          { label: "Import", icon: "⬆" },
        ].map(a => (
          <a key={a.label} href="#" className="ab-btn ab-btn--ghost ab-btn--sm" style={{ fontSize: 11, padding: "3px 8px" }}>
            {a.icon} {a.label}
          </a>
        ))}
      </div>

      <div style={{ marginTop: 10, fontSize: 11, color: "var(--ab-text-subtle)", display: "flex", justifyContent: "space-between" }}>
        <span>AI Boost v0.15.0</span>
        <a href="#" style={{ color: "var(--ab-primary)", textDecoration: "none" }}>aiboostnow.com</a>
      </div>
    </div>
  )
}
