import { Link } from 'react-router-dom'

interface BreadcrumbItem {
  label: string
  to?: string
}

interface BreadcrumbProps {
  items: BreadcrumbItem[]
  maxWidth?: number
}

const SEPARATOR = (
  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" style={{ flexShrink: 0, marginTop: 1 }}>
    <path d="M5 3l4 4-4 4" stroke="#C4BCDE" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
  </svg>
)

export default function Breadcrumb({ items, maxWidth = 1200 }: BreadcrumbProps) {
  return (
    <nav aria-label="Breadcrumb" style={{ maxWidth, margin: '0 auto', padding: '18px 32px 0' }}>
      <ol
        style={{
          display: 'flex',
          alignItems: 'center',
          gap: 6,
          margin: 0,
          padding: 0,
          listStyle: 'none',
          flexWrap: 'wrap',
        }}
      >
        {items.map((item, i) => {
          const isLast = i === items.length - 1
          return (
            <li key={i} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              {i > 0 && SEPARATOR}
              {isLast || !item.to ? (
                <span
                  style={{
                    fontSize: 13,
                    fontWeight: 500,
                    color: '#0C0B1D',
                    letterSpacing: '-.1px',
                  }}
                  aria-current={isLast ? 'page' : undefined}
                >
                  {item.label}
                </span>
              ) : (
                <Link
                  to={item.to}
                  style={{
                    fontSize: 13,
                    fontWeight: 500,
                    color: '#7B7BA0',
                    textDecoration: 'none',
                    letterSpacing: '-.1px',
                  }}
                >
                  {item.label}
                </Link>
              )}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
