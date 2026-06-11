<template>
  <div class="ab-code-tab">
    <!-- Custom Code Injection — the whole tab is Pro. -->
    <ProGate mode="card" label="Custom Code">
    <div class="ab-card">
      <div class="ab-card-header">💉 Custom Code Injection <span class="ab-pro-tag">Pro</span></div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-4">
          <input v-model="s.enable_custom_code" true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="cc-enable">
          <label class="ab-check__label" for="cc-enable">Enable Custom Code Injection</label>
          <div class="ab-help mt-1">The plugin must also be enabled in Joomla Plugin Manager.</div>
        </div>

        <!-- Head Code -->
        <div class="ab-sec">Head Code</div>
        <div class="mb-3">
          <label class="ab-label">Inject before <code>&lt;/head&gt;</code></label>
          <textarea v-model="s.custom_code_head" class="ab-input font-monospace" rows="7"
            placeholder="&lt;!-- paste scripts, meta tags, stylesheets here --&gt;"></textarea>
          <div class="ab-code-meta">
            <span class="ab-char-count">{{ formatCount(s.custom_code_head) }}</span>
            <span v-if="headWarnings.length" class="ab-syntax-warn">
              ⚠ {{ headWarnings.join(' · ') }}
            </span>
          </div>
          <div class="ab-help">Raw HTML injected into every selected page's &lt;head&gt;. Accepts &lt;script&gt;, &lt;link&gt;, &lt;meta&gt;, &lt;style&gt; tags.</div>
        </div>
        <ScopeSelector
          field="head"
          data-ab-field="custom_code_head_menu_ids"
          :s="s"
          :menu-groups="menuGroups"
          v-model:selected-ids="selectedHeadIds"
        />

        <!-- Body Code -->
        <div class="ab-sec mt-4">Body Code</div>
        <div class="mb-3">
          <label class="ab-label">Inject after opening <code>&lt;body&gt;</code></label>
          <textarea v-model="s.custom_code_body" data-ab-field="custom_code_body" class="ab-input font-monospace" rows="7"
            placeholder="&lt;!-- paste chat widgets, noscript tags, etc. --&gt;"></textarea>
          <div class="ab-code-meta">
            <span class="ab-char-count">{{ formatCount(s.custom_code_body) }}</span>
            <span v-if="bodyWarnings.length" class="ab-syntax-warn">
              ⚠ {{ bodyWarnings.join(' · ') }}
            </span>
          </div>
          <div class="ab-help">Raw HTML injected immediately after the opening &lt;body&gt; tag. Useful for GTM noscript, chat widgets.</div>
        </div>
        <ScopeSelector
          field="body"
          data-ab-field="custom_code_body_menu_ids"
          :s="s"
          :menu-groups="menuGroups"
          v-model:selected-ids="selectedBodyIds"
        />

        <!-- Footer Code -->
        <div class="ab-sec mt-4">Footer Code</div>
        <div class="mb-3">
          <label class="ab-label">Inject before <code>&lt;/body&gt;</code></label>
          <textarea v-model="s.custom_code_footer" data-ab-field="custom_code_footer" class="ab-input font-monospace" rows="7"
            placeholder="&lt;!-- paste deferred scripts, chat widgets, tracking pixels here --&gt;"></textarea>
          <div class="ab-code-meta">
            <span class="ab-char-count">{{ formatCount(s.custom_code_footer) }}</span>
            <span v-if="footerWarnings.length" class="ab-syntax-warn">
              ⚠ {{ footerWarnings.join(' · ') }}
            </span>
          </div>
          <div class="ab-help">Raw HTML injected just before the closing <code>&lt;/body&gt;</code> tag. Ideal for deferred scripts, chat widgets, and tracking pixels.</div>
        </div>
        <ScopeSelector
          field="footer"
          data-ab-field="custom_code_footer_menu_ids"
          :s="s"
          :menu-groups="menuGroups"
          v-model:selected-ids="selectedFooterIds"
        />

      </div>
    </div>
    </ProGate>
  </div>
</template>

<script>
import ProGate from '../components/ProGate.vue'

const ScopeSelector = {
  name: 'ScopeSelector',
  props: {
    field: { type: String, required: true },
    s: { type: Object, required: true },
    menuGroups: { type: Array, required: true },
    selectedIds: { type: Array, required: true },
  },
  emits: ['update:selectedIds'],
  computed: {
    scopeKey()   { return `custom_code_${this.field}_scope` },
    menuIdsKey() { return `custom_code_${this.field}_menu_ids` },
    labelId()    { return `cc-${this.field}-scope` },
    count()      { return this.selectedIds.length },
  },
  methods: {
    onScopeChange(val) {
      this.s[this.scopeKey] = val
    },
    onSelectChange(e) {
      const selected = Array.from(e.target.selectedOptions).map(o => Number(o.value))
      this.$emit('update:selectedIds', selected)
      this.s[this.menuIdsKey] = JSON.stringify(selected)
    },
  },
  template: `
    <div class="ab-scope-block mb-2">
      <label class="ab-label fw-semibold text-secondary small mb-1">
        Apply {{ field }} code to
      </label>
      <div class="d-flex gap-4 mb-2">
        <div class="ab-check">
          <input class="ab-toggle__input" type="radio"
            :id="labelId + '-all'" :name="labelId"
            value="all"
            :checked="(s[scopeKey] || 'all') === 'all'"
            @change="onScopeChange('all')">
          <label class="ab-check__label" :for="labelId + '-all'"><strong>All pages</strong></label>
        </div>
        <div class="ab-check">
          <input class="ab-toggle__input" type="radio"
            :id="labelId + '-specific'" :name="labelId"
            value="specific"
            :checked="s[scopeKey] === 'specific'"
            @change="onScopeChange('specific')">
          <label class="ab-check__label" :for="labelId + '-specific'"><strong>Specific menu items only</strong></label>
        </div>
      </div>
      <div v-if="s[scopeKey] === 'specific'" class="mb-1">
        <div v-if="menuGroups.length === 0" class="text-muted small">No menu items found.</div>
        <select v-else multiple class="ab-select ab-menu-select"
          @change="onSelectChange">
          <optgroup v-for="group in menuGroups" :key="group.type" :label="group.type">
            <option v-for="item in group.items" :key="item.id" :value="item.id"
              :selected="selectedIds.includes(item.id)"
              :style="'padding-left:' + (Math.max(0, item.level - 1) * 14) + 'px'">
              {{ '\\u00a0'.repeat(Math.max(0, item.level - 1) * 2) }}{{ item.title }}
            </option>
          </optgroup>
        </select>
        <div class="ab-help mt-1">
          Hold <kbd>Ctrl</kbd> (Windows) or <kbd>⌘ Cmd</kbd> (Mac) to select multiple items.
          <strong>{{ count }}</strong> item{{ count !== 1 ? 's' : '' }} selected.
        </div>
      </div>
    </div>
  `,
}

export default {
  name: 'CodeTab',
  components: { ScopeSelector, ProGate },
  props: { s: { type: Object, required: true } },

  data() {
    const parseIds = (key) => {
      try { return JSON.parse(this.s[key] || '[]').map(Number) } catch { return [] }
    }

    // Legacy fallback: if a per-field scope/menu key is absent but the old shared
    // key exists (pre-v0.12.4 save), seed the per-field keys from the shared value
    // so the UI reflects what is actually active on the first edit after upgrade.
    const legacyScope   = this.s.custom_code_scope    || null
    const legacyMenuIds = this.s.custom_code_menu_ids || '[]'
    for (const field of ['head', 'body', 'footer']) {
      const sk = `custom_code_${field}_scope`
      const mk = `custom_code_${field}_menu_ids`
      if (!this.s[sk] && legacyScope) {
        this.s[sk] = legacyScope
      }
      if (!this.s[mk] || this.s[mk] === '[]') {
        const legacyIds = parseIds('custom_code_menu_ids')
        if (legacyIds.length > 0) {
          this.s[mk] = legacyMenuIds
        }
      }
    }

    const rawItems = window.aiBoostMenuItems || []
    const groupMap = {}
    for (const item of rawItems) {
      if (!groupMap[item.menutype]) groupMap[item.menutype] = []
      groupMap[item.menutype].push(item)
    }
    const menuGroups = Object.entries(groupMap).map(([type, items]) => ({ type, items }))

    return {
      selectedHeadIds:   parseIds('custom_code_head_menu_ids'),
      selectedBodyIds:   parseIds('custom_code_body_menu_ids'),
      selectedFooterIds: parseIds('custom_code_footer_menu_ids'),
      menuGroups,
    }
  },

  computed: {
    headWarnings()   { return this.checkSyntax(this.s.custom_code_head) },
    bodyWarnings()   { return this.checkSyntax(this.s.custom_code_body) },
    footerWarnings() { return this.checkSyntax(this.s.custom_code_footer) },
  },

  methods: {
    formatCount(val) {
      const len = (val || '').length
      if (len === 0) return '0 chars'
      return len.toLocaleString() + ' char' + (len !== 1 ? 's' : '')
    },

    checkSyntax(code) {
      if (!code || !code.trim()) return []
      const warnings = []

      const voidTags = new Set([
        'area','base','br','col','embed','hr','img','input',
        'link','meta','param','source','track','wbr',
      ])

      const stripComments = code.replace(/<!--[\s\S]*?-->/g, '')
      const stripStrings  = stripComments
        .replace(/"[^"]*"/g, '""')
        .replace(/'[^']*'/g, "''")
        .replace(/`[^`]*`/g, '``')

      const openStack = []
      const mismatchedTags = []
      const unexpectedCloseTags = []

      const tagRe = /<\/?([a-zA-Z][a-zA-Z0-9]*)[^>]*\/?>/g
      let m
      while ((m = tagRe.exec(stripStrings)) !== null) {
        const full = m[0]
        const tag  = m[1].toLowerCase()
        if (voidTags.has(tag)) continue
        if (full.endsWith('/>')) continue

        const isClose = full.startsWith('</')
        if (!isClose) {
          openStack.push(tag)
        } else {
          if (openStack.length === 0) {
            unexpectedCloseTags.push('</' + tag + '>')
          } else {
            const top = openStack[openStack.length - 1]
            if (top === tag) {
              openStack.pop()
            } else {
              mismatchedTags.push('</' + top + '> expected, got </' + tag + '>')
              if (openStack.includes(tag)) {
                while (openStack.length && openStack[openStack.length - 1] !== tag) {
                  openStack.pop()
                }
                openStack.pop()
              }
            }
          }
        }
      }

      if (openStack.length) {
        const unique = [...new Set(openStack)]
        warnings.push('Unclosed tag' + (unique.length > 1 ? 's' : '') + ': ' + unique.map(t => '<' + t + '>').join(', '))
      }

      if (mismatchedTags.length) {
        warnings.push('Mismatched tag' + (mismatchedTags.length > 1 ? 's' : '') + ': ' + mismatchedTags.slice(0, 2).join('; '))
      }

      if (unexpectedCloseTags.length) {
        const unique = [...new Set(unexpectedCloseTags)]
        warnings.push('Unexpected closing tag' + (unique.length > 1 ? 's' : '') + ': ' + unique.join(', '))
      }

      const openBrackets  = (stripStrings.match(/\{/g)  || []).length
      const closeBrackets = (stripStrings.match(/\}/g)  || []).length
      if (openBrackets !== closeBrackets) {
        warnings.push('Mismatched { } (' + openBrackets + ' open, ' + closeBrackets + ' close)')
      }

      const openParens  = (stripStrings.match(/\(/g) || []).length
      const closeParens = (stripStrings.match(/\)/g) || []).length
      if (openParens !== closeParens) {
        warnings.push('Mismatched ( ) (' + openParens + ' open, ' + closeParens + ' close)')
      }

      return warnings
    },
  },
}
</script>

<style scoped>
.ab-code-tab { max-width: 860px; }
.ab-scope-block {
  background: #f8f9fa;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 12px 14px;
}
.ab-menu-select {
  min-height: 160px;
  max-height: 280px;
  font-size: .875rem;
}
.ab-menu-select option { padding: 3px 6px; }
.ab-menu-select option:checked { background: #0d6efd; color: #fff; }

.ab-code-meta {
  display: flex;
  align-items: center;
  gap: .75rem;
  margin-top: .25rem;
  min-height: 1.25rem;
}
.ab-char-count {
  font-size: .8rem;
  color: #6c757d;
  font-variant-numeric: tabular-nums;
}
.ab-syntax-warn {
  font-size: .8rem;
  color: #b45309;
  background: #fef9c3;
  border: 1px solid #fde68a;
  border-radius: .25rem;
  padding: .1rem .45rem;
  line-height: 1.4;
}
</style>
