<template>
  <div class="ab-settings-tab">

    <!-- 01 llms.txt — AI Site Index -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">01</span>
        llms.txt — AI Site Index
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div><div class="ab-label">Enable <code>/llms.txt</code></div></div>
          <span class="ab-toggle" :class="{'is-on': s.llmstxt_enabled === '1'}">
            <input v-model="s.llmstxt_enabled" data-ab-field="llmstxt_enabled" true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="aeo-llms-enabled">
            <span class="ab-toggle__track"></span>
          </span>
        </label>

        <template v-if="s.llmstxt_enabled === '1'">
          <div class="ab-field" data-ab-field="llmstxt_custom_pages">
            <label class="ab-label">Site Description for AI</label>
            <textarea v-model="s.llmstxt_description" class="ab-input" rows="3"
              placeholder="A brief description of your site for AI engines (ChatGPT, Claude, Perplexity…)"></textarea>
            <div class="ab-help">Appears at the top of <code>llms.txt</code> as the primary site summary.</div>
            <ProGate mode="field" label="Translate"><TranslationExpander field-key="llmstxt_description" /></ProGate>
          </div>

          <div class="ab-field">
            <label class="ab-label">Recent Articles</label>
            <input v-model="s.llmstxt_recent_articles" type="number" min="1" max="50"
              class="ab-input" style="max-width:120px">
            <div class="ab-help">Number of most-recent articles to auto-include.</div>
          </div>

          <div class="ab-field">
            <label class="ab-label">Custom Pages</label>
            <div v-for="(page, i) in customPages" :key="i" class="d-flex gap-2 mb-2 align-items-start">
              <input v-model="page.url" placeholder="https://example.com/about" class="ab-input form-control-sm">
              <input v-model="page.description" placeholder="About page — who we are and what we do" class="ab-input form-control-sm">
              <button @click="removePage(i)" type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost">×</button>
            </div>
            <button @click="addPage" type="button" class="ab-btn ab-btn--sm ab-btn--ghost">+ Add Page</button>
            <div class="ab-help">Manual pages to include in llms.txt with descriptions.</div>
          </div>

          <div class="ab-alert ab-alert--info">
            <strong>FAQ</strong> is managed in one place —
            <strong>Schema.org → FAQ / QAPage</strong>. Whatever you add there is automatically included
            in <code>/llms.txt</code> too, so you only enter your Q&amp;A once.
          </div>
        </template>
      </div>
    </div>

    <!-- 02 llms-full.txt (Pro) -->
    <ProGate mode="card" label="llms-full.txt">
      <div class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">02</span>
          llms-full.txt — Full Site Index
          <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
        </div>
        <div class="ab-section__body">
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable <code>/llms-full.txt</code></div></div>
            <span class="ab-toggle" :class="{'is-on': s.llms_full_txt_enabled === '1'}">
              <input v-model="s.llms_full_txt_enabled" data-ab-field="llms_full_txt_enabled"
                true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <div class="ab-field">
            <label class="ab-label">Max Articles to Include</label>
            <input v-model="s.llms_full_max_articles" type="number" min="10" max="5000"
              class="ab-input" style="max-width:120px">
            <div class="ab-help">All articles up to this limit are indexed for AI engines.</div>
          </div>
        </div>
      </div>
    </ProGate>

    <!-- 03 IndexNow (Pro) -->
    <ProGate mode="card" label="IndexNow">
      <div class="ab-section">
        <div class="ab-section__head">
          <span class="ab-section__num">03</span>
          IndexNow — Instant Search Indexing
          <span class="ab-tag ab-tag--pro" style="margin-left:.4rem">Pro</span>
        </div>
        <div class="ab-section__body">
          <label class="ab-toggle-row">
            <div><div class="ab-label">Enable IndexNow</div></div>
            <span class="ab-toggle" :class="{'is-on': s.indexnow_enabled === '1'}">
              <input v-model="s.indexnow_enabled" data-ab-field="indexnow_enabled"
                true-value="1" false-value="0" type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
          <div class="ab-field">
            <label class="ab-label">API Key</label>
            <div class="d-flex gap-2 align-items-center">
              <input v-model="s.indexnow_api_key" data-ab-field="indexnow_api_key"
                type="text" class="ab-input font-monospace"
                placeholder="Leave empty to auto-generate on save" style="max-width:360px">
              <button @click="generateKey" type="button" class="ab-btn ab-btn--ghost">Generate</button>
            </div>
            <div class="ab-help">
              Used for Bing, Yandex, Seznam. IndexNow key file is auto-served at
              <code>/&lt;key&gt;.txt</code>.
            </div>
          </div>
          <label class="ab-toggle-row">
            <div>
              <div class="ab-label">Auto-submit URLs on article publish / update</div>
            </div>
            <span class="ab-toggle" :class="{'is-on': s.indexnow_auto_submit === '1'}">
              <input v-model="s.indexnow_auto_submit" true-value="1" false-value="0"
                type="checkbox" class="ab-toggle__input">
              <span class="ab-toggle__track"></span>
            </span>
          </label>
        </div>
      </div>
    </ProGate>

    <!-- 04 Markdown Pages -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">04</span>
        Markdown Pages — AI Agent Endpoint
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div><div class="ab-label">Serve pages as Markdown for AI agents</div></div>
          <span class="ab-toggle" :class="{'is-on': s.markdown_pages_enabled === '1'}">
            <input v-model="s.markdown_pages_enabled" data-ab-field="markdown_pages_enabled"
              true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="aeo-markdown-enabled">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
        <div class="ab-help">
          Any public page can be fetched as clean Markdown by AI tools (ChatGPT, Claude,
          Perplexity, custom agents). Three triggers:
          <ul class="mb-0 mt-1 ps-3">
            <li>Append <code>.md</code> to the URL — <code>/article-slug.md</code></li>
            <li>Add query param <code>?markdown=1</code> to any page URL</li>
            <li>Send an <code>Accept: text/markdown</code> HTTP header</li>
          </ul>
          A <code>&lt;link rel="alternate" type="text/markdown"&gt;</code> tag is also added
          to every <code>&lt;head&gt;</code> so agents auto-discover the Markdown URL.
          Navigation, sidebars, and scripts are stripped — only the main content stays.
        </div>
      </div>
    </div>

    <!-- 05 AI Signals -->
    <div class="ab-section">
      <div class="ab-section__head">
        <span class="ab-section__num">05</span>
        AI Signals
      </div>
      <div class="ab-section__body">
        <label class="ab-toggle-row">
          <div>
            <div class="ab-label">Enable AI meta tags</div>
            <div class="ab-help">
              Injects <code>&lt;meta name="ai-content-optimized"&gt;</code> and related signals
              that help AI engines identify and index your content.
            </div>
          </div>
          <span class="ab-toggle" :class="{'is-on': s.aeo_ai_meta_enabled === '1'}">
            <input v-model="s.aeo_ai_meta_enabled" data-ab-field="aeo_ai_meta_enabled"
              true-value="1" false-value="0" type="checkbox" class="ab-toggle__input" id="aeo-ai-meta">
            <span class="ab-toggle__track"></span>
          </span>
        </label>
      </div>
    </div>

  </div>
</template>

<script>
import TranslationExpander from '../components/TranslationExpander.vue'
import ProGate from '../components/ProGate.vue'

export default {
  name: 'AeoTab',
  components: { TranslationExpander, ProGate },
  props: { s: { type: Object, required: true } },

  data() {
    let customPages = []
    try { customPages = JSON.parse(this.s.llmstxt_custom_pages || '[]') } catch { /**/ }
    if (!Array.isArray(customPages)) customPages = []

    return {
      customPages,
    }
  },

  watch: {
    customPages: {
      handler(v) { this.s.llmstxt_custom_pages = JSON.stringify(v) },
      deep: true,
    },
  },

  methods: {
    addPage()     { this.customPages.push({ url: '', description: '' }) },
    removePage(i) { this.customPages.splice(i, 1) },

    generateKey() {
      const chars = 'abcdefghijklmnopqrstuvwxyz0123456789'
      this.s.indexnow_api_key = Array.from(
        { length: 32 },
        () => chars[Math.floor(Math.random() * chars.length)]
      ).join('')
    },
  },
}
</script>
