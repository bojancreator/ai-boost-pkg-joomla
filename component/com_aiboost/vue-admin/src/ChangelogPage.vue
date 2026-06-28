<template>
  <div class="ab-changelog-page">
    <PageHeader title="What's New" subtitle="Release history for AI Boost for Joomla." />

    <div v-for="rel in releases" :key="rel.version" class="ab-section">
      <div class="ab-section__head" style="justify-content:space-between">
        <span>Version {{ rel.version }}</span>
        <span class="ab-badge" :class="rel.date ? '' : 'ab-badge--success'">{{ rel.date || 'Latest' }}</span>
      </div>
      <div class="ab-section__body">
        <ul v-if="rel.highlights && rel.highlights.length" class="ab-changelog-highlights">
          <li v-for="(h, i) in rel.highlights" :key="i"><strong>{{ h }}</strong></li>
        </ul>

        <template v-for="grp in groups" :key="grp.key">
          <div v-if="rel[grp.key] && rel[grp.key].length">
            <div class="ab-eyebrow ab-changelog-group">{{ grp.label }}</div>
            <ul class="ab-changelog-list">
              <li v-for="(item, i) in rel[grp.key]" :key="i">
                <AbIcon :name="grp.icon" />
                <span>{{ item }}</span>
              </li>
            </ul>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script>
import PageHeader from './components/PageHeader.vue'
import AbIcon from './components/AbIcon.vue'
import { CHANGELOG } from './changelog.js'

export default {
  name: 'ChangelogPage',

  components: { PageHeader, AbIcon },

  data() {
    return {
      releases: CHANGELOG,
      groups: [
        { key: 'added',    label: 'Added',    icon: 'check' },
        { key: 'improved', label: 'Improved', icon: 'bolt' },
        { key: 'fixed',    label: 'Fixed',    icon: 'ok' },
      ],
    }
  },

  mounted() {
    // Opening "What's New" clears the post-update highlight on the Dashboard.
    try {
      const tn = (window.aiBoostBootstrap && window.aiBoostBootstrap.tokenName) || window.aiBoostToken || ''
      const body = new URLSearchParams()
      if (tn) body.append(tn, '1')
      fetch('index.php?option=com_aiboost&task=dashboard.markVersionSeen&format=json', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: body.toString(),
        credentials: 'same-origin',
      }).catch(() => { /* non-critical */ })
    } catch { /* non-critical */ }
  },
}
</script>

<style scoped>
.ab-changelog-page { max-width: 880px; }
.ab-changelog-page > .ab-section { margin-bottom: 1rem; }
.ab-changelog-highlights { margin: 0 0 1rem; padding-left: 1.1rem; }
.ab-changelog-highlights li { margin-bottom: .25rem; }
.ab-changelog-group { margin: .9rem 0 .4rem; }
.ab-changelog-list { list-style: none; margin: 0; padding: 0; display: grid; gap: .4rem; }
.ab-changelog-list li { display: grid; grid-template-columns: auto 1fr; gap: .5rem; align-items: start; }
.ab-changelog-list .ab-icon { margin-top: .15rem; color: var(--ab-primary); }
</style>
