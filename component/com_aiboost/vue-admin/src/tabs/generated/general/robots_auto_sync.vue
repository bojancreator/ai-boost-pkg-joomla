<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=robots_auto_sync)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.robots_auto_sync" data-ab-field="robots_auto_sync"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-robots-auto-sync" />
      <label class="ab-check__label" for="ab-gen-robots-auto-sync">Auto-sync physical robots.txt file</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedRobotsAutoSync',
  props: { s: { type: Object, required: true } },
}
</script>
