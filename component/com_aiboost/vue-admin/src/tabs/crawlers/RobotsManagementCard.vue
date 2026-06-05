<template>
  <div class="ab-card">
    <div class="ab-card-header">robots.txt Management</div>
    <div class="ab-card-body">
      <div class="ab-check ab-toggle mb-2">
        <input v-model="s.enable_robots" data-ab-field="enable_robots" true-value="1" false-value="0"
          type="checkbox" class="ab-toggle__input" id="cr-robots">
        <label class="ab-check__label" for="cr-robots">Enable robots.txt management</label>
      </div>
      <div class="ab-check ab-toggle mb-3">
        <input v-model="s.robots_auto_sync" data-ab-field="robots_auto_sync" true-value="1" false-value="0"
          type="checkbox" class="ab-toggle__input" id="cr-robots-sync">
        <label class="ab-check__label" for="cr-robots-sync">Auto-sync physical robots.txt file</label>
      </div>

      <div class="ab-subcard">
        <div class="ab-subcard-head">
          <strong>Live robots.txt preview</strong>
          <span class="ab-card-header-actions">
            <button type="button" class="btn btn-sm btn-primary" @click="loadRobots" :disabled="robots.loading">
              {{ robots.loading ? 'Loading...' : (robots.body ? 'Refresh' : 'Preview robots.txt') }}
            </button>
          </span>
        </div>
        <div class="ab-robots-urlbar">
          <input type="text" class="ab-input font-monospace" :value="robotsUrl" readonly @focus="$event.target.select()">
          <a class="btn btn-sm btn-outline-secondary" :href="robotsUrl" target="_blank" rel="noopener">Open</a>
        </div>
        <div v-if="robots.error" class="ab-alert ab-alert--danger mt-3">{{ robots.error }}</div>
        <RobotsPreviewResult v-if="robots.body && !robots.error" :robots="robots" :fix-applied="fixApplied" @fix="applyFix" />
      </div>
    </div>
  </div>
</template>

<script>
import { makeAdminUrl } from '../../api.js'
import RobotsPreviewResult from './RobotsPreviewResult.vue'

function siteRootUrl(fileName) {
  const origin = window.location.origin
  const path = window.location.pathname.replace(/\/administrator(\/.*)?$/, '/')
  return origin + path.replace(/\/+$/, '') + '/' + fileName
}

function createRobotsState() {
  return {
    loading: false,
    error: '',
    body: '',
    size_bytes: 0,
    summary: { line_count: 0, user_agents: [], sitemaps: [] },
    issues: [],
  }
}

export default {
  name: 'RobotsManagementCard',
  components: { RobotsPreviewResult },
  props: { s: { type: Object, required: true } },
  data() { return { robots: createRobotsState(), fixApplied: {} } },
  computed: {
    robotsUrl() { return siteRootUrl('robots.txt') },
    sitemapUrl() { return siteRootUrl('sitemap.xml') },
  },
  methods: {
    async loadRobots() {
      this.robots.loading = true
      this.robots.error = ''
      this.fixApplied = {}
      try {
        const token = window.aiBoostToken
        const url = makeAdminUrl('settings.previewRobots') + (token ? '&' + token + '=1' : '')
        const response = await fetch(url, { credentials: 'same-origin' })
        this.applyRobotsPreview(await response.json())
      } catch (error) {
        this.robots.error = 'Network error: ' + (error && error.message ? error.message : error)
      } finally {
        this.robots.loading = false
      }
    },
    applyRobotsPreview(data) {
      if (!data.success) {
        this.robots.error = data.message || 'Preview failed.'
        this.robots.body = ''
        return
      }
      this.robots.body = data.body || ''
      this.robots.size_bytes = data.size_bytes || 0
      this.robots.summary = Object.assign(this.robots.summary, data.summary || {})
      this.robots.issues = data.issues || []
    },
    applyFix(fix) {
      const actions = {
        'add-sitemap': this.addSitemapFix,
        'add-user-agent-star': this.addUserAgentStarFix,
        'remove-block-all': this.removeBlockAllFix,
      }
      if (actions[fix]) actions[fix]()
      this.fixApplied = Object.assign({}, this.fixApplied, { [fix]: true })
    },
    addSitemapFix() {
      const line = 'Sitemap: ' + this.sitemapUrl
      const current = (this.s.crawler_rules || '').trim()
      if (!current.split(/\r?\n/).some((item) => item.trim().toLowerCase() === line.toLowerCase())) {
        this.s.crawler_rules = (current ? current + '\n' : '') + line + '\n'
      }
      this.s.enable_robots = '1'
    },
    addUserAgentStarFix() {
      const block = 'User-agent: *\nDisallow:'
      const current = (this.s.crawler_rules || '').trim()
      if (!/user-agent\s*:\s*\*/i.test(current)) this.s.crawler_rules = block + (current ? '\n\n' + current : '') + '\n'
      this.s.enable_robots = '1'
    },
    removeBlockAllFix() {
      const output = []
      let inStarBlock = false
      for (const line of (this.s.crawler_rules || '').split(/\r?\n/)) {
        const trimmed = line.trim()
        if (/^user-agent\s*:\s*\*/i.test(trimmed)) { inStarBlock = true; output.push(line); continue }
        if (/^user-agent\s*:/i.test(trimmed)) { inStarBlock = false; output.push(line); continue }
        output.push(inStarBlock && /^disallow\s*:\s*\/\s*$/i.test(trimmed) ? 'Disallow:' : line)
      }
      this.s.crawler_rules = output.join('\n')
      this.s.enable_robots = '1'
    },
  },
}
</script>

<style scoped>
.ab-subcard { border: 1px solid #e9ecef; border-radius: 8px; padding: 12px 14px; background: #fbfcfd; margin-top: 6px; }
.ab-subcard-head { display: flex; align-items: center; margin-bottom: 8px; }
.ab-card-header-actions { margin-left: auto; }
.ab-robots-urlbar { display: flex; gap: 6px; align-items: center; }
.ab-robots-urlbar input { flex: 1; font-size: 12.5px; }
.ab-alert { padding: 9px 13px; border-radius: 6px; font-size: 12.5px; line-height: 1.5; margin-top: 8px; }
.ab-alert--danger { background: #f8d7da; border: 1px solid #f1aeb5; color: #58151c; }
[data-bs-theme=dark] .ab-subcard { background: #1a1d21; border-color: #2d3338; }
</style>
