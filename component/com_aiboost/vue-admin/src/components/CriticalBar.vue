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
 * Reserved for genuinely blocking criticals (license lapsed, write failures, …)
 * that must interrupt the user on ANY page. Add them to the `criticals` list.
 *
 * The "no settings backup yet" reminder used to render here on every page. Per
 * the Instrument design it now lives on the Dashboard only (the notifications
 * hub), as page content shown *below* the page header — not as a bar stacked
 * above every page's header.
 */
import { computed } from 'vue'

export default {
  name: 'CriticalBar',
  setup() {
    const criticals = computed(() => [])
    function dismiss(_c) { /* placeholder for future dismissible criticals */ }
    return { criticals, dismiss }
  },
}
</script>

<style scoped>
.ab-critical-bar { margin-bottom: .25rem; }
</style>
