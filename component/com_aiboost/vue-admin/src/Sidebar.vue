<template>
  <aside class="ab-sidebar" :class="{ 'ab-sidebar--compact': compact }">
    <div class="ab-sidebar__brand">
      <span class="ab-sidebar__brand-name">AI Boost</span>
      <span v-if="version" class="ab-sidebar__version">{{ version }}</span>
      <button
        type="button"
        class="ab-sidebar__density-btn"
        :title="compact ? 'Switch to comfortable layout' : 'Switch to compact layout'"
        :aria-label="compact ? 'Switch to comfortable layout' : 'Switch to compact layout'"
        @click="toggleCompact"
      >
        <span :class="compact ? 'icon-expand' : 'icon-compress'" aria-hidden="true"></span>
      </button>
    </div>

    <div class="ab-sidebar__search">
      <AbIcon name="search" class="ab-sidebar__search-icon" />
      <input
        v-model="search"
        type="search"
        class="ab-sidebar__search-input"
        placeholder="Filter…"
        aria-label="Filter navigation"
      />
      <button
        v-if="search"
        type="button"
        class="ab-sidebar__search-clear"
        aria-label="Clear filter"
        @click="search = ''"
      >×</button>
    </div>

    <nav class="ab-sidebar__nav" aria-label="AI Boost navigation">
      <template v-for="group in filteredGroups" :key="group.title">
        <div v-if="group.items.length" class="ab-sidebar__group">
          <div class="ab-sidebar__group-title">{{ group.title }}</div>
          <ul class="ab-sidebar__list">
            <li v-for="item in group.items" :key="item.id">
              <router-link
                class="ab-sidebar__item"
                :class="{ active: isItemActive(item) }"
                :to="item.to"
              >
                <AbIcon class="ab-sidebar__icon" :name="item.icon" />
                <span class="ab-sidebar__label">{{ item.label }}</span>
                <span
                  v-if="item.badge === 'errors' && errorsBadge > 0"
                  class="ab-sidebar__badge"
                  :title="errorsBadge + ' error(s) in last 24h'"
                >{{ errorsBadge }}</span>
                <span
                  v-if="item.badge === 'conflicts' && conflictsBadge > 0"
                  class="ab-sidebar__badge"
                  :title="conflictsBadge + ' conflict(s) detected'"
                >{{ conflictsBadge }}</span>
              </router-link>
            </li>
          </ul>
        </div>
      </template>
      <div v-if="search && !hasResults" class="ab-sidebar__no-results">
        No results for "{{ search }}"
      </div>
    </nav>
  </aside>
</template>

<script>
import { useRoute } from 'vue-router'
import { createSidebarGroups } from './navigation.js'

const DENSITY_KEY = 'aiboost_ui_density'

export default {
  name: 'Sidebar',

  setup() {
    const route = useRoute()
    const boot = window.aiBoostBootstrap || {}
    const labels = boot.labels || {}

    const groups = createSidebarGroups(labels)

    const isItemActive = (item) => {
      if (item.tab) {
        return route.name === 'settings' && (route.query.tab || 'technical') === item.tab
      }
      return route.name === item.id
    }

    const errorsBadge = (boot.errorsSummary && Number(boot.errorsSummary.errors_24h)) || 0
    const conflictsBadge = (boot.conflicts && Array.isArray(boot.conflicts.detected) && boot.conflicts.detected.length) || 0
    const version = boot.version ? ('v' + String(boot.version).replace(/^v/, '')) : ''

    return { groups, isItemActive, errorsBadge, conflictsBadge, version }
  },

  data() {
    return {
      search: '',
      compact: localStorage.getItem(DENSITY_KEY) === 'compact',
    }
  },

  computed: {
    filteredGroups() {
      const q = this.search.trim().toLowerCase()
      if (!q) return this.groups
      return this.groups.map(group => ({
        ...group,
        items: group.items.filter(item => item.label.toLowerCase().includes(q)),
      }))
    },
    hasResults() {
      return this.filteredGroups.some(g => g.items.length > 0)
    },
  },

  methods: {
    toggleCompact() {
      this.compact = !this.compact
      localStorage.setItem(DENSITY_KEY, this.compact ? 'compact' : 'comfortable')
    },
  },
}
</script>

<style>
.ab-sidebar {
  flex: 0 0 276px;
  width: 276px;
  align-self: stretch;
  display: flex;
  flex-direction: column;
  background: var(--ab-sidebar-bg);
  border-right: 1px solid var(--ab-sidebar-border);
}

.ab-sidebar__brand {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--ab-sidebar-border);
}
.ab-sidebar__brand-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--ab-sidebar-text, #fff);
  letter-spacing: -.01em;
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
.ab-sidebar__density-btn {
  flex-shrink: 0;
  background: transparent;
  border: none;
  color: var(--ab-sidebar-label);
  padding: 2px 4px;
  cursor: pointer;
  border-radius: 4px;
  line-height: 1;
  font-size: 11px;
  opacity: .7;
  transition: opacity .12s, background .12s;
}
.ab-sidebar__density-btn:hover { opacity: 1; background: rgba(255,255,255,.08); }

/* ── Search bar ─────────────────────────────────────────────── */
.ab-sidebar__search {
  position: relative;
  padding: 8px 10px;
  border-bottom: 1px solid var(--ab-sidebar-border);
}
.ab-sidebar__search-icon {
  position: absolute;
  left: 19px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 14px;
  color: var(--ab-sidebar-label);
  pointer-events: none;
}
.ab-sidebar__search-input {
  width: 100%;
  background: rgba(255,255,255,.06);
  border: 1px solid var(--ab-sidebar-border);
  border-radius: 5px;
  color: var(--ab-sidebar-text);
  font-size: 12px;
  padding: 5px 24px 5px 26px;
  outline: none;
  transition: border-color .12s, background .12s;
}
.ab-sidebar__search-input::placeholder { color: var(--ab-sidebar-label); }
.ab-sidebar__search-input:focus {
  background: rgba(255,255,255,.10);
  border-color: var(--ab-primary, #4f46e5);
}
/* Hide browser's default × in search inputs */
.ab-sidebar__search-input::-webkit-search-cancel-button { display: none; }
.ab-sidebar__search-clear {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: var(--ab-sidebar-label);
  font-size: 14px;
  line-height: 1;
  cursor: pointer;
  padding: 0 2px;
}
.ab-sidebar__search-clear:hover { color: #fff; }
.ab-sidebar__no-results {
  padding: 12px 16px;
  font-size: 12px;
  color: var(--ab-sidebar-label);
  font-style: italic;
}

/* ── Nav ────────────────────────────────────────────────────── */
.ab-sidebar__nav {
  flex: 1;
  overflow-y: auto;
  padding: 10px 0 16px;
}
.ab-sidebar__group { margin-bottom: 14px; }
.ab-sidebar__group-title {
  font-family: var(--ab-font-mono);
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 1.4px;
  text-transform: uppercase;
  color: var(--ab-sidebar-label);
  padding: 0 16px 6px;
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
  border-radius: 0;
  border-left: 2px solid transparent;
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 500;
  color: var(--ab-sidebar-text);
  text-decoration: none;
  transition: background .12s, color .12s, border-color .12s;
}
.ab-sidebar__item:hover {
  background: var(--ab-sidebar-hover-bg, rgba(255, 255, 255, .05));
  color: var(--ab-sidebar-active-text, #fff);
  text-decoration: none;
}
.ab-sidebar__item.active {
  background: var(--ab-sidebar-active-bg, rgba(79, 70, 229, .22));
  color: var(--ab-sidebar-active-text, #fff);
  border-left-color: var(--ab-primary, #4f46e5);
  font-weight: 600;
}
.ab-sidebar__icon {
  font-size: 15px;
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
  background: var(--ab-danger);
  border-radius: 10px;
  padding: 1px 7px;
  line-height: 1.4;
}

/* ── Compact density ────────────────────────────────────────── */
.ab-sidebar--compact .ab-sidebar__item { padding: 5px 10px; font-size: 12.5px; }
.ab-sidebar--compact .ab-sidebar__group { margin-bottom: 10px; }
.ab-sidebar--compact .ab-sidebar__group-title { font-size: 9px; padding-bottom: 4px; }
.ab-sidebar--compact .ab-sidebar__icon { font-size: 12px; }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 782px) {
  .ab-sidebar {
    flex-basis: auto;
    width: 100%;
    position: static;
    max-height: none;
    border-right: none;
    border-bottom: 1px solid var(--ab-sidebar-border);
    border-radius: 8px 8px 0 0;
  }
  .ab-sidebar__nav {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .ab-sidebar__group { margin-bottom: 0; }
  .ab-sidebar__search { display: none; }
}
</style>
