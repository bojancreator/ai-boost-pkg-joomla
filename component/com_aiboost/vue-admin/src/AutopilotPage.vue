<template>
  <div class="ab-autopilot-page">

    <PageHeader title="Quick Setup" subtitle="A guided setup checklist for the core AI Boost configuration.">
      <div class="ab-autopilot-score" aria-live="polite">
        <strong>{{ completedCount }}/{{ steps.length }}</strong>
        <span>complete</span>
      </div>
    </PageHeader>

    <section class="ab-autopilot-progress" aria-label="Quick Setup progress">
      <div class="ab-autopilot-progress__bar">
        <div class="ab-autopilot-progress__fill" :style="{ width: progressPercent + '%' }"></div>
      </div>
      <div class="ab-help">{{ progressPercent }}% configured</div>
    </section>

    <div class="ab-autopilot-grid">
      <article
        v-for="step in steps"
        :key="step.id"
        class="ab-section ab-autopilot-card"
        :class="{ 'ab-autopilot-card--done': step.done }"
      >
        <div class="ab-section__head ab-autopilot-card__head">
          <span class="ab-autopilot-card__icon" :class="step.icon" aria-hidden="true"></span>
          <div>
            <span class="ab-autopilot-card__title">{{ step.title }}</span>
            <span class="ab-help d-block">{{ step.summary }}</span>
          </div>
          <span class="ab-badge ms-auto" :class="step.done ? 'ab-badge--success' : 'ab-badge--warning'">
            {{ step.done ? 'Configured' : 'Needs setup' }}
          </span>
        </div>

        <div class="ab-section__body">
          <ul class="ab-autopilot-checks">
            <li v-for="check in step.checks" :key="check.label" :class="{ done: check.done }">
              <span :class="check.done ? 'icon-check' : 'icon-warning'" aria-hidden="true"></span>
              <span>{{ check.label }}</span>
            </li>
          </ul>
        </div>

        <div class="ab-autopilot-card__footer">
          <router-link class="ab-btn ab-btn--sm ab-btn--primary" :to="step.to">
            <span class="icon-pencil" aria-hidden="true"></span>{{ step.cta }}
          </router-link>
        </div>
      </article>
    </div>

    <section class="ab-alert ab-alert--info ab-autopilot-health">
      <div>
        <strong>Health is the feedback center.</strong>
        <span class="ab-help d-inline"> Use it after setup to verify output and review the Error Log.</span>
      </div>
      <router-link class="ab-btn ab-btn--sm ab-btn--ghost" to="/health">
        <span class="icon-heart" aria-hidden="true"></span>Open Health
      </router-link>
    </section>

  </div>
</template>

<script>
import { computed } from 'vue'
import PageHeader from './components/PageHeader.vue'

function configured(value) {
  if (value === true || value === 1) return true
  const normalized = String(value ?? '').trim().toLowerCase()
  return normalized !== '' && normalized !== '0' && normalized !== 'false' && normalized !== 'none'
}

function settingsTo(tab, field) {
  return { path: '/settings', query: field ? { tab, field } : { tab } }
}

export default {
  name: 'AutopilotPage',

  components: { PageHeader },

  setup() {
    const settings = window.aiBoostSettings || {}

    const steps = computed(() => {
      const siteName = configured(settings.org_name)
      const siteUrl = configured(settings.org_url)
      const siteLogo = configured(settings.org_logo)
      const schemaEnabled = configured(settings.enable_schema)
      const schemaType = configured(settings.schema_type)
      const sitemapEnabled = configured(settings.enable_sitemap)
      const socialName = configured(settings.site_name)
      const socialImage = configured(settings.default_og_image)
      const twitterEnabled = configured(settings.enable_twitter_cards)

      return [
        {
          id: 'site-identity',
          title: 'Site Identity',
          summary: 'Name, URL, and logo used across structured data and previews.',
          icon: 'icon-users',
          cta: 'Edit identity',
          to: settingsTo('org', siteName ? 'org_logo' : 'org_name'),
          checks: [
            { label: 'Organization name', done: siteName },
            { label: 'Organization URL', done: siteUrl },
            { label: 'Organization logo', done: siteLogo },
          ],
        },
        {
          id: 'schema',
          title: 'Schema.org Core',
          summary: 'Structured data foundation for the website or business.',
          icon: 'icon-code',
          cta: 'Edit schema',
          to: settingsTo('schema', schemaEnabled ? 'schema_type' : 'enable_schema'),
          checks: [
            { label: 'Schema output enabled', done: schemaEnabled },
            { label: 'Schema type selected', done: schemaType },
          ],
        },
        {
          id: 'sitemap',
          title: 'Sitemap',
          summary: 'XML sitemap output for discovery and indexing.',
          icon: 'icon-list',
          cta: 'Edit sitemap',
          to: settingsTo('sitemap', 'enable_sitemap'),
          checks: [
            { label: 'Sitemap enabled', done: sitemapEnabled },
          ],
        },
        {
          id: 'social',
          title: 'Social Meta',
          summary: 'Default preview data for shared pages and articles.',
          icon: 'icon-share',
          cta: 'Edit social meta',
          to: settingsTo('social', socialName ? 'default_og_image' : 'site_name'),
          checks: [
            { label: 'OpenGraph site name', done: socialName },
            { label: 'Default share image', done: socialImage },
            { label: 'Twitter Cards enabled', done: twitterEnabled },
          ],
        },
      ].map((step) => ({
        ...step,
        done: step.checks.every((check) => check.done),
      }))
    })

    const completedCount = computed(() => steps.value.filter((step) => step.done).length)
    const progressPercent = computed(() => Math.round((completedCount.value / steps.value.length) * 100))

    return { steps, completedCount, progressPercent }
  },
}
</script>

<style scoped>
.ab-autopilot-page { }

.ab-autopilot-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 1rem; margin-bottom: 1rem;
}

.ab-autopilot-score {
  min-width: 118px; padding: .75rem .9rem;
  border: 1px solid var(--ab-border); border-radius: var(--ab-radius);
  text-align: right; background: var(--ab-surface);
}
.ab-autopilot-score strong { display: block; font-size: 1.35rem; line-height: 1.1; }
.ab-autopilot-score span   { color: var(--ab-text-muted); font-size: .82rem; }

.ab-autopilot-progress {
  display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem;
}
.ab-autopilot-progress__bar {
  flex: 1; height: 8px; overflow: hidden; border-radius: 999px;
  background: var(--ab-surface-raised);
}
.ab-autopilot-progress__fill {
  height: 100%; border-radius: inherit;
  background: var(--ab-primary); transition: width .18s ease;
}

.ab-autopilot-grid {
  display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem;
}
.ab-autopilot-card--done { border-color: color-mix(in srgb, var(--ab-success) 34%, var(--ab-border)); }

.ab-autopilot-card__head {
  display: flex; align-items: flex-start; gap: .75rem;
}
.ab-autopilot-card__title { font-weight: 600; font-size: .95rem; display: block; margin-bottom: .15rem; }
.ab-autopilot-card__icon {
  width: 34px; height: 34px; display: inline-flex; align-items: center;
  justify-content: center; flex: 0 0 34px; border-radius: var(--ab-radius);
  background: color-mix(in srgb, var(--ab-primary) 12%, transparent);
  color: var(--ab-primary);
}

.ab-autopilot-checks { display: grid; gap: .45rem; margin: 0; padding: 0; list-style: none; }
.ab-autopilot-checks li { display: flex; align-items: center; gap: .45rem; color: var(--ab-text-muted); font-size: .92rem; }
.ab-autopilot-checks li.done { color: var(--ab-text); }
.ab-autopilot-checks .icon-check   { color: var(--ab-success); }
.ab-autopilot-checks .icon-warning { color: var(--ab-warning); }

.ab-autopilot-card__footer { padding: var(--ab-space-3) var(--ab-space-4); }
.ab-autopilot-health {
  display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-top: 1rem;
}

@media (max-width: 900px)  { .ab-autopilot-grid { grid-template-columns: 1fr; } }
@media (max-width: 640px) {
  .ab-autopilot-header,
  .ab-autopilot-health,
  .ab-autopilot-progress { align-items: stretch; flex-direction: column; }
  .ab-autopilot-score { text-align: left; }
}
</style>
