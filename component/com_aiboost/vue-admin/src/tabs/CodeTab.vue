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
        <div class="ab-help ab-scope-note">ℹ️ This code is inserted on <strong>all pages</strong>.</div>

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
        <div class="ab-help ab-scope-note">ℹ️ This code is inserted on <strong>all pages</strong>.</div>

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
        <div class="ab-help ab-scope-note">ℹ️ This code is inserted on <strong>all pages</strong>.</div>

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
.ab-code-tab { max-width: 860px; }
/* Custom code applies to all pages for now; the per-menu ScopeSelector was
   removed from this tab (kept in components/ for future scope options). */
.ab-scope-note { margin-top: .5rem; }

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
