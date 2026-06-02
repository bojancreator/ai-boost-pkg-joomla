import { useState } from "react"
import { AbShell, useTheme } from "./_shared/AbShell"
import "./_shared/tokens.css"

/* Variant A: horizontal tab strip (improved current) */
const TABS = [
  { id: "general",   label: "General",       icon: "⊞" },
  { id: "org",       label: "Organisation",  icon: "🏢" },
  { id: "schema",    label: "Schema.org",    icon: "{ }" },
  { id: "sitemap",   label: "Sitemap",       icon: "≡" },
  { id: "social",    label: "Social",        icon: "⇄" },
  { id: "analytics", label: "Analytics",     icon: "▦" },
  { id: "aeo",       label: "AEO",           icon: "⚡" },
  { id: "debug",     label: "Debug",         icon: "🔧" },
  { id: "license",   label: "License",       icon: "🔑" },
]

function FieldRow({ label, children, hint }: { label: string; children: React.ReactNode; hint?: string }) {
  return (
    <div style={{ display: "grid", gridTemplateColumns: "200px 1fr", gap: 16, alignItems: "start", padding: "12px 0", borderBottom: "1px solid var(--ab-border)" }}>
      <div>
        <div style={{ fontWeight: 500, fontSize: 13, color: "var(--ab-text)" }}>{label}</div>
        {hint && <div style={{ fontSize: 11.5, color: "var(--ab-text-muted)", marginTop: 3, lineHeight: 1.4 }}>{hint}</div>}
      </div>
      <div>{children}</div>
    </div>
  )
}

function SchemaTabContent() {
  return (
    <div>
      <FieldRow label="Site Type" hint="Determines which Schema.org type is generated">
        <select className="ab-input" style={{ maxWidth: 280 }}>
          <option>LocalBusiness</option>
          <option selected>TouristAttraction</option>
          <option>Restaurant</option>
          <option>Hotel</option>
          <option>MedicalBusiness</option>
        </select>
      </FieldRow>
      <FieldRow label="FAQ Schema" hint="Inject FAQ structured data on pages with FAQ blocks">
        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
          <div style={{
            width: 40, height: 22, borderRadius: 11,
            background: "var(--ab-success)", position: "relative", cursor: "pointer"
          }}>
            <div style={{ width: 18, height: 18, borderRadius: "50%", background: "#fff", position: "absolute", top: 2, left: 20 }} />
          </div>
          <span className="ab-small ab-text-muted">Enabled — auto-detect FAQ blocks</span>
        </div>
      </FieldRow>
      <FieldRow label="Events Schema" hint="Add Event schema to Joomla content with event dates">
        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
          <div style={{
            width: 40, height: 22, borderRadius: 11,
            background: "var(--ab-border)", position: "relative", cursor: "pointer"
          }}>
            <div style={{ width: 18, height: 18, borderRadius: "50%", background: "#fff", position: "absolute", top: 2, left: 2 }} />
          </div>
          <span className="ab-small ab-text-muted">Disabled</span>
          <span className="ab-badge ab-badge--primary" style={{ marginLeft: 4 }}>Pro</span>
        </div>
      </FieldRow>
      <FieldRow label="BreadcrumbList" hint="Generate BreadcrumbList schema for all pages">
        <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
          <div style={{
            width: 40, height: 22, borderRadius: 11,
            background: "var(--ab-success)", position: "relative", cursor: "pointer"
          }}>
            <div style={{ width: 18, height: 18, borderRadius: "50%", background: "#fff", position: "absolute", top: 2, left: 20 }} />
          </div>
          <span className="ab-small ab-text-muted">Enabled</span>
        </div>
      </FieldRow>
      <FieldRow label="Schema Output Mode" hint="How Schema.org markup is output to the page">
        <div style={{ display: "flex", gap: 8 }}>
          {["Inline JSON-LD (recommended)", "Microdata", "Disabled"].map(opt => (
            <label key={opt} style={{ display: "flex", alignItems: "center", gap: 6, fontSize: 13, cursor: "pointer" }}>
              <input type="radio" name="schema_mode" defaultChecked={opt.includes("JSON-LD")} />
              {opt}
            </label>
          ))}
        </div>
      </FieldRow>
      <FieldRow label="Exclude URLs" hint="Comma-separated paths where Schema.org is disabled">
        <textarea className="ab-input" rows={3} placeholder="/admin, /cart, /checkout" />
      </FieldRow>
    </div>
  )
}

export function SettingsA() {
  const { dark, setDark } = useTheme()
  const [tab, setTab] = useState("schema")
  const [saved, setSaved] = useState(false)

  return (
    <AbShell activeNav="settings" dark={dark} onThemeToggle={setDark}>
      <div className="ab-page" style={{ paddingBottom: 80 }}>

        {/* Sticky save bar */}
        <div style={{
          position: "sticky", top: 48, zIndex: 90,
          background: "var(--ab-surface)",
          borderBottom: "1px solid var(--ab-border)",
          padding: "10px 0",
          display: "flex", alignItems: "center", gap: 10,
          marginBottom: 16, marginLeft: -24, marginRight: -24, paddingLeft: 24, paddingRight: 24
        }}>
          <h1 style={{ fontSize: 16, fontWeight: 700, margin: 0 }}>Settings</h1>
          <div style={{ marginLeft: "auto", display: "flex", gap: 8, alignItems: "center" }}>
            {saved && <span style={{ fontSize: 12, color: "var(--ab-success)" }}>✓ Saved</span>}
            <button className="ab-btn ab-btn--ghost ab-btn--sm">Discard changes</button>
            <button className="ab-btn ab-btn--primary" onClick={() => setSaved(true)}>💾 Save All Settings</button>
          </div>
        </div>

        {/* Tab strip */}
        <div className="ab-tabs" style={{ marginBottom: 20, flexWrap: "nowrap", overflowX: "auto" }}>
          {TABS.map(t => (
            <button key={t.id} className={`ab-tab${tab === t.id ? " active" : ""}`} onClick={() => setTab(t.id)}>
              <span>{t.icon}</span>{t.label}
            </button>
          ))}
        </div>

        {/* Tab content */}
        <div className="ab-card">
          <div className="ab-card__header">
            {TABS.find(t => t.id === tab)?.icon} {TABS.find(t => t.id === tab)?.label}
          </div>
          <div className="ab-card__body">
            {tab === "schema" ? (
              <SchemaTabContent />
            ) : tab === "general" ? (
              <div>
                <FieldRow label="Site Name" hint="Used in all Schema.org markup as the site identifier">
                  <input className="ab-input" defaultValue="Offroad Serbia Adventure Tours" />
                </FieldRow>
                <FieldRow label="Site URL" hint="Base URL of the site (no trailing slash)">
                  <input className="ab-input" defaultValue="https://staging.offroadserbia.com" />
                </FieldRow>
                <FieldRow label="Default Language" hint="ISO language code for schema hreflang defaults">
                  <select className="ab-input" style={{ maxWidth: 180 }}>
                    <option>sr-RS</option>
                    <option>en-GB</option>
                    <option>de-DE</option>
                  </select>
                </FieldRow>
                <FieldRow label="Plugin Debug Log" hint="Write plugin activity to Joomla debug log">
                  <div style={{ display: "flex", alignItems: "center", gap: 10 }}>
                    <div style={{ width: 40, height: 22, borderRadius: 11, background: "var(--ab-border)", position: "relative", cursor: "pointer" }}>
                      <div style={{ width: 18, height: 18, borderRadius: "50%", background: "#fff", position: "absolute", top: 2, left: 2 }} />
                    </div>
                    <span className="ab-small ab-text-muted">Disabled</span>
                  </div>
                </FieldRow>
              </div>
            ) : (
              <div style={{ padding: "32px 0", textAlign: "center", color: "var(--ab-text-muted)" }}>
                <div style={{ fontSize: 32, marginBottom: 8 }}>{TABS.find(t => t.id === tab)?.icon}</div>
                <div style={{ fontWeight: 600, marginBottom: 4 }}>{TABS.find(t => t.id === tab)?.label} Settings</div>
                <div className="ab-small">Fields for this tab would appear here.</div>
              </div>
            )}
          </div>
        </div>
      </div>
    </AbShell>
  )
}
