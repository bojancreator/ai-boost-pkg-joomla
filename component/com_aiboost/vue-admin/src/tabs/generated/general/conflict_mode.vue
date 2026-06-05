<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=conflict_mode)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-conflict-mode">Conflict Resolution Mode</label>
      <select v-model="s.conflict_mode" data-ab-field="conflict_mode"
              class="ab-select" id="ab-gen-conflict-mode">
        <option value="cooperative">Cooperative â€” skip our tag when one exists (recommended)</option>
        <option value="aggressive">Aggressive â€” always emit our tag (may produce duplicates)</option>
        <option value="off">Off â€” disable conflict handling entirely</option>
      </select>
      <div class="ab-help">How AI Boost behaves when another extension is already emitting the same meta tag, JSON-LD block, or analytics snippet.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictMode',
  props: { s: { type: Object, required: true } },
}
</script>
