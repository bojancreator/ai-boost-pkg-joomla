<template>
  <div class="ab-card">
    <div class="ab-card-header">SEO Scraper Rules</div>
    <div class="ab-card-body">
      <p class="ab-help mb-2">
        <strong>SEO-analysis crawlers</strong> (Ahrefs, Semrush, Majestic…) scrape your site for competitor and
        backlink research — they are <strong>not</strong> search engines or AI engines. Blocking them does
        <strong>not</strong> affect your Google/Bing ranking or AI-search visibility; it only hides your site from
        competitor tools and saves a little crawl bandwidth.
        <strong>Recommended: leave all allowed</strong> unless you specifically want to hide from SEO tools.
        Tick a box (= <strong>block</strong> that bot in <code>robots.txt</code>); untick = allow.
      </p>
      <div class="ab-bulk-actions mb-3">
        <span class="ab-bulk-label">Quick set:</span>
        <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="setAllScrapers('1')">Block all</button>
        <button type="button" class="ab-btn ab-btn--sm ab-btn--ghost" @click="setAllScrapers('0')">Allow all</button>
      </div>
      <div class="row g-2 mb-3">
        <div v-for="bot in SEO_SCRAPERS" :key="bot.key" class="col-6 col-sm-4">
          <label :for="'scraper-' + bot.key" class="ab-scraper-box d-flex align-items-center gap-2 p-2 border rounded small mb-0"
                 :class="{ 'ab-scraper-blocked': s[bot.key] === '1' }">
            <input type="checkbox" class="ab-toggle__input" :id="'scraper-' + bot.key" :data-ab-field="bot.key"
              v-model="s[bot.key]" true-value="1" false-value="0" />
            <span class="lh-sm">
              <span class="fw-semibold d-block">{{ bot.label }}</span>
              <span class="text-muted" style="font-size:.7rem">{{ bot.agent }}</span>
            </span>
          </label>
        </div>
      </div>

      <label class="ab-label">Additional user-agent blocks</label>
      <textarea v-model="s.robots_custom_scrapers" data-ab-field="robots_custom_scrapers" class="ab-input font-monospace" rows="4"
        placeholder="User-agent: CustomBot&#10;Disallow: /"></textarea>
      <!-- "Free-form robots.txt rules" (robots_custom_rules) removed: it had no
           runtime consumer (only the never-instantiated RobotsTxtManager read
           it). The live free-form editor is "Custom robots.txt rules"
           (crawler_rules) on the AI Crawler Rules card. -->
    </div>
  </div>
</template>

<script>
const SEO_SCRAPERS = [
  { key: 'scraper_ahrefsbot', label: 'Ahrefs', agent: 'AhrefsBot' },
  { key: 'scraper_semrushbot', label: 'Semrush', agent: 'SemrushBot' },
  { key: 'scraper_dotbot', label: 'Dotbot', agent: 'DotBot' },
  { key: 'scraper_mj12bot', label: 'Majestic', agent: 'MJ12bot' },
  { key: 'scraper_blexbot', label: 'BLEXBot', agent: 'BLEXBot' },
  { key: 'scraper_rogerbot', label: 'Moz', agent: 'rogerbot' },
  { key: 'scraper_screamingfrog', label: 'Screaming Frog', agent: 'Screaming Frog SEO Spider' },
  { key: 'scraper_sitebulb', label: 'Sitebulb', agent: 'Sitebulb' },
  { key: 'scraper_siteauditor', label: 'SE Ranking', agent: 'SiteAuditBot' },
  { key: 'scraper_serpstatbot', label: 'Serpstat', agent: 'SerpstatBot' },
  { key: 'scraper_bytespider', label: 'Bytespider', agent: 'Bytespider' },
  { key: 'scraper_petalbot', label: 'PetalBot', agent: 'PetalBot' },
]

export default {
  name: 'ScraperRulesCard',
  props: { s: { type: Object, required: true } },
  data() { return { SEO_SCRAPERS } },
  methods: {
    // Block all (= '1') / Allow all (= '0') the listed SEO scrapers at once.
    setAllScrapers(value) {
      for (const bot of SEO_SCRAPERS) {
        this.s[bot.key] = value
      }
    },
  },
}
</script>

<style scoped>
.ab-bulk-actions { display: inline-flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.ab-bulk-label { font-weight: 500; font-size: .85rem; }
.ab-scraper-box { transition: border-color .15s, background .15s; cursor: pointer; }
.ab-scraper-blocked { background: rgba(220,53,69,.06); border-color: rgba(220,53,69,.3) !important; }
[data-bs-theme=dark] .ab-scraper-blocked { background: rgba(220,53,69,.12); border-color: rgba(220,53,69,.4) !important; }
</style>
