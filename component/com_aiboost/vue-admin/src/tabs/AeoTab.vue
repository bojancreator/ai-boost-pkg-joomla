<template>
  <div class="ab-aeo-tab">

    <div class="ab-page-intro mb-3">
      <h2 class="ab-page-intro__title">AEO</h2>
      <p class="ab-page-intro__text">
        Signals that help AI search and generative engines understand, index, and cite your site.
      </p>
    </div>

    <!-- ── Section: llms.txt (Free baseline) ─────────────────────── -->
    <div class="ab-card mb-3">
      <div class="ab-card-header">
        llms.txt — AI Site Index
      </div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input
            v-model="s.llmstxt_enabled" data-ab-field="llmstxt_enabled"
            true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="aeo-llms-enabled"
          />
          <label class="ab-check__label" for="aeo-llms-enabled">
            Enable <code>/llms.txt</code>
          </label>
        </div>

        <template v-if="s.llmstxt_enabled === '1'">
          <div class="mb-3" data-ab-field="llmstxt_custom_pages">
            <label class="ab-label">Site Description for AI</label>
            <textarea
              v-model="s.llmstxt_description"
              class="ab-input" rows="3"
              placeholder="A brief description of your site for AI engines (ChatGPT, Claude, Perplexity…)"
            ></textarea>
            <div class="ab-help">Appears at the top of <code>llms.txt</code> as the primary site summary.</div>
            <ProGate mode="field" label="Translate"><TranslationExpander field-key="llmstxt_description" /></ProGate>
          </div>

          <div class="mb-3">
            <label class="ab-label">Recent Articles</label>
            <input
              v-model="s.llmstxt_recent_articles"
              type="number" min="1" max="50"
              class="ab-input" style="max-width:120px"
            />
            <div class="ab-help">Number of most-recent articles to auto-include.</div>
          </div>

          <!-- Custom Pages -->
          <div class="mb-3">
            <label class="ab-label">Custom Pages</label>
            <div
              v-for="(page, i) in customPages" :key="i"
              class="d-flex gap-2 mb-2 align-items-start"
            >
              <input v-model="page.url" placeholder="https://example.com/about" class="ab-input form-control-sm" />
              <input v-model="page.description" placeholder="About page — who we are and what we do" class="ab-input form-control-sm" />
              <button @click="removePage(i)" type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost">×</button>
            </div>
            <button @click="addPage" type="button" class="ab-btn ab-btn--sm ab-btn--ghost">+ Add Page</button>
            <div class="ab-help mt-1">Manual pages to include in llms.txt with descriptions.</div>
          </div>

          <!-- FAQ is managed once in Schema.org and reused here (Korak 3.2 #7). -->
          <div class="ab-info-box mb-1">
            <strong>FAQ</strong> is now managed in one place —
            <strong>Schema.org → FAQ / QAPage</strong>. Whatever you add there is automatically included
            in <code>/llms.txt</code> too, so you only enter your Q&amp;A once.
          </div>
        </template>
      </div>
    </div>

    <!-- ── Section: llms-full.txt (Pro) ── -->
    <ProGate mode="card" label="llms-full.txt">
      <div class="ab-card mb-3">
        <div class="ab-card-header">llms-full.txt — Full Site Index <span class="ab-pro-tag">Pro</span></div>
        <div class="ab-card-body">
          <div class="ab-check ab-toggle mb-3">
            <input
              v-model="s.llms_full_txt_enabled" data-ab-field="llms_full_txt_enabled"
              true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input"
            />
            <label class="ab-check__label">Enable <code>/llms-full.txt</code></label>
          </div>
          <div class="mb-2">
            <label class="ab-label">Max Articles to Include</label>
            <input
              v-model="s.llms_full_max_articles"
              type="number" min="10" max="5000"
              class="ab-input" style="max-width:120px"
            />
            <div class="ab-help">All articles up to this limit are indexed for AI engines.</div>
          </div>
        </div>
      </div>
    </ProGate>

    <!-- ── Section: IndexNow (Pro) ── -->
    <ProGate mode="card" label="IndexNow">
      <div class="ab-card mb-3">
        <div class="ab-card-header">IndexNow — Instant Search Indexing <span class="ab-pro-tag">Pro</span></div>
        <div class="ab-card-body">
          <div class="ab-check ab-toggle mb-3">
            <input
              v-model="s.indexnow_enabled"
              data-ab-field="indexnow_enabled"
              true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input"
            />
            <label class="ab-check__label">Enable IndexNow</label>
          </div>
          <div class="mb-3">
            <label class="ab-label">API Key</label>
            <div class="input-group">
              <input
                v-model="s.indexnow_api_key"
                data-ab-field="indexnow_api_key"
                type="text" class="ab-input font-monospace"
                placeholder="Leave empty to auto-generate on save"
              />
              <button
                @click="generateKey" type="button"
                class="ab-btn ab-btn--ghost"
              >
                Generate
              </button>
            </div>
            <div class="ab-help">
              Used for Bing, Yandex, Seznam. IndexNow key file is auto-served at
              <code>/&lt;key&gt;.txt</code>.
            </div>
          </div>
          <div class="ab-check ab-toggle">
            <input
              v-model="s.indexnow_auto_submit"
              true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input"
            />
            <label class="ab-check__label">Auto-submit URLs on article publish / update</label>
          </div>
        </div>
      </div>
    </ProGate>

    <!-- ── Section: Markdown Pages (Free) ── -->
      <div class="ab-card mb-3">
        <div class="ab-card-header">Markdown Pages — AI Agent Endpoint</div>
        <div class="ab-card-body">
          <div class="ab-check ab-toggle mb-2">
            <input
              v-model="s.markdown_pages_enabled" data-ab-field="markdown_pages_enabled"
              true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="aeo-markdown-enabled"
            />
            <label class="ab-check__label" for="aeo-markdown-enabled">
              Serve pages as Markdown for AI agents
            </label>
          </div>
          <div class="ab-help">
            Any public page can be fetched as clean Markdown by AI tools (ChatGPT, Claude,
            Perplexity, custom agents). Three triggers:
            <ul class="mb-1 mt-1 ps-3">
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

    <!-- ── Section: AI Signals (Free) ────────────────────────────── -->
      <div class="ab-card mb-4">
        <div class="ab-card-header">
          AI Signals
        </div>
        <div class="ab-card-body">
          <div class="ab-check ab-toggle mb-1">
            <input
              v-model="s.aeo_ai_meta_enabled" data-ab-field="aeo_ai_meta_enabled"
              true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="aeo-ai-meta"
            />
            <label class="ab-check__label" for="aeo-ai-meta">Enable AI meta tags</label>
          </div>
          <div class="ab-help">
            Injects <code>&lt;meta name="ai-content-optimized"&gt;</code> and related signals
            that help AI engines identify and index your content.
          </div>
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

<style scoped>
.ab-aeo-tab { max-width: 860px; }
.ab-page-intro__title {
  margin: 0 0 4px;
  font-size: 1.25rem;
  font-weight: 700;
}
.ab-page-intro__text {
  margin: 0;
  color: var(--secondary-color, #6c757d);
  font-size: .92rem;
}
.ab-info-box {
  font-size: .82rem;
  line-height: 1.6;
  color: var(--secondary-color, #6c757d);
  background: var(--secondary-bg, #f8f9fa);
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 6px;
  padding: 8px 11px;
}
.ab-pro-tag {
  font-size: .62rem;
  font-weight: 700;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: #b8860b;
  background: #fffbf0;
  border: 1px solid #ffe8a1;
  border-radius: 999px;
  padding: 1px 7px;
  vertical-align: middle;
}

/* Task #473 — AEO inputs must be readable in dark mode. */
[data-bs-theme=dark] .ab-input,
[data-bs-theme=dark] .ab-select {
  background-color: #1a1d21 !important;
  color: #e6edf3 !important;
  border-color: #2d3338 !important;
}
[data-bs-theme=dark] .ab-input::placeholder { color: #6e7681 !important; }
[data-bs-theme=dark] .ab-input:focus,
[data-bs-theme=dark] .ab-select:focus {
  background-color: #21262d !important;
  border-color: #388bfd !important;
}
[data-bs-theme=dark] .ab-pro-tag { background: #2a2000; border-color: #4a3800; }
</style>
