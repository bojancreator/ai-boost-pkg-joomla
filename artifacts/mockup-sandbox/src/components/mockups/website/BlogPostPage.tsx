const PURPLE = "#7B4FFF";

const css = `
  * { box-sizing: border-box; }
  .bp-wrap { font-family: 'Inter', system-ui, sans-serif; background: #fff; color: #0C0B1D; }

  /* NAV */
  .bp-nav { display:flex; align-items:center; justify-content:space-between; padding:0 64px; height:72px; border-bottom:1px solid #E8E4F4; background:#fff; }
  .bp-logo { font-size:20px; font-weight:900; color:${PURPLE}; text-decoration:none; }
  .bp-nav-links { display:flex; align-items:center; gap:28px; }
  .bp-nav-link { color:#5A5A7A; font-size:14px; font-weight:500; text-decoration:none; }
  .bp-btn-sm { background:${PURPLE}; color:#fff; font-size:13px; font-weight:700; padding:9px 18px; border-radius:8px; text-decoration:none; }

  /* BREADCRUMB */
  .bp-breadcrumb { max-width:760px; margin:0 auto; padding:24px 64px 0; display:flex; align-items:center; gap:8px; font-size:13px; color:#9090B0; }
  .bp-breadcrumb a { color:#9090B0; text-decoration:none; }
  .bp-breadcrumb a:hover { color:${PURPLE}; }

  /* ARTICLE */
  .bp-article { max-width:760px; margin:0 auto; padding:40px 64px 80px; }

  .bp-meta { display:flex; align-items:center; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
  .bp-tag { background:#F3F0FF; color:${PURPLE}; font-size:12px; font-weight:700; padding:4px 12px; border-radius:100px; text-transform:uppercase; letter-spacing:.5px; }
  .bp-date { font-size:13px; color:#9090B0; }
  .bp-read { font-size:13px; color:#9090B0; }

  .bp-h1 { font-size:44px; font-weight:900; line-height:1.1; letter-spacing:-1.5px; margin:0 0 20px; color:#0C0B1D; }
  .bp-lead { font-size:18px; color:#5A5A7A; line-height:1.7; margin:0 0 40px; border-bottom:1px solid #E8E4F4; padding-bottom:40px; }

  .bp-body h2 { font-size:28px; font-weight:800; letter-spacing:-0.8px; margin:48px 0 16px; color:#0C0B1D; }
  .bp-body h3 { font-size:20px; font-weight:700; margin:36px 0 12px; color:#0C0B1D; }
  .bp-body p { font-size:16px; color:#3A3A5A; line-height:1.8; margin:0 0 20px; }
  .bp-body ul { padding-left:20px; margin:0 0 20px; }
  .bp-body li { font-size:16px; color:#3A3A5A; line-height:1.8; margin-bottom:8px; }
  .bp-body a { color:${PURPLE}; text-decoration:underline; }

  .bp-code { background:#F3F0FF; border:1px solid #E0D8FF; border-radius:10px; padding:20px 24px; font-family:monospace; font-size:14px; line-height:1.7; color:#2A1A6E; margin:0 0 24px; overflow-x:auto; white-space:pre; display:block; }
  .bp-inline-code { background:#F3F0FF; color:${PURPLE}; font-family:monospace; font-size:13px; padding:2px 6px; border-radius:4px; }

  /* CALLOUT / CTA BOX */
  .bp-cta-box { background:#F3F0FF; border:1.5px solid #D4C9FF; border-radius:16px; padding:32px 36px; margin:48px 0; }
  .bp-cta-box h3 { font-size:22px; font-weight:800; margin:0 0 10px; color:#0C0B1D; }
  .bp-cta-box p { font-size:15px; color:#5A5A7A; margin:0 0 20px; line-height:1.6; }
  .bp-cta-btn { display:inline-block; background:${PURPLE}; color:#fff; font-size:15px; font-weight:700; padding:12px 24px; border-radius:10px; text-decoration:none; }

  /* AUTHOR */
  .bp-author { display:flex; align-items:center; gap:16px; border-top:1px solid #E8E4F4; border-bottom:1px solid #E8E4F4; padding:28px 0; margin-top:48px; }
  .bp-author-avatar { width:52px; height:52px; border-radius:50%; background:${PURPLE}; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
  .bp-author-name { font-size:15px; font-weight:700; color:#0C0B1D; margin-bottom:4px; }
  .bp-author-bio { font-size:13px; color:#9090B0; line-height:1.5; }

  /* RELATED */
  .bp-related { max-width:760px; margin:0 auto; padding:48px 64px 80px; }
  .bp-related-title { font-size:22px; font-weight:800; margin:0 0 28px; color:#0C0B1D; }
  .bp-related-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; }
  .bp-related-card { border:1.5px solid #E8E4F4; border-radius:12px; padding:20px 22px; text-decoration:none; display:block; }
  .bp-related-card:hover { border-color:#D4C9FF; }
  .bp-related-tag { font-size:11px; font-weight:700; color:${PURPLE}; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
  .bp-related-headline { font-size:15px; font-weight:700; color:#0C0B1D; line-height:1.4; }

  /* FOOTER */
  .bp-footer { border-top:1px solid #E8E4F4; padding:32px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:12px; }
  .bp-footer-text { font-size:13px; color:#9090B0; }
  .bp-footer-link { font-size:13px; color:#9090B0; text-decoration:none; }

  /* ─── RESPONSIVE 900px ─── */
  @media (max-width: 900px) {
    .bp-nav { padding:0 20px; }
    .bp-nav-links a.bp-nav-link { display:none; }
    .bp-breadcrumb { padding:20px 20px 0; }
    .bp-article { padding:32px 20px 60px; }
    .bp-h1 { font-size:34px; letter-spacing:-1px; }
    .bp-lead { font-size:16px; }
    .bp-body h2 { font-size:24px; }
    .bp-cta-box { padding:24px 24px; }
    .bp-related { padding:40px 20px 60px; }
    .bp-footer { padding:24px 20px; flex-direction:column; align-items:flex-start; }
  }

  /* ─── RESPONSIVE 480px ─── */
  @media (max-width: 480px) {
    .bp-nav { height:60px; padding:0 16px; }
    .bp-breadcrumb { padding:16px 16px 0; }

    .bp-article { max-width:100%; padding:24px 16px 48px; }
    .bp-h1 { font-size:28px; letter-spacing:-0.5px; }
    .bp-lead { font-size:15px; margin-bottom:28px; padding-bottom:28px; }

    .bp-body h2 { font-size:20px; margin:36px 0 12px; }
    .bp-body h3 { font-size:17px; }
    .bp-body p, .bp-body li { font-size:15px; }

    .bp-code { overflow-x:auto; font-size:12px; padding:14px 16px; border-radius:8px; -webkit-overflow-scrolling:touch; }

    .bp-cta-box { padding:20px 16px; margin:32px 0; border-radius:12px; }
    .bp-cta-box h3 { font-size:18px; }
    .bp-cta-box p { font-size:14px; }
    .bp-cta-btn { display:block; text-align:center; width:100%; padding:13px; }

    .bp-related { max-width:100%; padding:32px 16px 48px; }
    .bp-related-grid { grid-template-columns:1fr; }

    .bp-author { gap:12px; }
    .bp-author-avatar { width:44px; height:44px; font-size:18px; }
  }
`;

export function BlogPostPage() {
  return (
    <div className="bp-wrap">
      <style>{css}</style>

      {/* NAV */}
      <nav className="bp-nav">
        <a href="#" className="bp-logo">AI Boost</a>
        <div className="bp-nav-links">
          <a href="#" className="bp-nav-link">Features</a>
          <a href="#" className="bp-nav-link">Docs</a>
          <a href="#" className="bp-nav-link">Blog</a>
          <a href="#" className="bp-btn-sm">Get AI Boost →</a>
        </div>
      </nav>

      {/* BREADCRUMB */}
      <div className="bp-breadcrumb">
        <a href="#">Home</a>
        <span>›</span>
        <a href="#">Blog</a>
        <span>›</span>
        <span style={{ color: "#0C0B1D" }}>Schema.org for Joomla</span>
      </div>

      {/* ARTICLE */}
      <article className="bp-article">
        <div className="bp-meta">
          <span className="bp-tag">SEO</span>
          <span className="bp-date">May 11, 2026</span>
          <span className="bp-read">· 6 min read</span>
        </div>

        <h1 className="bp-h1">
          How Schema.org JSON-LD Gets Your Joomla Site Into Google AI Overviews
        </h1>
        <p className="bp-lead">
          Google AI Overview, ChatGPT, and Perplexity all rely on structured data to understand and recommend websites. Here's exactly how to set up Schema.org on Joomla — and why AI Boost automates the hard parts.
        </p>

        <div className="bp-body">
          <h2>What is Schema.org and why does it matter in 2026?</h2>
          <p>
            Schema.org is a shared vocabulary for structured data — a way to tell search engines and AI systems not just what your page says, but what it <em>means</em>. When your Joomla site outputs valid JSON-LD, Google can confidently include your content in AI Overview snippets, Perplexity citations, and Bing Copilot answers.
          </p>
          <p>
            Without structured data, AI engines treat your content as plain text and may misclassify or ignore it entirely. With it, every article, product, event, or FAQ becomes a first-class signal in AI search.
          </p>

          <h2>The JSON-LD approach vs. microdata</h2>
          <p>
            There are two ways to add Schema.org to a page: inline microdata attributes on HTML elements, or a standalone{" "}
            <code className="bp-inline-code">&lt;script type="application/ld+json"&gt;</code>{" "}
            block. Google recommends JSON-LD because it's decoupled from your HTML and easier to maintain. AI Boost for Joomla exclusively uses JSON-LD.
          </p>

          <h3>A minimal LocalBusiness example</h3>
          <code className="bp-code">{`{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Your Joomla Site",
  "url": "https://yoursite.com",
  "telephone": "+1-555-0100",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 Main St",
    "addressLocality": "Springfield",
    "addressCountry": "US"
  },
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": "Monday",
      "opens": "09:00",
      "closes": "17:00"
    }
  ]
}`}</code>

          <p>
            AI Boost generates this block automatically from your plugin settings — no JSON editing required. Just fill in your business details in the <strong>Organization</strong> tab and the correct schema type in the <strong>Schema.org</strong> tab.
          </p>

          <h2>Which schema types does AI Boost support?</h2>
          <ul>
            <li><strong>LocalBusiness</strong> — the base type for any local business</li>
            <li><strong>Restaurant, Hotel, MedicalClinic, Dentist, LegalService</strong> — vertical presets</li>
            <li><strong>Article &amp; BlogPosting</strong> — automatic for Joomla articles</li>
            <li><strong>FAQPage</strong> — auto-detected from article content or manual entry</li>
            <li><strong>Event</strong> — for conferences, workshops, and webinars</li>
            <li><strong>BreadcrumbList</strong> — automatic from Joomla category path</li>
            <li><strong>Person, NewsMediaOrganization, EducationalOrganization</strong> — advanced types</li>
          </ul>

          <div className="bp-cta-box">
            <h3>Get structured data working in 5 minutes</h3>
            <p>
              AI Boost for Joomla generates Schema.org, XML sitemap, llms.txt, and AI crawler signals — all from a single plugin. Joomla 4, 5, and 6 compatible.
            </p>
            <a href="https://aiboostnow.gumroad.com/l/joomlaboost" target="_blank" rel="noopener noreferrer" className="bp-cta-btn">
              Buy Developer — €119 →
            </a>
          </div>

          <h2>Testing your Schema.org output</h2>
          <p>
            After installing AI Boost and saving your settings, use Google's{" "}
            <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener noreferrer">Rich Results Test</a>{" "}
            to verify the output. You can also use the{" "}
            <a href="https://validator.schema.org" target="_blank" rel="noopener noreferrer">Schema.org Validator</a>{" "}
            for a more detailed breakdown.
          </p>
          <p>
            Enable the <strong>Debug</strong> tab in the plugin settings and turn on <em>HTML markers</em> to see exactly which schema block is injected on each page without leaving the Joomla backend.
          </p>
        </div>

        {/* AUTHOR */}
        <div className="bp-author">
          <div className="bp-author-avatar">🧠</div>
          <div>
            <div className="bp-author-name">AI Boost Team</div>
            <div className="bp-author-bio">We build AI-first SEO tools for Joomla developers and site owners. Questions? support@aiboostnow.com</div>
          </div>
        </div>
      </article>

      {/* RELATED */}
      <div className="bp-related">
        <div className="bp-related-title">Related articles</div>
        <div className="bp-related-grid">
          {[
            { tag: "AEO", title: "What is llms.txt and why every Joomla site needs one in 2026" },
            { tag: "IndexNow", title: "IndexNow: Get new Joomla content indexed by Bing within minutes" },
            { tag: "Sitemap", title: "Hreflang + XML Sitemap for multilingual Joomla sites" },
            { tag: "Analytics", title: "How to install GA4 and Meta Pixel on Joomla without touching code" },
          ].map(({ tag, title }) => (
            <a key={title} href="#" className="bp-related-card">
              <div className="bp-related-tag">{tag}</div>
              <div className="bp-related-headline">{title}</div>
            </a>
          ))}
        </div>
      </div>

      {/* FOOTER */}
      <footer className="bp-footer">
        <span className="bp-footer-text">© 2026 AI Boost · aiboostnow.com</span>
        <div style={{ display: "flex", gap: 20 }}>
          <a href="#" className="bp-footer-link">Privacy</a>
          <a href="#" className="bp-footer-link">Terms</a>
          <a href="#" className="bp-footer-link">Contact</a>
        </div>
      </footer>
    </div>
  );
}
