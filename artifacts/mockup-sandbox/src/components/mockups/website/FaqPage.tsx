import { useState } from "react";

const PURPLE = "#7B4FFF";

const faqs = [
  {
    category: "Purchasing",
    items: [
      {
        q: 'What does "one-time payment" mean?',
        a: 'You pay once and own the plugin forever. Updates are included for 1 year after purchase. After that, the plugin keeps working — renewal is optional and costs 50% of the original price.',
      },
      {
        q: "Is there a free trial?",
        a: "There is no free trial, but every purchase includes a 30-day money-back guarantee. If AI Boost for Joomla doesn't work as expected on your site, contact support@aiboostnow.com within 30 days for a full refund.",
      },
      {
        q: "Can I upgrade my license later?",
        a: "Yes. If you purchased Starter and need Developer or Agency, contact support@aiboostnow.com. We'll arrange an upgrade at the price difference — you only pay what's left.",
      },
      {
        q: "Do prices include VAT?",
        a: "Prices shown are before VAT. Gumroad (our payment processor) automatically applies the correct VAT rate for your country as Merchant of Record, so you never need to calculate it manually.",
      },
    ],
  },
  {
    category: "Compatibility",
    items: [
      {
        q: "Which Joomla versions are supported?",
        a: "AI Boost for Joomla supports Joomla 4.0 through 6.x. It is tested on every minor release. PHP 8.1, 8.2, 8.3, 8.4, and 8.5 are all supported.",
      },
      {
        q: "Does it conflict with other SEO extensions?",
        a: "AI Boost is a system plugin that injects structured data into the page head. It does not override Joomla's metadata fields, so it generally coexists peacefully with other SEO tools. If you notice a conflict, the Debug tab shows exactly what is injected on each page.",
      },
      {
        q: "Does it work with third-party Joomla templates?",
        a: "Yes. AI Boost operates at the system plugin level and does not depend on template structure. It works with any Joomla-compatible template, including Protostar, Cassiopeia, Helix, Astroid, and commercial templates.",
      },
    ],
  },
  {
    category: "Features",
    items: [
      {
        q: "What Schema.org types does AI Boost generate?",
        a: "AI Boost generates 20+ schema types including LocalBusiness, Restaurant, Hotel, MedicalClinic, Dentist, LegalService, EducationalOrganization, HealthClub, RealEstateAgent, Person, NewsMediaOrganization, Article, BlogPosting, FAQPage, Event, and BreadcrumbList.",
      },
      {
        q: "What is llms.txt and does AI Boost create it?",
        a: 'llms.txt is a plain-text file (similar to robots.txt) that tells AI crawlers like ChatGPT and Perplexity what content they are allowed to index. AI Boost generates it automatically based on your settings — you just enable it in the Analytics tab.',
      },
      {
        q: "Which Pro features are locked to Developer/Agency?",
        a: "Manual FAQ entry, Event schema, IndexNow, llms.txt, and 8 specialised site types (Medical, Dentist, Lawyer, School, Gym, Real Estate, Portfolio, News) are Pro-only. The Advanced Business Hours system is also Pro. All other features are available on all licenses including Starter.",
      },
      {
        q: "How does IndexNow work?",
        a: "IndexNow allows you to instantly notify Bing, Yandex, and Seznam whenever you publish or update content. AI Boost handles the key generation and automatic URL submission — no manual configuration required beyond entering your key in the Analytics tab.",
      },
    ],
  },
  {
    category: "Support",
    items: [
      {
        q: "How do I get support?",
        a: "Email support@aiboostnow.com. Starter licenses receive email support with a 48-hour response target. Developer and Agency licenses receive priority support with a 24-hour response target on business days.",
      },
      {
        q: "Where is the documentation?",
        a: "Full documentation is available at aiboostnow.com/docs. It covers installation, every plugin tab, Schema.org types, multilingual setup, and troubleshooting. A Getting Started guide is also included as a PDF in the download.",
      },
    ],
  },
];

const css = `
  * { box-sizing: border-box; }
  .fq-wrap { font-family: 'Inter', system-ui, sans-serif; background: #fff; color: #0C0B1D; min-height: 100vh; }

  /* NAV */
  .fq-nav { display:flex; align-items:center; justify-content:space-between; padding:0 64px; height:72px; border-bottom:1px solid #E8E4F4; background:#fff; }
  .fq-logo { font-size:20px; font-weight:900; color:${PURPLE}; text-decoration:none; }
  .fq-nav-links { display:flex; align-items:center; gap:28px; }
  .fq-nav-link { color:#5A5A7A; font-size:14px; font-weight:500; text-decoration:none; }
  .fq-btn-sm { background:${PURPLE}; color:#fff; font-size:13px; font-weight:700; padding:9px 18px; border-radius:8px; text-decoration:none; }

  /* HERO */
  .fq-hero { background:#F8F7FF; border-bottom:1px solid #E8E4F4; padding:64px 64px 56px; text-align:center; }
  .fq-hero-pill { display:inline-flex; align-items:center; gap:8px; background:#F3F0FF; border:1px solid #D4C9FF; border-radius:100px; padding:5px 14px; margin-bottom:20px; font-size:12px; font-weight:700; color:${PURPLE}; text-transform:uppercase; letter-spacing:.5px; }
  .fq-hero-h1 { font-size:44px; font-weight:900; letter-spacing:-1.5px; margin:0 0 16px; color:#0C0B1D; }
  .fq-hero-sub { font-size:17px; color:#5A5A7A; max-width:480px; margin:0 auto; line-height:1.6; }

  /* CONTENT */
  .fq-content { max-width:760px; margin:0 auto; padding:64px 64px 80px; }

  /* CATEGORY */
  .fq-category { margin-bottom:48px; }
  .fq-category-title { font-size:13px; font-weight:700; color:${PURPLE}; text-transform:uppercase; letter-spacing:1px; margin-bottom:16px; }

  /* ACCORDION */
  .fq-item { border:1.5px solid #E8E4F4; border-radius:12px; margin-bottom:10px; overflow:hidden; }
  .fq-item.open { border-color:#D4C9FF; }

  .fq-btn { width:100%; display:flex; align-items:center; justify-content:space-between; gap:12px; background:#fff; border:none; cursor:pointer; padding:18px 22px; text-align:left; }
  .fq-btn:hover { background:#FAFAFE; }
  .fq-btn-text { font-size:16px; font-weight:700; color:#0C0B1D; line-height:1.4; flex:1; min-width:0; }
  .fq-icon { font-size:20px; color:${PURPLE}; flex-shrink:0; line-height:1; transition:transform .2s; }
  .fq-icon.rotated { transform:rotate(45deg); }

  .fq-answer { padding:0 22px 20px; font-size:15px; color:#5A5A7A; line-height:1.75; border-top:1px solid #F0ECF8; padding-top:16px; }

  /* CTA */
  .fq-cta { background:#F3F0FF; border:1.5px solid #D4C9FF; border-radius:16px; padding:36px 40px; text-align:center; margin-top:16px; }
  .fq-cta h2 { font-size:26px; font-weight:900; letter-spacing:-0.8px; margin:0 0 10px; color:#0C0B1D; }
  .fq-cta p { font-size:15px; color:#5A5A7A; margin:0 0 24px; line-height:1.6; }
  .fq-cta-btn { display:inline-block; background:${PURPLE}; color:#fff; font-size:15px; font-weight:700; padding:13px 28px; border-radius:10px; text-decoration:none; }

  /* FOOTER */
  .fq-footer { border-top:1px solid #E8E4F4; padding:32px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:12px; }
  .fq-footer-text { font-size:13px; color:#9090B0; }
  .fq-footer-link { font-size:13px; color:#9090B0; text-decoration:none; }

  /* ─── RESPONSIVE 900px ─── */
  @media (max-width: 900px) {
    .fq-nav { padding:0 20px; }
    .fq-nav-links a.fq-nav-link { display:none; }

    .fq-hero { padding:48px 20px 40px; }
    .fq-hero-h1 { font-size:34px; letter-spacing:-1px; }
    .fq-hero-sub { font-size:16px; }

    .fq-content { padding:48px 20px 60px; }
    .fq-cta { padding:28px 24px; }
    .fq-footer { padding:24px 20px; flex-direction:column; align-items:flex-start; }
  }

  /* ─── RESPONSIVE 480px ─── */
  @media (max-width: 480px) {
    .fq-nav { height:60px; padding:0 16px; }

    .fq-hero { padding:36px 16px 32px; }
    .fq-hero-h1 { font-size:26px; letter-spacing:-0.5px; }
    .fq-hero-sub { font-size:14px; }

    .fq-content { max-width:100%; padding:32px 16px 48px; }

    .fq-btn { padding:14px 14px; gap:8px; }
    .fq-btn-text { font-size:14px; word-break:break-word; overflow-wrap:break-word; }
    .fq-icon { font-size:18px; }
    .fq-answer { padding:0 14px 16px; font-size:14px; padding-top:14px; }

    .fq-cta { padding:20px 16px; border-radius:12px; }
    .fq-cta h2 { font-size:20px; }
    .fq-cta p { font-size:14px; }
    .fq-cta-btn { display:block; width:100%; text-align:center; padding:13px; }

    .fq-category-title { font-size:12px; }
    .fq-item { border-radius:10px; }
  }
`;

export function FaqPage() {
  const [open, setOpen] = useState<string | null>(null);

  const toggle = (key: string) => {
    setOpen(prev => (prev === key ? null : key));
  };

  return (
    <div className="fq-wrap">
      <style>{css}</style>

      {/* NAV */}
      <nav className="fq-nav">
        <a href="#" className="fq-logo">AI Boost</a>
        <div className="fq-nav-links">
          <a href="#" className="fq-nav-link">Features</a>
          <a href="#" className="fq-nav-link">Docs</a>
          <a href="#" className="fq-nav-link">Blog</a>
          <a href="#" className="fq-btn-sm">Get AI Boost →</a>
        </div>
      </nav>

      {/* HERO */}
      <div className="fq-hero">
        <div className="fq-hero-pill">❓ FAQ</div>
        <h1 className="fq-hero-h1">Frequently asked questions</h1>
        <p className="fq-hero-sub">
          Everything you need to know about AI Boost for Joomla. Can't find your answer?{" "}
          <a href="mailto:support@aiboostnow.com" style={{ color: PURPLE }}>Email us</a>.
        </p>
      </div>

      {/* ACCORDION */}
      <div className="fq-content">
        {faqs.map(({ category, items }) => (
          <div key={category} className="fq-category">
            <div className="fq-category-title">{category}</div>
            {items.map(({ q, a }) => {
              const key = `${category}-${q}`;
              const isOpen = open === key;
              return (
                <div key={key} className={`fq-item${isOpen ? " open" : ""}`}>
                  <button className="fq-btn" onClick={() => toggle(key)} aria-expanded={isOpen}>
                    <span className="fq-btn-text">{q}</span>
                    <span className={`fq-icon${isOpen ? " rotated" : ""}`}>+</span>
                  </button>
                  {isOpen && (
                    <div className="fq-answer">{a}</div>
                  )}
                </div>
              );
            })}
          </div>
        ))}

        {/* CTA */}
        <div className="fq-cta">
          <h2>Ready to get started?</h2>
          <p>
            AI Boost for Joomla installs in 5 minutes. One-time payment. 30-day money-back guarantee.
          </p>
          <a href="https://aiboostnow.gumroad.com/l/joomlaboost" target="_blank" rel="noopener noreferrer" className="fq-cta-btn">
            Buy Developer — €119 →
          </a>
        </div>
      </div>

      {/* FOOTER */}
      <footer className="fq-footer">
        <span className="fq-footer-text">© 2026 AI Boost · aiboostnow.com</span>
        <div style={{ display: "flex", gap: 20 }}>
          <a href="#" className="fq-footer-link">Privacy</a>
          <a href="#" className="fq-footer-link">Terms</a>
          <a href="#" className="fq-footer-link">Contact</a>
        </div>
      </footer>
    </div>
  );
}
