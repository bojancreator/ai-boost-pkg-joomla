<template>
  <div class="ab-help-page">

    <!-- Quick Start -->
    <div class="ab-card mb-4">
      <div class="ab-card__header">
        <span class="icon-rocket" aria-hidden="true"></span>
        <h2 class="fs-5 mb-0">Quick Start</h2>
      </div>
      <div class="ab-card__body">
        <p class="text-muted mb-3">New to AI Boost? Follow these steps to get up and running in minutes.</p>
        <ol class="ab-quickstart-list">
          <li>
            <strong>Install the package</strong> — Install <code>pkg_aiboost</code> via Joomla Extension Manager. This installs the component and all 6 plugins in one step.
          </li>
          <li>
            <strong>Enable the plugins</strong> — Go to <strong>Extensions → Plugins</strong>, search for "AI Boost", and enable the plugins you need. Start with <em>aiboost_schema</em> and <em>aiboost_social</em>.
          </li>
          <li>
            <strong>Configure your Organization</strong> — In <a :href="urls.settings">Settings → Organization</a>, fill in your site name, URL, logo, and contact details. This data is used across all schema types.
          </li>
          <li>
            <strong>Set your site type</strong> — In <em>Settings → General</em>, select the site type that best describes your business (Restaurant, Hotel, Doctor, etc.) to auto-enable the right schema.
          </li>
          <li>
            <strong>Run a Health Check</strong> — Visit the <a :href="urls.health">Health tab</a> to see a score and list of issues to resolve.
          </li>
          <li>
            <strong>Run the Analyzers</strong> — Use the <a :href="urls.analyzer">Analyzers tab</a> to check SEO, JSON-LD, and AI Visibility scores for your site.
          </li>
        </ol>
      </div>
    </div>

    <!-- Video Walkthrough -->
    <div class="ab-card mb-4">
      <div class="ab-card__header">
        <span class="icon-play" aria-hidden="true"></span>
        <h2 class="fs-5 mb-0">Video Walkthrough</h2>
      </div>
      <div class="ab-card__body">
        <p class="text-muted mb-3">Watch the complete setup walkthrough for AI Boost for Joomla.</p>

        <!-- YouTube embed — replace videoId with the published tutorial video ID -->
        <div v-if="videoId" class="ab-video-wrap ratio ratio-16x9">
          <iframe
            :src="'https://www.youtube.com/embed/' + videoId + '?rel=0&modestbranding=1'"
            title="AI Boost for Joomla — Setup Walkthrough"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            loading="lazy"
          ></iframe>
        </div>

        <!-- Shown when no video is published yet -->
        <div v-else class="ab-video-placeholder d-flex flex-column align-items-center justify-content-center gap-3 text-center p-5">
          <a
            href="https://www.youtube.com/@aiboostnow"
            target="_blank"
            rel="noopener"
            class="ab-yt-btn d-inline-flex align-items-center gap-2 ab-btn ab-btn--danger"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
              <path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2C0 8.1 0 12 0 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1c.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.6 15.6V8.4l6.3 3.6-6.3 3.6z"/>
            </svg>
            Subscribe on YouTube
          </a>
          <p class="text-muted small mb-0">
            Video walkthrough is coming soon. Subscribe to the <a href="https://www.youtube.com/@aiboostnow" target="_blank" rel="noopener">AI Boost YouTube channel</a> to be notified when it's published.
          </p>
          <a href="https://aiboostnow.com/docs/getting-started" target="_blank" rel="noopener" class="ab-btn ab-btn--ghost ab-btn--sm">
            <span class="icon-book me-1" aria-hidden="true"></span> Read the Getting Started Guide instead
          </a>
        </div>
      </div>
    </div>

    <!-- Plugin Documentation -->
    <div class="ab-card mb-4">
      <div class="ab-card__header">
        <span class="icon-book" aria-hidden="true"></span>
        <h2 class="fs-5 mb-0">Plugin Documentation</h2>
      </div>
      <div class="ab-card__body" style="padding:0">
        <div class="ab-accordion">
          <div v-for="plugin in plugins" :key="plugin.id" class="ab-accordion__item">
            <button
              class="ab-accordion__trigger"
              type="button"
              @click="toggleAccordion(plugin.id)"
              :aria-expanded="openPlugin === plugin.id"
            >
              <span :class="[plugin.icon, 'me-2']" aria-hidden="true"></span>
              <strong>{{ plugin.name }}</strong>
              <span class="ab-badge ms-2">{{ plugin.element }}</span>
              <span class="ab-accordion__arrow ms-auto" aria-hidden="true">▾</span>
            </button>
            <div v-show="openPlugin === plugin.id" class="ab-accordion__body">
              <p class="mb-2">{{ plugin.description }}</p>
              <div v-if="plugin.features.length" class="mb-3">
                <strong class="d-block mb-1 small text-muted text-uppercase" style="letter-spacing:.05em">Key Features</strong>
                <ul class="mb-0 ps-3">
                  <li v-for="f in plugin.features" :key="f" class="small">{{ f }}</li>
                </ul>
              </div>
              <div v-if="plugin.notes" class="ab-alert ab-alert--info mb-2 small" role="note">
                <span class="icon-info-circle me-1" aria-hidden="true"></span>{{ plugin.notes }}
              </div>
              <a :href="plugin.docsUrl" target="_blank" rel="noopener" class="ab-btn ab-btn--ghost ab-btn--sm">
                <span class="icon-external-link-square me-1" aria-hidden="true"></span> Read full docs
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Support & Resources -->
    <div class="ab-card mb-4">
      <div class="ab-card__header">
        <span class="icon-life-ring" aria-hidden="true"></span>
        <h2 class="fs-5 mb-0">Support &amp; Resources</h2>
      </div>
      <div class="ab-card__body">
        <div class="row g-3">
          <div v-for="link in resources" :key="link.url" class="col-sm-6 col-md-4">
            <a :href="link.url" target="_blank" rel="noopener"
               class="ab-card d-flex align-items-start gap-2 p-3 text-decoration-none ab-resource-card h-100">
              <span :class="[link.icon, 'fs-5 mt-1 flex-shrink-0']"
                    style="color:var(--ab-primary, #2a6496)" aria-hidden="true"></span>
              <div>
                <div class="fw-semibold">{{ link.title }}</div>
                <div class="text-muted small">{{ link.desc }}</div>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
export default {
  name: 'HelpPage',

  data() {
    return {
      openPlugin: null,

      // YouTube video ID for the walkthrough embed.
      // Set to a non-empty string when the tutorial video is published.
      // e.g. videoId: 'dQw4w9WgXcQ'
      videoId: '',

      urls: {
        settings: 'index.php?option=com_aiboost&view=settings',
        health:   'index.php?option=com_aiboost&view=health',
        analyzer: 'index.php?option=com_aiboost&view=analyzer',
      },

      plugins: [
        {
          id:          'schema',
          name:        'AI Boost Schema',
          element:     'aiboost_schema',
          icon:        'icon-code',
          description: 'Generates Schema.org structured data (JSON-LD) for your Joomla site. Supports Organization, LocalBusiness, WebSite, Article, BreadcrumbList, FAQPage, Event, Hotel, and more.',
          features: [
            'Auto-selects schema type based on site type setting',
            'FAQPage schema from Joomla article content',
            'BreadcrumbList for all category/article paths',
            'Organization and LocalBusiness with full address, hours, geo',
            'Custom event schema with start/end dates',
          ],
          notes:   'Enable this plugin first — it provides the foundation for all structured data on your site.',
          docsUrl: 'https://aiboostnow.com/docs/schema',
        },
        {
          id:          'sitemap',
          name:        'AI Boost Sitemap',
          element:     'aiboost_sitemap',
          icon:        'icon-map',
          description: 'Generates an XML sitemap and optionally hreflang tags for multilingual sites. The sitemap is accessible at /sitemap.xml.',
          features: [
            'Auto-generated /sitemap.xml with all articles, categories, and custom pages',
            'Priority and changefreq per content type',
            'hreflang support for multilingual Joomla setups',
            'Sitemap ping on new content (optional)',
          ],
          notes:   'Submit your sitemap URL to Google Search Console and Bing Webmaster Tools after enabling.',
          docsUrl: 'https://aiboostnow.com/docs/sitemap',
        },
        {
          id:          'social',
          name:        'AI Boost Social',
          element:     'aiboost_social',
          icon:        'icon-share',
          description: 'Adds OpenGraph and Twitter Card meta tags to every page. Controls how your content appears when shared on Facebook, LinkedIn, Twitter/X, WhatsApp, and other platforms.',
          features: [
            'og:title, og:description, og:image, og:type for every page',
            'Twitter Card (summary_large_image) support',
            'Custom OG image per article via custom fields',
            'Fallback to default OG image from Settings',
          ],
          notes:   null,
          docsUrl: 'https://aiboostnow.com/docs/social',
        },
        {
          id:          'analytics',
          name:        'AI Boost Analytics',
          element:     'aiboost_analytics',
          icon:        'icon-bar-chart',
          description: 'Integrates Google Analytics 4, Google Tag Manager, Google Search Console verification, and Meta Pixel without editing template files.',
          features: [
            'GA4 tracking code injection',
            'GTM container script (head + body noscript)',
            'Google Search Console HTML meta verification',
            'Meta Pixel / Facebook Pixel',
            'Optional Cookiebot integration flag',
          ],
          notes:   null,
          docsUrl: 'https://aiboostnow.com/docs/analytics',
        },
        {
          id:          'aeo',
          name:        'AI Boost AEO',
          element:     'aiboost_aeo',
          icon:        'icon-magic',
          description: 'Answer Engine Optimization features for AI discoverability. Generates llms.txt, serves the IndexNow key file, and adds AI signal meta tags.',
          features: [
            'Auto-generates /llms.txt with site description and content inventory',
            'Serves IndexNow API key file at /<key>.txt',
            'AI crawler directives in robots.txt (GPTBot, ClaudeBot, PerplexityBot, etc.)',
            'ai-content-type and ai-instructions meta tags',
          ],
          notes:   'This plugin handles the most critical signals for AI search engine discoverability.',
          docsUrl: 'https://aiboostnow.com/docs/aeo',
        },
        {
          id:          'core',
          name:        'AI Boost Core',
          element:     'aiboost_core',
          icon:        'icon-bolt',
          description: 'Manages canonical URLs to prevent duplicate content penalties and controls robots.txt generation.',
          features: [
            'Canonical URL injection on all pages',
            'Self-referencing canonicals (prevents Joomla duplicate issues)',
            'robots.txt management — edit via Settings',
            'Custom robots meta per article (optional)',
          ],
          notes:   null,
          docsUrl: 'https://aiboostnow.com/docs/core',
        },
      ],

      resources: [
        {
          title: 'Full Documentation',
          desc:  'Complete reference for every setting and plugin.',
          url:   'https://aiboostnow.com/docs',
          icon:  'icon-book',
        },
        {
          title: 'Getting Started Guide',
          desc:  'Step-by-step guide for new installations.',
          url:   'https://aiboostnow.com/docs/getting-started',
          icon:  'icon-play-circle',
        },
        {
          title: 'Schema.org Reference',
          desc:  'Official Schema.org type and property reference.',
          url:   'https://schema.org/',
          icon:  'icon-external-link-square',
        },
        {
          title: 'Joomla Extensions Directory',
          desc:  'AI Boost listing on JED.',
          url:   'https://extensions.joomla.org/',
          icon:  'icon-joomla',
        },
        {
          title: 'Support Forum',
          desc:  'Community help and bug reports.',
          url:   'https://aiboostnow.com/support',
          icon:  'icon-comments',
        },
        {
          title: 'Changelog',
          desc:  'What\'s new in each version.',
          url:   'https://aiboostnow.com/changelog',
          icon:  'icon-list',
        },
      ],
    }
  },

  methods: {
    toggleAccordion(id) {
      this.openPlugin = this.openPlugin === id ? null : id
    },
  },
}
</script>

<style scoped>
/* Quick Start ordered list with custom counter circles */
.ab-quickstart-list {
  counter-reset: qs;
  list-style: none;
  padding-left: 0;
}
.ab-quickstart-list > li {
  counter-increment: qs;
  display: flex;
  align-items: flex-start;
  gap: .75rem;
  padding: .6rem 0;
  border-bottom: 1px solid var(--ab-border, #dee2e6);
}
.ab-quickstart-list > li:last-child { border-bottom: none; }
.ab-quickstart-list > li::before {
  content: counter(qs);
  flex-shrink: 0;
  width: 1.75rem;
  height: 1.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  background: var(--ab-primary, #2a6496);
  color: #fff;
  font-size: .8rem;
  font-weight: 700;
  margin-top: .1rem;
}

/* Video placeholder */
.ab-video-placeholder {
  border: 2px dashed var(--ab-border, #dee2e6);
  border-radius: var(--ab-radius-md, .5rem);
}

/* Accordion */
.ab-accordion__item {
  border-bottom: 1px solid var(--ab-border, #dee2e6);
}
.ab-accordion__item:last-child {
  border-bottom: none;
}
.ab-accordion__trigger {
  display: flex;
  align-items: center;
  width: 100%;
  padding: .65rem 1rem;
  background: transparent;
  border: none;
  cursor: pointer;
  font-size: .9375rem;
  color: var(--ab-text, inherit);
  text-align: left;
  gap: 0;
}
.ab-accordion__trigger:hover {
  background: var(--ab-bg-muted, rgba(0,0,0,.04));
}
.ab-accordion__trigger[aria-expanded="true"] {
  background: var(--ab-primary-soft, rgba(13,110,253,.06));
}
.ab-accordion__arrow {
  transition: transform .2s;
  margin-left: auto;
  font-size: .85rem;
  padding-left: .5rem;
}
.ab-accordion__trigger[aria-expanded="true"] .ab-accordion__arrow {
  transform: rotate(180deg);
}
.ab-accordion__body {
  padding: .65rem 1rem 1rem 1rem;
  border-top: 1px solid var(--ab-border, #dee2e6);
}

/* Resource cards */
.ab-resource-card {
  color: inherit;
  transition: border-color .15s, box-shadow .15s;
}
.ab-resource-card:hover {
  border-color: var(--ab-primary, #2a6496) !important;
  box-shadow: 0 0 0 .15rem rgba(42, 100, 150, .12);
}
</style>
