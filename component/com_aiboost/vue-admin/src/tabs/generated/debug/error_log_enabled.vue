<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/debug.php (key=error_log_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.error_log_enabled" data-ab-field="error_log_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-error-log-enabled" />
      <label class="ab-check__label" for="ab-gen-error-log-enabled">Enable AI Boost error log</label>
      <div class="ab-help">Write AI Boost warnings/errors to the #__aiboost_error_log table (and Joomla log) so they can be reviewed from the admin instead of being lost in PHP error_log.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedErrorLogEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
