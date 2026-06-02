import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

export function ImportA() {
  const { dark, setDark } = useTheme()
  const [file, setFile] = useState<string | null>(null)
  const [importing, setImporting] = useState(false)
  const [done, setDone] = useState(false)

  function handleFile(e: React.ChangeEvent<HTMLInputElement>) {
    setFile(e.target.files?.[0]?.name ?? null)
    setDone(false)
  }

  function handleImport() {
    setImporting(true)
    setTimeout(() => { setImporting(false); setDone(true) }, 1400)
  }

  return (
    <AbShell activeNav="import" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page">
        <h1 className="ab-page-title">Import / Export Settings</h1>
        <p className="ab-page-sub">Move your AI Boost configuration between Joomla sites by exporting a JSON snapshot and importing it on another installation.</p>

        <div className="ab-grid-2" style={{ alignItems: "start" }}>

          {/* Export card */}
          <div className="ab-card">
            <div className="ab-card__header">⬇ Export current settings</div>
            <div className="ab-card__body">
              <p className="ab-text-muted ab-small" style={{ marginBottom: 16 }}>
                Downloads a single <code className="ab-code">.json</code> file with every key currently saved in <code className="ab-code">#__aiboost_settings</code>.
                Translations are included when the multilingual feature is active.
              </p>
              <div style={{ padding: "16px", background: "var(--ab-surface-raised)", borderRadius: 6, border: "1px solid var(--ab-border)", marginBottom: 16, fontSize: 12, color: "var(--ab-text-muted)" }}>
                <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                  <span>Settings keys</span><strong style={{ color: "var(--ab-text)" }}>247</strong>
                </div>
                <div style={{ display: "flex", justifyContent: "space-between", marginBottom: 4 }}>
                  <span>Translations</span><strong style={{ color: "var(--ab-text)" }}>3 languages</strong>
                </div>
                <div style={{ display: "flex", justifyContent: "space-between" }}>
                  <span>Last saved</span><strong style={{ color: "var(--ab-text)" }}>2026-05-19 14:32</strong>
                </div>
              </div>
              <a className="ab-btn ab-btn--ghost" href="#">⬇ Download settings export (.json)</a>
            </div>
          </div>

          {/* Import card */}
          <div className="ab-card">
            <div className="ab-card__header">⬆ Import from file</div>
            <div className="ab-card__body">
              <p className="ab-text-muted ab-small" style={{ marginBottom: 14 }}>
                Select a JSON export file from another AI Boost installation. All existing settings will be overwritten.
              </p>

              {/* Drop zone */}
              <div
                style={{
                  border: "2px dashed var(--ab-border-strong)",
                  borderRadius: 7, padding: "24px",
                  textAlign: "center", cursor: "pointer",
                  background: file ? "rgba(13,110,253,.04)" : "var(--ab-surface-raised)",
                  transition: "background .15s",
                  marginBottom: 14
                }}
                onClick={() => document.getElementById("ab-file-input")?.click()}
              >
                <div style={{ fontSize: 28, marginBottom: 6 }}>📄</div>
                {file ? (
                  <>
                    <div style={{ fontWeight: 600, fontSize: 13, color: "var(--ab-primary)", marginBottom: 2 }}>{file}</div>
                    <div className="ab-small ab-text-muted">Click to change file</div>
                  </>
                ) : (
                  <>
                    <div style={{ fontWeight: 500, fontSize: 13, marginBottom: 2 }}>Drop JSON file here</div>
                    <div className="ab-small ab-text-muted">or click to browse — max 5 MB</div>
                  </>
                )}
                <input id="ab-file-input" type="file" accept=".json,application/json" style={{ display: "none" }} onChange={handleFile} />
              </div>

              {done && (
                <div className="ab-alert ab-alert--success" style={{ marginBottom: 12 }}>
                  ✓ Settings imported successfully. Page will reload…
                </div>
              )}

              <div style={{ display: "flex", gap: 10, alignItems: "center" }}>
                <button
                  className={`ab-btn ab-btn--primary${!file || importing ? " " : ""}`}
                  disabled={!file || importing || done}
                  onClick={handleImport}
                >
                  {importing ? (
                    <><span style={{ display: "inline-block", animation: "spin 1s linear infinite", marginRight: 4 }}>↻</span>Importing…</>
                  ) : "⬆ Import settings"}
                </button>
                <span className="ab-small ab-text-muted">Existing settings will be overwritten.</span>
              </div>
            </div>
          </div>

        </div>

        {/* Migration notice */}
        <div className="ab-alert ab-alert--info" style={{ marginTop: 20 }}>
          <span>ℹ</span>
          <div>
            <strong>Migrating from the legacy plugin?</strong> The legacy export format (<code className="ab-code">plg_system_joomlaboost</code> v0.40.x) is automatically converted on import.
          </div>
        </div>

        <p style={{ fontSize: 12, color: "var(--ab-text-muted)", marginTop: 20 }}>
          © 2026 <a href="#" style={{ color: "var(--ab-primary)" }}>AI Boost</a> · <a href="#" style={{ color: "var(--ab-primary)" }}>Documentation</a>
        </p>
      </div>
    </AbShell>
  )
}
