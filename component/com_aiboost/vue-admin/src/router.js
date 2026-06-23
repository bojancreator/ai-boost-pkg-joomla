/**
 * AI Boost SPA router (hash mode).
 *
 * Each Vue route mirrors a legacy PHP view. The `meta.legacyUrl` is fetched
 * lazily by AppShell to materialise the per-view window.aiBoost{X} globals
 * the component expects. Components without external data (HelpPage) leave
 * legacyUrl empty.
 *
 * Routes that have not been migrated to a real Vue component yet
 * (redirects, urlchecker, import) fall through to a stub that redirects
 * to the classic view.
 */

import { createRouter, createWebHashHistory } from 'vue-router'

import DashboardApp     from './DashboardApp.vue'
import AutopilotPage    from './AutopilotPage.vue'
import HealthApp        from './HealthApp.vue'
import SettingsApp      from './App.vue'
import HelpPage         from './HelpPage.vue'
import IntegrationsPage from './IntegrationsPage.vue'
import LicensesPage    from './LicensesPage.vue'
import AnalyzerPage     from './AnalyzerPage.vue'
import RedirectsPage    from './RedirectsPage.vue'
import UrlCheckerPage   from './UrlCheckerPage.vue'
import ImportPage       from './ImportPage.vue'
import ConflictManagerPage from './ConflictManagerPage.vue'
import ChangelogPage     from './ChangelogPage.vue'
import StyleguidePage   from './StyleguidePage.vue'
import { pageRouteAliases, settingsRouteAliases } from './navigation.js'

function urls() {
  const boot = (globalThis.window && globalThis.window.aiBoostBootstrap) || {}
  return boot.legacyUrls || {}
}

export function createSpaRouter() {
  const u = urls()

  const aliasRoutes = settingsRouteAliases.map(({ path, tab }) => ({
    path,
    redirect: (to) => ({ path: '/settings', query: { ...to.query, tab } }),
  }))

  const pageAliases = pageRouteAliases.map(({ path, target }) => ({
    path,
    redirect: target,
  }))

  const routes = [
    { path: '/',             redirect: '/dashboard' },
    ...aliasRoutes,
    ...pageAliases,

    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardApp,
      meta: { legacyUrl: u.dashboard },
    },
    {
      path: '/autopilot',
      name: 'autopilot',
      component: AutopilotPage,
      meta: { legacyUrl: u.settings, title: 'Quick Setup' },
    },
    {
      path: '/settings',
      name: 'settings',
      component: SettingsApp,
      meta: { legacyUrl: u.settings },
    },
    {
      path: '/health',
      name: 'health',
      component: HealthApp,
      meta: { legacyUrl: u.health },
    },
    {
      path: '/health/errors',
      name: 'health-errors',
      component: HealthApp,
      meta: { legacyUrl: u.health, title: 'Health' },
    },
    {
      path: '/integrations',
      name: 'integrations',
      component: IntegrationsPage,
      meta: { legacyUrl: u.integrations },
    },
    {
      path: '/licenses',
      name: 'licenses',
      component: LicensesPage,
      meta: { legacyUrl: '', title: 'License & Updates' },
    },
    {
      path: '/analyzers',
      name: 'analyzers',
      component: AnalyzerPage,
      meta: { legacyUrl: u.analyzer },
    },
    {
      path: '/help',
      name: 'help',
      component: HelpPage,
      meta: { legacyUrl: '' },
    },
    {
      path: '/changelog',
      name: 'changelog',
      component: ChangelogPage,
      meta: { legacyUrl: '', title: "What's New" },
    },
    { path: '/errors', redirect: '/health/errors' },

    {
      path: '/redirects',
      name: 'redirects',
      component: RedirectsPage,
      meta: { legacyUrl: '', title: 'Redirects' },
    },
    {
      path: '/urlchecker',
      name: 'urlchecker',
      component: UrlCheckerPage,
      meta: { legacyUrl: '', title: 'URL Checker' },
    },
    {
      path: '/import',
      name: 'import',
      component: ImportPage,
      meta: { legacyUrl: '', title: 'Import' },
    },
    {
      path: '/conflicts',
      name: 'conflicts',
      component: ConflictManagerPage,
      meta: { legacyUrl: '', title: 'Conflict Manager' },
    },

    // Internal design-system reference page (no nav entry — open via URL hash).
    {
      path: '/_styleguide',
      name: 'styleguide',
      component: StyleguidePage,
      meta: { legacyUrl: '', title: 'AI Boost Design System' },
    },

    { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
  ]

  const router = createRouter({
    history: createWebHashHistory(),
    routes,
  })

  return router
}
