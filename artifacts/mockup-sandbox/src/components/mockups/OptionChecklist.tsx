import { useState, useEffect } from "react";

type Plugin =
  | "aiboost_schema"
  | "aiboost_opengraph"
  | "aiboost_codemanager"
  | "aiboost_aeo"
  | "aiboost_perf"
  | "new_plugin"
  | "n_a"
  | "";

type Status = "done" | "partial" | "missing" | "planned";

interface Option {
  id: string;
  tab: string;
  section: string;
  param: string;
  label: string;
  type: string;
  pro?: boolean;
  status: Status;
  plugin: Plugin;
  note?: string;
}

const PLUGINS: { value: Plugin; label: string }[] = [
  { value: "", label: "— odaberi —" },
  { value: "aiboost_schema", label: "Schema" },
  { value: "aiboost_opengraph", label: "OpenGraph" },
  { value: "aiboost_codemanager", label: "Code Manager" },
  { value: "aiboost_aeo", label: "AEO" },
  { value: "aiboost_perf", label: "Perf/Canonical" },
  { value: "new_plugin", label: "Novi plugin" },
  { value: "n_a", label: "N/A" },
];

const PLUGIN_COLORS: Record<string, string> = {
  aiboost_schema: "#8b5cf6",
  aiboost_opengraph: "#2563eb",
  aiboost_codemanager: "#d97706",
  aiboost_aeo: "#059669",
  aiboost_perf: "#dc2626",
  new_plugin: "#6b7280",
  n_a: "#9ca3af",
};

const ALL_OPTIONS: Option[] = [
  // ─── TAB 1: GENERAL ───────────────────────────────────────────────────────
  { id: "g1",  tab: "General", section: "License",      param: "license_key",             label: "License Key",                            type: "text",     status: "n_a" as any, plugin: "n_a", note: "Komponenta samo" },
  { id: "g2",  tab: "General", section: "License",      param: "license_tier",            label: "License Tier (read-only)",               type: "text",     status: "n_a" as any, plugin: "n_a", note: "Komponenta samo" },
  { id: "g3",  tab: "General", section: "Interface",    param: "show_advanced_options",   label: "Show Advanced Options",                  type: "checkbox", status: "n_a" as any, plugin: "n_a", note: "Komponenta samo" },
  { id: "g4",  tab: "General", section: "Quick Setup",  param: "vertical_preset",         label: "Site Type Preset (13 tipova)",           type: "select",   status: "n_a" as any, plugin: "n_a", note: "Komponenta samo" },
  { id: "g5",  tab: "General", section: "Domain",       param: "auto_domain_detection",   label: "Auto-detect domain",                     type: "checkbox", status: "missing", plugin: "" },
  { id: "g6",  tab: "General", section: "Domain",       param: "manual_domain",           label: "Manual Domain override",                 type: "url",      status: "missing", plugin: "" },
  { id: "g7",  tab: "General", section: "robots.txt",   param: "enable_robots",           label: "Enable robots.txt management",           type: "checkbox", status: "done",    plugin: "aiboost_aeo",  note: "robots_txt_enabled" },
  { id: "g8",  tab: "General", section: "robots.txt",   param: "robots_auto_sync",        label: "Auto-sync physical robots.txt file",     type: "checkbox", status: "missing", plugin: "" },
  { id: "g9",  tab: "General", section: "AI Crawlers",  param: "ai_crawlers_enabled",     label: "Enable crawler management",              type: "checkbox", status: "partial", plugin: "aiboost_aeo",  note: "Postoji kao robots_ai_mode" },
  { id: "g10", tab: "General", section: "AI Crawlers",  param: "crawler_disabled_bots",   label: "28-bot checkbox grid (3 grupe)",         type: "widget",   status: "planned", plugin: "aiboost_aeo",  note: "v1.3.0 — CrawlerGridField" },
  { id: "g11", tab: "General", section: "AI Crawlers",  param: "crawler_rules",           label: "Custom Rules — vizualni rule builder",   type: "widget",   status: "missing", plugin: "" },

  // ─── TAB 2: ORGANIZATION ─────────────────────────────────────────────────
  { id: "o1",  tab: "Organization", section: "Identity",  param: "org_name_en",               label: "Organization Name (EN)",               type: "text",     status: "partial", plugin: "aiboost_aeo",     note: "llmstxt_org_name — nije multilang" },
  { id: "o2",  tab: "Organization", section: "Identity",  param: "org_description_en",         label: "Organization Description (EN)",        type: "textarea", status: "partial", plugin: "aiboost_aeo",     note: "llmstxt_org_desc — nije multilang" },
  { id: "o3",  tab: "Organization", section: "Identity",  param: "schema_logo_url",            label: "Organization Logo URL",                type: "media",    status: "done",    plugin: "aiboost_schema" },
  { id: "o4",  tab: "Organization", section: "Contact",   param: "schema_url",                 label: "Organization URL",                     type: "url",      status: "done",    plugin: "aiboost_schema" },
  { id: "o5",  tab: "Organization", section: "Contact",   param: "schema_email",               label: "Email Address",                        type: "email",    status: "done",    plugin: "aiboost_schema" },
  { id: "o6",  tab: "Organization", section: "Contact",   param: "schema_phone",               label: "Phone Number (E.164)",                 type: "tel",      status: "done",    plugin: "aiboost_schema" },
  { id: "o7",  tab: "Organization", section: "Social",    param: "schema_social_facebook",     label: "Facebook URL",                         type: "url",      status: "done",    plugin: "aiboost_schema" },
  { id: "o8",  tab: "Organization", section: "Social",    param: "schema_social_instagram",    label: "Instagram URL",                        type: "url",      status: "done",    plugin: "aiboost_schema" },
  { id: "o9",  tab: "Organization", section: "Social",    param: "schema_social_youtube",      label: "YouTube URL",                          type: "url",      status: "done",    plugin: "aiboost_schema" },
  { id: "o10", tab: "Organization", section: "Social",    param: "schema_social_twitter",      label: "Twitter/X URL",                        type: "url",      status: "done",    plugin: "aiboost_schema" },
  { id: "o11", tab: "Organization", section: "Social",    param: "schema_social_linkedin",     label: "LinkedIn URL",                         type: "url",      status: "done",    plugin: "aiboost_schema" },
  { id: "o12", tab: "Organization", section: "Social",    param: "schema_social_tiktok",       label: "TikTok URL",                           type: "url",      status: "done",    plugin: "aiboost_schema" },
  { id: "o13", tab: "Organization", section: "Social",    param: "schema_social_pinterest",    label: "Pinterest URL",                        type: "url",      status: "done",    plugin: "aiboost_schema" },
  { id: "o14", tab: "Organization", section: "Address",   param: "schema_address_street_en",   label: "Street Address",                       type: "text",     status: "done",    plugin: "aiboost_schema" },
  { id: "o15", tab: "Organization", section: "Address",   param: "schema_address_locality_en", label: "City / Locality",                      type: "text",     status: "done",    plugin: "aiboost_schema" },
  { id: "o16", tab: "Organization", section: "Address",   param: "schema_address_zip",         label: "Postal Code",                          type: "text",     status: "done",    plugin: "aiboost_schema" },
  { id: "o17", tab: "Organization", section: "Address",   param: "schema_address_country",     label: "Country Code (2 chars)",               type: "text",     status: "done",    plugin: "aiboost_schema" },
  { id: "o18", tab: "Organization", section: "Geo",       param: "schema_latitude",            label: "Latitude + Map picker (OpenStreetMap)", type: "text",    status: "done",    plugin: "aiboost_schema" },
  { id: "o19", tab: "Organization", section: "Geo",       param: "schema_longitude",           label: "Longitude",                            type: "text",     status: "done",    plugin: "aiboost_schema" },
  { id: "o20", tab: "Organization", section: "Rating",    param: "schema_rating_value",        label: "Rating Value (AggregateRating)",       type: "text",     status: "missing", plugin: "" },
  { id: "o21", tab: "Organization", section: "Rating",    param: "schema_rating_count",        label: "Review Count",                         type: "number",   status: "missing", plugin: "" },
  { id: "o22", tab: "Organization", section: "Rating",    param: "schema_rating_best",         label: "Best Rating",                          type: "number",   status: "missing", plugin: "" },
  { id: "o23", tab: "Organization", section: "Rating",    param: "schema_rating_worst",        label: "Worst Rating",                         type: "number",   status: "missing", plugin: "" },
  { id: "o24", tab: "Organization", section: "Rating",    param: "schema_rating_source",       label: "Rating Source (npr. Booking.com)",     type: "text",     status: "missing", plugin: "" },

  // ─── TAB 3: SCHEMA.ORG ───────────────────────────────────────────────────
  { id: "s1",  tab: "Schema.org", section: "General",       param: "enable_schema",               label: "Enable Schema.org structured data",         type: "checkbox", status: "done",    plugin: "aiboost_schema" },
  { id: "s2",  tab: "Schema.org", section: "General",       param: "page_type_auto_detect",       label: "Auto-detect page types (About, Contact...)", type: "checkbox", status: "missing", plugin: "" },
  { id: "s3",  tab: "Schema.org", section: "Org Type",      param: "schema_type",                 label: "Schema.org Organization Type (18 tipova)",   type: "select",   status: "done",    plugin: "aiboost_schema" },
  { id: "s4",  tab: "Schema.org", section: "Org Type",      param: "schema_org_image",            label: "Organization / Venue Photo URL",             type: "media",    status: "missing", plugin: "" },
  { id: "s5",  tab: "Schema.org", section: "Org Type",      param: "schema_price_range",          label: "Price Range ($–$$$$)",                       type: "select",   status: "missing", plugin: "" },
  { id: "s6",  tab: "Schema.org", section: "Hotel",         param: "schema_hotel_star_rating",    label: "Hotel: Star Rating",                         type: "select",   status: "done",    plugin: "aiboost_schema" },
  { id: "s7",  tab: "Schema.org", section: "Hotel",         param: "schema_hotel_checkin_time",   label: "Hotel: Check-in Time",                       type: "time",     status: "done",    plugin: "aiboost_schema" },
  { id: "s8",  tab: "Schema.org", section: "Hotel",         param: "schema_hotel_checkout_time",  label: "Hotel: Check-out Time",                      type: "time",     status: "done",    plugin: "aiboost_schema" },
  { id: "s9",  tab: "Schema.org", section: "Hotel",         param: "schema_hotel_pets_allowed",   label: "Hotel: Pets Allowed",                        type: "select",   status: "done",    plugin: "aiboost_schema" },
  { id: "s10", tab: "Schema.org", section: "Hotel",         param: "schema_hotel_image",          label: "Hotel: Featured Image",                      type: "media",    status: "done",    plugin: "aiboost_schema" },
  { id: "s11", tab: "Schema.org", section: "Medical",       param: "schema_medical_specialty",    label: "Medical Specialty",                          type: "text",     status: "missing", plugin: "" },
  { id: "s12", tab: "Schema.org", section: "Lawyer",        param: "schema_legal_area",           label: "Area of Practice",                           type: "text",     status: "missing", plugin: "" },
  { id: "s13", tab: "Schema.org", section: "School",        param: "schema_edu_level",            label: "Education Level",                            type: "select",   status: "missing", plugin: "" },
  { id: "s14", tab: "Schema.org", section: "Portfolio",     param: "schema_job_title",            label: "Job Title / Profession",                     type: "text",     status: "missing", plugin: "" },
  { id: "s15", tab: "Schema.org", section: "Portfolio",     param: "schema_portfolio_url",        label: "Portfolio URL",                              type: "url",      status: "missing", plugin: "" },
  { id: "s16", tab: "Schema.org", section: "Dentist",       param: "schema_dental_specialty",     label: "Dental Specialty / Services",                type: "text",     status: "missing", plugin: "" },
  { id: "s17", tab: "Schema.org", section: "Real Estate",   param: "schema_realestate_area_served", label: "Area Served",                             type: "text",     status: "missing", plugin: "" },
  { id: "s18", tab: "Schema.org", section: "Real Estate",   param: "schema_realestate_property_types", label: "Property Types",                       type: "text",     status: "missing", plugin: "" },
  { id: "s19", tab: "Schema.org", section: "Gym",           param: "schema_gym_sport",            label: "Primary Sport / Activity",                   type: "text",     status: "missing", plugin: "" },
  { id: "s20", tab: "Schema.org", section: "Gym",           param: "schema_gym_amenities",        label: "Amenities",                                  type: "text",     status: "missing", plugin: "" },
  { id: "s21", tab: "Schema.org", section: "News",          param: "schema_news_topics",          label: "Editorial Topics",                           type: "text",     status: "missing", plugin: "" },
  { id: "s22", tab: "Schema.org", section: "News",          param: "schema_news_principles",      label: "Publishing Principles URL",                  type: "url",      status: "missing", plugin: "" },
  { id: "s23", tab: "Schema.org", section: "Hours",         param: "schema_hours_mode",           label: "Opening Hours Mode (simple/advanced/none)",  type: "select",   status: "done",    plugin: "aiboost_schema" },
  { id: "s24", tab: "Schema.org", section: "Hours",         param: "schema_opening_hours",        label: "Opening Hours Text (simple mode)",           type: "text",     status: "done",    plugin: "aiboost_schema" },
  { id: "s25", tab: "Schema.org", section: "Hours",         param: "schema_hours_temp_closed",    label: "Temporarily Closed",                         type: "checkbox", status: "done",    plugin: "aiboost_schema" },
  { id: "s26", tab: "Schema.org", section: "Hours",         param: "schema_hours_appointment_only", label: "By Appointment Only",                     type: "checkbox", status: "missing", plugin: "" },
  { id: "s27", tab: "Schema.org", section: "Hours",         param: "schema_business_hours",       label: "Business Hours Widget (JSON, 7 dana)",       type: "widget",   status: "done",    plugin: "aiboost_schema" },
  { id: "s28", tab: "Schema.org", section: "Hours",         param: "schema_season_from",          label: "Season Start (MM-DD)",                       type: "text",     status: "missing", plugin: "" },
  { id: "s29", tab: "Schema.org", section: "Hours",         param: "schema_season_to",            label: "Season End (MM-DD)",                         type: "text",     status: "missing", plugin: "" },
  { id: "s30", tab: "Schema.org", section: "Hours",         param: "schema_holiday_closed",       label: "Holiday Closures (YYYY-MM-DD po liniji)",    type: "textarea", status: "missing", plugin: "" },
  { id: "s31", tab: "Schema.org", section: "FAQ",           param: "faq_auto_detect",             label: "Auto-Detect FAQ from Content",               type: "checkbox", status: "done",    plugin: "aiboost_schema" },
  { id: "s32", tab: "Schema.org", section: "FAQ",           param: "enable_manual_faqs",          label: "Enable Manual FAQs",                         type: "checkbox", pro: true, status: "missing", plugin: "" },
  { id: "s33", tab: "Schema.org", section: "FAQ",           param: "manual_faq_scope",            label: "Manual FAQ — When to Apply",                 type: "select",   pro: true, status: "missing", plugin: "" },
  { id: "s34", tab: "Schema.org", section: "FAQ",           param: "manual_faqs_en",              label: "Manual FAQ Items JSON (EN)",                 type: "textarea", pro: true, status: "missing", plugin: "" },
  { id: "s35", tab: "Schema.org", section: "WebSite",       param: "website_schema_enabled",      label: "Enable WebSite Schema (homepage)",           type: "checkbox", status: "missing", plugin: "" },
  { id: "s36", tab: "Schema.org", section: "WebSite",       param: "website_schema_search_enabled", label: "Include SearchAction (Sitelinks Box)",    type: "checkbox", status: "missing", plugin: "" },
  { id: "s37", tab: "Schema.org", section: "Article",       param: "article_schema_enabled",      label: "Enable Article Schema",                      type: "checkbox", status: "missing", plugin: "" },
  { id: "s38", tab: "Schema.org", section: "Article",       param: "article_schema_type_auto",    label: "Always use BlogPosting type",                type: "checkbox", status: "missing", plugin: "" },
  { id: "s39", tab: "Schema.org", section: "Article",       param: "schema_author_name",          label: "Default Article Author Name",                type: "text",     status: "missing", plugin: "" },
  { id: "s40", tab: "Schema.org", section: "Article",       param: "schema_author_url",           label: "Default Article Author URL",                 type: "url",      status: "missing", plugin: "" },
  { id: "s41", tab: "Schema.org", section: "Events",        param: "schema_events_enabled",       label: "Enable Event Schema",                        type: "checkbox", pro: true, status: "done",    plugin: "aiboost_schema" },
  { id: "s42", tab: "Schema.org", section: "Events",        param: "schema_events_en",            label: "Events JSON (EN)",                           type: "textarea", pro: true, status: "done",    plugin: "aiboost_schema" },

  // ─── TAB 4: SITEMAP (sadrži i Title/Meta/Redirects/Canonical/Hreflang) ──
  { id: "sm1",  tab: "Sitemap", section: "Title Templates", param: "title_template_home",     label: "Homepage title template",              type: "text",     status: "missing", plugin: "" },
  { id: "sm2",  tab: "Sitemap", section: "Title Templates", param: "title_template_article",  label: "Article title template",               type: "text",     status: "missing", plugin: "" },
  { id: "sm3",  tab: "Sitemap", section: "Title Templates", param: "title_template_category", label: "Category title template",              type: "text",     status: "missing", plugin: "" },
  { id: "sm4",  tab: "Sitemap", section: "Title Templates", param: "title_template_search",   label: "Search results title template",        type: "text",     status: "missing", plugin: "" },
  { id: "sm5",  tab: "Sitemap", section: "Title Templates", param: "title_template_tag",      label: "Tag title template",                   type: "text",     status: "missing", plugin: "" },
  { id: "sm6",  tab: "Sitemap", section: "Title Templates", param: "title_template_default",  label: "Default title template (fallback)",    type: "text",     status: "missing", plugin: "" },
  { id: "sm7",  tab: "Sitemap", section: "Title Templates", param: "title_template",          label: "Global title template (legacy)",       type: "text",     status: "missing", plugin: "" },
  { id: "sm8",  tab: "Sitemap", section: "Title Templates", param: "title_separator",         label: "Title Separator (default: ' | ')",     type: "text",     status: "missing", plugin: "" },
  { id: "sm9",  tab: "Sitemap", section: "Title Templates", param: "title_template_maxlen",   label: "Max title length (chars, SEO: 60)",    type: "number",   status: "missing", plugin: "" },
  { id: "sm10", tab: "Sitemap", section: "Meta Desc",       param: "meta_desc_template_article", label: "Article meta description template", type: "text",     status: "missing", plugin: "" },
  { id: "sm11", tab: "Sitemap", section: "Meta Desc",       param: "meta_desc_template_default", label: "Default meta description template", type: "text",     status: "missing", plugin: "" },
  { id: "sm12", tab: "Sitemap", section: "Meta Desc",       param: "meta_desc_template",      label: "Global meta description template",     type: "text",     status: "missing", plugin: "" },
  { id: "sm13", tab: "Sitemap", section: "Meta Desc",       param: "meta_desc_maxlen",        label: "Max description length (SEO: 160)",    type: "number",   status: "missing", plugin: "" },
  { id: "sm14", tab: "Sitemap", section: "Redirects",       param: "redirect_enabled",        label: "Enable Redirect Manager",              type: "checkbox", status: "missing", plugin: "" },
  { id: "sm15", tab: "Sitemap", section: "Redirects",       param: "redirect_404_log_enabled", label: "Log 404 Errors",                     type: "checkbox", status: "missing", plugin: "" },
  { id: "sm16", tab: "Sitemap", section: "Canonical",       param: "enable_canonical",        label: "Manage canonical URL",                 type: "checkbox", status: "done",    plugin: "aiboost_perf" },
  { id: "sm17", tab: "Sitemap", section: "Canonical",       param: "canonical_url_map",       label: "Canonical URL Map (regex rules)",      type: "textarea", status: "missing", plugin: "" },
  { id: "sm18", tab: "Sitemap", section: "Hreflang",        param: "enable_hreflang",         label: "Enable hreflang tags",                 type: "checkbox", status: "done",    plugin: "aiboost_opengraph" },
  { id: "sm19", tab: "Sitemap", section: "XML Sitemap",     param: "enable_sitemap",          label: "Enable XML Sitemap",                   type: "checkbox", status: "done",    plugin: "aiboost_schema" },
  { id: "sm20", tab: "Sitemap", section: "XML Sitemap",     param: "sitemap_include_articles", label: "Sitemap: Include Articles",           type: "checkbox", status: "done",    plugin: "aiboost_schema" },
  { id: "sm21", tab: "Sitemap", section: "XML Sitemap",     param: "sitemap_include_categories", label: "Sitemap: Include Categories",       type: "checkbox", status: "done",    plugin: "aiboost_schema" },
  { id: "sm22", tab: "Sitemap", section: "XML Sitemap",     param: "sitemap_include_menus",   label: "Sitemap: Include Menu Items",          type: "checkbox", status: "done",    plugin: "aiboost_schema" },
  { id: "sm23", tab: "Sitemap", section: "XML Sitemap",     param: "sitemap_max_articles",    label: "Sitemap: Max Articles",                type: "number",   status: "done",    plugin: "aiboost_schema" },
  { id: "sm24", tab: "Sitemap", section: "XML Sitemap",     param: "sitemap_exclude_ids",     label: "Sitemap: Exclude Article IDs (chip)", type: "widget",   status: "done",    plugin: "aiboost_schema" },
  { id: "sm25", tab: "Sitemap", section: "XML Sitemap",     param: "sitemap_exclude_urls",    label: "Sitemap: Exclude URL Patterns (chip)", type: "widget",  status: "missing", plugin: "" },
  { id: "sm26", tab: "Sitemap", section: "XML Sitemap",     param: "sitemap_hreflang",        label: "Add hreflang to XML Sitemap",          type: "checkbox", status: "done",    plugin: "aiboost_schema" },
  { id: "sm27", tab: "Sitemap", section: "SEO Priority",    param: "sitemap_priority_articles", label: "Articles Priority (range 0–1)",      type: "range",    status: "done",    plugin: "aiboost_schema" },
  { id: "sm28", tab: "Sitemap", section: "SEO Priority",    param: "sitemap_priority_categories", label: "Categories Priority",             type: "range",    status: "done",    plugin: "aiboost_schema" },
  { id: "sm29", tab: "Sitemap", section: "SEO Priority",    param: "sitemap_priority_menu",   label: "Menu Priority",                        type: "range",    status: "done",    plugin: "aiboost_schema" },
  { id: "sm30", tab: "Sitemap", section: "Changefreq",      param: "sitemap_changefreq_articles", label: "Articles Frequency (daily/weekly/monthly)", type: "pills", status: "done", plugin: "aiboost_schema" },
  { id: "sm31", tab: "Sitemap", section: "Changefreq",      param: "sitemap_changefreq_categories", label: "Categories Frequency",         type: "pills",    status: "done",    plugin: "aiboost_schema" },
  { id: "sm32", tab: "Sitemap", section: "Changefreq",      param: "sitemap_changefreq_menu", label: "Menu Frequency",                       type: "pills",    status: "done",    plugin: "aiboost_schema" },

  // ─── TAB 5: SOCIAL & META ────────────────────────────────────────────────
  { id: "c1",  tab: "Social & Meta", section: "OpenGraph",     param: "enable_opengraph",              label: "Enable OpenGraph tags",                       type: "checkbox", status: "done",    plugin: "aiboost_opengraph" },
  { id: "c2",  tab: "Social & Meta", section: "OpenGraph",     param: "og_site_name",                  label: "OG Site Name",                                type: "text",     status: "planned", plugin: "aiboost_opengraph", note: "v1.3.0 multilang" },
  { id: "c3",  tab: "Social & Meta", section: "OpenGraph",     param: "og_default_image",              label: "Default OG Image",                            type: "media",    status: "planned", plugin: "aiboost_opengraph", note: "v1.3.0 multilang" },
  { id: "c4",  tab: "Social & Meta", section: "Twitter",       param: "enable_twitter_cards",          label: "Enable Twitter Card meta tags",               type: "checkbox", status: "done",    plugin: "aiboost_opengraph" },
  { id: "c5",  tab: "Social & Meta", section: "Meta Pixel",    param: "enable_meta_pixel",             label: "Enable Meta (Facebook) Pixel",                type: "checkbox", status: "done",    plugin: "aiboost_codemanager" },
  { id: "c6",  tab: "Social & Meta", section: "Meta Pixel",    param: "meta_pixel_ids",                label: "Pixel IDs chip widget (do 5)",                type: "widget",   status: "done",    plugin: "aiboost_codemanager", note: "textarea one-per-line" },
  { id: "c7",  tab: "Social & Meta", section: "Meta Pixel",    param: "pixel_consent_mode",            label: "GDPR Consent Mode (None / YooTheme)",         type: "select",   status: "done",    plugin: "aiboost_codemanager" },
  { id: "c8",  tab: "Social & Meta", section: "Meta Pixel",    param: "fb_domain_verification",        label: "Facebook Domain Verification",                type: "text",     pro: true, status: "done", plugin: "aiboost_codemanager" },
  { id: "c9",  tab: "Social & Meta", section: "Pixel Events",  param: "meta_pixel_standard_events",    label: "Standard Events tabela (15 event-a toggle)", type: "widget",   pro: true, status: "missing", plugin: "" },
  { id: "c10", tab: "Social & Meta", section: "Pixel Events",  param: "meta_custom_events",            label: "Custom Events repeater (name + URL pattern)", type: "widget",  pro: true, status: "missing", plugin: "" },

  // ─── TAB 6: ANALYTICS ────────────────────────────────────────────────────
  { id: "a1",  tab: "Analytics", section: "Site Verification", param: "enable_google_verification",    label: "Enable Google Search Console Verification",   type: "checkbox", status: "done",    plugin: "aiboost_codemanager" },
  { id: "a2",  tab: "Analytics", section: "Site Verification", param: "gsc_codes",                     label: "GSC Verification Codes chip (do 10)",         type: "widget",   status: "done",    plugin: "aiboost_codemanager", note: "textarea one-per-line" },
  { id: "a3",  tab: "Analytics", section: "Site Verification", param: "gsc_additional_html",           label: "Additional Verification HTML (textarea)",     type: "textarea", status: "missing", plugin: "" },
  { id: "a4",  tab: "Analytics", section: "GA4",               param: "enable_ga4",                    label: "Enable Google Analytics 4",                   type: "checkbox", status: "done",    plugin: "aiboost_codemanager" },
  { id: "a5",  tab: "Analytics", section: "GA4",               param: "ga4_measurement_id",            label: "GA4 Measurement ID (G-XXXXXXXXXX)",           type: "text",     status: "done",    plugin: "aiboost_codemanager" },
  { id: "a6",  tab: "Analytics", section: "GA4",               param: "ga4_consent_mode",              label: "GA4 GDPR Consent Mode (None/YooTheme/GTM)",   type: "select",   status: "done",    plugin: "aiboost_codemanager" },
  { id: "a7",  tab: "Analytics", section: "GTM",               param: "enable_gtm",                    label: "Enable Google Tag Manager",                   type: "checkbox", status: "done",    plugin: "aiboost_codemanager" },
  { id: "a8",  tab: "Analytics", section: "GTM",               param: "gtm_container_id",              label: "GTM Container ID (GTM-XXXXXXX)",              type: "text",     status: "done",    plugin: "aiboost_codemanager" },
  { id: "a9",  tab: "Analytics", section: "IndexNow",          param: "indexnow_enabled",              label: "Enable IndexNow",                             type: "checkbox", pro: true, status: "done", plugin: "aiboost_aeo" },
  { id: "a10", tab: "Analytics", section: "IndexNow",          param: "indexnow_auto_submit",          label: "Auto-Submit on Publish",                      type: "checkbox", pro: true, status: "done", plugin: "aiboost_aeo" },
  { id: "a11", tab: "Analytics", section: "IndexNow",          param: "indexnow_api_key",              label: "IndexNow API Key + Generate button",          type: "widget",   pro: true, status: "done", plugin: "aiboost_aeo" },
  { id: "a12", tab: "Analytics", section: "LLMs.txt",          param: "llmstxt_enabled",               label: "Generate /llms.txt",                          type: "checkbox", pro: true, status: "done", plugin: "aiboost_aeo" },
  { id: "a13", tab: "Analytics", section: "LLMs.txt",          param: "llms_full_txt_enabled",         label: "Generate /llms-full.txt (Extended Index)",    type: "checkbox", pro: true, status: "done", plugin: "aiboost_aeo" },
  { id: "a14", tab: "Analytics", section: "LLMs.txt",          param: "llmstxt_custom_pages_en",       label: "Custom Pages JSON (EN) za llms.txt",          type: "textarea", pro: true, status: "partial", plugin: "aiboost_aeo", note: "Plugin koristi Markdown format, komponenta JSON" },

  // ─── TAB 7: CUSTOM CODE ──────────────────────────────────────────────────
  { id: "cc1", tab: "Custom Code", section: "Injection",   param: "enable_custom_code",      label: "Enable Custom Code Injection",         type: "checkbox", status: "done",    plugin: "aiboost_codemanager" },
  { id: "cc2", tab: "Custom Code", section: "Head",        param: "custom_code_head",        label: "Inject before </head> (CodeMirror)",   type: "textarea", status: "done",    plugin: "aiboost_codemanager" },
  { id: "cc3", tab: "Custom Code", section: "Body",        param: "custom_code_body",        label: "Inject after <body> (CodeMirror)",     type: "textarea", status: "done",    plugin: "aiboost_codemanager" },
  { id: "cc4", tab: "Custom Code", section: "Apply To",    param: "custom_code_scope",       label: "Apply To: All Pages / Specific Menu",  type: "radio",    status: "done",    plugin: "aiboost_codemanager" },
  { id: "cc5", tab: "Custom Code", section: "Apply To",    param: "custom_code_menu_ids",    label: "Menu Items checklist (per menutype)",  type: "widget",   status: "missing", plugin: "" },

  // ─── TAB 8: DEBUG ────────────────────────────────────────────────────────
  { id: "d1",  tab: "Debug", section: "Debug", param: "debug_mode",            label: "Enable debug mode (verbose logging)",          type: "checkbox", status: "missing", plugin: "" },
  { id: "d2",  tab: "Debug", section: "Debug", param: "debug_wrap_markers",    label: "Inject HTML wrap markers",                     type: "checkbox", status: "missing", plugin: "" },
  { id: "d3",  tab: "Debug", section: "Debug", param: "dev_license_preview",   label: "Simulate Professional license (bypass key)",   type: "checkbox", status: "partial", plugin: "",         note: "Postoji u schema pluginu" },
  { id: "d4",  tab: "Debug", section: "Debug", param: "dev_force_free_tier",   label: "Force Free tier (test gating)",                type: "checkbox", status: "partial", plugin: "",         note: "Postoji u schema pluginu" },
  { id: "d5",  tab: "Debug", section: "Debug", param: "staging_mode",          label: "Staging mode (suppress analytics/IndexNow)",   type: "checkbox", status: "done",    plugin: "aiboost_aeo", note: "I u OpenGraph pluginu" },
];

const STATUS_INFO: Record<Status, { label: string; color: string; bg: string }> = {
  done:    { label: "✅ Implementirano",   color: "#065f46", bg: "#d1fae5" },
  partial: { label: "⚠️ Djelimično",       color: "#92400e", bg: "#fef3c7" },
  missing: { label: "❌ Nedostaje",         color: "#991b1b", bg: "#fee2e2" },
  planned: { label: "🔵 Planirano v1.3.0", color: "#1e40af", bg: "#dbeafe" },
};

const TABS = [...new Set(ALL_OPTIONS.map((o) => o.tab))];

type RowState = {
  implement: boolean;
  plugin: Plugin;
  note: string;
};

const STORAGE_KEY = "aiboost_checklist_v1";

function loadState(): Record<string, RowState> {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw) return JSON.parse(raw) as Record<string, RowState>;
  } catch {}
  const init: Record<string, RowState> = {};
  ALL_OPTIONS.forEach((o) => {
    init[o.id] = {
      implement: o.status !== ("n_a" as any) && o.status !== "done",
      plugin: o.plugin,
      note: o.note ?? "",
    };
  });
  return init;
}

export default function OptionChecklist() {
  const [activeTab, setActiveTab] = useState(TABS[0]);
  const [rows, setRows] = useState<Record<string, RowState>>(loadState);
  const [filterStatus, setFilterStatus] = useState<Status | "all">("all");
  const [filterImplement, setFilterImplement] = useState<"all" | "yes" | "no">("all");
  const [exported, setExported] = useState(false);

  useEffect(() => {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(rows)); } catch {}
  }, [rows]);

  function updateRow(id: string, patch: Partial<RowState>) {
    setRows((prev) => ({ ...prev, [id]: { ...prev[id], ...patch } }));
  }

  function exportJson() {
    const result = ALL_OPTIONS.map((o) => ({
      tab: o.tab,
      section: o.section,
      param: o.param,
      label: o.label,
      status: o.status,
      implement: rows[o.id].implement,
      plugin: rows[o.id].plugin,
      note: rows[o.id].note,
    }));
    const blob = new Blob([JSON.stringify(result, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url; a.download = "aiboost-options-checklist.json"; a.click();
    URL.revokeObjectURL(url);
    setExported(true);
    setTimeout(() => setExported(false), 2000);
  }

  const tabOptions = ALL_OPTIONS.filter((o) => o.tab === activeTab);
  const filteredOptions = tabOptions.filter((o) => {
    if (filterStatus !== "all" && o.status !== filterStatus) return false;
    if (filterImplement === "yes" && !rows[o.id].implement) return false;
    if (filterImplement === "no" && rows[o.id].implement) return false;
    return true;
  });

  const sections = [...new Set(filteredOptions.map((o) => o.section))];

  const totalImplement = ALL_OPTIONS.filter((o) => rows[o.id].implement).length;
  const totalMissing = ALL_OPTIONS.filter((o) => o.status === "missing").length;
  const totalDone = ALL_OPTIONS.filter((o) => o.status === "done").length;

  return (
    <div style={{ fontFamily: "system-ui, sans-serif", fontSize: 13, minHeight: "100vh", background: "#f8fafc" }}>
      {/* Header */}
      <div style={{ background: "#1e293b", color: "#fff", padding: "14px 20px", display: "flex", alignItems: "center", gap: 16 }}>
        <div style={{ fontWeight: 700, fontSize: 15 }}>AI Boost — Component Options Checklist</div>
        <div style={{ marginLeft: "auto", display: "flex", gap: 10, alignItems: "center", fontSize: 12 }}>
          <span style={{ background: "#d1fae5", color: "#065f46", borderRadius: 4, padding: "2px 8px" }}>✅ {totalDone} done</span>
          <span style={{ background: "#fee2e2", color: "#991b1b", borderRadius: 4, padding: "2px 8px" }}>❌ {totalMissing} missing</span>
          <span style={{ background: "#dbeafe", color: "#1e40af", borderRadius: 4, padding: "2px 8px" }}>🔵 označeno za impl.: {totalImplement}</span>
          <button onClick={exportJson} style={{ background: exported ? "#059669" : "#3b82f6", color: "#fff", border: "none", borderRadius: 5, padding: "5px 12px", cursor: "pointer", fontWeight: 600 }}>
            {exported ? "✓ Saved!" : "⬇ Export JSON"}
          </button>
        </div>
      </div>

      {/* Tab nav */}
      <div style={{ display: "flex", background: "#fff", borderBottom: "2px solid #e2e8f0", overflowX: "auto", padding: "0 12px" }}>
        {TABS.map((tab) => {
          const count = ALL_OPTIONS.filter((o) => o.tab === tab && rows[o.id].implement).length;
          return (
            <button key={tab} onClick={() => setActiveTab(tab)}
              style={{ padding: "10px 14px", border: "none", background: "none", cursor: "pointer", fontWeight: activeTab === tab ? 700 : 400,
                borderBottom: activeTab === tab ? "2px solid #3b82f6" : "2px solid transparent", color: activeTab === tab ? "#1d4ed8" : "#374151",
                whiteSpace: "nowrap", fontSize: 13 }}>
              {tab}
              {count > 0 && <span style={{ marginLeft: 5, background: "#dbeafe", color: "#1d4ed8", borderRadius: 10, padding: "1px 6px", fontSize: 11 }}>{count}</span>}
            </button>
          );
        })}
      </div>

      {/* Filters */}
      <div style={{ display: "flex", gap: 12, padding: "8px 16px", background: "#fff", borderBottom: "1px solid #e2e8f0", alignItems: "center" }}>
        <span style={{ color: "#6b7280", fontWeight: 600 }}>Filter:</span>
        <select value={filterStatus} onChange={(e) => setFilterStatus(e.target.value as any)}
          style={{ border: "1px solid #d1d5db", borderRadius: 4, padding: "3px 8px", fontSize: 12 }}>
          <option value="all">Sve opcije</option>
          <option value="done">✅ Implementirano</option>
          <option value="partial">⚠️ Djelimično</option>
          <option value="missing">❌ Nedostaje</option>
          <option value="planned">🔵 Planirano</option>
        </select>
        <select value={filterImplement} onChange={(e) => setFilterImplement(e.target.value as any)}
          style={{ border: "1px solid #d1d5db", borderRadius: 4, padding: "3px 8px", fontSize: 12 }}>
          <option value="all">Svi redovi</option>
          <option value="yes">Samo označeni za impl.</option>
          <option value="no">Samo neoznačeni</option>
        </select>
        <span style={{ color: "#9ca3af", fontSize: 11 }}>{filteredOptions.length} / {tabOptions.length} opcija</span>
      </div>

      {/* Table */}
      <div style={{ padding: "12px 16px" }}>
        {sections.map((section) => {
          const sectionOptions = filteredOptions.filter((o) => o.section === section);
          return (
            <div key={section} style={{ marginBottom: 20 }}>
              <div style={{ fontWeight: 700, fontSize: 11, textTransform: "uppercase", letterSpacing: "0.07em",
                color: "#6b7280", marginBottom: 6, paddingLeft: 2 }}>{section}</div>
              <table style={{ width: "100%", borderCollapse: "collapse", background: "#fff", borderRadius: 8, overflow: "hidden",
                boxShadow: "0 1px 3px rgba(0,0,0,0.08)" }}>
                <thead>
                  <tr style={{ background: "#f1f5f9", fontSize: 11, color: "#64748b" }}>
                    <th style={{ padding: "6px 10px", textAlign: "left", width: 32 }}>Impl.</th>
                    <th style={{ padding: "6px 10px", textAlign: "left", width: 180 }}>Param</th>
                    <th style={{ padding: "6px 10px", textAlign: "left" }}>Label</th>
                    <th style={{ padding: "6px 10px", textAlign: "left", width: 80 }}>Tip</th>
                    <th style={{ padding: "6px 10px", textAlign: "left", width: 140 }}>Status</th>
                    <th style={{ padding: "6px 10px", textAlign: "left", width: 160 }}>Plugin</th>
                    <th style={{ padding: "6px 10px", textAlign: "left", width: 180 }}>Napomena</th>
                  </tr>
                </thead>
                <tbody>
                  {sectionOptions.map((o, idx) => {
                    const row = rows[o.id];
                    const st = STATUS_INFO[o.status as Status] ?? STATUS_INFO.missing;
                    const isNA = o.status === ("n_a" as any);
                    return (
                      <tr key={o.id} style={{ borderTop: idx > 0 ? "1px solid #f1f5f9" : undefined,
                        background: row.implement && !isNA ? "#fafffe" : isNA ? "#f8fafc" : "#fff" }}>
                        <td style={{ padding: "6px 10px", textAlign: "center" }}>
                          {!isNA && (
                            <input type="checkbox" checked={row.implement}
                              onChange={(e) => updateRow(o.id, { implement: e.target.checked })}
                              style={{ width: 15, height: 15, cursor: "pointer" }} />
                          )}
                        </td>
                        <td style={{ padding: "6px 10px" }}>
                          <code style={{ fontSize: 11, background: "#f1f5f9", padding: "1px 5px", borderRadius: 3, color: "#374151" }}>{o.param}</code>
                        </td>
                        <td style={{ padding: "6px 10px", color: "#111827" }}>
                          {o.label}
                          {o.pro && <span style={{ marginLeft: 5, background: "#fef3c7", color: "#92400e", fontSize: 10, fontWeight: 700,
                            padding: "1px 5px", borderRadius: 3 }}>PRO</span>}
                        </td>
                        <td style={{ padding: "6px 10px", color: "#6b7280", fontSize: 11 }}>{o.type}</td>
                        <td style={{ padding: "6px 10px" }}>
                          <span style={{ background: st.bg, color: st.color, borderRadius: 4, padding: "2px 7px", fontSize: 11, whiteSpace: "nowrap" }}>
                            {st.label}
                          </span>
                        </td>
                        <td style={{ padding: "6px 10px" }}>
                          {isNA ? (
                            <span style={{ color: "#9ca3af", fontSize: 11 }}>N/A</span>
                          ) : (
                            <select value={row.plugin}
                              onChange={(e) => updateRow(o.id, { plugin: e.target.value as Plugin })}
                              style={{ border: "1px solid #d1d5db", borderRadius: 4, padding: "3px 6px", fontSize: 11, width: "100%",
                                background: row.plugin && PLUGIN_COLORS[row.plugin] ? PLUGIN_COLORS[row.plugin] + "20" : "#fff" }}>
                              {PLUGINS.map((p) => (
                                <option key={p.value} value={p.value}>{p.label}</option>
                              ))}
                            </select>
                          )}
                        </td>
                        <td style={{ padding: "6px 10px" }}>
                          <input type="text" value={row.note}
                            onChange={(e) => updateRow(o.id, { note: e.target.value })}
                            placeholder="komentar..."
                            style={{ border: "1px solid #e5e7eb", borderRadius: 4, padding: "3px 6px", fontSize: 11, width: "100%", background: "transparent" }} />
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          );
        })}
      </div>
    </div>
  );
}
