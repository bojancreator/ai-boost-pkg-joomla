<template>
  <div class="ab-aeo-tab">

    <!-- ── Section: llms.txt ─────────────────────────────────────── -->
    <div class="ab-card mb-3">
      <div class="ab-card-header">
        <span class="ab-badge ab-badge-free">Free</span>
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
          <div class="mb-3">
            <label class="ab-label">Site Description for AI</label>
            <textarea
              v-model="s.llmstxt_description"
              class="ab-input" rows="3"
              placeholder="A brief description of your site for AI engines (ChatGPT, Claude, Perplexity…)"
            ></textarea>
            <div class="ab-help">Appears at the top of <code>llms.txt</code> as the primary site summary.</div>
            <TranslationExpander field-key="llmstxt_description" />
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

          <!-- FAQ Auto-Detect -->
          <div class="ab-check ab-toggle mb-2">
            <input
              v-model="s.llmstxt_faq_auto_detect" data-ab-field="llmstxt_faq_auto_detect"
              true-value="1" false-value="0"
              type="checkbox" class="ab-toggle__input" id="aeo-faq-auto"
            />
            <label class="ab-check__label" for="aeo-faq-auto">
              Auto-Detect FAQ from Articles
            </label>
            <div class="ab-help">
              Scans the 25 most recent published articles and extracts Q&amp;A pairs from
              <code>&lt;h2&gt;/&lt;h3&gt;/&lt;h4&gt;</code> question headings followed by
              paragraph answers. Detected items are appended after manual FAQs (duplicates are skipped).
              Capped at 30 pairs total.
            </div>
          </div>

          <!-- FAQ Items -->
          <div class="mb-1">
            <label class="ab-label">Manual FAQ Items</label>
            <div
              v-for="(faq, i) in faqItems" :key="i"
              class="ab-faq-row mb-2 p-2 rounded border"
            >
              <div class="d-flex gap-2 mb-1">
                <input v-model="faq.question" placeholder="Question…" class="ab-input form-control-sm" />
                <button @click="removeFaq(i)" type="button" class="ab-btn ab-btn--sm ab-btn--ghost ab-btn--danger-ghost">×</button>
              </div>
              <textarea v-model="faq.answer" placeholder="Answer…" class="ab-input form-control-sm" rows="2"></textarea>
            </div>
            <button @click="addFaq" type="button" class="ab-btn ab-btn--sm ab-btn--ghost">+ Add FAQ Item</button>
            <div class="ab-help mt-1">Q&amp;A pairs included in llms.txt for AI answer engines.</div>
          </div>
        </template>
      </div>
    </div>

    <!-- ── Section: llms-full.txt (Pro — gated via ProGate registry) ── -->
    <ProGate gate-key="section:aeo.llms_full" mode="section">
    <div class="ab-card mb-3">
      <div class="ab-card-header">llms-full.txt — Full Site Index</div>
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

    <!-- ── Section: robots.txt ───────────────────────────────────── -->
    <div class="ab-card mb-3">
      <div class="ab-card-header">
        <span class="ab-badge ab-badge-free">Free</span>
        robots.txt Management
      </div>
      <div class="ab-card-body">
        <div class="ab-help mb-3">
          Block SEO data-mining bots via robots.txt. Toggle each crawler individually.
        </div>

        <!-- Individual SEO scraper checkboxes -->
        <div class="row g-2 mb-3">
          <div v-for="bot in SEO_SCRAPERS" :key="bot.key" class="col-6 col-sm-4">
            <label :for="'scraper-' + bot.key"
                   class="ab-scraper-box d-flex align-items-center gap-2 p-2 border rounded small mb-0"
                   :class="{ 'ab-scraper-blocked': s[bot.key] === '1' }"
                   style="cursor:pointer">
              <div class="ab-check mb-0">
                <input
                  type="checkbox"
                  class="ab-toggle__input"
                  :id="'scraper-' + bot.key"
                  :data-ab-field="bot.key"
                  v-model="s[bot.key]"
                  true-value="1" false-value="0"
                />
              </div>
              <span class="lh-sm">
                <span class="fw-semibold d-block">{{ bot.label }}</span>
                <span class="text-muted" style="font-size:.7rem">{{ bot.agent }}</span>
              </span>
            </label>
          </div>
        </div>

        <!-- Unified Custom robots.txt Rules (Task #481 — merged the old
             "Advanced — custom user-agent rules" collapsible and the standalone
             "Custom robots.txt Rules" textarea into one sub-section. Both
             setting keys (`robots_custom_scrapers`, `robots_custom_rules`) are
             still saved separately so RobotsTxtBuilder / RobotsTxtManager see
             no behaviour change. -->
        <div class="ab-subcard">
          <div class="ab-subcard-head">
            <strong>Custom robots.txt Rules</strong>
            <span class="text-muted small ms-2">(appended verbatim to robots.txt)</span>
          </div>

          <div class="mt-2 mb-3">
            <label class="ab-label">Additional user-agent blocks</label>
            <textarea
              v-model="s.robots_custom_scrapers" data-ab-field="robots_custom_scrapers"
              class="ab-input font-monospace" rows="4"
              placeholder="User-agent: CustomBot&#10;Disallow: /"
            ></textarea>
            <div class="ab-help mt-1">
              For bots not in the checkbox list above. One
              <code>User-agent:</code> + <code>Disallow:</code> pair per bot.
            </div>
          </div>

          <div>
            <label class="ab-label">Free-form rules</label>
            <textarea
              v-model="s.robots_custom_rules" data-ab-field="robots_custom_rules"
              class="ab-input font-monospace" rows="4"
              placeholder="Sitemap: https://example.com/sitemap.xml&#10;Crawl-delay: 10"
            ></textarea>
            <div class="ab-help">
              Anything else you want in robots.txt — sitemap lines, crawl-delay,
              host directives, comments…
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Section: AI Crawler Rules (Free — consolidated in Task #463) ── -->
    <div class="ab-card mb-3">
      <div class="ab-card-header">
        <span class="ab-badge ab-badge-free">Free</span>
        AI Crawler Rules
      </div>
      <div class="ab-card-body">
        <div class="ab-check ab-toggle mb-3">
          <input
            v-model="s.ai_crawlers_enabled" data-ab-field="ai_crawlers_enabled"
            true-value="1" false-value="0"
            type="checkbox" class="ab-toggle__input" id="aeo-crawlers-enabled"
          />
          <label class="ab-check__label" for="aeo-crawlers-enabled">Enable per-bot rules</label>
        </div>
        <div class="ab-help mb-3">
          Control which AI engines may crawl your content. Default = follow global robots.txt rules.
        </div>

        <template v-if="s.ai_crawlers_enabled === '1'">
          <!-- Task #482 — page-level default policy for crawlers without
               an explicit per-bot rule. Replaces the per-row "Default"
               option, which was unclear to admins. -->
          <div class="ab-default-policy mb-3" data-ab-field="aeo_crawler_default_policy">
            <div class="ab-default-policy__label">Default policy for unspecified crawlers</div>
            <div class="ab-radio-group" role="radiogroup" aria-label="Default policy for unspecified crawlers">
              <label class="ab-radio">
                <input type="radio" v-model="s.aeo_crawler_default_policy" value="allow" />
                <span>Allow all</span>
              </label>
              <label class="ab-radio">
                <input type="radio" v-model="s.aeo_crawler_default_policy" value="block" />
                <span>Block all</span>
              </label>
            </div>
            <div class="ab-help">
              Crawlers not given an explicit Allow or Block below will use this default.
            </div>
          </div>

          <div class="ab-bot-list" data-ab-field="crawler_bot_rules">
            <div
              v-for="bot in BOTS" :key="bot.id"
              class="ab-bot-row"
            >
              <div class="ab-bot-info">
                <span class="ab-bot-name">{{ bot.label }}</span>
                <span class="ab-bot-company">{{ bot.company }}</span>
              </div>
              <div class="ab-radio-group ab-bot-radio-group" role="radiogroup" :aria-label="bot.label + ' policy'">
                <label class="ab-radio">
                  <input type="radio" :name="'bot-' + bot.id" :value="'allow'" v-model="botRules[bot.id]" />
                  <span>Allow</span>
                </label>
                <label class="ab-radio">
                  <input type="radio" :name="'bot-' + bot.id" :value="'block'" v-model="botRules[bot.id]" />
                  <span>Block</span>
                </label>
              </div>
            </div>
          </div>

          <div class="ab-sec mt-3">Custom Rules <span style="opacity:.5;font-weight:400">(appended verbatim after the per-bot section above)</span></div>
          <textarea
            v-model="s.crawler_rules" data-ab-field="crawler_rules"
            class="ab-input font-monospace" rows="5"
            placeholder="User-agent: CCBot&#10;Disallow: /"
          ></textarea>
          <div class="ab-help mt-1">
            One block per bot. Format: <code>User-agent: Name</code> then <code>Disallow: /path</code> or <code>Allow: /path</code>.
            Use this for bots not in the list above (Common Crawl, Wayback Machine, social previews, …).
          </div>
        </template>
      </div>
    </div>

    <!-- ── Section: IndexNow (Pro — gated via ProGate registry) ── -->
    <ProGate gate-key="section:aeo.indexnow" mode="section">
    <div class="ab-card mb-3">
      <div class="ab-card-header">IndexNow — Instant Search Indexing</div>
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

    <!-- ── Section: Markdown Pages (Pro — gated via ProGate registry) ── -->
    <ProGate gate-key="section:aeo.markdown" mode="section">
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
    </ProGate>

    <!-- ── Section: AI Signals ───────────────────────────────────── -->
    <div class="ab-card mb-4">
      <div class="ab-card-header">
        <span class="ab-badge ab-badge-free">Free</span>
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
// Pro gating for this tab's 4 Pro sections is fully delegated to <ProGate>
// (registry keys: section:aeo.llms_full, .crawler_rules, .indexnow, .markdown).
// No local isPro check or :disabled wiring is required here.
import TranslationExpander from '../components/TranslationExpander.vue'

const SEO_SCRAPERS = [
  { key: 'scraper_ahrefsbot',      label: 'Ahrefs',          agent: 'AhrefsBot' },
  { key: 'scraper_semrushbot',     label: 'Semrush',         agent: 'SemrushBot' },
  { key: 'scraper_dotbot',         label: 'Dotbot',          agent: 'DotBot' },
  { key: 'scraper_mj12bot',        label: 'Majestic',        agent: 'MJ12bot' },
  { key: 'scraper_blexbot',        label: 'BLEXBot',         agent: 'BLEXBot' },
  { key: 'scraper_rogerbot',       label: 'Moz',             agent: 'rogerbot' },
  { key: 'scraper_screamingfrog',  label: 'Screaming Frog',  agent: 'Screaming Frog SEO Spider' },
  { key: 'scraper_sitebulb',       label: 'Sitebulb',        agent: 'Sitebulb' },
  { key: 'scraper_siteauditor',    label: 'SE Ranking',      agent: 'SiteAuditBot' },
  { key: 'scraper_serpstatbot',    label: 'Serpstat',        agent: 'SerpstatBot' },
  { key: 'scraper_bytespider',     label: 'Bytespider',      agent: 'Bytespider' },
  { key: 'scraper_petalbot',       label: 'PetalBot',        agent: 'PetalBot' },
]

const BOTS = [
  { id: 'gptbot',        label: 'GPTBot',              company: 'OpenAI' },
  { id: 'claudebot',     label: 'ClaudeBot',           company: 'Anthropic' },
  { id: 'perplexitybot', label: 'PerplexityBot',       company: 'Perplexity' },
  { id: 'geminibot',     label: 'Google-Extended',     company: 'Google (Gemini)' },
  { id: 'bingbot',       label: 'Bingbot',             company: 'Microsoft' },
  { id: 'facebookbot',   label: 'FacebookBot',         company: 'Meta' },
  { id: 'applebot',      label: 'Applebot-Extended',   company: 'Apple' },
  { id: 'duckassistbot', label: 'DuckAssistBot',       company: 'DuckDuckGo' },
  { id: 'youbot',        label: 'YouBot',              company: 'You.com' },
]

// Task #482 — per-bot rule starts unspecified ('') so the page-level
// default policy applies. Legacy 'default' values are normalized to '' on
// load so the Allow/Block radios render as unselected (= "follow default").
const DEFAULT_BOT_RULES = Object.fromEntries(BOTS.map((b) => [b.id, '']))

export default {
  name: 'AeoTab',
  components: { TranslationExpander },
  props: { s: { type: Object, required: true } },

  data() {
    let customPages = []
    try { customPages = JSON.parse(this.s.llmstxt_custom_pages || '[]') } catch { /**/ }
    if (!Array.isArray(customPages)) customPages = []

    let faqItems = []
    try { faqItems = JSON.parse(this.s.llmstxt_faq_items || '[]') } catch { /**/ }
    if (!Array.isArray(faqItems)) faqItems = []

    let botRules = {}
    try { botRules = JSON.parse(this.s.crawler_bot_rules || '{}') } catch { /**/ }

    // Task #482 — normalize per-bot values for the new Allow/Block radio UI:
    //   'allow' / 'block'  → keep as-is
    //   'disallow'         → 'block' (legacy synonym still honoured by the builder)
    //   'default' / other  → '' (radios unselected → follow page-level policy)
    for (const k of Object.keys(botRules)) {
      const v = String(botRules[k] ?? '').toLowerCase().trim()
      if (v === 'allow' || v === 'block') {
        botRules[k] = v
      } else if (v === 'disallow') {
        botRules[k] = 'block'
      } else {
        botRules[k] = ''
      }
    }

    // Task #482 — page-level default policy: backfill 'allow' when missing
    // so existing installs that never set the new key behave as before.
    if (typeof this.s.aeo_crawler_default_policy === 'undefined'
        || this.s.aeo_crawler_default_policy === null
        || this.s.aeo_crawler_default_policy === '') {
      this.s.aeo_crawler_default_policy = 'allow'
    }

    return {
      BOTS,
      SEO_SCRAPERS,
      customPages,
      faqItems,
      botRules: { ...DEFAULT_BOT_RULES, ...botRules },
    }
  },

  watch: {
    customPages: {
      handler(v) { this.s.llmstxt_custom_pages = JSON.stringify(v) },
      deep: true,
    },
    faqItems: {
      handler(v) { this.s.llmstxt_faq_items = JSON.stringify(v) },
      deep: true,
    },
    botRules: {
      handler(v) { this.s.crawler_bot_rules = JSON.stringify(v) },
      deep: true,
    },
  },

  methods: {
    addPage()     { this.customPages.push({ url: '', description: '' }) },
    removePage(i) { this.customPages.splice(i, 1) },
    addFaq()      { this.faqItems.push({ question: '', answer: '' }) },
    removeFaq(i)  { this.faqItems.splice(i, 1) },

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
.ab-faq-row { background: var(--secondary-bg, #f8f9fa); color: var(--body-color, #212529); }
.ab-pro-section { position: relative; }
.ab-disabled    { opacity: .42; pointer-events: none; user-select: none; }
.ab-lock        { font-size: .85rem; }
.ab-upgrade-bar {
  padding: .55rem 1rem .7rem;
  background: #fffbf0;
  border-top: 1px solid #ffe8a1;
  text-align: center;
  color: var(--secondary-color, #6c757d);
}
.ab-upgrade-bar a { color: #0d6efd; }
[data-bs-theme=dark] .ab-upgrade-bar { background: #2a2000; border-top-color: #4a3800; }

/* Task #473 — robots.txt + AEO inputs must be readable in dark mode.
   Joomla's Bootstrap data-bs-theme=dark variant doesn't always reach our
   custom `.ab-input` / `.ab-select` classes, so force theme-safe colours. */
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
.ab-scraper-box { transition: border-color .15s, background .15s; cursor: default; }
.ab-scraper-blocked { background: rgba(220,53,69,.06); border-color: rgba(220,53,69,.3) !important; }
[data-bs-theme=dark] .ab-scraper-blocked { background: rgba(220,53,69,.12); border-color: rgba(220,53,69,.4) !important; }
details > summary { list-style: none; }
details > summary::-webkit-details-marker { display: none; }
.ab-bot-list { display: flex; flex-direction: column; gap: .25rem; }
.ab-bot-row {
  display: flex; align-items: center; gap: 1rem;
  padding: .4rem .5rem; border-bottom: 1px solid var(--border-color, #dee2e6); color: var(--body-color, #212529);
}
.ab-bot-row:last-child { border-bottom: none; }
.ab-bot-info { flex: 1; }
.ab-bot-name    { font-weight: 500; font-size: .875rem; display: block; color: var(--body-color, #212529); }
.ab-bot-company { font-size: .75rem; color: var(--secondary-color, #6c757d); }
.ab-bot-select  { width: 110px; }

/* Task #482 — Allow/Block radio groups for AI crawler rules. */
.ab-radio-group { display: inline-flex; gap: .75rem; flex-wrap: wrap; }
.ab-radio { display: inline-flex; align-items: center; gap: .35rem; cursor: pointer; font-size: .875rem; color: var(--body-color, #212529); margin: 0; }
.ab-radio input[type=radio] { margin: 0; }
.ab-default-policy {
  padding: .6rem .75rem;
  border: 1px solid var(--border-color, #dee2e6);
  border-radius: 6px;
  background: var(--secondary-bg, #f8f9fa);
}
.ab-default-policy__label { font-weight: 500; margin-bottom: .35rem; color: var(--body-color, #212529); }
[data-bs-theme=dark] .ab-default-policy { background: #1a1d21; border-color: #2d3338; }
[data-bs-theme=dark] .ab-radio { color: #e6edf3; }
.ab-bot-radio-group { min-width: 150px; justify-content: flex-end; }
</style>
