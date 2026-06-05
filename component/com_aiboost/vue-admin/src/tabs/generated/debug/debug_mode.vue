<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/debug.php (key=debug_mode)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.debug_mode" data-ab-field="debug_mode"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-debug-mode" />
      <label class="ab-check__label" for="ab-gen-debug-mode">Enable debug mode</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedDebugMode',
  props: { s: { type: Object, required: true } },
}
</script>
