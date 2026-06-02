<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/general.php (key=translation_source_priority)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-translation-source-priority">Translation source priority</label>
      <select v-model="s.translation_source_priority" data-ab-field="translation_source_priority"
              class="ab-select" id="ab-gen-translation-source-priority">
        <option value="joomla_native">Joomla native (recommended if both exist)</option>
        <option value="falang">Falang</option>
        <option value="joomfish">JoomFish</option>
        <option value="auto">Auto-detect best</option>
      </select>
      <div class="ab-help">When a site mixes Joomla native multilingual with Falang or JoomFish, AI Boost uses this source as primary.</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedTranslationSourcePriority',
  props: { s: { type: Object, required: true } },
}
</script>
