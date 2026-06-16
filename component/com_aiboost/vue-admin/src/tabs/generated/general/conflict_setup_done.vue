<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=conflict_setup_done)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.conflict_setup_done" data-ab-field="conflict_setup_done"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-conflict-setup-done" />
      <label class="ab-check__label" for="ab-gen-conflict-setup-done">Conflict setup completed</label>
      <div class="ab-help">Internal flag set once the first-run Conflict Manager wizard has been answered. Not shown as a control.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictSetupDone',
  props: { s: { type: Object, required: true } },
}
</script>
