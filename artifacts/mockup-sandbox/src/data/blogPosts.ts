export interface BlogPost {
  slug: string;
  title: string;
  excerpt: string;
  category: string;
  readTime: string;
  date: string;
  isNew?: boolean;
}

export const blogPosts: BlogPost[] = [
  {
    slug: "what-is-schema-org",
    title: "What is Schema.org and why every website needs it in 2026",
    excerpt: "Schema.org structured data helps search engines and AI systems understand what your content is about — not just what it says. Here's how it works and why it's no longer optional.",
    category: "Schema.org",
    readTime: "5 min read",
    date: "2026-01-10",
  },
  {
    slug: "llms-txt-ai-search",
    title: "How llms.txt makes your site visible to ChatGPT and Perplexity",
    excerpt: "llms.txt is a simple text file that tells AI language models what your site is about. Without it, AI engines may ignore you entirely. Learn how to create one in minutes.",
    category: "AI Search",
    readTime: "4 min read",
    date: "2026-01-18",
  },
  {
    slug: "what-is-indexnow",
    title: "What is IndexNow and how it gets your pages indexed in minutes",
    excerpt: "Traditional crawling can take days or weeks. IndexNow lets you push URLs directly to Bing, Yandex, and Seznam the moment you publish. Here's the complete guide.",
    category: "Indexing",
    readTime: "4 min read",
    date: "2026-01-25",
  },
  {
    slug: "opengraph-tags-explained",
    title: "OpenGraph tags explained: control how your site looks on social media",
    excerpt: "Every time someone shares your link on Facebook, LinkedIn, or WhatsApp, OpenGraph tags decide the title, image, and description that appear. Are yours set correctly?",
    category: "Social & Meta",
    readTime: "5 min read",
    date: "2026-02-03",
  },
  {
    slug: "robots-txt-ai-crawlers",
    title: "What is robots.txt and how to configure it for AI crawlers in 2026",
    excerpt: "25+ AI bots crawl the web every day — GPTBot, ClaudeBot, PerplexityBot, and more. Your robots.txt controls which ones can access your content. Here's what you need to know.",
    category: "Technical SEO",
    readTime: "6 min read",
    date: "2026-02-12",
  },
  {
    slug: "json-ld-vs-microdata",
    title: "Structured data 101: JSON-LD vs Microdata vs RDFa — which to use",
    excerpt: "Google recommends JSON-LD. Microdata is embedded in HTML. RDFa is rarely used today. We break down the differences so you can make the right choice for your Joomla site.",
    category: "Schema.org",
    readTime: "5 min read",
    date: "2026-02-20",
  },
  {
    slug: "faq-schema-joomla",
    title: "How to add FAQ Schema to your Joomla website",
    excerpt: "FAQPage schema can put your questions and answers directly in Google search results — no clicking required. Learn how AI Boost auto-detects your FAQ articles and generates the markup.",
    category: "Schema.org",
    readTime: "5 min read",
    date: "2026-03-04",
  },
  {
    slug: "what-is-aeo",
    title: "What is AEO (Answer Engine Optimization) and why it matters",
    excerpt: "Search is shifting from ten blue links to direct answers. AEO is the practice of optimising your content so AI engines choose your site as the source. Here's how to start.",
    category: "AI Search",
    readTime: "6 min read",
    date: "2026-03-15",
  },
  {
    slug: "localbusiness-schema-guide",
    title: "LocalBusiness Schema: the complete guide for service-based websites",
    excerpt: "Restaurants, clinics, law firms, gyms — any business with a physical location needs LocalBusiness schema. We cover every property that makes a difference in local AI search.",
    category: "Schema.org",
    readTime: "7 min read",
    date: "2026-03-28",
  },
  {
    slug: "xml-sitemap-joomla",
    title: "XML sitemap best practices for Joomla 4, 5, and 6",
    excerpt: "A well-configured XML sitemap tells search engines which pages to crawl and how often they change. Learn the exact settings that work across all current Joomla versions.",
    category: "Technical SEO",
    readTime: "5 min read",
    date: "2026-04-08",
  },
  {
    slug: "business-hours-joomla-schema",
    title: "How to add Business Hours to your Joomla schema (LocalBusiness opening hours)",
    excerpt: "openingHoursSpecification is the Schema.org property that tells Google and AI engines exactly when you're open. Learn how to add correct Business Hours markup to your Joomla site without writing a single line of JSON.",
    category: "Schema.org",
    readTime: "6 min read",
    date: "2026-04-22",
    isNew: true,
  },
  {
    slug: "hreflang-multilingual-joomla",
    title: "Setting up hreflang for a multilingual Joomla site step by step",
    excerpt: "Without hreflang tags, Google may serve the wrong language version to international visitors — and AI engines may cite the wrong page. Here's how to set up hreflang correctly in Joomla.",
    category: "Multilingual",
    readTime: "7 min read",
    date: "2026-04-30",
    isNew: true,
  },
  {
    slug: "google-ai-overviews-sources",
    title: "How Google AI Overviews decide which sources to cite (and how to be one)",
    excerpt: "Google AI Overviews now appear above organic results for millions of queries. We analysed what separates cited sources from ignored ones — and what Joomla site owners can do about it today.",
    category: "AI Search",
    readTime: "8 min read",
    date: "2026-05-06",
    isNew: true,
  },
  {
    slug: "ga4-gtm-joomla-no-code",
    title: "GA4 and Google Tag Manager setup in Joomla — no coding required",
    excerpt: "Getting GA4 and GTM running on a Joomla site used to mean editing template files. AI Boost lets you paste your Measurement ID and GTM container code in one field and you're done.",
    category: "Analytics",
    readTime: "5 min read",
    date: "2026-05-09",
    isNew: true,
  },
  {
    slug: "breadcrumblist-schema-joomla",
    title: "What is BreadcrumbList schema and why every Joomla site should use it",
    excerpt: "BreadcrumbList schema improves how your URLs appear in Google and helps AI engines understand your site's hierarchy. Most Joomla sites miss this easy win — here's how to add it in seconds.",
    category: "Schema.org",
    readTime: "4 min read",
    date: "2026-05-11",
    isNew: true,
  },
];

export const categories = [...new Set(blogPosts.map(p => p.category))];
