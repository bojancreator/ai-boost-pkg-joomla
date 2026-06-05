/**
 * AI Boost — Vue admin entry.
 *
 * Two modes:
 *   1. SPA mode  — when the page contains `#ab-app` (the new AppShell PHP view),
 *      the whole admin UI runs as one Vue 3 SPA with hash routing.
 *   2. Legacy mode — when the page contains the per-view mount points
 *      (`#ab-vue-dashboard`, `#ab-vue-settings`, …), each component is mounted
 *      individually as before. This keeps `?option=com_aiboost&view=...` URLs
 *      working during the migration window.
 */

import { createApp } from 'vue'
import { installErrorReporter, vueErrorHandler } from './composables/errorReporter.js'
import { installOnlineStatus } from './composables/useOnlineStatus.js'
import AppShell         from './AppShell.vue'
import DashboardApp     from './DashboardApp.vue'
import HealthApp        from './HealthApp.vue'
import HelpPage         from './HelpPage.vue'
import IntegrationsPage from './IntegrationsPage.vue'
import AnalyzerPage     from './AnalyzerPage.vue'
import RedirectsPage    from './RedirectsPage.vue'
import UrlCheckerPage   from './UrlCheckerPage.vue'
import ImportPage       from './ImportPage.vue'
import SettingsApp      from './App.vue'
import ProGate          from './components/ProGate.vue'
import ToastStack       from './components/ToastStack.vue'
import { createSpaRouter } from './router.js'

/**
 * Register ProGate globally so existing tab templates can keep their wrapper
 * markup while the component renders as a pass-through during the one-product
 * transition.
 */
function installGlobals(app) {
  app.component('ProGate', ProGate)
  // Task #513 — every Vue app instance gets the global errorHandler so
  // uncaught render/lifecycle exceptions are toasted and forwarded to
  // the server-side error log (source = "frontend:vue").
  app.config.errorHandler = vueErrorHandler
  return app
}

function mountSpa(el) {
  const app = installGlobals(createApp(AppShell))
  app.use(createSpaRouter())
  app.mount(el)
}

/**
 * Mount a singleton ToastStack into <body> for legacy mode so global
 * errors and api.js failures surface even when AppShell isn't loaded
 * (Task #513 review fix). SPA mode already mounts it inside AppShell.
 */
function mountLegacyToastStack() {
  if (document.getElementById('ab-toast-root')) return
  const root = document.createElement('div')
  root.id = 'ab-toast-root'
  document.body.appendChild(root)
  installGlobals(createApp(ToastStack)).mount(root)
}

function mountLegacy() {
  mountLegacyToastStack()
  const settingsEl = document.getElementById('ab-vue-settings')
  if (settingsEl) installGlobals(createApp(SettingsApp)).mount(settingsEl)

  const dashboardEl = document.getElementById('ab-vue-dashboard')
  if (dashboardEl) installGlobals(createApp(DashboardApp)).mount(dashboardEl)

  const healthEl = document.getElementById('ab-vue-health')
  if (healthEl) installGlobals(createApp(HealthApp)).mount(healthEl)

  const helpEl = document.getElementById('ab-vue-help')
  if (helpEl) installGlobals(createApp(HelpPage)).mount(helpEl)

  const integrationsEl = document.getElementById('ab-vue-integrations')
  if (integrationsEl) installGlobals(createApp(IntegrationsPage)).mount(integrationsEl)

  const analyzerEl = document.getElementById('ab-vue-analyzer')
  if (analyzerEl) installGlobals(createApp(AnalyzerPage)).mount(analyzerEl)

  const redirectsEl = document.getElementById('ab-vue-redirects')
  if (redirectsEl) installGlobals(createApp(RedirectsPage)).mount(redirectsEl)

  const urlcheckerEl = document.getElementById('ab-vue-urlchecker')
  if (urlcheckerEl) installGlobals(createApp(UrlCheckerPage)).mount(urlcheckerEl)

  const importEl = document.getElementById('ab-vue-import')
  if (importEl) installGlobals(createApp(ImportPage)).mount(importEl)
}

document.addEventListener('DOMContentLoaded', () => {
  // Install once per page-load — covers both SPA and legacy modes.
  installOnlineStatus()
  installErrorReporter()

  const spaEl = document.getElementById('ab-app')
  if (spaEl) {
    mountSpa(spaEl)
    return
  }
  mountLegacy()
})
