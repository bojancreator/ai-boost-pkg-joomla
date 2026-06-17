<?php
/**
 * AI Boost — Health Check Service
 * Executes all site health checks and returns a structured result with score.
 *
 * DatabaseInterface and AppContextInterface are injected so this service
 * makes no Factory:: / Uri:: calls.
 *
 * Each check result contains:
 *   id, status (critical|warning|info), category (General|Schema|Sitemap|Social|Analytics|AEO|License),
 *   label, pass, show_pass, message, fix_url, dismissed
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\AdapterRegistry;
use Joomla\Database\DatabaseInterface;

class HealthCheckService
{
    private array $settings;
    private array $dismissed;
    private DatabaseInterface $db;
    private AppContextInterface $ctx;
    private bool $skipHttpScan;

    /** Points deducted per failing critical check */
    private const CRITICAL_PENALTY = 15;

    /** Points deducted per failing warning check */
    private const WARNING_PENALTY = 5;

    /** Score below this threshold surfaces the meta low-score warning */
    private const LOW_SCORE_THRESHOLD = 80;

    /**
     * Functional category for each check ID.
     * Template groups checks by these categories.
     */
    private const CATEGORIES = [
        // ── Pro / Integration architecture (Task #428) ──────────────
        'info_pro_features_locked'             => 'License',
        'info_pro_gating_active'               => 'License',
        'info_schema_type_gating_active'       => 'License',
        'info_article_custom_fields_pro'       => 'License',
        'info_integration_detected_no_bridge'  => 'Conflicts',
        'warning_third_party_og_conflict'      => 'Conflicts',
        'warning_third_party_schema_conflict'  => 'Conflicts',
        'critical_pro_plugin_disabled'         => 'License',
        'info_license_simulation_active'       => 'License',
        'warning_license_domain_mismatch'      => 'License',
        'warning_pro_install_no_license'       => 'License',

        // ── Task #486 — Integration SDK (Open Integration Architecture) ──
        'info_integration_bridges_installed'   => 'Integrations',
        'info_integration_master_toggle'       => 'Integrations',
        'warning_bridge_sdk_mismatch'          => 'Integrations',
        'warning_bridge_slot_collision'        => 'Integrations',

        'critical_no_org_name'           => 'General',
        'critical_schema_disabled'       => 'Schema',
        'critical_sitemap_disabled'      => 'Sitemap',
        'critical_robots_blocks_all'     => 'Crawlers & Robots',
        'critical_no_og_image'           => 'Social',
        'critical_canonical_disabled'    => 'Sitemap',
        'warning_no_org_logo'            => 'Schema',
        'warning_no_ga4_id'              => 'Analytics',
        'warning_no_gsc_code'            => 'Analytics',
        'warning_hreflang_no_urls'       => 'Sitemap',
        'warning_sitemap_few_urls'       => 'Sitemap',
        'warning_indexnow_inactive'      => 'AEO',
        'warning_llmstxt_inactive'       => 'AEO',
        'warning_install_integrity'      => 'General',
        'warning_staging_mode_on'        => 'General',
        'warning_debug_on_prod'          => 'General',
        'warning_backup_stale'           => 'General',
        'warning_license_unverified'     => 'License',
        'critical_license_invalid'       => 'License',
        'info_auto_update_disabled'      => 'License',
        'info_license_last_heartbeat'    => 'License',
        'info_license_renewal'           => 'License',
        'critical_license_domain_collision' => 'License',
        'warning_article_schema_author'  => 'Schema',
        'warning_article_schema_image'   => 'Schema',
        'info_og_description_override_active' => 'Social',
        'info_social_og_pro_active'           => 'Social',
        'info_fb_domain_verification_active'  => 'Analytics',
        'info_aeo_ai_meta_tags_active'        => 'AEO',
        'info_aeo_markdown_discovery_active'  => 'AEO',
        'info_aeo_faq_auto_detect_active'     => 'AEO',
        'info_position_gtm_noscript_body'         => 'Analytics',
        'info_position_meta_pixel_noscript_body'  => 'Analytics',
        'info_position_custom_code_body_slot'     => 'General',
        'info_position_custom_code_footer_slot'   => 'General',
        'duplicate_fb_domain_verification'    => 'Conflicts',
        'info_robots_block_ahrefsbot'        => 'Crawlers & Robots',
        'info_robots_block_semrushbot'       => 'Crawlers & Robots',
        'info_robots_block_dotbot'           => 'Crawlers & Robots',
        'info_robots_block_mj12bot'          => 'Crawlers & Robots',
        'info_robots_block_blexbot'          => 'Crawlers & Robots',
        'info_robots_block_rogerbot'         => 'Crawlers & Robots',
        'info_robots_block_screamingfrog'    => 'Crawlers & Robots',
        'info_robots_block_sitebulb'         => 'Crawlers & Robots',
        'info_robots_block_siteauditor'      => 'Crawlers & Robots',
        'info_robots_block_serpstatbot'      => 'Crawlers & Robots',
        'info_robots_block_bytespider'       => 'Crawlers & Robots',
        'info_robots_block_petalbot'         => 'Crawlers & Robots',
        'info_health_score_low'          => 'General',
        'info_php_version'               => 'General',
        'info_joomla_version'            => 'General',
        'info_last_saved'                => 'General',
        'info_active_plugins'            => 'General',
        'info_license_tier'              => 'License',
        'info_sitemap_url_count'         => 'Sitemap',
        'info_ai_visibility_score'       => 'AEO',
        'info_schema_author_fields_coverage' => 'Schema',
        'info_error_logging'             => 'General',

        // ── Conflict & Compatibility ──────────────────────────────
        'conflict_4seo'              => 'Conflicts',
        'conflict_sh404sef'          => 'Conflicts',
        'conflict_joomsef'           => 'Conflicts',
        'conflict_admintools'        => 'Conflicts',
        'conflict_osmap'             => 'Conflicts',
        'conflict_joomla_og'         => 'Conflicts',
        'duplicate_title'            => 'Conflicts',
        'duplicate_meta_description' => 'Conflicts',
        'duplicate_canonical'        => 'Conflicts',
        'duplicate_og_title'         => 'Conflicts',
        'duplicate_og_description'   => 'Conflicts',
        'duplicate_og_image'         => 'Conflicts',
        'duplicate_schema_organization' => 'Conflicts',
        'duplicate_schema_website'   => 'Conflicts',
        'duplicate_jsonld'           => 'Conflicts',
        'duplicate_ga4'              => 'Conflicts',
        'duplicate_gtm'              => 'Conflicts',
        'duplicate_meta_pixel'       => 'Conflicts',
    ];

    public function __construct(
        array $settings,
        DatabaseInterface $db,
        AppContextInterface $ctx,
        bool $skipHttpScan = false
    ) {
        $this->settings     = $settings;
        $this->db           = $db;
        $this->ctx          = $ctx;
        $this->skipHttpScan = $skipHttpScan;
        $dismissed          = json_decode((string) ($settings['dismissed_checks'] ?? '[]'), true);
        $this->dismissed    = is_array($dismissed) ? $dismissed : [];
    }

    /**
     * Run all health checks.
     *
     * @return array{score: int, checks: list<array>, dismissed: list<string>}
     */
    public function run(): array
    {
        $checks = [
            // ── Critical ──────────────────────────────────────────────────
            $this->checkOrgName(),
            $this->checkSchemaPlugin(),
            $this->checkSitemapPlugin(),
            $this->checkRobotsBlocksAll(),
            $this->checkOgImage(),
            $this->checkCanonicalPlugin(),

            // ── Warnings ──────────────────────────────────────────────────
            $this->checkOrgLogo(),
            $this->checkGa4Id(),
            $this->checkGscCode(),
            $this->checkHreflangUrls(),
            $this->checkSitemapUrlCount(),
            $this->checkIndexNow(),
            $this->checkLlmsTxt(),
            $this->checkInstallationIntegrity(),
            $this->checkStagingMode(),
            $this->checkDebugMode(),
            $this->checkBackupStale(),
            $this->checkLicenseVerified(),
            $this->criticalLicenseInvalid(),
            $this->infoAutoUpdateDisabled(),
            $this->infoLicenseLastHeartbeat(),
            $this->infoLicenseRenewal(),
            $this->criticalLicenseDomainCollision(),
            $this->checkArticleSchemaAuthor(),
            $this->infoSchemaAuthorFieldsCoverage(),
            $this->checkArticleSchemaImage(),
            $this->infoOgDescriptionOverride(),
            $this->infoSocialOgPro(),
            $this->infoFbDomainVerification(),
            $this->infoAeoAiMetaTags(),
            $this->infoAeoMarkdownDiscovery(),
            $this->infoAeoFaqAutoDetect(),
            $this->infoPositionGtmNoscriptBody(),
            $this->infoPositionMetaPixelNoscriptBody(),
            $this->infoPositionCustomCodeBodySlot(),
            $this->infoPositionCustomCodeFooterSlot(),
            ...$this->infoRobotsBlockedScrapers(),

            // ── Info ──────────────────────────────────────────────────────
            $this->infoPhpVersion(),
            $this->infoJoomlaVersion(),
            $this->infoLastSaved(),
            $this->infoActivePlugins(),
            $this->infoLicenseTier(),
            $this->infoSitemapUrlCount(),
            $this->infoAiVisibilityScore(),

            // ── Pro / Integration architecture (Task #428) ──────────
            $this->infoProFeaturesLocked(),
            $this->infoProGatingActive(),
            $this->infoSchemaTypeGatingActive(),
            $this->infoArticleCustomFieldsPro(),
            $this->criticalProPluginDisabled(),
            $this->infoIntegrationDetectedNoBridge('falang'),
            $this->infoIntegrationDetectedNoBridge('yootheme'),
            $this->infoIntegrationBridgesInstalled(),
            $this->infoIntegrationMasterToggle(),
            $this->warningBridgeSdkMismatch(),
            $this->warningBridgeSlotCollision(),
            $this->warningThirdPartyOgConflict(),
            $this->warningThirdPartySchemaConflict(),
            $this->infoLicenseSimulationActive(),
            $this->warningLicenseDomainMismatch(),
            $this->warningProInstallNoLicense(),
            $this->infoErrorLogging(),
        ];

        // Task #462 — auto-register Health entries declared in manifest `health` blocks.
        foreach ($this->registerFromManifest() as $c) {
            $checks[] = $c;
        }

        // ── Conflict & Compatibility checks ────────────────────────────────
        // conflict_mode='off' means "I know about my conflicts, stop telling me"
        // — suppress the conflict warnings (cooperative + aggressive keep them).
        if (strtolower((string) ($this->settings['conflict_mode'] ?? 'cooperative')) !== 'off') {
            $conflictDetector = new ConflictDetector($this->db, $this->settings, $this->dismissed);
            foreach ($conflictDetector->scan() as $c) {
                $checks[] = $c;
            }
        }

        // conflict_mode='off' silences ALL conflict/duplicate warnings (same as
        // the ConflictDetector gate above).
        if (!$this->skipHttpScan
            && strtolower((string) ($this->settings['conflict_mode'] ?? 'cooperative')) !== 'off') {
            $duplicateScanner = new DuplicateTagScanner($this->ctx, $this->dismissed);
            foreach ($duplicateScanner->scan() as $c) {
                $checks[] = $c;
            }
        }

        // Task #485 — compute score first, then surface a meta low-score
        // info entry so the admin understands what dragged the number down.
        $score    = $this->calculateScore($checks);
        $checks[] = $this->infoHealthScoreLow($score);

        return [
            'score'     => $score,
            'checks'    => $checks,
            'dismissed' => $this->dismissed,
        ];
    }

    /**
     * Task #485 — meta info entry. Surfaces a friendly explanation when the
     * overall score is below LOW_SCORE_THRESHOLD; always-pass when score is
     * fine. Never affects the score itself (status=info is skipped by
     * calculateScore()).
     */
    private function infoHealthScoreLow(int $score): array
    {
        $pass = $score >= self::LOW_SCORE_THRESHOLD;
        $msg  = $pass
            ? sprintf('Site Health score is %d/100 — good.', $score)
            : sprintf(
                'Site Health score is %d/100 — the share of weighted checks that pass (each critical counts %dx a warning). '
                . 'Resolve the items flagged above (or dismiss the ones that do not apply to this site) to raise it. '
                . 'Conflicts and informational checks do not affect the score.',
                $score,
                (int) round(self::CRITICAL_PENALTY / self::WARNING_PENALTY)
            );

        return $this->make(
            'info_health_score_low', 'info', 'Site Health score',
            $pass, true, $msg,
            ''
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRITICAL CHECKS
    // ─────────────────────────────────────────────────────────────────────────

    private function checkOrgName(): array
    {
        $pass = trim((string) ($this->settings['org_name'] ?? $this->settings['org_name_en'] ?? '')) !== '';
        $msg  = $pass
            ? 'Organization name is set.'
            : 'Organization name is missing — required for Schema.org markup.';

        return $this->make(
            'critical_no_org_name', 'critical', 'Organization Name',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('tab-org-btn', 'org_name')
        );
    }

    private function checkSchemaPlugin(): array
    {
        $enabled   = $this->isPluginEnabled('aiboost_schema');
        $settingOn = (string) ($this->settings['enable_schema'] ?? '1') !== '0';
        $pass      = $enabled && $settingOn;

        if (!$enabled) {
            $msg    = 'Schema.org plugin is disabled — structured data is not being output.';
            $fixUrl = $this->pluginManagerUrl('aiboost_schema');
        } elseif (!$settingOn) {
            $msg    = 'Schema.org output is disabled in settings.';
            $fixUrl = $this->settingsUrl('tab-schema-btn', 'enable_schema');
        } else {
            $msg    = 'Schema.org plugin is active and enabled.';
            $fixUrl = '';
        }

        return $this->make('critical_schema_disabled', 'critical', 'Schema.org Plugin', $pass, $pass, $msg, $fixUrl);
    }

    /**
     * Critical only when sitemap plugin is disabled AND sitemap.xml was never generated.
     * If a sitemap.xml already exists on disk, the old one is still served — not critical.
     */
    private function checkSitemapPlugin(): array
    {
        $enabled      = $this->isPluginEnabled('aiboost_sitemap');
        $sitemapExists = AdapterRegistry::filesystem()->siteFileExists('sitemap.xml');

        if ($enabled) {
            return $this->make(
                'critical_sitemap_disabled', 'critical', 'XML Sitemap Plugin',
                true, true, 'XML Sitemap plugin is active.',
                ''
            );
        }

        // Plugin disabled
        if ($sitemapExists) {
            // Sitemap was generated previously — not critical, but warn user
            return $this->make(
                'critical_sitemap_disabled', 'critical', 'XML Sitemap Plugin',
                true, true,
                'XML Sitemap plugin is disabled, but a sitemap.xml from a previous run still exists on disk.',
                $this->pluginManagerUrl('aiboost_sitemap')
            );
        }

        // Plugin disabled AND no sitemap ever generated — critical
        return $this->make(
            'critical_sitemap_disabled', 'critical', 'XML Sitemap Plugin',
            false, false,
            'XML Sitemap plugin is disabled and no sitemap.xml has been generated — search engines cannot discover your URLs.',
            $this->pluginManagerUrl('aiboost_sitemap')
        );
    }

    private function checkRobotsBlocksAll(): array
    {
        $pass = true;
        $msg  = 'robots.txt does not block all crawlers.';

        try {
            $robotsFile = AdapterRegistry::filesystem()->sitePath('robots.txt');
            if (file_exists($robotsFile)) {
                $content = (string) @file_get_contents($robotsFile);
                if ($this->robotsBlocksAll($content)) {
                    $pass = false;
                    $msg  = 'robots.txt contains "Disallow: /" for all bots — search engines are blocked from crawling your site.';
                }
            }
        } catch (\Throwable $e) {
        }

        return $this->make(
            'critical_robots_blocks_all', 'critical', 'robots.txt Blocking All',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('crawlers', 'enable_robots')
        );
    }

    private function checkOgImage(): array
    {
        $raw  = self::normaliseImagePath((string) ($this->settings['default_og_image'] ?? $this->settings['og_default_image'] ?? ''));
        $pass = $raw !== '';
        $msg  = $pass
            ? 'Default OpenGraph image is set.'
            : 'No default OG image — social media shares will show no image preview.';

        return $this->make(
            'critical_no_og_image', 'critical', 'Default OG Image',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('tab-social-btn', 'default_og_image')
        );
    }

    /**
     * Canonical URL management is critical only on production.
     * If staging_mode is enabled in settings, this check is skipped (passes).
     */
    private function checkCanonicalPlugin(): array
    {
        if ($this->isStagingMode()) {
            return $this->make(
                'critical_canonical_disabled', 'critical', 'Canonical URLs',
                true, true,
                'Canonical URL check skipped — staging mode is active.',
                ''
            );
        }

        $enabled   = $this->isPluginEnabled('aiboost_core');
        $settingOn = (string) ($this->settings['enable_canonical'] ?? '1') !== '0';
        $pass      = $enabled && $settingOn;

        if (!$enabled) {
            $msg    = 'Canonical URL plugin (Core) is disabled — duplicate content issues may occur on production.';
            $fixUrl = $this->pluginManagerUrl('aiboost_core');
        } elseif (!$settingOn) {
            $msg    = 'Canonical URL management is disabled in settings — duplicate content issues may occur.';
            $fixUrl = $this->settingsUrl('technical', 'enable_canonical');
        } else {
            $msg    = 'Canonical URL management is active.';
            $fixUrl = '';
        }

        return $this->make('critical_canonical_disabled', 'critical', 'Canonical URLs', $pass, $pass, $msg, $fixUrl);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WARNING CHECKS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Organization logo URL — warns if missing or if the URL returns a non-200 HTTP status.
     * HTTP HEAD check uses a 3-second timeout; network failures are treated as "skip" (pass).
     * When skipHttpScan=true (module / lightweight mode) the HTTP check is skipped entirely.
     */
    private function checkOrgLogo(): array
    {
        $logo = trim((string) ($this->settings['schema_logo_url'] ?? $this->settings['org_logo'] ?? ''));

        if ($logo === '') {
            return $this->make(
                'warning_no_org_logo', 'warning', 'Organization Logo URL',
                false, false,
                'Organization logo URL is not set — Schema.org Organization markup will be incomplete.',
                $this->settingsUrl('tab-org-btn', 'org_logo')
            );
        }

        // Skip HTTP reachability check in lightweight/module mode
        if ($this->skipHttpScan) {
            return $this->make(
                'warning_no_org_logo', 'warning', 'Organization Logo URL',
                true, true,
                'Organization logo URL is configured (HTTP accessibility check skipped in lightweight mode).',
                ''
            );
        }

        // The media picker stores logos root-relative (/images/...) — absolutize
        // before the HEAD check, mirroring the builders' absoluteUrl(), so the
        // reachability check actually runs (FILTER_VALIDATE_URL rejects bare paths).
        if (!str_starts_with($logo, 'http://') && !str_starts_with($logo, 'https://')) {
            $logo = rtrim($this->ctx->getBaseUrl(), '/') . '/' . ltrim($logo, '/');
        }

        $httpStatus = $this->fetchHeadStatus($logo);

        if ($httpStatus === null) {
            // Network or config issue — treat as pass to avoid false alarm
            return $this->make(
                'warning_no_org_logo', 'warning', 'Organization Logo URL',
                true, true,
                'Organization logo URL is configured (HTTP check could not be completed).',
                $this->settingsUrl('tab-org-btn', 'org_logo')
            );
        }

        if ($httpStatus >= 200 && $httpStatus < 300) {
            return $this->make(
                'warning_no_org_logo', 'warning', 'Organization Logo URL',
                true, true,
                'Organization logo URL is accessible (HTTP ' . $httpStatus . ').',
                ''
            );
        }

        return $this->make(
            'warning_no_org_logo', 'warning', 'Organization Logo URL',
            false, false,
            'Organization logo URL returns HTTP ' . $httpStatus . ' — check the URL is accessible.',
            $this->settingsUrl('tab-org-btn', 'org_logo')
        );
    }

    private function checkGa4Id(): array
    {
        $id   = trim((string) ($this->settings['ga4_measurement_id'] ?? ''));
        $pass = $id !== '';
        $msg  = $pass
            ? 'GA4 Measurement ID is configured (' . htmlspecialchars(substr($id, 0, 12), ENT_QUOTES) . '…).'
            : 'Google Analytics Measurement ID is not set — no traffic data will be tracked.';

        return $this->make(
            'warning_no_ga4_id', 'warning', 'Google Analytics (GA4)',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('tab-analytics-btn', 'ga4_measurement_id')
        );
    }

    private function checkGscCode(): array
    {
        $code  = trim((string) ($this->settings['gsc_verification_code'] ?? ''));
        $codes = json_decode((string) ($this->settings['gsc_codes'] ?? '[]'), true);
        $pass  = $code !== '' || (is_array($codes) && !empty($codes));
        $msg   = $pass
            ? 'Google Search Console verification code is configured.'
            : 'No Google Search Console verification code — you cannot verify site ownership.';

        return $this->make(
            'warning_no_gsc_code', 'warning', 'Google Search Console',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('tab-analytics-btn', 'gsc_verification_code')
        );
    }

    private function checkHreflangUrls(): array
    {
        $hreflangOn = (string) ($this->settings['enable_hreflang'] ?? '0') === '1';

        if (!$hreflangOn) {
            return $this->make(
                'warning_hreflang_no_urls', 'warning', 'Hreflang URLs',
                true, true,
                'Hreflang is not enabled — no action needed.',
                ''
            );
        }

        $urls = trim((string) ($this->settings['sitemap_hreflang'] ?? ''));
        $pass = $urls !== '';
        $msg  = $pass
            ? 'Hreflang is enabled and URLs are configured.'
            : 'Hreflang is enabled but no alternate URLs are defined — hreflang tags will be empty.';

        return $this->make(
            'warning_hreflang_no_urls', 'warning', 'Hreflang URLs',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('tab-sitemap-btn', 'enable_hreflang')
        );
    }

    private function checkSitemapUrlCount(): array
    {
        $count = $this->getSitemapUrlCount();

        if ($count === null) {
            return $this->make(
                'warning_sitemap_few_urls', 'warning', 'Sitemap URL Count',
                true, true,
                'Sitemap not yet generated or not accessible.',
                $this->settingsUrl('tab-sitemap-btn', 'enable_sitemap')
            );
        }

        $pass = $count >= 5;
        $msg  = $pass
            ? "Sitemap contains {$count} URL(s) — looks good."
            : "Sitemap contains only {$count} URL(s) — check sitemap settings.";

        return $this->make(
            'warning_sitemap_few_urls', 'warning', 'Sitemap URL Count',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('tab-sitemap-btn', 'enable_sitemap')
        );
    }

    /**
     * IndexNow warning when the API key has not been generated or the key file is missing.
     * The AEO plugin generates a key file at /{key}.txt when IndexNow is enabled.
     */
    private function checkIndexNow(): array
    {
        $key          = trim((string) ($this->settings['indexnow_api_key'] ?? ''));
        $keyGenerated = $key !== '';
        $keyFile      = $keyGenerated ? AdapterRegistry::filesystem()->sitePath(preg_replace('/[^a-zA-Z0-9\-_]/', '', $key) . '.txt') : '';
        $fileExists   = $keyGenerated && $keyFile && file_exists($keyFile);

        if (!$keyGenerated) {
            return $this->make(
                'warning_indexnow_inactive', 'warning', 'IndexNow (AEO)',
                false, false,
                'IndexNow API key has not been generated — instant indexing signals for AI search engines are missing.',
                $this->settingsUrl('tab-aeo-btn', 'indexnow_api_key')
            );
        }

        if (!$fileExists) {
            return $this->make(
                'warning_indexnow_inactive', 'warning', 'IndexNow (AEO)',
                false, false,
                'IndexNow key is set but the key file (' . htmlspecialchars($key, ENT_QUOTES) . '.txt) is missing from the site root.',
                $this->settingsUrl('tab-aeo-btn', 'indexnow_api_key')
            );
        }

        return $this->make(
            'warning_indexnow_inactive', 'warning', 'IndexNow (AEO)',
            true, true,
            'IndexNow is active and key file exists — new content is submitted to Bing and Yandex automatically.',
            ''
        );
    }

    private function checkLlmsTxt(): array
    {
        $enabled = (string) ($this->settings['llmstxt_enabled'] ?? '0') === '1';
        $msg     = $enabled
            ? 'llms.txt is active — AI crawlers can discover your content structure.'
            : 'llms.txt is not enabled — AI engines cannot easily discover your site content.';

        return $this->make(
            'warning_llmstxt_inactive', 'warning', 'llms.txt (AEO)',
            $enabled, $enabled, $msg,
            $enabled ? '' : $this->settingsUrl('tab-aeo-btn', 'llmstxt_enabled')
        );
    }

    /**
     * Warn loudly when staging_mode is ON.
     *
     * staging_mode=1 silently suppresses all output from aiboost_analytics
     * (GA4, GTM, Meta Pixel, Google/Facebook verification), aiboost_core
     * (canonical, title/desc templates, redirects), and aiboost_code
     * (custom head/body/footer injection). Nothing about these plugins
     * appears in the rendered HTML while this flag is active.
     */
    private function checkStagingMode(): array
    {
        $staging = !empty($this->settings['staging_mode']);
        $msg     = $staging
            ? 'Staging Mode is ON — the following plugins are completely suppressed and produce no HTML output: Analytics (GA4, GTM, Meta Pixel, GSC/Facebook verification), Core (canonical, title/description templates, redirects), Custom Code. Disable Staging Mode before testing or going live.'
            : 'Staging Mode is off — all plugins inject their output normally.';

        return $this->make(
            'warning_staging_mode_on', 'warning', 'Staging Mode Active',
            !$staging, !$staging, $msg,
            $staging ? $this->settingsUrl('tab-debug-btn', 'staging_mode') : ''
        );
    }

    /**
     * If staging_mode is enabled, debug being on is expected and not warned.
     */
    private function checkDebugMode(): array
    {
        if ($this->isStagingMode()) {
            return $this->make(
                'warning_debug_on_prod', 'warning', 'Debug Mode',
                true, true,
                'Debug mode check skipped — staging mode is active.',
                ''
            );
        }

        $debug = (string) ($this->settings['debug_mode'] ?? '0') === '1';
        $msg   = !$debug
            ? 'Debug mode is off — good for production.'
            : 'Debug mode is ON — this exposes diagnostic data and should be disabled on production.';

        return $this->make(
            'warning_debug_on_prod', 'warning', 'Debug Mode',
            !$debug, !$debug, $msg,
            $debug ? $this->settingsUrl('tab-debug-btn', 'debug_mode') : ''
        );
    }

    /**
     * Task #500 — warn when no settings backup has been taken in the last 30
     * days (or never). Persisted server-side as `last_backup_at` whenever an
     * admin uses the Dashboard → Danger Zone → "Backup settings now" button,
     * which routes through SettingsController::export().
     */
    private function checkBackupStale(): array
    {
        $staleDays = 30;
        $raw       = trim((string) ($this->settings['last_backup_at'] ?? ''));
        $fixUrl    = 'index.php?option=com_aiboost&view=dashboard#ab-backup-button';

        if ($raw === '') {
            return $this->make(
                'warning_backup_stale', 'warning', 'Settings Backup',
                false, false,
                'No settings backup has ever been taken from this site. Download one now so you can restore everything on a future install or on a different site.',
                $fixUrl
            );
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return $this->make(
                'warning_backup_stale', 'warning', 'Settings Backup',
                false, false,
                'Last backup timestamp is unreadable — please take a fresh backup from the Dashboard Danger Zone.',
                $fixUrl
            );
        }

        $ageDays = (int) floor((time() - $ts) / 86400);
        if ($ageDays > $staleDays) {
            return $this->make(
                'warning_backup_stale', 'warning', 'Settings Backup',
                false, false,
                sprintf(
                    'Last settings backup is %d days old (older than %d days). Take a fresh backup so recent changes are not lost.',
                    $ageDays,
                    $staleDays
                ),
                $fixUrl
            );
        }

        return $this->make(
            'warning_backup_stale', 'warning', 'Settings Backup',
            true, true,
            sprintf('Last settings backup was %d day(s) ago — within the %d-day window.', $ageDays, $staleDays),
            ''
        );
    }

    private function checkLicenseVerified(): array
    {
        // Task #429 — new per-SKU license_state map is the source of truth.
        // Pass if ANY SKU (or bundle) is verified active.
        $states = [];
        if (class_exists('AiBoost\\Lib\\PluginRegistry')) {
            $states = \AiBoost\Lib\PluginRegistry::loadLicenseStates();
        }

        $active   = [];
        $expired  = [];
        $invalid  = [];
        foreach (['schema', 'og', 'hreflang', 'code', 'aeo', 'bundle'] as $sku) {
            $st = $states[$sku] ?? null;
            $resolved = \AiBoost\Lib\PluginRegistry::resolveRealStatus($st);
            if ($resolved === 'active') {
                $active[] = $sku;
            } elseif ($resolved === 'expired') {
                $status = strtolower((string) ($st['status'] ?? ''));
                if ($status === 'invalid' || $status === 'deactivated') {
                    $invalid[] = $sku;
                } else {
                    $expired[] = $sku;
                }
            }
        }

        $pass = !empty($active);
        if ($pass) {
            $msg = 'Active license(s) verified for: <strong>'
                . htmlspecialchars(implode(', ', $active), ENT_QUOTES)
                . '</strong>.';
            if (!empty($expired)) {
                $msg .= ' ⚠ Expired: ' . htmlspecialchars(implode(', ', $expired), ENT_QUOTES) . '.';
            }
        } else {
            // Back-compat fallback so users mid-migration still see correct state.
            $legacyTier = strtolower(trim((string) ($this->settings['license_tier'] ?? 'free')));
            $legacyKey  = trim((string) ($this->settings['license_key'] ?? ''));
            if ($legacyTier !== 'free' && $legacyKey !== '') {
                $pass = true;
                $msg  = 'Legacy license_tier="' . htmlspecialchars($legacyTier, ENT_QUOTES) . '" detected — '
                      . 'upgrade migration will materialise it on next install/upgrade.';
            } else {
                 $msg = 'No verified license key is stored. '
                     . 'Open the Licenses tab to enter your AI Boost license key for updates and support.';
            }
        }

        return $this->make(
            'warning_license_unverified', 'warning', 'License Key',
            $pass, $pass, $msg,
            $pass ? '' : 'index.php?option=com_aiboost#/licenses'
        );
    }

    /**
     * Task #429 — surface any SKU whose stored license_state has resolved to
     * 'expired' (or raw status invalid / limit_reached / deactivated). Pass
     * means zero invalid SKUs. Fix link → Licenses tab.
     */
    private function criticalLicenseInvalid(): array
    {
        $invalidSkus = [];
        if (class_exists('AiBoost\\Lib\\PluginRegistry')) {
            $states = \AiBoost\Lib\PluginRegistry::loadLicenseStates();
            foreach (['schema', 'og', 'hreflang', 'code', 'aeo', 'bundle'] as $sku) {
                $st = $states[$sku] ?? null;
                if (!is_array($st)) {
                    continue;
                }
                $resolved = \AiBoost\Lib\PluginRegistry::resolveRealStatus($st);
                if ($resolved === 'expired') {
                    $invalidSkus[] = $sku . ' (' . strtolower((string) ($st['status'] ?? 'expired')) . ')';
                }
            }
        }

        $pass = empty($invalidSkus);
        $msg  = $pass
            ? 'No invalid or expired licenses detected.'
            : 'Invalid or expired license(s) detected: <strong>'
                . htmlspecialchars(implode(', ', $invalidSkus), ENT_QUOTES)
                . '</strong>. Renew the key for updates/support or remove it on the Licenses tab.';

        return $this->make(
            'critical_license_invalid', 'critical', 'License Invalid / Expired',
            $pass, $pass, $msg,
            $pass ? '' : 'index.php?option=com_aiboost#/licenses'
        );
    }

    /**
    * Task #439 — info card surfaced when no active license is on file, so
    * the auto-update server can withhold licensed add-on updates while the
    * base package continues to update normally.
     */
    private function infoAutoUpdateDisabled(): array
    {
        $state = $this->settings['license_state'] ?? [];
        $hasActive = false;
        if (is_array($state)) {
            foreach (['bundle', 'schema', 'aeo', 'og', 'hreflang', 'code'] as $sku) {
                $status = $state[$sku]['status'] ?? '';
                $key    = $state[$sku]['key'] ?? '';
                if ($status === 'active' && is_string($key) && $key !== '') {
                    $hasActive = true;
                    break;
                }
            }
        }
        $message = $hasActive
            ? 'Auto-updates for licensed add-ons are enabled — Joomla will check updates.aiboostnow.com on its schedule.'
            : 'No active license on file. The base package will still update; licensed add-on updates will not appear in Joomla → Update until a key is verified.';
        return $this->make(
            'info_auto_update_disabled', 'info', 'Auto-update server',
            true, true, $message,
            $hasActive ? '' : 'index.php?option=com_aiboost#/licenses'
        );
    }

    // ── Task #440 — License heartbeat / grace / domain-collision ────────

    /**
     * Info: when was the last successful phone-home heartbeat? Pass when
     * a heartbeat ran in the last HEARTBEAT_INTERVAL_DAYS, fail (info-
     * level) otherwise so admins know the plugin couldn't reach
     * api.aiboostnow.com.
     */
    private function infoLicenseLastHeartbeat(): array
    {
        $hb     = is_array($this->settings['license_heartbeat'] ?? null)
            ? $this->settings['license_heartbeat']
            : [];
        $last   = isset($hb['last_checked_at']) ? (int) strtotime((string) $hb['last_checked_at']) : 0;
        $maxAge = \AiBoost\Lib\LicenseHeartbeat::HEARTBEAT_INTERVAL_DAYS * 86400;
        $pass   = $last > 0 && (time() - $last) <= ($maxAge + 86400); // 1-day slack

        if ($last <= 0) {
            $msg = 'License has not phoned home yet. The first heartbeat runs automatically when an admin loads the AI Boost backend with an active license key.';
        } else {
            $ageDays = max(0, floor((time() - $last) / 86400));
            $msg = $pass
                ? sprintf('Last verified %d day(s) ago — next automatic check in up to 7 days.', $ageDays)
                : sprintf('Last verified %d day(s) ago — older than expected. Check that this site can reach api.aiboostnow.com (firewall, outbound HTTPS).', $ageDays);
        }

        return $this->make(
            'info_license_last_heartbeat', 'info', 'License — last verified',
            $pass, true, $msg,
            $pass ? '' : 'index.php?option=com_aiboost#/licenses'
        );
    }

    /**
     * Info: licence renewal reminder (Task #565 — perpetual activation).
     *
     * Pro is unlocked permanently once a key is activated, so an expired or
     * non-active licence NEVER disables features. The only consequence of an
     * expired licence is that automatic updates + support pause until renewal.
     * This check surfaces that as a friendly info notice — it passes silently
     * while the licence is active (or the install was never activated).
     */
    private function infoLicenseRenewal(): array
    {
        $activated = (string) ($this->settings['pro_activated'] ?? '0') === '1';
        $hb        = is_array($this->settings['license_heartbeat'] ?? null)
            ? $this->settings['license_heartbeat']
            : [];
        $status    = strtolower((string) ($hb['status'] ?? ''));
        $verdict   = strtolower((string) ($hb['last_verdict'] ?? ''));

        // Only nudge an activated Pro install whose last verified status looks
        // lapsed. Everything else passes with no action needed.
        $lapsed = $activated
            && ($status === 'expired' || ($verdict !== '' && $verdict !== 'ok'));

        if (!$lapsed) {
            return $this->make(
                'info_license_renewal', 'info', 'License — renewal',
                true, false,
                $activated
                    ? 'Your licence is current. Automatic updates and support are available.'
                    : 'No active licence to renew.',
                ''
            );
        }

        $msg = 'Your licence has lapsed. Renewing restores automatic updates and support. Open the Licenses tab to renew.';

        return $this->make(
            'info_license_renewal', 'info', 'License — renewal',
            false, false, $msg,
            'index.php?option=com_aiboost#/licenses'
        );
    }

    /**
     * Critical: server reported `domain_mismatch` — same key activated on
     * another domain. Show a hard warning so admins (and pirates) can't
     * miss it.
     */
    private function criticalLicenseDomainCollision(): array
    {
        $collision = \AiBoost\Lib\LicenseHeartbeat::hasDomainCollision($this->settings);
        $msg       = $collision
            ? 'This license key is already activated on another domain. Please release it from the other site through the Lemon Squeezy customer portal, or contact support.'
            : 'No domain collision detected.';
        return $this->make(
            'critical_license_domain_collision', 'critical', 'License — domain collision',
            !$collision, false, $msg,
            $collision ? 'index.php?option=com_aiboost#/licenses' : ''
        );
    }

    /**
     * Warn when Article Schema is enabled but no author name is configured.
     */
    private function checkArticleSchemaAuthor(): array
    {
        $articleSchemaOn = (string) ($this->settings['article_schema_enabled'] ?? '1') !== '0';
        if (!$articleSchemaOn) {
            return $this->make(
                'warning_article_schema_author', 'warning', 'Article Schema — Author',
                true, true, 'Article Schema is disabled — author check skipped.', ''
            );
        }

        $entityOn = (string) ($this->settings['schema_author_entity_enabled'] ?? '0') === '1';
        $pass     = $entityOn;
        $msg      = $pass
            ? 'Author Entity is enabled — Article/BlogPosting schema will include a Person entity built from each article author\'s Joomla User Custom Fields.'
            : 'Author Entity is disabled — Article/BlogPosting schema will only include the author\'s name, which reduces E-E-A-T signals. Turn on the Author Entity toggle and set up aiboost_* custom fields on user profiles.';

        return $this->make(
            'warning_article_schema_author', 'warning', 'Article Schema — Author',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('tab-schema-btn', 'schema_author_entity_enabled')
        );
    }

    /**
     * Info: coverage of AI Boost user custom fields on Joomla content authors.
     *
     * Counts how many users with at least one published article have an
     * `aiboost_job_title*` custom field populated. Surfaces a clear signal so
     * admins know whether the Author Entity toggle is actually doing anything.
     *
     * Skip path: when the toggle is OFF, returns a neutral info row pointing
     * to the toggle.
     */
    private function infoSchemaAuthorFieldsCoverage(): array
    {
        $entityOn = (string) ($this->settings['schema_author_entity_enabled'] ?? '0') === '1';

        $fixActions = [[
            'label' => 'Open Author Entity toggle',
            'url'   => $this->settingsUrl('tab-schema-btn', 'schema_author_entity_enabled'),
            'tab'   => 'tab-schema-btn',
            'field' => 'schema_author_entity_enabled',
        ]];

        if (!$entityOn) {
            return $this->make(
                'info_schema_author_fields_coverage', 'info', 'Author Entity — Custom Fields Coverage',
                true, false,
                'Author Entity toggle is off — coverage of AI Boost user custom fields is not measured.',
                $this->settingsUrl('tab-schema-btn', 'schema_author_entity_enabled'),
                $fixActions
            );
        }

        try {
            $db = $this->db;

            // Authors with at least one published article.
            $authors = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(DISTINCT ' . $db->quoteName('created_by') . ')')
                    ->from('#__content')
                    ->where($db->quoteName('state') . ' = 1')
            )->loadResult();

            // Users whose profile has any `aiboost_job_title*` custom field populated.
            $populated = (int) $db->setQuery(
                'SELECT COUNT(DISTINCT fv.item_id) '
                . 'FROM ' . $db->quoteName('#__fields_values') . ' fv '
                . 'INNER JOIN ' . $db->quoteName('#__fields') . ' f ON f.id = fv.field_id '
                . 'INNER JOIN ' . $db->quoteName('#__content') . ' c ON c.created_by = fv.item_id AND c.state = 1 '
                . 'WHERE f.context = ' . $db->quote('com_users.user')
                . ' AND f.name LIKE ' . $db->quote('aiboost_job_title%')
                . ' AND fv.value IS NOT NULL AND fv.value <> ' . $db->quote('')
            )->loadResult();
        } catch (\Throwable $e) {
            $authors   = 0;
            $populated = 0;
        }

        if ($authors === 0) {
            $msg = 'Author Entity is on, but no published articles were found yet — nothing to enrich.';
            return $this->make(
                'info_schema_author_fields_coverage', 'info', 'Author Entity — Custom Fields Coverage',
                true, false, $msg,
                $this->settingsUrl('tab-schema-btn', 'schema_author_entity_enabled'),
                $fixActions
            );
        }

        $allGood = $populated >= $authors;
        $msg     = $allGood
            ? sprintf(
                'All %d author(s) with published articles have an AI Boost job title set — every article will emit a rich Person entity.',
                $authors
            )
            : sprintf(
                '%d of %d author(s) with published articles have an AI Boost job title set — articles by the remaining %d author(s) will emit a basic Person entity with name only. Add aiboost_job_title (and other aiboost_* fields) on their Joomla user profiles.',
                $populated, $authors, max(0, $authors - $populated)
            );

        return $this->make(
            'info_schema_author_fields_coverage', 'info', 'Author Entity — Custom Fields Coverage',
            true, $allGood, $msg,
            $this->settingsUrl('tab-schema-btn', 'schema_author_entity_enabled'),
            $fixActions
        );
    }

    /**
     * Task #511/#512 — Reports the state of the central error log.
     *
     * Behaviour:
     *   - OFF     → info pass, message says logging is off.
     *   - ON, 0 error-level rows in last 24h → info pass with stats.
     *   - ON, ≥1 error-level row in last 24h → WARNING fail, Fix It
     *     deep-links to the new Errors tab (#/errors) so the admin
     *     can review what was logged.
     */
    private function infoErrorLogging(): array
    {
        $enabled = (string) ($this->settings['error_log_enabled'] ?? '1') === '1';
        $min     = (string) ($this->settings['error_log_min_severity'] ?? 'warning');

        $count24h  = 0;
        $errors24h = 0;
        $lastAt    = null;
        try {
            $cutoff = gmdate('Y-m-d H:i:s', time() - 86400);
            $count24h = (int) $this->db->setQuery(
                'SELECT COUNT(*) FROM ' . $this->db->quoteName('#__aiboost_error_log')
                . ' WHERE ' . $this->db->quoteName('created_at') . ' >= ' . $this->db->quote($cutoff)
            )->loadResult();
            $errors24h = (int) $this->db->setQuery(
                'SELECT COUNT(*) FROM ' . $this->db->quoteName('#__aiboost_error_log')
                . ' WHERE ' . $this->db->quoteName('severity') . ' = ' . $this->db->quote('error')
                . ' AND ' . $this->db->quoteName('created_at') . ' >= ' . $this->db->quote($cutoff)
            )->loadResult();
            $lastAt = (string) $this->db->setQuery(
                'SELECT MAX(' . $this->db->quoteName('created_at') . ') FROM '
                . $this->db->quoteName('#__aiboost_error_log')
                . ' WHERE ' . $this->db->quoteName('severity') . ' = ' . $this->db->quote('error')
            )->loadResult();
        } catch (\Throwable $e) {
            // table may not exist on very first request after install
        }

        if ($enabled && $errors24h > 0) {
            $msg = sprintf(
                '%d error-level event(s) recorded in the last 24h%s. Review them in the Errors tab and fix the underlying issue.',
                $errors24h,
                $lastAt ? ' (last: ' . $lastAt . ' UTC)' : ''
            );

            // The Errors tab lives in the Vue admin SPA shell (view=app),
            // not the legacy view=settings page. Build the deep link
            // directly against view=app and pass the target field as a
            // query param so the SPA can scroll to / highlight the
            // element with data-ab-field="errors_clear_all" after mount.
            $errorsUrl = 'index.php?option=com_aiboost&view=app'
                . '&field=errors_clear_all#/errors';

            return $this->make(
                'info_error_logging', 'warning', 'Error logging',
                false, true, $msg,
                $errorsUrl,
                [[
                    'label'        => 'Open Errors tab',
                    'url'          => $errorsUrl,
                    'target_tab'   => 'errors',
                    'target_field' => 'errors_clear_all',
                ]]
            );
        }

        $msg = $enabled
            ? sprintf(
                'Central error log is ON. Severity floor: %s. %d event(s) written in the last 24h (no error-level). Retention: 1000 rows or 30 days, whichever comes first.',
                $min,
                $count24h
            )
            : 'Central error log is OFF. AI Boost warnings/errors are not being recorded — turn on in Debug → Error logging to collect them.';

        return $this->make(
            'info_error_logging', 'info', 'Error logging',
            true, true, $msg,
            $this->settingsUrl('tab-debug-btn', 'error_log_enabled'),
            [[
                'label'        => 'Open Debug → Error logging',
                'url'          => $this->settingsUrl('tab-debug-btn', 'error_log_enabled'),
                'target_tab'   => 'debug',
                'target_field' => 'error_log_enabled',
            ]]
        );
    }

    /**
     * Info: confirm that og_description_override (Social tab) is reaching the
     * front end as the og:description value. Expected HTML artifact when set:
     *   <meta property="og:description" content="{override text}">
     *
     * The override is consumed by OgTagBuilder; per-language translations and
     * per-article custom fields take precedence when present.
     */
    private function infoOgDescriptionOverride(): array
    {
        $override = trim((string) ($this->settings['og_description_override'] ?? ''));

        $fixActions = [[
            'label' => 'Edit OG Description Override',
            'url'   => $this->settingsUrl('tab-social-btn', 'og_description_override'),
            'tab'   => 'tab-social-btn',
            'field' => 'og_description_override',
        ]];

        if ($override === '') {
            return $this->make(
                'info_og_description_override_active', 'info', 'OG Description Override',
                true, false,
                'No sitewide OpenGraph description override is set — og:description falls back to each page\'s meta description.',
                $this->settingsUrl('tab-social-btn', 'og_description_override'),
                $fixActions
            );
        }

        $preview = mb_substr($override, 0, 60);
        if (mb_strlen($override) > 60) {
            $preview .= '…';
        }

        return $this->make(
            'info_og_description_override_active', 'info', 'OG Description Override',
            true, true,
            'Sitewide OpenGraph description override is active and will be emitted as <meta property="og:description"> on pages without a more specific override. Preview: "' . htmlspecialchars($preview, ENT_QUOTES) . '".',
            $this->settingsUrl('tab-social-btn', 'og_description_override'),
            $fixActions
        );
    }

    /**
     * Task #537 — Info: Pro OpenGraph/Twitter enrichment gating state.
     *
     * The aiboost_social_pro plugin decorates the Free OG/Twitter props with
     * Pro-only output (og:type=article + article:* meta, og:locale, og:video,
     * fb:app_id, twitter:site, per-article OG custom-field overrides). That
     * decoration is now gated on a verified-active 'og' license via
     * PluginRegistry::hasPro('og'). This info entry lets the admin confirm the
     * gate behaves: enriched output only on a licensed (Pro-active) install,
     * suppressed (== Free) when the Pro plugin is present but unlicensed.
     */
    private function infoSocialOgPro(): array
    {
        // Post-collapse the Pro OG/Twitter enrichment lives in the free
        // aiboost_social plugin (relocated OgTagProDecorator), gated on a
        // verified-active 'og' license — not in a separate *_pro decorator.
        $enabled = $this->isPluginEnabled('aiboost_social');
        $active  = false;
        try {
            $active = \AiBoost\Lib\PluginRegistry::hasPro('og');
        } catch (\Throwable) { /* silent — treat as not active */ }

        $fixActions = [[
            'label' => 'Open Social tab',
            'url'   => $this->settingsUrl('tab-social-btn', 'enable_og_locale'),
            'tab'   => 'tab-social-btn',
            'field' => 'enable_og_locale',
        ]];
        $fixUrl = $this->settingsUrl('tab-social-btn', 'enable_og_locale');

        if (!$enabled) {
            return $this->make(
                'info_social_og_pro_active', 'info', 'Pro OpenGraph enrichment',
                true, false,
                'The Pro OpenGraph plugin is not active (not installed or disabled) — only the Free baseline og:/twitter: tags are emitted.',
                $fixUrl, $fixActions
            );
        }

        if ($active) {
            return $this->make(
                'info_social_og_pro_active', 'info', 'Pro OpenGraph enrichment',
                true, true,
                'OpenGraph enrichment is active — og:type=article + article:* meta, og:locale, og:video, fb:app_id, twitter:site and per-article OG overrides are emitted on the front-end where configured.',
                $fixUrl, $fixActions
            );
        }

        return $this->make(
            'info_social_og_pro_active', 'info', 'OpenGraph enrichment',
            true, false,
            'OpenGraph enrichment is installed but no active license is verified for updates/support; configured front-end tags continue to use the available emitter.',
            $fixUrl, $fixActions
        );
    }

    /**
     * Confirm that fb_domain_verification (Social tab) is reaching the front
     * end as a <meta name="facebook-domain-verification"> tag emitted by the
     * aiboost_analytics plugin. Reports:
     *   - pass + info  : token set and analytics plugin is enabled
     *   - warning      : token set but analytics plugin disabled / suppressed
     *   - info (skip)  : no token set
     *
     * The emitter is gated on staging_mode=0; in staging mode we treat the
     * suppression as expected and return a passing skip message.
     */
    private function infoFbDomainVerification(): array
    {
        $token = trim((string) ($this->settings['fb_domain_verification'] ?? ''));

        $fixActions = [[
            'label' => 'Edit Facebook Domain Verification',
            'url'   => $this->settingsUrl('tab-analytics-btn', 'fb_domain_verification'),
            'tab'   => 'tab-analytics-btn',
            'field' => 'fb_domain_verification',
        ]];

        if ($token === '') {
            return $this->make(
                'info_fb_domain_verification_active', 'info', 'Facebook Domain Verification',
                true, false,
                'No Facebook Domain Verification token set — the <meta name="facebook-domain-verification"> tag will not be emitted.',
                $this->settingsUrl('tab-analytics-btn', 'fb_domain_verification'),
                $fixActions
            );
        }

        if ($this->isStagingMode()) {
            return $this->make(
                'info_fb_domain_verification_active', 'info', 'Facebook Domain Verification',
                true, true,
                'Facebook Domain Verification token is set but suppressed because Staging Mode is active. The <meta name="facebook-domain-verification"> tag will appear once Staging Mode is turned off.',
                $this->settingsUrl('tab-debug-btn', 'staging_mode'),
                $fixActions
            );
        }

        if (!$this->isPluginEnabled('aiboost_analytics')) {
            return $this->make(
                'info_fb_domain_verification_active', 'warning', 'Facebook Domain Verification',
                false, false,
                'Facebook Domain Verification token is set but the Analytics plugin is disabled, so the <meta name="facebook-domain-verification"> tag is not being emitted.',
                $this->pluginManagerUrl('aiboost_analytics'),
                $fixActions
            );
        }

        // Verify the tag is actually present in the rendered homepage <head>.
        // Skipped in lightweight (module) mode to avoid extra HTTP latency.
        if ($this->skipHttpScan) {
            return $this->make(
                'info_fb_domain_verification_active', 'info', 'Facebook Domain Verification',
                true, true,
                'Facebook Domain Verification is configured (token: ' . htmlspecialchars(substr($token, 0, 8), ENT_QUOTES) . '…). Live HTML check skipped in lightweight mode.',
                '',
                $fixActions
            );
        }

        $html = $this->getHomepageHtml();
        if ($html === null) {
            return $this->make(
                'info_fb_domain_verification_active', 'info', 'Facebook Domain Verification',
                true, true,
                'Facebook Domain Verification is configured (token: ' . htmlspecialchars(substr($token, 0, 8), ENT_QUOTES) . '…). Could not fetch the homepage to verify <meta name="facebook-domain-verification"> emission.',
                '',
                $fixActions
            );
        }

        $detected = stripos($html, 'facebook-domain-verification') !== false
            && stripos($html, $token) !== false;

        if ($detected) {
            return $this->make(
                'info_fb_domain_verification_active', 'info', 'Facebook Domain Verification',
                true, true,
                'Facebook Domain Verification is active — <meta name="facebook-domain-verification" content="' . htmlspecialchars(substr($token, 0, 8), ENT_QUOTES) . '…"> was detected in the homepage <head>.',
                '',
                $fixActions
            );
        }

        return $this->make(
            'info_fb_domain_verification_active', 'warning', 'Facebook Domain Verification',
            false, false,
            'Facebook Domain Verification token is saved but <meta name="facebook-domain-verification"> was NOT found in the live homepage HTML — caching, a template override, or another extension may be stripping the tag.',
            $this->settingsUrl('tab-analytics-btn', 'fb_domain_verification'),
            $fixActions
        );
    }

    /**
     * Confirm that the AEO "AI Signals" toggle (aeo_ai_meta_enabled) is actually
     * emitting the three <meta> tags on the front-end:
     *   <meta name="ai-content-verified"  content="true">
     *   <meta name="ai-content-optimized" content="true">
     *   <meta name="llms-txt"             content="{root}/llms.txt">
     *
     * The emitter (aiboost_aeo/onBeforeCompileHead) is gated on isPro() + the
     * setting + Cooperative-mode DocumentInspector skip. Reports:
     *   - info (skip)   : toggle is OFF
     *   - warning       : toggle ON but plugin disabled / not Pro
     *   - info (active) : toggle ON, live HTML check passes
     *   - warning       : toggle ON but tags NOT found in live HTML
     *
     * Audit #377 (C1): replaces the previously empty wrapper-comment artifact
     * with the actual meta tags as the verifiable signal.
     */
    private function infoAeoAiMetaTags(): array
    {
        $enabled = (string) ($this->settings['aeo_ai_meta_enabled'] ?? '0') === '1';

        $fixActions = [[
            'label' => 'Toggle AI meta tags (AEO tab)',
            'url'   => $this->settingsUrl('tab-aeo-btn', 'aeo_ai_meta_enabled'),
            'tab'   => 'tab-aeo-btn',
            'field' => 'aeo_ai_meta_enabled',
        ]];

        if (!$enabled) {
            return $this->make(
                'info_aeo_ai_meta_tags_active', 'info', 'AI Meta Tags',
                true, false,
                'AI meta tags are disabled — <meta name="ai-content-verified">, <meta name="ai-content-optimized"> and <meta name="llms-txt"> will not be emitted.',
                $this->settingsUrl('tab-aeo-btn', 'aeo_ai_meta_enabled'),
                $fixActions
            );
        }

        if (!$this->isPluginEnabled('aiboost_aeo')) {
            return $this->make(
                'info_aeo_ai_meta_tags_active', 'warning', 'AI Meta Tags',
                false, false,
                'AI meta tags toggle is ON but the AEO plugin is disabled — no <meta> tags are being injected.',
                $this->pluginManagerUrl('aiboost_aeo'),
                $fixActions
            );
        }

        if ($this->skipHttpScan) {
            return $this->make(
                'info_aeo_ai_meta_tags_active', 'info', 'AI Meta Tags',
                true, true,
                'AI meta tags are enabled (live HTML check skipped in lightweight mode). Expected on every page: <meta name="ai-content-verified">, <meta name="ai-content-optimized">, <meta name="llms-txt">.',
                '',
                $fixActions
            );
        }

        $html = $this->getHomepageHtml();
        if ($html === null) {
            return $this->make(
                'info_aeo_ai_meta_tags_active', 'warning', 'AI Meta Tags',
                false, false,
                'AI meta tags are enabled but the homepage could not be fetched to verify <meta> emission — check site availability, network access, or HTTP block rules. The check cannot confirm the three signals (ai-content-verified, ai-content-optimized, llms-txt) are reaching visitors.',
                $this->settingsUrl('tab-aeo-btn', 'aeo_ai_meta_enabled'),
                $fixActions
            );
        }

        $hasVerified  = stripos($html, 'name="ai-content-verified"')  !== false;
        $hasOptimized = stripos($html, 'name="ai-content-optimized"') !== false;
        $hasLlmsTxt   = stripos($html, 'name="llms-txt"')             !== false;

        if ($hasVerified && $hasOptimized && $hasLlmsTxt) {
            return $this->make(
                'info_aeo_ai_meta_tags_active', 'info', 'AI Meta Tags',
                true, true,
                'AI meta tags are active — all three signals detected in the homepage <head>: ai-content-verified, ai-content-optimized, llms-txt.',
                '',
                $fixActions
            );
        }

        $missing = [];
        if (!$hasVerified)  { $missing[] = 'ai-content-verified'; }
        if (!$hasOptimized) { $missing[] = 'ai-content-optimized'; }
        if (!$hasLlmsTxt)   { $missing[] = 'llms-txt'; }

        return $this->make(
            'info_aeo_ai_meta_tags_active', 'warning', 'AI Meta Tags',
            false, false,
            'AI meta tags toggle is ON but the following <meta> tag(s) were NOT found in the live homepage HTML: ' . htmlspecialchars(implode(', ', $missing), ENT_QUOTES) . '. Caching, Cooperative-mode skip (another SEO extension is emitting them), or a template override may be intercepting the output.',
            $this->settingsUrl('tab-aeo-btn', 'aeo_ai_meta_enabled'),
            $fixActions
        );
    }

    /**
    * Info: Markdown discovery <link> tag in <head>.
     *
     * The aiboost_aeo plugin emits, when markdown_pages_enabled=1 and tier=Pro:
     *   <link rel="alternate" type="text/markdown" href="{current-url}?markdown=1">
     * This lets AI agents auto-discover the Markdown version of every page.
     *
     * Audit #378 (C2): pairs the per-page artifact with a Health entry so users
     * can confirm the feature is live. Live HTML scan looks for the literal
     * type="text/markdown" attribute on a <link rel="alternate"> tag.
     */
    /**
     * Info — llms.txt FAQ Auto-Detect is enabled.
     */
    private function infoAeoFaqAutoDetect(): array
    {
        $enabled = (string) ($this->settings['faq_auto_detect'] ?? '0') === '1';
        $llmsOn  = (string) ($this->settings['llmstxt_enabled'] ?? '0') === '1';
        $faqOn   = (int) ($this->settings['llmstxt_include_faq'] ?? 1) === 1;

        $fixActions = [[
            'label' => 'Toggle FAQ Auto-Detect (Schema tab)',
            'url'   => $this->settingsUrl('tab-schema-btn', 'faq_auto_detect'),
            'tab'   => 'tab-schema-btn',
            'field' => 'faq_auto_detect',
        ]];

        if (!$enabled) {
            return $this->make(
                'info_aeo_faq_auto_detect_active', 'info', 'llms.txt FAQ Auto-Detect',
                true, false,
                'FAQ Auto-Detect is OFF — only manually-entered FAQ items are added to llms.txt. Enable it to have AI Boost scan your 25 most recent articles for <h2>/<h3>/<h4> question headings and append the answers automatically.',
                $this->settingsUrl('tab-schema-btn', 'faq_auto_detect'),
                $fixActions
            );
        }

        if (!$llmsOn || !$faqOn) {
            return $this->make(
                'info_aeo_faq_auto_detect_active', 'warning', 'llms.txt FAQ Auto-Detect',
                false, false,
                'FAQ Auto-Detect is ON but the parent feature is disabled — enable both /llms.txt and the FAQ section before auto-detected pairs can appear.',
                $this->settingsUrl('tab-aeo-btn', 'llmstxt_enabled'),
                $fixActions
            );
        }

        if (!$this->isPluginEnabled('aiboost_aeo')) {
            return $this->make(
                'info_aeo_faq_auto_detect_active', 'warning', 'llms.txt FAQ Auto-Detect',
                false, false,
                'FAQ Auto-Detect is ON but the AEO plugin is disabled — /llms.txt is not being served. Enable plg_system_aiboost_aeo.',
                $this->pluginManagerUrl('aiboost_aeo'),
                $fixActions
            );
        }

        return $this->make(
            'info_aeo_faq_auto_detect_active', 'info', 'llms.txt FAQ Auto-Detect',
            true, false,
            'FAQ Auto-Detect is active — AI Boost scans the 25 most recent published articles for <h2>/<h3>/<h4> question headings and appends up to 30 detected Q&A pairs to /llms.txt (manual FAQs win on duplicates).',
            '',
            $fixActions
        );
    }

    private function infoAeoMarkdownDiscovery(): array
    {
        $enabled = (string) ($this->settings['markdown_pages_enabled'] ?? '0') === '1';

        $fixActions = [[
            'label' => 'Toggle Markdown pages (AEO tab)',
            'url'   => $this->settingsUrl('tab-aeo-btn', 'markdown_pages_enabled'),
            'tab'   => 'tab-aeo-btn',
            'field' => 'markdown_pages_enabled',
        ]];

        if (!$enabled) {
            return $this->make(
                'info_aeo_markdown_discovery_active', 'info', 'Markdown Discovery Link',
                true, false,
                'Markdown pages are disabled — no <link rel="alternate" type="text/markdown"> will be emitted and AI agents cannot auto-discover the Markdown version of pages.',
                $this->settingsUrl('tab-aeo-btn', 'markdown_pages_enabled'),
                $fixActions
            );
        }

        if (!$this->isPluginEnabled('aiboost_aeo')) {
            return $this->make(
                'info_aeo_markdown_discovery_active', 'warning', 'Markdown Discovery Link',
                false, false,
                'Markdown pages toggle is ON but the AEO plugin is disabled — no <link rel="alternate" type="text/markdown"> is being injected.',
                $this->pluginManagerUrl('aiboost_aeo'),
                $fixActions
            );
        }

        if ($this->skipHttpScan) {
            return $this->make(
                'info_aeo_markdown_discovery_active', 'info', 'Markdown Discovery Link',
                true, true,
                'Markdown pages are enabled (live HTML check skipped in lightweight mode). Expected on every page: <link rel="alternate" type="text/markdown" href="…?markdown=1">.',
                '',
                $fixActions
            );
        }

        $html = $this->getHomepageHtml();
        if ($html === null) {
            return $this->make(
                'info_aeo_markdown_discovery_active', 'warning', 'Markdown Discovery Link',
                false, false,
                'Markdown pages are enabled but the homepage could not be fetched to verify the discovery <link> emission — check site availability, network access, or HTTP block rules. The check cannot confirm the Markdown alternate link is reaching visitors.',
                $this->settingsUrl('tab-aeo-btn', 'markdown_pages_enabled'),
                $fixActions
            );
        }

        // Look for <link rel="alternate" ... type="text/markdown" ...> in either attribute order.
        $hasLink = (bool) preg_match(
            '/<link\b[^>]*\brel\s*=\s*["\']alternate["\'][^>]*\btype\s*=\s*["\']text\/markdown["\']/i',
            $html
        ) || (bool) preg_match(
            '/<link\b[^>]*\btype\s*=\s*["\']text\/markdown["\'][^>]*\brel\s*=\s*["\']alternate["\']/i',
            $html
        );

        if ($hasLink) {
            return $this->make(
                'info_aeo_markdown_discovery_active', 'info', 'Markdown Discovery Link',
                true, true,
                'Markdown discovery link is active — <link rel="alternate" type="text/markdown"> detected in the homepage <head>. AI agents can auto-discover the Markdown version of every page.',
                '',
                $fixActions
            );
        }

        return $this->make(
            'info_aeo_markdown_discovery_active', 'warning', 'Markdown Discovery Link',
            false, false,
            'Markdown pages toggle is ON but no <link rel="alternate" type="text/markdown"> was found in the live homepage HTML. Caching or a template override may be stripping the tag.',
            $this->settingsUrl('tab-aeo-btn', 'markdown_pages_enabled'),
            $fixActions
        );
    }

    /**
    * Info: emit one health check per enabled SEO scraper-block.
     * Mirrors the canonical scraper_* keys consumed by RobotsTxtManager and
     * SettingsController::regenerateRobotsTxt(). Expected artifact for each
     * enabled bot:
     *   User-agent: {ua}
     *   Disallow: /
     * in /robots.txt. When skipHttpScan is false the live file is read and
     * the block must be present; otherwise the check is configuration-only.
     *
     * Bots with no setting (or '0') produce no health entry — keeps the
     * Health page uncluttered.
     *
     * @return list<array>
     */
    private function infoRobotsBlockedScrapers(): array
    {
        $defs = [
            'scraper_ahrefsbot'     => 'AhrefsBot',
            'scraper_semrushbot'    => 'SemrushBot',
            'scraper_dotbot'        => 'DotBot',
            'scraper_mj12bot'       => 'MJ12bot',
            'scraper_blexbot'       => 'BLEXBot',
            'scraper_rogerbot'      => 'rogerbot',
            'scraper_screamingfrog' => 'Screaming Frog SEO Spider',
            'scraper_sitebulb'      => 'Sitebulb',
            'scraper_siteauditor'   => 'SiteAuditBot',
            'scraper_serpstatbot'   => 'SerpstatBot',
            'scraper_bytespider'    => 'Bytespider',
            'scraper_petalbot'      => 'PetalBot',
        ];

        $checks    = [];
        $robotsTxt = null;
        if (!$this->skipHttpScan) {
            $file = AdapterRegistry::filesystem()->sitePath('robots.txt');
            if (file_exists($file)) {
                $robotsTxt = (string) @file_get_contents($file);
            }
        }

        foreach ($defs as $key => $ua) {
            if ((string) ($this->settings[$key] ?? '0') !== '1') {
                continue;
            }

            $id    = 'info_robots_block_' . substr($key, 8); // strip "scraper_"
            $label = 'robots.txt blocks ' . $ua;

            $fixActions = [[
                'label' => 'Edit scraper toggle (Crawlers & Robots tab)',
                'url'   => $this->settingsUrl('tab-crawlers-btn', $key),
                'tab'   => 'tab-crawlers-btn',
                'field' => $key,
            ]];

            if ($robotsTxt === null) {
                // Lightweight mode or no robots.txt on disk — configuration-only
                $checks[] = $this->make(
                    $id, 'info', $label,
                    true, true,
                    'Configured to block ' . htmlspecialchars($ua, ENT_QUOTES) . ' via robots.txt (live file check skipped).',
                    '', $fixActions
                );
                continue;
            }

            // Detect "User-agent: {ua}" block followed (within a few lines) by "Disallow: /"
            $detected = false;
            if (preg_match('/User-agent:\s*' . preg_quote($ua, '/') . '\s*\R+(?:[^\r\n]*\R+){0,5}Disallow:\s*\/(?:\s|$)/i', $robotsTxt)) {
                $detected = true;
            }

            if ($detected) {
                $checks[] = $this->make(
                    $id, 'info', $label,
                    true, true,
                    htmlspecialchars($ua, ENT_QUOTES) . ' is blocked in /robots.txt as configured.',
                    '', $fixActions
                );
            } else {
                $checks[] = $this->make(
                    $id, 'warning', $label,
                    false, false,
                    htmlspecialchars($ua, ENT_QUOTES) . ' is enabled in settings but no matching "User-agent: ' . htmlspecialchars($ua, ENT_QUOTES) . '" + "Disallow: /" block was found in /robots.txt. Save settings to regenerate the file.',
                    $this->settingsUrl('tab-crawlers-btn', $key),
                    $fixActions
                );
            }
        }

        return $checks;
    }

    /**
     * Fetch and cache the live homepage HTML for artifact detection.
     * Returns null on failure (network error, redirect loop, empty body).
     * Honors `skipHttpScan` by always returning null in that mode.
     */
    private ?string $homepageHtmlCache = null;
    private bool   $homepageHtmlFetched = false;

    private function getHomepageHtml(): ?string
    {
        if ($this->skipHttpScan) {
            return null;
        }
        if ($this->homepageHtmlFetched) {
            return $this->homepageHtmlCache;
        }
        $this->homepageHtmlFetched = true;

        try {
            $base = rtrim($this->ctx->getBaseUrl(), '/');
            if ($base === '' || !filter_var($base . '/', FILTER_VALIDATE_URL)) {
                return null;
            }
            $ctx  = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'timeout'         => 5.0,
                    'follow_location' => 1,
                    'max_redirects'   => 5,
                    'ignore_errors'   => true,
                    'user_agent'      => 'AI Boost Health Checker/1.0',
                ],
                'ssl'  => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $html = @file_get_contents($base . '/', false, $ctx);
            if ($html === false || strlen($html) < 200) {
                return null;
            }
            $this->homepageHtmlCache = $html;
            return $html;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Warn when Article Schema is enabled but no fallback image is set.
     */
    private function checkArticleSchemaImage(): array
    {
        $articleSchemaOn = (string) ($this->settings['article_schema_enabled'] ?? '1') !== '0';
        if (!$articleSchemaOn) {
            return $this->make(
                'warning_article_schema_image', 'warning', 'Article Schema — Image',
                true, true, 'Article Schema is disabled — image check skipped.', ''
            );
        }

        $ogImage = self::normaliseImagePath((string) ($this->settings['default_og_image'] ?? $this->settings['og_default_image'] ?? ''));
        $pass    = $ogImage !== '';
        $msg     = $pass
            ? 'Default OG image is set and will be used as the Article Schema image fallback.'
            : 'No default OG image configured — articles without an intro image will generate Article Schema without an image property, which may prevent Google Rich Results.';

        $contributingFields = [
            [
                'label' => 'Article Schema enabled',
                'url'   => $this->settingsUrl('tab-schema-btn', 'article_schema_enabled'),
                'pass'  => $articleSchemaOn,
            ],
            [
                'label' => 'Default OG image set',
                'url'   => $this->settingsUrl('tab-social-btn', 'default_og_image'),
                'pass'  => $pass,
            ],
        ];

        return $this->make(
            'warning_article_schema_image', 'warning', 'Article Schema — Image',
            $pass, $pass, $msg,
            $pass ? '' : $this->settingsUrl('tab-social-btn', 'default_og_image'),
            [],
            $contributingFields
        );
    }

    /**
     * AI Visibility Score — composite indicator of how well the site signals
     * its content to AI search engines (ChatGPT, Perplexity, Google AI Overview).
     *
     * Score is 0–100 based on: Schema.org, Article Schema, WebSite+SearchAction,
     * llms.txt, llms-full.txt, IndexNow, sameAs social links, author name.
     */
    private function infoAiVisibilityScore(): array
    {
        $points  = 0;
        $maxPts  = 100;
        $missing = [];

        // Schema.org active (20 pts)
        if (!empty($this->settings['enable_schema']) && $this->isPluginEnabled('aiboost_schema')) {
            $points += 20;
        } else {
            $missing[] = 'Schema.org disabled';
        }

        // Article Schema (15 pts)
        if ((string) ($this->settings['article_schema_enabled'] ?? '1') !== '0') {
            $points += 15;
        } else {
            $missing[] = 'Article Schema disabled';
        }

        // WebSite + SearchAction (10 pts)
        if ((string) ($this->settings['website_schema_enabled'] ?? '1') !== '0') {
            $points += 10;
        } else {
            $missing[] = 'WebSite schema disabled';
        }

        // llms.txt (15 pts)
        if ((string) ($this->settings['llmstxt_enabled'] ?? '0') === '1' && $this->isPluginEnabled('aiboost_aeo')) {
            $points += 15;
        } else {
            $missing[] = 'llms.txt inactive';
        }

        // llms-full.txt (10 pts)
        if ((string) ($this->settings['llms_full_txt_enabled'] ?? '0') === '1') {
            $points += 10;
        } else {
            $missing[] = 'llms-full.txt inactive';
        }

        // IndexNow (15 pts)
        $indexNowKey = trim((string) ($this->settings['indexnow_api_key'] ?? ''));
        if ($indexNowKey !== '') {
            $points += 15;
        } else {
            $missing[] = 'IndexNow not configured';
        }

        // sameAs social links (5 pts — at least 2 configured)
        $socialCount = 0;
        foreach (['facebook', 'instagram', 'youtube', 'twitter', 'linkedin', 'tiktok', 'pinterest'] as $net) {
            if (trim((string) ($this->settings["schema_social_{$net}"] ?? '')) !== '') {
                $socialCount++;
            }
        }
        if ($socialCount >= 2) {
            $points += 5;
        } else {
            $missing[] = 'Few sameAs links (<2)';
        }

        // Author name for E-E-A-T (10 pts)
        if ((string) ($this->settings['schema_author_entity_enabled'] ?? '0') === '1') {
            $points += 10;
        } else {
            $missing[] = 'Author Entity disabled';
        }

        $score = (int) round(($points / $maxPts) * 100);
        $label = $score >= 85 ? 'Excellent' : ($score >= 65 ? 'Good' : ($score >= 40 ? 'Fair' : 'Poor'));
        $emoji = $score >= 85 ? '✅' : ($score >= 65 ? '🟡' : '⚠️');

        $msg = "AI Visibility Score: {$score}/100 ({$label}) {$emoji}";
        if (!empty($missing)) {
            $msg .= ' — Missing signals: ' . implode(', ', $missing) . '.';
        } else {
            $msg .= ' — All AI visibility signals are active.';
        }

        $schemaOn    = !empty($this->settings['enable_schema']) && $this->isPluginEnabled('aiboost_schema');
        $articleOn   = (string) ($this->settings['article_schema_enabled'] ?? '1') !== '0';
        $websiteOn   = (string) ($this->settings['website_schema_enabled'] ?? '1') !== '0';
        $llmsOn      = (string) ($this->settings['llmstxt_enabled'] ?? '0') === '1' && $this->isPluginEnabled('aiboost_aeo');
        $llmsFullOn  = (string) ($this->settings['llms_full_txt_enabled'] ?? '0') === '1';
        $indexNowSet = trim((string) ($this->settings['indexnow_api_key'] ?? '')) !== '';
        $sameAsOk    = $socialCount >= 2;
        $authorSet   = (string) ($this->settings['schema_author_entity_enabled'] ?? '0') === '1';

        $contributingFields = [
            [
                'label'  => 'Schema.org active (20 pts)',
                'url'    => $this->settingsUrl('tab-schema-btn', 'enable_schema'),
                'pass'   => $schemaOn,
            ],
            [
                'label'  => 'Article Schema (15 pts)',
                'url'    => $this->settingsUrl('tab-schema-btn', 'article_schema_enabled'),
                'pass'   => $articleOn,
            ],
            [
                'label'  => 'WebSite + SearchAction (10 pts)',
                'url'    => $this->settingsUrl('tab-schema-btn', 'website_schema_enabled'),
                'pass'   => $websiteOn,
            ],
            [
                'label'  => 'llms.txt (15 pts)',
                'url'    => $this->settingsUrl('tab-aeo-btn', 'llmstxt_enabled'),
                'pass'   => $llmsOn,
            ],
            [
                'label'  => 'llms-full.txt (10 pts)',
                'url'    => $this->settingsUrl('tab-aeo-btn', 'llms_full_txt_enabled'),
                'pass'   => $llmsFullOn,
            ],
            [
                'label'  => 'IndexNow configured (15 pts)',
                'url'    => $this->settingsUrl('tab-aeo-btn', 'indexnow_api_key'),
                'pass'   => $indexNowSet,
            ],
            [
                'label'  => 'sameAs social links ≥2 (5 pts)',
                'url'    => $this->settingsUrl('tab-schema-btn', 'schema_social_facebook'),
                'pass'   => $sameAsOk,
            ],
            [
                'label'  => 'Author Entity / E-E-A-T (10 pts)',
                'url'    => $this->settingsUrl('tab-schema-btn', 'schema_author_entity_enabled'),
                'pass'   => $authorSet,
            ],
        ];

        return $this->make(
            'info_ai_visibility_score', 'info', 'AI Visibility Score',
            $score >= 65, true, $msg,
            $score < 65 ? $this->settingsUrl('tab-aeo-btn', 'llmstxt_enabled') : '',
            [],
            $contributingFields
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INFO CHECKS
    // ─────────────────────────────────────────────────────────────────────────

    // ─────────────────────────────────────────────────────────────────────────
    // POSITION CHECKS (#379) — verify in-HTML placement of injected blocks
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Split rendered HTML into [head, body] strings. Returns ['', ''] when
     * the markers cannot be located.
     *
     * @return array{0:string,1:string}
     */
    private function splitHeadBody(string $html): array
    {
        if (!preg_match('/<head\b[^>]*>(.*?)<\/head\s*>/is', $html, $hm)) {
            return ['', ''];
        }
        if (!preg_match('/<body\b[^>]*>(.*?)<\/body\s*>/is', $html, $bm)) {
            return [$hm[1], ''];
        }
        return [$hm[1], $bm[1]];
    }

    /**
     * Verify the GTM <noscript> iframe is injected immediately inside <body>
     * (Google spec). The aiboost_analytics plugin places it via onAfterRender.
     */
    private function infoPositionGtmNoscriptBody(): array
    {
        $enabled = !empty($this->settings['enable_gtm']);
        $gtmId   = trim((string) ($this->settings['gtm_container_id'] ?? ''));

        $fixActions = [[
            'label' => 'Edit GTM settings (Analytics tab)',
            'url'   => $this->settingsUrl('tab-analytics-btn', 'gtm_container_id'),
            'tab'   => 'tab-analytics-btn',
            'field' => 'gtm_container_id',
        ]];

        if (!$enabled || $gtmId === '') {
            return $this->make(
                'info_position_gtm_noscript_body', 'info', 'GTM noscript position',
                true, false,
                'Google Tag Manager is not configured — the <noscript> iframe is not being injected.',
                $this->settingsUrl('tab-analytics-btn', 'gtm_container_id'), $fixActions
            );
        }

        if ($this->isStagingMode() || !$this->isPluginEnabled('aiboost_analytics')) {
            return $this->make(
                'info_position_gtm_noscript_body', 'info', 'GTM noscript position',
                true, true,
                'GTM noscript position check skipped — Analytics plugin is disabled or Staging Mode is active.',
                '', $fixActions
            );
        }

        if ($this->skipHttpScan) {
            return $this->make(
                'info_position_gtm_noscript_body', 'info', 'GTM noscript position',
                true, true,
                'GTM noscript position check skipped in lightweight mode. Expected: <noscript><iframe src="…/ns.html?id=' . htmlspecialchars($gtmId, ENT_QUOTES) . '"></iframe></noscript> immediately inside <body>.',
                '', $fixActions
            );
        }

        $html = $this->getHomepageHtml();
        if ($html === null) {
            return $this->make(
                'info_position_gtm_noscript_body', 'info', 'GTM noscript position',
                true, true,
                'GTM is configured but the homepage could not be fetched to verify the <noscript> iframe position.',
                '', $fixActions
            );
        }

        [, $bodyStr] = $this->splitHeadBody($html);
        $needle = '/ns.html?id=' . $gtmId;

        if ($bodyStr === '') {
            return $this->make(
                'info_position_gtm_noscript_body', 'warning', 'GTM noscript position',
                false, false,
                'Could not locate <body>…</body> in the rendered homepage — cannot verify GTM noscript position.',
                '', $fixActions
            );
        }
        if (stripos($bodyStr, $needle) === false) {
            return $this->make(
                'info_position_gtm_noscript_body', 'warning', 'GTM noscript position',
                false, false,
                'GTM noscript iframe was NOT found inside <body>. Google requires <noscript><iframe src="…/ns.html?id=…"></iframe></noscript> immediately after the opening <body> tag — caching, a template override, or an extension may be stripping it.',
                $this->settingsUrl('tab-analytics-btn', 'gtm_container_id'), $fixActions
            );
        }

        return $this->make(
            'info_position_gtm_noscript_body', 'info', 'GTM noscript position',
            true, true,
            'GTM noscript iframe is correctly placed inside <body> (Google spec).',
            '', $fixActions
        );
    }

    /**
     * Verify the Meta Pixel <noscript><img> fallback is injected in <body>
     * (Facebook spec). The base pixel <script> stays in <head>; only the
     * noscript img belongs in body. The aiboost_analytics plugin moved it
     * to onAfterRender body injection in #379.
     */
    private function infoPositionMetaPixelNoscriptBody(): array
    {
        $enabled = !empty($this->settings['enable_meta_pixel']);
        $hasIds  = trim((string) ($this->settings['meta_pixel_id'] ?? '')) !== ''
                || trim((string) ($this->settings['meta_pixel_ids'] ?? '')) !== '';

        $fixActions = [[
            'label' => 'Edit Meta Pixel settings (Analytics tab)',
            'url'   => $this->settingsUrl('tab-analytics-btn', 'meta_pixel_id'),
            'tab'   => 'tab-analytics-btn',
            'field' => 'meta_pixel_id',
        ]];

        if (!$enabled || !$hasIds) {
            return $this->make(
                'info_position_meta_pixel_noscript_body', 'info', 'Meta Pixel noscript position',
                true, false,
                'Meta Pixel is not configured — the <noscript><img> fallback is not being injected.',
                $this->settingsUrl('tab-analytics-btn', 'meta_pixel_id'), $fixActions
            );
        }

        if ($this->isStagingMode() || !$this->isPluginEnabled('aiboost_analytics')) {
            return $this->make(
                'info_position_meta_pixel_noscript_body', 'info', 'Meta Pixel noscript position',
                true, true,
                'Meta Pixel noscript position check skipped — Analytics plugin is disabled or Staging Mode is active.',
                '', $fixActions
            );
        }

        if ($this->skipHttpScan) {
            return $this->make(
                'info_position_meta_pixel_noscript_body', 'info', 'Meta Pixel noscript position',
                true, true,
                'Meta Pixel noscript position check skipped in lightweight mode. Expected: <noscript><img src="https://www.facebook.com/tr?id=…&ev=PageView&noscript=1"></noscript> inside <body>.',
                '', $fixActions
            );
        }

        $html = $this->getHomepageHtml();
        if ($html === null) {
            return $this->make(
                'info_position_meta_pixel_noscript_body', 'info', 'Meta Pixel noscript position',
                true, true,
                'Meta Pixel is configured but the homepage could not be fetched to verify the <noscript> position.',
                '', $fixActions
            );
        }

        [$headStr, $bodyStr] = $this->splitHeadBody($html);
        $needle = 'facebook.com/tr?id=';

        if ($bodyStr === '') {
            return $this->make(
                'info_position_meta_pixel_noscript_body', 'warning', 'Meta Pixel noscript position',
                false, false,
                'Could not locate <body>…</body> in the rendered homepage — cannot verify Meta Pixel noscript position.',
                '', $fixActions
            );
        }

        $inBody = stripos($bodyStr, $needle) !== false;
        $inHead = stripos($headStr, $needle) !== false;

        if ($inBody && !$inHead) {
            return $this->make(
                'info_position_meta_pixel_noscript_body', 'info', 'Meta Pixel noscript position',
                true, true,
                'Meta Pixel <noscript><img> fallback is correctly placed inside <body> (Facebook spec).',
                '', $fixActions
            );
        }
        if ($inHead && !$inBody) {
            return $this->make(
                'info_position_meta_pixel_noscript_body', 'warning', 'Meta Pixel noscript position',
                false, false,
                'Meta Pixel <noscript><img> fallback was found in <head> instead of <body>. Facebook recommends placing the noscript fallback in <body> — clear caches and reinstall the package to pick up the v0.32.16+ position fix (#379).',
                $this->settingsUrl('tab-analytics-btn', 'meta_pixel_id'), $fixActions
            );
        }
        if ($inBody && $inHead) {
            return $this->make(
                'info_position_meta_pixel_noscript_body', 'warning', 'Meta Pixel noscript position',
                false, false,
                'Meta Pixel <noscript><img> fallback was found in BOTH <head> and <body>. A cached page or a second plugin may be emitting a duplicate fallback. Clear caches; if it persists, check the Conflicts panel.',
                $this->settingsUrl('tab-analytics-btn', 'meta_pixel_id'), $fixActions
            );
        }

        return $this->make(
            'info_position_meta_pixel_noscript_body', 'warning', 'Meta Pixel noscript position',
            false, false,
            'Meta Pixel is enabled but the <noscript><img> fallback was NOT found in the live homepage HTML — caching, a template override, or another extension may be stripping it.',
            $this->settingsUrl('tab-analytics-btn', 'meta_pixel_id'), $fixActions
        );
    }

    /**
     * Verify Custom Code "Body" slot lands inside <body> (immediately after
     * the opening <body> tag), not in <head>. aiboost_code injects via
     * onAfterRender regex on `<body>` open.
     */
    private function infoPositionCustomCodeBodySlot(): array
    {
        $masterEnabled = !empty($this->settings['enable_custom_code'])
            && (string) ($this->settings['enable_custom_code'] ?? '0') !== '0';
        $code = trim((string) ($this->settings['custom_code_body'] ?? ''));

        $fixActions = [[
            'label' => 'Edit Custom Code (Code tab)',
            'url'   => $this->settingsUrl('tab-code-btn', 'custom_code_body'),
            'tab'   => 'tab-code-btn',
            'field' => 'custom_code_body',
        ]];

        if (!$masterEnabled) {
            return $this->make(
                'info_position_custom_code_body_slot', 'info', 'Custom Code — Body slot position',
                true, false,
                'Custom Code master toggle is off — nothing is being injected.',
                $this->settingsUrl('tab-code-btn', 'enable_custom_code'), $fixActions
            );
        }

        if ($code === '') {
            return $this->make(
                'info_position_custom_code_body_slot', 'info', 'Custom Code — Body slot position',
                true, false,
                'No custom code in the Body slot — nothing to verify.',
                $this->settingsUrl('tab-code-btn', 'custom_code_body'), $fixActions
            );
        }

        if ($this->isStagingMode() || !$this->isPluginEnabled('aiboost_code')) {
            return $this->make(
                'info_position_custom_code_body_slot', 'info', 'Custom Code — Body slot position',
                true, true,
                'Custom Code Body slot position check skipped — Code Manager plugin is disabled or Staging Mode is active.',
                '', $fixActions
            );
        }

        return $this->positionSlotCheck(
            'info_position_custom_code_body_slot',
            'Custom Code — Body slot position',
            $code,
            'body',
            $fixActions
        );
    }

    /**
     * Verify Custom Code "Footer" slot lands inside <body> (immediately before
     * the closing </body> tag), not in <head>. aiboost_code injects via
     * onAfterRender regex on `</body>` close.
     */
    private function infoPositionCustomCodeFooterSlot(): array
    {
        $masterEnabled = !empty($this->settings['enable_custom_code'])
            && (string) ($this->settings['enable_custom_code'] ?? '0') !== '0';
        $code = trim((string) ($this->settings['custom_code_footer'] ?? ''));

        $fixActions = [[
            'label' => 'Edit Custom Code (Code tab)',
            'url'   => $this->settingsUrl('tab-code-btn', 'custom_code_footer'),
            'tab'   => 'tab-code-btn',
            'field' => 'custom_code_footer',
        ]];

        if (!$masterEnabled) {
            return $this->make(
                'info_position_custom_code_footer_slot', 'info', 'Custom Code — Footer slot position',
                true, false,
                'Custom Code master toggle is off — nothing is being injected.',
                $this->settingsUrl('tab-code-btn', 'enable_custom_code'), $fixActions
            );
        }

        if ($code === '') {
            return $this->make(
                'info_position_custom_code_footer_slot', 'info', 'Custom Code — Footer slot position',
                true, false,
                'No custom code in the Footer slot — nothing to verify.',
                $this->settingsUrl('tab-code-btn', 'custom_code_footer'), $fixActions
            );
        }

        if ($this->isStagingMode() || !$this->isPluginEnabled('aiboost_code')) {
            return $this->make(
                'info_position_custom_code_footer_slot', 'info', 'Custom Code — Footer slot position',
                true, true,
                'Custom Code Footer slot position check skipped — Code Manager plugin is disabled or Staging Mode is active.',
                '', $fixActions
            );
        }

        return $this->positionSlotCheck(
            'info_position_custom_code_footer_slot',
            'Custom Code — Footer slot position',
            $code,
            'footer',
            $fixActions
        );
    }

    /**
     * Shared verification: extract a short distinctive substring (≤80 chars,
     * stripped of HTML whitespace) from the user's custom code and confirm it
     * appears inside <body> (and NOT in <head>) on the live homepage.
     */
    private function positionSlotCheck(string $id, string $label, string $code, string $slot, array $fixActions): array
    {
        if ($this->skipHttpScan) {
            return $this->make(
                $id, 'info', $label,
                true, true,
                'Custom Code ' . $slot . ' slot position check skipped in lightweight mode.',
                '', $fixActions
            );
        }

        $html = $this->getHomepageHtml();
        if ($html === null) {
            return $this->make(
                $id, 'info', $label,
                true, true,
                'Custom Code ' . $slot . ' slot is set but the homepage could not be fetched to verify position.',
                '', $fixActions
            );
        }

        // Build a needle that is stable enough to detect: strip HTML tags +
        // collapse whitespace, take up to 60 chars. If the snippet is too
        // generic, fall back to a longer raw substring.
        $stripped = trim(preg_replace('/\s+/', ' ', strip_tags($code)));
        $needle   = $stripped !== '' ? mb_substr($stripped, 0, 60) : mb_substr($code, 0, 80);

        if (mb_strlen($needle) < 6) {
            return $this->make(
                $id, 'info', $label,
                true, true,
                'Custom Code ' . $slot . ' slot is too short or generic to verify by content match — position check skipped.',
                '', $fixActions
            );
        }

        [$headStr, $bodyStr] = $this->splitHeadBody($html);
        if ($bodyStr === '') {
            return $this->make(
                $id, 'warning', $label,
                false, false,
                'Could not locate <body>…</body> in the rendered homepage — cannot verify Custom Code ' . $slot . ' slot position.',
                '', $fixActions
            );
        }

        $strippedHead = trim(preg_replace('/\s+/', ' ', strip_tags($headStr)));
        $strippedBody = trim(preg_replace('/\s+/', ' ', strip_tags($bodyStr)));

        $inBody = stripos($strippedBody, $needle) !== false || stripos($bodyStr, $needle) !== false;
        $inHead = stripos($strippedHead, $needle) !== false || stripos($headStr, $needle) !== false;

        if ($inBody && !$inHead) {
            return $this->make(
                $id, 'info', $label,
                true, true,
                'Custom Code ' . $slot . ' slot is correctly placed inside <body>.',
                '', $fixActions
            );
        }
        if ($inHead && !$inBody) {
            return $this->make(
                $id, 'warning', $label,
                false, false,
                'Custom Code ' . $slot . ' slot content was detected in <head> instead of <body> — the slot label promises ' . $slot . ' placement. Check that you did not paste head-only markup in the wrong slot.',
                $this->settingsUrl('tab-code-btn', (string) ($fixActions[0]['field'] ?? '')), $fixActions
            );
        }
        if ($inBody && $inHead) {
            return $this->make(
                $id, 'info', $label,
                true, true,
                'Custom Code ' . $slot . ' slot is present in <body> (a similar snippet also appears in <head> — likely your code references a string that also exists in head content).',
                '', $fixActions
            );
        }

        return $this->make(
            $id, 'warning', $label,
            false, false,
            'Custom Code ' . $slot . ' slot is set but its content was NOT found anywhere on the live homepage — the Code Manager plugin may be disabled, the menu scope may exclude the homepage, or caching may be serving an older version.',
            $this->settingsUrl('tab-code-btn', (string) ($fixActions[0]['field'] ?? '')), $fixActions
        );
    }

    private function infoPhpVersion(): array
    {
        $version = PHP_VERSION;
        $major   = (int) PHP_MAJOR_VERSION;
        $minor   = (int) PHP_MINOR_VERSION;
        $pass    = $major === 8 && $minor >= 1;
        $msg     = "PHP {$version}" . ($pass ? ' (supported: 8.1–8.5).' : ' — recommended: PHP 8.1–8.5.');

        return $this->make('info_php_version', 'info', 'PHP Version', $pass, true, $msg, '');
    }

    private function infoJoomlaVersion(): array
    {
        $version = defined('JVERSION') ? (string) JVERSION : 'unknown';
        $major   = (int) $version;
        $pass    = $major >= 5 && $major <= 6;
        $msg     = "Joomla {$version}" . ($pass ? ' (supported: 5–6).' : ' — AI Boost supports Joomla 5–6.');

        return $this->make('info_joomla_version', 'info', 'Joomla Version', $pass, true, $msg, '');
    }

    private function infoLastSaved(): array
    {
        $saved = '';

        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('updated_at'))
                ->from('#__aiboost_settings')
                ->where($this->db->quoteName('setting_key') . '=' . $this->db->quote('main'));
            $at = (string) $this->db->setQuery($query)->loadResult();
            if ($at && $at !== '0000-00-00 00:00:00') {
                $date = new \DateTime($at, new \DateTimeZone('UTC'));
                try {
                    $tz = $this->ctx->getUserTimezone();
                    $date->setTimezone(new \DateTimeZone($tz));
                } catch (\Throwable $e) {
                }
                $saved = $date->format('d M Y H:i');
            }
        } catch (\Throwable $e) {
        }

        $msg = $saved !== '' ? "Settings last saved: {$saved}." : 'Settings have not been saved yet.';

        return $this->make('info_last_saved', 'info', 'Last Settings Save', $saved !== '', true, $msg, '');
    }

    private function infoActivePlugins(): array
    {
        $plugins = [
            'aiboost_schema', 'aiboost_sitemap', 'aiboost_social',
            'aiboost_analytics', 'aiboost_aeo', 'aiboost_core', 'aiboost_code',
        ];
        $active = 0;
        try {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from('#__extensions')
                ->where($this->db->quoteName('type') . '=' . $this->db->quote('plugin'))
                ->where($this->db->quoteName('folder') . '=' . $this->db->quote('system'))
                ->where($this->db->quoteName('element') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $plugins)) . ')')
                ->where($this->db->quoteName('enabled') . '=1');
            $active = (int) $this->db->setQuery($query)->loadResult();
        } catch (\Throwable $e) {
        }
        $total = count($plugins);
        $msg   = "{$active} of {$total} AI Boost plugins are active.";

        return $this->make('info_active_plugins', 'info', 'Active AI Boost Plugins', $active >= 4, true, $msg, '');
    }

    /**
     * Installation integrity audit — confirms every expected AI Boost
     * extension (for the current Free/Pro edition) is installed, enabled, and
     * on the package version, and flags leftover/orphan or version-mismatched
     * extensions. Delegates the classification to InstallIntegrity so the
     * package postflight summary uses the exact same logic.
     */
    private function checkInstallationIntegrity(): array
    {
        $version = class_exists('AiBoost\\Version') ? (string) \AiBoost\Version::VERSION : '';
        $isPro   = InstallIntegrity::isProEdition($this->db);
        $audit   = InstallIntegrity::audit($this->db, $isPro, $version);

        $problems = [];
        if (!empty($audit['missing'])) {
            $problems[] = 'not installed: ' . implode(', ', $audit['missing']);
        }
        if (!empty($audit['disabled'])) {
            $problems[] = 'disabled: ' . implode(', ', $audit['disabled']);
        }
        if (!empty($audit['orphan'])) {
            $problems[] = 'unexpected/leftover: ' . implode(', ', $audit['orphan']);
        }
        if (!empty($audit['mismatch'])) {
            $mm = array_map(
                static fn (array $m): string => $m['element'] . ' (' . ($m['version'] ?: '?') . ' vs ' . ($audit['version'] ?: '?') . ')',
                $audit['mismatch']
            );
            $problems[] = 'version mismatch: ' . implode(', ', $mm);
        }

        if ($problems === []) {
            $msg = sprintf(
                'All %d expected AI Boost extensions (%s edition) are installed, enabled, and on v%s.',
                (int) $audit['active_count'],
                (string) $audit['edition'],
                $audit['version'] ?: '?'
            );
            return $this->make('warning_install_integrity', 'warning', 'Installation Integrity', true, true, $msg, '');
        }

        $msg = sprintf('%s edition — ', (string) $audit['edition']) . implode('. ', $problems) . '.';

        $fixActions = [[
            'label' => 'Open Plugin Manager',
            'url'   => $this->pluginManagerUrl('aiboost_'),
        ]];

        // A *_pro plugin left on a Free install: also offer the Licenses tab so
        // the admin can either license Pro or knows where the Pro UI lives.
        $proOrphan = false;
        foreach ($audit['orphan'] as $el) {
            if (str_ends_with((string) $el, '_pro')) {
                $proOrphan = true;
                break;
            }
        }
        if ($proOrphan) {
            $fixActions[] = [
                'label' => 'Open Licenses',
                'url'   => \Joomla\CMS\Router\Route::_('index.php?option=com_aiboost&view=licenses', false),
            ];
        }

        return $this->make(
            'warning_install_integrity', 'warning', 'Installation Integrity',
            false, false, $msg,
            $fixActions[0]['url'], $fixActions
        );
    }

    private function infoLicenseTier(): array
    {
        $tier = trim((string) ($this->settings['license_tier'] ?? 'free')) ?: 'free';
        $msg  = 'Current license tier: ' . ucfirst($tier) . '.';

        // Task #473 — License UI moved off the General tab to a dedicated
        // /licenses page. Build a direct admin URL to that page so the Fix It
        // deep link doesn't dead-end on a non-existent section.
        $licensesUrl = \Joomla\CMS\Router\Route::_(
            'index.php?option=com_aiboost&view=licenses',
            false
        );
        return $this->make('info_license_tier', 'info', 'License Tier', true, true, $msg, $licensesUrl);
    }

    private function infoSitemapUrlCount(): array
    {
        $count = $this->getSitemapUrlCount();
        $msg   = $count !== null
            ? "Sitemap contains {$count} URL(s)."
            : 'Sitemap not yet generated or not accessible via filesystem.';

        return $this->make('info_sitemap_url_count', 'info', 'Sitemap URL Count', true, true, $msg, '');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Task #428 — Legacy entitlement / Integration architecture checks
    // ─────────────────────────────────────────────────────────────────────────

    /**
    * Informational: legacy tier-based feature locking has been retired.
     */
    private function infoProFeaturesLocked(): array
    {
        $message = 'Legacy tier-based feature locking is disabled; feature settings remain saveable in the one-product admin.';

        return $this->make(
            'info_pro_features_locked', 'info', 'Legacy feature locks',
            true, false, $message,
            'https://aiboostnow.com/pricing'
        );
    }

    /**
    * Info: legacy gating registry is present as a compatibility shim.
     *
    * Smoke-test for regressions when someone removes or renames the registry
    * while old migrations/tests may still reference it.
     */
    private function infoProGatingActive(): array
    {
        $pass = class_exists('AiBoost\\Lib\\ProFeatureRegistry');
        $msg = $pass
            ? 'Legacy feature registry compatibility shim is available; tier-based UI gating is retired.'
            : 'Legacy feature registry compatibility shim is missing.';

        return $this->make(
            'info_pro_gating_active', 'info', 'Legacy feature registry',
            $pass, false, $msg,
            ''
        );
    }

    /**
     * Task #459 — Info: Schema Type enum gating is wired.
     *
     * The Schema Type dropdown (Schema tab → Business/Organization Type) has
     * a Pro subset of values that Free admins cannot save. Server enforcement
     * is in ProFeatureRegistry::stripProOptions(); SPA hint is per-option
     * disabled state. This check confirms the registry has a non-empty Pro
     * subset for schema_type and that the documented Free fallback is one
     * of the *unlocked* values — a missing or self-referential default would
     * silently let Pro values through on Free.
     */
    private function infoSchemaTypeGatingActive(): array
    {
        $pass = false;
        $msg  = 'Schema Type enum gating could not be evaluated.';
        try {
            if (class_exists('AiBoost\\Lib\\ProFeatureRegistry')) {
                $proOptions = \AiBoost\Lib\ProFeatureRegistry::proOptions();
                $defaults   = \AiBoost\Lib\ProFeatureRegistry::proOptionDefaults();
                $proSet     = $proOptions['schema_type'] ?? [];
                $default    = (string) ($defaults['schema_type'] ?? '');

                if (empty($proSet)) {
                    $msg = 'Schema Type Pro subset is EMPTY — every dropdown value is Free.';
                } elseif ($default === '' || in_array($default, $proSet, true)) {
                    $msg = 'Schema Type Free fallback is missing or itself a Pro value (' . $default . ').';
                } else {
                    $pass = true;
                    $msg  = sprintf(
                        'Schema Type enum gating active — %d Pro values, Free fallback "%s".',
                        count($proSet), $default
                    );
                }
            }
        } catch (\Throwable) { /* silent */ }

        return $this->make(
            'info_schema_type_gating_active', 'info', 'Schema Type enum gating',
            $pass, false, $msg,
            $this->settingsUrl('tab-schema-btn', 'schema_type')
        );
    }

    /**
     * Task #454 — Info: Article OG custom fields visibility matches license.
     *
     * Article custom fields (aiboost_og_title/description/image/type/video,
    * aiboost_twitter_card) are maintained by the package installer, which creates
     * them on Pro installs and removes them on Free installs. This check
     * verifies the fields' presence in `#__fields` matches the current
     * license tier — if they drift (e.g. Free site shows them), Fix It
     * routes the admin to reinstall the package so pkg_script reconciles.
     */
    private function infoArticleCustomFieldsPro(): array
    {
        $fieldNames = [
            'aiboost_og_title', 'aiboost_og_description', 'aiboost_og_image',
            'aiboost_og_type', 'aiboost_og_video', 'aiboost_twitter_card',
        ];
        $tier  = strtolower((string) ($this->settings['license_tier'] ?? 'free'));
        $isPro = in_array($tier, ['pro', 'developer', 'agency'], true)
              || (string) ($this->settings['dev_license_preview'] ?? '0') === '1';

        $present = 0;
        try {
            // Aligned with installer ownership rule: only count fields we own
            // (`note LIKE 'aiboost_version:%'`), so a third-party field with a
            // colliding name never trips drift detection.
            $q = $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__fields'))
                ->where($this->db->quoteName('context') . ' = ' . $this->db->quote('com_content.article'))
                ->where($this->db->quoteName('name') . ' IN (' . implode(',', array_map([$this->db, 'quote'], $fieldNames)) . ')')
                ->where($this->db->quoteName('note') . ' LIKE ' . $this->db->quote('aiboost_version:%'));
            $present = (int) $this->db->setQuery($q)->loadResult();

            // Mirror pkg_script::isProInstall() — Pro package physically
            // installed counts as Pro even before the settings tier is
            // persisted, to avoid false drift right after a Pro install.
            if (!$isPro) {
                $proPkg = (int) $this->db->setQuery(
                    $this->db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($this->db->quoteName('#__extensions'))
                        ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('package'))
                        ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('pkg_aiboost_pro'))
                )->loadResult();
                if ($proPkg > 0) {
                    $isPro = true;
                }
            }
        } catch (\Throwable) { /* silent */ }

        if ($isPro) {
            $pass = $present === count($fieldNames);
            $msg  = $pass
                ? sprintf('All %d article OG custom fields are installed.', count($fieldNames))
                : sprintf(
                    'Only %d of %d article OG custom fields are installed. Reinstall the AI Boost package to reconcile.',
                    $present,
                    count($fieldNames)
                );
        } else {
            $pass = $present === 0;
            $msg  = $pass
                ? 'Article OG custom fields are not installed.'
                : sprintf(
                    '%d article OG custom field(s) are present in the article editor.',
                    $present
                );
        }

        return $this->make(
            'info_article_custom_fields_pro', 'info', 'Article OG custom fields',
            $pass, false, $msg,
            $pass ? '' : 'index.php?option=com_installer&view=install'
        );
    }

    /**
     * Critical: a Pro plugin is installed in #__extensions but the system
     * plugin row is disabled, so its features will never apply at runtime.
     */
    private function criticalProPluginDisabled(): array
    {
        $broken = [];
        try {
            if (class_exists('AiBoost\\Lib\\PluginRegistry')) {
                $broken = \AiBoost\Lib\PluginRegistry::installedButDisabledPro();
            }
        } catch (\Throwable) { /* silent */ }

        $pass = empty($broken);
        $msg  = $pass
                        ? 'All installed AI Boost legacy add-on plugins are enabled.'
                        : 'Installed but disabled legacy add-on plugins: ' . implode(', ', $broken)
              . '. Enable them in Joomla Extensions → Plugins.';

        return $this->make(
                        'critical_pro_plugin_disabled', 'critical', 'Legacy add-on plugin disabled in Joomla',
            $pass, false, $msg,
            'index.php?option=com_plugins&filter[folder]=system&filter[search]=aiboost'
        );
    }

    /**
     * Informational: third-party extension (Falang / YOOtheme Pro) is on the
     * site but the matching AI Boost integration plugin is not installed,
     * so bridging features are unavailable.
     */
    private function infoIntegrationDetectedNoBridge(string $integration): array
    {
        $id = 'info_integration_detected_no_bridge_' . $integration;
        $thirdParty = false;
        $bridgeInstalled = false;
        try {
            if (class_exists('AiBoost\\Lib\\PluginRegistry')) {
                $caps = \AiBoost\Lib\PluginRegistry::capabilities();
                $cap  = $caps['int_' . $integration] ?? null;
                $thirdParty      = !empty($cap['detected_third_party']);
                $bridgeInstalled = !empty($cap['installed']) && !empty($cap['enabled']);
            }
        } catch (\Throwable) { /* silent */ }

        $pass = !$thirdParty || $bridgeInstalled;
        $msg  = $pass
            ? ucfirst($integration) . ' bridging is in a healthy state.'
            : ucfirst($integration) . ' is installed on this site but the AI Boost '
              . ucfirst($integration) . ' Integration plugin is not. Install it to enable bridging.';

        // Reuse single registered category id for both integrations
        return [
            'id'                  => $id,
            'status'              => 'info',
            'category'            => self::CATEGORIES['info_integration_detected_no_bridge'] ?? 'Conflicts',
            'label'               => 'AI Boost ' . ucfirst($integration) . ' Integration',
            'pass'                => $pass,
            'show_pass'           => false,
            'message'             => $msg,
            'fix_url'             => 'https://aiboostnow.com/integrations/' . $integration,
            'fix_actions'         => [],
            'contributing_fields' => [],
            'dismissed'           => in_array($id, $this->dismissed, true),
        ];
    }

    /**
     * Warning: another extension on the site is already emitting og: tags;
     * AI Boost should not double-emit. Uses the BridgeDetector signal map
     * already maintained by integration plugins.
     */
    private function warningThirdPartyOgConflict(): array
    {
        $conflicting = $this->detectThirdPartyOgEmitters();
        $pass = empty($conflicting);
        $msg  = $pass
            ? 'No third-party extension is competing with AI Boost OpenGraph output.'
            : 'Third-party OpenGraph emitter(s) detected: ' . implode(', ', $conflicting)
              . '. Disable duplicate OG output in their settings or in AI Boost.';

        return $this->make(
            'warning_third_party_og_conflict', 'warning', 'Third-party OpenGraph conflict',
            $pass, false, $msg,
            'index.php?option=com_aiboost#tab-social-btn'
        );
    }

    /**
     * Warning: another extension is emitting Schema.org JSON-LD already.
     */
    private function warningThirdPartySchemaConflict(): array
    {
        $conflicting = $this->detectThirdPartySchemaEmitters();
        $pass = empty($conflicting);
        $msg  = $pass
            ? 'No third-party extension is competing with AI Boost Schema.org output.'
            : 'Third-party Schema.org emitter(s) detected: ' . implode(', ', $conflicting)
              . '. Consider disabling duplicate Schema output to avoid Google warnings.';

        return $this->make(
            'warning_third_party_schema_conflict', 'warning', 'Third-party Schema.org conflict',
            $pass, false, $msg,
            'index.php?option=com_aiboost#tab-schema-btn'
        );
    }

    /**
    * Info / warning when the legacy dev-only license override map is active.
    * Defensive — surfaces to users if an override is accidentally left on in
    * a production environment.
     */
    private function infoLicenseSimulationActive(): array
    {
        $active = false;
        $debug  = defined('JDEBUG') && JDEBUG === true;
        $skus   = [];

        try {
            if (class_exists('AiBoost\\Lib\\PluginRegistry')) {
                $active = \AiBoost\Lib\PluginRegistry::isSimulationActive();
                if ($active) {
                    $sim = \AiBoost\Lib\PluginRegistry::loadSimulation();
                    foreach ($sim as $k => $v) {
                        if (is_string($v) && $k !== '_domain_override') {
                            $skus[] = $k . '=' . $v;
                        }
                    }
                }
            }
        } catch (\Throwable) { /* silent */ }

                // Pass when the override is off, OR when it's on and JDEBUG is on
        // (developer is expected to know what they're doing in debug mode).
        $pass = !$active || $debug;
        $msg  = $pass
            ? ($active
                                ? 'License test override is active (JDEBUG on) — overrides: ' . implode(', ', $skus)
                                : 'License test override is off.')
                        : 'License test override is replacing real license state OUTSIDE Joomla debug mode. '
              . 'Active overrides: ' . implode(', ', $skus) . '. '
                            . 'Clear the stored override map or enable Joomla debug mode.';

        return $this->make(
            'info_license_simulation_active',
            $pass ? 'info' : 'warning',
            'License test override',
            $pass,
            $active,
            $msg,
            'index.php?option=com_aiboost&view=health'
        );
    }

    /**
     * Warns when the resolved site domain differs from the domain the
     * license is registered to. In real licensing this catches accidental
     * use of a single-site license on a second site (multi-site warning).
     *
    * Today the only producer of a "license-registered domain" is the
    * legacy test override via PluginRegistry::simulatedDomainOverride().
     * Once Lemon Squeezy enforcement lands (Task #108), the same check
     * will compare against the real registered domain too.
     */
    private function warningLicenseDomainMismatch(): array
    {
        $override = '';
        try {
            if (class_exists('AiBoost\\Lib\\PluginRegistry')) {
                $override = \AiBoost\Lib\PluginRegistry::simulatedDomainOverride();
            }
        } catch (\Throwable) { /* silent */ }

        // No override -> nothing to compare yet, pass cleanly.
        if ($override === '') {
            return $this->make(
                'warning_license_domain_mismatch',
                'warning',
                'License domain matches site',
                true,
                false,
                'No license-registered domain override is set; site domain check is OK.',
                'index.php?option=com_aiboost&view=health'
            );
        }

        $siteUrl = '';
        try {
            $detector = new DomainDetectionService($this->ctx);
            $siteUrl  = $detector->getBaseUrl($this->settings);
        } catch (\Throwable) { /* silent */ }

        $normalize = static function (string $url): string {
            $host = parse_url($url, PHP_URL_HOST);
            return is_string($host) ? strtolower($host) : strtolower(trim($url));
        };
        $simHost  = $normalize($override);
        $siteHost = $normalize($siteUrl);

        $matches = $simHost !== '' && $siteHost !== '' && $simHost === $siteHost;
        $pass    = $matches;
        $msg     = $matches
            ? 'License-registered domain matches the current site (' . $siteHost . ').'
            : 'License is registered to "' . $simHost . '" but this site is "' . ($siteHost ?: 'unknown') . '". '
              . 'Update the license site, move the license, or clear the test override.';

        return $this->make(
            'warning_license_domain_mismatch',
            'warning',
            'License domain mismatch',
            $pass,
            !$pass,
            $msg,
            'index.php?option=com_aiboost&view=health'
        );
    }

    /**
    * Warn when the legacy Pro package is physically installed but no license
    * has been activated for updates/support tracking.
     */
    private function warningProInstallNoLicense(): array
    {
        $proInstalled = false;
        $activated    = (string) ($this->settings['pro_activated'] ?? '0') === '1';
        try {
            $proInstalled = (int) $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($this->db->quoteName('#__extensions'))
                    ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('package'))
                    ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('pkg_aiboost_pro'))
            )->loadResult() > 0;
        } catch (\Throwable) { /* silent */ }

        $pass = !$proInstalled || $activated;
        $msg = $pass
            ? ($proInstalled
                ? 'AI Boost legacy add-on package is installed and license activation is recorded.'
                : 'AI Boost legacy add-on package is not installed.')
                        : 'AI Boost legacy Pro package is installed but no activation is recorded. '
                            . 'Enter your licence key on the Licenses tab for updates/support tracking.';

        return [
            'id'                  => 'warning_pro_install_no_license',
            'status'              => 'warning',
            'category'            => self::CATEGORIES['warning_pro_install_no_license'],
            'label'               => 'Pro install not activated',
            'pass'                => $pass,
            'show_pass'           => false,
            'message'             => $msg,
            // The Licenses page lives in the SPA shell (view=app), not in the
            // Settings form, so the fix action must carry an explicit SPA url —
            // otherwise HealthApp falls back to a dead href="#".
            'fix_url'             => $this->appUrl('licenses'),
            'fix_actions'         => $pass ? [] : [
                [
                    'label'        => 'Open Licenses tab',
                    'url'          => $this->appUrl('licenses'),
                    'target_tab'   => 'licenses',
                    'target_field' => 'license_key',
                ],
            ],
            'contributing_fields' => [],
            'dismissed'           => in_array('warning_pro_install_no_license', $this->dismissed, true),
        ];
    }

    /**
     * @return array<int,string>
     */
    private function detectThirdPartyOgEmitters(): array
    {
        $found = [];
        try {
            if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
                return $found;
            }
            if (\AiBoost\Lib\BridgeDetector::isInstalled('com_yootheme')
                || \AiBoost\Lib\BridgeDetector::isInstalled('yootheme')) {
                $found[] = 'YOOtheme Pro';
            }
            if (\AiBoost\Lib\BridgeDetector::isInstalled('sp_page_builder')
                || \AiBoost\Lib\BridgeDetector::isInstalled('com_sppagebuilder')) {
                $found[] = 'SP Page Builder';
            }
        } catch (\Throwable) { /* silent */ }
        return $found;
    }

    /**
     * @return array<int,string>
     */
    private function detectThirdPartySchemaEmitters(): array
    {
        $found = [];
        try {
            if (!class_exists('AiBoost\\Lib\\BridgeDetector')) {
                return $found;
            }
            if (\AiBoost\Lib\BridgeDetector::isInstalled('osmap')
                || \AiBoost\Lib\BridgeDetector::isInstalled('com_osmap')) {
                $found[] = 'OSMap';
            }
            if (\AiBoost\Lib\BridgeDetector::isInstalled('jce')
                || \AiBoost\Lib\BridgeDetector::isInstalled('com_jce')) {
                // JCE itself does not emit JSON-LD; only flag if a JCE schema
                // add-on is present. Left as a hook for the next integration.
            }
        } catch (\Throwable) { /* silent */ }
        return $found;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SCORE CALCULATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compute the Site Health score shown in the animated circle on the
     * Health tab.
     *
     * Formula (Task #485; reworked v0.79.x — item 13):
     *   score = round(100 × earnedWeight ÷ totalWeight)
     *   where each scoring check weighs CRITICAL_PENALTY (15) when critical or
     *   WARNING_PENALTY (5) when a warning, and earns its weight when it passes.
     *   The score is therefore the share of weighted checks that pass — partial
     *   success is always reflected and it only reaches 0 when EVERY scoring
     *   check fails (the old "100 − penalties" model bottomed out at 0 even when
     *   many checks passed). No scoring checks at all ⇒ 100.
     *
     * What is EXCLUDED from the score (and why):
     *   • status = 'info'           — informational, never a fail.
     *   • dismissed checks          — admin opted out.
     *   • category = 'Conflicts'    — duplicate / third-party-conflict
     *     findings are surfaced separately as the "Conflicts: N" pill and
     *     listed under their own Conflicts card. They are NOT counted into
     *     the headline "Critical: X/Y OK" or "Warnings: X/Y OK" pills in
     *     the UI, so they must not be counted in the score either —
     *     otherwise a Pro install with several detected duplicates would
     *     score 0 even though every AI Boost check passed (the original
     *     Pro=0 bug). Conflicts are a separate dimension of site health
     *     reported on their own pill.
     *
     * Keep this comment and the user-facing tooltip in HealthApp.vue in
     * lockstep — they document the same formula in two places.
     */
    private function calculateScore(array $checks): int
    {
        // Weighted-proportional: each scoring check contributes its weight
        // (critical = CRITICAL_PENALTY, warning = WARNING_PENALTY) to the total
        // and earns that weight when it passes. The score is the percentage of
        // weighted checks that pass, so partial success is always reflected and
        // the score only reaches 0 when EVERY scoring check fails — fixing the
        // old "100 − penalties" model that bottomed out at 0 even when many
        // checks passed (item 13).
        $totalWeight  = 0;
        $earnedWeight = 0;
        foreach ($checks as $check) {
            if ($check['status'] === 'info') {
                continue;
            }
            if (($check['category'] ?? '') === 'Conflicts') {
                continue;
            }
            if (in_array($check['id'], $this->dismissed, true)) {
                continue;
            }
            $weight       = $check['status'] === 'critical' ? self::CRITICAL_PENALTY : self::WARNING_PENALTY;
            $totalWeight += $weight;
            if (!empty($check['pass'])) {
                $earnedWeight += $weight;
            }
        }
        if ($totalWeight === 0) {
            return 100;
        }
        return (int) round(100 * $earnedWeight / $totalWeight);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a single check result array.
     * `category` is automatically resolved from CATEGORIES map.
     */
    /**
     * Task #462 — Auto-register Health entries from manifest `health` blocks.
     *
     * Reads `Manifest\Registry::all()` and, for every field that ships with
     * a `health` block AND is currently enabled in $this->settings, emits a
     * pass-info check confirming the option's expected_artifact should be
     * present. When the option is disabled, no check is emitted (silent).
     *
     * This removes the requirement to hand-write a private function and a
     * CATEGORIES row for every new manifest option — the manifest itself is
     * now the single source of truth.
     *
     * @return list<array>
     */
    private function registerFromManifest(): array
    {
        $checks = [];
        try {
            if (!class_exists(\AiBoost\Lib\Manifest\Registry::class)) {
                return [];
            }
            $fields = \AiBoost\Lib\Manifest\Registry::all();
        } catch (\Throwable $e) {
            return [];
        }

        foreach ($fields as $f) {
            $h = $f['health'] ?? null;
            if (!is_array($h) || empty($h['id'])) {
                continue;
            }

            $key   = (string) ($f['key'] ?? '');
            $value = $this->settings[$key] ?? '';
            $on    = ($value === '1' || $value === 1 || $value === true);
            if (!$on) {
                continue;
            }

            $id          = (string) $h['id'];
            $category    = (string) ($h['category'] ?? 'General');
            $message     = (string) ($h['message'] ?? '');
            $expected    = (string) ($h['expected_artifact'] ?? '');
            $fixActions  = is_array($h['fix_actions'] ?? null) ? $h['fix_actions'] : [];

            // Task #469 — defer to a codegen-stubbed override class
            // (component/lib/src/Manifest/Health/{StudlyHealthId}.php)
            // when one exists, so developers can replace the always-pass
            // default with real probing logic.
            $pass   = true;
            $msg    = $message;
            if ($expected !== '') {
                $msg .= ' Expected: ' . $expected . '.';
            }

            $studly       = '';
            foreach (preg_split('/[^A-Za-z0-9]+/', $id) ?: [] as $part) {
                if ($part !== '') {
                    $studly .= ucfirst($part);
                }
            }
            $overrideCls = '\\AiBoost\\Lib\\Manifest\\Health\\' . $studly;
            try {
                if ($studly !== '' && class_exists($overrideCls) && method_exists($overrideCls, 'evaluate')) {
                    $r = $overrideCls::evaluate($this->settings, $this->ctx);
                    if (is_array($r)) {
                        if (array_key_exists('pass', $r)) {
                            $pass = (bool) $r['pass'];
                        }
                        if (array_key_exists('message', $r) && is_string($r['message'])) {
                            $msg = $r['message'];
                        }
                        if (array_key_exists('fix_actions', $r) && is_array($r['fix_actions'])) {
                            $fixActions = $r['fix_actions'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Override failed to load or threw — fall back to declarative default.
            }

            $row = [
                'id'                  => $id,
                'status'              => str_starts_with($id, 'critical_')
                                          ? 'critical'
                                          : (str_starts_with($id, 'warning_') ? 'warning' : 'info'),
                'category'            => $category,
                'label'               => (string) ($f['label'] ?? $key),
                'pass'                => $pass,
                'show_pass'           => true,
                'message'             => $msg,
                'fix_url'             => $this->settingsUrl((string) ($f['tab'] ?? ''), $key),
                'fix_actions'         => $fixActions,
                'contributing_fields' => [$key],
                'dismissed'           => in_array($id, $this->dismissed, true),
            ];
            $checks[] = $row;
        }
        return $checks;
    }

    /**
     * Task #486 — Info row listing every bridge plugin that registered via
     * the Open Integration SDK. Always passes; the value is the count.
     */
    private function infoIntegrationBridgesInstalled(): array
    {
        $bridges = [];
        try {
            if (class_exists(\AiBoost\Lib\Integration\IntegrationRegistry::class)) {
                foreach (\AiBoost\Lib\Integration\IntegrationRegistry::all() as $key => $desc) {
                    $bridges[] = sprintf('%s (SDK v%d)', $desc->label !== '' ? $desc->label : $key, $desc->sdkVersion);
                }
            }
        } catch (\Throwable) { /* silent */ }

        $count   = count($bridges);
        $message = $count === 0
            ? 'No third-party AI Boost integration bridges are currently registered.'
            : sprintf('%d integration bridge(s) registered: %s.', $count, implode(', ', $bridges));

        return $this->make(
            'info_integration_bridges_installed',
            'info',
            'Installed integration bridges',
            true,
            true,
            $message,
            $this->appUrl('integrations'),
            [['label' => 'Open Integrations tab', 'url' => $this->appUrl('integrations'), 'target_tab' => 'integrations', 'target_field' => '']],
            []
        );
    }

    /**
     * Surface integrations whose host is installed but that the admin has
     * switched OFF on the Integrations page (status 'paused'). Being off is a
     * valid choice, so this is informational and only shown when something is
     * actually paused — it stops a user wondering why a detected integration
     * produces no output.
     */
    private function infoIntegrationMasterToggle(): array
    {
        $paused = [];
        try {
            if (class_exists(\AiBoost\Lib\IntegrationDetectorService::class)) {
                foreach ((new \AiBoost\Lib\IntegrationDetectorService($this->db))->detect() as $tile) {
                    if (($tile['status'] ?? '') === 'paused') {
                        $paused[] = (string) ($tile['name'] ?? $tile['key'] ?? '');
                    }
                }
            }
        } catch (\Throwable) { /* silent */ }

        $pass = $paused === [];
        if ($pass) {
            $message = 'All detected integrations are switched on.';
        } elseif (count($paused) === 1) {
            $message = sprintf(
                '%s is switched off on the Integrations page, so AI Boost is not adding its '
                . 'enhancements for it. Turn it back on if that was not intended.',
                $paused[0]
            );
        } else {
            $message = sprintf(
                '%s are switched off on the Integrations page, so AI Boost is not adding their '
                . 'enhancements. Turn them back on if that was not intended.',
                implode(', ', $paused)
            );
        }

        return $this->make(
            'info_integration_master_toggle',
            'info',
            'Integration switches',
            true,
            !$pass,
            $message,
            $this->appUrl('integrations'),
            [['label' => 'Open Integrations page', 'url' => $this->appUrl('integrations'), 'target_tab' => 'integrations', 'target_field' => '']],
            []
        );
    }

    /**
     * Task #486 — Warn when a bridge declares an SDK version core cannot
     * support. The bridge appears in IntegrationRegistry::getSdkMismatches()
     * and is silently excluded from active() until the bridge or core is
     * upgraded.
     */
    private function warningBridgeSdkMismatch(): array
    {
        $mismatches = [];
        try {
            if (class_exists(\AiBoost\Lib\Integration\IntegrationRegistry::class)) {
                $mismatches = \AiBoost\Lib\Integration\IntegrationRegistry::getSdkMismatches();
            }
        } catch (\Throwable) { /* silent */ }

        $pass = empty($mismatches);
        if ($pass) {
            $msg = 'All registered bridges report an SDK version this core release can talk to.';
        } else {
            $details = [];
            foreach ($mismatches as $m) {
                $details[] = $m['reason'] ?? '';
            }
            $msg = 'One or more integration bridges declare an unsupported SDK version: '
                . implode(' | ', array_filter($details))
                . ' Expected: the bridge to be hidden from the dashboard until it is updated.';
        }

        return $this->make(
            'warning_bridge_sdk_mismatch',
            'warning',
            'Integration SDK compatibility',
            $pass,
            true,
            $msg,
            $this->appUrl('integrations'),
            [['label' => 'Open Integrations tab', 'url' => $this->appUrl('integrations'), 'target_tab' => 'integrations', 'target_field' => '']],
            []
        );
    }

    /**
     * Task #486 — Warn when two bridges try to claim the same ConflictManager
     * slot. The first claim wins; the rejected bridge silently skips its
     * injection. Surfaces "owner ↔ rejected" pairs so the admin can decide
     * which bridge to disable.
     */
    private function warningBridgeSlotCollision(): array
    {
        $collisions = [];
        try {
            if (class_exists(\AiBoost\Lib\ConflictManager::class)) {
                $collisions = \AiBoost\Lib\ConflictManager::collisions();
            }
        } catch (\Throwable) { /* silent */ }

        $pass = empty($collisions);
        if ($pass) {
            $msg = 'No integration bridges are competing for the same ConflictManager slot.';
        } else {
            $parts = [];
            foreach ($collisions as $c) {
                $parts[] = sprintf(
                    '"%s" owned by "%s", rejected: %s',
                    $c['slot'],
                    $c['owner'],
                    implode(', ', $c['rejected'])
                );
            }
            $msg = 'Slot collision(s) detected: ' . implode(' | ', $parts)
                . '. Expected: only one plugin emits each AI Boost output slot per request.';
        }

        return $this->make(
            'warning_bridge_slot_collision',
            'warning',
            'Integration slot collisions',
            $pass,
            true,
            $msg,
            $this->appUrl('integrations'),
            [['label' => 'Open Integrations tab', 'url' => $this->appUrl('integrations'), 'target_tab' => 'integrations', 'target_field' => '']],
            []
        );
    }

    private function make(
        string $id,
        string $status,
        string $label,
        bool   $pass,
        bool   $showPass,
        string $message,
        string $fixUrl = '',
        array  $fixActions = [],
        array  $contributingFields = []
    ): array {
        return [
            'id'                  => $id,
            'status'              => $status,
            'category'            => self::CATEGORIES[$id] ?? 'General',
            'label'               => $label,
            'pass'                => $pass,
            'show_pass'           => $showPass,
            'message'             => $message,
            'fix_url'             => $fixUrl,
            'fix_actions'         => $fixActions,
            'contributing_fields' => $contributingFields,
            'dismissed'           => in_array($id, $this->dismissed, true),
        ];
    }

    private function isPluginEnabled(string $element): bool
    {
        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('enabled'))
                ->from('#__extensions')
                ->where($this->db->quoteName('type') . '=' . $this->db->quote('plugin'))
                ->where($this->db->quoteName('folder') . '=' . $this->db->quote('system'))
                ->where($this->db->quoteName('element') . '=' . $this->db->quote($element));
            $row = $this->db->setQuery($query)->loadResult();
            return $row !== null && (int) $row === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Returns true when the site is in staging/development mode.
     * Checks the AI Boost staging_mode flag or CMS global debug setting.
     */
    private function isStagingMode(): bool
    {
        if ((string) ($this->settings['staging_mode'] ?? '0') === '1') {
            return true;
        }
        try {
            return (bool) $this->ctx->getConfigValue('debug', '0');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Perform an HTTP HEAD request and return the status code.
     * Returns null when the request cannot be completed (timeout, invalid URL, etc.).
     * Timeout: 3 seconds.
     */
    private function fetchHeadStatus(string $url): ?int
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        try {
            $ctx     = stream_context_create([
                'http' => [
                    'method'          => 'HEAD',
                    'timeout'         => 3.0,
                    'follow_location' => 1,
                    'ignore_errors'   => true,
                    'user_agent'      => 'AI Boost Health Checker/1.0',
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $headers = @get_headers($url, false, $ctx);
            if (!is_array($headers) || empty($headers[0])) {
                return null;
            }
            if (preg_match('#HTTP/\d+\.?\d*\s+(\d+)#', (string) $headers[0], $m)) {
                return (int) $m[1];
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if robots.txt has a blanket Disallow for User-agent: *.
     * Parses by sections — only the wildcard User-agent block is evaluated.
     */
    private function robotsBlocksAll(string $content): bool
    {
        $lines      = preg_split('/\r?\n/', $content);
        $inWildcard = false;

        foreach ($lines as $raw) {
            $line = trim($raw);
            if ($line === '' || str_starts_with($line, '#')) {
                $inWildcard = false;
                continue;
            }
            if (stripos($line, 'User-agent:') === 0) {
                $inWildcard = trim(substr($line, 11)) === '*';
                continue;
            }
            if ($inWildcard && stripos($line, 'Disallow:') === 0) {
                if (trim(substr($line, 9)) === '/') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Count <url> entries in sitemap.xml (reads filesystem, not HTTP).
     * Returns null if sitemap file is missing or unreadable.
     */
    private function getSitemapUrlCount(): ?int
    {
        $file = AdapterRegistry::filesystem()->sitePath('sitemap.xml');
        if (!file_exists($file)) {
            return null;
        }
        try {
            $content = (string) @file_get_contents($file);
            return empty($content) ? null : substr_count($content, '<url>');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build a deep-link URL to the Settings page, optionally targeting a
     * specific tab and field. The Vue SettingsApp (App.vue) reads the
     * `tab` and `field` query params on mount, activates the matching tab,
     * then smooth-scrolls to and briefly highlights the element tagged with
     * `data-ab-field="<field>"`.
     *
     * Backward compatibility: callers may still pass the legacy
     * `tab-<id>-btn` hash form (e.g. `tab-sitemap-btn`) as the first arg —
     * the helper transparently extracts the tab id.
     *
     * @param string $tab   Tab id (e.g. "sitemap") or legacy "tab-sitemap-btn"
     * @param string $field Optional settings field key (matches data-ab-field)
     */
    private function settingsUrl(string $tab = '', string $field = ''): string
    {
        $base = 'index.php?option=com_aiboost&view=settings';

        // Normalise legacy "tab-<id>-btn" hash form → bare tab id.
        if ($tab !== '' && preg_match('/^tab-([a-z0-9_]+)-btn$/i', $tab, $m)) {
            $tab = $m[1];
        }

        $params = [];
        if ($tab !== '') {
            $params[] = 'tab=' . rawurlencode($tab);
        }
        if ($field !== '') {
            $params[] = 'field=' . rawurlencode($field);
        }

        return $params ? $base . '&' . implode('&', $params) : $base;
    }

    private function pluginManagerUrl(string $element): string
    {
        return 'index.php?option=com_plugins&filter[folder]=system&filter[search]='
            . urlencode($element);
    }

    /**
     * Deep link to a page inside the Vue admin SPA shell (view=app).
     *
     * Used by fix actions whose destination is NOT a Settings form tab —
     * e.g. the Licenses or Integrations page — so they always carry an
     * explicit url and never render as a dead href="#" button in HealthApp.
     */
    private function appUrl(string $route): string
    {
        return 'index.php?option=com_aiboost&view=app#/' . ltrim($route, '/');
    }

    /**
     * Normalise an image path stored by Joomla's native type="media" form field.
     *
     * Joomla stores the value as JSON: {"imagefile":"local-images:\/\/images\/photo.jpg","alt":""}
     * This method extracts the plain relative path so health checks correctly detect
     * whether an image has been configured.
     *
     * @param string $raw Raw value from DB settings.
     * @return string     Root-relative path (e.g. "images/photo.jpg") or empty string.
     */
    private static function normaliseImagePath(string $raw): string
    {
        $path = trim($raw);
        if ($path === '') {
            return '';
        }

        // JSON media-field format: {"imagefile":"...","alt":""}
        if (str_starts_with($path, '{')) {
            try {
                $decoded = json_decode($path, true, 4, JSON_THROW_ON_ERROR);
                $path = trim((string) ($decoded['imagefile'] ?? ''));
            } catch (\JsonException) {
                return '';
            }
        }

        if ($path === '') {
            return '';
        }

        // Strip Joomla URI scheme prefixes.
        if (str_starts_with($path, 'joomlaImage://local-images:')) {
            $path = substr($path, strlen('joomlaImage://local-images:'));
        }
        if (str_starts_with($path, 'local-images://')) {
            $path = substr($path, strlen('local-images://'));
        }

        return ltrim(preg_replace('#//+#', '/', $path) ?? '', '/');
    }
}
