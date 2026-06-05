<template>
  <div class="ab-crawlers-robots-tab">
    <RobotsManagementCard :s="s" />
    <ScraperRulesCard :s="s" />
    <AiCrawlerRulesCard :s="s" />
  </div>
</template>

<script>
import RobotsManagementCard from './crawlers/RobotsManagementCard.vue'
import ScraperRulesCard from './crawlers/ScraperRulesCard.vue'
import AiCrawlerRulesCard from './crawlers/AiCrawlerRulesCard.vue'

export default {
  name: 'CrawlersRobotsTab',
  components: { RobotsManagementCard, ScraperRulesCard, AiCrawlerRulesCard },
  props: { s: { type: Object, required: true } },
}
</script>

<style scoped>
.ab-crawlers-robots-tab { max-width: 860px; }
</style>
