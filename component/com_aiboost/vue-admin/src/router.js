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
import HealthApp        from './HealthApp.vue'
import SettingsApp      from './App.vue'
import HelpPage         from './HelpPage.vue'
import IntegrationsPage from './IntegrationsPage.vue'
import LicensesPage    from './LicensesPage.vue'
import AnalyzerPage     from './AnalyzerPage.vue'
import RedirectsPage    from './RedirectsPage.vue'
import UrlCheckerPage   from './UrlCheckerPage.vue'
import ImportPage       from './ImportPage.vue'
import StyleguidePage   from './StyleguidePage.vue'
import ErrorsPage       from './ErrorsPage.vue'

function urls() {
  const boot = window.aiBoostBootstrap || {}
  return boot.legacyUrls || {}
}

/**
 * v0.55.0 — The route-level Pro guard was removed. Per Bojan's directive,
 * every Pro page must be REACHABLE on Free / unlicensed installs so the
 * user can see what they would get with Pro. The page contents are
 * rendered through <ProGate gate-key="page:*"> instead, which shows a
 * muted preview and an "Unlock Pro version" link to the pricing page.
 *
 * `import` is the lone exception — it stays hidden from the TopNav
 * (`proHidden`) because it is an export-only utility and not a feature
 * we want to advertise. We keep a passive redirect on Free so a stale
 * /#/import deep link does not error.
 *
 * Server-side enforcement (controllers + ProFeatureRegistry::stripLocked)
 * remains the real authorization boundary.
 */
function isProTier() {
  const boot = window.aiBoostBootstrap || {}
  return !!(boot.isPro || (boot.license && boot.license.isPro))
}

export function createSpaRouter() {
  const u = urls()

  const routes = [
    { path: '/',             redirect: '/dashboard' },

    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardApp,
      meta: { legacyUrl: u.dashboard },
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
      path: '/integrations',
      name: 'integrations',
      component: IntegrationsPage,
      meta: { legacyUrl: u.integrations, proGate: 'page:integrations' },
    },
    {
      path: '/licenses',
      name: 'licenses',
      component: LicensesPage,
      // Licenses uses proGateForceUnlockOnInstall so a Pro install with
      // no verified key yet can still type one in.
      meta: {
        legacyUrl: '', title: 'Licenses',
        proGate: 'page:licenses',
        proGateForceUnlockOnInstall: true,
      },
    },
    {
      path: '/analyzers',
      name: 'analyzers',
      component: AnalyzerPage,
      meta: { legacyUrl: u.analyzer, proGate: 'page:analyzers' },
    },
    {
      path: '/help',
      name: 'help',
      component: HelpPage,
      meta: { legacyUrl: '' },
    },
    {
      // Task #512 — Errors tab: Vue-only, no legacyUrl (data via AJAX).
      // Available on Free too — error log is a free feature.
      path: '/errors',
      name: 'errors',
      component: ErrorsPage,
      meta: { legacyUrl: '', title: 'Errors' },
    },

    {
      path: '/redirects',
      name: 'redirects',
      component: RedirectsPage,
      meta: { legacyUrl: '', title: 'Redirects', proGate: 'page:redirects' },
    },
    {
      path: '/urlchecker',
      name: 'urlchecker',
      component: UrlCheckerPage,
      meta: { legacyUrl: '', title: 'URL Checker', proGate: 'page:urlchecker' },
    },
    {
      path: '/import',
      name: 'import',
      component: ImportPage,
      meta: { legacyUrl: '', title: 'Import' },
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

  router.beforeEach((to) => {
    // `import` is the only Pro route still bounced — it is hidden from
    // the TopNav (proHidden) and serves no purpose on Free.
    if (to.name === 'import' && !isProTier()) {
      return { name: 'dashboard' }
    }
    return true
  })

  return router
}
