<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=conflict_sitemap)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-conflict-sitemap">XML sitemap</label>
      <select v-model="s.conflict_sitemap" data-ab-field="conflict_sitemap"
              class="ab-select" id="ab-gen-conflict-sitemap">
        <option value="inherit">Inherit — use the global conflict mode</option>
        <option value="takeover">AI Boost handles it — serve ours</option>
        <option value="defer">Defer — let the existing sitemap extension own it</option>
      </select>
      <div class="ab-help">Per-feature override for the XML sitemap when another sitemap extension (OSMap, Xmap, etc.) is active.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedConflictSitemap',
  props: { s: { type: Object, required: true } },
}
</script>
