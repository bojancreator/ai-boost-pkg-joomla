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
        <div v-if="group.items.length" class="ab-sidebar__group" :class="{ 'is-open': isGroupOpen(group) }">
          <button
            type="button"
            class="ab-sidebar__group-header"
            :aria-expanded="isGroupOpen(group) ? 'true' : 'false'"
            @click="toggleGroup(group.title)"
          >
            <span class="ab-sidebar__group-title">{{ group.title }}</span>
            <svg class="ab-sidebar__caret" viewBox="0 0 16 16" width="13" height="13"
                 fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
              <path d="M4 6l4 4 4-4" />
            </svg>
          </button>
          <ul v-show="isGroupOpen(group)" class="ab-sidebar__list">
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
      openGroup: null,
    }
  },

  created() {
    // Single-open accordion: start with the active group expanded.
    this.openGroup = this.activeGroupTitle
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
    activeGroupTitle() {
      const g = this.groups.find(group => group.items.some(it => this.isItemActive(it)))
      return g ? g.title : null
    },
  },

  watch: {
    // Follow the route: open the active group (and close the others).
    activeGroupTitle(title) {
      if (title) this.openGroup = title
    },
  },

  methods: {
    isGroupOpen(group) {
      // While filtering, reveal every group that still has matches.
      if (this.search.trim()) return true
      return this.openGroup === group.title
    },
    toggleGroup(title) {
      this.openGroup = this.openGroup === title ? null : title
    },
    toggleCompact() {
      this.compact = !this.compact
      localStorage.setItem(DENSITY_KEY, this.compact ? 'compact' : 'comfortable')
    },
  },
}
</script>

<style>
.ab-sidebar {
  flex: 0 0 286px;
  width: 286px;
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
  font-size: 1.0625rem;
  font-weight: 700;
  color: var(--ab-sidebar-text);
  letter-spacing: -.01em;
}
.ab-sidebar__version {
  margin-left: auto;
  font-size: .68rem;
  font-weight: 700;
  color: #fff;
  background: var(--ab-primary);
  border-radius: var(--ab-radius);
  padding: 2px 6px;
}
.ab-sidebar__density-btn {
  flex-shrink: 0;
  background: transparent;
  border: none;
  color: var(--ab-sidebar-label);
  padding: 2px 4px;
  cursor: pointer;
  border-radius: var(--ab-radius);
  line-height: 1;
  font-size: 12px;
  opacity: .8;
  transition: opacity .12s, background .12s, color .12s;
}
.ab-sidebar__density-btn:hover { opacity: 1; background: var(--ab-sidebar-hover-bg); color: var(--ab-sidebar-text); }

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
  font-size: 15px;
  color: var(--ab-sidebar-label);
  pointer-events: none;
}
.ab-sidebar__search-input {
  width: 100%;
  background: var(--ab-sidebar-hover-bg);
  border: 1px solid var(--ab-sidebar-border);
  border-radius: var(--ab-radius);
  color: var(--ab-sidebar-text);
  font-size: var(--ab-font-size-xs);
  padding: 6px 24px 6px 28px;
  outline: none;
  transition: border-color .12s, background .12s;
}
.ab-sidebar__search-input::placeholder { color: var(--ab-sidebar-label); }
.ab-sidebar__search-input:focus { border-color: var(--ab-primary); }
.ab-sidebar__search-input::-webkit-search-cancel-button { display: none; }
.ab-sidebar__search-clear {
  position: absolute;
  right: 16px;
  top: 50%;
  transform: translateY(-50%);
  background: transparent;
  border: none;
  color: var(--ab-sidebar-label);
  font-size: 15px;
  line-height: 1;
  cursor: pointer;
  padding: 0 2px;
}
.ab-sidebar__search-clear:hover { color: var(--ab-sidebar-text); }
.ab-sidebar__no-results {
  padding: 12px 16px;
  font-size: var(--ab-font-size-xs);
  color: var(--ab-sidebar-label);
  font-style: italic;
}

/* ── Nav (accordion) ────────────────────────────────────────── */
.ab-sidebar__nav {
  flex: 1;
  overflow-y: auto;
  padding: 8px 0 16px;
}
.ab-sidebar__group { margin-bottom: 4px; }
.ab-sidebar__group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  background: none;
  border: none;
  cursor: pointer;
  padding: 10px 16px 8px;
  color: var(--ab-sidebar-text);
  text-align: left;
}
.ab-sidebar__group-title {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: .01em;
}
.ab-sidebar__caret {
  flex: none;
  color: var(--ab-sidebar-label);
  transition: transform .15s ease;
}
.ab-sidebar__group.is-open .ab-sidebar__caret { transform: rotate(180deg); }
.ab-sidebar__group-header:hover .ab-sidebar__group-title { color: var(--ab-sidebar-active-text); }

.ab-sidebar__list {
  list-style: none;
  margin: 0;
  padding: 0 0 6px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.ab-sidebar__item {
  display: flex;
  align-items: center;
  gap: 11px;
  width: 100%;
  border-radius: 0;
  border-left: 2px solid transparent;
  padding: 9px 16px;
  font-size: 1.025rem;
  font-weight: 500;
  color: var(--ab-sidebar-text);
  text-decoration: none;
  transition: background .12s, color .12s, border-color .12s;
}
.ab-sidebar__item:hover {
  background: var(--ab-sidebar-hover-bg);
  color: var(--ab-sidebar-active-text);
  text-decoration: none;
}
.ab-sidebar__item.active {
  background: var(--ab-sidebar-active-bg);
  color: var(--ab-sidebar-active-text);
  border-left-color: var(--ab-primary);
  font-weight: 600;
}
.ab-sidebar__icon {
  font-size: 18px;
  opacity: .9;
  flex-shrink: 0;
}
.ab-sidebar__item.active .ab-sidebar__icon { opacity: 1; }
.ab-sidebar__label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
/* notification count — light: stroke (red border + red number); dark: solid red + white */
.ab-sidebar__badge {
  font-size: .7rem;
  font-weight: 700;
  color: var(--ab-danger);
  background: transparent;
  border: 1px solid var(--ab-danger);
  border-radius: 999px;
  padding: 0 7px;
  line-height: 1.5;
}
[data-bs-theme="dark"] .ab-sidebar__badge { color: #fff; background: var(--ab-danger); }

/* ── Compact density ────────────────────────────────────────── */
.ab-sidebar--compact .ab-sidebar__item { padding: 6px 12px; font-size: .94rem; gap: 9px; }
.ab-sidebar--compact .ab-sidebar__group { margin-bottom: 2px; }
.ab-sidebar--compact .ab-sidebar__group-header { padding: 8px 12px 5px; }
.ab-sidebar--compact .ab-sidebar__group-title { font-size: .9rem; }
.ab-sidebar--compact .ab-sidebar__icon { font-size: 15px; }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 782px) {
  .ab-sidebar {
    flex-basis: auto;
    width: 100%;
    position: static;
    max-height: none;
    border-right: none;
    border-bottom: 1px solid var(--ab-sidebar-border);
    border-radius: 0;
  }
  .ab-sidebar__search { display: none; }
}
</style>
