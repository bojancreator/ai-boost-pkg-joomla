import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

const SECTIONS = [
  { id: "health",     label: "Health Overview",  icon: "♥"  },
  { id: "urlchecker", label: "URL Checker",       icon: "🔗" },
  { id: "jsonld",     label: "JSON-LD Validator", icon: "{ }" },
  { id: "aivisibility", label: "AI Visibility",  icon: "⚡" },
]

const checks = [
  { cat: "General",   label: "Plugins enabled",              status: "pass",     msg: "5 of 6 active." },
  { cat: "Schema",    label: "Organization name set",        status: "pass",     msg: "Offroad Serbia Adventure Tours" },
  { cat: "Sitemap",   label: "Sitemap URL in robots.txt",    status: "warning",  msg: "Sitemap directive missing." },
  { cat: "Social",    label: "OG image set",                 status: "warning",  msg: "No og:image fallback." },
  { cat: "Analytics", label: "Analytics plugin enabled",     status: "critical", msg: "aiboost_analytics is disabled." },
  { cat: "AEO",       label: "IndexNow key file present",    status: "warning",  msg: "Key file not found." },
  { cat: "AEO",       label: "llms.txt present",             status: "pass",     msg: "/llms.txt returns 200." },
  { cat: "License",   label: "License key valid",            status: "pass",     msg: "Pro license active." },
]

function ScoreRing({ score, size = 80 }: { score: number; size?: number }) {
  const pct = `${score * 3.6}deg`
  const c = score >= 80 ? "var(--ab-score-good)" : score >= 50 ? "var(--ab-score-ok)" : "var(--ab-score-poor)"
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

export function HealthB() {
  const { dark, setDark } = useTheme()
  const [section, setSection] = useState("health")
  const score = 72

  return (
    <AbShell activeNav="health" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page" style={{ padding: 0 }}>

        {/* Health sub-nav — horizontal strip under topbar */}
        <div style={{
          borderBottom: "1px solid var(--ab-border)",
          background: "var(--ab-surface)",
          padding: "0 24px",
          display: "flex", gap: 2,
        }}>
          {SECTIONS.map(s => (
            <button
              key={s.id}
              onClick={() => setSection(s.id)}
              style={{
                display: "flex", alignItems: "center", gap: 6,
                padding: "10px 14px",
                fontSize: 13, fontWeight: 500,
                color: section === s.id ? "var(--ab-primary)" : "var(--ab-text-muted)",
                background: "transparent",
                border: "none",
                borderBottom: `2px solid ${section === s.id ? "var(--ab-primary)" : "transparent"}`,
                cursor: "pointer",
                marginBottom: -1,
                transition: "color .12s",
              }}
            >
              {s.icon} {s.label}
            </button>
          ))}
        </div>

        <div style={{ padding: "20px 24px", maxWidth: 1200, margin: "0 auto" }}>

          {section === "health" && (
            <>
              {/* Score + stats */}
              <div className="ab-card" style={{ marginBottom: 16 }}>
                <div className="ab-card__body">
                  <div style={{ display: "flex", alignItems: "center", gap: 20, flexWrap: "wrap" }}>
                    <ScoreRing score={score} />
                    <div style={{ flex: 1 }}>
                      <div style={{ fontSize: 17, fontWeight: 700, marginBottom: 2 }}>Needs Work</div>
                      <div className="ab-text-muted ab-small" style={{ marginBottom: 10 }}>
                        1 critical issue, 3 warnings found.
                      </div>
                      <div className="ab-cluster">
                        <button className="ab-btn ab-btn--primary ab-btn--sm">↻ Re-run Checks</button>
                        <button className="ab-btn ab-btn--ghost ab-btn--sm">⎘ Copy Report</button>
                      </div>
                    </div>
                    <div className="ab-stack" style={{ flexShrink: 0 }}>
                      <span className="ab-badge ab-badge--danger">Critical: 7/8 OK</span>
                      <span className="ab-badge ab-badge--warning">Warnings: 5/8 OK</span>
                    </div>
                  </div>
                </div>
              </div>

              {/* Check list all in one */}
              <div className="ab-card">
                <div className="ab-card__header">All Checks</div>
                {checks.map((ck, i) => (
                  <div key={i} className={`ab-hc-row ab-hc-row--${ck.status === "pass" ? "pass" : ck.status === "critical" ? "crit" : "warn"}`}>
                    <span className={`ab-hc-icon ab-hc-icon--${ck.status === "pass" ? "pass" : ck.status === "critical" ? "crit" : "warn"}`}>
                      {ck.status === "pass" ? "✓" : ck.status === "critical" ? "✗" : "⚠"}
                    </span>
                    <div style={{ flex: 1 }}>
                      <div className="ab-hc-label">
                        <span style={{ fontSize: 10.5, color: "var(--ab-text-subtle)", marginRight: 6, textTransform: "uppercase", letterSpacing: ".4px" }}>{ck.cat}</span>
                        {ck.label}
                        {ck.status !== "pass" && (
                          <span className={`ab-badge ab-badge--${ck.status === "critical" ? "danger" : "warning"}`} style={{ marginLeft: 6 }}>{ck.status}</span>
                        )}
                      </div>
                      <div className="ab-hc-msg">{ck.msg}</div>
                    </div>
                    {ck.status !== "pass" && (
                      <div style={{ display: "flex", gap: 5, flexShrink: 0 }}>
                        <button className="ab-btn ab-btn--subtle ab-btn--sm">Fix it</button>
                        <button className="ab-btn ab-btn--ghost ab-btn--sm">Dismiss</button>
                      </div>
                    )}
                  </div>
                ))}
              </div>
            </>
          )}

          {section === "urlchecker" && (
            <div className="ab-card">
              <div className="ab-card__header">🔗 URL Checker</div>
              <div className="ab-card__body">
                <p className="ab-text-muted ab-small" style={{ marginBottom: 14 }}>
                  Scan a list of URLs — HTTP status, redirect chains, canonical tags, thin content.
                </p>
                <div style={{ display: "flex", gap: 8, marginBottom: 10 }}>
                  <button className="ab-btn ab-btn--primary ab-btn--sm">Load from Sitemap</button>
                  <button className="ab-btn ab-btn--ghost ab-btn--sm">Clear</button>
                </div>
                <textarea
                  className="ab-input ab-input--mono"
                  rows={6}
                  defaultValue={"https://staging.offroadserbia.com/\nhttps://staging.offroadserbia.com/tours/\nhttps://staging.offroadserbia.com/contact/"}
                  style={{ resize: "vertical" }}
                />
                <div style={{ marginTop: 12, display: "flex", gap: 8 }}>
                  <button className="ab-btn ab-btn--success">▶ Start Scan</button>
                  <button className="ab-btn ab-btn--ghost" style={{ marginLeft: "auto" }}>Compare vs Google Search Console</button>
                </div>
              </div>
            </div>
          )}

          {section === "jsonld" && (
            <div className="ab-card">
              <div className="ab-card__header">{"{ }"} JSON-LD Validator</div>
              <div className="ab-card__body">
                <p className="ab-text-muted ab-small" style={{ marginBottom: 12 }}>
                  Paste structured data or fetch from a URL to validate against Schema.org rules.
                </p>
                <div style={{ display: "flex", gap: 8, marginBottom: 10 }}>
                  <input className="ab-input" placeholder="Fetch JSON-LD from URL…" style={{ flex: 1 }} />
                  <button className="ab-btn ab-btn--ghost">Fetch</button>
                </div>
                <textarea
                  className="ab-input ab-input--mono"
                  rows={10}
                  placeholder='{"@context":"https://schema.org","@type":"Organization","name":"…"}'
                  style={{ resize: "vertical" }}
                />
                <div style={{ marginTop: 12, display: "flex", gap: 8 }}>
                  <button className="ab-btn ab-btn--primary">✓ Validate JSON-LD</button>
                  <button className="ab-btn ab-btn--ghost">Pretty Print</button>
                  <button className="ab-btn ab-btn--ghost">Clear</button>
                </div>
              </div>
            </div>
          )}

          {section === "aivisibility" && (
            <div className="ab-card">
              <div className="ab-card__header">⚡ AI Visibility Score</div>
              <div className="ab-card__body">
                <div style={{ display: "flex", alignItems: "center", gap: 16, marginBottom: 16 }}>
                  <ScoreRing score={65} size={72} />
                  <div>
                    <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 3 }}>Moderate AI Visibility</div>
                    <p className="ab-text-muted ab-small">
                      Based on AEO signals: Schema.org, llms.txt, IndexNow, robots.txt, author markup.
                    </p>
                  </div>
                </div>
                <div style={{ display: "flex", gap: 6, flexWrap: "wrap", marginBottom: 16 }}>
                  {["ChatGPT: allowed ✓", "Perplexity: allowed ✓", "Google AI: allowed ✓", "GPTBot: blocked ✗"].map(c => (
                    <span key={c} className={`ab-badge ab-badge--${c.includes("✓") ? "success" : "danger"}`}>{c}</span>
                  ))}
                </div>
                {[
                  { label: "llms.txt present",     status: "pass",    msg: "/llms.txt returns 200." },
                  { label: "IndexNow key file",     status: "warning", msg: "Key file not found at root." },
                  { label: "Schema.org author",     status: "pass",    msg: "Author markup found." },
                  { label: "AI crawlers in robots", status: "warning", msg: "GPTBot is blocked." },
                ].map((ck, i) => (
                  <div key={i} className={`ab-hc-row ab-hc-row--${ck.status === "pass" ? "pass" : "warn"}`}>
                    <span className={`ab-hc-icon ab-hc-icon--${ck.status === "pass" ? "pass" : "warn"}`}>
                      {ck.status === "pass" ? "✓" : "⚠"}
                    </span>
                    <div style={{ flex: 1 }}>
                      <div className="ab-hc-label">{ck.label}</div>
                      <div className="ab-hc-msg">{ck.msg}</div>
                    </div>
                    {ck.status !== "pass" && <button className="ab-btn ab-btn--subtle ab-btn--sm">Fix it</button>}
                  </div>
                ))}
              </div>
            </div>
          )}

          <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
            © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a>
          </p>
        </div>
      </div>
    </AbShell>
  )
}
