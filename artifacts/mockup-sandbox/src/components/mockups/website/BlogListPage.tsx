import { useState } from "react";
import { blogPosts, categories } from "@/data/blogPosts";

const PURPLE = "#7B4FFF";

function formatDate(iso: string) {
  const d = new Date(iso);
  return d.toLocaleDateString("en-GB", { day: "numeric", month: "short", year: "numeric" });
}

const css = `
  * { box-sizing: border-box; }
  .bl-wrap { font-family: 'Inter', system-ui, sans-serif; background: #fff; color: #0C0B1D; min-height: 100vh; }

  /* NAV */
  .bl-nav { display:flex; align-items:center; justify-content:space-between; padding:0 64px; height:72px; border-bottom:1px solid #E8E4F4; background:#fff; position:sticky; top:0; z-index:100; }
  .bl-logo { font-size:20px; font-weight:900; color:${PURPLE}; text-decoration:none; }
  .bl-nav-links { display:flex; align-items:center; gap:28px; }
  .bl-nav-link { color:#5A5A7A; font-size:14px; font-weight:500; text-decoration:none; }
  .bl-nav-link.active { color:${PURPLE}; }
  .bl-btn-sm { background:${PURPLE}; color:#fff; font-size:13px; font-weight:700; padding:9px 18px; border-radius:8px; text-decoration:none; }

  /* HEADER */
  .bl-header { max-width:1100px; margin:0 auto; padding:64px 64px 48px; }
  .bl-header-pill { display:inline-flex; align-items:center; gap:8px; background:#F3F0FF; border:1px solid #D4C9FF; border-radius:100px; padding:5px 14px; margin-bottom:20px; font-size:12px; font-weight:700; color:${PURPLE}; text-transform:uppercase; letter-spacing:.5px; }
  .bl-header-h1 { font-size:48px; font-weight:900; letter-spacing:-1.5px; margin:0 0 16px; color:#0C0B1D; }
  .bl-header-sub { font-size:18px; color:#5A5A7A; max-width:520px; line-height:1.6; }

  /* FEATURED */
  .bl-featured { max-width:1100px; margin:0 auto; padding:0 64px 48px; }
  .bl-featured-card { display:flex; align-items:center; gap:48px; background:#F8F7FF; border:1.5px solid #E8E4F4; border-radius:20px; padding:40px 44px; text-decoration:none; transition:border-color .15s; }
  .bl-featured-card:hover { border-color:#D4C9FF; }
  .bl-featured-text { flex:1; }
  .bl-featured-badges { display:flex; align-items:center; gap:8px; margin-bottom:16px; }
  .bl-featured-badge { display:inline-block; background:#F3F0FF; color:${PURPLE}; font-size:11px; font-weight:700; padding:4px 12px; border-radius:100px; text-transform:uppercase; letter-spacing:.5px; }
  .bl-featured-new { background:#0C0B1D; color:#fff; font-size:11px; font-weight:700; padding:4px 12px; border-radius:100px; }
  .bl-featured-star { background:#E8F5E9; color:#2E7D32; font-size:11px; font-weight:700; padding:4px 12px; border-radius:100px; }
  .bl-featured-title { font-size:28px; font-weight:900; letter-spacing:-0.8px; line-height:1.2; color:#0C0B1D; margin:0 0 14px; }
  .bl-featured-excerpt { font-size:15px; color:#5A5A7A; line-height:1.7; margin:0 0 20px; }
  .bl-featured-meta { font-size:13px; color:#9090B0; }
  .bl-featured-arrow { font-size:40px; flex-shrink:0; color:${PURPLE}; }

  /* FILTER TABS */
  .bl-filters { max-width:1100px; margin:0 auto; padding:0 64px 32px; }
  .bl-filter-label { font-size:13px; font-weight:600; color:#9090B0; text-transform:uppercase; letter-spacing:.5px; margin-bottom:12px; }
  .bl-tabs { display:flex; gap:8px; flex-wrap:wrap; }
  .bl-tab { border:1.5px solid #E8E4F4; border-radius:100px; padding:8px 18px; font-size:13px; font-weight:600; color:#5A5A7A; background:#fff; cursor:pointer; transition:all .15s; }
  .bl-tab:hover { border-color:#D4C9FF; color:${PURPLE}; }
  .bl-tab.active { background:${PURPLE}; border-color:${PURPLE}; color:#fff; }

  /* GRID */
  .bl-grid-wrap { max-width:1100px; margin:0 auto; padding:0 64px 80px; }
  .bl-grid-header { display:flex; align-items:baseline; gap:12px; margin-bottom:28px; }
  .bl-grid-title { font-size:22px; font-weight:800; color:#0C0B1D; }
  .bl-grid-count { font-size:14px; color:#9090B0; }
  .bl-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; }

  .bl-card { border:1.5px solid #E8E4F4; border-radius:16px; padding:28px 24px; text-decoration:none; display:flex; flex-direction:column; transition:border-color .15s; }
  .bl-card:hover { border-color:#D4C9FF; }
  .bl-card-top { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
  .bl-card-tag { font-size:11px; font-weight:700; color:${PURPLE}; text-transform:uppercase; letter-spacing:.5px; }
  .bl-card-new { background:#F3F0FF; color:${PURPLE}; font-size:10px; font-weight:700; padding:3px 8px; border-radius:100px; }
  .bl-card-title { font-size:17px; font-weight:800; color:#0C0B1D; line-height:1.35; margin-bottom:12px; letter-spacing:-0.3px; flex:1; }
  .bl-card-excerpt { font-size:13px; color:#5A5A7A; line-height:1.6; margin-bottom:16px; }
  .bl-card-meta { font-size:12px; color:#9090B0; margin-top:auto; }

  /* EMPTY STATE */
  .bl-empty { text-align:center; padding:80px 20px; }
  .bl-empty-icon { font-size:48px; margin-bottom:16px; }
  .bl-empty-text { font-size:18px; font-weight:700; color:#0C0B1D; margin-bottom:8px; }
  .bl-empty-sub { font-size:15px; color:#9090B0; }

  /* NEWSLETTER */
  .bl-newsletter { background:#F3F0FF; border-top:1px solid #E0D8FF; padding:64px; text-align:center; }
  .bl-newsletter h2 { font-size:30px; font-weight:900; letter-spacing:-1px; margin:0 0 12px; color:#0C0B1D; }
  .bl-newsletter p { font-size:16px; color:#5A5A7A; margin:0 0 28px; }
  .bl-email-row { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
  .bl-email-input { padding:12px 18px; border:1.5px solid #D4C9FF; border-radius:10px; font-size:15px; width:280px; outline:none; background:#fff; }
  .bl-email-btn { background:${PURPLE}; color:#fff; font-size:15px; font-weight:700; padding:12px 24px; border-radius:10px; border:none; cursor:pointer; }

  /* FOOTER */
  .bl-footer { border-top:1px solid #E8E4F4; padding:32px 64px; display:flex; justify-content:space-between; align-items:center; background:#F8F7FF; flex-wrap:wrap; gap:12px; }
  .bl-footer-text { font-size:13px; color:#9090B0; }
  .bl-footer-link { font-size:13px; color:#9090B0; text-decoration:none; }

  /* ─── RESPONSIVE 900px ─── */
  @media (max-width: 900px) {
    .bl-nav { padding:0 20px; }
    .bl-nav-links a.bl-nav-link { display:none; }
    .bl-header { padding:48px 20px 36px; }
    .bl-header-h1 { font-size:36px; letter-spacing:-1px; }
    .bl-header-sub { font-size:16px; }
    .bl-featured { padding:0 20px 36px; }
    .bl-featured-card { flex-direction:column; gap:20px; padding:28px 24px; }
    .bl-featured-arrow { display:none; }
    .bl-featured-title { font-size:22px; }
    .bl-filters { padding:0 20px 24px; }
    .bl-grid-wrap { padding:0 20px 60px; }
    .bl-grid { grid-template-columns:repeat(2,1fr); }
    .bl-newsletter { padding:48px 20px; }
    .bl-footer { padding:24px 20px; flex-direction:column; align-items:flex-start; }
  }

  /* ─── RESPONSIVE 600px ─── */
  @media (max-width: 600px) {
    .bl-nav { height:60px; padding:0 16px; }
    .bl-header { padding:36px 16px 28px; }
    .bl-header-h1 { font-size:28px; }
    .bl-header-sub { font-size:15px; }
    .bl-featured { padding:0 16px 28px; }
    .bl-featured-card { padding:20px 16px; }
    .bl-featured-title { font-size:18px; letter-spacing:-0.3px; }
    .bl-featured-excerpt { font-size:14px; }
    .bl-filters { padding:0 16px 20px; }
    .bl-grid-wrap { padding:0 16px 48px; }
    .bl-grid { grid-template-columns:1fr; }
    .bl-newsletter { padding:40px 16px; }
    .bl-newsletter h2 { font-size:22px; }
    .bl-email-row { flex-direction:column; align-items:stretch; }
    .bl-email-input { width:100%; }
    .bl-email-btn { width:100%; }
  }
`;

export function BlogListPage() {
  const [activeCategory, setActiveCategory] = useState<string>("All");

  const sortedPosts = [...blogPosts].sort(
    (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime()
  );

  const featured = sortedPosts[0];

  const filteredPosts =
    activeCategory === "All"
      ? sortedPosts
      : sortedPosts.filter((p) => p.category === activeCategory);

  const allCategories = ["All", ...categories];

  const gridLabel =
    activeCategory === "All" ? "All articles" : `${activeCategory} articles`;

  const gridCount = filteredPosts.length;

  return (
    <div className="bl-wrap">
      <style>{css}</style>

      {/* NAV */}
      <nav className="bl-nav">
        <a href="#" className="bl-logo">AI Boost</a>
        <div className="bl-nav-links">
          <a href="#" className="bl-nav-link">Features</a>
          <a href="#" className="bl-nav-link">Docs</a>
          <a href="#" className="bl-nav-link active">Blog</a>
          <a href="#" className="bl-btn-sm">Get AI Boost →</a>
        </div>
      </nav>

      {/* HEADER */}
      <div className="bl-header">
        <div className="bl-header-pill">✍️ Blog</div>
        <h1 className="bl-header-h1">AI Search &amp; SEO for Joomla</h1>
        <p className="bl-header-sub">
          Practical guides, tutorials, and updates to help your Joomla site get recommended by ChatGPT, Google AI Overview, and Perplexity.
        </p>
      </div>

      {/* FEATURED — only shown when "All" is selected */}
      {activeCategory === "All" && (
        <div className="bl-featured">
          <a href="#" className="bl-featured-card">
            <div className="bl-featured-text">
              <div className="bl-featured-badges">
                <span className="bl-featured-badge">{featured.category}</span>
                <span className="bl-featured-star">⭐ Featured</span>
                {featured.isNew && <span className="bl-featured-new">New</span>}
              </div>
              <div className="bl-featured-title">{featured.title}</div>
              <div className="bl-featured-excerpt">{featured.excerpt}</div>
              <div className="bl-featured-meta">
                {formatDate(featured.date)} · {featured.readTime}
              </div>
            </div>
            <div className="bl-featured-arrow">→</div>
          </a>
        </div>
      )}

      {/* FILTER TABS */}
      <div className="bl-filters">
        <div className="bl-filter-label">Filter by category</div>
        <div className="bl-tabs">
          {allCategories.map((cat) => (
            <button
              key={cat}
              className={`bl-tab${activeCategory === cat ? " active" : ""}`}
              onClick={() => setActiveCategory(cat)}
            >
              {cat}
            </button>
          ))}
        </div>
      </div>

      {/* GRID */}
      <div className="bl-grid-wrap">
        <div className="bl-grid-header">
          <div className="bl-grid-title">{gridLabel}</div>
          <div className="bl-grid-count">{gridCount} posts</div>
        </div>

        {filteredPosts.length === 0 ? (
          <div className="bl-empty">
            <div className="bl-empty-icon">📭</div>
            <div className="bl-empty-text">No articles in this category yet</div>
            <div className="bl-empty-sub">Check back soon — we publish weekly.</div>
          </div>
        ) : (
          <div className="bl-grid">
            {filteredPosts.map((post) => (
              <a key={post.slug} href={`#${post.slug}`} className="bl-card">
                <div className="bl-card-top">
                  <div className="bl-card-tag">{post.category}</div>
                  {post.isNew && <span className="bl-card-new">New</span>}
                </div>
                <div className="bl-card-title">{post.title}</div>
                <div className="bl-card-excerpt">{post.excerpt}</div>
                <div className="bl-card-meta">
                  {formatDate(post.date)} · {post.readTime}
                </div>
              </a>
            ))}
          </div>
        )}
      </div>

      {/* NEWSLETTER */}
      <div className="bl-newsletter">
        <h2>Stay ahead of AI search</h2>
        <p>Get practical Joomla SEO tips and AI Boost updates delivered to your inbox.</p>
        <div className="bl-email-row">
          <input className="bl-email-input" type="email" placeholder="you@example.com" />
          <button className="bl-email-btn">Subscribe</button>
        </div>
      </div>

      {/* FOOTER */}
      <footer className="bl-footer">
        <span className="bl-footer-text">© 2026 AI Boost · aiboostnow.com</span>
        <div style={{ display: "flex", gap: 20 }}>
          <a href="#" className="bl-footer-link">Privacy</a>
          <a href="#" className="bl-footer-link">Terms</a>
          <a href="#" className="bl-footer-link">Contact</a>
        </div>
      </footer>
    </div>
  );
}
