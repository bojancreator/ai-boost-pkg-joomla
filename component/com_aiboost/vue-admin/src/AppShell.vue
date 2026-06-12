<template>
  <div class="ab-spa-shell ab-spa-shell--sidebar" :data-ab-theme="scheme">
    <Sidebar />

    <div class="ab-spa-main">
      <ToastStack />

      <div v-if="loading" class="ab-spa-loader" role="status" aria-live="polite">
        <div class="ab-spinner ab-spinner--sm text-primary me-2" aria-hidden="true"></div>
        <span>Loading…</span>
      </div>

      <div v-else-if="error" class="ab-alert ab-alert--danger">
        <strong>Failed to load this section.</strong>
        <div class="small text-muted mt-1">{{ error }}</div>
        <a v-if="legacyHref" :href="legacyHref" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost mt-2">
          Open in classic view
        </a>
      </div>

      <router-view v-else v-slot="{ Component }">
        <component :is="Component" />
      </router-view>
    </div>
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './Sidebar.vue'
import ToastStack from './components/ToastStack.vue'
import { useColorScheme } from './composables/useColorScheme.js'
import { ensureLegacyGlobals, isLegacyGlobalsReady } from './composables/useLegacyGlobals.js'

export default {
  name: 'AppShell',
  components: { Sidebar, ToastStack },

  setup() {
    const { scheme } = useColorScheme()
    const route = useRoute()
    const loading = ref(false)
    const error = ref('')

    const legacyHref = computed(() => {
      const meta = route.meta || {}
      return meta.legacyUrl || ''
    })

    async function loadGlobalsForRoute(r) {
      const meta = r.meta || {}
      if (!meta.legacyUrl) {
        error.value = ''
        loading.value = false
        return
      }
      // Cache hit — the globals are already on window. Do NOT flip the
      // loading v-if: that would unmount <router-view> and silently discard
      // the routed component's local state (e.g. unsaved Settings edits when
      // the Sidebar switches sub-tabs via /settings?tab=<id>).
      if (isLegacyGlobalsReady(meta.legacyUrl)) {
        error.value = ''
        loading.value = false
        return
      }
      loading.value = true
      error.value = ''
      try {
        await ensureLegacyGlobals(meta.legacyUrl)
      } catch (e) {
        error.value = e && e.message ? e.message : String(e)
      } finally {
        loading.value = false
      }
    }

    onMounted(() => loadGlobalsForRoute(route))
    watch(() => route.fullPath, () => loadGlobalsForRoute(route))

    return { scheme, loading, error, legacyHref }
  },
}
</script>

<style>
/* Plan B — vertical grouped sidebar layout. The sidebar sits on the left
   and the routed content fills the remaining width. */
.ab-spa-shell--sidebar {
  display: flex;
  align-items: stretch;
  gap: 0;
  /* Trim the empty band between the Joomla toolbar and the SPA. */
  margin-top: .25rem;
  min-height: calc(100vh - 1.25rem);
}
.ab-spa-main {
  flex: 1;
  min-width: 0;
  padding: 0 1rem 1.5rem 1.5rem;
}

.ab-spa-loader {
  display: flex;
  align-items: center;
  padding: 2rem 1rem;
  color: var(--secondary-color, #6c757d);
}

@media (max-width: 782px) {
  .ab-spa-shell--sidebar { flex-direction: column; }
  .ab-spa-main { padding: 1rem 0.5rem; }
}
</style>
