/**
 * Customer-facing changelog — the single source of truth for the in-admin
 * "What's New" page. The website /changelog page (built with the site redesign)
 * mirrors these same entries.
 *
 * Conventions:
 *  - Newest release first.
 *  - Versioning starts at 1.0.0; pre-1.0 development history is intentionally
 *    omitted (it never shipped to customers).
 *  - en-GB, plain language — describe the benefit, not the implementation.
 *  - `date` may be '' for an unreleased entry; the page shows "Latest" instead.
 *  - Add a new entry here in the same commit that bumps to that version.
 */
export const CHANGELOG = [
  {
    version: '1.0.0',
    date: '', // set to the release date (YYYY-MM-DD) when 1.0.0 ships
    highlights: [
      'First public release of AI Boost for Joomla.',
    ],
    added: [
      'Schema.org structured data for 36 business types, plus FAQ, HowTo and event schema.',
      'XML sitemap with multilingual hreflang, OpenGraph & Twitter cards, robots.txt control and an llms.txt file for AI search engines.',
      'Guided Quick Setup wizard to configure the essentials in a few minutes.',
      'Health checks with one-click fixes, a Conflict Manager for other SEO plugins, and a Dashboard that surfaces the most important issues at a glance.',
      'Settings import / export so you can back up and move your configuration.',
    ],
    improved: [],
    fixed: [],
  },
]
