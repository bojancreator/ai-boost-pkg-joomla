import { useState } from "react"
import "./tokens.css"

export type NavItem = {
  id: string
  label: string
  icon: string
  active?: boolean
}

const DEFAULT_NAV: NavItem[] = [
  { id: "dashboard",   label: "Dashboard",     icon: "⊞" },
  { id: "settings",    label: "Settings",       icon: "⚙" },
  { id: "health",      label: "Health",         icon: "♥" },
  { id: "redirects",   label: "Redirects",      icon: "↪" },
  { id: "urlchecker",  label: "URL Checker",    icon: "🔗" },
  { id: "analyzers",   label: "Analyzers",      icon: "🔍" },
  { id: "import",      label: "Import",         icon: "⬆" },
  { id: "help",        label: "Help",           icon: "?" },
]

type Props = {
  activeNav?: string
  dark?: boolean
  onThemeToggle?: (dark: boolean) => void
  children: React.ReactNode
  version?: string
  nav?: NavItem[]
}

export function AbShell({ activeNav = "dashboard", dark = false, onThemeToggle, children, version = "v0.15.0", nav = DEFAULT_NAV }: Props) {
  return (
    <div className={`ab-admin-root${dark ? " dark" : ""}`} style={{ minHeight: "100vh" }}>
      <div className="ab-topbar">
        <div className="ab-topbar__brand">
          <div className="ab-topbar__brand-dot" />
          AI Boost
        </div>
        <nav className="ab-topbar__nav">
          {nav.map(item => (
            <button
              key={item.id}
              className={`ab-nav-item${activeNav === item.id ? " active" : ""}`}
            >
              <span className="nav-icon">{item.icon}</span>
              {item.label}
            </button>
          ))}
        </nav>
        <div className="ab-topbar__right">
          <span className="ab-version-badge">{version}</span>
          <button className="ab-theme-btn" onClick={() => onThemeToggle?.(!dark)}>
            {dark ? "☀" : "☾"}
          </button>
        </div>
      </div>
      {children}
    </div>
  )
}

export function useTheme() {
  const [dark, setDark] = useState(false)
  return { dark, setDark }
}
