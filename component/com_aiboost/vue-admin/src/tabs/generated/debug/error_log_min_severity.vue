<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/debug.php (key=error_log_min_severity)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-error-log-min-severity">Minimum severity to log</label>
      <select v-model="s.error_log_min_severity" data-ab-field="error_log_min_severity"
              class="ab-select" id="ab-gen-error-log-min-severity">
        <option value="debug">Debug (very verbose â€” troubleshooting only)</option>
        <option value="info">Info</option>
        <option value="warning">Warning (recommended)</option>
        <option value="error">Error only</option>
      </select>
      <div class="ab-help">Events below this severity are dropped. Use &quot;debug&quot; temporarily when troubleshooting; &quot;warning&quot; is safe for production.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedErrorLogMinSeverity',
  props: { s: { type: Object, required: true } },
}
</script>
