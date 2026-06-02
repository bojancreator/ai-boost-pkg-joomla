export interface FeatureDetail {
  icon: string
  title: string
  desc: string
}

export interface CompareRow {
  feature: string
  free: boolean
  pro: boolean
}

export interface FaqItem {
  q: string
  a: string
}

export interface MockupPanel {
  icon: string
  label: string
  subLabel: string
  content: string
  type?: 'code' | 'ping'
  pingRows?: { engine: string; status: string; ms: string }[]
}

export interface SocialProof {
  emoji: string
  quote: string
  attribution: string
}

export interface PluginDef {
  slug: string
  name: string
  shortName: string
  icon: string
  tagline: string
  price: string
  priceNote: string
  desc: string
  status: 'live' | 'coming-soon'
  buyUrl: string
  heroHeadline?: string
  heroSub?: string
  features?: string[]
  featuresTitle?: string
  featuresSub?: string
  featureDetails?: FeatureDetail[]
  compareRows?: CompareRow[]
  pricingFeatures?: string[]
  faqs?: FaqItem[]
  socialProof?: SocialProof
  mockupsTitle?: string
  mockupsSub?: string
  mockups?: MockupPanel[]
  comingSoonTeaser?: string[]
}

const BUY_BASE = 'https://aiboost.lemonsqueezy.com/checkout/buy'

export const pluginsData: PluginDef[] = [
  {
    slug:       'aeo-ai-signals',
    name:       'AI Boost AEO',
    shortName:  'AEO & AI Signals',
    icon:       '🤖',
    tagline:    'llms.txt · IndexNow · AI signals',
    price:      '€20',
    priceNote:  '/year',
    desc:       'Make your Joomla site readable by ChatGPT, Perplexity, and Bing Copilot. Generates llms.txt, fires IndexNow on every publish, and injects AI-readable meta signals.',
    status:     'live',
    buyUrl:     `${BUY_BASE}/aiboost-aeo`,
    heroHeadline: 'Be the First Result AI Engines Recommend',
    heroSub:    'AI Boost AEO turns your Joomla site into an AI-ready source — indexed faster, understood better, and cited more.',
    features: [
      'llms.txt & llms-full.txt endpoints auto-generated',
      'IndexNow: instant ping to Bing, Yandex, Seznam on publish',
      'Speakable JSON-LD marks key content for AI assistants',
      'ai:description meta tag on every article',
      '25+ AI crawler rules injected into robots.txt',
      'Custom AI instructions field per article',
    ],
    featuresTitle: 'Everything AI engines need to recommend you',
    featuresSub:   'Six powerful features that make your Joomla content machine-readable — and citation-worthy.',
    featureDetails: [
      { icon: '📄', title: 'llms.txt & llms-full.txt', desc: 'Auto-generated endpoints list your site structure and full Markdown article export so ChatGPT, Perplexity, and Claude can understand your content.' },
      { icon: '⚡', title: 'IndexNow Auto-Ping', desc: 'Every time you publish or edit an article, AI Boost fires an IndexNow ping to Bing, Yandex, and Seznam — your content gets indexed in minutes, not days.' },
      { icon: '🗣️', title: 'Speakable JSON-LD', desc: 'Marks the key paragraphs on each article so Google Assistant and smart speakers read the right part of your page aloud.' },
      { icon: '🏷️', title: 'ai:description meta tag', desc: 'A dedicated meta tag that AI crawlers use as the authoritative summary — separate from your SEO description, tuned for AI retrieval.' },
      { icon: '🤖', title: '25+ AI Crawler Rules', desc: 'Inject a curated robots.txt allow-list for GPTBot, PerplexityBot, Anthropic, and 20+ other AI crawlers — one toggle, instantly configured.' },
      { icon: '✍️', title: 'Per-Article AI Instructions', desc: 'Custom field on every article lets you write a specific instruction for AI engines: "This page supersedes X" or "Summarise as bullet points".' },
    ],
    compareRows: [
      { feature: 'llms.txt endpoint',            free: true,  pro: true  },
      { feature: 'robots.txt AI crawler rules',  free: true,  pro: true  },
      { feature: 'ai:description meta tag',      free: true,  pro: true  },
      { feature: 'llms-full.txt (full export)',  free: false, pro: true  },
      { feature: 'IndexNow auto-ping',           free: false, pro: true  },
      { feature: 'Speakable JSON-LD',            free: false, pro: true  },
      { feature: 'Custom AI instructions field', free: false, pro: true  },
      { feature: 'Priority indexing signals',    free: false, pro: true  },
    ],
    pricingFeatures: [
      'All 6 Pro features unlocked',
      'llms-full.txt + IndexNow auto-ping',
      'Speakable JSON-LD + ai:description',
      '12 months of updates & support',
      'Plugin keeps working after expiry',
    ],
    socialProof: {
      emoji: '🤖',
      quote: 'AI Boost AEO is the only Joomla plugin that generates a proper llms.txt and fires IndexNow in the same install.',
      attribution: '— Early adopter, Joomla 6 site · myjoomlasite.com',
    },
    faqs: [
      { q: 'What exactly is llms.txt?', a: 'llms.txt is a plain-text file at the root of your site that tells AI language models what your site is about, what pages exist, and how to summarise your content. It\'s like robots.txt — but for AI engines instead of Google.' },
      { q: 'Does IndexNow work with Joomla 4, 5, and 6?', a: 'Yes. AI Boost AEO supports Joomla 4.0 through 6.x with PHP 8.1 through 8.5. IndexNow pings fire automatically on onExtensionAfterSave.' },
      { q: 'What\'s the difference between Free and Pro?', a: 'Free gives you llms.txt, basic robots.txt AI rules, and the ai:description meta tag. Pro unlocks llms-full.txt, IndexNow auto-ping, Speakable JSON-LD, and the per-article custom instruction field.' },
      { q: 'Will this help me appear in ChatGPT answers?', a: 'AI Boost AEO makes your content machine-readable and signals your authority to AI crawlers. That significantly improves your chances of being cited — though no plugin can guarantee a specific AI response.' },
      { q: 'Is a license required to keep the plugin working?', a: 'No. If your license expires, the plugin keeps running. You only need to renew if you want continued updates and support.' },
    ],
    mockupsTitle: 'See what it generates',
    mockupsSub:   'One click in Joomla admin — AI engines see all of this on your site instantly.',
    mockups: [
      {
        icon: '📄', label: '/llms.txt', subLabel: 'auto-generated · updates on publish', type: 'code',
        content: `# AI Boost for Joomla — AI Manifest
# aiboostnow.com

> AI Boost generates Schema.org, llms.txt,
> IndexNow and AI signals for Joomla sites.

## Docs
- Getting Started: /docs/getting-started
- Schema.org Guide: /docs/schema
- llms.txt Setup:  /docs/aeo

## Articles
- /blog/what-is-llms-txt
- /blog/indexnow-joomla
- /blog/schema-org-guide`,
      },
      {
        icon: '🤖', label: '/robots.txt', subLabel: '25+ AI crawlers allowed', type: 'code',
        content: `# AI crawler allow-list (AI Boost AEO)
User-agent: GPTBot
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: anthropic-ai
Allow: /

User-agent: Googlebot
Allow: /`,
      },
      {
        icon: '🗣️', label: 'Speakable JSON-LD', subLabel: 'injected into <head>', type: 'code',
        content: `{
  "@context": "https://schema.org",
  "@type": "Article",
  "speakable": {
    "@type": "SpeakableSpecification",
    "cssSelector": [
      ".article-intro",
      "h1",
      ".article-summary"
    ]
  },
  "url": "https://example.com/article"
}`,
      },
      {
        icon: '⚡', label: 'IndexNow auto-ping', subLabel: 'fired on publish', type: 'ping',
        content: '',
        pingRows: [
          { engine: 'Bing',   status: '200 OK', ms: '142ms' },
          { engine: 'Yandex', status: '200 OK', ms: '189ms' },
          { engine: 'Seznam', status: '200 OK', ms: '211ms' },
        ],
      },
    ],
    comingSoonTeaser: [],
  },

  // ─── AI Boost Schema ──────────────────────────────────────────────────────
  {
    slug:       'schema',
    name:       'AI Boost Schema',
    shortName:  'Schema.org',
    icon:       '🧠',
    tagline:    'Schema.org JSON-LD',
    price:      '€15',
    priceNote:  '/year',
    desc:       'Schema.org JSON-LD on every page: Organization, LocalBusiness, Article, FAQ, Speakable and 20+ more types. 13 site-type presets fill the right fields automatically.',
    status:     'live',
    buyUrl:     `${BUY_BASE}/aiboost-schema`,
    heroHeadline: 'Rich Results Start With the Right Schema',
    heroSub:    'AI Boost Schema generates perfect Schema.org JSON-LD for every Joomla page — so Google shows rich results and AI engines understand exactly what your site is about.',
    featuresTitle: 'Every Schema.org type your site needs',
    featuresSub:   'From a simple blog to a multi-location restaurant chain — the right structured data, generated automatically.',
    featureDetails: [
      { icon: '🏢', title: 'Organization & LocalBusiness', desc: 'Auto-populates your business name, logo, address, phone, and opening hours into the correct Schema.org types. One save — works everywhere.' },
      { icon: '📝', title: 'Article & BlogPosting', desc: 'Every Joomla article gets a complete Article or BlogPosting schema with author, datePublished, dateModified, headline, and image — exactly what Google needs for news rich results.' },
      { icon: '❓', title: 'FAQ & HowTo Schemas', desc: 'Add FAQ items once in the admin and AI Boost generates proper FAQPage JSON-LD — eligible for Google\'s FAQ rich result panels that dominate the SERPs.' },
      { icon: '🎭', title: 'Event Schema', desc: 'Concert? Workshop? Webinar? Event schemas with startDate, endDate, location, performer, and offers — so your events appear directly in Google Search.' },
      { icon: '🛍️', title: 'Product & Review', desc: 'Product pages get price, availability, brand, and aggregate rating markup — exactly what triggers star-rating rich results in Google Shopping.' },
      { icon: '🏨', title: '13 Site-Type Presets', desc: 'Choose Restaurant, Hotel, Law Firm, Medical, Gym, or 8 other presets and the right fields are pre-filled automatically. Business Hours widget included.' },
    ],
    compareRows: [
      { feature: 'Organization schema',                 free: true,  pro: true  },
      { feature: 'Article & BlogPosting schema',        free: true,  pro: true  },
      { feature: 'BreadcrumbList schema',               free: true,  pro: true  },
      { feature: 'LocalBusiness schema',                free: false, pro: true  },
      { feature: 'FAQ & HowTo schema',                  free: false, pro: true  },
      { feature: 'Event & Product schema',              free: false, pro: true  },
      { feature: '13 site-type presets',                free: false, pro: true  },
      { feature: 'Business Hours (OpeningHoursSpec)',   free: false, pro: true  },
      { feature: 'SameAs / social profile links',       free: false, pro: true  },
      { feature: 'Review & AggregateRating',            free: false, pro: true  },
    ],
    pricingFeatures: [
      'All 20+ Schema.org types unlocked',
      'LocalBusiness + 13 site-type presets',
      'FAQ, Event, Product, Review schemas',
      '12 months of updates & support',
      'Plugin keeps working after expiry',
    ],
    socialProof: {
      emoji: '🧠',
      quote: 'We enabled AI Boost Schema on our restaurant site and had a rich result for our menu FAQ within 48 hours. The site-type preset did most of the work.',
      attribution: '— Joomla site owner, restaurant chain, 3 locations',
    },
    faqs: [
      { q: 'What is Schema.org and why does it matter?', a: 'Schema.org is a structured data vocabulary that tells search engines and AI tools exactly what your content means — not just what it says. Proper Schema.org markup unlocks Google rich results (star ratings, FAQ panels, event cards) and helps AI engines like ChatGPT cite you accurately.' },
      { q: 'Will it conflict with other SEO plugins?', a: 'No. AI Boost Schema outputs JSON-LD in a separate <script> block, which is the cleanest possible method. It does not modify your meta tags or interfere with other plugins.' },
      { q: 'Do I need to configure every page manually?', a: 'No. Site-type presets fill the right fields automatically for your business type. Article schemas are generated on every Joomla article with zero per-article setup required.' },
      { q: 'Does it support Joomla 4, 5, and 6?', a: 'Yes. AI Boost Schema is tested on Joomla 4.0 through 6.x with PHP 8.1 through 8.5.' },
      { q: 'What\'s included in the Free tier?', a: 'Free gives you Organization schema, Article/BlogPosting schema, and BreadcrumbList — the three types Google cares about most for basic crawling. Pro unlocks LocalBusiness, FAQ, Event, Product, all 13 site-type presets, and Business Hours.' },
      { q: 'Is a license required to keep the plugin working?', a: 'No. If your license expires, the plugin continues working. Renew to keep receiving updates and support.' },
    ],
    mockupsTitle: 'See the JSON-LD it generates',
    mockupsSub:   'Clean, valid Schema.org markup injected into your Joomla pages — no template edits required.',
    mockups: [
      {
        icon: '🏢', label: 'LocalBusiness schema', subLabel: 'injected into <head>', type: 'code',
        content: `{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Acme Coffee Roasters",
  "url": "https://example.com",
  "telephone": "+1-555-0100",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 Main Street",
    "addressLocality": "Berlin",
    "postalCode": "10115",
    "addressCountry": "DE"
  },
  "openingHoursSpecification": [
    { "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday","Tuesday",
        "Wednesday","Thursday","Friday"],
      "opens": "08:00", "closes": "18:00" }
  ]
}`,
      },
      {
        icon: '❓', label: 'FAQPage schema', subLabel: 'Google FAQ rich result eligible', type: 'code',
        content: `{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Do you offer free shipping?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes, free shipping on all orders
          over €50 within the EU."
      }
    },
    {
      "@type": "Question",
      "name": "What is your return policy?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "30-day returns, no questions asked."
      }
    }
  ]
}`,
      },
      {
        icon: '📝', label: 'Article schema', subLabel: 'on every Joomla article', type: 'code',
        content: `{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "10 Ways to Improve Joomla SEO",
  "author": {
    "@type": "Person",
    "name": "Jane Smith"
  },
  "datePublished": "2026-05-01",
  "dateModified": "2026-05-14",
  "image": "https://example.com/img/seo.jpg",
  "publisher": {
    "@type": "Organization",
    "name": "My Joomla Site",
    "logo": {
      "@type": "ImageObject",
      "url": "https://example.com/logo.png"
    }
  }
}`,
      },
      {
        icon: '🎭', label: 'Event schema', subLabel: 'appears in Google event cards', type: 'code',
        content: `{
  "@context": "https://schema.org",
  "@type": "Event",
  "name": "Joomla Day Europe 2026",
  "startDate": "2026-09-15T09:00",
  "endDate": "2026-09-16T18:00",
  "eventStatus": "EventScheduled",
  "eventAttendanceMode":
    "OfflineEventAttendanceMode",
  "location": {
    "@type": "Place",
    "name": "Berlin Congress Center",
    "address": "Berlin, Germany"
  },
  "offers": {
    "@type": "Offer",
    "price": "49",
    "priceCurrency": "EUR",
    "availability": "InStock"
  }
}`,
      },
    ],
    comingSoonTeaser: [
      '20+ Schema.org types including LocalBusiness, Article, FAQ, Event, and Product',
      '13 site-type presets (Restaurant, Hotel, Medical, Law, Gym…) fill fields automatically',
      'Business Hours widget generates proper OpeningHoursSpecification in one click',
    ],
  },

  // ─── AI Boost OpenGraph ───────────────────────────────────────────────────
  {
    slug:       'opengraph',
    name:       'AI Boost OpenGraph',
    shortName:  'OpenGraph',
    icon:       '📣',
    tagline:    'Social sharing tags',
    price:      '€15',
    priceNote:  '/year',
    desc:       'OG + Twitter Cards on every page, per-article images, per-category overrides. Looks great when shared on LinkedIn, Facebook, X, and Slack.',
    status:     'live',
    buyUrl:     `${BUY_BASE}/aiboost-opengraph`,
    heroHeadline: 'Look Great Every Time Your Site Gets Shared',
    heroSub:    'AI Boost OpenGraph adds perfect og: tags and Twitter Cards to every Joomla page — so your links look sharp on LinkedIn, Facebook, X, and Slack without any manual work.',
    featuresTitle: 'Social sharing that works out of the box',
    featuresSub:   'Set it once and every page, article, and category page renders a polished social preview — automatically.',
    featureDetails: [
      { icon: '🖼️', title: 'og:image on every page', desc: 'Automatically pulls the first article image, falls back to your site logo, or uses a custom image you specify. No broken blank previews ever again.' },
      { icon: '🐦', title: 'Twitter Cards', desc: 'Generates twitter:card="summary_large_image" on all pages, twitter:site, and twitter:creator — exactly what X/Twitter needs to render a full-width image card.' },
      { icon: '📰', title: 'Per-Article Image Overrides', desc: 'Add a custom OG image to any individual article via a Joomla custom field. Blog posts, press releases, product pages — each gets its own share image.' },
      { icon: '📁', title: 'Per-Category Overrides', desc: 'Set a default OG image for an entire category. Articles without a custom image fall back to the category image, then the site default.' },
      { icon: '🔍', title: 'og:type Intelligence', desc: 'AI Boost sets og:type="article" on content pages and og:type="website" on menus and category pages — exactly matching what Facebook and LinkedIn expect.' },
      { icon: '🌐', title: 'og:locale & Language Tags', desc: 'Multilingual Joomla site? og:locale auto-sets the correct IETF language code and og:locale:alternate links list your other language versions.' },
    ],
    compareRows: [
      { feature: 'og:title, og:description, og:url',    free: true,  pro: true  },
      { feature: 'og:image (site default)',             free: true,  pro: true  },
      { feature: 'og:type (website / article)',         free: true,  pro: true  },
      { feature: 'Twitter Card (summary_large_image)',  free: true,  pro: true  },
      { feature: 'Per-article custom OG image field',  free: false, pro: true  },
      { feature: 'Per-category OG image override',     free: false, pro: true  },
      { feature: 'og:locale & og:locale:alternate',    free: false, pro: true  },
      { feature: 'Custom og:site_name override',       free: false, pro: true  },
      { feature: 'twitter:creator per article',        free: false, pro: true  },
      { feature: 'Image dimension tags (width/height)', free: false, pro: true  },
    ],
    pricingFeatures: [
      'All OG tags + Twitter Cards unlocked',
      'Per-article & per-category image overrides',
      'og:locale for multilingual Joomla sites',
      '12 months of updates & support',
      'Plugin keeps working after expiry',
    ],
    socialProof: {
      emoji: '📣',
      quote: 'After installing AI Boost OpenGraph, our LinkedIn shares went from a blank box to a proper preview image. Click-through rates on shared posts went up noticeably.',
      attribution: '— Marketing manager, B2B SaaS, Joomla 5 site',
    },
    faqs: [
      { q: 'What happens if an article has no image?', a: 'AI Boost falls back gracefully: first to the article intro image, then the full-text image, then the category OG image, and finally to your site-wide default OG image. You always get a preview — never a blank box.' },
      { q: 'Do I need to install anything on Twitter/Facebook to make this work?', a: 'No server-side setup is needed. The plugin writes the meta tags into your page head and social networks read them automatically when someone pastes the link.' },
      { q: 'Does it work with Joomla multilingual sites?', a: 'Yes. The Pro tier sets og:locale to the correct IETF language code (e.g. de_DE, fr_FR) and adds og:locale:alternate links for each active language — exactly what Facebook needs.' },
      { q: 'Will it conflict with my existing SEO plugin?', a: 'AI Boost OpenGraph outputs only og: and twitter: meta tags — the ones most SEO plugins skip. If your current plugin already outputs OG tags, you can disable that feature there and let AI Boost handle it instead.' },
      { q: 'What is the correct image size for OG images?', a: 'Facebook and LinkedIn recommend 1200×630 px. Twitter works best at the same dimensions. AI Boost outputs the correct og:image:width and og:image:height tags automatically so platforms know not to crop the image.' },
      { q: 'Is a license required to keep the plugin working?', a: 'No. If your license expires, the plugin continues generating OG tags. Renew to keep receiving updates and support.' },
    ],
    mockupsTitle: 'See the tags it outputs',
    mockupsSub:   'Clean meta tags injected into every Joomla page head — zero template edits, zero manual tagging.',
    mockups: [
      {
        icon: '📣', label: 'OpenGraph meta tags', subLabel: 'in <head> on every page', type: 'code',
        content: `<!-- AI Boost OpenGraph -->
<meta property="og:type"
  content="article" />
<meta property="og:title"
  content="10 Ways to Improve Joomla SEO" />
<meta property="og:description"
  content="Practical tips to rank your
    Joomla site higher in 2026." />
<meta property="og:url"
  content="https://example.com/blog/joomla-seo" />
<meta property="og:site_name"
  content="My Joomla Site" />
<meta property="og:image"
  content="https://example.com/img/seo.jpg" />
<meta property="og:image:width"
  content="1200" />
<meta property="og:image:height"
  content="630" />
<meta property="og:locale"
  content="en_US" />`,
      },
      {
        icon: '🐦', label: 'Twitter Card tags', subLabel: 'summary_large_image format', type: 'code',
        content: `<!-- Twitter / X Card -->
<meta name="twitter:card"
  content="summary_large_image" />
<meta name="twitter:site"
  content="@myjoomlasite" />
<meta name="twitter:creator"
  content="@janesmith" />
<meta name="twitter:title"
  content="10 Ways to Improve Joomla SEO" />
<meta name="twitter:description"
  content="Practical tips to rank your
    Joomla site higher in 2026." />
<meta name="twitter:image"
  content="https://example.com/img/seo.jpg" />`,
      },
      {
        icon: '🌐', label: 'Multilingual og:locale', subLabel: 'Pro — alternate language links', type: 'code',
        content: `<!-- og:locale for multilingual sites -->
<meta property="og:locale"
  content="en_US" />
<meta property="og:locale:alternate"
  content="de_DE" />
<meta property="og:locale:alternate"
  content="fr_FR" />
<meta property="og:locale:alternate"
  content="es_ES" />

<!-- Per-category fallback image -->
<meta property="og:image"
  content="https://example.com/
    og/blog-category.jpg" />`,
      },
      {
        icon: '📰', label: 'og:type intelligence', subLabel: 'article vs website — set automatically', type: 'code',
        content: `<!-- Category / menu page -->
<meta property="og:type"
  content="website" />

<!-- Article / blog post page -->
<meta property="og:type"
  content="article" />
<meta property="article:published_time"
  content="2026-05-01T08:00:00Z" />
<meta property="article:modified_time"
  content="2026-05-14T12:30:00Z" />
<meta property="article:author"
  content="Jane Smith" />
<meta property="article:section"
  content="SEO Tips" />`,
      },
    ],
    comingSoonTeaser: [
      'og:title, og:description, og:image on every page with zero config',
      'Twitter Card (summary_large_image) generated automatically',
      'Per-article and per-category image overrides via Joomla custom fields',
    ],
  },

  // ─── AI Boost Sitemap ─────────────────────────────────────────────────────
  {
    slug:       'sitemap-xml',
    name:       'AI Boost Sitemap',
    shortName:  'Sitemap XML',
    icon:       '🗺️',
    tagline:    'XML Sitemap + hreflang',
    price:      '€15',
    priceNote:  '/year',
    desc:       'Dynamic XML sitemap auto-generated at /sitemap.xml. Priority, changefreq, image sitemap, and hreflang links for multilingual Joomla sites.',
    status:     'live',
    buyUrl:     `${BUY_BASE}/aiboost-sitemap`,
    heroHeadline: 'Get Every Page Indexed — Fast',
    heroSub:    'AI Boost Sitemap generates a dynamic XML sitemap the moment you publish content. Images, priorities, and multilingual hreflang links — all included, no cron job required.',
    featuresTitle: 'A sitemap that stays accurate without effort',
    featuresSub:   'Every new article, menu item, and category page appears in your sitemap automatically — exactly as Google expects.',
    featureDetails: [
      { icon: '⚡', title: 'Instant publish updates', desc: 'The sitemap reflects your content the moment you hit Save in Joomla. No cron, no manual regeneration, no cached stale URLs ever sent to Google.' },
      { icon: '🖼️', title: 'Image Sitemap Extension', desc: 'Every image embedded in your articles is listed under its parent URL in an <image:image> extension block — so Google Image Search can index and rank your photos.' },
      { icon: '🌍', title: 'Hreflang in Sitemap', desc: 'For multilingual Joomla sites, each URL entry includes the correct <xhtml:link> hreflang alternates — compatible with Falang and Joomla Language Associations.' },
      { icon: '📊', title: 'Priority & changefreq Control', desc: 'Set global priority defaults per content type (articles, categories, menus) or override on individual items. Google uses this as a crawl-budget hint.' },
      { icon: '🔗', title: 'Sitemap Index for Large Sites', desc: 'Sites with over 1,000 URLs automatically get a sitemap index file that splits the sitemap into clean sub-files — within Google\'s 50 MB / 50,000 URL limits.' },
      { icon: '📤', title: 'Auto-Submit to Google & Bing', desc: 'On first activation, AI Boost pings Google Search Console and Bing Webmaster Tools with your sitemap URL so indexing starts immediately.' },
    ],
    compareRows: [
      { feature: 'Dynamic /sitemap.xml',                free: true,  pro: true  },
      { feature: 'Articles, categories, menus',         free: true,  pro: true  },
      { feature: 'Sitemap ping on publish',             free: true,  pro: true  },
      { feature: 'Image sitemap extension',             free: false, pro: true  },
      { feature: 'Hreflang alternates in sitemap',     free: false, pro: true  },
      { feature: 'Per-item priority & changefreq',     free: false, pro: true  },
      { feature: 'Sitemap index (large sites)',         free: false, pro: true  },
      { feature: 'Auto-submit to Google & Bing',       free: false, pro: true  },
      { feature: 'Exclude URLs by menu item / category', free: false, pro: true  },
      { feature: 'Custom URL entries',                  free: false, pro: true  },
    ],
    pricingFeatures: [
      'Dynamic sitemap with instant publish sync',
      'Image sitemap + hreflang alternates',
      'Sitemap index for unlimited-size sites',
      '12 months of updates & support',
      'Plugin keeps working after expiry',
    ],
    socialProof: {
      emoji: '🗺️',
      quote: 'Our old sitemap plugin was always out of date. With AI Boost Sitemap the sitemap updates the moment we publish — and the image sitemap got our product photos into Google Images within a week.',
      attribution: '— E-commerce Joomla site owner, 4,200 products',
    },
    faqs: [
      { q: 'Do I need to manually regenerate the sitemap?', a: 'No. The sitemap is generated dynamically on every request from Google or Bing. It always reflects your current content with no cron job or manual step required.' },
      { q: 'What if my site has more than 50,000 URLs?', a: 'Google\'s sitemap protocol limits each file to 50,000 URLs and 50 MB. AI Boost automatically creates a sitemap index that splits large sites into multiple sub-sitemaps — all within the limits.' },
      { q: 'Will it work with Falang for multilingual sites?', a: 'Yes. AI Boost Sitemap detects Falang and Joomla Language Associations and adds the correct hreflang <xhtml:link> alternates inside each URL block — exactly as Google requires.' },
      { q: 'Does it support Joomla 4, 5, and 6?', a: 'Yes. AI Boost Sitemap is tested on Joomla 4.0 through 6.x with PHP 8.1 through 8.5.' },
      { q: 'Can I exclude certain pages from the sitemap?', a: 'Yes, in Pro. You can exclude specific menu items, categories, or individual articles from the sitemap via the plugin admin — useful for login pages, thank-you pages, or admin areas.' },
      { q: 'Is a license required to keep the plugin working?', a: 'No. If your license expires, the plugin keeps generating the sitemap. Renew to keep receiving updates and support.' },
    ],
    mockupsTitle: 'See the sitemap it generates',
    mockupsSub:   'Valid, up-to-date XML that Google and Bing accept on the first crawl — auto-generated on every request.',
    mockups: [
      {
        icon: '🗺️', label: '/sitemap.xml', subLabel: 'updated on every publish', type: 'code',
        content: `<?xml version="1.0" encoding="UTF-8"?>
<urlset
  xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
  xmlns:image=
    "http://www.google.com/schemas/sitemap-image/1.1"
  xmlns:xhtml=
    "http://www.w3.org/1999/xhtml">

  <url>
    <loc>https://example.com/blog/joomla-seo</loc>
    <lastmod>2026-05-14</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>

  <url>
    <loc>https://example.com/about</loc>
    <lastmod>2026-03-01</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

</urlset>`,
      },
      {
        icon: '🖼️', label: 'Image sitemap extension', subLabel: 'article images indexed by Google', type: 'code',
        content: `<url>
  <loc>
    https://example.com/blog/joomla-seo
  </loc>
  <image:image>
    <image:loc>
      https://example.com/img/seo-guide.jpg
    </image:loc>
    <image:title>
      Joomla SEO guide cover
    </image:title>
    <image:caption>
      A step-by-step visual guide to
      Joomla SEO settings
    </image:caption>
  </image:image>
  <image:image>
    <image:loc>
      https://example.com/img/schema.jpg
    </image:loc>
  </image:image>
</url>`,
      },
      {
        icon: '🌍', label: 'Hreflang in sitemap', subLabel: 'multilingual alternates per URL', type: 'code',
        content: `<url>
  <loc>
    https://example.com/en/about
  </loc>
  <xhtml:link
    rel="alternate"
    hreflang="en"
    href="https://example.com/en/about"/>
  <xhtml:link
    rel="alternate"
    hreflang="de"
    href="https://example.com/de/ueber-uns"/>
  <xhtml:link
    rel="alternate"
    hreflang="fr"
    href="https://example.com/fr/a-propos"/>
  <xhtml:link
    rel="alternate"
    hreflang="x-default"
    href="https://example.com/en/about"/>
</url>`,
      },
      {
        icon: '📋', label: 'Sitemap index', subLabel: 'auto-splits large sites', type: 'code',
        content: `<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex
  xmlns=
    "http://www.sitemaps.org/schemas/sitemap/0.9">

  <sitemap>
    <loc>
      https://example.com/sitemap-articles.xml
    </loc>
    <lastmod>2026-05-15</lastmod>
  </sitemap>

  <sitemap>
    <loc>
      https://example.com/sitemap-categories.xml
    </loc>
    <lastmod>2026-05-15</lastmod>
  </sitemap>

  <sitemap>
    <loc>
      https://example.com/sitemap-images.xml
    </loc>
    <lastmod>2026-05-15</lastmod>
  </sitemap>

</sitemapindex>`,
      },
    ],
    comingSoonTeaser: [
      'Dynamic sitemap.xml auto-updated every time you publish or edit content',
      'Image sitemap extension lists all article images for Google Image Search',
      'Hreflang link tags for multilingual sites — Falang and Joomla Associations supported',
    ],
  },

  // ─── AI Boost Hreflang ────────────────────────────────────────────────────
  {
    slug:       'seo',
    name:       'AI Boost Hreflang',
    shortName:  'SEO & Hreflang',
    icon:       '🌍',
    tagline:    'Multilingual SEO',
    price:      '€15',
    priceNote:  '/year',
    desc:       'Hreflang tags, x-default, canonical URLs, and multilingual SEO signals. Works with Joomla core language associations and Falang.',
    status:     'live',
    buyUrl:     `${BUY_BASE}/aiboost-hreflang`,
    heroHeadline: 'Rank in Every Language You Target',
    heroSub:    'AI Boost Hreflang adds correct hreflang link tags, x-default, and canonical URLs to every Joomla page — eliminating duplicate-content penalties and sending the right signals to Google in each market.',
    featuresTitle: 'Multilingual SEO — done correctly',
    featuresSub:   'Hreflang is the most misimplemented tag in SEO. AI Boost gets it exactly right — automatically.',
    featureDetails: [
      { icon: '🌍', title: 'Hreflang on every page', desc: 'Every page gets the full set of <link rel="alternate" hreflang="..."> tags for all your active Joomla languages — self-referencing, cross-referencing, and x-default included.' },
      { icon: '🎯', title: 'x-default fallback', desc: 'The x-default hreflang value tells Google which page to show users whose language has no translation. AI Boost sets this correctly on your primary language pages automatically.' },
      { icon: '🔗', title: 'Canonical URL management', desc: 'Prevents duplicate-content penalties by setting a correct canonical tag on every page. Handles Joomla\'s index.php?option= URLs, pagination, and category listings.' },
      { icon: '🔄', title: 'Falang & Associations support', desc: 'AI Boost auto-detects whether you\'re using Joomla Language Associations or Falang and reads the correct translation pairs for each page — no manual setup.' },
      { icon: '📑', title: 'Pagination canonicals', desc: 'Category list pages with ?start=20 pagination get proper rel="canonical" pointing to the first page — stopping Joomla\'s paginated pages from competing with each other.' },
      { icon: '🚫', title: 'Trailing slash consistency', desc: 'Enforces a consistent trailing-slash policy across all URLs and redirects or canonicalises variations — a common Joomla duplicate-content source.' },
    ],
    compareRows: [
      { feature: 'Canonical tag on all pages',           free: true,  pro: true  },
      { feature: 'Pagination canonical (rel=canonical)', free: true,  pro: true  },
      { feature: 'Hreflang on all pages',                free: false, pro: true  },
      { feature: 'x-default hreflang tag',               free: false, pro: true  },
      { feature: 'Falang integration',                   free: false, pro: true  },
      { feature: 'Joomla Associations integration',      free: false, pro: true  },
      { feature: 'Trailing slash normalisation',         free: false, pro: true  },
      { feature: 'Per-page canonical override',          free: false, pro: true  },
      { feature: 'Hreflang XML sitemap integration',    free: false, pro: true  },
      { feature: 'Region targeting (hreflang en-GB)',   free: false, pro: true  },
    ],
    pricingFeatures: [
      'Full hreflang implementation on all pages',
      'x-default + Falang & Associations support',
      'Canonical + pagination + trailing slash fixes',
      '12 months of updates & support',
      'Plugin keeps working after expiry',
    ],
    socialProof: {
      emoji: '🌍',
      quote: 'We run Joomla in 6 languages and hreflang was always a mess — wrong x-default, missing self-references. AI Boost Hreflang fixed everything in one install and our international traffic increased 40% over two months.',
      attribution: '— Head of Digital, European NGO, Joomla 5 multisite',
    },
    faqs: [
      { q: 'What is hreflang and why is it so often wrong?', a: 'Hreflang tells Google which version of a page to show users in each language and region. It\'s bidirectional — every translated page must reference all other translations, including itself. Most plugins only add the tag to one end, which Google ignores. AI Boost implements the full bidirectional set correctly.' },
      { q: 'Does it work with Falang?', a: 'Yes. AI Boost auto-detects Falang and reads its translation table to build the correct hreflang set for each page — no manual configuration required.' },
      { q: 'What if I only have one language?', a: 'For single-language sites, the Pro canonical and trailing-slash features still provide value by eliminating Joomla\'s common duplicate-content patterns. You don\'t need hreflang tags at all for single-language sites.' },
      { q: 'How does x-default work?', a: 'x-default tells Google which page to show users whose browser language doesn\'t match any of your translations. Typically this is your primary language. AI Boost sets it on the correct page automatically.' },
      { q: 'Does it support Joomla 4, 5, and 6?', a: 'Yes. AI Boost Hreflang is tested on Joomla 4.0 through 6.x with PHP 8.1 through 8.5.' },
      { q: 'Is a license required to keep the plugin working?', a: 'No. If your license expires, the plugin continues outputting tags. Renew to keep receiving updates and support.' },
    ],
    mockupsTitle: 'See the tags it outputs',
    mockupsSub:   'Correct, bidirectional hreflang and canonical tags in every page head — exactly what Google needs.',
    mockups: [
      {
        icon: '🌍', label: 'Hreflang link tags', subLabel: 'in <head> on every multilingual page', type: 'code',
        content: `<!-- AI Boost Hreflang — auto-generated -->
<link rel="alternate"
  hreflang="en"
  href="https://example.com/en/about" />
<link rel="alternate"
  hreflang="de"
  href="https://example.com/de/ueber-uns" />
<link rel="alternate"
  hreflang="fr"
  href="https://example.com/fr/a-propos" />
<link rel="alternate"
  hreflang="es"
  href="https://example.com/es/acerca-de" />
<link rel="alternate"
  hreflang="x-default"
  href="https://example.com/en/about" />`,
      },
      {
        icon: '🔗', label: 'Canonical tag', subLabel: 'prevents duplicate-content penalties', type: 'code',
        content: `<!-- Canonical: clean URL enforced -->
<link rel="canonical"
  href="https://example.com/blog/joomla-seo" />

<!-- Without AI Boost, Joomla may output: -->
<!-- /index.php?option=com_content
       &view=article&id=42 -->
<!-- /blog/joomla-seo?Itemid=101 -->
<!-- /blog/joomla-seo/ (trailing slash) -->

<!-- AI Boost consolidates all variations
     to the single canonical URL so Google
     doesn't split ranking signals. -->`,
      },
      {
        icon: '📑', label: 'Pagination canonical', subLabel: 'category list pages with ?start=N', type: 'code',
        content: `<!-- Category page — first page (canonical) -->
<link rel="canonical"
  href="https://example.com/blog" />

<!-- Category page — page 2 (?start=20) -->
<link rel="canonical"
  href="https://example.com/blog" />
<!-- Points to page 1, not itself.
     Tells Google that all paginated
     views are part of the same URL,
     consolidating ranking signals. -->

<!-- Optional: rel=prev/next for crawlers -->
<link rel="prev"
  href="https://example.com/blog" />
<link rel="next"
  href="https://example.com/blog?start=40" />`,
      },
      {
        icon: '🎯', label: 'Region targeting', subLabel: 'en-GB vs en-US — Pro', type: 'code',
        content: `<!-- Region-specific hreflang (Pro) -->
<!-- Targeting UK and US separately -->
<link rel="alternate"
  hreflang="en-GB"
  href="https://example.com/en-gb/pricing" />
<link rel="alternate"
  hreflang="en-US"
  href="https://example.com/en-us/pricing" />
<link rel="alternate"
  hreflang="de-DE"
  href="https://example.com/de/preise" />
<link rel="alternate"
  hreflang="de-AT"
  href="https://example.com/at/preise" />
<link rel="alternate"
  hreflang="x-default"
  href="https://example.com/en-us/pricing" />`,
      },
    ],
    comingSoonTeaser: [
      'Hreflang link tags on every page with correct x-default fallback',
      'Canonical URL management prevents duplicate-content penalties',
      'Auto-detects Joomla language setup — Falang and Associations both supported',
    ],
  },

  // ─── AI Boost Code Manager ────────────────────────────────────────────────
  {
    slug:       'code-manager',
    name:       'AI Boost Code Manager',
    shortName:  'Code Manager',
    icon:       '📊',
    tagline:    'Analytics + Custom code',
    price:      '€15',
    priceNote:  '/year',
    desc:       'GA4, GTM, Meta Pixel, Search Console verification, and custom head/body/footer code injection — all from one Joomla admin panel.',
    status:     'live',
    buyUrl:     `${BUY_BASE}/aiboost-codemanager`,
    heroHeadline: 'All Your Tracking Codes in One Place',
    heroSub:    'GA4, Google Tag Manager, Meta Pixel, and Search Console verification — installed once in Joomla admin, injected perfectly on every page. No template edits. No FTP.',
    featuresTitle: 'One panel for every tracking tool you use',
    featuresSub:   'Stop editing template files every time a marketing tool needs a snippet. AI Boost Code Manager handles it all from your Joomla admin.',
    featureDetails: [
      { icon: '📈', title: 'Google Analytics 4', desc: 'Paste your GA4 Measurement ID and AI Boost injects the gtag.js snippet correctly in the <head> of every page. Supports both gtag and GTM-based GA4 deployments.' },
      { icon: '🏷️', title: 'Google Tag Manager', desc: 'Full GTM container implementation — the <script> in <head> and the <noscript> iframe in <body> placed exactly where Google requires them, on every page.' },
      { icon: '📘', title: 'Meta Pixel', desc: 'Your Meta (Facebook) Pixel base code injected into every page. Conversion events fire correctly for Joomla\'s article view and category pages.' },
      { icon: '✅', title: 'Search Console Verification', desc: 'Paste your Google, Bing, Yandex, or Pinterest verification meta tag and AI Boost adds it to your homepage head. Works for all major webmaster tools.' },
      { icon: '💻', title: 'Custom Code Injection', desc: 'Need to add a live chat widget, a cookie consent script, or a custom font? Inject arbitrary HTML/JS into <head>, after <body>, or before </body> on all pages — or only on specific menu items.' },
      { icon: '🔒', title: 'Per-Page and Global Rules', desc: 'Apply tracking codes globally or restrict them to specific Joomla menu items. Exclude the Joomla admin, thank-you pages, or member-only areas from analytics with a single toggle.' },
    ],
    compareRows: [
      { feature: 'Google Analytics 4',                  free: true,  pro: true  },
      { feature: 'Google Search Console verification',  free: true,  pro: true  },
      { feature: 'Bing & Yandex verification tags',    free: true,  pro: true  },
      { feature: 'Google Tag Manager',                  free: false, pro: true  },
      { feature: 'Meta (Facebook) Pixel',               free: false, pro: true  },
      { feature: 'Custom <head> code injection',        free: false, pro: true  },
      { feature: 'Custom <body> / footer injection',   free: false, pro: true  },
      { feature: 'Per-menu-item code rules',            free: false, pro: true  },
      { feature: 'Exclude admin from tracking',         free: false, pro: true  },
      { feature: 'Multiple GTM containers',             free: false, pro: true  },
    ],
    pricingFeatures: [
      'GA4 + GTM + Meta Pixel in one panel',
      'Custom head/body/footer code injection',
      'Per-menu-item rules + admin exclusion',
      '12 months of updates & support',
      'Plugin keeps working after expiry',
    ],
    socialProof: {
      emoji: '📊',
      quote: 'We used to edit index.php in the template every time marketing needed a new snippet. AI Boost Code Manager ended that — everything goes through the admin panel now and the template is clean.',
      attribution: '— Lead developer, digital agency, 12 Joomla client sites',
    },
    faqs: [
      { q: 'Can I use both GA4 and GTM at the same time?', a: 'Yes, but you should typically choose one or the other. If you use GTM, configure GA4 inside GTM as a tag — and leave the AI Boost GA4 field empty. If you don\'t use GTM, use the GA4 field directly. AI Boost supports both workflows.' },
      { q: 'Where exactly is the GTM noscript tag placed?', a: 'Google requires the GTM <noscript> tag immediately after the opening <body> tag. AI Boost injects it in exactly that position using Joomla\'s onAfterRender event — no template edits needed.' },
      { q: 'Can I inject code on only specific pages?', a: 'Yes, in Pro. You can assign any custom code snippet to specific Joomla menu items. For example, fire a conversion pixel only on your thank-you page, or load a live chat widget only on your contact page.' },
      { q: 'Does it work with Joomla 4, 5, and 6?', a: 'Yes. AI Boost Code Manager is tested on Joomla 4.0 through 6.x with PHP 8.1 through 8.5.' },
      { q: 'Is it safe to inject custom JavaScript?', a: 'AI Boost outputs whatever code you paste without modification. You are responsible for the code you inject — exactly as if you had edited the template file directly.' },
      { q: 'Is a license required to keep the plugin working?', a: 'No. If your license expires, the plugin keeps injecting your tracking codes. Renew to keep receiving updates and support.' },
    ],
    mockupsTitle: 'See what it injects',
    mockupsSub:   'Perfectly placed tracking code on every page — injected by the plugin, not hard-coded into your template.',
    mockups: [
      {
        icon: '📈', label: 'Google Analytics 4', subLabel: 'injected into <head>', type: 'code',
        content: `<!-- Google Analytics 4 (AI Boost) -->
<script async
  src="https://www.googletagmanager.com/
    gtag/js?id=G-XXXXXXXXXX">
</script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){
    dataLayer.push(arguments);
  }
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX', {
    'anonymize_ip': true
  });
</script>`,
      },
      {
        icon: '🏷️', label: 'Google Tag Manager', subLabel: '<head> + <body> placed correctly', type: 'code',
        content: `<!-- GTM: in <head> (AI Boost) -->
<script>(function(w,d,s,l,i){
  w[l]=w[l]||[];
  w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});
  var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),
    dl=l!='dataLayer'?'&l='+l:'';
  j.async=true;
  j.src='https://www.googletagmanager.com/
    gtm.js?id='+i+dl;
  f.parentNode.insertBefore(j,f);
})(window,document,'script',
  'dataLayer','GTM-XXXXXXX');
</script>

<!-- GTM: immediately after <body> -->
<noscript>
  <iframe src="https://www.googletagmanager
    .com/ns.html?id=GTM-XXXXXXX"
    height="0" width="0"
    style="display:none;visibility:hidden">
  </iframe>
</noscript>`,
      },
      {
        icon: '📘', label: 'Meta Pixel', subLabel: 'base code + PageView event', type: 'code',
        content: `<!-- Meta Pixel (AI Boost Code Manager) -->
<script>
!function(f,b,e,v,n,t,s){
  if(f.fbq)return;
  n=f.fbq=function(){
    n.callMethod?
    n.callMethod.apply(n,arguments)
    :n.queue.push(arguments)
  };
  if(!f._fbq)f._fbq=n;
  n.push=n;n.loaded=!0;
  n.version='2.0';
  n.queue=[];
  t=b.createElement(e);
  t.async=!0;
  t.src=v;
  s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)
}(window,document,'script',
'https://connect.facebook.net/
  en_US/fbevents.js');
fbq('init', '123456789012345');
fbq('track', 'PageView');
</script>`,
      },
      {
        icon: '✅', label: 'Verification tags', subLabel: 'Google · Bing · Yandex · Pinterest', type: 'code',
        content: `<!-- Search Console verifications
     (AI Boost Code Manager) -->

<!-- Google Search Console -->
<meta name="google-site-verification"
  content="abc123XYZ..." />

<!-- Bing Webmaster Tools -->
<meta name="msvalidate.01"
  content="def456ABC..." />

<!-- Yandex Webmaster -->
<meta name="yandex-verification"
  content="ghi789DEF..." />

<!-- Pinterest -->
<meta name="p:domain_verify"
  content="jkl012GHI..." />`,
      },
    ],
    comingSoonTeaser: [
      'Google Analytics 4, Google Tag Manager, and Meta Pixel in one panel',
      'Search Console, Bing Webmaster, and Yandex verification tags',
      'Inject custom code into <head>, <body>, or footer on any page',
    ],
  },
]

export const pluginBySlug = (slug: string): PluginDef | undefined =>
  pluginsData.find(p => p.slug === slug)
