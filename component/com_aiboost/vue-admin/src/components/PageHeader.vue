<template>
  <div class="ab-topbar">
    <h2 class="ab-topbar__title">{{ title }}</h2>
    <span v-if="subtitle" class="ab-topbar__sub">{{ subtitle }}</span>
    <div v-if="$slots.default" class="ab-topbar__actions">
      <slot />
    </div>
  </div>
</template>

<script>
/**
 * PageHeader — the shared sticky "topbar" used at the top of every SPA page.
 *
 * Matches the Instrument design's page header: a bar with a bold page title on
 * the left, an optional subtitle, and a right-aligned actions slot (Save, Copy
 * report, Run wizard, …). Page notifications/content render BELOW this bar.
 *
 * Styling lives in the shared admin stylesheet (.ab-topbar / .ab-topbar__title /
 * .ab-topbar__sub / .ab-topbar__actions in ab-components.css) so this header
 * looks identical to the hand-rolled topbars it replaced — single source of truth.
 *
 *   <PageHeader title="Redirects" />
 *   <PageHeader title="Health"><button class="ab-btn ...">Copy report</button></PageHeader>
 *   <PageHeader title="Dashboard" subtitle="Settings last saved 19 Jun 2026" />
 */
export default {
  name: 'PageHeader',
  props: {
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
  },
}
</script>
