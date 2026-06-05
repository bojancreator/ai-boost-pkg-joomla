<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/sitemap.php (key=default_changefreq)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-default-changefreq">Default changefreq</label>
      <select v-model="s.default_changefreq" data-ab-field="default_changefreq"
              class="ab-select" id="ab-gen-default-changefreq">
        <option value="always">Always</option>
        <option value="hourly">Hourly</option>
        <option value="daily">Daily</option>
        <option value="weekly">Weekly</option>
        <option value="monthly">Monthly</option>
        <option value="yearly">Yearly</option>
        <option value="never">Never</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedDefaultChangefreq',
  props: { s: { type: Object, required: true } },
}
</script>
