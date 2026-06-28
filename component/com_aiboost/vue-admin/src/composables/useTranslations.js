/**
 * AI Boost Vue Admin — useTranslations composable
 *
 * Singleton module-level state — all Vue components that import this module
 * share the same languages list and translations map.
 *
 * Fields that support per-language translation:
 *   org_name, org_description, org_logo,
 *   org_address_street, org_address_city,
 *   default_og_image, llmstxt_description
 */

import { ref, reactive } from 'vue'
import { makeAdminUrl } from '../api.js'

// ── Singleton state ────────────────────────────────────────────────────────

/** @type {import('vue').Ref<Array<{lang_code:string,title:string,sef:string,image:string}>>} */
export const languages = ref([])

/**
 * Installation default language code.
 * Resolved from window.aiBoostDefaultLang (injected by PHP HtmlView) so it
 * works even when the site's default language is not en-GB.
 * Falls back to 'en-GB' when the global is absent (e.g. during unit tests).
 * @type {import('vue').Ref<string>}
 */
export const defaultLang = ref(
  (typeof window !== 'undefined' && typeof window.aiBoostDefaultLang === 'string')
    ? window.aiBoostDefaultLang
    : 'en-GB'
)

/**
 * Whether multilingual output is actually active (Multilingual bridge plugin
 * published + master switch on + 2+ published languages). Resolved from the
 * settings.getLanguages endpoint. When false, the per-field Translation UI is
 * hidden and replaced by a "Turn on Multilingual" hint — stored translations
 * are never touched. Defaults true so the dropdowns are not hidden before the
 * flag loads (and on contexts that don't report it).
 * @type {import('vue').Ref<boolean>}
 */
export const multilangActive = ref(true)

/**
 * Nested translations map: { fieldKey: { langCode: value } }
 * Reactive so TranslationExpander components stay in sync.
 * @type {Record<string, Record<string, string>>}
 */
export const translations = reactive({})

let _loaded = false

// ── Public API ─────────────────────────────────────────────────────────────

/**
 * Load published Joomla languages from the server (once per page).
 * Also hydrates `translations` from window.aiBoostTranslations if present.
 */
export async function loadTranslationData() {
  if (_loaded) return
  _loaded = true

  // Hydrate from server-injected translations (set by HtmlView)
  const injected = window.aiBoostTranslations
  if (injected && typeof injected === 'object') {
    for (const [fk, langMap] of Object.entries(injected)) {
      if (!translations[fk]) translations[fk] = {}
      Object.assign(translations[fk], langMap)
    }
  }

  // Load published languages from Joomla DB
  try {
    const res  = await fetch(makeAdminUrl('settings.getLanguages'))
    const data = await res.json()
    if (data.success && Array.isArray(data.languages)) {
      languages.value = data.languages
    }
    // Update defaultLang if the server provides a more authoritative value
    if (data.success && typeof data.default_lang === 'string' && data.default_lang !== '') {
      defaultLang.value = data.default_lang
    }
    // Whether multilingual output is active — gates the per-field Translation UI.
    if (data.success && typeof data.multilang_active === 'boolean') {
      multilangActive.value = data.multilang_active
    }
  } catch (e) {
    // Non-fatal — TranslationExpander simply won't render rows
  }

  // If HtmlView didn't inject translations, try the getSettings endpoint
  if (!injected) {
    try {
      const res  = await fetch(makeAdminUrl('settings.getSettings'))
      const data = await res.json()
      if (data.success && data.translations && typeof data.translations === 'object') {
        for (const [fk, langMap] of Object.entries(data.translations)) {
          if (!translations[fk]) translations[fk] = {}
          Object.assign(translations[fk], langMap)
        }
      }
    } catch (e) {
      // Non-fatal
    }
  }
}

/**
 * Get the translation for a specific field + language combination.
 */
export function getT(fieldKey, langCode) {
  return (translations[fieldKey] || {})[langCode] || ''
}

/**
 * Set a translation value (mutates the reactive map).
 */
export function setT(fieldKey, langCode, value) {
  if (!translations[fieldKey]) translations[fieldKey] = {}
  translations[fieldKey][langCode] = value
}

/**
 * Return the full translations map (plain object, safe to JSON.stringify).
 * Used by App.vue save() to append translations to the FormData payload.
 */
export function getAllTranslations() {
  // Return a plain (non-reactive) snapshot
  const out = {}
  for (const [fk, langMap] of Object.entries(translations)) {
    out[fk] = { ...langMap }
  }
  return out
}
