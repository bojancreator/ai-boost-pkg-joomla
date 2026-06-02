<template>
  <aside class="ab-sidebar">
    <div class="ab-sidebar__brand">
      <span class="ab-sidebar__brand-dot" aria-hidden="true"></span>
      <span class="ab-sidebar__brand-name">AI Boost</span>
      <span v-if="version" class="ab-sidebar__version">{{ version }}</span>
    </div>

    <nav class="ab-sidebar__nav" aria-label="AI Boost navigation">
      <div v-for="group in groups" :key="group.title" class="ab-sidebar__group">
        <div class="ab-sidebar__group-title">{{ group.title }}</div>
        <ul class="ab-sidebar__list">
          <li v-for="item in group.items" :key="item.id">
            <router-link
              class="ab-sidebar__item"
              :class="{ active: isItemActive(item) }"
              :to="item.to"
            >
              <span v-if="isItemLocked(item)" class="ab-sidebar__lock" aria-hidden="true">🔒</span>
              <span v-else class="ab-sidebar__icon" :class="item.icon" aria-hidden="true"></span>
              <span class="ab-sidebar__label">{{ item.label }}</span>
              <span
                v-if="item.id === 'errors' && errorsBadge > 0"
                class="ab-sidebar__badge"
                :title="errorsBadge + ' error(s) in last 24h'"
              >{{ errorsBadge }}</span>
              <span v-else-if="item.pro" class="ab-sidebar__pro">PRO</span>
            </router-link>
          </li>
        </ul>
      </div>
    </nav>
  </aside>
</template>

<script>
import { useRoute } from 'vue-router'

export default {
  name: 'Sidebar',

  setup() {
    const route = useRoute()
    const boot = window.aiBoostBootstrap || {}
    const labels = boot.labels || {}

    const isPro = !!(boot.isPro || (boot.license && boot.license.isPro))
    const isProInstall = !!(boot.isProInstall
      || (boot.license && boot.license.isProInstall))

    // Settings sub-tab target: a query on the /settings route. App.vue
    // watches `$route.query.tab` and switches its active tab reactively.
    const settingsTo = (tab) => ({ path: '/settings', query: { tab } })

    const groups = [
      {
        title: 'OVERVIEW',
        items: [
          { id: 'dashboard', to: '/dashboard', icon: 'icon-home',    label: labels.dashboard || 'Dashboard' },
          { id: 'health',    to: '/health',    icon: 'icon-heart',   label: labels.health    || 'Health' },
          { id: 'errors',    to: '/errors',    icon: 'icon-warning', label: labels.errors    || 'Errors' },
        ],
      },
      {
        title: 'SEO FEATURES',
        items: [
          { id: 'schema',    to: settingsTo('schema'),    tab: 'schema',    icon: 'icon-code',     label: 'Schema.org' },
          { id: 'sitemap',   to: settingsTo('sitemap'),   tab: 'sitemap',   icon: 'icon-list',     label: 'Sitemap' },
          { id: 'social',    to: settingsTo('social'),    tab: 'social',     icon: 'icon-share',    label: 'Social & Meta' },
          { id: 'analytics', to: settingsTo('analytics'), tab: 'analytics', icon: 'icon-chart',    label: 'Analytics' },
          { id: 'aeo',       to: settingsTo('aeo'),       tab: 'aeo',        icon: 'icon-comments', label: 'AEO' },
          { id: 'code',      to: settingsTo('code'),      tab: 'code',       icon: 'icon-wrench',   label: 'Custom Code' },
        ],
      },
      {
        title: 'TOOLS',
        items: [
          { id: 'analyzers',  to: '/analyzers',  icon: 'icon-search',       label: labels.analyzers  || 'Analyzers',   pro: true },
          { id: 'urlchecker', to: '/urlchecker', icon: 'icon-link',         label: labels.urlchecker || 'URL Checker', pro: true },
          { id: 'redirects',  to: '/redirects',  icon: 'icon-arrow-right',  label: labels.redirects  || 'Redirects',   pro: true },
          { id: 'import',     to: '/import',     icon: 'icon-upload',       label: labels.import     || 'Import',      pro: true, proHidden: true },
        ],
      },
      {
        title: 'SETTINGS',
        items: [
          { id: 'general',      to: settingsTo('general'), tab: 'general', icon: 'icon-cog',           label: 'General' },
          { id: 'org',          to: settingsTo('org'),     tab: 'org',     icon: 'icon-users',         label: 'Organization' },
          { id: 'integrations', to: '/integrations',       icon: 'icon-puzzle-piece',                  label: labels.integrations || 'Integrations', pro: true },
          { id: 'licenses',     to: '/licenses',           icon: 'icon-key',                           label: labels.licenses     || 'Licenses',     pro: true, allowOnProInstall: true },
          { id: 'debug',        to: settingsTo('debug'),   tab: 'debug',   icon: 'icon-pencil',        label: 'Debug' },
          { id: 'help',         to: '/help',               icon: 'icon-question',                      label: labels.help         || 'Help' },
        ],
      },
    ]

    // Hide proHidden routes (import) on Free/unlicensed installs.
    groups.forEach(g => { g.items = g.items.filter(it => !it.proHidden || isPro) })

    const isItemLocked = (item) => {
      if (!item.pro) return false
      if (isPro) return false
      if (item.allowOnProInstall && isProInstall) return false
      return true
    }

    const isItemActive = (item) => {
      // Settings sub-tab items are active when on /settings and the active
      // tab matches (default tab is "general" when no query present).
      if (item.tab) {
        return route.name === 'settings' && (route.query.tab || 'general') === item.tab
      }
      return route.name === item.id
    }

    const errorsBadge = (boot.errorsSummary && Number(boot.errorsSummary.errors_24h)) || 0
    const version = boot.version ? ('v' + String(boot.version).replace(/^v/, '')) : ''

    return { groups, isPro, isProInstall, isItemLocked, isItemActive, errorsBadge, version }
  },
}
</script>

<style>
.ab-sidebar {
  flex: 0 0 230px;
  width: 230px;
  align-self: stretch;
  display: flex;
  flex-direction: column;
  background: #1e2532;
  border-right: 1px solid #2d3548;
  border-radius: 8px 0 0 8px;
  position: sticky;
  top: 0;
  max-height: calc(100vh - 1rem);
  overflow: hidden;
}

.ab-sidebar__brand {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 14px 16px;
  border-bottom: 1px solid #2d3548;
}
.ab-sidebar__brand-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--ab-primary, #4f46e5);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ab-primary, #4f46e5) 30%, transparent);
  flex-shrink: 0;
}
.ab-sidebar__brand-name {
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  letter-spacing: .3px;
}
.ab-sidebar__version {
  margin-left: auto;
  font-size: 10px;
  font-weight: 600;
  color: #fff;
  background: var(--ab-primary, #4f46e5);
  border-radius: 5px;
  padding: 2px 6px;
}

.ab-sidebar__nav {
  flex: 1;
  overflow-y: auto;
  padding: 10px 10px 16px;
}
.ab-sidebar__group { margin-bottom: 14px; }
.ab-sidebar__group-title {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .8px;
  color: #8a93a6;
  padding: 0 10px 6px;
}
.ab-sidebar__list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ab-sidebar__item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  border-radius: 7px;
  padding: 8px 10px;
  font-size: 13.5px;
  font-weight: 500;
  color: #c8d0e0;
  text-decoration: none;
  transition: background .12s, color .12s;
}
.ab-sidebar__item:hover {
  background: rgba(255, 255, 255, .06);
  color: #fff;
  text-decoration: none;
}
.ab-sidebar__item.active {
  background: var(--ab-primary, #4f46e5);
  color: #fff;
  font-weight: 600;
}
.ab-sidebar__icon {
  width: 18px;
  text-align: center;
  font-size: 13px;
  opacity: .85;
  flex-shrink: 0;
}
.ab-sidebar__item.active .ab-sidebar__icon { opacity: 1; }
.ab-sidebar__lock {
  width: 18px;
  text-align: center;
  font-size: 12px;
  flex-shrink: 0;
}
.ab-sidebar__label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.ab-sidebar__pro {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: .4px;
  color: #ffd166;
  border: 1px solid rgba(255, 209, 102, .35);
  border-radius: 4px;
  padding: 1px 5px;
}
.ab-sidebar__item.active .ab-sidebar__pro {
  color: #fff;
  border-color: rgba(255, 255, 255, .5);
}
.ab-sidebar__badge {
  font-size: 10px;
  font-weight: 700;
  color: #fff;
  background: #dc3545;
  border-radius: 10px;
  padding: 1px 7px;
  line-height: 1.4;
}

/* Stack the sidebar on top on narrow viewports. */
@media (max-width: 782px) {
  .ab-sidebar {
    flex-basis: auto;
    width: 100%;
    position: static;
    max-height: none;
    border-right: none;
    border-bottom: 1px solid #2d3548;
    border-radius: 8px 8px 0 0;
  }
  .ab-sidebar__nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .ab-sidebar__group { margin-bottom: 0; }
}
</style>
