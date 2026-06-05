<template>
  <div class="ab-trans-wrap">

    <button type="button" class="ab-trans-toggle" @click="open = !open">
      <svg
        class="ab-trans-arrow"
        :class="{ 'ab-trans-arrow--open': open }"
        width="10" height="10" viewBox="0 0 16 16" fill="currentColor"
      >
        <path fill-rule="evenodd"
          d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
      </svg>
      <span>Translations</span>
      <span v-if="filledCount > 0" class="ab-trans-count">{{ filledCount }}</span>
    </button>

    <div v-if="open" class="ab-trans-rows">

      <div v-if="transLanguages.length === 0" class="ab-trans-empty">
        No additional languages installed in Joomla.
        <a href="?option=com_languages&view=languages" target="_blank">Manage languages →</a>
      </div>

      <template v-else>
        <div
          v-for="lang in transLanguages"
          :key="lang.lang_code"
          class="ab-trans-row"
        >
          <span class="ab-lang-flag" :title="lang.title">
            {{ lang.sef.toUpperCase() }}
          </span>
          <MediaPicker
            v-if="fieldType === 'media'"
            class="ab-trans-media"
            :compact="true"
            :placeholder="lang.title + ' image URL…'"
            :label="lang.title"
            :model-value="getT(fieldKey, lang.lang_code)"
            @update:model-value="setT(fieldKey, lang.lang_code, $event)"
          />
          <component
            v-else
            :is="fieldType === 'textarea' ? 'textarea' : 'input'"
            v-bind="fieldType === 'textarea' ? {} : { type: fieldType === 'url' ? 'url' : 'text' }"
            class="ab-input form-control-sm"
            :placeholder="lang.title + ' translation…'"
            :rows="fieldType === 'textarea' ? 2 : undefined"
            :value="getT(fieldKey, lang.lang_code)"
            @input="setT(fieldKey, lang.lang_code, $event.target.value)"
          />
        </div>
      </template>

    </div>
  </div>
</template>

<script>
import { computed, ref } from 'vue'
import { languages, defaultLang, getT, setT } from '../composables/useTranslations.js'
import MediaPicker from './MediaPicker.vue'

export default {
  name: 'TranslationExpander',
  components: { MediaPicker },

  props: {
    fieldKey:  { type: String,  required: true },
    fieldType: { type: String,  default: 'text' },
  },

  setup(props) {
    const open  = ref(false)
    // Exclude the installation default language — that's the source value
    // edited directly in the main field. defaultLang is resolved from
    // window.aiBoostDefaultLang (injected by PHP) so it works on any locale.
    const transLanguages = computed(() =>
      languages.value.filter(l => l.lang_code !== defaultLang.value)
    )

    const filledCount = computed(() =>
      transLanguages.value.filter(l => getT(props.fieldKey, l.lang_code) !== '').length
    )

    return { open, transLanguages, filledCount, getT, setT }
  },
}
</script>

<style scoped>
.ab-trans-wrap {
  margin-top: 6px;
}

.ab-trans-toggle {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  border: 1px solid var(--ab-border-strong);
  border-radius: 5px;
  background: var(--ab-bg-muted);
  color: var(--ab-text);
  font-size: .78rem;
  cursor: pointer;
  transition: background .15s, border-color .15s;
  user-select: none;
}
.ab-trans-toggle:hover {
  background: var(--ab-bg-elev-2);
  border-color: var(--ab-border-strong);
}

.ab-trans-arrow {
  transition: transform .2s;
  flex-shrink: 0;
}
.ab-trans-arrow--open {
  transform: rotate(-180deg);
}

.ab-trans-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 17px;
  height: 17px;
  padding: 0 4px;
  border-radius: 9px;
  background: var(--ab-success);
  color: #fff;
  font-size: .7rem;
  font-weight: 600;
  line-height: 1;
}

.ab-trans-rows {
  margin-top: 6px;
  padding: 10px 12px;
  border: 1px solid var(--ab-border);
  border-radius: 6px;
  background: var(--ab-bg-elev);
}

.ab-trans-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.ab-trans-row:last-child {
  margin-bottom: 0;
}

.ab-lang-flag {
  flex-shrink: 0;
  min-width: 36px;
  text-align: center;
  padding: 2px 6px;
  border-radius: 4px;
  background: var(--ab-bg-muted);
  color: var(--ab-text);
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .04em;
}

.ab-trans-row--locked input,
.ab-trans-row--locked textarea {
  opacity: .55;
  cursor: not-allowed;
  background: var(--ab-bg-muted);
}

.ab-trans-pro-notice {
  font-size: .82rem;
  color: var(--ab-text-muted);
  padding: 6px 0 2px;
}
.ab-trans-pro-notice a {
  color: var(--ab-primary);
  text-decoration: none;
  font-weight: 500;
}
.ab-trans-pro-notice a:hover { text-decoration: underline; }

.ab-trans-empty {
  font-size: .82rem;
  color: var(--ab-text-muted);
  padding: 4px 0;
}
.ab-trans-empty a {
  color: var(--ab-primary);
  text-decoration: none;
}
</style>
