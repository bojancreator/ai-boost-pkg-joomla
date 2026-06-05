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
              <span class="ab-sidebar__icon" :class="item.icon" aria-hidden="true"></span>
              <span class="ab-sidebar__label">{{ item.label }}</span>
              <span
                v-if="item.badge === 'errors' && errorsBadge > 0"
                class="ab-sidebar__badge"
                :title="errorsBadge + ' error(s) in last 24h'"
              >{{ errorsBadge }}</span>
            </router-link>
          </li>
        </ul>
      </div>
    </nav>
  </aside>
</template>

<script>
import { useRoute } from 'vue-router'
import { createSidebarGroups } from './navigation.js'

export default {
  name: 'Sidebar',

  setup() {
    const route = useRoute()
    const boot = window.aiBoostBootstrap || {}
    const labels = boot.labels || {}

    const groups = createSidebarGroups(labels)

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

    return { groups, isItemActive, errorsBadge, version }
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
.ab-sidebar__label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
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
