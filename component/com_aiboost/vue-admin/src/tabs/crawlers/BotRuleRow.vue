<template>
  <div class="ab-bot-row" style="cursor:pointer" @click="toggle">
    <div class="ab-bot-info">
      <span class="ab-bot-name">{{ bot.label }}</span>
      <span class="ab-bot-company">{{ bot.company }}</span>
    </div>
    <span class="ab-toggle" :class="{ 'is-on': modelValue !== 'block' }">
      <span class="ab-toggle__track"></span>
    </span>
    <span class="ab-bot-state" :class="modelValue === 'block' ? 'ab-bot-state--block' : 'ab-bot-state--allow'">
      {{ modelValue === 'block' ? 'Block' : 'Allow' }}
    </span>
  </div>
</template>

<script>
export default {
  name: 'BotRuleRow',
  props: {
    bot: { type: Object, required: true },
    modelValue: { type: String, default: '' },
  },
  emits: ['update:modelValue'],
  methods: {
    toggle() {
      this.$emit('update:modelValue', this.modelValue === 'block' ? 'allow' : 'block')
    },
  },
}
</script>

<style scoped>
.ab-bot-row { display: flex; align-items: center; gap: 1rem; padding: .4rem .5rem; border-bottom: 1px solid var(--ab-border); user-select: none; }
.ab-bot-row:hover { background: var(--ab-surface-raised); }
.ab-bot-info { flex: 1; }
.ab-bot-name { font-weight: 500; font-size: .875rem; display: block; }
.ab-bot-company { font-size: .75rem; color: var(--ab-text-muted); }
.ab-bot-state { font-size: .78rem; min-width: 2.5rem; text-align: right; font-variant-numeric: tabular-nums; }
.ab-bot-state--allow { color: var(--ab-success); }
.ab-bot-state--block { color: var(--ab-danger); }
</style>
