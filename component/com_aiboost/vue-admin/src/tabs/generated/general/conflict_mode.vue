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
        <option value="cooperative">Cooperative — remove our duplicate when another tool already emits it (recommended)</option>
        <option value="aggressive">Aggressive — always emit ours (duplicates allowed); still warn in Health</option>
        <option value="off">Off — always emit ours and silence the conflict warnings in Health</option>
      </select>
      <div class="ab-help">How AI Boost behaves when another extension (4SEO, a template, etc.) already emits the same tag. Checked at final render, so it also catches tags other tools inject late. AI Boost only ever removes ITS OWN tag — never another extension's.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictMode',
  props: { s: { type: Object, required: true } },
}
</script>
