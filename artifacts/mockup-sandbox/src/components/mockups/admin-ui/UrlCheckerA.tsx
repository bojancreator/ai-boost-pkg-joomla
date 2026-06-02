import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

/* Variant A: Linear flow — input panel on top, results table below */

const SAMPLE_RESULTS = [
  { url: "https://staging.offroadserbia.com/",         status: 200, redirectChain: "",                       canonical: "self",    flags: [] },
  { url: "https://staging.offroadserbia.com/tours/",   status: 200, redirectChain: "",                       canonical: "self",    flags: ["thin-content"] },
  { url: "https://staging.offroadserbia.com/contact/", status: 200, redirectChain: "",                       canonical: "self",    flags: [] },
  { url: "https://staging.offroadserbia.com/old-page", status: 301, redirectChain: "→ /contact/",            canonical: "—",       flags: ["redirect"] },
  { url: "https://staging.offroadserbia.com/404-url",  status: 404, redirectChain: "",                       canonical: "—",       flags: ["error"] },
  { url: "https://staging.offroadserbia.com/noindex",  status: 200, redirectChain: "",                       canonical: "self",    flags: ["noindex"] },
]

const statusBadge = (s: number) => {
  if (s === 200) return <span className="ab-badge ab-badge--success">{s}</span>
  if (s >= 300 && s < 400) return <span className="ab-badge ab-badge--info">{s}</span>
  return <span className="ab-badge ab-badge--danger">{s}</span>
}

export function UrlCheckerA() {
  const { dark, setDark } = useTheme()
  const [urls, setUrls] = useState(
    "https://staging.offroadserbia.com/\nhttps://staging.offroadserbia.com/tours/\nhttps://staging.offroadserbia.com/contact/"
  )
  const [scanning, setScanning] = useState(false)
  const [progress, setProgress] = useState({ done: 0, total: 0 })
  const [hasResults, setHasResults] = useState(true)
  const urlCount = urls.split("\n").filter(u => u.trim()).length

  function startScan() {
    setScanning(true)
    setProgress({ done: 0, total: urlCount })
    let done = 0
    const t = setInterval(() => {
      done++
      setProgress({ done, total: urlCount })
      if (done >= urlCount) { clearInterval(t); setScanning(false); setHasResults(true) }
    }, 300)
  }

  const counts = {
    ok:       SAMPLE_RESULTS.filter(r => r.status === 200 && r.flags.length === 0).length,
    redirect: SAMPLE_RESULTS.filter(r => r.flags.includes("redirect")).length,
    error:    SAMPLE_RESULTS.filter(r => r.flags.includes("error")).length,
    noindex:  SAMPLE_RESULTS.filter(r => r.flags.includes("noindex")).length,
    thin:     SAMPLE_RESULTS.filter(r => r.flags.includes("thin-content")).length,
  }

  return (
    <AbShell activeNav="urlchecker" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page">
        <h1 className="ab-page-title">URL Checker</h1>
        <p className="ab-page-sub">Scan a list of URLs and check HTTP status, redirect chains, canonical tags, and thin content.</p>

        {/* Input card */}
        <div className="ab-card" style={{ marginBottom: 16 }}>
          <div className="ab-card__header">🔗 URLs to scan</div>
          <div className="ab-card__body">
            <div className="ab-cluster" style={{ marginBottom: 12 }}>
              <button className="ab-btn ab-btn--ghost ab-btn--sm">📄 Load from Sitemap</button>
              <button className="ab-btn ab-btn--ghost ab-btn--sm" onClick={() => setUrls("")}>✕ Clear</button>
              <span className="ab-text-muted ab-small" style={{ marginLeft: "auto" }}>
                {urlCount} URL{urlCount === 1 ? "" : "s"} ready · max 50 per scan
              </span>
            </div>
            <label className="ab-label">URLs (one per line)</label>
            <textarea
              className="ab-input ab-input--mono"
              rows={6}
              value={urls}
              onChange={e => setUrls(e.target.value)}
              placeholder="https://example.com/page-1&#10;https://example.com/page-2"
              style={{ resize: "vertical" }}
            />
            <div className="ab-cluster" style={{ marginTop: 12 }}>
              <button
                className={`ab-btn ${scanning ? "ab-btn--ghost" : "ab-btn--success"}`}
                disabled={!urlCount && !scanning}
                onClick={startScan}
              >
                {scanning ? `↻ Scanning ${progress.done}/${progress.total}…` : "▶ Start Scan"}
              </button>
              {scanning && (
                <button className="ab-btn ab-btn--danger-ghost ab-btn--sm">✕ Cancel</button>
              )}
              <button className="ab-btn ab-btn--ghost" style={{ marginLeft: "auto" }} disabled={!urlCount}>
                Compare vs Google Search Console
              </button>
            </div>
          </div>
        </div>

        {/* Results */}
        {hasResults && (
          <div className="ab-card">
            <div className="ab-card__header">
              Results
              <span className="ab-text-muted ab-small" style={{ marginLeft: 6 }}>({SAMPLE_RESULTS.length} URLs)</span>
              <div className="ab-cluster" style={{ marginLeft: "auto" }}>
                <span className="ab-badge ab-badge--success">200 OK: {counts.ok}</span>
                <span className="ab-badge ab-badge--info">3xx: {counts.redirect}</span>
                <span className="ab-badge ab-badge--danger">4xx/5xx: {counts.error}</span>
                <span className="ab-badge ab-badge--warning">Noindex: {counts.noindex}</span>
                <span className="ab-badge ab-badge--warning">Thin: {counts.thin}</span>
              </div>
            </div>
            <table className="ab-table">
              <thead>
                <tr>
                  <th>URL</th>
                  <th style={{ width: 70, textAlign: "center" }}>Status</th>
                  <th style={{ width: 180 }}>Redirect chain</th>
                  <th style={{ width: 100, textAlign: "center" }}>Canonical</th>
                  <th style={{ width: 160 }}>Flags</th>
                </tr>
              </thead>
              <tbody>
                {SAMPLE_RESULTS.map((r, i) => (
                  <tr key={i}>
                    <td>
                      <code className="ab-code" style={{ fontSize: 11 }}>
                        {r.url.replace("https://staging.offroadserbia.com", "")}
                      </code>
                    </td>
                    <td style={{ textAlign: "center" }}>{statusBadge(r.status)}</td>
                    <td className="ab-small ab-text-muted">
                      {r.redirectChain ? <code className="ab-code" style={{ fontSize: 11 }}>{r.redirectChain}</code> : "—"}
                    </td>
                    <td style={{ textAlign: "center" }}>
                      {r.canonical === "self"
                        ? <span style={{ color: "var(--ab-success)", fontSize: 13 }}>✓ self</span>
                        : <span className="ab-text-muted">—</span>}
                    </td>
                    <td>
                      <div className="ab-cluster" style={{ gap: 4 }}>
                        {r.flags.map(f => (
                          <span key={f} className={`ab-badge ab-badge--${f === "error" ? "danger" : f === "redirect" ? "info" : "warning"}`}
                            style={{ fontSize: 10 }}>
                            {f}
                          </span>
                        ))}
                        {r.flags.length === 0 && <span className="ab-badge ab-badge--success" style={{ fontSize: 10 }}>OK</span>}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <div className="ab-card__footer">
              <button className="ab-btn ab-btn--ghost ab-btn--sm">⬇ Export CSV</button>
              <span className="ab-small ab-text-muted" style={{ marginLeft: "auto" }}>
                Scanned in 1.2s · 2026-05-20 14:38
              </span>
            </div>
          </div>
        )}

        <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
          © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a>
        </p>
      </div>
    </AbShell>
  )
}
