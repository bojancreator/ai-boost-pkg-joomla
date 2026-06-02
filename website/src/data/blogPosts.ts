export interface BlogPost {
  slug: string
  title: string
  description: string
  date: string
  readTime: string
  tags: string[]
  content: string
}

export const blogPosts: BlogPost[] = [
  {
    slug: 'what-is-schema-org',
    title: 'What is Schema.org — and why Google, ChatGPT and Perplexity care about it',
    description: 'Schema.org is the shared vocabulary that helps search engines and AI assistants understand what your web pages are really about — not just the words on them.',
    date: '2026-05-01',
    readTime: '6 min read',
    tags: ['Schema.org', 'Structured Data'],
    content: `
## The invisible layer of the web

When you visit a webpage, you see text, images, and layout. But search engines and AI assistants see something different — they see HTML, and they have to guess what everything means. Is that number a price, a phone number, or a star rating? Is that name the author, the business owner, or a fictional character?

Schema.org was created to solve this problem. It is a shared vocabulary — a standardised set of labels — that lets you tell search engines exactly what your content means, not just what it says.

Schema.org is maintained by Google, Bing, Yahoo, and Yandex jointly. It defines types like *Organization*, *Article*, *FAQPage*, *Product*, *Event*, *LocalBusiness*, *Hotel*, and hundreds more. Each type has a set of properties: a Hotel has a starRating and a checkInTime. An Article has an author and a datePublished. A FAQPage has questions and acceptedAnswers.

## Why it matters for traditional search

Google uses Schema.org data to power its **rich results** — the enhanced search listings that show star ratings, event dates, FAQ dropdowns, product prices, and recipe details directly in the search results page, before a user even clicks.

Rich results consistently earn higher click-through rates than plain blue links. A listing with five gold stars stands out. A FAQ accordion that expands directly in the SERP captures attention at zero cost.

Beyond click-through, structured data also helps Google understand your site well enough to show it in the **Knowledge Panel** — the information box that appears on the right side of search results for well-known businesses, people, and places. Getting into the Knowledge Panel requires that Google can confidently identify who you are and what you do. Schema.org is how you make that identification explicit.

## Why it matters for AI search

This is where things get significantly more interesting in 2026.

AI assistants like ChatGPT (with Browse), Perplexity, and Google AI Overviews do not simply rank pages by keyword relevance. They *read* pages, extract facts, and synthesise answers from multiple sources. The pages they choose to cite are the ones they can most confidently understand and verify.

Structured data plays a direct role here. When your FAQ questions and answers are marked up with FAQPage schema, an AI assistant can extract them reliably without having to parse your HTML structure and guess which paragraphs are questions versus answers. When your business hours are in openingHoursSpecification format, Perplexity can answer "Is Acme Hotel open on Sundays?" with confidence.

Google's AI Overviews in particular have been shown to disproportionately cite pages with strong structured data signals. The reason is straightforward: structured data reduces ambiguity, and AI systems prefer unambiguous sources.

## The JSON-LD format

Schema.org data can be embedded in several ways. The format recommended by Google — and the one that causes zero risk of visual interference — is **JSON-LD** (JavaScript Object Notation for Linked Data). It sits in a \`<script type="application/ld+json">\` block in your page's \`<head>\`, completely separate from the visible HTML.

A simple Organization schema looks like this:

\`\`\`json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Acme Hotel Manhattan",
  "url": "https://acme-hotel.com",
  "telephone": "+1 212 555 0123",
  "address": {
    "@type": "PostalAddress",
    "addressCountry": "US",
    "addressLocality": "New York"
  }
}
\`\`\`

Every field you add improves the machine's confidence in your data.

## The challenge for Joomla sites

Writing Schema.org JSON-LD by hand is technically straightforward but practically tedious. A proper Hotel schema with opening hours, ratings, amenities, and multilingual fields runs to dozens of lines. Keeping it synchronised with your actual content — when you update phone numbers, add events, or publish new articles — is even harder.

This is exactly what **AI Boost for Joomla** handles automatically. It reads your Joomla configuration, generates the correct Schema.org type for your site (Hotel, Restaurant, MedicalClinic, LocalBusiness, and 10 more), populates all required and recommended fields, and injects a correctly formatted JSON-LD block on every page — without you touching a single line of code.

The result is that your pages communicate their meaning clearly to every search engine and AI assistant that crawls them, giving you the structural foundation for rich results and AI citations.
    `.trim(),
  },
  {
    slug: 'what-is-llms-txt',
    title: 'What is llms.txt — the new standard that helps AI engines understand your site',
    description: 'llms.txt is a simple text file, placed at the root of your website, that gives AI assistants a structured overview of your content so they can crawl and cite it more accurately.',
    date: '2026-05-03',
    readTime: '5 min read',
    tags: ['llms.txt', 'AI Search'],
    content: `
## A new file for a new era

You almost certainly know about \`robots.txt\` — the file that tells search engine crawlers which pages they can and cannot access. And you probably know about \`sitemap.xml\` — the file that lists all your pages so crawlers can find them efficiently.

**\`llms.txt\`** is the newest addition to this family. Proposed in late 2024 and rapidly adopted by websites aiming for visibility in AI-powered search, it is a plain text file that describes your site's content in a format optimised for Large Language Models (LLMs) — the AI systems that power ChatGPT, Perplexity, Claude, and Google AI Overviews.

## What robots.txt and sitemaps cannot do

\`robots.txt\` and \`sitemap.xml\` were designed for traditional web crawlers. They answer two questions: *can you crawl this page?* and *does this page exist?* They say nothing about what the content means, how it is structured, or which parts are most important.

AI assistants face a harder problem than traditional crawlers. They do not just index pages — they understand them. When a user asks "what's the best way to configure Schema.org for a hotel website?", the AI needs to find not just a page that contains those words, but a page that actually addresses that question with accurate, trustworthy content.

\`llms.txt\` gives AI systems the orientation they need before they dive into your individual pages. It answers: *what is this site about, who runs it, and where is the most important content?*

## The format

\`llms.txt\` follows a simple Markdown-like structure:

\`\`\`
# Acme Hotel Manhattan

> A 4-star hotel in Manhattan, New York, USA, offering luxury rooms and conference facilities.

## Key pages

- [Home](https://acme-hotel.com/): Hotel overview and booking
- [Rooms](https://acme-hotel.com/rooms): Room types and rates
- [Restaurant](https://acme-hotel.com/restaurant): Dining options
- [Contact](https://acme-hotel.com/contact): Address and directions

## About

Acme Hotel has been operating since 1999. We accommodate both leisure and business travellers.
\`\`\`

The file starts with a level-1 heading (the site name), a blockquote description, and then structured sections linking to the most important pages. The AI uses this as a map — a high-level understanding of your site before it decides which pages to crawl and cite.

## Who reads llms.txt?

The file is read by AI crawlers like **PerplexityBot**, **GPTBot** (OpenAI), **ClaudeBot** (Anthropic), **Googlebot** (for AI Overviews), and emerging crawlers from other AI providers. It is also read by AI tools that allow users to ask questions about specific websites.

As of 2026, support among major AI systems is growing rapidly. Perplexity has confirmed that it uses \`llms.txt\` when present. ChatGPT's Browse feature and Google's AI Overviews are increasingly influenced by the file's existence and content quality.

## Why AI crawlers benefit from it

Without \`llms.txt\`, an AI crawler arrives at your site cold. It crawls your homepage, follows some links, reads some pages, and tries to build a picture of what you offer. It may miss your best content if it is buried in page 3 of category archives. It may misidentify your site's primary purpose.

With \`llms.txt\`, you give the AI a curated introduction. You highlight your most authoritative pages. You provide a precise description of who you are and what you do. This reduces the chance of misattribution — being cited for something tangential when your core expertise is something else entirely.

## How AI Boost for Joomla handles llms.txt

Writing and maintaining \`llms.txt\` by hand requires you to keep it in sync with your site's actual content — updating it whenever you add major new pages or change your site's focus.

**AI Boost for Joomla** generates \`llms.txt\` dynamically and serves it at \`yoursite.com/llms.txt\`. It automatically includes your organisation name, description, and key pages from your Joomla menu and sitemap. When you add new content or update your organisation details, \`llms.txt\` updates with it.

The file is one of the features available on the Developer and Agency plans — because for sites that depend on AI-driven discovery, being invisible to LLM crawlers is a significant competitive disadvantage.
    `.trim(),
  },
  {
    slug: 'what-is-indexnow',
    title: 'What is IndexNow — get your pages indexed in minutes, not days',
    description: 'IndexNow is an open protocol that lets you notify Bing, Yandex, and other search engines the instant you publish or update a page — bypassing the standard crawl queue entirely.',
    date: '2026-05-05',
    readTime: '5 min read',
    tags: ['IndexNow', 'Indexing'],
    content: `
## The crawl queue problem

Traditional search engine indexing works like this: you publish a page, and then you wait. A Googlebot or Bingbot crawler eventually discovers the page — through your sitemap, through a link from another page, or through periodic re-crawling of your domain. This process takes anywhere from a few hours to several days. For large sites with thousands of pages, some content may wait weeks before being indexed.

For news sites, events, time-sensitive promotions, or any business that publishes regularly, this delay is a real problem. A hotel that adds a new special offer wants that offer visible in search results today, not next week.

**IndexNow** solves this directly.

## How IndexNow works

IndexNow is an open protocol developed by Microsoft (Bing) and adopted by Yandex, Seznam, and other search engines. When you publish or update a page, your server sends an HTTP request to the IndexNow endpoint — a simple notification that says: *"this URL has just changed, please re-crawl it now."*

The participating search engines share IndexNow submissions with each other, so a single API call notifies multiple engines at once.

The technical mechanism:

1. You generate a unique API key (a random string)
2. You place a text file at \`yoursite.com/{api-key}.txt\` containing the key — this proves you own the domain
3. When content changes, you send a POST request to \`api.indexnow.org\` with the API key and the URL

The search engine receives the notification and prioritises crawling that URL, typically within minutes.

## What IndexNow does not cover

IndexNow notifies Bing, Yandex, and Seznam. **It does not directly affect Google indexing.** Google has its own inspection API (via Search Console) and its own crawl scheduling. However, Google has signalled interest in IndexNow and partially participates through its membership in the broader ecosystem.

For Google specifically, the best complementary tool is Google Search Console's URL inspection — but that requires manual action per URL. IndexNow is fully automated.

## Who benefits most from IndexNow

- **News and magazine sites** that publish multiple articles per day
- **E-commerce sites** where prices, availability, and products change frequently
- **Event sites** announcing dates, lineups, and ticket availability
- **Hotel and hospitality sites** running limited-time offers
- **Any site** where freshness of content is part of the value proposition

Even for slower-publishing sites, IndexNow ensures that when you do update something important — your homepage, your pricing page, your contact information — search engines know about it immediately.

## IndexNow in AI Boost for Joomla

Setting up IndexNow manually requires generating a key, placing the key file, writing the API call logic, and wiring it to Joomla's content events. For most Joomla site owners, that is an afternoon of work.

**AI Boost for Joomla** handles all of this with a single toggle. Enable IndexNow in the Analytics tab, click "Generate API Key", and save. The plugin:

- Generates a cryptographically random API key
- Serves the key verification file at the correct URL
- Listens to Joomla's \`onContentAfterSave\` events
- Sends an IndexNow notification to Bing, Yandex, and Seznam automatically whenever you publish or update an article

The feature is available on Developer and Agency plans. For sites where content freshness matters, it is one of the fastest wins available in the SEO toolkit.
    `.trim(),
  },
  {
    slug: 'what-is-opengraph',
    title: 'What is OpenGraph — control how your pages look on social media',
    description: 'OpenGraph is a protocol that lets you define exactly how your pages appear when shared on Facebook, LinkedIn, X, and other social platforms — title, description, and image.',
    date: '2026-05-07',
    readTime: '5 min read',
    tags: ['OpenGraph', 'Social Media'],
    content: `
## The preview problem

When someone shares a link on Facebook, LinkedIn, or X (formerly Twitter), the platform automatically generates a preview card — a thumbnail, title, and short description. If you have not configured anything, the platform guesses: it picks whatever image it finds first on the page, takes the first paragraph of text it can scrape, and produces a result that may look completely wrong.

A hotel shares a link to their seasonal offer page. The preview shows their cookie consent banner image and the text from their navigation menu. Not ideal.

**OpenGraph** is the solution.

## What OpenGraph is

OpenGraph (OG) is a protocol created by Facebook in 2010 and now universally supported by every major social platform and messaging app. It uses \`<meta>\` tags in your page's \`<head>\` to explicitly declare:

- **og:title** — the title that should appear in the preview card
- **og:description** — the description text
- **og:image** — the image to use for the preview thumbnail
- **og:url** — the canonical URL of the page
- **og:type** — the type of content (website, article, product, etc.)

When a platform crawls a URL to generate a preview, it reads these tags first and uses them rather than guessing from the page content.

## Why it matters

**Click-through rates.** A well-configured OG image and title dramatically outperforms a randomly scraped image and truncated paragraph. Social sharing is often the primary traffic driver for articles, events, and promotions. A bad preview kills that potential.

**Brand consistency.** Every link shared to your site is an impression. With proper OG tags, every share shows your chosen image — your hero shot, your event poster, your product photo — framed by your headline and description.

**Messaging control.** Different pages serve different purposes. Your homepage, your about page, your latest article, and your pricing page should each have different titles and descriptions when shared. OG tags let you customise each one independently.

## Twitter Cards

Twitter/X uses its own equivalent system called **Twitter Cards**. The tags are slightly different (\`twitter:title\`, \`twitter:description\`, \`twitter:image\`, \`twitter:card\`), but the purpose is identical. The card type \`summary_large_image\` produces the large preview card that performs best for content-driven pages.

In practice, most sites implement both OpenGraph and Twitter Cards. Many platforms fall back to OG tags when Twitter Card tags are absent, but using both ensures correct rendering everywhere.

## Per-article overrides

Site-wide OG configuration — a global default image and description — is the minimum. But the most effective implementation allows each article or page to override the defaults with its own title, description, and image.

For a Joomla blog or news site, this means every article gets its own OG image (ideally the article's featured image) and its own OG description (ideally the article's custom meta description). Each shared link looks distinct and relevant.

## How AI Boost for Joomla handles OpenGraph

**AI Boost for Joomla** injects OpenGraph and Twitter Card meta tags automatically on every page. You configure a global default image (1200×630 pixels is the recommended size) and the plugin handles the rest.

For per-article customisation, the plugin integrates with Joomla's Custom Fields system. Add a custom field for OG image override, OG title override, and OG description override to your article type, and the plugin reads them automatically — no template modifications required.

The Social & Meta tab in the plugin configuration controls all of this: toggle OpenGraph on, upload your default image, and it works across your entire Joomla installation immediately.
    `.trim(),
  },
  {
    slug: 'what-is-robots-txt',
    title: 'What is robots.txt — and why AI crawlers need special rules in 2026',
    description: 'robots.txt is the access control file for web crawlers. In 2026, with 25+ AI bots crawling the web, getting your robots.txt right is more important than ever.',
    date: '2026-05-09',
    readTime: '6 min read',
    tags: ['robots.txt', 'AI Crawlers'],
    content: `
## The original gatekeeping file

Every website that has ever had a \`robots.txt\` file at its root has been using a standard that dates back to 1994. The Robots Exclusion Protocol (REP) was one of the first informal agreements of the web: crawlers should check \`/robots.txt\` before indexing a site and respect the rules they find there.

The file syntax is simple:

\`\`\`
User-agent: *
Disallow: /admin/
Disallow: /private/
Allow: /
\`\`\`

\`User-agent: *\` means "all crawlers". \`Disallow: /admin/\` means "do not crawl anything under /admin/". \`Allow: /\` means "everything else is fine".

Simple in principle. More consequential in 2026.

## The AI crawler explosion

In 2024 and 2025, the number of distinct web crawlers grew dramatically. Every major AI company deployed its own crawler:

- **GPTBot** — OpenAI (ChatGPT, GPT-4)
- **ClaudeBot** — Anthropic (Claude)
- **PerplexityBot** — Perplexity
- **GoogleOther** — Google AI Overviews and Gemini
- **Applebot-Extended** — Apple AI features
- **Bytespider** — ByteDance (TikTok AI)
- **CCBot** — Common Crawl (training data)
- **DataForSeoBot**, **SemrushBot**, **AhrefsBot** — SEO tools

...and dozens more. Some are search crawlers. Some are training data collectors. Some are fact-checking systems.

For many sites, the question is no longer just "which pages can Google access?" but "which of these 25+ crawlers do I want to allow, and under what conditions?"

## The two failure modes

**Failure mode 1: Blocking AI crawlers that bring traffic.**
Some site owners, concerned about AI systems using their content, added \`Disallow: /\` for GPTBot and others. The problem: GPTBot is not just used for training data — it is also used when ChatGPT Browse answers user questions by reading live web pages. Blocking GPTBot means your site cannot be cited in ChatGPT answers.

**Failure mode 2: Allowing all crawlers unconditionally.**
An unrestricted \`robots.txt\` allows every crawler, including aggressive training data harvesters that may stress your server, bypass your terms of service, and use your content in ways you have not authorised.

The correct approach in 2026 is to **explicitly allow the AI search crawlers** (GPTBot, PerplexityBot, ClaudeBot, Googlebot) while blocking or rate-limiting aggressive training crawlers (CCBot, Common Crawl variants).

## The Sitemap declaration

\`robots.txt\` also serves a secondary purpose: it should declare where your sitemap lives:

\`\`\`
Sitemap: https://yoursite.com/sitemap.xml
\`\`\`

This line alone ensures every crawler that reads \`robots.txt\` also knows where to find your complete list of pages.

## Joomla's default robots.txt

Joomla installs a static \`robots.txt\` by default. It is outdated — written for an era when Googlebot and Bingbot were the only crawlers worth considering. It does not mention AI crawlers at all.

Updating it manually means editing a file on your server and maintaining it yourself every time a new crawler appears.

## How AI Boost for Joomla handles robots.txt

**AI Boost for Joomla** replaces Joomla's static \`robots.txt\` with a dynamically generated one. When a crawler requests \`yoursite.com/robots.txt\`, the plugin serves a file that includes:

- Standard Joomla path restrictions (\`/administrator/\`, \`/cache/\`, etc.)
- Explicit \`Allow: /\` directives for 25+ AI search crawlers
- Explicit rules for training data harvesters
- A \`Sitemap:\` declaration pointing to your dynamic sitemap

The file is regenerated automatically when you save plugin settings, and cached for performance. You never need to touch the server file again.

For site owners who want full control, the plugin provides a custom robots.txt field where you can add your own rules that are appended to the generated output.
    `.trim(),
  },
  {
    slug: 'what-is-xml-sitemap',
    title: 'What is an XML Sitemap — the map that helps search engines find every page',
    description: 'An XML sitemap is a structured file that lists every URL on your website, helping search engines and AI crawlers discover and index your content reliably.',
    date: '2026-05-11',
    readTime: '5 min read',
    tags: ['XML Sitemap', 'SEO'],
    content: `
## The discovery problem

Search engines find pages in two ways: by following links from pages they already know about, and by reading sitemaps. Links are unreliable — a page with no inbound links is effectively invisible to crawlers that rely on link-following alone. A sitemap is the direct solution: it tells crawlers exactly which pages exist, regardless of how well-linked they are.

The **XML Sitemap** format, standardised as the Sitemap Protocol and now supported by Google, Bing, and all major search engines, is the definitive way to give crawlers a complete map of your site.

## The sitemap format

A basic XML sitemap looks like this:

\`\`\`xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://yoursite.com/</loc>
    <lastmod>2026-05-01</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc>https://yoursite.com/about</loc>
    <lastmod>2026-04-15</lastmod>
    <changefreq>monthly</changefreq>
    <priority>0.7</priority>
  </url>
</urlset>
\`\`\`

Each \`<url>\` entry contains:
- **loc**: the canonical URL of the page
- **lastmod**: when the page was last modified (helps crawlers prioritise re-crawling)
- **changefreq**: how often the page typically changes (a hint, not a rule)
- **priority**: relative importance within the site (0.0 to 1.0)

## Hreflang in sitemaps

For multilingual sites, sitemaps can include \`xhtml:link\` elements that declare language variants:

\`\`\`xml
<url>
  <loc>https://yoursite.com/about</loc>
  <xhtml:link rel="alternate" hreflang="en" href="https://yoursite.com/en/about"/>
  <xhtml:link rel="alternate" hreflang="de" href="https://yoursite.com/de/about"/>
</url>
\`\`\`

This supplements the \`hreflang\` tags in the page \`<head>\`, giving search engines two independent sources confirming your language structure.

## Why dynamic sitemaps matter

A static sitemap file — one you generate once and upload — goes stale. Every time you publish a new article, the sitemap is outdated. Every time you unpublish a page, the sitemap still points to it.

A **dynamic sitemap** is generated on the fly by your CMS. When a search engine requests \`/sitemap.xml\`, the server queries the database for all published content and returns an always-current response. The sitemap reflects your actual published content at the moment of the request.

For Joomla sites that publish new articles, add menu items, or manage categories regularly, a dynamic sitemap is essential.

## Common sitemap mistakes

**Including non-canonical URLs.** Your sitemap should only list the canonical version of each page. If \`?lang=en\` and the base URL are both valid, list only the one search engines should treat as canonical.

**Setting all priorities to 1.0.** Priority is relative, not absolute. If everything is priority 1.0, the signal is meaningless. Use a range that reflects actual importance.

**Not declaring the sitemap in robots.txt.** Crawlers look for sitemaps in the \`Sitemap:\` declaration in \`robots.txt\` and in your Google Search Console submission. Both are recommended.

**Forgetting large sites need sitemap indices.** A single sitemap file supports a maximum of 50,000 URLs and 50 MB uncompressed. Large sites need a sitemap index file that lists multiple sitemap files.

## How AI Boost for Joomla handles sitemaps

**AI Boost for Joomla** generates a dynamic XML sitemap at \`yoursite.com/sitemap.xml\` with no static file to maintain. It automatically includes published articles, category pages, and menu items from your Joomla installation.

Configuration options let you set per-type priorities and change frequencies, filter specific categories, exclude individual article IDs, and include hreflang entries for multilingual sites. The sitemap updates instantly when you publish, unpublish, or modify content — no manual regeneration needed.
    `.trim(),
  },
  {
    slug: 'what-is-aeo',
    title: 'What is AEO (Answer Engine Optimization) — SEO for the AI era',
    description: 'AEO is the practice of optimising your content to be cited and recommended by AI assistants like ChatGPT, Perplexity, and Google AI Overviews — not just ranked in traditional search.',
    date: '2026-05-13',
    readTime: '7 min read',
    tags: ['AEO', 'AI Search'],
    content: `
## Search is changing

For twenty-five years, the goal of search engine optimisation was straightforward: rank as high as possible in the blue-link results for relevant queries. Position 1 on Google meant the most clicks, the most traffic, and the most revenue.

That model is still valid — but it is no longer the only model that matters.

A growing share of search queries in 2026 are answered not by a list of links, but by a synthesised response from an AI assistant. When someone asks ChatGPT "what is the best way to configure structured data for a hotel website?", they get a direct answer, not ten blue links. When someone asks Perplexity "which Joomla plugins handle Schema.org automatically?", they get a curated recommendation, not a list of sites to visit.

**Answer Engine Optimization (AEO)** is the practice of optimising your content and technical infrastructure so that AI assistants cite, quote, and recommend your site in these synthesised answers.

## How AI assistants decide what to cite

Traditional SEO factors (backlinks, keyword density, domain authority) matter less to AI assistants than they do to Google's PageRank algorithm. AI systems evaluate pages differently:

**Factual clarity.** Can the AI extract clear, specific facts from the page? A page that hedges everything and never commits to a direct statement is harder to cite than a page that says "check-in is at 14:00, check-out is at 12:00".

**Structural clarity.** Is the content organised in a way that maps to questions and answers? Pages with clear headings, FAQ sections, definition lists, and step-by-step guides are structurally easy for AI to process.

**Structured data.** Schema.org markup tells AI crawlers not just what the content says, but what it *means*. FAQPage schema, for example, explicitly marks which paragraphs are questions and which are answers — eliminating ambiguity.

**Authority signals.** AI systems still weight domain authority, publication date, and citation consistency. A site that is mentioned by name across multiple authoritative sources is more likely to be cited.

**Crawl accessibility.** AI crawlers must be able to access your content. A site that blocks GPTBot and PerplexityBot in \`robots.txt\` cannot be cited by those systems, regardless of content quality.

## AEO vs SEO: the relationship

AEO is not a replacement for SEO — it is an extension. The technical foundations overlap significantly:

| Signal | SEO value | AEO value |
|--------|-----------|-----------|
| Fast loading | High | Medium |
| Mobile-friendly | High | Medium |
| Schema.org markup | Medium | Very high |
| FAQPage markup | Medium | Very high |
| llms.txt | None | High |
| Clear factual content | High | Very high |
| Authoritative backlinks | Very high | Medium |
| IndexNow (freshness) | Medium | Medium |

The key difference is emphasis. AEO heavily weights structured data, factual clarity, and AI crawler accessibility — areas that traditional SEO often treated as secondary.

## Practical AEO implementation

**Step 1: Implement Schema.org.** At minimum, implement Organization or LocalBusiness schema with accurate, complete data. Add FAQPage schema to any page with Q&A content. Add Article schema to blog posts and news articles.

**Step 2: Structure content for Q&A.** Use clear headings that match search queries. Use FAQ sections. Provide direct, specific answers to likely user questions.

**Step 3: Create an llms.txt file.** Give AI crawlers an explicit, curated overview of your site's most important content.

**Step 4: Allow AI crawlers.** Review your \`robots.txt\` and ensure major AI crawlers (GPTBot, ClaudeBot, PerplexityBot) have access.

**Step 5: Enable IndexNow.** Fresh content is more likely to be cited accurately. Ensure AI crawlers see your current content, not a cached version from weeks ago.

**Step 6: Build authoritative content.** Write content that directly answers specific questions with specificity and confidence. Vague, hedged content is less citeable.

## AI Boost for Joomla and AEO

**AI Boost for Joomla** was designed from the ground up for the AEO era. Every feature addresses one or more AEO signals:

- **Schema.org generation** (13 site types) — structured data clarity
- **FAQPage auto-detection** — question-and-answer signals
- **llms.txt generator** — AI crawler orientation
- **robots.txt with AI crawler rules** — crawl accessibility
- **IndexNow integration** — content freshness
- **XML Sitemap with hreflang** — complete content discovery

The plugin exists because implementing all of these correctly in Joomla requires deep integration with the CMS — integration that would take weeks to build manually and maintain indefinitely.
    `.trim(),
  },
  {
    slug: 'what-is-json-ld',
    title: 'What is JSON-LD — the structured data format search engines love',
    description: 'JSON-LD is the recommended format for embedding Schema.org structured data in web pages — clean, maintainable, and completely separate from your visible HTML.',
    date: '2026-05-15',
    readTime: '5 min read',
    tags: ['JSON-LD', 'Structured Data'],
    content: `
## Three ways to add structured data

When Schema.org was first proposed, it recommended embedding structured data directly in your HTML using **Microdata** — adding special attributes like \`itemscope\`, \`itemtype\`, and \`itemprop\` to your existing HTML elements.

Later, **RDFa** (Resource Description Framework in Attributes) offered another HTML-attribute-based approach, more expressive but even more verbose.

Both approaches have the same fundamental problem: they require you to modify your HTML template to add semantic attributes to every element. Change your template, and you risk breaking your structured data. Add an element, and you have to remember to annotate it.

**JSON-LD** (JavaScript Object Notation for Linked Data) solves this cleanly.

## What JSON-LD looks like

JSON-LD lives in a \`<script>\` block in your page's \`<head>\`:

\`\`\`html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What is AI Boost for Joomla?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "AI Boost for Joomla is an all-in-one SEO and AEO plugin that generates Schema.org, XML sitemaps, llms.txt, and AI crawler signals automatically."
      }
    }
  ]
}
</script>
\`\`\`

The script tag has type \`application/ld+json\`, which tells browsers to treat it as data, not executable JavaScript. Search engines and AI crawlers read it directly from the parsed HTML.

## Why JSON-LD is the recommended format

Google has explicitly stated that JSON-LD is their preferred format for structured data. The reasons are practical:

**No HTML modification required.** JSON-LD is injected as a separate block, completely independent of your visible HTML. You can change your page template, add or remove elements, redesign entirely — and your structured data stays correct.

**Easy to validate and debug.** The JSON structure is self-contained. You can copy the entire block, paste it into Google's Rich Results Test or Schema.org's validator, and see immediately whether it is correct.

**Multiple schemas on one page.** A page can have multiple \`<script type="application/ld+json">\` blocks. Your article page can simultaneously declare an \`Article\` schema, a \`BreadcrumbList\` schema, and a \`FAQPage\` schema — independently, without interference.

**Server-side generation.** Because JSON-LD is just text output, it can be generated server-side by your CMS and inserted into the page's \`<head>\` at render time. No client-side JavaScript required. No rendering delay.

## Common JSON-LD types and their use cases

| Type | Use case |
|------|----------|
| Organization | Business identity, contact, social profiles |
| LocalBusiness | Physical location, opening hours, address |
| Article | Blog posts, news articles |
| FAQPage | FAQ sections, question-and-answer content |
| Event | Concerts, conferences, appointments |
| Product | E-commerce product pages |
| BreadcrumbList | Navigation path (Home > Blog > Article) |
| Hotel | Accommodation with check-in, amenities |
| Person | Author profiles, professional portfolios |

## Validating your JSON-LD

Google provides the **Rich Results Test** at \`search.google.com/test/rich-results\`. Paste a URL or a code snippet and it shows which rich result types your structured data qualifies for, and any errors or warnings.

For development and debugging, **Schema.org's validator** at \`validator.schema.org\` checks structural correctness independent of rich result eligibility.

## How AI Boost for Joomla generates JSON-LD

**AI Boost for Joomla** generates all Schema.org blocks as JSON-LD, injected into the \`<head>\` of every page. You never write or edit JSON-LD manually.

The plugin reads your configuration (organisation name, address, phone, social profiles, schema type, FAQ data, events, ratings), constructs the correct JSON-LD structure for each page, and outputs it server-side. The result is always syntactically valid and semantically correct — ready to pass the Rich Results Test out of the box.
    `.trim(),
  },
  {
    slug: 'what-is-hreflang',
    title: 'What is hreflang — multilingual SEO explained',
    description: 'hreflang is an HTML attribute (and sitemap element) that tells search engines which language version of a page to show to which users — essential for multilingual websites.',
    date: '2026-05-17',
    readTime: '5 min read',
    tags: ['Hreflang', 'Multilingual'],
    content: `
## The multilingual indexing problem

If your website serves content in multiple languages — English for international visitors, German for German-speaking visitors, French for French-speaking visitors — you face a specific SEO challenge: search engines need to know which version of a page to show to which user.

Without explicit signals, Google may index only one language version, show the wrong language to visitors, or treat the multiple language versions as duplicate content (which can negatively affect rankings).

**Hreflang** is the standard solution.

## What hreflang tells search engines

The hreflang attribute declares the relationship between language versions of the same page. It says: "this page at URL X is the English version; the German version is at URL Y; the French version is at URL Z."

Search engines use this to serve the appropriate language version to users based on their browser language, search language setting, and geographic location.

A German speaker searching in German gets the German version of your page. An English speaker searching in English gets the English version. Without hreflang, both might get the same version — likely whichever Google indexed first.

## The two places hreflang lives

**In the HTML \`<head>\`:**

\`\`\`html
<link rel="alternate" hreflang="en" href="https://yoursite.com/en/about" />
<link rel="alternate" hreflang="de" href="https://yoursite.com/de/about" />
<link rel="alternate" hreflang="fr" href="https://yoursite.com/fr/about" />
<link rel="alternate" hreflang="x-default" href="https://yoursite.com/about" />
\`\`\`

Every language version must include hreflang tags pointing to *all* versions, including itself. The \`x-default\` value marks the fallback version for users whose language is not explicitly listed.

**In the XML Sitemap:**

\`\`\`xml
<url>
  <loc>https://yoursite.com/en/about</loc>
  <xhtml:link rel="alternate" hreflang="en" href="https://yoursite.com/en/about"/>
  <xhtml:link rel="alternate" hreflang="de" href="https://yoursite.com/de/about"/>
</url>
\`\`\`

Using both the \`<head>\` tags and the sitemap entries is redundant but recommended — it gives Google two independent sources confirming the language structure, reducing the chance of misconfiguration going undetected.

## Common hreflang mistakes

**Missing reciprocal links.** If the English page links to the German page but the German page does not link back to the English page, the signal is broken. All language versions must reference all other versions.

**Wrong language codes.** hreflang uses IETF language tags. \`en\` is correct for generic English. \`en-GB\` is British English. \`en-US\` is American English. \`de\` is German. \`de-CH\` is Swiss German. Using the wrong tag means the signal is ignored.

**Forgetting x-default.** The \`x-default\` tag tells Google what to show when no language match is found. Without it, Google has to guess.

**Applying hreflang to non-translatable pages.** Pages like privacy policies, cookie notices, and technical documents that are not actually translated do not need hreflang. Only apply it to pages with genuine language variants.

## Hreflang and Joomla

Joomla supports multilingual sites natively through its content language system. The core installation supports installing multiple languages, and content can be associated with specific languages. Extensions like Falang provide additional field-level translation for structured data.

However, Joomla does not automatically generate hreflang tags. Without a plugin, your multilingual Joomla site gets zero hreflang signals to search engines.

## How AI Boost for Joomla handles hreflang

**AI Boost for Joomla** automatically generates hreflang tags for all installed Joomla languages and injects them into the \`<head>\` of every page. It also adds the corresponding \`xhtml:link\` entries to the XML sitemap.

The plugin reads your Joomla language configuration, identifies which languages are installed and active, and generates the correct IETF language tags (including region-specific codes like \`en-GB\`, \`pt-BR\`, \`zh-CN\`) for every page. It also generates the \`x-default\` tag pointing to your default language version.

For Falang users, the plugin integrates with Falang's field translation system so that per-language structured data (organisation names, descriptions, addresses in local language) is correctly included in the JSON-LD output for each language version.
    `.trim(),
  },
  {
    slug: 'what-is-twitter-cards',
    title: 'What are Twitter Cards — richer previews on X and beyond',
    description: 'Twitter Cards are meta tags that control how your pages appear when shared on X (formerly Twitter) — enabling large image previews, article summaries, and app download cards.',
    date: '2026-05-19',
    readTime: '4 min read',
    tags: ['Twitter Cards', 'Social Media'],
    content: `
## Beyond the plain link

When you share a URL on X (formerly Twitter), the platform has two options: display a plain link with no decoration, or display a **Twitter Card** — a rich preview with an image, title, description, and sometimes interactive elements.

Twitter Cards are controlled by \`<meta>\` tags in your page's \`<head>\`, similar to OpenGraph tags. Where OpenGraph was created by Facebook and is used by most social platforms, Twitter Cards were created by Twitter specifically for its own platform. In practice, most pages implement both.

## Card types

Twitter supports several card types, but two are most useful for content websites:

**summary** — A small thumbnail image alongside title and description. Good for pages without a strong visual focus (contact pages, about pages, documentation).

**summary_large_image** — A large banner image above the title and description. This is the format that performs best for articles, blog posts, landing pages, and any content with a strong visual component. The image dominates the preview card in the X feed.

**app** — A card for mobile app download links. Specific to app store marketing.

**player** — A card with an embedded video or audio player. Requires approval from X.

For most content sites, \`summary_large_image\` is the right choice for pages with featured images, and \`summary\` for pages without.

## The essential tags

\`\`\`html
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="What is Schema.org" />
<meta name="twitter:description" content="Schema.org is the shared vocabulary..." />
<meta name="twitter:image" content="https://yoursite.com/images/schema-og.jpg" />
<meta name="twitter:site" content="@aiboostnow" />
\`\`\`

- **twitter:card** — declares the card type (required)
- **twitter:title** — the title shown in the preview
- **twitter:description** — up to 200 characters of description
- **twitter:image** — the preview image (minimum 300×157 pixels; 1200×630 is ideal for summary_large_image)
- **twitter:site** — your X handle (optional but recommended for attribution)

## Fallback to OpenGraph

X's card system falls back to OpenGraph tags when Twitter Card tags are absent. If your page has \`og:title\` and \`og:image\` but no \`twitter:title\` or \`twitter:image\`, X will use the OG values.

This means implementing OpenGraph correctly gives you baseline Twitter Card support without duplication. Adding explicit Twitter Card tags lets you customise the X-specific presentation independently — for example, writing a shorter title optimised for X's character limits while keeping a longer title for Facebook.

## Validating Twitter Cards

X provides a Card Validator at \`cards-dev.twitter.com/validator\`. Enter a URL and it shows how the card will appear, and any errors or missing tags.

Note: The validator requires your page to be publicly accessible. Local development environments or password-protected staging sites cannot be validated through it.

## Image specifications

For \`summary_large_image\`:
- Recommended size: **1200 × 630 pixels**
- Minimum size: 300 × 157 pixels
- Maximum file size: 5 MB
- Formats: JPG, PNG, WebP, GIF (static only)
- Aspect ratio: 2:1

The image must be served over HTTPS. X will not display HTTP image URLs.

## How AI Boost for Joomla handles Twitter Cards

**AI Boost for Joomla** generates both OpenGraph and Twitter Card meta tags from a single configuration. You set a default OG image, toggle OpenGraph on, and both sets of tags are injected on every page automatically.

The plugin sets \`twitter:card\` to \`summary_large_image\` by default — the format that performs best for most content types. Per-article image overrides (configured through Joomla Custom Fields) apply to both OG and Twitter Card tags simultaneously, so each article shows its own preview image across all social platforms with a single custom field configuration.
    `.trim(),
  },
  {
    slug: 'how-to-appear-in-google-ai-overview',
    title: 'How to appear in Google AI Overview — a practical guide for website owners',
    description: 'Google AI Overview now appears above traditional results for millions of queries. Here is what determines whether your site gets cited — and what you can do about it.',
    date: '2026-05-12',
    readTime: '7 min read',
    tags: ['AI Search', 'Google', 'AEO'],
    content: `
## What is Google AI Overview

Since mid-2024, Google has been showing AI-generated summaries at the very top of search results for a growing range of queries. These summaries — called **AI Overview** (previously called SGE, Search Generative Experience) — answer the user's question directly, citing a small number of sources below the summary.

If your website is one of those cited sources, you get a link, a snippet, and visibility that places you above all traditional organic results. If you are not cited, you are competing for positions that appear below an answer box that already satisfied the user's intent.

Understanding how to get into AI Overview is one of the most valuable SEO skills you can develop right now.

## How Google selects AI Overview sources

Google has not published a definitive technical specification, but research and observation since 2024 reveal consistent patterns:

**Authoritative, entity-rich content.** Pages that clearly establish who the author or business is, what their expertise is, and what the page is about get cited more than anonymous content. Schema.org structured data — particularly \`Organization\`, \`Person\`, and \`Article\` types — helps Google establish this entity context.

**FAQ and question-answer structures.** AI Overview often answers questions. Pages that explicitly structure content as questions and answers — through \`FAQPage\` Schema, HTML \`<details>\` accordions, or clear \`<h3>\` question / \`<p>\` answer patterns — are disproportionately cited for question-type queries.

**Freshness signals.** AI Overview favors recently updated content, particularly for time-sensitive queries. Your \`dateModified\` in Article Schema, your \`lastmod\` in the XML sitemap, and your IndexNow submissions all contribute to freshness signals.

**Topical relevance and depth.** Thin content rarely appears in AI Overview. Pages that cover a topic comprehensively — including related subtopics, definitions, examples, and practical steps — perform significantly better.

**Strong E-E-A-T signals.** Experience, Expertise, Authoritativeness, and Trustworthiness. This means: author attribution with \`Person\` Schema, business identity via \`Organization\` Schema, social profile links in \`sameAs\`, aggregate ratings from verified platforms, and a clean link profile.

## Structured data is not optional for AI Overview

AI systems need unambiguous signals. Structured data is the clearest signal you can give. For local businesses, a complete \`LocalBusiness\` or \`Hotel\` schema with address, phone, opening hours, and GPS coordinates gives Google everything it needs to cite your business for location-based queries.

For content sites, \`Article\` Schema with \`author\`, \`datePublished\`, \`dateModified\`, and \`description\` tells Google that this is a fresh, attributed piece of expertise — not an anonymous wall of text.

\`FAQPage\` Schema is particularly powerful. When a user asks a question and your page has that question (and answer) marked up in \`FAQPage\` Schema, Google has a machine-readable, extractable answer ready. That is exactly what AI Overview needs.

## What llms.txt adds to the picture

Beyond structured data, \`llms.txt\` gives AI systems a high-level map of your site before they crawl individual pages. A well-crafted \`llms.txt\` that describes your expertise, links to your authoritative pages, and presents your content architecture clearly helps AI systems (including Google's AI infrastructure) understand what your site is the go-to resource for.

## Practical checklist for AI Overview eligibility

- Implement Schema.org structured data (Organization, Article, FAQPage at minimum)
- Add \`FAQPage\` Schema to pages that answer questions
- Keep your \`dateModified\` accurate — update it when you genuinely update content
- Submit updated pages via IndexNow for faster indexing
- Add \`llms.txt\` to give AI crawlers a site-level orientation
- Include author attribution with \`Person\` Schema on article pages
- Add \`AggregateRating\` from real third-party review platforms
- Ensure your \`robots.txt\` explicitly allows Googlebot and AI crawlers

## What AI Boost for Joomla does automatically

**AI Boost for Joomla** handles the entire structured data layer without you writing a single line of JSON-LD. It generates \`Organization\`, \`LocalBusiness\`, \`Hotel\`, \`Article\`, and \`FAQPage\` schemas automatically — including auto-detection of FAQ patterns in your article content. It also generates \`llms.txt\`, manages your \`robots.txt\` crawler permissions, submits pages via IndexNow, and injects \`Article\` Schema with fresh \`dateModified\` values from your Joomla database.

The result is a site that gives Google's AI systems everything they need to cite you confidently.
    `.trim(),
  },
  {
    slug: 'joomla-seo-checklist-2026',
    title: 'Joomla SEO checklist 2026 — 20 things to do before you go live',
    description: 'A practical 20-point checklist covering structured data, sitemaps, crawl permissions, social meta, analytics, and AI search signals — everything a Joomla site needs in 2026.',
    date: '2026-05-14',
    readTime: '8 min read',
    tags: ['Joomla', 'SEO', 'Checklist'],
    content: `
## Why this checklist exists

Launching a Joomla site in 2026 without SEO foundations is expensive to fix later. Structured data, sitemaps, and crawler permissions are far easier to configure before launch than to retrofit after thousands of pages are already indexed incorrectly.

This checklist covers all the essentials — not just for traditional Google SEO, but for AI search engines (ChatGPT, Perplexity, Google AI Overview) that are now a significant traffic source for many sites.

Work through each item before you go live or as an audit of an existing site.

---

## Technical foundation

**1. Verify robots.txt is configured correctly**

Visit \`yoursite.com/robots.txt\`. It should allow Googlebot and major AI crawlers. It should block \`/administrator/\`, \`/cache/\`, \`/tmp/\`, and other Joomla system paths. It should include a \`Sitemap:\` line pointing to your sitemap URL.

**2. Submit your XML sitemap to Google Search Console**

Your sitemap should be at \`yoursite.com/sitemap.xml\`. Log in to Google Search Console, go to **Sitemaps**, and submit it. This tells Google where all your pages are — it does not wait to find them by crawling links.

**3. Submit your sitemap to Bing Webmaster Tools**

Bing is separate from Google. Create a Bing Webmaster Tools account, verify your site, and submit your sitemap. Bing powers AI Overview features in Microsoft Copilot.

**4. Verify your canonical tags**

Every page should have a \`<link rel="canonical">\` tag pointing to its canonical URL. This prevents duplicate content issues when Joomla generates the same content at multiple URLs (e.g., with and without trailing slash, with and without query parameters).

**5. Check page speed (Core Web Vitals)**

Use Google PageSpeed Insights or Lighthouse. Target LCP (Largest Contentful Paint) under 2.5 seconds, CLS (Cumulative Layout Shift) under 0.1, and INP (Interaction to Next Paint) under 200ms. Slow pages rank lower and are less likely to be cited in AI Overview.

---

## Schema.org structured data

**6. Implement Organization or LocalBusiness Schema**

Every Joomla site should have at minimum an \`Organization\` schema on the homepage with: name, URL, logo, contact email, and \`sameAs\` links to your social profiles. Physical businesses should use \`LocalBusiness\` with address, phone, and GPS coordinates.

**7. Add Article Schema to blog and news pages**

Article pages need: \`headline\`, \`author\` (with \`Person\` type), \`datePublished\`, \`dateModified\`, and \`image\`. This is required for Google News inclusion and significantly improves AI Overview eligibility.

**8. Implement FAQPage Schema where applicable**

Any page with a question-and-answer section should have \`FAQPage\` Schema. This is one of the highest-impact structured data types for AI search citations.

**9. Validate with Google's Rich Results Test**

After implementing Schema, test at \`search.google.com/test/rich-results\`. Fix any errors — missing required fields, incorrect nesting, or invalid property values.

---

## AI search signals

**10. Generate llms.txt**

Place an \`llms.txt\` file at \`yoursite.com/llms.txt\` describing your site in a format optimised for AI assistants. This helps ChatGPT, Perplexity, and Claude understand what your site is about before they crawl it.

**11. Verify AI crawlers are allowed**

Check that GPTBot, ClaudeBot, PerplexityBot, and other major AI crawlers are allowed in your robots.txt. Many Joomla sites accidentally block them with broad Disallow rules.

**12. Enable IndexNow**

Set up IndexNow to notify Bing, Yandex, and Seznam the instant you publish new content. Pages submitted via IndexNow are typically indexed within minutes rather than days.

---

## Social and meta

**13. Set up OpenGraph tags**

Every page needs \`og:title\`, \`og:description\`, \`og:image\`, and \`og:url\`. The OG image should be 1200×630 pixels. Without OG tags, social platforms generate ugly, random previews when anyone shares your content.

**14. Set up Twitter Cards**

Add \`twitter:card\`, \`twitter:title\`, \`twitter:description\`, and \`twitter:image\` tags. Use \`summary_large_image\` for content pages.

**15. Verify per-article OG overrides work**

Test that blog and news articles show their own OG image (not the site-wide default) when shared on social media. Use the Facebook Sharing Debugger to verify.

---

## Analytics and verification

**16. Install Google Analytics 4**

Set up a GA4 property and implement the tracking code via Google Tag Manager or directly. Verify that pageviews are recording in GA4's Realtime report.

**17. Set up Google Search Console**

Verify your site in Google Search Console using the HTML meta tag method. This is the primary tool for monitoring your search performance, indexing issues, and structured data errors.

**18. Verify the robots meta tag**

Ensure your pages do not have \`<meta name="robots" content="noindex">\` unless intentional. A common Joomla mistake is leaving noindex enabled from development. Check the Joomla Global Configuration → Metadata settings.

---

## Content quality

**19. Write meta descriptions for key pages**

Meta descriptions (150–160 characters) do not directly affect rankings, but they control what appears in search snippets. Write them for your homepage, category pages, and top content pages.

**20. Set hreflang if you have multiple languages**

For multilingual Joomla sites, implement hreflang tags for all language versions. Every language version must reference all other versions, including \`x-default\`. Without hreflang, Google may serve the wrong language to users.

---

## Making this checklist automatic

Most of these 20 items can be automated with a single plugin. **AI Boost for Joomla** handles items 1–2 (robots.txt + sitemap), 6–9 (Schema.org), 10–12 (llms.txt, AI crawler permissions, IndexNow), 13–15 (OpenGraph + Twitter Cards), and 20 (hreflang) automatically — leaving you to focus on content quality, page speed, and analytics.
    `.trim(),
  },
  {
    slug: 'what-is-rich-snippet',
    title: 'What is a rich snippet — and how structured data creates them',
    description: 'Rich snippets are enhanced search results that show star ratings, FAQ dropdowns, event dates, and prices directly in Google. Here is exactly how they work and how to get them.',
    date: '2026-05-16',
    readTime: '6 min read',
    tags: ['Rich Snippets', 'Schema.org', 'Google'],
    content: `
## The difference between a regular result and a rich snippet

A standard Google search result shows three things: a blue link title, a green URL, and a short description snippet. That has been the default for over two decades.

A **rich snippet** adds visual enhancements — star ratings, review counts, prices, availability, event dates, FAQ dropdowns, recipe details, or images — directly into the search result listing, before the user even clicks.

These enhancements are not styled guesses by Google. They come from structured data that you embed in your page's HTML — specifically, from **Schema.org JSON-LD markup** that tells Google precisely what type of content the page contains and what its key properties are.

## Why rich snippets matter

**Higher click-through rates.** A search result with five gold stars and "4.8 (2,341 reviews)" consistently outperforms a plain text result, even when the plain result ranks higher. Studies show CTR improvements of 20–40% for results with star ratings.

**More search result real estate.** A FAQ rich result with three expanded question-answer pairs takes up dramatically more vertical space than a standard result. More space means more visibility.

**Better qualified traffic.** When users see the price range, opening hours, or event date in the search result, they know what they are clicking into. This tends to improve the quality of traffic — fewer people bouncing because the page was not what they expected.

## The main rich snippet types for 2026

| Type | What it shows | Schema required |
|------|--------------|-----------------|
| Review / Rating | Stars, score, review count | \`AggregateRating\` |
| FAQ | Expandable Q&A items under the result | \`FAQPage\` |
| Event | Date, location, ticket status | \`Event\` |
| Product | Price, availability, rating | \`Product\` |
| Recipe | Time, calories, rating | \`Recipe\` |
| Article / News | Date, author, publisher | \`Article\` / \`NewsArticle\` |
| Breadcrumb | Hierarchical site path | \`BreadcrumbList\` |
| Local Business | Address, phone, hours | \`LocalBusiness\` |

## How structured data triggers rich snippets

Google reads your structured data when it crawls your page. If the data is correctly formatted, relevant to the page content, and complies with Google's content policies, Google may show a rich snippet for queries where that data is relevant.

The word "may" is important — structured data makes you **eligible** for rich snippets, it does not guarantee them. Google decides when and where to show them based on query intent, result quality, and space on the page.

The structured data must be:

1. **Accurate** — it must describe the actual content of the page, not different content
2. **Complete** — required fields for each type must be present
3. **Correctly formatted** — valid JSON-LD with proper nesting and property types
4. **Not spammy** — fabricated ratings, missing content, or manipulative markup will result in manual or algorithmic penalties

## FAQ rich snippets — the most accessible type

For most content sites, \`FAQPage\` Schema is the quickest path to rich snippet eligibility. If your page has a section with questions and answers — even a simple FAQ at the bottom of an article — marking it up with \`FAQPage\` Schema can produce an expandable Q&A block directly in the search result.

\`\`\`json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "How do I install AI Boost for Joomla?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Download the ZIP from aiboostnow.com, install via the Joomla Extension Manager, and enable the plugin."
      }
    }
  ]
}
\`\`\`

The FAQ rich snippet is particularly valuable because it captures additional vertical space without requiring a higher ranking position.

## Star rating rich snippets — what you need

Star ratings appear in rich snippets only for specific content types: Products, Recipes, Events, Local Businesses (via Google Business Profile), and some Review-type pages. They do **not** appear for generic Organization schemas or arbitrary websites.

For local businesses, the rating typically comes from Google Business Profile, not your Schema markup. But adding \`AggregateRating\` to your \`LocalBusiness\` Schema with a verifiable rating source (Booking.com, TripAdvisor, etc.) signals that verifiable third-party data exists and can reinforce your profile.

## Validating your rich snippet eligibility

1. Go to [Google's Rich Results Test](https://search.google.com/test/rich-results)
2. Enter your page URL (or paste the HTML directly)
3. See which rich result types your page is eligible for
4. Fix any errors or warnings

The tool shows you exactly which fields are missing, which values are incorrect, and which rich result types your structured data qualifies for. Run it every time you implement or change structured data.

## How AI Boost for Joomla creates rich snippet eligibility

**AI Boost for Joomla** generates Schema.org markup automatically for every page on your Joomla site. It creates:

- \`Organization\` or \`LocalBusiness\` schema with complete identity data
- \`Article\` schema with author, dates, and publisher for blog/news pages
- \`FAQPage\` schema by auto-detecting Q&A patterns in article content
- \`Event\` schema for event pages
- \`AggregateRating\` when you supply verified third-party rating data

Every schema it generates passes Google's Rich Results Test out of the box — valid JSON-LD, correct property types, required fields present.
    `.trim(),
  },
  {
    slug: 'how-chatgpt-decides-what-to-recommend',
    title: 'How ChatGPT decides which websites to recommend — and how to be one of them',
    description: 'When ChatGPT recommends a website or cites a source, it is not random. Here is what drives those recommendations and what website owners can do to influence them.',
    date: '2026-05-19',
    readTime: '7 min read',
    tags: ['AI Search', 'ChatGPT', 'AEO'],
    content: `
## ChatGPT is now a traffic source

For years, website traffic came from three places: organic search, social media, and direct/email. In 2025 and 2026, a fourth channel has become meaningful: **AI assistants**.

ChatGPT, Perplexity, Claude, and Microsoft Copilot are now used by millions of people to research products, find services, answer questions, and compare options. When these systems recommend your website — or cite it as a source — they send real traffic with high intent.

Understanding how to appear in those recommendations is the next frontier of digital marketing.

## How ChatGPT sources its answers

ChatGPT operates in two modes:

**Training data mode** — the model answers from knowledge baked in during training. This knowledge has a cutoff date and does not include your latest content.

**Browse mode (web search)** — ChatGPT retrieves live web pages to answer the query. This is what matters for website owners. When a user asks ChatGPT a question that requires current information, ChatGPT fetches relevant pages and synthesises its answer from them.

The browse mode uses **GPTBot** — OpenAI's web crawler — to index pages, and a search layer to retrieve relevant content at query time.

## What signals GPTBot and ChatGPT look for

**Clear entity identification.** ChatGPT needs to know who you are before it can confidently recommend you. A complete \`Organization\` or \`LocalBusiness\` Schema.org block — with your business name, URL, description, phone, address, and \`sameAs\` links to your social profiles — tells ChatGPT's crawlers that this is a real, identifiable entity, not an anonymous page.

**Explicit topic coverage.** ChatGPT searches for pages that directly address the user's query. A page titled "Hotel Schema.org setup guide for Joomla" signals relevance for queries about Hotel structured data in Joomla far more clearly than a generic "Joomla SEO tips" page.

**Structured Q&A content.** When a user asks a question, ChatGPT prefers sources that contain an explicit answer to that question — not just related content. \`FAQPage\` Schema, clear heading structures, and direct question-answer paragraphs all make it easier for AI systems to extract and present your content.

**Freshness and update signals.** ChatGPT's browse mode prefers fresh content. Your \`dateModified\` in Article Schema, your IndexNow submissions, and your XML sitemap's \`lastmod\` values all signal recency. Outdated pages with stale information are less likely to be cited.

**Crawlability.** If your \`robots.txt\` blocks GPTBot, ChatGPT's crawler cannot index your pages. Check that \`User-agent: GPTBot\` is followed by \`Allow: /\`, not \`Disallow: /\`.

## The llms.txt advantage

When GPTBot crawls your site, it starts by reading \`llms.txt\` if it exists. This file is a structured, machine-readable summary of your site — who you are, what you cover, and where your most important content lives.

Think of it as a curated introduction that you give to AI crawlers instead of forcing them to figure it out by crawling every page. A well-written \`llms.txt\` ensures that ChatGPT understands your site's expertise before it even reads a single article.

Without \`llms.txt\`, an AI crawler arrives cold. It may misidentify your primary focus, miss your best content in deep URLs, or associate your site with tangential topics it encountered first.

## Content strategies that get cited

**Answer questions explicitly.** The most-cited pages in AI systems are those that provide clear, direct answers. Structure your content with question headings and immediate answers, not pages that bury the answer in four paragraphs of preamble.

**Be specific and verifiable.** AI systems prefer concrete, specific claims over vague generalities. "Our hotel has 47 rooms and a 4.6/5 rating on Booking.com from 2,341 guests" is citable. "Our hotel offers a great stay" is not.

**Cover your topic completely.** Thin pages with 200 words of content are rarely cited. Comprehensive resources that address the topic from multiple angles, include examples, and anticipate follow-up questions perform significantly better.

**Use Schema.org for everything factual.** Every factual claim — your business name, your address, your opening hours, your rating — should be in structured data as well as in the page content. Schema provides machine-readable verification for what your text asserts.

## What AI Boost for Joomla does for AI citation readiness

**AI Boost for Joomla** addresses every signal that GPTBot and other AI crawlers look for:

- **robots.txt** — explicitly allows GPTBot, ClaudeBot, PerplexityBot, and 22 other AI crawlers
- **Schema.org** — generates \`Organization\`, \`LocalBusiness\`, \`Article\`, and \`FAQPage\` JSON-LD automatically
- **llms.txt** — generates a dynamic \`llms.txt\` from your Joomla configuration and sitemap
- **IndexNow** — submits pages to search engines on publication for faster AI indexer discovery
- **Sitemap** — generates a complete XML sitemap with accurate \`lastmod\` timestamps

The combination of these signals makes your Joomla site one that AI assistants can confidently identify, index, and recommend.
    `.trim(),
  },
  {
    slug: 'schema-org-for-restaurants',
    title: 'Schema.org for Restaurants — a complete setup guide for Joomla',
    description: 'A step-by-step guide to implementing Restaurant Schema.org on a Joomla site — covering the right types, required fields, menu markup, and how to validate your output.',
    date: '2026-05-21',
    readTime: '7 min read',
    tags: ['Schema.org', 'Restaurant', 'Joomla'],
    content: `
## Why Restaurant structured data matters

When someone searches for "Italian restaurant near me" or asks ChatGPT "what's a good restaurant in New York?", the AI systems and search engines that respond need structured, machine-readable information to answer confidently.

A restaurant website without Schema.org structured data is invisible to this layer of the web. The AI cannot verify your cuisine type, your opening hours, your location, your price range, or your rating — so it either does not recommend you, or cites less reliable sources (review platforms, directory listings) instead.

Restaurant Schema.org is also one of the most impactful structured data implementations for traditional SEO: it enables the rich Knowledge Panel in Google Search, the opening hours display, the price range indicator, and the review stars.

## The correct Schema.org type for restaurants

Use \`Restaurant\` — which extends \`FoodEstablishment\`, which extends \`LocalBusiness\`. You do not need to declare all three; declaring \`Restaurant\` is sufficient because Schema.org types are hierarchical.

\`\`\`json
{
  "@context": "https://schema.org",
  "@type": "Restaurant",
  "name": "Acme Bistro",
  "url": "https://acme-bistro.com",
  "telephone": "+1 212 555 0123",
  "servesCuisine": "Italian",
  "priceRange": "$$",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 W 44th St",
    "addressLocality": "New York",
    "addressCountry": "US",
    "postalCode": "10001"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 40.7527,
    "longitude": -73.9772
  },
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday"],
      "opens": "12:00",
      "closes": "23:00"
    },
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": ["Friday", "Saturday"],
      "opens": "12:00",
      "closes": "00:00"
    },
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": "Sunday",
      "opens": "13:00",
      "closes": "22:00"
    }
  ]
}
\`\`\`

## Required and recommended fields

| Field | Required | Description |
|-------|----------|-------------|
| \`name\` | Yes | Restaurant name |
| \`address\` | Yes | Full postal address |
| \`telephone\` | Recommended | International format |
| \`url\` | Recommended | Your website URL |
| \`servesCuisine\` | Recommended | Cuisine type (e.g., "Italian", "Serbian") |
| \`priceRange\` | Recommended | $ to $$$$ |
| \`openingHoursSpecification\` | Recommended | Day-by-day hours |
| \`geo\` | Recommended | GPS coordinates |
| \`image\` | Recommended | Photo of the restaurant |
| \`aggregateRating\` | Optional | Star rating with review count |
| \`menu\` | Optional | URL of the menu page |
| \`acceptsReservations\` | Optional | Boolean |
| \`hasMap\` | Optional | Google Maps URL |

## Adding aggregate ratings

If your restaurant has verified reviews on TripAdvisor, Google, or another platform, include \`aggregateRating\`:

\`\`\`json
"aggregateRating": {
  "@type": "AggregateRating",
  "ratingValue": "4.7",
  "reviewCount": "892",
  "bestRating": "5",
  "worstRating": "1"
}
\`\`\`

**Important:** Only use real, verifiable ratings. Google's policies prohibit self-authored ratings or fabricated review counts. Violations can result in rich result ineligibility.

## Menu markup

If you have a digital menu on your site, link to it in the schema:

\`\`\`json
"menu": "https://acme-bistro.com/menu",
"hasMenu": {
  "@type": "Menu",
  "name": "Main Menu",
  "url": "https://acme-bistro.com/menu"
}
\`\`\`

For full menu item markup (individual dishes with descriptions and prices), use \`MenuSection\` and \`MenuItem\` types. This is advanced but can produce rich results for specific dish queries.

## The social and meta layer

Beyond Schema.org, restaurant sites benefit from complete OpenGraph and Twitter Card implementation. When anyone shares your website on Facebook, Instagram Stories, or X, the preview card should show your best photo, your restaurant name, and a compelling description — not a random thumbnail and truncated URL.

Recommended OG image: a high-quality food or interior photo at 1200×630 pixels.

## Implementing on Joomla

Implementing Restaurant Schema.org manually on a Joomla site requires editing templates, writing PHP, or managing custom modules. For most restaurant owners, that is not realistic.

**AI Boost for Joomla** provides a Restaurant preset that configures the correct Schema.org type and all recommended fields through a user-friendly admin interface. You fill in your restaurant name, address, phone, cuisine type, price range, and opening hours through a form — the plugin generates and injects the correct JSON-LD automatically.

The Business Hours widget in AI Boost provides a 7-row weekly table (Mon–Sun) for setting opening hours, with support for individual day configuration, closed day marking, and automatic \`openingHoursSpecification\` generation.

After saving, visit your homepage, view source, find the \`application/ld+json\` block, and validate it at [Google's Rich Results Test](https://search.google.com/test/rich-results). A correctly configured restaurant schema passes the test and qualifies for the Local Business rich result panel.
    `.trim(),
  },
  {
    slug: 'why-allow-ai-crawlers',
    title: 'Why You Should Allow AI Crawlers on Your Joomla Website',
    description: 'Most Joomla websites accidentally block GPTBot, PerplexityBot, and ClaudeBot through their robots.txt — meaning ChatGPT, Perplexity, and Google AI Overview cannot cite them. Here is how to fix it.',
    date: '2026-05-20',
    readTime: '6 min read',
    tags: ['robots.txt', 'AI Crawlers', 'AEO'],
    content: `
## The new reality of web search

In 2026, getting traffic from the web means more than ranking in Google. ChatGPT, Perplexity, Microsoft Copilot, and Google AI Overview are now actively sending visitors to websites — but only to websites they can access, read, and understand.

If your Joomla site is blocking AI crawlers, those systems cannot see your content. You will never appear in their answers, no matter how good your content is.

The surprising truth: most Joomla sites are already blocking AI crawlers without knowing it.

## What AI crawlers are

AI crawlers are automated bots that read web pages on behalf of AI search engines. When a user asks ChatGPT "what is the best hotel in New York?", ChatGPT's crawler (GPTBot) has already visited thousands of hotel websites, read their content, and stored what it found. It uses that content to construct its answer.

Here are the main AI crawlers you need to know:

| Crawler | Powers | What it does |
|---------|--------|--------------|
| GPTBot | ChatGPT (Browse mode) | Reads live pages for real-time answers |
| PerplexityBot | Perplexity AI | Crawls and cites sources in answers |
| ClaudeBot | Anthropic Claude | Web lookup and research |
| Googlebot | Google AI Overview, Gemini | Standard Google crawler |
| Bingbot | Microsoft Copilot | Powers Bing and Copilot answers |
| Google-Extended | Google AI training | Used for Gemini model training |

Each of these crawlers visits your site, reads your pages, and uses that information when generating AI-powered answers. If they cannot reach your site, you cannot be cited.

## Why so many sites accidentally block them

When Joomla installs, it creates a default \`robots.txt\` file. This file was written years ago, when Googlebot and Bingbot were the only crawlers that mattered. It has broad \`Disallow:\` rules that block system paths like \`/administrator/\` — but many site owners add additional rules that inadvertently block everything:

\`\`\`
User-agent: *
Disallow: /
\`\`\`

This catch-all rule blocks every crawler, including AI ones. Many site owners add rules like this to their staging environment and forget to remove them on production. Others copy \`robots.txt\` configurations from old tutorials that predate AI crawlers entirely.

Even a correctly structured Joomla \`robots.txt\` with no explicit \`Allow:\` statements for AI crawlers can cause problems with some implementations. The safest approach is always to be explicit.

## The correct robots.txt configuration

The fix is straightforward. Your \`robots.txt\` should explicitly allow the major AI crawlers:

\`\`\`
User-agent: *
Disallow: /administrator/
Disallow: /cache/
Disallow: /tmp/
Disallow: /installation/
Allow: /

User-agent: GPTBot
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: Googlebot
Allow: /

User-agent: Bingbot
Allow: /

Sitemap: https://yoursite.com/sitemap.xml
\`\`\`

The explicit \`Allow: /\` directives for each AI crawler ensure they are never accidentally caught by a broad \`Disallow:\` rule. The \`Sitemap:\` declaration gives every crawler that reads \`robots.txt\` a direct pointer to all your pages.

Check your current \`robots.txt\` by visiting \`yoursite.com/robots.txt\` in a browser. Look for any \`Disallow: /\` rules that apply to \`User-agent: *\` without corresponding \`Allow:\` rules for the AI crawlers listed above.

## What about crawlers you do NOT want?

Not all AI crawlers are equal. Some — like GPTBot and PerplexityBot — power live answer systems that can send you traffic. Others — like CCBot (Common Crawl) — primarily harvest content for AI training datasets.

You may want to allow the first group and block the second. Your \`robots.txt\` can be specific:

\`\`\`
User-agent: GPTBot
Allow: /

User-agent: PerplexityBot
Allow: /

User-agent: CCBot
Disallow: /
\`\`\`

This lets AI search engines cite you while preventing your content from being used for model training without your consent.

## How llms.txt complements robots.txt

Allowing AI crawlers is the minimum. The next step is actively helping them understand your site.

\`llms.txt\` is a plain text file at \`yoursite.com/llms.txt\` that provides AI assistants with a structured overview of your site — who you are, what you do, and where your most important content lives. Think of it as a guided introduction for AI engines:

\`\`\`
# Acme Hotel Manhattan

> A 4-star hotel in the heart of Manhattan, New York, USA, offering luxury rooms and conference facilities.

## Key pages

- [Home](https://acme-hotel.com/): Hotel overview and booking
- [Rooms](https://acme-hotel.com/rooms): Room types and pricing
- [Restaurant](https://acme-hotel.com/restaurant): Dining and bar

## About

Acme Hotel has been in operation since 1999 and accommodates both leisure and business travellers.
\`\`\`

Without \`llms.txt\`, an AI crawler arrives cold and has to guess your site's structure. With it, you control the first impression — highlighting your most authoritative pages and your primary expertise.

## The strategic case for acting now

Every week your site is blocked to AI crawlers is a week your competitors who did allow access are being cited in ChatGPT and Perplexity answers that you are missing.

AI search engines build their understanding of the web incrementally. Sites that have been accessible and well-documented for months have a meaningful advantage over sites that only start opening up later. The cost of fixing your \`robots.txt\` today is 10 minutes. The cost of having been blocked for six months is six months of missed AI citations.

## How AI Boost for Joomla handles all of this automatically

Maintaining a correct \`robots.txt\` as new AI crawlers appear, and keeping a \`llms.txt\` in sync with your site's content, are ongoing tasks if done manually.

**AI Boost for Joomla** handles both automatically:

- **Dynamic robots.txt** — generates and serves a \`robots.txt\` that explicitly allows 25+ AI crawlers, blocks Joomla system paths, and includes your sitemap declaration
- **Dynamic llms.txt** — generates a \`llms.txt\` from your organisation details and sitemap, updated automatically when your content changes
- **AI Crawlers widget** — gives you a per-crawler toggle so you can allow search crawlers while blocking training data harvesters

For the full technical details of how the plugin manages AI crawler permissions and llms.txt, see the [AEO & AI Visibility documentation](/docs/aeo).
    `.trim(),
  },
]

export function getBlogPost(slug: string): BlogPost | undefined {
  return blogPosts.find(p => p.slug === slug)
}

export function getAllTags(): string[] {
  const tags = new Set<string>()
  blogPosts.forEach(p => p.tags.forEach(t => tags.add(t)))
  return Array.from(tags).sort()
}
