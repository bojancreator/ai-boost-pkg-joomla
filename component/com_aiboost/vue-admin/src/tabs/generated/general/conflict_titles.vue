<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=conflict_titles)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-conflict-titles">Page titles &amp; meta description</label>
      <select v-model="s.conflict_titles" data-ab-field="conflict_titles"
              class="ab-select" id="ab-gen-conflict-titles">
        <option value="inherit">Inherit — use the global conflict mode</option>
        <option value="takeover">AI Boost handles it — apply our templates</option>
        <option value="defer">Defer — let the existing extension own them</option>
      </select>
      <div class="ab-help">Per-feature override for page-title and meta-description templates when another SEO extension already rewrites them.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictTitles',
  props: { s: { type: Object, required: true } },
}
</script>
