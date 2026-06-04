# Free/Pro Options Architect Review

Purpose: provide a complete settings option inventory for an architecture/product review. The architect should decide which individual options, sections, or whole tabs belong in Free vs Pro, and whether uncertain areas need their own SKU.

Scope: current settings save surface from `SettingsSaveDefinition::fields()` plus `ProFeatureRegistry::lockedSettingsKeys()`.

Current counts:

| Source | Count | Meaning |
|---|---:|---|
| Manifest-backed settings | 176 | Already have tab/section/type/tier/SKU metadata. |
| Compatibility/review settings | 142 | Accepted by settings save for legacy, import, migration, or not-yet-owned areas. |
| Total accepted settings | 318 | Full current settings save surface. |

Legend:

| Column | Meaning |
|---|---|
| Current tier | Current code classification: `free`, `pro`, `pro-locked`, or `review`. |
| SKU | Current manifest SKU when known. Empty means not assigned to a static SKU. |
| Locked | Whether Free save payload stripping currently protects the key. |
| Architect recommendation | Fill with `Free`, `Pro`, `Legacy only`, `Remove later`, `Needs SKU`, or another decision. |

## Decision Areas

| Area | Current state | Architect question | Current recommendation placeholder |
|---|---|---|---|
| Sitemap advanced | Active UI/runtime and Pro locks exist, but no static `sitemap` SKU exists. | Should advanced sitemap be Core Pro, a new Sitemap SKU, or another product bundle? | Pending architect |
| Analytics/GSC/GTM/GA4 | Active save keys exist, many are Pro-locked, but no static `analytics` SKU exists. | Should Analytics be a SKU, Core tooling, or mixed Free/Pro utility area? | Pending architect |
| License/dev state | Accepted save keys exist but belong to license/dev boundary. | Should these remain outside feature manifests? | Likely outside feature manifests |
| Legacy/import aliases | Compatibility-only historical keys. | Keep writable forever, migrate, or remove after release? | Keep for release |
| AEO X-Robots header | Runtime reads the key, active Vue AEO tab does not expose it. | Restore UI as AEO Pro, keep legacy-only, or remove later? | Pending architect |

## Manifest-Backed Options

These already have explicit metadata in manifests. Architect can still override Free/Pro product positioning, but the table shows the current code state.

### Tab: General

| Section | Options | Current tier | SKU | Locked | Architect recommendation |
|---|---|---|---|---|---|
| conflicts | `conflict_mode` | free | core | no |  |
| domain | `auto_domain_detection`, `manual_domain` | free | core | no |  |
| multilingual | `translation_source_priority` | free | core | no |  |
| robots | `enable_robots`, `robots_auto_sync` | free | core | no |  |
| seo_templates | `title_template`, `title_separator`, `title_template_home`, `title_template_article`, `title_template_category`, `title_template_search`, `title_template_tag`, `title_template_default`, `title_template_maxlen`, `meta_desc_template`, `meta_desc_template_article`, `meta_desc_template_category`, `meta_desc_template_default`, `meta_desc_maxlen` | free | core | no |  |

### Tab: Debug

| Section | Options | Current tier | SKU | Locked | Architect recommendation |
|---|---|---|---|---|---|
| diagnostics | `debug_mode`, `hide_comments`, `staging_mode` | pro | core | yes |  |
| logging | `error_log_enabled`, `error_log_min_severity` | free | core | no |  |

### Tab: Sitemap

| Section | Options | Current tier | SKU | Locked | Architect recommendation |
|---|---|---|---|---|---|
| xml | `enable_sitemap`, `sitemap_limit`, `default_changefreq`, `default_priority` | free | core | no |  |
| xml_content | `include_articles`, `include_categories`, `include_menu_items` | free | core | no |  |
| xml_exclusions | `exclude_category_ids`, `exclude_menu_ids` | free | core | no |  |
| canonical | `enable_canonical`, `canonical_url_map` | free | core | no |  |
| redirects | `redirect_404_log_enabled` | free | core | no |  |
| ping_legacy | `ping_google`, `ping_bing` | free | core | no |  |
| hreflang | `hreflang_sitemap`, `enable_hreflang` | pro | hreflang | yes |  |

### Tab: Social

| Section | Options | Current tier | SKU | Locked | Architect recommendation |
|---|---|---|---|---|---|
| og | `site_name`, `default_og_image`, `og_description_override`, `enable_opengraph`, `enable_article_og_type`, `og_image_width`, `og_image_height`, `enable_per_article_fields` | free | og | no |  |
| facebook | `fb_app_id` | free | og | no |  |
| twitter | `twitter_site_handle`, `enable_twitter_cards` | free | og | no |  |
| og_locale | `enable_og_locale` | pro | og | yes |  |
| pixel | `enable_meta_pixel`, `meta_pixel_id`, `meta_pixel_ids`, `pixel_consent_mode` | pro | og | yes |  |
| pixel_events | `meta_pixel_standard_events` | pro | og | yes |  |
| pixel_custom | `meta_custom_events` | pro | og | yes |  |
| hreflang | `hreflang_enabled`, `hreflang_primary_language` | pro | hreflang | yes |  |

### Tab: Organization

| Section | Options | Current tier | SKU | Locked | Architect recommendation |
|---|---|---|---|---|---|
| identity | `org_name`, `org_description`, `org_logo` | free | schema | no |  |
| contact | `org_url`, `org_email`, `org_phone` | free | schema | no |  |
| address | `org_address_street`, `org_address_city`, `org_address_state`, `org_address_zip`, `org_address_country` | free | schema | no |  |
| geo | `org_latitude`, `org_longitude` | free | schema | no |  |
| rating | `rating_value`, `rating_count`, `rating_best`, `rating_worst`, `rating_source` | free | schema | no |  |
| social | `social_facebook`, `social_instagram`, `social_youtube`, `social_twitter`, `social_linkedin`, `social_tiktok` | free | schema | no |  |

### Tab: Schema

| Section | Options | Current tier | SKU | Locked | Architect recommendation |
|---|---|---|---|---|---|
| core | `enable_schema`, `page_type_auto_detect` | free | schema | no |  |
| business | `schema_type`, `specific_price_range`, `specific_serves_cuisine`, `specific_available_service` | free | schema | no |  |
| business_details | `specific_star_rating`, `specific_checkin_time`, `specific_checkout_time`, `specific_pets_allowed`, `specific_area_served` | pro | schema | yes |  |
| website | `website_schema_enabled`, `enable_search_action` | free | schema | no |  |
| article | `article_schema_enabled` | free | schema | no |  |
| hours | `schema_hours_mode`, `schema_opening_hours` | free | schema | no |  |
| hours_advanced | `schema_hours_temp_closed`, `schema_holiday_closed`, `hours_mon_opens`, `hours_mon_closes`, `hours_mon_closed`, `hours_tue_opens`, `hours_tue_closes`, `hours_tue_closed`, `hours_wed_opens`, `hours_wed_closes`, `hours_wed_closed`, `hours_thu_opens`, `hours_thu_closes`, `hours_thu_closed`, `hours_fri_opens`, `hours_fri_closes`, `hours_fri_closed`, `hours_sat_opens`, `hours_sat_closes`, `hours_sat_closed`, `hours_sun_opens`, `hours_sun_closes`, `hours_sun_closed` | pro | schema | yes |  |
| faq | `faq_auto_detect`, `enable_manual_faqs`, `faq_items`, `manual_faq_scope`, `schema_faq_output_type` | pro | schema | yes |  |
| author | `schema_author_entity_enabled` | pro | schema | yes |  |
| howto | `schema_howto`, `schema_howto_enabled` | pro | schema | yes |  |
| event | `events_enabled`, `events_category_id` | pro | schema | yes |  |
| breadcrumb | `schema_breadcrumb_pro` | pro | schema | yes |  |

### Tab: AEO

| Section | Options | Current tier | SKU | Locked | Architect recommendation |
|---|---|---|---|---|---|
| llmstxt | `llmstxt_enabled`, `llmstxt_description`, `llmstxt_recent_articles`, `llmstxt_custom_pages`, `llmstxt_faq_auto_detect`, `llmstxt_faq_items` | free | aeo | no |  |
| llms_full | `llms_full_txt_enabled`, `llms_full_max_articles` | pro | aeo | yes |  |
| crawlers | `ai_crawlers_enabled`, `aeo_crawler_default_policy`, `crawler_bot_rules`, `crawler_rules` | free | aeo | no |  |
| robots | `robots_custom_scrapers`, `robots_custom_rules` | free | aeo | no |  |
| robots_scrapers | `scraper_ahrefsbot`, `scraper_semrushbot`, `scraper_dotbot`, `scraper_mj12bot`, `scraper_blexbot`, `scraper_rogerbot`, `scraper_screamingfrog`, `scraper_sitebulb`, `scraper_siteauditor`, `scraper_serpstatbot`, `scraper_bytespider`, `scraper_petalbot` | free | aeo | no |  |
| indexnow | `indexnow_enabled`, `indexnow_api_key`, `indexnow_auto_submit` | pro | aeo | yes |  |
| markdown | `markdown_pages_enabled` | pro | aeo | yes |  |
| ai_signals | `aeo_ai_meta_enabled` | free | aeo | no |  |

### Tab: Code

| Section | Options | Current tier | SKU | Locked | Architect recommendation |
|---|---|---|---|---|---|
| general | `enable_custom_code` | pro | code | yes |  |
| head | `custom_code_head`, `custom_code_head_scope`, `custom_code_head_menu_ids` | pro | code | yes |  |
| body | `custom_code_body`, `custom_code_body_scope`, `custom_code_body_menu_ids` | pro | code | yes |  |
| footer | `custom_code_footer`, `custom_code_footer_scope`, `custom_code_footer_menu_ids` | pro | code | yes |  |

## Compatibility/Review Options

These are still accepted by settings save but do not have final manifest ownership. They should be reviewed by area. Some are intentionally legacy aliases and should not become product-facing options.

### License / Dev Boundary

| Options | Current state | Architect recommendation |
|---|---|---|
| `license_key`, `license_tier`, `dev_license_preview`, `dev_force_free_tier` | Accepted compatibility keys, not feature manifest fields. |  |

### Analytics / Verification / Tracking

| Options | Current state | Architect recommendation |
|---|---|---|
| `enable_ga4`, `ga4_measurement_id`, `ga4_consent_mode`, `enable_gtm`, `gtm_container_id`, `enable_google_verification`, `gsc_verification_code`, `gsc_codes`, `gsc_additional_html`, `fb_domain_verification` | No static analytics SKU yet. Several are already Pro-locked by section registry. |  |

### Sitemap Advanced And Legacy

| Options | Current state | Architect recommendation |
|---|---|---|
| `enable_sitemap_index`, `enable_image_sitemap`, `enable_news_sitemap`, `news_category_id`, `news_publication_name`, `priority_homepage`, `priority_articles`, `priority_categories`, `priority_tags`, `ping_on_publish`, `include_tags`, `sitemap_priority_articles`, `sitemap_priority_categories`, `sitemap_priority_menu` | Active or historically active sitemap advanced options. Several are Pro-locked. No static sitemap SKU yet. |  |
| `sitemap_include_articles`, `sitemap_include_categories`, `sitemap_include_menus`, `sitemap_max_articles`, `sitemap_exclude_ids`, `sitemap_exclude_urls`, `sitemap_hreflang`, `sitemap_changefreq_articles`, `sitemap_changefreq_categories`, `sitemap_changefreq_menu` | Legacy/import aliases or older sitemap settings. |  |
| `schema_news_topics`, `schema_news_principles` | News/media schema-adjacent settings currently grouped with sitemap/news review. |  |

### Schema Legacy / Import Aliases

| Options | Current state | Architect recommendation |
|---|---|---|
| `org_name_en`, `org_description_en`, `schema_logo_url`, `schema_url`, `schema_email`, `schema_phone`, `schema_social_facebook`, `schema_social_instagram`, `schema_social_youtube`, `schema_social_twitter`, `schema_social_linkedin`, `schema_social_tiktok`, `schema_social_pinterest`, `schema_address_street_en`, `schema_address_locality_en`, `schema_address_zip`, `schema_address_country`, `schema_latitude`, `schema_longitude` | Legacy/import aliases for organization identity/contact/address/geo fields. |  |
| `schema_rating_value`, `schema_rating_count`, `schema_rating_best`, `schema_rating_worst`, `schema_rating_source` | Legacy/import aliases for rating fields. |  |
| `schema_hotel_star_rating`, `schema_hotel_checkin_time`, `schema_hotel_checkout_time`, `schema_hotel_pets_allowed`, `schema_hotel_image`, `schema_org_image`, `schema_price_range`, `schema_medical_specialty`, `schema_legal_area`, `schema_edu_level`, `schema_job_title`, `schema_portfolio_url`, `schema_dental_specialty`, `schema_realestate_area_served`, `schema_realestate_property_types`, `schema_gym_sport`, `schema_gym_amenities` | Legacy/import aliases for business-specific Schema details. |  |
| `schema_business_hours`, `schema_hours_appointment_only`, `schema_season_from`, `schema_season_to` | Legacy/import aliases for business hours/seasonal details. |  |
| `schema_events_enabled`, `schema_events_en`, `manual_faqs_en`, `article_schema_type_auto`, `website_schema_search_enabled` | Legacy or inactive alternatives to current canonical keys. |  |
| `hours_monday_opens`, `hours_monday_closes`, `hours_monday_closed`, `hours_tuesday_opens`, `hours_tuesday_closes`, `hours_tuesday_closed`, `hours_wednesday_opens`, `hours_wednesday_closes`, `hours_wednesday_closed`, `hours_thursday_opens`, `hours_thursday_closes`, `hours_thursday_closed`, `hours_friday_opens`, `hours_friday_closes`, `hours_friday_closed`, `hours_saturday_opens`, `hours_saturday_closes`, `hours_saturday_closed`, `hours_sunday_opens`, `hours_sunday_closes`, `hours_sunday_closed` | Full weekday-name legacy aliases for opening hours. Pro-locked where mapped. |  |
| `hours_mo_opens`, `hours_mo_closes`, `hours_mo_closed`, `hours_tu_opens`, `hours_tu_closes`, `hours_tu_closed`, `hours_we_opens`, `hours_we_closes`, `hours_we_closed`, `hours_th_opens`, `hours_th_closes`, `hours_th_closed`, `hours_fr_opens`, `hours_fr_closes`, `hours_fr_closed`, `hours_sa_opens`, `hours_sa_closes`, `hours_sa_closed`, `hours_su_opens`, `hours_su_closes`, `hours_su_closed` | Two-letter weekday legacy aliases for opening hours. Pro-locked where mapped. |  |

### AEO Legacy / Inactive

| Options | Current state | Architect recommendation |
|---|---|---|
| `enable_x_robots_header` | AEO Pro runtime reads it, but active Vue AEO tab does not currently expose it. |  |
| `llmstxt_custom_pages_en` | Language-suffixed legacy/custom-pages alias. |  |
| `robots_block_scrapers` | Legacy robots/scraper setting. |  |

### Social Legacy

| Options | Current state | Architect recommendation |
|---|---|---|
| `og_site_name`, `og_default_image`, `social_pinterest` | Legacy/import aliases. Current active OpenGraph keys are `site_name` and `default_og_image`. |  |

### Code Legacy

| Options | Current state | Architect recommendation |
|---|---|---|
| `custom_code_scope`, `custom_code_menu_ids` | Legacy custom-code aliases. Currently Pro-locked. |  |

### Other Compatibility Keys

| Options | Current state | Architect recommendation |
|---|---|---|
| `show_advanced_options`, `redirect_enabled` | Compatibility-only settings. Need review before manifest ownership. |  |

## Suggested Architect Output Format

Ask the architect to return decisions in this shape:

| Area/tab/section/key | Decision | SKU/product | Notes |
|---|---|---|---|
| `sitemap.advanced` | Free/Pro/Legacy/Remove | core/sitemap/other |  |
| `analytics.gtm` | Free/Pro/Legacy/Remove | analytics/core/other |  |
| `enable_x_robots_header` | Free/Pro/Legacy/Remove | aeo/other |  |

Minimum decisions needed before more manifest expansion:

1. Sitemap ownership: Core vs new Sitemap SKU vs another product bundle.
2. Analytics ownership: Core/tooling vs new Analytics SKU vs mixed Free/Pro.
3. AEO X-Robots header: restore UI as AEO option, keep legacy-only, or remove later.
4. Legacy aliases: keep compatibility-only for this release, migrate, or schedule removal.
