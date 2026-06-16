<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=conflict_analytics)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-conflict-analytics">Analytics (GA4 / GTM / Pixel)</label>
      <select v-model="s.conflict_analytics" data-ab-field="conflict_analytics"
              class="ab-select" id="ab-gen-conflict-analytics">
        <option value="inherit">Inherit — use the global conflict mode</option>
        <option value="takeover">AI Boost handles it — always emit ours</option>
        <option value="defer">Defer — let the existing extension own it</option>
      </select>
      <div class="ab-help">Per-feature override for analytics snippets (Google Analytics 4, Google Tag Manager, Meta Pixel) when another extension already loads them.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictAnalytics',
  props: { s: { type: Object, required: true } },
}
</script>
