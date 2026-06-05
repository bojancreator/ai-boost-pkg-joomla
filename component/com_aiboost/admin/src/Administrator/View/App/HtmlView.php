<?php
/**
 * AI Boost — SPA Shell View
 *
 * Renders the empty SPA mount point + window.aiBoostBootstrap. All actual UI
 * is rendered by Vue (AppShell.vue + vue-router). Each individual view
 * (?view=dashboard, ?view=settings, …) is still available as legacy fallback.
 *
 * @package     AiBoost\Component\AiBoost\Administrator\View\App
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Component\AiBoost\Administrator\View\App;

defined('_JEXEC') or die;

use AiBoost\Component\AiBoost\Administrator\Controller\ErrorsController;
use AiBoost\Version;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    public string $version          = '';
    public array  $bootstrap        = [];
    public string $bootstrapJson    = '{}';

    public function display($tpl = null): void
    {
        $this->version       = Version::VERSION;
        $this->bootstrap     = $this->buildBootstrap();
        $this->bootstrapJson = json_encode(
            $this->bootstrap,
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $this->addToolbar();
        parent::display($tpl);
    }

    private function addToolbar(): void
    {
        ToolbarHelper::title('AI Boost <small>v' . Version::VERSION . '</small>', 'bolt');
    }

    /**
     * Static bootstrap data the SPA needs immediately on first paint.
     *
     * Per-view data (dashboard plugin status, health checks, etc.) is fetched
     * lazily by the SPA via the legacy tmpl=component URLs — see
     * vue-admin/src/composables/useLegacyGlobals.js.
     */
    /**
    * Returns true when the legacy AI Boost add-on package (or any of its
    * add-on plugins) is installed and enabled. Independent of license key
    * status so the Licenses page can be reached to enter that key.
     */
    private function detectProInstall(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            // v0.55.4 — do NOT require enabled=1. Packages (type=package)
            // don't have a meaningful enabled flag, and individual Pro
            // plugins may be toggled off by the admin without uninstalling
            // the bundle. The presence of the row in #__extensions is what
            // "Pro install" means — the Licenses page must remain reachable
            // either way, otherwise the user has no path back to entering
            // a key after they disabled a single Pro plugin or after they
            // hit Release (Bojan's bug: Licenses tab disappeared on Pro
            // install after a license Release).
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__extensions'))
                ->where(
                    '(' . $db->quoteName('element') . ' = ' . $db->quote('pkg_aiboost_pro')
                    . ' OR ' . $db->quoteName('element') . ' LIKE ' . $db->quote('aiboost_%\\_pro') . ' ESCAPE ' . $db->quote('\\')
                    . ')'
                );
            $db->setQuery($query);
            $cached = ((int) $db->loadResult()) > 0;
        } catch (\Throwable $e) {
            $cached = false;
        }
        return $cached;
    }

    private function buildBootstrap(): array
    {
        $tokenName = Session::getFormToken();

        $licenseTier       = '';
        $isPro             = false;
        $proActivated      = false;
        $proActivatedAt    = null;
        $licenseHeartbeat  = new \stdClass();
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('settings_json'))
                ->from($db->quoteName('#__aiboost_settings'))
                ->where($db->quoteName('setting_key') . ' = ' . $db->quote('main'));
            $db->setQuery($query);
            $raw = $db->loadResult();
            if ($raw) {
                $settings = json_decode($raw, true);
                if (is_array($settings)) {
                    $licenseTier = (string) ($settings['license_tier'] ?? '');

                    // v0.54.2 — Pro UI is gated on a VERIFIED license key, not
                    // on `license_tier` alone. A Pro install with no entered
                    // license code must look identical to Free; only an active
                    // license key (or the dev_license_preview QA flag) unlocks
                    // the UI. This matches Bojan's directive: "Pro verzija se
                    // isto ponaša kao Free dok se ne unese licence kod i
                    // provjeri".
                    //
                    // v0.69.1 — delegate to the single canonical gate so the
                    // admin UI, the settings-save endpoint and the sitemap
                    // runtime all derive `isPro` from the SAME signal (no
                    // gating-source drift).
                    //
                    // Task #565 — PERPETUAL ACTIVATION. isProActive() now resolves
                    // from the permanent `pro_activated` flag: once a key verifies
                    // active, Pro stays unlocked forever. An expired licence only
                    // pauses updates + support (shown as a renewal notice), it
                    // never relocks the UI. The dev_force_free_tier QA override
                    // (render Pro as Free for screenshots) still wins.
                    $isPro            = \AiBoost\Lib\PluginRegistry::isProActive($settings);
                    $proActivated     = (string) ($settings['pro_activated'] ?? '0') === '1';
                    $proActivatedAt   = $settings['pro_activated_at'] ?? null;
                    if (isset($settings['license_heartbeat']) && is_array($settings['license_heartbeat'])) {
                        $hb = $settings['license_heartbeat'];
                        // Days until the next informational re-validate (display only).
                        $hb['days_until_next_check'] = \AiBoost\Lib\LicenseHeartbeat::daysUntilNextCheck($settings);
                        $licenseHeartbeat = $hb;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Settings table may not exist on first run — ignore.
        }

        // Legacy feature registry payload kept for compatibility during the
        // one-product transition.
        $proFeatures = [];
        try {
            if (class_exists('AiBoost\\Lib\\ProFeatureRegistry')) {
                $proFeatures = \AiBoost\Lib\ProFeatureRegistry::all();
            }
        } catch (\Throwable $e) {
            // Silent: SPA falls back to inline (non-gated) render.
        }

        return [
            'version'          => Version::VERSION,
            'tokenName'        => $tokenName,
            'csrfToken'        => $tokenName,
            'baseUrl'          => Route::_('index.php?option=com_aiboost', false),
            'ajaxBase'         => 'index.php?option=com_aiboost',
            'isPro'            => $isPro,
            // v0.55.1 — "Pro package installed" detection via #__extensions.
            // `license_tier` is materialised by saveLicenseState() and ends
            // up 'free' until a key is actually verified, so we cannot use
            // it to tell "Pro pkg installed" from "Free pkg installed".
            // Look up the Pro package + Pro plugins in the extensions
            // table instead — that is the unambiguous signal.
            // Used only by the Licenses page so a fresh Pro install (no
            // key entered yet) stays usable and the user can paste a key.
            'isProInstall'     => $this->detectProInstall(),
            'proFeatures'      => $proFeatures,
            'licenseHeartbeat' => $licenseHeartbeat,
            'license'          => [
                'tier'          => $licenseTier,
                'isPro'         => $isPro,
                'isProInstall'  => $this->detectProInstall(),
                // Task #565 — perpetual activation status for the Licenses tab.
                'proActivated'  => $proActivated,
                'proActivatedAt' => $proActivatedAt,
            ],
            'urls'         => [
                'app'           => Route::_('index.php?option=com_aiboost&view=app', false),
                'settingsSave'  => Route::_('index.php?option=com_aiboost&task=settings.save&format=json', false),
                'pluginManager' => Route::_('index.php?option=com_plugins&filter[folder]=system&filter[search]=ai+boost', false),
            ],
            'legacyUrls' => [
                'dashboard'    => Route::_('index.php?option=com_aiboost&view=dashboard',    false),
                'settings'     => Route::_('index.php?option=com_aiboost&view=settings',     false),
                'health'       => Route::_('index.php?option=com_aiboost&view=health',       false),
                'redirects'    => Route::_('index.php?option=com_aiboost&view=redirects',    false),
                'urlchecker'   => Route::_('index.php?option=com_aiboost&view=urlchecker',   false),
                'import'       => Route::_('index.php?option=com_aiboost&view=import',      false),
                'integrations' => Route::_('index.php?option=com_aiboost&view=integrations', false),
                'analyzer'     => Route::_('index.php?option=com_aiboost&view=analyzer',     false),
                'help'         => Route::_('index.php?option=com_aiboost&view=help',         false),
            ],
            'labels' => [
                'dashboard'    => Text::_('COM_AIBOOST_NAV_DASHBOARD'),
                'autopilot'    => Text::_('COM_AIBOOST_NAV_AUTOPILOT'),
                'settings'     => Text::_('COM_AIBOOST_NAV_SETTINGS'),
                'health'       => Text::_('COM_AIBOOST_NAV_HEALTH'),
                'redirects'    => Text::_('COM_AIBOOST_NAV_REDIRECTS'),
                'urlchecker'   => Text::_('COM_AIBOOST_NAV_URLCHECKER'),
                'import'       => Text::_('COM_AIBOOST_NAV_IMPORT'),
                'integrations' => Text::_('COM_AIBOOST_NAV_INTEGRATIONS'),
                'analyzers'    => Text::_('COM_AIBOOST_NAV_ANALYZERS'),
                'errors'       => Text::_('COM_AIBOOST_NAV_ERRORS'),
                'help'         => Text::_('COM_AIBOOST_NAV_HELP'),
            ],
            // Task #512 — seed the nav badge + Errors page so the user
            // sees the count without an extra round-trip on first paint.
            'errorsSummary' => ErrorsController::buildSummary(),
        ];
    }
}
