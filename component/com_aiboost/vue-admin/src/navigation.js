export function settingsTo(tab) {
  // 'technical' is the default (no-query) tab — the merged General + Technical SEO page.
  return { path: '/settings', query: tab === 'technical' ? {} : { tab } }
}

export const settingsRouteAliases = [
  // The old General tab was merged into Technical SEO.
  { path: '/general', tab: 'technical' },
  { path: '/site-identity', tab: 'org' },
  { path: '/organization', tab: 'org' },
  { path: '/schema', tab: 'schema' },
  { path: '/technical-seo', tab: 'technical' },
  { path: '/titles', tab: 'titles' },
  { path: '/titles-meta', tab: 'titles' },
  { path: '/sitemap', tab: 'sitemap' },
  { path: '/robots', tab: 'crawlers' },
  { path: '/crawlers-robots', tab: 'crawlers' },
  { path: '/social', tab: 'social' },
  { path: '/social-meta', tab: 'social' },
  { path: '/analytics', tab: 'analytics' },
  { path: '/analytics-tracking', tab: 'analytics' },
  { path: '/aeo', tab: 'aeo' },
  { path: '/custom-code', tab: 'code' },
  { path: '/code', tab: 'code' },
  { path: '/debug', tab: 'debug' },
]

export const pageRouteAliases = [
  { path: '/setup', target: '/autopilot' },
  { path: '/license-updates', target: '/licenses' },
]

const sidebarGroupFactories = [
  () => ({ title: 'Overview', items: [
    { id: 'dashboard', to: '/dashboard', icon: 'dash', label: 'Dashboard' },
    { id: 'health', to: '/health', icon: 'heart', label: 'Health', badge: 'errors' },
  ] }),
  () => ({ title: 'Setup', items: [
    { id: 'autopilot', to: '/autopilot', icon: 'bolt', label: 'Quick Setup' },
    { id: 'org', to: settingsTo('org'), tab: 'org', icon: 'id', label: 'Site Identity' },
    { id: 'licenses', to: '/licenses', icon: 'key', label: 'License & Updates' },
    { id: 'integrations', to: '/integrations', icon: 'plug', label: 'Integrations' },
    { id: 'conflicts', to: '/conflicts', icon: 'shield', label: 'Conflict Manager', badge: 'conflicts' },
  ] }),
  () => ({ title: 'SEO', items: [
    { id: 'technical', to: settingsTo('technical'), tab: 'technical', icon: 'cog', label: 'Technical SEO' },
    { id: 'titles', to: settingsTo('titles'), tab: 'titles', icon: 'tag', label: 'Titles & Meta' },
    { id: 'schema', to: settingsTo('schema'), tab: 'schema', icon: 'schema', label: 'Schema.org' },
    { id: 'sitemap', to: settingsTo('sitemap'), tab: 'sitemap', icon: 'map', label: 'Sitemap' },
    { id: 'social', to: settingsTo('social'), tab: 'social', icon: 'share', label: 'Social Meta / OG' },
    { id: 'analytics', to: settingsTo('analytics'), tab: 'analytics', icon: 'chart', label: 'Analytics & Tracking' },
  ] }),
  () => ({ title: 'AI Visibility', items: [
    { id: 'aeo', to: settingsTo('aeo'), tab: 'aeo', icon: 'ai', label: 'AEO' },
    { id: 'crawlers', to: settingsTo('crawlers'), tab: 'crawlers', icon: 'robot', label: 'Crawlers & Robots' },
  ] }),
  () => ({ title: 'Tools', items: [
    { id: 'redirects', to: '/redirects', icon: 'arrow', label: 'Redirects' },
    { id: 'analyzers', to: '/analyzers', icon: 'search', label: 'Analyzers' },
    { id: 'urlchecker', to: '/urlchecker', icon: 'link', label: 'URL Checker' },
    { id: 'import', to: '/import', icon: 'upload', label: 'Import' },
  ] }),
  () => ({ title: 'Advanced', items: [
    { id: 'code', to: settingsTo('code'), tab: 'code', icon: 'code', label: 'Custom Code' },
    { id: 'debug', to: settingsTo('debug'), tab: 'debug', icon: 'bug', label: 'Debug' },
    { id: 'help', to: '/help', icon: 'help', label: 'Help' },
  ] }),
]

export function createSidebarGroups(labels = {}) {
  return sidebarGroupFactories.map((makeGroup) => applySidebarLabels(makeGroup(), labels))
}

function applySidebarLabels(group, labels) {
  return {
    ...group,
    items: group.items.map((item) => ({ ...item, label: labels[item.id] || item.label })),
  }
}