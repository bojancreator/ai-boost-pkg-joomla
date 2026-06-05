<template>
  <div class="mt-3">
    <div class="ab-chip-row">
      <span class="ab-chip">{{ robots.summary.line_count }} lines</span>
      <span class="ab-chip">{{ robots.size_bytes }} bytes</span>
      <span class="ab-chip">{{ robots.summary.user_agents.length }} User-agent blocks</span>
      <span class="ab-chip">{{ robots.summary.sitemaps.length }} Sitemap lines</span>
    </div>
    <div v-if="robots.issues.length" class="mt-3">
      <div v-for="issue in robots.issues" :key="issue.id" class="ab-alert" :class="issueClass(issue)">
        <strong>{{ issue.title }}</strong>
        <div>{{ issue.detail }}</div>
        <button v-if="issue.fix" type="button" class="btn btn-sm btn-outline-dark mt-2" @click="$emit('fix', issue.fix)">
          {{ fixApplied[issue.fix] ? 'Applied' : 'Fix it' }}
        </button>
      </div>
    </div>
    <div v-else class="ab-alert ab-alert--ok mt-3">No structural issues detected.</div>
  </div>
</template>

<script>
export default {
  name: 'RobotsPreviewResult',
  props: {
    robots: { type: Object, required: true },
    fixApplied: { type: Object, required: true },
  },
  emits: ['fix'],
  methods: {
    issueClass(issue) {
      const level = issue.level === 'danger' ? 'danger' : issue.level === 'warn' ? 'warn' : 'info'
      return 'ab-alert--' + level
    },
  },
}
</script>

<style scoped>
.ab-chip-row { display: flex; flex-wrap: wrap; gap: 6px; }
.ab-chip { background: #f1f3f5; color: #495057; font-size: 11.5px; padding: 3px 9px; border-radius: 10px; font-weight: 500; }
.ab-alert { padding: 9px 13px; border-radius: 6px; font-size: 12.5px; line-height: 1.5; margin-top: 8px; }
.ab-alert--info { background: #e7f1ff; border: 1px solid #b6d4fe; color: #084298; }
.ab-alert--warn { background: #fff3cd; border: 1px solid #ffe69c; color: #664d03; }
.ab-alert--ok { background: #d1e7dd; border: 1px solid #a3cfbb; color: #0a3622; }
.ab-alert--danger { background: #f8d7da; border: 1px solid #f1aeb5; color: #58151c; }
</style>
