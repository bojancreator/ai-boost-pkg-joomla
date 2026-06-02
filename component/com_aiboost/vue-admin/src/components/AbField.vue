<template>
  <div class="ab-field" :class="{ 'ab-field--locked': field.locked }">
    <label v-if="field.label" :for="domId" class="form-label d-flex align-items-center gap-2">
      <span>{{ field.label }}</span>
      <span v-if="lockBadge" :class="['badge', 'rounded-pill', badgeClass]">
        {{ lockBadge }}
      </span>
    </label>

    <!-- Locked placeholder: clicking opens upsell modal -->
    <div v-if="field.locked"
         class="ab-field__locked-shell"
         role="button"
         tabindex="0"
         @click="openUpsell"
         @keyup.enter="openUpsell">
      <component :is="placeholderTag"
                 class="form-control ab-field__locked-input"
                 disabled
                 :placeholder="placeholderText">{{ placeholderText }}</component>
      <span class="ab-field__lock-icon" aria-hidden="true">🔒</span>
    </div>

    <!-- Active field -->
    <template v-else>
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

    <!-- Upsell modal -->
    <teleport to="body" v-if="modalOpen">
      <div class="ab-upsell-backdrop" @click.self="modalOpen = false">
        <div class="ab-upsell-modal" role="dialog" aria-modal="true">
          <button type="button" class="btn-close ab-upsell-close" @click="modalOpen = false" aria-label="Close"></button>
          <h3>{{ modalTitle }}</h3>
          <p class="mb-3">{{ modalBody }}</p>
          <div class="d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary" @click="modalOpen = false">Not now</button>
            <a class="btn btn-primary" :href="modalCtaUrl" target="_blank" rel="noopener">{{ modalCtaLabel }}</a>
          </div>
        </div>
      </div>
    </teleport>
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
  data() {
    return { modalOpen: false }
  },
  computed: {
    domId() { return 'ab-f-' + this.field.key },
    placeholderTag() { return this.field.type === 'textarea' ? 'textarea' : 'input' },
    placeholderText() {
      if (this.field.lock_reason === 'pro') return 'Unlock with AI Boost Pro'
      if (this.field.lock_reason?.startsWith('integration:')) {
        const name = this.field.lock_reason.split(':')[1]
        return `Requires AI Boost ${this.title(name)} Integration`
      }
      return 'Locked'
    },
    lockBadge() {
      if (!this.field.locked) return ''
      return this.field.lock_reason === 'pro' ? 'Pro' : 'Add-on'
    },
    badgeClass() {
      return this.field.lock_reason === 'pro' ? 'bg-warning text-dark' : 'bg-info text-dark'
    },
    modalTitle() {
      if (this.field.lock_reason === 'pro') return 'Unlock with AI Boost Pro'
      if (this.field.lock_reason?.startsWith('integration:')) {
        return `Install AI Boost ${this.title(this.field.lock_reason.split(':')[1])} Integration`
      }
      return 'Feature locked'
    },
    modalBody() {
      if (this.field.lock_reason === 'pro') {
        return 'This feature is part of the AI Boost Pro upgrade. Install the Pro plugin to enable it.'
      }
      if (this.field.lock_reason?.startsWith('integration:')) {
        const name = this.field.lock_reason.split(':')[1]
        return `Install the AI Boost ${this.title(name)} Integration plugin to enable bridging with ${this.title(name)}.`
      }
      return ''
    },
    modalCtaLabel() {
      return this.field.lock_reason === 'pro' ? 'View Pro pricing' : 'View integration'
    },
    modalCtaUrl() {
      if (this.field.lock_reason === 'pro') return 'https://aiboostnow.com/pricing'
      if (this.field.lock_reason?.startsWith('integration:')) {
        const name = this.field.lock_reason.split(':')[1]
        return `https://aiboostnow.com/integrations/${name}`
      }
      return 'https://aiboostnow.com/pricing'
    },
  },
  methods: {
    openUpsell() { this.modalOpen = true },
    title(s) { return (s || '').charAt(0).toUpperCase() + (s || '').slice(1) },
  },
}
</script>

<style scoped>
.ab-field { margin-bottom: 1rem; }
.ab-field--locked .form-label { color: #6c757d; }
.ab-field__locked-shell {
  position: relative;
  cursor: pointer;
  opacity: 0.7;
  transition: opacity .15s ease;
}
.ab-field__locked-shell:hover { opacity: 0.9; }
.ab-field__locked-input {
  background: #f4f4f6 !important;
  border-style: dashed !important;
  color: #6c757d !important;
  pointer-events: none;
}
.ab-field__lock-icon {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  font-size: 1.1rem; opacity: 0.7;
}
.ab-upsell-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,.45);
  display: flex; align-items: center; justify-content: center;
  z-index: 11000;
}
.ab-upsell-modal {
  background: #fff; border-radius: 12px; padding: 24px 24px 20px;
  max-width: 460px; width: 92%; position: relative;
  box-shadow: 0 20px 60px rgba(0,0,0,.25);
}
.ab-upsell-close { position: absolute; top: 12px; right: 12px; }
.ab-upsell-modal h3 { margin: 0 0 12px; font-size: 1.25rem; }
</style>
