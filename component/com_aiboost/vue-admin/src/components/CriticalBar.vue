<template>
  <div v-if="criticals.length" class="ab-critical-bar">
    <div v-for="c in criticals" :key="c.id"
         class="ab-alert ab-alert--danger d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2"
         role="alert">
      <span>
        <span class="icon-warning me-1" aria-hidden="true"></span>
        <strong>{{ c.title }}</strong> {{ c.message }}
      </span>
      <span class="d-flex align-items-center gap-2">
        <router-link v-if="c.route" :to="c.route" class="ab-btn ab-btn--primary ab-btn--sm">{{ c.actionLabel }}</router-link>
        <button v-if="c.dismissible" type="button" class="ab-btn ab-btn--ghost ab-btn--sm" @click="dismiss(c)">Dismiss</button>
      </span>
    </div>
  </div>
</template>

<script>
/**
 * Global critical-notifications bar (item 12a "step 2").
 *
 * Renders critical notifications at the top of EVERY SPA page. Today the only
 * critical is "no settings backup yet"; the Dashboard shows that one itself, so
 * it is suppressed here on the Dashboard route to avoid a duplicate. This is the
 * extensible home for future critical events (license lapsed, write failures, …)
 * — add them to the `criticals` list.
 */
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'

const BACKUP_LS_KEY         = 'aiboost.dashboard.lastBackupAt'
const BACKUP_DISMISS_LS_KEY = 'aiboost.dashboard.backupReminderDismissedAt'
const DISMISS_DAYS          = 7

function lsGet(key) {
  try { return window.localStorage.getItem(key) || '' } catch (e) { return '' }
}

export default {
  name: 'CriticalBar',
  setup() {
    const route = useRoute()
    const boot = window.aiBoostBootstrap || {}
    const hasSettings = boot.hasSettings === true

    const lastBackupAt = ref(lsGet(BACKUP_LS_KEY))
    const dismissedAt   = ref(lsGet(BACKUP_DISMISS_LS_KEY))

    const backupDismissed = computed(() => {
      if (!dismissedAt.value) return false
      const d = new Date(dismissedAt.value).getTime()
      if (Number.isNaN(d)) return false
      return (Date.now() - d) < DISMISS_DAYS * 86400000
    })

    const onDashboard = computed(() => (route.path || '') === '/dashboard' || route.name === 'dashboard')

    const criticals = computed(() => {
      const out = []
      // "No settings backup yet" — shown on every page except the Dashboard
      // (which renders its own copy), once the site has settings to protect.
      if (hasSettings && !lastBackupAt.value && !backupDismissed.value && !onDashboard.value) {
        out.push({
          id: 'backup_never',
          title: 'No settings backup yet.',
          message: 'Download a backup so you can restore your configuration if something goes wrong.',
          actionLabel: 'Back up settings →',
          route: '/import',
          dismissible: true,
        })
      }
      return out
    })

    function dismiss(c) {
      if (c.id === 'backup_never') {
        const nowIso = new Date().toISOString()
        try { window.localStorage.setItem(BACKUP_DISMISS_LS_KEY, nowIso) } catch (e) { /* ignore */ }
        dismissedAt.value = nowIso
      }
    }

    return { criticals, dismiss }
  },
}
</script>

<style scoped>
.ab-critical-bar { margin-bottom: .25rem; }
</style>
