<template>
  <div class="ab-settings-tab">
    <ProGate mode="card" label="Custom Code">
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">01</span>
        Custom Code Injection
        <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Enable Custom Code Injection</div>
            <div class="ab-help">The plugin must also be enabled in Joomla Plugin Manager.</div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.enable_custom_code === '1'}">
            <input v-model="s.enable_custom_code" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="cc-enable">
            <span class="ab-toggle__track"></span>
          </span>
        </label>

        <div class="ab-field">
          <div class="ab-eyebrow">Head Code</div>
          <label class="ab-label">Inject before <code>&lt;/head&gt;</code></label>
          <textarea v-model="s.custom_code_head" class="ab-textarea ab-code-area" rows="7"
            placeholder="&lt;!-- paste scripts, meta tags, stylesheets here --&gt;"></textarea>
          <div class="ab-code-meta">
            <span class="ab-char-count">{{ formatCount(s.custom_code_head) }}</span>
            <span v-if="headWarnings.length" class="ab-syntax-warn">
              {{ headWarnings.join(' · ') }}
            </span>
          </div>
          <div class="ab-help">Raw HTML injected into every page's &lt;head&gt;. Accepts &lt;script&gt;, &lt;link&gt;, &lt;meta&gt;, &lt;style&gt; tags.</div>
        </div>

        <div class="ab-field">
          <div class="ab-eyebrow">Body Code</div>
          <label class="ab-label">Inject after opening <code>&lt;body&gt;</code></label>
          <textarea v-model="s.custom_code_body" data-ab-field="custom_code_body" class="ab-textarea ab-code-area" rows="7"
            placeholder="&lt;!-- paste chat widgets, noscript tags, etc. --&gt;"></textarea>
          <div class="ab-code-meta">
            <span class="ab-char-count">{{ formatCount(s.custom_code_body) }}</span>
            <span v-if="bodyWarnings.length" class="ab-syntax-warn">
              {{ bodyWarnings.join(' · ') }}
            </span>
          </div>
          <div class="ab-help">Raw HTML injected immediately after the opening &lt;body&gt; tag. Useful for GTM noscript, chat widgets.</div>
        </div>

        <div class="ab-field">
          <div class="ab-eyebrow">Footer Code</div>
          <label class="ab-label">Inject before <code>&lt;/body&gt;</code></label>
          <textarea v-model="s.custom_code_footer" data-ab-field="custom_code_footer" class="ab-textarea ab-code-area" rows="7"
            placeholder="&lt;!-- paste deferred scripts, chat widgets, tracking pixels here --&gt;"></textarea>
          <div class="ab-code-meta">
            <span class="ab-char-count">{{ formatCount(s.custom_code_footer) }}</span>
            <span v-if="footerWarnings.length" class="ab-syntax-warn">
              {{ footerWarnings.join(' · ') }}
            </span>
          </div>
          <div class="ab-help">Raw HTML injected just before the closing <code>&lt;/body&gt;</code> tag. Ideal for deferred scripts, chat widgets, and tracking pixels.</div>
        </div>

      </div>
    </div>
    </ProGate>
  </div>
</template>

<script>
import ProGate from '../components/ProGate.vue'

export default {
  name: 'CodeTab',
  components: { ProGate },
  props: { s: { type: Object, required: true } },

  created() {
    // Custom code currently applies to ALL pages. The per-menu scope picker was
    // removed (only an "all pages" note for now; richer scope options come later),
    // so force the saved scope to 'all' and clear any stale menu-id selection —
    // both per-field and legacy — so an older 'specific' config can't keep the
    // code off some pages with no UI left to fix it. The consuming plugin
    // (AiBoostCode::isFieldApplicable) injects everywhere when scope='all'.
    for (const field of ['head', 'body', 'footer']) {
      this.s[`custom_code_${field}_scope`] = 'all'
      this.s[`custom_code_${field}_menu_ids`] = '[]'
    }
    this.s.custom_code_scope = 'all'
    this.s.custom_code_menu_ids = '[]'
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
.ab-code-meta {
  display: flex;
  align-items: center;
  gap: .75rem;
  margin-top: .25rem;
  min-height: 1.25rem;
}
.ab-char-count {
  font-size: var(--ab-font-size-xs);
  color: var(--ab-text-muted);
  font-variant-numeric: tabular-nums;
}
.ab-syntax-warn {
  font-size: var(--ab-font-size-xs);
  color: var(--ab-warning);
  background: var(--ab-warning-soft);
  border: 1px solid var(--ab-warning);
  border-radius: var(--ab-radius);
  padding: .1rem .45rem;
  line-height: 1.4;
}
.ab-code-area { font-family: var(--ab-font-mono); }
</style>
