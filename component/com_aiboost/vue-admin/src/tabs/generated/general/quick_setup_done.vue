<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=quick_setup_done)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.quick_setup_done" data-ab-field="quick_setup_done"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-quick-setup-done" />
      <label class="ab-check__label" for="ab-gen-quick-setup-done">Quick Setup completed</label>
      <div class="ab-help">Internal flag set once the Quick Setup wizard has been finished (controls whether Quick Setup opens the summary view instead of step 1). Not shown as a control.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedQuickSetupDone',
  props: { s: { type: Object, required: true } },
}
</script>
