<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=conflict_schema)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-conflict-schema">Schema (JSON-LD)</label>
      <select v-model="s.conflict_schema" data-ab-field="conflict_schema"
              class="ab-select" id="ab-gen-conflict-schema">
        <option value="inherit">Inherit — use the global conflict mode</option>
        <option value="takeover">AI Boost handles it — always emit ours</option>
        <option value="defer">Defer — let the existing extension own it</option>
      </select>
      <div class="ab-help">Per-feature override for Schema.org JSON-LD when another extension or the template already emits structured data.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictSchema',
  props: { s: { type: Object, required: true } },
}
</script>
