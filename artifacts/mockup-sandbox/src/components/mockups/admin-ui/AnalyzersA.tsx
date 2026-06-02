import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

type AnalyzerTab = "seo" | "jsonld" | "ai"

const SEO_CHECKS = [
  { label: "Title tag",          severity: "pass",    msg: "Good length (52 chars). Keyword present." },
  { label: "Meta description",   severity: "warning", msg: "Too long (182 chars). Shorten to under 160." },
  { label: "H1 heading",         severity: "pass",    msg: "One H1 found, matches page title." },
  { label: "Canonical tag",      severity: "pass",    msg: "Self-referencing canonical found." },
  { label: "Schema.org markup",  severity: "pass",    msg: "Organization + LocalBusiness found." },
  { label: "Image alt text",     severity: "warning", msg: "3 images missing alt attributes." },
  { label: "robots.txt",         severity: "pass",    msg: "Accessible at /robots.txt." },
  { label: "Sitemap in robots",  severity: "error",   msg: "Sitemap directive missing from robots.txt." },
  { label: "Page load speed",    severity: "warning", msg: "LCP estimated 3.2s — optimize images." },
  { label: "Mobile viewport",    severity: "pass",    msg: "Viewport meta tag present." },
]

function ScoreCircle({ score }: { score: number }) {
  const c = score >= 80 ? "#198754" : score >= 50 ? "#fd7e14" : "#dc3545"
  return (
    <div style={{
      width: 72, height: 72, borderRadius: "50%",
      background: `conic-gradient(${c} ${score * 3.6}deg, var(--ab-border) ${score * 3.6}deg)`,
      display: "flex", alignItems: "center", justifyContent: "center", flexShrink: 0
    }}>
      <div style={{
        width: 60, height: 60, borderRadius: "50%",
        background: "var(--ab-surface)",
        display: "flex", alignItems: "center", justifyContent: "center",
        fontWeight: 800, fontSize: 18, color: c
      }}>{score}</div>
    </div>
  )
}

const badgeMap: Record<string, string> = { pass: "success", warning: "warning", error: "danger", info: "info" }
const iconMap:  Record<string, string> = { pass: "✓", warning: "⚠", error: "✗", info: "ℹ" }
const clsMap:   Record<string, string> = { pass: "pass", warning: "warn", error: "crit", info: "pass" }

export function AnalyzersA() {
  const { dark, setDark } = useTheme()
  const [tab, setTab] = useState<AnalyzerTab>("seo")
  const [seoUrl, setSeoUrl] = useState("https://staging.offroadserbia.com/")
  const [jsonldInput, setJsonldInput] = useState("")
  const [aiUrl, setAiUrl] = useState("")
  const [hasResult, setHasResult] = useState(true)

  const errors  = SEO_CHECKS.filter(c => c.severity === "error").length
  const warnings = SEO_CHECKS.filter(c => c.severity === "warning").length
  const passed  = SEO_CHECKS.filter(c => c.severity === "pass").length

  return (
    <AbShell activeNav="analyzers" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page">

        {/* Tab navigation */}
        <div className="ab-tabs">
          <button className={`ab-tab${tab === "seo" ? " active" : ""}`} onClick={() => setTab("seo")}>
            🔍 SEO Analyzer
          </button>
          <button className={`ab-tab${tab === "jsonld" ? " active" : ""}`} onClick={() => setTab("jsonld")}>
            {"{ }"} JSON-LD Validator
          </button>
          <button className={`ab-tab${tab === "ai" ? " active" : ""}`} onClick={() => setTab("ai")}>
            ⚡ AI Visibility
          </button>
        </div>

        {/* ── SEO tab ── */}
        {tab === "seo" && (
          <>
            <div className="ab-card" style={{ marginBottom: 14 }}>
              <div className="ab-card__header">🔍 SEO Analyzer</div>
              <div className="ab-card__body">
                <p className="ab-text-muted ab-small" style={{ marginBottom: 12 }}>
                  Enter a URL to get an SEO score and actionable recommendations.
                </p>
                <div style={{ display: "flex", gap: 8 }}>
                  <span style={{ display: "flex", alignItems: "center", padding: "0 10px", background: "var(--ab-surface-raised)", border: "1px solid var(--ab-border-strong)", borderRight: "none", borderRadius: "5px 0 0 5px", color: "var(--ab-text-muted)" }}>🌐</span>
                  <input
                    className="ab-input"
                    style={{ flex: 1, borderRadius: "0", borderLeft: "none", borderRight: "none" }}
                    value={seoUrl}
                    onChange={e => setSeoUrl(e.target.value)}
                    placeholder="https://example.com/your-page"
                  />
                  <button className="ab-btn ab-btn--primary" style={{ borderRadius: "0 5px 5px 0" }}>
                    🔍 Analyze
                  </button>
                </div>
              </div>
            </div>

            {hasResult && (
              <>
                {/* Score header */}
                <div className="ab-card" style={{ marginBottom: 14 }}>
                  <div className="ab-card__body" style={{ display: "flex", alignItems: "center", gap: 16, flexWrap: "wrap" }}>
                    <ScoreCircle score={74} />
                    <div style={{ flex: 1 }}>
                      <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 4 }}>Needs Improvement</div>
                      <div className="ab-small ab-text-muted" style={{ marginBottom: 8 }}>
                        Analyzed: <code className="ab-code">{seoUrl}</code>
                      </div>
                      <div className="ab-cluster">
                        <span className="ab-badge ab-badge--danger">{errors} Error{errors !== 1 ? "s" : ""}</span>
                        <span className="ab-badge ab-badge--warning">{warnings} Warning{warnings !== 1 ? "s" : ""}</span>
                        <span className="ab-badge ab-badge--success">{passed} Passed</span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Checks */}
                <div className="ab-card">
                  <div className="ab-card__header">SEO Check Details</div>
                  {SEO_CHECKS.map((c, i) => (
                    <div key={i} className={`ab-hc-row ab-hc-row--${clsMap[c.severity]}`}>
                      <span className={`ab-hc-icon ab-hc-icon--${clsMap[c.severity]}`}>{iconMap[c.severity]}</span>
                      <div style={{ flex: 1 }}>
                        <div className="ab-hc-label">{c.label}</div>
                        <div className="ab-hc-msg">{c.msg}</div>
                      </div>
                      <span className={`ab-badge ab-badge--${badgeMap[c.severity]}`} style={{ flexShrink: 0 }}>{c.severity}</span>
                    </div>
                  ))}
                </div>
              </>
            )}
          </>
        )}

        {/* ── JSON-LD tab ── */}
        {tab === "jsonld" && (
          <>
            <div className="ab-card" style={{ marginBottom: 14 }}>
              <div className="ab-card__header">{"{ }"} JSON-LD Validator</div>
              <div className="ab-card__body">
                <p className="ab-text-muted ab-small" style={{ marginBottom: 12 }}>
                  Paste your JSON-LD structured data, or fetch it from a URL, to validate against Schema.org.
                </p>
                <div style={{ display: "flex", gap: 8, marginBottom: 12 }}>
                  <input className="ab-input" placeholder="Fetch JSON-LD from URL…" style={{ flex: 1 }} value={aiUrl} onChange={e => setAiUrl(e.target.value)} />
                  <button className="ab-btn ab-btn--ghost">⬇ Fetch</button>
                </div>
                <label className="ab-label">JSON-LD Code</label>
                <textarea
                  className="ab-input ab-input--mono"
                  rows={10}
                  placeholder='{"@context":"https://schema.org","@type":"Organization","name":"…"}'
                  value={jsonldInput}
                  onChange={e => setJsonldInput(e.target.value)}
                  style={{ resize: "vertical" }}
                />
                <div style={{ marginTop: 12, display: "flex", gap: 8 }}>
                  <button className="ab-btn ab-btn--primary">✓ Validate JSON-LD</button>
                  <button className="ab-btn ab-btn--ghost">Pretty Print</button>
                  <button className="ab-btn ab-btn--ghost">Clear</button>
                </div>
              </div>
            </div>

            {/* Sample result */}
            <div className="ab-card">
              <div className="ab-card__header" style={{ gap: 12 }}>
                Validation Results
                <span className="ab-text-muted ab-small">Type: <strong>Organization</strong></span>
                <ScoreCircle score={90} />
              </div>
              {[
                { label: "Required: @context", level: "pass",    msg: "Found: https://schema.org" },
                { label: "Required: @type",    level: "pass",    msg: "Type: Organization" },
                { label: "name property",      level: "pass",    msg: "Found: Offroad Serbia" },
                { label: "url property",       level: "warning", msg: "Recommended field missing." },
                { label: "logo property",      level: "warning", msg: "Logo URL not set — helps AI engines recognize your brand." },
              ].map((issue, i) => (
                <div key={i} className={`ab-hc-row ab-hc-row--${clsMap[issue.level]}`}>
                  <span className={`ab-hc-icon ab-hc-icon--${clsMap[issue.level]}`}>{iconMap[issue.level]}</span>
                  <div style={{ flex: 1 }}>
                    <div className="ab-hc-label">{issue.label}</div>
                    <div className="ab-hc-msg">{issue.msg}</div>
                  </div>
                  <span className={`ab-badge ab-badge--${badgeMap[issue.level]}`}>{issue.level}</span>
                </div>
              ))}
            </div>
          </>
        )}

        {/* ── AI Visibility tab ── */}
        {tab === "ai" && (
          <>
            <div className="ab-card" style={{ marginBottom: 14 }}>
              <div className="ab-card__header">⚡ AI Visibility Analyzer</div>
              <div className="ab-card__body">
                <p className="ab-text-muted ab-small" style={{ marginBottom: 12 }}>
                  Check how visible your site is to ChatGPT, Perplexity, Google AI Overviews, and Bing Copilot.
                </p>
                <div style={{ display: "flex", gap: 8 }}>
                  <span style={{ display: "flex", alignItems: "center", padding: "0 10px", background: "var(--ab-surface-raised)", border: "1px solid var(--ab-border-strong)", borderRight: "none", borderRadius: "5px 0 0 5px", color: "var(--ab-text-muted)" }}>🌐</span>
                  <input
                    className="ab-input"
                    style={{ flex: 1, borderRadius: 0, borderLeft: "none", borderRight: "none" }}
                    placeholder="Leave blank for current site"
                    value={aiUrl}
                    onChange={e => setAiUrl(e.target.value)}
                  />
                  <button className="ab-btn ab-btn--primary" style={{ borderRadius: "0 5px 5px 0" }}>⚡ Check AI Visibility</button>
                </div>
              </div>
            </div>

            {/* Score + crawlers */}
            <div className="ab-card" style={{ marginBottom: 14 }}>
              <div className="ab-card__body" style={{ display: "flex", alignItems: "center", gap: 16, flexWrap: "wrap" }}>
                <ScoreCircle score={65} />
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 16, fontWeight: 700, marginBottom: 4 }}>Moderate AI Visibility</div>
                  <div className="ab-small ab-text-muted" style={{ marginBottom: 8 }}>Base URL: <code className="ab-code">https://staging.offroadserbia.com</code></div>
                  <div className="ab-cluster">
                    <span className="ab-badge ab-badge--danger">2 Errors</span>
                    <span className="ab-badge ab-badge--warning">3 Warnings</span>
                    <span className="ab-badge ab-badge--success">4 Passed</span>
                  </div>
                </div>
              </div>
            </div>

            {/* Crawler status */}
            <div className="ab-card" style={{ marginBottom: 14 }}>
              <div className="ab-card__header">AI Crawler Status</div>
              <div className="ab-card__body">
                <div style={{ display: "grid", gridTemplateColumns: "repeat(3, 1fr)", gap: 8 }}>
                  {[
                    { name: "ChatGPT / GPTBot",  pass: false },
                    { name: "Perplexity Bot",     pass: true  },
                    { name: "Google Gemini",      pass: true  },
                    { name: "Bing Copilot",       pass: true  },
                    { name: "Claude AI",          pass: true  },
                    { name: "Anthropic Bot",      pass: false },
                  ].map(c => (
                    <div key={c.name} style={{
                      display: "flex", alignItems: "center", gap: 8,
                      padding: "8px 10px", borderRadius: 6, fontSize: 12,
                      border: `1px solid ${c.pass ? "rgba(25,135,84,.3)" : "rgba(253,126,20,.3)"}`,
                      background: c.pass ? "rgba(25,135,84,.06)" : "rgba(253,126,20,.06)"
                    }}>
                      <span style={{ color: c.pass ? "var(--ab-success)" : "var(--ab-warning)" }}>
                        {c.pass ? "✓" : "⚠"}
                      </span>
                      <span style={{ color: "var(--ab-text)", flex: 1, overflow: "hidden", textOverflow: "ellipsis", whiteSpace: "nowrap" }}>{c.name}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Checks */}
            <div className="ab-card">
              <div className="ab-card__header">AI Signal Details</div>
              {[
                { label: "llms.txt present",              severity: "pass",    msg: "/llms.txt returns 200 OK." },
                { label: "IndexNow key file",             severity: "error",   msg: "Key file not found at /[key].txt." },
                { label: "Schema.org author markup",      severity: "pass",    msg: "Author entity found on homepage." },
                { label: "AI crawlers in robots.txt",     severity: "error",   msg: "GPTBot and anthropic-ai are disallowed." },
                { label: "Organization schema",           severity: "pass",    msg: "Organization markup present." },
                { label: "sitemap.xml accessible",        severity: "pass",    msg: "/sitemap.xml returns 200 OK." },
                { label: "Structured data coverage",      severity: "warning", msg: "Only 40% of articles have Schema markup." },
                { label: "llms-full.txt present",         severity: "warning", msg: "Detailed llms-full.txt not found." },
                { label: "Contact info in schema",        severity: "warning", msg: "ContactPoint not set in Organization schema." },
              ].map((c, i) => (
                <div key={i} className={`ab-hc-row ab-hc-row--${clsMap[c.severity]}`}>
                  <span className={`ab-hc-icon ab-hc-icon--${clsMap[c.severity]}`}>{iconMap[c.severity]}</span>
                  <div style={{ flex: 1 }}>
                    <div className="ab-hc-label">{c.label}</div>
                    <div className="ab-hc-msg">{c.msg}</div>
                  </div>
                  <span className={`ab-badge ab-badge--${badgeMap[c.severity]}`}>{c.severity}</span>
                </div>
              ))}
            </div>
          </>
        )}

        <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
          © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a>
        </p>
      </div>
    </AbShell>
  )
}
