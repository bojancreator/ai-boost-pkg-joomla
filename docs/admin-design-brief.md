# AI Boost for Joomla — Admin UI Design Brief

**For:** Claude Designer (external visual design pass)
**Scope:** Visual redesign of the AI Boost for Joomla **admin component** (the back-office settings app).
**Date:** 2026-06-19 · **Component version shown in screenshots:** 0.85.4 (Pro)

---

## 1. What this product is

AI Boost for Joomla is a commercial SEO & AEO (AI-Engine-Optimization) plugin package for Joomla 5–6.
Site owners configure it through a **single-page admin app** (Vue 3 SPA) inside Joomla's administrator
back-office. This brief is **only** about that admin app's look — not the public website, not the
front-end output.

The app is mounted inside Joomla's "Atum" admin template, so it shares the very top Joomla toolbar, but
**everything below that is ours**: our own left sidebar + content area. The redesign should make the app
feel like a polished, modern, professional product — clearly its own thing, visually distinct from the
default Joomla blue/grey chrome.

---

## 2. What we are asking for

Redesign the **visual appearance** of the admin app:

- Colour system (surfaces, text, borders, brand + semantic colours), for **light and dark** themes.
- Component styling: buttons, cards, inputs, badges/tags, tabs, alerts, toggles, tables, modals, toasts.
- Depth/elevation (shadows), corner radii, spacing rhythm, typographic scale and hierarchy.
- Interaction states: hover, active/selected, focus, disabled, and "locked / Pro-only".
- Within-screen visual layout & hierarchy (grouping, spacing, emphasis) is welcome to be refined.

**Do NOT change** the information architecture: keep the same set of pages, the same navigation
structure, the same fields/flows. We are restyling, not re-planning the product. If you believe a
structural change is worthwhile, describe it as a separate note — do not bake it into the deliverable.

### Design goals
- Modern, clean, confident, "premium SaaS dashboard" feel.
- High contrast and **WCAG AA** legibility in both themes.
- A distinct brand identity (today it leans on an indigo accent — you may keep, refine, or replace it).
- Calm, scannable settings screens (these pages are dense with form fields).
- A consistent component language so every screen feels part of one system.

---

## 3. Deliverable format (important)

We need to both **see** the design and **drop it into code** with minimal interpretation. Please provide:

### (A) Rendered HTML + CSS mock-ups — so we can look at the result
Self-contained static HTML files (CSS inline or in a sibling `.css`; **no build step, no external CDN,
no JS framework required**) that render the proposed look. We will open these in a browser and judge them
with our own eyes. Cover at least these representative screens:

1. **Dashboard** (overview with alert banners, cards, a data table)
2. **Health** (score circle + a long list of status items)
3. **One settings tab** (a dense, card-based form — e.g. Schema.org or Site Identity)
4. **Integrations** (a grid of status cards with toggles)
5. **The component showcase** (all the building blocks on one page — see §6)

Provide each for **both design directions × both themes = 4 rendered sets**. The component showcase
(item 5) is the single most useful artefact — it shows every component in one place.

### (B) The `--ab-*` design-token values — so we can apply it mechanically
Fill in concrete values for our **existing token names** (the full list is in §5), for each of the 4
combinations (2 directions × light/dark). This is the machine-readable contract: we copy these values
into `ab-tokens.css` and the whole app re-skins. **Please keep the existing token names**; if you need a
new token, add it explicitly and say where it's used.

### (C) *(Optional but very welcome)* component CSS
A re-skinned stylesheet for the components (our file is `ab-components.css`) consistent with the chosen
direction — buttons, cards, inputs, etc.

### Hard constraints
- **Tokens only, our namespace.** All colours/spacing/radius/shadows must be expressed via `--ab-*`
  tokens. **Do not** rely on Joomla/Bootstrap variables (`--bs-*`) or Bootstrap component classes — the
  surrounding Joomla template aggressively redefines `--bs-*`, which would break us.
- **Two themes, switched by an attribute.** Light is the default; dark activates when an ancestor element
  has `data-bs-theme="dark"`. Ship full token sets for both.
- **Sidebar is currently always-dark** in both themes (a dark left nav next to a light content area in
  light mode). You may keep this two-tone pattern or propose an all-light/all-dark sidebar — just state
  the choice explicitly and provide tokens for it.
- **Accessibility:** AA contrast for text and UI; visible focus rings; don't encode meaning in colour
  alone.

---

## 4. The screens (page inventory)

The app has **22 captured screens**. Fresh screenshots (light + dark) are attached alongside this brief
in `artifacts/ui-audit/light/` and `artifacts/ui-audit/dark/` (filenames numbered to match below).

### App shell (present on every screen)
- **Left sidebar** (`01–22`): dark vertical nav with a search box, collapsible groups (OVERVIEW, SETUP,
  SEO, AI VISIBILITY, TOOLS, ADVANCED), per-item icons, small **badge counts** (errors/conflicts), and a
  density toggle (compact/comfortable). Brand wordmark "AI Boost" + a Pro/Free chip at the top.
- **Sticky action bar / header**: page title, an "unsaved changes" indicator, and on settings pages the
  **Save / Discard** buttons; a "Back to settings" link on sub-pages.
- **Toasts** (transient notifications) and a **critical alert bar** (for blocking issues).

### Overview
- **01 Dashboard** — landing page: status alert banners (backup reminder, multilingual notice), a
  "Module Status" grid of feature cards (Schema.org, XML Sitemap, Social & OG, Analytics, AEO, Custom
  Code) each with an enabled/disabled state + "Configure", Quick Actions row, a "Top 404 Errors" data
  table, and a Settings Backup panel. **This is the notifications hub** (high importance).
- **04 Autopilot** — a guided first-run setup wizard (step flow) for a 5-minute initial configuration.

### Health
- **02 Health** — a large **animated score circle** (0–100) at the top, then a long, grouped list of
  health-check items (status icon + title + description + "Fix it" deep-links), plus summary stat pills.
  Note: this page is intentionally very tall.
- **03 Health (errors filter)** — the same page filtered to error items only.

### Tools
- **05 Integrations** — a grid of integration **cards** with status badges and toggle switches
  (third-party integrations like YOOtheme, Falang, etc.).
- **06 License & Updates** — license status card, version info, update controls.
- **08 Analyzers** — tabbed validators (e.g. JSON-LD / AI-visibility checks) with results panels.
- **07 Redirects** — a management table (301/302 redirects).
- **09 URL Checker** — a form input + a results table.
- **10 Import** — an import/restore form/wizard.
- **11 Help** — documentation & support links.

### Settings (one page, 11 tabs — each a dense, card-based form)
Each tab groups fields into cards with a label, help text, and an input/toggle/select. Tabs:
- **12 Technical SEO** — domain detection, canonical URLs, 404 logging.
- **13 Site Identity** — organisation schema: name, address, contacts, social profiles, logo (many fields).
- **14 Schema.org** — structured-data config with **sub-tabs** (Core, Business, Hours, FAQ, HowTo,
  Events…) and an author-entity table. The richest settings screen.
- **15 Sitemap** — XML sitemap options (what to include, priority, ping search engines).
- **16 Social Meta / OG** — Open Graph, Twitter cards, image dimensions, Meta Pixel.
- **17 Analytics & Tracking** — GA4, GTM, Meta Pixel, Search Console verification.
- **18 AI Visibility (AEO)** — AI-crawler rules, llms.txt, IndexNow, markdown pages.
- **19 Crawlers & Robots** — robots.txt management, per-bot rules (cards + rule rows).
- **20 Custom Code** — head/body/footer custom-code injection with scope selectors.
- **21 Debug** — debug mode, staging mode, error logging.

### Reference
- **22 /_styleguide** — our internal **Design System showcase**: every component on one page with a
  light/dark toggle. **Use this as your primary reference for component states** (see §6).

### Also exists (not in the numbered audit, but please keep in mind)
- **Conflict Manager** page + a first-run **Conflict Wizard** modal (detects clashes with other SEO
  plugins; card list + step wizard).

---

## 5. Current design tokens — the template to fill

These are our **existing** `--ab-*` tokens with their current values. Please return new values for each,
**per direction, per theme**. Keep the names. (Source of truth: `component/com_aiboost/admin/css/ab-tokens.css`.)

### Light theme (default)
```css
/* surfaces */
--ab-bg:              #f7f8fa;   /* app background            */
--ab-bg-elev:         #ffffff;   /* cards / elevated surfaces */
--ab-bg-elev-2:       #ffffff;   /* nested elevated surfaces  */
--ab-bg-muted:        #eef0f3;   /* subtle fills / table head */

/* text */
--ab-text:            #1f2937;
--ab-text-muted:      #6b7280;
--ab-text-on-primary: #ffffff;

/* borders */
--ab-border:          #e2e6ec;
--ab-border-strong:   #cdd3db;

/* brand + semantic (each: base / hover / soft tint for backgrounds) */
--ab-primary: #4f46e5;  --ab-primary-hover: #4338ca;  --ab-primary-soft: rgba(79,70,229,.08);
--ab-success: #16a34a;  --ab-success-hover: #15803d;  --ab-success-soft: rgba(22,163,74,.10);
--ab-warning: #d97706;  --ab-warning-hover: #b45309;  --ab-warning-soft: rgba(217,119,6,.10);
--ab-danger:  #dc2626;  --ab-danger-hover:  #b91c1c;  --ab-danger-soft:  rgba(220,38,38,.10);
--ab-info:    #0284c7;  --ab-info-hover:    #0369a1;  --ab-info-soft:    rgba(2,132,199,.10);

/* typography (scale rarely needs changing; restyle freely) */
--ab-font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--ab-font-mono:   ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
--ab-font-size-xs: .75rem;  --ab-font-size-sm: .8125rem;  --ab-font-size-base: .9375rem;
--ab-font-size-lg: 1.0625rem; --ab-font-size-xl: 1.25rem;
--ab-font-weight-normal: 400; --ab-font-weight-medium: 500; --ab-font-weight-semibold: 600; --ab-font-weight-bold: 700;
--ab-line-height-tight: 1.25; --ab-line-height-base: 1.5;

/* spacing (4px base) */
--ab-space-1: .25rem; --ab-space-2: .5rem; --ab-space-3: .75rem; --ab-space-4: 1rem; --ab-space-5: 1.5rem; --ab-space-6: 2rem;

/* radius */
--ab-radius-sm: .25rem; --ab-radius: .375rem; --ab-radius-md: .5rem; --ab-radius-lg: .75rem; --ab-radius-pill: 999px;

/* shadows */
--ab-shadow-sm: 0 1px 2px rgba(15,23,42,.06);
--ab-shadow:    0 2px 6px rgba(15,23,42,.08);
--ab-shadow-md: 0 4px 14px rgba(15,23,42,.10);
--ab-shadow-lg: 0 10px 30px rgba(15,23,42,.14);

/* focus rings + transition */
--ab-focus-ring:        0 0 0 .2rem rgba(79,70,229,.25);
--ab-focus-ring-danger: 0 0 0 .2rem rgba(220,38,38,.25);
--ab-transition:        .15s ease;

/* sidebar (always-dark in light theme) */
--ab-sidebar-bg: #1e2532; --ab-sidebar-border: #2d3548; --ab-sidebar-text: #c8d0e0; --ab-sidebar-label: #8a93a6;
```

### Dark theme (`[data-bs-theme="dark"]`)
```css
--ab-bg: #1a1d23; --ab-bg-elev: #23272f; --ab-bg-elev-2: #2b3038; --ab-bg-muted: #2a2f37;
--ab-text: #e5e7eb; --ab-text-muted: #b3bac3; --ab-text-on-primary: #ffffff;
--ab-border: #3a414b; --ab-border-strong: #4d5560;

--ab-primary: #8b88f5;  --ab-primary-hover: #a5a3ff;  --ab-primary-soft: rgba(139,136,245,.15);
--ab-success: #4ade80;  --ab-success-hover: #6ee7a0;  --ab-success-soft: rgba(74,222,128,.15);
--ab-warning: #fbbf24;  --ab-warning-hover: #fcd34d;  --ab-warning-soft: rgba(251,191,36,.15);
--ab-danger:  #f87171;  --ab-danger-hover:  #fca5a5;  --ab-danger-soft:  rgba(248,113,113,.15);
--ab-info:    #38bdf8;  --ab-info-hover:    #7dd3fc;  --ab-info-soft:    rgba(56,189,248,.15);

--ab-shadow-sm: 0 1px 2px rgba(0,0,0,.35);
--ab-shadow:    0 2px 6px rgba(0,0,0,.40);
--ab-shadow-md: 0 4px 14px rgba(0,0,0,.45);
--ab-shadow-lg: 0 10px 30px rgba(0,0,0,.55);

--ab-focus-ring:        0 0 0 .2rem rgba(139,136,245,.35);
--ab-focus-ring-danger: 0 0 0 .2rem rgba(248,113,113,.35);

--ab-sidebar-bg: #141920; --ab-sidebar-border: #1e2430; --ab-sidebar-text: #b3bac3; --ab-sidebar-label: #687280;
```

---

## 6. Components to style (the building blocks)

These appear on the **/_styleguide** screen (screenshot `22-styleguide`, light + dark) — the best single
reference. Style each with all relevant states (default / hover / active / focus / disabled):

- **Buttons** — variants: Primary, Success, Danger, Ghost, Subtle, Default; sizes: small / base / large;
  plus disabled.
- **Cards** — a basic card and a card "with header"; used everywhere as the main container.
- **Inputs** — text input, textarea, select; plus an **invalid** state with a validation message.
- **Badges & Tags** — neutral + semantic badges; and **Pro / Free / Beta** tags (the Pro tag matters:
  it marks premium-only features).
- **Tabs** — horizontal tab strip with an active/selected tab.
- **Alerts** — Success / Warning / Error / Info banners.
- **Toggles** — on/off switches (enabled state and a "locked/disabled" state).
- **Tables** — data tables (e.g. the 404 list, redirects) with header + rows.
- **Modals & toasts** — overlay dialog and transient toast notifications.
- **The score circle** — Health's circular 0–100 gauge (SVG ring).
- **"Locked / Pro" treatment** — how a Pro-only control looks to a Free user (lock affordance + an
  "upgrade" hint), since both editions share these screens.

---

## 7. How we'll use your output

1. We open your HTML mock-ups in a browser and pick a direction (with the product owner).
2. We paste your `--ab-*` values into `ab-tokens.css` (light + dark blocks).
3. We optionally adopt your `ab-components.css`.
4. We rebuild and verify on a live Joomla staging site in both themes.

So: the **HTML mock-ups are how you show the look**; the **token map is how we apply it**. The closer your
tokens map to the names in §5, the faster and safer the hand-off.

Thank you!
