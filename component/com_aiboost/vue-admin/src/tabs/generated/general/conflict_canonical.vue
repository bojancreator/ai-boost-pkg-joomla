<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=conflict_canonical)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-conflict-canonical">Canonical link</label>
      <select v-model="s.conflict_canonical" data-ab-field="conflict_canonical"
              class="ab-select" id="ab-gen-conflict-canonical">
        <option value="inherit">Inherit — use the global conflict mode</option>
        <option value="takeover">AI Boost handles it — always set ours</option>
        <option value="defer">Defer — let the existing extension own it</option>
      </select>
      <div class="ab-help">Per-feature override for the canonical link when another SEO extension already sets one.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictCanonical',
  props: { s: { type: Object, required: true } },
}
</script>
