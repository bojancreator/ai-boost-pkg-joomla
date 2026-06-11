<template>
  <div class="ab-card">
    <div class="ab-card-header">AI Crawler Rules</div>
    <div class="ab-card-body">
      <p class="ab-help mb-3">
        Controls which <strong>AI training &amp; answer bots</strong> (GPTBot, ClaudeBot, PerplexityBot…) may
        crawl your site. The rules are written into your <code>robots.txt</code>.
        <strong>On</strong> publishes the rules below; <strong>Off</strong> removes them.
        Per bot: <strong>Allow</strong> = the bot may crawl, <strong>Block</strong> = it is disallowed.
      </p>
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.ai_crawlers_enabled" data-ab-field="ai_crawlers_enabled" true-value="1" false-value="0"
          type="checkbox" class="ab-toggle__input" id="cr-crawlers-enabled">
        <label class="ab-check__label" for="cr-crawlers-enabled">Enable per-bot rules</label>
      </div>

      <template v-if="s.ai_crawlers_enabled === '1'">
        <div class="ab-bulk-actions" data-ab-field="aeo_crawler_default_policy">
          <span class="ab-bulk-label">Quick set all bots:</span>
          <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="setAllBots('allow')">Allow all</button>
          <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="setAllBots('block')">Block all</button>
        </div>
        <div class="ab-help mb-3">Fills every bot below at once — then fine-tune individual bots if needed.</div>

        <div class="ab-bot-list" data-ab-field="crawler_bot_rules">
          <BotRuleRow v-for="bot in BOTS" :key="bot.id" :bot="bot" v-model="botRules[bot.id]" />
        </div>

        <label class="ab-label mt-3">Custom AI crawler rules</label>
        <textarea v-model="s.crawler_rules" data-ab-field="crawler_rules" class="ab-input font-monospace" rows="5"
          placeholder="User-agent: CCBot&#10;Disallow: /"></textarea>
      </template>
    </div>
  </div>
</template>

<script>
import BotRuleRow from './BotRuleRow.vue'

const BOTS = [
  { id: 'gptbot', label: 'GPTBot', company: 'OpenAI' },
  { id: 'claudebot', label: 'ClaudeBot', company: 'Anthropic' },
  { id: 'perplexitybot', label: 'PerplexityBot', company: 'Perplexity' },
  { id: 'geminibot', label: 'Google-Extended', company: 'Google (Gemini)' },
  { id: 'bingbot', label: 'Bingbot', company: 'Microsoft' },
  { id: 'facebookbot', label: 'FacebookBot', company: 'Meta' },
  { id: 'applebot', label: 'Applebot-Extended', company: 'Apple' },
  { id: 'duckassistbot', label: 'DuckAssistBot', company: 'DuckDuckGo' },
  { id: 'youbot', label: 'YouBot', company: 'You.com' },
]

function normalizeBotRules(rawRules) {
  const normalized = Object.fromEntries(BOTS.map((bot) => [bot.id, '']))
  for (const key of Object.keys(rawRules)) {
    const value = String(rawRules[key] ?? '').toLowerCase().trim()
    normalized[key] = value === 'allow' || value === 'block' ? value : value === 'disallow' ? 'block' : ''
  }
  return normalized
}

export default {
  name: 'AiCrawlerRulesCard',
  components: { BotRuleRow },
  props: { s: { type: Object, required: true } },
  data() {
    let rawRules = {}
    try { rawRules = JSON.parse(this.s.crawler_bot_rules || '{}') } catch {}
    if (!this.s.aeo_crawler_default_policy) this.s.aeo_crawler_default_policy = 'allow'
    return { BOTS, botRules: normalizeBotRules(rawRules) }
  },
  watch: {
    botRules: {
      handler(value) { this.s.crawler_bot_rules = JSON.stringify(value) },
      deep: true,
    },
  },
  methods: {
    // "Allow all" / "Block all" — bulk-set every listed bot so each per-bot
    // radio reflects the choice; individual bots can still be changed after.
    setAllBots(policy) {
      for (const bot of this.BOTS) {
        this.botRules[bot.id] = policy
      }
      this.s.aeo_crawler_default_policy = policy
    },
  },
}
</script>

<style scoped>
.ab-bot-list { display: flex; flex-direction: column; gap: .25rem; }
.ab-radio-group { display: inline-flex; gap: .75rem; flex-wrap: wrap; }
.ab-radio { display: inline-flex; align-items: center; gap: .35rem; cursor: pointer; font-size: .875rem; margin: 0; }
.ab-bulk-actions { display: inline-flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.ab-bulk-label { font-weight: 500; font-size: .85rem; }
</style>
