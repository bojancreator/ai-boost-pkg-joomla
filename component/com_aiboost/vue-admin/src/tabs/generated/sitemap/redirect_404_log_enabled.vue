<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/sitemap.php (key=redirect_404_log_enabled)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.redirect_404_log_enabled" data-ab-field="redirect_404_log_enabled"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-redirect-404-log-enabled" />
      <label class="ab-check__label" for="ab-gen-redirect-404-log-enabled">Log 404 Errors</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedRedirect404LogEnabled',
  props: { s: { type: Object, required: true } },
}
</script>
