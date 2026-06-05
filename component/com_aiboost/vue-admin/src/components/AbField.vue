<template>
  <div class="ab-field">
    <label v-if="field.label" :for="domId" class="form-label d-flex align-items-center gap-2">
      <span>{{ field.label }}</span>
    </label>

    <template>
      <input v-if="['text','number'].includes(field.type)"
             :id="domId"
             :type="field.type"
             :value="modelValue"
             class="form-control"
             @input="$emit('update:modelValue', $event.target.value)" />

      <textarea v-else-if="field.type === 'textarea'"
                :id="domId"
                :value="modelValue"
                class="form-control"
                rows="3"
                @input="$emit('update:modelValue', $event.target.value)"></textarea>

      <select v-else-if="field.type === 'select'"
              :id="domId"
              :value="modelValue"
              class="form-select"
              @change="$emit('update:modelValue', $event.target.value)">
        <option v-for="(label, val) in field.options" :key="val" :value="val">{{ label }}</option>
      </select>

      <div v-else-if="field.type === 'toggle'" class="ab-check ab-toggle">
        <input :id="domId"
               type="checkbox"
               class="ab-toggle__input"
               :checked="modelValue === '1' || modelValue === 1 || modelValue === true"
               @change="$emit('update:modelValue', $event.target.checked ? '1' : '0')" />
      </div>

      <input v-else
             :id="domId"
             type="text"
             :value="modelValue"
             class="form-control"
             @input="$emit('update:modelValue', $event.target.value)" />
    </template>

    <p v-if="field.description" class="form-text text-muted small mb-0 mt-1">
      {{ field.description }}
    </p>

  </div>
</template>

<script>
export default {
  name: 'AbField',
  props: {
    field: { type: Object, required: true },
    modelValue: { default: '' },
  },
  emits: ['update:modelValue'],
  computed: {
    domId() { return 'ab-f-' + this.field.key },
  },
}
</script>
<style scoped>
.ab-field { margin-bottom: 1rem; }
</style>
