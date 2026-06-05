<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=enable_robots)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="ab-check ab-toggle mb-3">
      <input v-model="s.enable_robots" data-ab-field="enable_robots"
             true-value="1" false-value="0"
             type="checkbox" class="ab-toggle__input" id="ab-gen-enable-robots" />
      <label class="ab-check__label" for="ab-gen-enable-robots">Enable robots.txt management</label>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedEnableRobots',
  props: { s: { type: Object, required: true } },
}
</script>
