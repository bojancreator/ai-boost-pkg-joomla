<template>
  <span
    class="ab-toggle ab-toggle--onoff"
    :class="{ 'is-on': modelValue, 'is-locked': disabled }"
    role="switch"
    :aria-checked="modelValue ? 'true' : 'false'"
    :tabindex="disabled ? -1 : 0"
    @click="toggle"
    @keydown.enter.prevent="toggle"
    @keydown.space.prevent="toggle"
  >
    <span class="ab-toggle__track"></span>
    <span class="ab-toggle__label">{{ modelValue ? 'ON' : 'OFF' }}</span>
  </span>
</template>

<script>
/**
 * OnOffSwitch — the Instrument "ON/OFF" labelled switch.
 *
 * A bare toggle track + an ON/OFF mono-bold label, with no surrounding border or
 * chip. Styling lives in ab-components.css (.ab-toggle / .ab-toggle--onoff).
 * Used on the Dashboard module cards and the Integrations cards.
 *
 *   <OnOffSwitch v-model="enabled" @change="onToggle" :disabled="busy" />
 */
export default {
  name: 'OnOffSwitch',
  props: {
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
  },
  emits: ['update:modelValue', 'change'],
  methods: {
    toggle() {
      if (this.disabled) return
      const next = !this.modelValue
      this.$emit('update:modelValue', next)
      this.$emit('change', next)
    },
  },
}
</script>
