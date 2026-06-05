<template>
  <div class="ab-bot-row">
    <div class="ab-bot-info">
      <span class="ab-bot-name">{{ bot.label }}</span>
      <span class="ab-bot-company">{{ bot.company }}</span>
    </div>
    <div class="ab-radio-group ab-bot-radio-group" role="radiogroup" :aria-label="bot.label + ' policy'">
      <label class="ab-radio"><input type="radio" :name="'bot-' + bot.id" value="allow" :checked="modelValue === 'allow'" @change="$emit('update:modelValue', 'allow')" /> <span>Allow</span></label>
      <label class="ab-radio"><input type="radio" :name="'bot-' + bot.id" value="block" :checked="modelValue === 'block'" @change="$emit('update:modelValue', 'block')" /> <span>Block</span></label>
    </div>
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
}
</script>

<style scoped>
.ab-bot-row { display: flex; align-items: center; gap: 1rem; padding: .4rem .5rem; border-bottom: 1px solid var(--border-color, #dee2e6); }
.ab-bot-info { flex: 1; }
.ab-bot-name { font-weight: 500; font-size: .875rem; display: block; }
.ab-bot-company { font-size: .75rem; color: var(--secondary-color, #6c757d); }
.ab-radio-group { display: inline-flex; gap: .75rem; flex-wrap: wrap; }
.ab-radio { display: inline-flex; align-items: center; gap: .35rem; cursor: pointer; font-size: .875rem; margin: 0; }
.ab-bot-radio-group { min-width: 150px; justify-content: flex-end; }
</style>
