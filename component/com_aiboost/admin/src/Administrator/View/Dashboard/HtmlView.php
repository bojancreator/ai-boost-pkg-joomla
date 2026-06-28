<?php
/**
 * @package     AiBoost\Component\AiBoost\Administrator\View\Dashboard
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use AiBoost\Lib\ConflictDetector;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Lib\LanguageService;
use AiBoost\Lib\NotificationService;
use AiBoost\Lib\PluginRegistry;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string  $version            = '';
    public array   $pluginStatus       = [];
    public bool    $hasSettings        = false;
    public ?string $lastSaved          = null;
    /** Monotonic counter bumped per changed field on each Settings save (Task #497). */
    public int     $changeCounter      = 0;
    public array   $top404             = [];
    public int     $total404           = 0;
    public int     $redirectCount      = 0;
    public array   $conflicts          = [];
    /** Curated "headline" notifications for the Dashboard panel. */
    public array   $notifications      = [];
    /** Last version the admin has seen the "What's New" highlight for. */
    public string  $lastSeenVersion    = '';
    /** True when Joomla Multilanguage is active and >1 published language. */
    public bool    $multilingualActive = false;
    /** Number of published content languages (0 when multilingual not active). */
    public int     $multilingualLangCount = 0;
    /** Legacy translation row count — kept for template back-compat. */
    public int     $multilingualCount  = 0;

    public function display($tpl = null): void
    {
        $this->version            = Version::VERSION;
        $this->pluginStatus       = $this->getPluginStatus();
        $this->hasSettings        = $this->checkHasSettings();
        $this->lastSaved          = $this->getLastSaved();
        $this->changeCounter      = (int) ($this->loadSettings()['change_counter'] ?? 0);
        $this->top404             = $this->getTop404(20);
        $this->total404           = $this->count404();
        $this->redirectCount      = $this->countRedirects();
        $this->conflicts          = $this->getConflicts();

        // ── Multilingual status via LanguageService ──────────────────────
        try {
            $langService                 = new LanguageService(new JoomlaAppContext(), Factory::getDbo());
            $this->multilingualActive    = $langService->isMultilingual();
            $this->multilingualLangCount = $this->multilingualActive
                ? count($langService->getPublishedLanguages())
                : 0;
        } catch (\Throwable $e) {
            $this->multilingualActive    = false;
            $this->multilingualLangCount = 0;
        }
        $this->multilingualCount = $this->countTranslations();
        $this->notifications     = $this->getNotifications();
        $this->lastSeenVersion   = (string) ($this->loadSettings()['last_seen_version'] ?? '');
        $this->addToolbar();

        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost <small>v' . Version::VERSION . '</small>', 'bolt');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONFLICT DETECTION
    // ─────────────────────────────────────────────────────────────────────────

    private function getConflicts(): array
    {
        try {
            $settings  = $this->loadSettings();
            $dismissed = json_decode((string) ($settings['dismissed_checks'] ?? '[]'), true);
            $dismissed = is_array($dismissed) ? $dismissed : [];
            $detector  = new ConflictDetector(Factory::getDbo(), $settings, $dismissed);
            return $detector->scan();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function loadSettings(): array
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $json  = (string) $db->setQuery($query)->loadResult();
            if (empty($json)) {
                return [];
            }
            $decoded = json_decode($json, true);
            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NOTIFICATIONS — curated "headline" subset of the Health/Conflict signals,
    // assembled from cheap data already loaded above (see NotificationService).
    // ─────────────────────────────────────────────────────────────────────────

    private function getNotifications(): array
    {
        try {
            $settings  = $this->loadSettings();
            $dismissed = json_decode((string) ($settings['dismissed_checks'] ?? '[]'), true);
            $dismissed = is_array($dismissed) ? $dismissed : [];

            return (new NotificationService($settings, $dismissed))->build([
                'conflicts'             => $this->conflicts,
                'plugins'               => $this->pluginStatus,
                'multilingualLangCount' => $this->multilingualLangCount,
                'hasSettings'           => $this->hasSettings,
            ]);
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PLUGIN STATUS
    // ─────────────────────────────────────────────────────────────────────────

    private function getPluginStatus(): array
    {
        $plugins = [
            'aiboost_schema'    => ['label' => 'Schema.org',      'desc' => 'Outputs structured data so search engines understand your content.'],
            'aiboost_sitemap'   => ['label' => 'XML Sitemap',     'desc' => 'Generates an XML sitemap with hreflang for multilingual sites.'],
            'aiboost_social'    => ['label' => 'Social & OG',     'desc' => 'Adds OpenGraph and Twitter Card tags for rich social previews.'],
            'aiboost_analytics' => ['label' => 'Analytics',       'desc' => 'Injects GA4, GTM, Meta Pixel and Google Search Console verification.'],
            'aiboost_aeo'       => ['label' => 'AEO / llms.txt',  'desc' => 'Creates llms.txt and IndexNow key file for AI search engines.'],
            'aiboost_core'      => ['label' => 'Core',     'desc' => 'Core SEO services: canonical URLs, title/description templates, 404 logging, and consolidated AI Boost head block finalisation.'],
            'aiboost_code'      => ['label' => 'Custom Code',     'desc' => 'Injects custom HTML/JS snippets into head, body start or body end.'],
        ];

        $status = [];

        try {
            $db = Factory::getDbo();
            foreach ($plugins as $element => $info) {
                $label = $info['label'];
                $desc  = $info['desc'];
                $query = $db->getQuery(true)
                    ->select(['extension_id', 'enabled'])
                    ->from('#__extensions')
                    ->where($db->quoteName('type') . '=' . $db->quote('plugin'))
                    ->where($db->quoteName('element') . '=' . $db->quote($element))
                    ->where($db->quoteName('folder') . '=' . $db->quote('system'));
                $db->setQuery($query);
                $row = $db->loadObject();

                $status[$element] = [
                    'label'        => $label,
                    'desc'         => $desc,
                    'enabled'      => $row !== null ? (bool) $row->enabled : null,
                    'found'        => $row !== null,
                    'extension_id' => $row !== null ? (int) $row->extension_id : null,
                ];
            }
        } catch (\Throwable $e) {
        }

        return $status;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 404 / REDIRECTS
    // ─────────────────────────────────────────────────────────────────────────

    private function getTop404(int $limit = 20): array
    {
        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'aiboost_404_log'))->loadColumn();
            if (empty($tables)) {
                return [];
            }
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'request_url', 'referrer', 'hits', 'last_seen']))
                ->from($db->quoteName('#__aiboost_404_log'))
                ->order($db->quoteName('hits') . ' DESC, ' . $db->quoteName('last_seen') . ' DESC')
                ->setLimit($limit);
            $db->setQuery($query);
            return $db->loadAssocList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function count404(): int
    {
        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'aiboost_404_log'))->loadColumn();
            if (empty($tables)) {
                return 0;
            }
            return (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__aiboost_404_log'))->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function countRedirects(): int
    {
        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'aiboost_redirects'))->loadColumn();
            if (empty($tables)) {
                return 0;
            }
            return (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__aiboost_redirects'))->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SETTINGS CHECK
    // ─────────────────────────────────────────────────────────────────────────

    private function checkHasSettings(): bool
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            return (int) $db->setQuery($query)->loadResult() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Whether Pro is functionally active, for raw.isPro in window.aiBoostDashboard
     * (so the Vue DashboardApp can show/hide Pro-conditioned UI without depending
     * on window.aiBoostSettings, only available on the Settings page).
     *
     * Uses the single canonical gate — the perpetual `pro_activated` flag via
     * PluginRegistry::isProActive() — NOT the drift-prone `license_tier`, so a
     * perpetual-Pro install keeps reading "Pro" after the licence expires.
     */
    private function checkIsProEnabled(): bool
    {
        try {
            return PluginRegistry::isProActive($this->loadSettings());
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function countTranslations(): int
    {
        try {
            $db     = Factory::getDbo();
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'aiboost_translations'))->loadColumn();
            if (empty($tables)) {
                return 0;
            }
            return (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__aiboost_translations'))->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getLastSaved(): ?string
    {
        try {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('updated_at'))
                ->from('#__aiboost_settings')
                ->where($db->quoteName('setting_key') . '=' . $db->quote('main'));
            $updatedAt = $db->setQuery($query)->loadResult();

            if (!$updatedAt || $updatedAt === '0000-00-00 00:00:00') {
                return null;
            }

            $date = Factory::getDate($updatedAt, 'UTC');
            $user = Factory::getApplication()->getIdentity();
            $tz   = $user ? $user->getParam('timezone', Factory::getApplication()->get('offset', 'UTC')) : 'UTC';
            $date->setTimezone(new \DateTimeZone($tz));

            return $date->format('d M Y \a\t H:i', true);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
