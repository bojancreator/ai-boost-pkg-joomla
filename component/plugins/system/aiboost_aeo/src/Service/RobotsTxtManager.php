<?php
/**
 * AI Boost — RobotsTxtManager (Free baseline)
 *
 * Free-tier /robots.txt builder. Reads the site's existing on-disk
 * robots.txt (if any), then appends the AI Boost managed section:
 *
 *   - Joomla system path disallows
 *   - Sitemap reference
 *   - Environment detection: staging/dev/local → Disallow: / for all bots
 *   - Free per-bot SEO scraper blocklist (`scraper_*` toggles)
 *   - Custom free-form rules (appended verbatim)
 *
 * Pro per-bot crawler rules (BOT_DEFINITIONS) live in aiboost_aeo_pro and
 * decorate this output via `EVENT_FILTER_ROBOTS_RULES`. Integration
 * bridges (e.g. JoomSEF) also use that event.
 *
 * Settings are read from #__aiboost_settings via the Extension class —
 * this service class receives a plain array, no Registry dependency. The
 * AppContextInterface is injected so this service makes no Uri:: or
 * Factory:: calls.
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Service;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Integration\FilterDispatcher;
use AiBoost\Lib\Integration\Sdk;

class RobotsTxtManager
{
    /**
     * Per-bot SEO scraper definitions (Free tier).
     *
     * Each scraper_* key in #__aiboost_settings is an independent on/off
     * toggle bound to the AEO tab UI. Pro per-bot AI/SEO crawler rules
     * (BOT_DEFINITIONS) live in aiboost_aeo_pro.
     *
     * @var array<string, string>  setting key => User-agent
     */
    public const SCRAPER_DEFINITIONS = [
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

    /** Domain substrings that indicate a non-production environment. */
    private const STAGING_KEYWORDS = [
        'staging', 'stage', 'dev.', '.dev', 'test.', '.test',
        'localhost', '127.0.0.1', '.local',
    ];

    /** @var array<string,mixed> */
    private array $settings;
    private string $baseUrl;

    /** @param array<string,mixed> $settings */
    public function __construct(
        array $settings,
        private readonly AppContextInterface $ctx,
    ) {
        $this->settings = $settings;
        $this->baseUrl  = $ctx->getBaseUrl();
    }

    /**
     * Generate the full merged robots.txt output.
     *
     * 1. Read existing site-root robots.txt (if any).
     * 2. Build the Free baseline managed section.
     * 3. Fire EVENT_FILTER_ROBOTS_RULES so aiboost_aeo_pro can append
     *    per-bot AI/SEO crawler rules and integration bridges can add
     *    spider blocks (e.g. JoomSEF virtual URL patterns).
     */
    public function generate(): string
    {
        $existing = $this->readExistingRobotsTxt();
        $managed  = $this->buildManagedSection();

        if (class_exists(FilterDispatcher::class)) {
            $filtered = FilterDispatcher::dispatch(
                Sdk::EVENT_FILTER_ROBOTS_RULES,
                ['rules' => $managed, 'existing' => $existing, 'settings' => $this->settings]
            );
            if (isset($filtered['rules']) && is_string($filtered['rules']) && $filtered['rules'] !== '') {
                $managed = $filtered['rules'];
            }
        }

        if ($existing !== '') {
            return rtrim($existing) . "\n\n" . $managed;
        }

        return $managed;
    }

    /**
     * Output served when another plugin has already claimed the robots_txt slot.
     */
    public function generateConflictNotice(string $owner): string
    {
        $existing = $this->readExistingRobotsTxt();
        $notice   = "\n# [AI Boost AEO] Conflict detected: robots.txt is managed by '{$owner}'.\n"
                  . "# AI Boost AEO robots.txt rules are skipped to avoid duplicate output.\n";

        if ($existing !== '') {
            return rtrim($existing) . $notice;
        }

        return "User-agent: *\nAllow: /\n" . $notice;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Detect whether the current host is a staging / development environment.
     */
    private function isStaging(): bool
    {
        $currentUrl = $this->ctx->getCurrentUrl();
        $host       = strtolower(
            (string) (parse_url($currentUrl, PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? ''))
        );

        foreach (self::STAGING_KEYWORDS as $kw) {
            if (str_contains($host, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the physical robots.txt from the Joomla site root.
     * Strips any previously-injected AI Boost section to avoid duplication.
     */
    private function readExistingRobotsTxt(): string
    {
        $path = defined('JPATH_ROOT') ? JPATH_ROOT . '/robots.txt' : '';
        if ($path && is_file($path) && is_readable($path)) {
            $content = (string) file_get_contents($path);
            $marker  = '# [AI Boost AEO — managed section]';
            $pos     = strpos($content, $marker);
            if ($pos !== false) {
                $content = substr($content, 0, $pos);
            }
            return rtrim($content);
        }

        return '';
    }

    /**
     * Build the Free AI Boost managed robots.txt section.
     */
    private function buildManagedSection(): string
    {
        $lines   = [];
        $lines[] = '# [AI Boost AEO — managed section]';
        $lines[] = '# Generated by AI Boost for Joomla (aiboostnow.com)';
        $lines[] = '# ' . gmdate('Y-m-d H:i:s') . ' UTC';
        $lines[] = '';

        if ($this->isStaging()) {
            $lines[] = '# !! STAGING / DEVELOPMENT ENVIRONMENT — all crawlers blocked !!';
            $lines[] = 'User-agent: *';
            $lines[] = 'Disallow: /';
            $lines[] = '';
            $lines[] = '# To allow crawling, move this site to a production domain.';
            return implode("\n", $lines);
        }

        // Standard Joomla system path disallows
        $lines[] = 'User-agent: *';
        $lines[] = 'Allow: /';
        $lines[] = '';
        $lines[] = '# Joomla system paths';
        $lines[] = 'Disallow: /administrator/';
        $lines[] = 'Disallow: /api/';
        $lines[] = 'Disallow: /bin/';
        $lines[] = 'Disallow: /cache/';
        $lines[] = 'Disallow: /cli/';
        $lines[] = 'Disallow: /components/';
        $lines[] = 'Disallow: /includes/';
        $lines[] = 'Disallow: /installation/';
        $lines[] = 'Disallow: /language/';
        $lines[] = 'Disallow: /layouts/';
        $lines[] = 'Disallow: /libraries/';
        $lines[] = 'Disallow: /logs/';
        $lines[] = 'Disallow: /modules/';
        $lines[] = 'Disallow: /plugins/';
        $lines[] = 'Disallow: /tmp/';
        $lines[] = '';
        $lines[] = '# Allow public assets';
        $lines[] = 'Allow: /templates/';
        $lines[] = 'Allow: /media/';
        $lines[] = 'Allow: /images/';
        $lines[] = '';

        // Sitemap reference
        $lines[] = '# Sitemap';
        $lines[] = 'Sitemap: ' . $this->baseUrl . '/sitemap.xml';
        $lines[] = '';

        // Free: per-bot SEO scraper blocklist (canonical scraper_* keys)
        $blockedScrapers = [];
        foreach (self::SCRAPER_DEFINITIONS as $key => $ua) {
            if ((string) ($this->settings[$key] ?? '0') === '1') {
                $blockedScrapers[] = $ua;
            }
        }
        if (!empty($blockedScrapers)) {
            $lines[] = '# Scraper bots (AI Boost — blocked)';
            foreach ($blockedScrapers as $ua) {
                $lines[] = 'User-agent: ' . $ua;
                $lines[] = 'Disallow: /';
                $lines[] = '';
            }
        }

        // Optional free-form scraper rules (appended verbatim)
        $customScrapers = trim((string) ($this->settings['robots_custom_scrapers'] ?? ''));
        if ($customScrapers !== '') {
            $lines[] = '# Custom scraper rules';
            $lines[] = $customScrapers;
            $lines[] = '';
        }

        // Custom free-form additions
        $custom = trim((string) ($this->settings['robots_custom_rules'] ?? ''));
        if ($custom !== '') {
            $lines[] = '# Custom rules';
            $lines[] = $custom;
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
