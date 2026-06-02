<?php
/**
 * AI Boost — Schema manifest.
 * Lists every Schema.org-related field with free/pro tier metadata.
 */

defined('_JEXEC') or die;

return [
    // ── Core (free) ────────────────────────────────────────────────
    [
        'key'         => 'enable_schema',
        'tab'         => 'schema',
        'section'     => 'core',
        'label'       => 'Enable Schema.org structured data',
        'type'        => 'toggle',
        'default'     => '1',
        'tier'        => 'free',
        'sku'         => 'schema',
    ],
    [
        'key'         => 'page_type_auto_detect',
        'tab'         => 'schema',
        'section'     => 'core',
        'label'       => 'Auto-detect page type',
        'type'        => 'toggle',
        'default'     => '1',
        'tier'        => 'free',
        'sku'         => 'schema',
    ],
    [
        'key'         => 'schema_type',
        'tab'         => 'schema',
        'section'     => 'business',
        'label'       => 'Schema Type',
        'type'        => 'select',
        'default'     => 'Organization',
        'tier'        => 'free',
        'sku'         => 'schema',
        // Free/Pro partitioning of this dropdown is enforced by
        // ProFeatureRegistry::proOptions()['schema_type'] +
        // stripProOptions() (Task #459). Keep the [Free]/[Pro] suffixes
        // here in lockstep with that map; the SPA dropdown rebuilds
        // these labels from SCHEMA_TYPE_OPTIONS in SchemaTab.vue.
        'options'     => [
            'Organization'              => 'Organization (generic) [Free]',
            'LocalBusiness'             => 'LocalBusiness [Free]',
            'FoodEstablishment'         => 'Restaurant / Cafe [Free]',
            'EducationalOrganization'   => 'School / University [Free]',
            'LodgingBusiness'           => 'Hotel / Accommodation [Pro]',
            'MedicalClinic'             => 'Medical Clinic [Pro]',
            'LegalService'              => 'Lawyer / Law Firm [Pro]',
            'SportsActivityLocation'    => 'Gym / Sports Club [Pro]',
            'Dentist'                   => 'Dentist [Pro]',
            'RealEstateAgent'           => 'Real Estate Agency [Pro]',
            'Person'                    => 'Person / Portfolio [Pro]',
            'NewsMediaOrganization'     => 'News / Media [Pro]',
        ],
    ],
    // Note: the legacy `schema_type_pro_medical` toggle was removed in
    // Task #460 — it was a stale stand-in from before per-value enum
    // gating existed (#459). Medical/Legal/etc. are now part of the
    // `schema_type` dropdown's Pro subset and need no separate flag.

    // ── Author Entity (Pro) ────────────────────────────────────────
    [
        'key'         => 'schema_author_entity_enabled',
        'tab'         => 'schema',
        'section'     => 'author',
        'label'       => 'Emit Person entity for each article author',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'pro',
        'sku'         => 'schema',
        'description' => 'Reads Joomla user custom fields (aiboost_job_title, _bio, _website, _linkedin, _wikipedia) to build E-E-A-T-grade Person schema.',
    ],

    // ── Opening hours advanced (Pro) ───────────────────────────────
    [
        'key'         => 'schema_hours_mode',
        'tab'         => 'schema',
        'section'     => 'hours',
        'label'       => 'Opening Hours Mode',
        'type'        => 'select',
        'default'     => 'simple',
        'tier'        => 'free',
        'sku'         => 'schema',
        'options'     => [
            'simple'   => 'Simple (one text line)',
            'advanced' => 'Advanced — day-by-day schedule [Pro]',
            'none'     => 'Not applicable / Hide',
        ],
    ],

    // ── FAQ (Task #473: whole section is Pro) ──────────────────────
    [
        'key'         => 'faq_auto_detect',
        'tab'         => 'schema',
        'section'     => 'faq',
        'label'       => 'Auto-Detect FAQ from Content',
        'type'        => 'toggle',
        'default'     => '1',
        'tier'        => 'pro',
        'sku'         => 'schema',
    ],
    [
        'key'         => 'enable_manual_faqs',
        'tab'         => 'schema',
        'section'     => 'faq',
        'label'       => 'Enable Manual FAQs',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'pro',
        'sku'         => 'schema',
    ],

    // ── HowTo (Pro) ────────────────────────────────────────────────
    [
        'key'         => 'schema_howto_enabled',
        'tab'         => 'schema',
        'section'     => 'howto',
        'label'       => 'Enable HowTo Schema',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'pro',
        'sku'         => 'schema',
    ],

    // ── Event (Pro) ────────────────────────────────────────────────
    [
        'key'         => 'events_enabled',
        'tab'         => 'schema',
        'section'     => 'event',
        'label'       => 'Enable Event Schema',
        'type'        => 'toggle',
        'default'     => '0',
        'tier'        => 'pro',
        'sku'         => 'schema',
    ],

    // ── WebSite + Article (free) ───────────────────────────────────
    [
        'key'         => 'website_schema_enabled',
        'tab'         => 'schema',
        'section'     => 'website',
        'label'       => 'Enable WebSite Schema (homepage only)',
        'type'        => 'toggle',
        'default'     => '1',
        'tier'        => 'free',
        'sku'         => 'schema',
    ],
    [
        'key'         => 'article_schema_enabled',
        'tab'         => 'schema',
        'section'     => 'article',
        'label'       => 'Enable Article Schema on article pages',
        'type'        => 'toggle',
        'default'     => '1',
        'tier'        => 'free',
        'sku'         => 'schema',
    ],

    // ── Breadcrumb Pro (Task #462 codegen smoke test) ──────────────
    // First field that exercises the new manifest-first codegen path:
    // - PHP handler stub auto-generated at aiboost_schema_pro/src/Features/BreadcrumbPro.php
    // - Health check auto-registered via HealthCheckService::registerFromManifest()
    // - en-GB .ini placeholder keys auto-added
    // The opt-in opening/closing markers below cause build-package-zip.py
    // to strip this whole entry out of the FREE package ZIP (Task #462). The
    // Pro plugin re-contributes the same field via onAiBoostRegisterFields at
    // runtime, so the SPA still shows the toggle when Pro is installed.
    // @pro:start
    [
        'key'           => 'schema_breadcrumb_pro',
        'tab'           => 'schema',
        'section'       => 'breadcrumb',
        'label'         => 'Enhanced BreadcrumbList (Pro)',
        'type'          => 'toggle',
        'default'       => '0',
        'tier'          => 'pro',
        'sku'           => 'schema',
        'description'   => 'Emit a richer BreadcrumbList with per-item images and structured position metadata. Free tier emits the basic BreadcrumbList.',
        'feature_class' => 'BreadcrumbPro',
        'health'        => [
            'id'                => 'info_schema_breadcrumb_pro_active',
            'category'          => 'Schema',
            'message'           => 'Enhanced BreadcrumbList is active. Pages should emit a JSON-LD BreadcrumbList with image and position attributes on every item.',
            'expected_artifact' => 'application/ld+json with @type=BreadcrumbList including itemListElement[].image',
            'fix_actions'       => [
                ['label' => 'Open Schema tab → Breadcrumb', 'target_tab' => 'schema', 'target_field' => 'schema_breadcrumb_pro'],
            ],
        ],
        'i18n'          => [
            'label_key'       => 'PLG_SYSTEM_AIBOOST_SCHEMA_BREADCRUMB_PRO_LABEL',
            'description_key' => 'PLG_SYSTEM_AIBOOST_SCHEMA_BREADCRUMB_PRO_DESC',
        ],
    ],
    // @pro:end
];
