<template>
  <slot />
</template>

<script>
export default {
  name: 'ProGate',
  props: {
    gateKey: { type: String, required: true },
    mode: { type: String, default: 'field' },
    forceUnlock: { type: Boolean, default: false },
  },
}
</script>
