<?php
/**
 * AI Boost Health Status — Admin Module
 *
 * Compact health status widget for the Joomla administrator dashboard.
 * Uses HealthCheckService with skipHttpScan=true so the score, critical count,
 * and warning count are consistent with the full Health view — with zero
 * outbound HTTP requests (logo HEAD check and DuplicateTagScanner both skipped).
 *
 * @package     mod_aiboost_health
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Router\Route;

// ── AI Boost autoloader ─────────────────────────────────────────────────────
$autoload = JPATH_ADMINISTRATOR . '/components/com_aiboost/lib/autoload.php';
if (!file_exists($autoload)) {
    return;
}
require_once $autoload;

use AiBoost\Lib\HealthCheckService;
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Version;

// ── Free hide (Task #478) ────────────────────────────────────────────────────
// The admin module is a Pro-only convenience widget. On a Free install it
// would advertise locked features (Analyzers, URL Checker, etc.) without
// actually being usable, so we render nothing at all. "Pro install" here
// means the Pro package OR any individual Pro plugin is present in
// #__extensions — same signal HtmlView::detectProInstall() uses.
$abIsProInstall = false;
try {
    $abExtDb    = Factory::getDbo();
    $abExtQuery = $abExtDb->getQuery(true)
        ->select('COUNT(*)')
        ->from($abExtDb->quoteName('#__extensions'))
        ->where(
            '(' . $abExtDb->quoteName('element') . ' = ' . $abExtDb->quote('pkg_aiboost_pro')
            . ' OR ' . $abExtDb->quoteName('element') . ' LIKE '
            . $abExtDb->quote('aiboost\\_%\\_pro') . ' ESCAPE ' . $abExtDb->quote('\\')
            . ')'
        );
    $abExtDb->setQuery($abExtQuery);
    $abIsProInstall = ((int) $abExtDb->loadResult()) > 0;
} catch (\Throwable $e) {
    $abIsProInstall = false;
}
if (!$abIsProInstall) {
    return;
}

// ── Load settings from #__aiboost_settings ───────────────────────────────────
$abSettings = [];
try {
    $abDb    = Factory::getDbo();
    $abQuery = $abDb->getQuery(true)
        ->select($abDb->quoteName('settings_json'))
        ->from('#__aiboost_settings')
        ->where($abDb->quoteName('setting_key') . '=' . $abDb->quote('main'));
    $abJson = (string) $abDb->setQuery($abQuery)->loadResult();
    if (!empty($abJson)) {
        $decoded    = json_decode($abJson, true);
        $abSettings = is_array($decoded) ? $decoded : [];
    }
} catch (\Throwable $e) {
}

// ── Package version and licence tier ─────────────────────────────────────────
$abVersion = Version::VERSION;
$abTier    = strtolower((string) ($abSettings['license_tier'] ?? 'free'));
$abIsPro   = in_array($abTier, ['pro', 'developer', 'agency'], true);

// ── Plugin enabled status from #__extensions ─────────────────────────────────
// Keys: element slug => display label
$abPluginDefs = [
    'aiboost_schema'    => 'Schema',
    'aiboost_sitemap'   => 'Sitemap',
    'aiboost_social'    => 'Social',
    'aiboost_analytics' => 'Analytics',
    'aiboost_aeo'       => 'AEO',
    'aiboost_core'      => 'Core',
];
$abPluginStatus = [];
try {
    $abDb2  = Factory::getDbo();
    $abQExt = $abDb2->getQuery(true)
        ->select([$abDb2->quoteName('element'), $abDb2->quoteName('enabled')])
        ->from($abDb2->quoteName('#__extensions'))
        ->where($abDb2->quoteName('type') . '=' . $abDb2->quote('plugin'))
        ->where($abDb2->quoteName('folder') . '=' . $abDb2->quote('system'))
        ->where($abDb2->quoteName('element') . ' IN (' .
            implode(',', array_map([$abDb2, 'quote'], array_keys($abPluginDefs))) . ')');
    $abExtRows = $abDb2->setQuery($abQExt)->loadAssocList('element') ?: [];
    foreach ($abPluginDefs as $slug => $label) {
        $abPluginStatus[$slug] = [
            'label'   => $label,
            'enabled' => isset($abExtRows[$slug]) ? (bool) $abExtRows[$slug]['enabled'] : false,
        ];
    }
} catch (\Throwable $e) {
    foreach ($abPluginDefs as $slug => $label) {
        $abPluginStatus[$slug] = ['label' => $label, 'enabled' => false];
    }
}

// ── Run HealthCheckService — skipHttpScan=true skips all HTTP requests ────────
$abScore    = 100;
$abCritical = 0;
$abWarnings = 0;
$abTotal    = 0;
$abChecks   = [];
$abTopIssues = [];

try {
    $abService = new HealthCheckService(
        $abSettings,
        Factory::getDbo(),
        new JoomlaAppContext(),
        true    // skipHttpScan=true — zero outbound HTTP requests
    );
    $abResult = $abService->run();

    $abScore  = $abResult['score'];
    $abChecks = $abResult['checks'];

    $abCritical = count(array_filter(
        $abChecks,
        static fn($c) => $c['status'] === 'critical' && !$c['pass'] && !$c['dismissed']
    ));
    $abWarnings = count(array_filter(
        $abChecks,
        static fn($c) => $c['status'] === 'warning' && !$c['pass'] && !$c['dismissed']
    ));
    $abTotal = $abCritical + $abWarnings;

    // Collect top 3 failing checks — critical and warning only (not info)
    $abFailing = array_filter(
        $abChecks,
        static fn($c) => !$c['pass'] && !$c['dismissed']
            && in_array($c['status'], ['critical', 'warning'], true)
    );
    usort($abFailing, static function ($a, $b) {
        $order = ['critical' => 0, 'warning' => 1];
        return ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);
    });
    $abTopIssues = array_slice(array_values($abFailing), 0, 3);
} catch (\Throwable $e) {
}

// ── Quick-action URLs ─────────────────────────────────────────────────────────
// Open every quick action inside the Vue SPA shell (view=app#/route) so the
// admin keeps the sidebar and never lands on a legacy standalone PHP page.
$abAppBase        = Route::_('index.php?option=com_aiboost&view=app', false);
$abDashboardUrl   = $abAppBase . '#/dashboard';
$abHealthUrl      = $abAppBase . '#/health';
$abSettingsUrl    = $abAppBase . '#/settings';
$abImportUrl      = $abAppBase . '#/import';
$abSitemapUrl     = $abAppBase . '#/redirects';
$abAnalyzerUrl    = $abAppBase . '#/analyzers';
$abUrlCheckerUrl  = $abAppBase . '#/urlchecker';

// ── Indexed URLs count — published #__content items (proxy for sitemap size) ─
// We count published, accessible articles as the "indexed URLs" signal because
// the sitemap is generated dynamically from the same source. Cheap query, no
// outbound HTTP.
$abIndexedUrls = null;
try {
    $abDb3  = Factory::getDbo();
    $abQUrl = $abDb3->getQuery(true)
        ->select('COUNT(*)')
        ->from($abDb3->quoteName('#__content'))
        ->where($abDb3->quoteName('state') . ' = 1');
    $abIndexedUrls = (int) $abDb3->setQuery($abQUrl)->loadResult();
} catch (\Throwable $e) {
}

// ── Last URL scan result — #__aiboost_404_log signal ─────────────────────────
// Reflects the most recent automated URL scan: total tracked 404s and the
// most recent 404 timestamp. If the table is empty or missing → "No issues".
$ab404Count     = 0;
$ab404LastSeen  = null;
try {
    $abDb4   = Factory::getDbo();
    $abPrefx = $abDb4->getPrefix();
    $abTbls  = $abDb4->setQuery('SHOW TABLES LIKE ' . $abDb4->quote($abPrefx . 'aiboost_404_log'))->loadColumn();
    if (!empty($abTbls)) {
        $abQ404 = $abDb4->getQuery(true)
            ->select(['COUNT(*) AS cnt', 'MAX(' . $abDb4->quoteName('last_seen') . ') AS last_seen'])
            ->from($abDb4->quoteName('#__aiboost_404_log'));
        $abRow404 = $abDb4->setQuery($abQ404)->loadObject();
        if ($abRow404 !== null) {
            $ab404Count    = (int) $abRow404->cnt;
            $ab404LastSeen = $abRow404->last_seen ? (string) $abRow404->last_seen : null;
        }
    }
} catch (\Throwable $e) {
}

require ModuleHelper::getLayoutPath('mod_aiboost_health', $params->get('layout', 'default'));
