export function settingsTo(tab) {
  return { path: '/settings', query: tab === 'general' ? {} : { tab } }
}

export const settingsRouteAliases = [
  { path: '/general', tab: 'general' },
  { path: '/site-identity', tab: 'org' },
  { path: '/organization', tab: 'org' },
  { path: '/schema', tab: 'schema' },
  { path: '/technical-seo', tab: 'technical' },
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
  () => ({ title: 'OVERVIEW', items: [
    { id: 'dashboard', to: '/dashboard', icon: 'icon-home', label: 'Dashboard' },
    { id: 'health', to: '/health', icon: 'icon-heart', label: 'Health', badge: 'errors' },
  ] }),
  () => ({ title: 'SETUP', items: [
    { id: 'autopilot', to: '/autopilot', icon: 'icon-lightning', label: 'Autopilot' },
    { id: 'org', to: settingsTo('org'), tab: 'org', icon: 'icon-users', label: 'Site Identity' },
    { id: 'licenses', to: '/licenses', icon: 'icon-key', label: 'License & Updates' },
    { id: 'integrations', to: '/integrations', icon: 'icon-puzzle-piece', label: 'Integrations' },
  ] }),
  () => ({ title: 'SEO', items: [
    { id: 'technical', to: settingsTo('technical'), tab: 'technical', icon: 'icon-cog', label: 'Technical SEO' },
    { id: 'schema', to: settingsTo('schema'), tab: 'schema', icon: 'icon-code', label: 'Schema.org' },
    { id: 'sitemap', to: settingsTo('sitemap'), tab: 'sitemap', icon: 'icon-list', label: 'Sitemap' },
    { id: 'social', to: settingsTo('social'), tab: 'social', icon: 'icon-share', label: 'Social Meta' },
    { id: 'analytics', to: settingsTo('analytics'), tab: 'analytics', icon: 'icon-chart', label: 'Analytics & Tracking' },
  ] }),
  () => ({ title: 'AI VISIBILITY', items: [
    { id: 'aeo', to: settingsTo('aeo'), tab: 'aeo', icon: 'icon-comments', label: 'AI Visibility' },
    { id: 'crawlers', to: settingsTo('crawlers'), tab: 'crawlers', icon: 'icon-link', label: 'Crawlers & Robots' },
  ] }),
  () => ({ title: 'TOOLS', items: [
    { id: 'redirects', to: '/redirects', icon: 'icon-arrow-right', label: 'Redirects' },
    { id: 'analyzers', to: '/analyzers', icon: 'icon-search', label: 'Analyzers' },
    { id: 'urlchecker', to: '/urlchecker', icon: 'icon-link', label: 'URL Checker' },
  ] }),
  () => ({ title: 'ADVANCED', items: [
    { id: 'code', to: settingsTo('code'), tab: 'code', icon: 'icon-wrench', label: 'Custom Code' },
    { id: 'debug', to: settingsTo('debug'), tab: 'debug', icon: 'icon-pencil', label: 'Debug' },
    { id: 'import', to: '/import', icon: 'icon-upload', label: 'Import' },
    { id: 'help', to: '/help', icon: 'icon-question', label: 'Help' },
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