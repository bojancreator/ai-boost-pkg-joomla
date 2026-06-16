<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=conflict_og)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-conflict-og">Open Graph / social meta</label>
      <select v-model="s.conflict_og" data-ab-field="conflict_og"
              class="ab-select" id="ab-gen-conflict-og">
        <option value="inherit">Inherit — use the global conflict mode</option>
        <option value="takeover">AI Boost handles it — always emit ours</option>
        <option value="defer">Defer — let the existing extension own it</option>
      </select>
      <div class="ab-help">Per-feature override for Open Graph and Twitter Card meta tags when another extension already sets them.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictOg',
  props: { s: { type: Object, required: true } },
}
</script>
