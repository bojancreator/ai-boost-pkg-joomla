<!--
  AI Boost — auto-generated form field.
  Source : component/lib/src/Manifest/schema.php (key=specific_star_rating)
  Codegen: scripts/codegen-from-manifest.py
  DO NOT EDIT — re-run codegen to regenerate.
-->
<template>
  <div class="ab-gen-field">
    <div class="mb-3">
      <label class="ab-label" for="ab-gen-specific-star-rating">Star Rating</label>
      <select v-model="s.specific_star_rating" data-ab-field="specific_star_rating"
              class="ab-select" id="ab-gen-specific-star-rating">
        <option value="">Not specified</option>
        <option value="1">1 Star</option>
        <option value="2">2 Stars</option>
        <option value="3">3 Stars</option>
        <option value="4">4 Stars</option>
        <option value="5">5 Stars</option>
      </select>
    </div>
  </div>
</template>

<script>
export default {
  name: 'GeneratedSpecificStarRating',
  props: { s: { type: Object, required: true } },
}
</script>
