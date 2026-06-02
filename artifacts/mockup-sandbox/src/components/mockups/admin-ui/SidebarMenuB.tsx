import { useState } from "react"
import "./_shared/tokens.css"

type Item = { id: string; label: string; icon: string; pro?: boolean; hint?: string }
type Group = { title: string; items: Item[] }

const GROUPS: Group[] = [
  {
    title: "PREGLED",
    items: [
      { id: "dashboard", label: "Dashboard", icon: "⊞" },
      { id: "health", label: "Health", icon: "♥" },
      { id: "errors", label: "Errors", icon: "!" },
    ],
  },
  {
    title: "SEO FUNKCIJE",
    items: [
      { id: "schema", label: "Schema.org", icon: "{ }" },
      { id: "sitemap", label: "Sitemap", icon: "≡" },
      { id: "social", label: "Social & Meta", icon: "⇄" },
      { id: "analytics", label: "Analytics", icon: "▦" },
      { id: "aeo", label: "AEO & llms.txt", icon: "⚡" },
      { id: "code", label: "Custom Code", icon: "</>" },
    ],
  },
  {
    title: "ALATI",
    items: [
      { id: "analyzers", label: "Analyzers", icon: "🔍", pro: true },
      { id: "urlchecker", label: "URL Checker", icon: "🔗", pro: true },
      { id: "redirects", label: "Redirects", icon: "↪", pro: true },
      { id: "import", label: "Import", icon: "⬆", pro: true },
    ],
  },
  {
    title: "PODEŠAVANJE",
    items: [
      { id: "general", label: "General", icon: "⚙" },
      { id: "organization", label: "Organization", icon: "🏢" },
      { id: "integrations", label: "Integrations", icon: "🧩", pro: true },
      { id: "licenses", label: "Licenses", icon: "🔑", pro: true },
      { id: "debug", label: "Debug", icon: "🐞" },
      { id: "help", label: "Help", icon: "?" },
    ],
  },
]

export function SidebarMenuB() {
  const [dark, setDark] = useState(false)
  const [active, setActive] = useState("schema")
  const activeItem = GROUPS.flatMap((g) => g.items).find((i) => i.id === active)

  return (
    <div
      className={`ab-admin-root${dark ? " dark" : ""}`}
      style={{
        display: "flex",
        minHeight: "100vh",
        height: "100%",
        background: "var(--ab-bg)",
      }}
    >
      {/* ───────── Sidebar ───────── */}
      <aside
        style={{
          width: 248,
          flexShrink: 0,
          background: "var(--ab-topbar-bg)",
          borderRight: "1px solid var(--ab-topbar-border)",
          display: "flex",
          flexDirection: "column",
          position: "sticky",
          top: 0,
          height: "100vh",
        }}
      >
        {/* Brand */}
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: 9,
            padding: "16px 18px",
            borderBottom: "1px solid var(--ab-topbar-border)",
          }}
        >
          <div
            style={{
              width: 10,
              height: 10,
              borderRadius: "50%",
              background: "var(--ab-primary)",
              boxShadow: "0 0 0 3px rgba(13,110,253,.25)",
            }}
          />
          <span
            style={{
              fontSize: 15,
              fontWeight: 700,
              color: "#fff",
              letterSpacing: ".3px",
            }}
          >
            AI Boost
          </span>
          <span
            style={{
              marginLeft: "auto",
              fontSize: 10,
              fontWeight: 600,
              color: "#fff",
              background: "var(--ab-primary)",
              borderRadius: 5,
              padding: "2px 6px",
            }}
          >
            v0.64.4
          </span>
        </div>

        {/* Nav groups */}
        <nav
          style={{
            flex: 1,
            overflowY: "auto",
            padding: "10px 10px 16px",
          }}
        >
          {GROUPS.map((group) => (
            <div key={group.title} style={{ marginBottom: 14 }}>
              <div
                style={{
                  fontSize: 10,
                  fontWeight: 700,
                  letterSpacing: ".8px",
                  color: "var(--ab-topbar-text)",
                  opacity: 0.6,
                  padding: "0 10px 6px",
                }}
              >
                {group.title}
              </div>
              <div style={{ display: "flex", flexDirection: "column", gap: 2 }}>
                {group.items.map((item) => {
                  const isActive = item.id === active
                  return (
                    <button
                      key={item.id}
                      onClick={() => setActive(item.id)}
                      style={{
                        display: "flex",
                        alignItems: "center",
                        gap: 10,
                        width: "100%",
                        textAlign: "left",
                        border: "none",
                        cursor: "pointer",
                        borderRadius: 7,
                        padding: "8px 10px",
                        fontSize: 13.5,
                        fontWeight: isActive ? 600 : 500,
                        color: isActive ? "#fff" : "var(--ab-topbar-text)",
                        background: isActive
                          ? "var(--ab-primary)"
                          : "transparent",
                        transition: "background .12s",
                      }}
                      onMouseEnter={(e) => {
                        if (!isActive)
                          e.currentTarget.style.background =
                            "rgba(255,255,255,.06)"
                      }}
                      onMouseLeave={(e) => {
                        if (!isActive)
                          e.currentTarget.style.background = "transparent"
                      }}
                    >
                      <span
                        style={{
                          width: 18,
                          textAlign: "center",
                          fontSize: 13,
                          opacity: isActive ? 1 : 0.8,
                          flexShrink: 0,
                        }}
                      >
                        {item.icon}
                      </span>
                      <span style={{ flex: 1 }}>{item.label}</span>
                      {item.pro && (
                        <span
                          style={{
                            fontSize: 9,
                            fontWeight: 700,
                            letterSpacing: ".4px",
                            color: isActive ? "#fff" : "#ffd166",
                            border: `1px solid ${
                              isActive ? "rgba(255,255,255,.5)" : "#ffd16655"
                            }`,
                            borderRadius: 4,
                            padding: "1px 5px",
                          }}
                        >
                          PRO
                        </span>
                      )}
                    </button>
                  )
                })}
              </div>
            </div>
          ))}
        </nav>

        {/* Theme toggle */}
        <button
          onClick={() => setDark((d) => !d)}
          style={{
            display: "flex",
            alignItems: "center",
            gap: 8,
            margin: 10,
            padding: "8px 10px",
            borderRadius: 7,
            border: "1px solid var(--ab-topbar-border)",
            background: "transparent",
            color: "var(--ab-topbar-text)",
            cursor: "pointer",
            fontSize: 12.5,
          }}
        >
          <span>{dark ? "☀" : "☾"}</span>
          {dark ? "Svijetla tema" : "Tamna tema"}
        </button>
      </aside>

      {/* ───────── Main content ───────── */}
      <main style={{ flex: 1, overflowY: "auto", minWidth: 0 }}>
        {/* Page header */}
        <div
          style={{
            display: "flex",
            alignItems: "center",
            gap: 10,
            padding: "16px 24px",
            borderBottom: "1px solid var(--ab-border)",
            background: "var(--ab-surface)",
            position: "sticky",
            top: 0,
            zIndex: 5,
          }}
        >
          <span style={{ fontSize: 18 }}>{activeItem?.icon}</span>
          <h1 style={{ fontSize: 17, fontWeight: 700, margin: 0 }}>
            {activeItem?.label}
          </h1>
          {activeItem?.pro && (
            <span
              style={{
                fontSize: 10,
                fontWeight: 700,
                color: "#fff",
                background: "var(--ab-warning)",
                borderRadius: 5,
                padding: "2px 7px",
              }}
            >
              PRO
            </span>
          )}
          <span
            style={{
              marginLeft: "auto",
              fontSize: 12,
              color: "var(--ab-text-muted)",
            }}
          >
            Prijedlog rasporeda — Plan B
          </span>
        </div>

        {/* Body */}
        <div style={{ padding: 24 }}>
          <div
            style={{
              background: "var(--ab-primary)",
              color: "#fff",
              borderRadius: 10,
              padding: "16px 18px",
              marginBottom: 18,
              fontSize: 13.5,
              lineHeight: 1.55,
            }}
          >
            Glavne SEO/AEO funkcije (Schema, Sitemap, Social, Analytics, AEO,
            Custom Code) su sada na <strong>prvom nivou</strong> u bočnom meniju —
            uvijek vidljive i na jedan klik, bez ulaska u Settings.
          </div>

          {/* Demo placeholder cards */}
          <div
            style={{
              display: "grid",
              gridTemplateColumns: "repeat(auto-fill, minmax(240px, 1fr))",
              gap: 14,
            }}
          >
            {[
              { h: "Schema.org tipovi", t: "Article, Organization, FAQ, Product, LocalBusiness — 13 tipova sajta." },
              { h: "Status generisanja", t: "JSON-LD se ubacuje u <head> na svim stranicama." },
              { h: "Health veza", t: "Greške u schemi se prikazuju i u Health pregledu." },
            ].map((c) => (
              <div
                key={c.h}
                style={{
                  background: "var(--ab-card-bg)",
                  border: "1px solid var(--ab-card-border)",
                  borderRadius: 9,
                  padding: 16,
                  boxShadow: "var(--ab-card-shadow)",
                }}
              >
                <div
                  style={{
                    fontSize: 13.5,
                    fontWeight: 600,
                    marginBottom: 6,
                  }}
                >
                  {c.h}
                </div>
                <div
                  style={{
                    fontSize: 12.5,
                    color: "var(--ab-text-muted)",
                    lineHeight: 1.5,
                  }}
                >
                  {c.t}
                </div>
              </div>
            ))}
          </div>
        </div>
      </main>
    </div>
  )
}
