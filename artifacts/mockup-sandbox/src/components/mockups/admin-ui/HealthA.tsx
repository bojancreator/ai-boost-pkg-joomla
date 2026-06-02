import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

const checks = [
  { cat: "General",   label: "AI Boost plugins enabled",           status: "pass",     msg: "5 of 6 plugins are enabled and active." },
  { cat: "General",   label: "Settings saved at least once",        status: "pass",     msg: "Configuration found in #__aiboost_settings." },
  { cat: "Schema",    label: "Organization name set",               status: "pass",     msg: "Organisation: Offroad Serbia Adventure Tours" },
  { cat: "Schema",    label: "Schema.org plugin enabled",           status: "pass",     msg: "aiboost_schema is active." },
  { cat: "Schema",    label: "Site type selected",                  status: "pass",     msg: "LocalBusiness > TouristAttraction" },
  { cat: "Sitemap",   label: "Sitemap XML accessible",              status: "pass",     msg: "https://staging.offroadserbia.com/sitemap.xml returns 200." },
  { cat: "Sitemap",   label: "Sitemap URL in robots.txt",           status: "warning",  msg: "robots.txt found but Sitemap directive is missing." },
  { cat: "Social",    label: "OpenGraph title defined",             status: "pass",     msg: "OG title found on homepage." },
  { cat: "Social",    label: "OG image set",                        status: "warning",  msg: "No og:image found on homepage — add a fallback image." },
  { cat: "Analytics", label: "Analytics plugin enabled",            status: "critical", msg: "aiboost_analytics is disabled. GA4 tracking will not fire." },
  { cat: "AEO",       label: "llms.txt present",                    status: "pass",     msg: "/llms.txt returns 200." },
  { cat: "AEO",       label: "IndexNow key file present",           status: "warning",  msg: "IndexNow key file not found. Enable in AEO settings." },
  { cat: "License",   label: "License key valid",                   status: "pass",     msg: "Pro license active — expires 2027-05-01." },
]

type Cat = string
const ICONS: Record<string, string> = { General: "⊞", Conflicts: "⚠", Schema: "{ }", Sitemap: "≡", Social: "⇄", Analytics: "▦", AEO: "⚡", License: "🔑" }

function ScoreRing({ score, size = 88 }: { score: number; size?: number }) {
  const pct = `${score * 3.6}deg`
  const cls = score >= 80 ? "good" : score >= 50 ? "ok" : "poor"
  const colors: Record<string, string> = { good: "var(--ab-score-good)", ok: "var(--ab-score-ok)", poor: "var(--ab-score-poor)" }
  const c = colors[cls]
  const inner = size * 0.82
  return (
    <div style={{
      width: size, height: size, borderRadius: "50%", flexShrink: 0,
      background: `conic-gradient(${c} ${pct}, var(--ab-border) ${pct})`,
      display: "flex", alignItems: "center", justifyContent: "center",
    }}>
      <div style={{
        width: inner, height: inner, borderRadius: "50%",
        background: "var(--ab-surface)",
        display: "flex", alignItems: "center", justifyContent: "center",
        fontSize: size * 0.24, fontWeight: 800, color: c,
      }}>{score}</div>
    </div>
  )
}

export function HealthA() {
  const { dark, setDark } = useTheme()
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({})
  const score = 72

  const cats = Array.from(new Set(checks.map(c => c.cat)))
  const catFails = (cat: string) => checks.filter(c => c.cat === cat && c.status !== "pass").length
  const toggle = (cat: Cat) => setCollapsed(prev => ({ ...prev, [cat]: !prev[cat] }))

  const crit  = checks.filter(c => c.status === "critical").length
  const warn  = checks.filter(c => c.status === "warning").length

  return (
    <AbShell activeNav="health" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page">

        {/* Score header */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__body">
            <div style={{ display: "flex", alignItems: "center", gap: 20, flexWrap: "wrap" }}>
              <ScoreRing score={score} />
              <div style={{ flex: 1 }}>
                <div style={{ fontSize: 17, fontWeight: 700, marginBottom: 3 }}>Needs Work</div>
                <div className="ab-text-muted ab-small" style={{ marginBottom: 10 }}>
                  {crit} critical issue{crit !== 1 ? "s" : ""}, {warn} warning{warn !== 1 ? "s" : ""} found.
                </div>
                <div className="ab-cluster">
                  <button className="ab-btn ab-btn--primary ab-btn--sm">↻ Re-run Checks</button>
                  <button className="ab-btn ab-btn--ghost ab-btn--sm">⎘ Copy Report</button>
                </div>
              </div>
              <div style={{ display: "flex", flexDirection: "column", gap: 6, flexShrink: 0 }}>
                <span className={`ab-badge ab-badge--${crit > 0 ? "danger" : "success"}`}>
                  Critical: {checks.filter(c => c.status !== "critical").length}/{checks.length} OK
                </span>
                <span className={`ab-badge ab-badge--${warn > 0 ? "warning" : "success"}`}>
                  Warnings: {checks.filter(c => c.status !== "warning").length}/{checks.length} OK
                </span>
              </div>
            </div>
          </div>

          {/* Quick actions */}
          <div className="ab-card__footer">
            <div className="ab-cluster">
              <button className="ab-btn ab-btn--ghost ab-btn--sm">⚙ Settings</button>
              <button className="ab-btn ab-btn--ghost ab-btn--sm">⬆ Import</button>
              <button className="ab-btn ab-btn--ghost ab-btn--sm">↪ Redirects</button>
              <button className="ab-btn ab-btn--subtle ab-btn--sm">🔍 Analyzers</button>
              <button className="ab-btn ab-btn--subtle ab-btn--sm">🔗 URL Checker</button>
              <button className="ab-btn ab-btn--ghost ab-btn--sm">{"{ }"} JSON-LD Validator</button>
              <button className="ab-btn ab-btn--ghost ab-btn--sm">⚡ AI Visibility</button>
            </div>
          </div>
        </div>

        {/* Category cards */}
        {cats.map(cat => {
          const catChecks = checks.filter(c => c.cat === cat)
          const fails = catFails(cat)
          const open = !collapsed[cat]
          return (
            <div className="ab-card" key={cat} style={{ marginBottom: 10 }}>
              <div
                className="ab-card__header"
                style={{ cursor: "pointer", userSelect: "none" }}
                onClick={() => toggle(cat)}
              >
                <span>{ICONS[cat] || "•"}</span>
                <span className="ab-fw600">{cat}</span>
                <span className={`ab-badge ab-badge--${fails > 0 ? "danger" : "success"}`} style={{ marginLeft: 6 }}>
                  {fails > 0 ? `${fails} issue${fails > 1 ? "s" : ""}` : "All OK"}
                </span>
                <span style={{ marginLeft: "auto", fontSize: 12 }}>{open ? "▾" : "▸"}</span>
              </div>
              {open && (
                <div>
                  {catChecks.map((ck, i) => (
                    <div key={i} className={`ab-hc-row ab-hc-row--${ck.status === "pass" ? "pass" : ck.status === "critical" ? "crit" : "warn"}`}>
                      <span className={`ab-hc-icon ab-hc-icon--${ck.status === "pass" ? "pass" : ck.status === "critical" ? "crit" : "warn"}`}>
                        {ck.status === "pass" ? "✓" : ck.status === "critical" ? "✗" : "⚠"}
                      </span>
                      <div style={{ flex: 1 }}>
                        <div className="ab-hc-label">
                          {ck.label}
                          {ck.status !== "pass" && (
                            <span className={`ab-badge ab-badge--${ck.status === "critical" ? "danger" : "warning"}`} style={{ marginLeft: 6 }}>
                              {ck.status}
                            </span>
                          )}
                        </div>
                        <div className="ab-hc-msg">{ck.msg}</div>
                      </div>
                      {ck.status !== "pass" && (
                        <div style={{ display: "flex", gap: 6, flexShrink: 0, alignItems: "center" }}>
                          <button className="ab-btn ab-btn--subtle ab-btn--sm">Fix it</button>
                          <button className="ab-btn ab-btn--ghost ab-btn--sm">Dismiss</button>
                        </div>
                      )}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )
        })}

        <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
          © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a>
        </p>
      </div>
    </AbShell>
  )
}
