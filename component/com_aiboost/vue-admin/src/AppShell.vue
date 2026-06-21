<template>
  <div class="ab-spa-shell ab-spa-shell--sidebar" :data-ab-theme="scheme">
    <Sidebar />

    <div class="ab-spa-main">
      <ToastStack />
      <CriticalBar />

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

    <ConflictWizard v-if="showWizard" :conflicts="wizardConflicts" @close="onWizardClose" />
  </div>
</template>

<script>
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Sidebar from './Sidebar.vue'
import ToastStack from './components/ToastStack.vue'
import ConflictWizard from './ConflictWizard.vue'
import CriticalBar from './components/CriticalBar.vue'
import { useColorScheme } from './composables/useColorScheme.js'
import { ensureLegacyGlobals, isLegacyGlobalsReady } from './composables/useLegacyGlobals.js'

export default {
  name: 'AppShell',
  components: { Sidebar, ToastStack, ConflictWizard, CriticalBar },

  setup() {
    const { scheme } = useColorScheme()
    const route = useRoute()
    const router = useRouter()
    const loading = ref(false)
    const error = ref('')

    // First-run Conflict Manager wizard — auto-open once when conflicts were
    // detected and the user hasn't answered yet. Both flags come from the cheap
    // bootstrap scan, so this needs no round-trip on first paint.
    const boot = (window.aiBoostBootstrap) || {}
    const conflictsBoot = boot.conflicts || {}
    const wizardConflicts = Array.isArray(conflictsBoot.detected) ? conflictsBoot.detected : []
    const setupDone = boot.conflictSetupDone === true || conflictsBoot.setupDone === true
    const showWizard = ref(!setupDone && wizardConflicts.length > 0)

    function onWizardClose(payload) {
      showWizard.value = false
      if (payload && payload.goTo) {
        router.push(payload.goTo)
      }
    }

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

    onMounted(() => {
      loadGlobalsForRoute(route)
      // Strip ALL ancestor padding/margin between #ab-app and <body> so the
      // SPA fills the Joomla content area edge-to-edge regardless of version.
      const root = document.getElementById('ab-app')
      if (root) {
        let el = root.parentElement
        while (el && el !== document.body) {
          el.style.setProperty('padding', '0', 'important')
          el.style.setProperty('margin', '0', 'important')
          el.style.setProperty('max-width', 'none', 'important')
          el.style.setProperty('box-shadow', 'none', 'important')
          el.style.setProperty('border-radius', '0', 'important')
          el.style.setProperty('background', 'transparent', 'important')
          el = el.parentElement
        }
        // Hide Joomla subhead/breadcrumb bar that sits above the content area
        const subhead = document.querySelector(
          '.subhead-main, .subhead, #toolbar-box, .header-subbar, .sticky-top:not(nav)'
        )
        if (subhead && !root.contains(subhead)) {
          subhead.style.setProperty('display', 'none', 'important')
        }
      }
    })
    watch(() => route.fullPath, () => loadGlobalsForRoute(route))

    return { scheme, loading, error, legacyHref, showWizard, wizardConflicts, onWizardClose }
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
  padding: 0 0.75rem 1rem 1rem;
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
