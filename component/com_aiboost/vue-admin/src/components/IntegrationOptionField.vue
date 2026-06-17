<template>
  <div class="ab-int-opt mb-2">
    <!-- toggle -->
    <label v-if="field.type === 'toggle'" class="ab-check ab-toggle mb-0 d-flex align-items-center gap-2">
      <input
        type="checkbox" class="ab-toggle__input"
        :data-ab-field="field.key"
        :checked="modelValue === '1'"
        @change="$emit('update:modelValue', $event.target.checked ? '1' : '0')">
      <span class="ab-check__label">{{ field.label }}</span>
    </label>

    <!-- select -->
    <template v-else-if="field.type === 'select'">
      <label class="ab-label">{{ field.label }}</label>
      <select
        class="ab-select form-select-sm" style="max-width:280px"
        :data-ab-field="field.key"
        :value="modelValue"
        @change="$emit('update:modelValue', $event.target.value)">
        <option v-for="o in field.options" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
    </template>

    <!-- text -->
    <template v-else>
      <label class="ab-label">{{ field.label }}</label>
      <input
        type="text" class="ab-input form-control-sm" style="max-width:280px"
        :data-ab-field="field.key"
        :value="modelValue"
        :placeholder="field.placeholder || ''"
        @input="$emit('update:modelValue', $event.target.value)">
    </template>

    <div v-if="field.help" class="ab-help">{{ field.help }}</div>
  </div>
</template>

<script>
export default {
  name: 'IntegrationOptionField',
  props: {
    field:      { type: Object, required: true },
    modelValue: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
}
</script>
