# Workspace — AI Boost Now / JoomlaBoost

## Project Overview

**Owner:** Bojan (bojancreator)  
**Brand:** AI Boost (aiboostnow.com) — ⚠️ see `.local/tasks/BRANDING.md` for naming rules  
**Product:** AI Boost for Joomla — commercial Joomla 4–6 SEO & AEO system plugin (PHP 8.1–8.5)  
**Mission:** All-in-one Joomla plugin that generates Schema.org, XML sitemap, OpenGraph, robots.txt, llms.txt, and AI search signals so that AI engines (ChatGPT, Perplexity, Google AI Overview, Bing Copilot) recommend the site.

**⚠️ BRANDING (single source of truth: `.local/tasks/BRANDING.md`)**  
| Element | Correct name |  
|---|---|  
| Website / Brand | **AI Boost** |  
| Joomla plugin | **AI Boost for Joomla** |  
| WordPress plugin | **AI Boost for WordPress** |  
| Domain | aiboostnow.com |  
| NEVER use | ~~JoomlaBoost~~, ~~AI Boost Now~~ |

**Plugin slug:** `plg_system_joomlaboost`  
**Plugin source:** `plugin/src/plugins/system/joomlaboost/`  
**Deliverables:** `.local/deliverables/` (gitignored)  
**Task plans:** `.local/tasks/`  
**Master plan:** `.local/tasks/joomlaboost-master-plan.md`

---

## ⚠️ Language Rules — ALWAYS FOLLOW

| What | Language | Note |
|------|----------|------|
| **Website (aiboostnow.com)** | 🇬🇧 English ONLY | Serbian/other website versions: after English is fully done |
| **Documentation** (user guide, getting started, API docs) | 🇬🇧 English ONLY | Translations: after English is fully done |
| **Marketing materials** (JED, forum, Product Hunt, LinkedIn, email) | 🇬🇧 English ONLY | SR/other versions: after English is fully done |
| **Plugin UI (`.ini` files)** | 🇬🇧 EN + all 10 others SIMULTANEOUSLY | Every new/changed string must be translated into all 11 languages immediately |
| **Our conversation** | 🇷🇸 Serbian | Only for communication between Bojan and agent |

### Plugin Translation Rule (CRITICAL)

Every task that adds or changes strings in plugin `.ini` files MUST:
1. Define the constant in `en-GB` (primary)
2. Immediately add the same constant with translation in all 10 other language packs: `de-DE`, `fr-FR`, `es-ES`, `it-IT`, `ru-RU`, `pt-BR`, `zh-CN`, `ar-AA`, `ja-JP`, `sr-RS`
3. **No exceptions** — never leave "TODO: translate" for any constant

---

## Plugin Architecture

- **Type:** Joomla System Plugin (group: system)
- **Entry point:** `joomlaboost.php` — extends `CMSPlugin`, loads services via autoloader
- **Services:** `src/Services/` — 20 PHP classes (SchemaService, OpenGraphService, SitemapService, RobotService, LlmsTxtService, IndexNowService, AnalyticsService, MetaPixelService, HreflangService, VerticalPresetService, SettingsPersistenceService, PerformanceService, TranslationService, LanguageService, CustomFieldsService, DomainDetectionService, HealthService, InjectionService, ServiceContainer, ServiceAutoloader, ServiceManager, QAManagementService...)
- **Custom Fields:** `src/Field/` — MultiLangTextField, MultiLangTextarea, MultiLangFaqField, MultiLangParamsTextField, MultiLangParamsTextarea, IndexNowKeyField, LicenseKeyField
- **Media:** `media/` — admin.css, multilang-fields.css, js/multilang-selector.js, js/indexnow-generator.js
- **SQL:** `sql/install.sql`, `sql/uninstall.sql`
- **Installer:** `script.php`

### Plugin Tabs (7)
1. **Plugin** — Quick Setup, Vertical Presets, Domain, robots.txt, License Key
2. **Organization** — Identity, Contact, Social links, Location (multilingual fields)
3. **Schema.org** — Structured data types, Hotel fields, FAQ auto-detect, Manual FAQ, Events
4. **Sitemap** — XML sitemap, hreflang, article/category/menu include options
5. **Social & Meta** — OpenGraph, Twitter Cards, per-article overrides
6. **Analytics** — GA4, GTM, GSC verification, Meta Pixel, IndexNow, llms.txt
7. **Debug** — Flash messages, HTML markers, staging mode

### 11 Language Packs
`en-GB` (primary), `sr-RS`, `de-DE`, `es-ES`, `fr-FR`, `it-IT`, `ru-RU`, `pt-BR`, `zh-CN`, `ar-AA`, `ja-JP`

---

## Building a Plugin ZIP

Always build from the workspace source — never from a partial extracted state:

```bash
python3 scripts/build-plugin-zip.py
# Output: .local/deliverables/plg_system_joomlaboost-{version}.zip
```

**ZIP structure** (flat, no subfolder — Joomla requires this):
```
joomlaboost.php
joomlaboost.xml
script.php
language/en-GB/...  (and all 10 other lang dirs)
media/...
sql/...
src/...
```

---

## CI/CD Pipeline — aiboostnow.com

**Repo:** `bojancreator/aiboostnow` (GitHub)  
**Trigger:** push to `main` → auto-deploy  
**Workflow:** `.github/workflows/deploy.yml`  
**Flow:** pnpm build → rsync (sudo) → chown lazarenet  
**Server:** VPS 109.199.99.205, SSH port 2222, user `neimar`  
**Deploy path:** `/home/lazarenet/public_html/aiboostnow/`  
**Keys:** `.local/deploy-keys/deploy_key` (private), `.local/deploy-keys/deploy_key.pub` (public, in neimar's authorized_keys)  
**GitHub Secrets:** `SSH_HOST`, `SSH_USER=neimar`, `SSH_DEPLOY_PATH`, `SSH_PRIVATE_KEY`  
**Gotcha:** rsync runs via `sudo rsync` — neimar mora imati passwordless sudo za rsync. Sudoers entry: `/etc/sudoers.d/neimar-rsync`

---

## Completed Tasks

| Task | What |
|------|------|
| #1 | Technical analysis and spec |
| #2 | Plugin refactor + new AEO features |
| #3 | Brand name decision → JoomlaBoost / AI Boost Now |
| #5 | i18n — 11 languages, 319 constants, 22 INI files |
| #6 | EN user documentation (17 sections), Getting Started PDF |
| #7 | JED listing, forum posts, Product Hunt, marketplace materials |
| #8 | Pricing strategy (Starter €59 / Developer €119 / Agency €199) + Gumroad setup guide |
| #17 | License key field in plugin admin (LicenseKeyField.php, all 11 lang files) |
| #CI | GitHub Actions CI/CD pipeline → aiboostnow.com (deploy kao neimar, sudo rsync) |

## Pending / Proposed Tasks

| Task | What | Status |
|------|------|--------|
| #9 | Logo & visual identity | PENDING |
| #10 | Marketing website (aiboostnow.com) | PENDING |
| #11 | Machine translation review (10 languages) | PENDING |
| #12 | Serbian user guide | PENDING |
| #13 | Styled PDFs for all docs | PENDING |
| #14 | Docs section on marketing website | PENDING |
| #15 | LinkedIn + email campaign copy | PROPOSED |
| #16 | AEO lead magnet PDF "Joomla AI Search Checklist 2026" | PROPOSED |
| #18 | Pricing page on marketing website | PROPOSED |
| #19 | Server-side Gumroad license validation | PROPOSED |
| #20 | Feature gating by license tier | PROPOSED |

---

## Pricing Model

| License | Price | Sites |
|---------|-------|-------|
| Starter | €59 one-time | 1 site |
| Developer | €119 one-time | 5 sites |
| Agency | €199 one-time | Unlimited |
| Renewals | 50% of original/year | Optional — plugin works without renewal |

**Payment processor:** Gumroad (Merchant of Record, handles EU VAT automatically)  
**Setup guide:** `.local/deliverables/pricing/gumroad-setup-guide.md`

---

## Key Deliverable Locations

| File | What |
|------|------|
| `.local/deliverables/plg_system_joomlaboost-0.24.0.zip` | Latest installable plugin ZIP |
| `.local/deliverables/docs/joomlaboost-user-guide.md` | EN user guide (17 sections) |
| `.local/deliverables/docs/getting-started-en.md` | EN getting started guide |
| `.local/deliverables/marketplace/JED-listing.md` | JED listing copy (EN) |
| `.local/deliverables/marketplace/forum-posts.md` | Forum & Facebook posts (EN + SR) |
| `.local/deliverables/marketplace/product-hunt.md` | Product Hunt listing (EN) |
| `.local/deliverables/pricing/pricing-strategy.md` | Pricing strategy & Gumroad comparison |
| `.local/deliverables/pricing/pricing-page-content.md` | Pricing page copy (EN, ready to implement) |
| `.local/deliverables/pricing/gumroad-setup-guide.md` | Step-by-step Gumroad setup |
| `.local/deliverables/JoomlaBoost-Brand-Brief.md` | Brand brief & name decision |

---

## Workspace Stack (this Replit)

- **Monorepo tool:** pnpm workspaces
- **Node.js:** 24
- **TypeScript:** 5.9
- **API framework:** Express 5
- **Database:** PostgreSQL + Drizzle ORM
- **Validation:** Zod (zod/v4), drizzle-zod
- **API codegen:** Orval (from OpenAPI spec)
- **Build:** esbuild (CJS bundle)

Key commands:
- `pnpm run typecheck` — full typecheck
- `pnpm --filter @workspace/api-spec run codegen` — regenerate API hooks from OpenAPI spec
- `pnpm --filter @workspace/db run push` — push DB schema (dev only)
