<?php
/**
 * AI Boost — Installation Integrity Audit
 *
 * Single source of truth for "did every expected AI Boost extension actually
 * install, is it enabled, and is it on the package version?". Used both by the
 * live Health check (HealthCheckService::checkInstallationIntegrity) and by the
 * package postflight summary message (pkg_script::showIntegritySummary).
 *
 * Classification per extension:
 *   ok        — expected for this edition, installed, enabled, version matches
 *   missing   — expected for this edition but no #__extensions row
 *   disabled  — expected and installed but the row is disabled
 *   orphan    — an aiboost_* extension present but NOT expected for this edition
 *               (e.g. a *_pro plugin on a Free install, or a stale sub-plugin
 *               left over from an older version). Integration bridges
 *               (aiboost_int_*) are sold separately and are never orphans.
 *   mismatch  — expected + installed but the manifest version != package version
 *
 * Edition is decided physically: a pkg_aiboost_pro package row in #__extensions
 * means the Pro plugins are expected; otherwise they are orphans.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use Joomla\Database\DatabaseInterface;

final class InstallIntegrity
{
    /** Free edition: the bundled core system plugins (mirror pkg_aiboost.xml). */
    public const FREE_SYSTEM_PLUGINS = [
        'aiboost_schema',
        'aiboost_sitemap',
        'aiboost_social',
        'aiboost_analytics',
        'aiboost_aeo',
        'aiboost_core',
        'aiboost_code',
    ];

    /** Pro upgrade plugins (mirror pkg_aiboost_pro.xml / PluginRegistry::PRO_SKUS keys). */
    public const PRO_SYSTEM_PLUGINS = [
        'aiboost_schema_pro',
        'aiboost_aeo_pro',
        'aiboost_social_pro',
        'aiboost_hreflang_pro',
        'aiboost_code_pro',
    ];

    /** Non-plugin extensions that ship in every edition. */
    public const COMPONENT_ELEMENT = 'com_aiboost';
    public const MODULE_ELEMENT    = 'mod_aiboost_health';

    /**
     * True when the Pro upgrade package is physically installed. This is the
     * signal that the *_pro plugins are *expected* (rather than orphan leftovers).
     */
    public static function isProEdition(DatabaseInterface $db): bool
    {
        try {
            $count = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_aiboost_pro'))
            )->loadResult();
            return $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Run the audit.
     *
     * @return array{
     *   edition: string,
     *   version: string,
     *   ok: list<string>,
     *   missing: list<string>,
     *   disabled: list<string>,
     *   orphan: list<string>,
     *   mismatch: list<array{element:string, version:string}>,
     *   active_count: int,
     *   expected_count: int
     * }
     */
    public static function audit(DatabaseInterface $db, bool $isPro, string $version): array
    {
        $version = trim($version);

        // Expected set: name => extension descriptor (for the query filter only).
        $expectedPlugins = self::FREE_SYSTEM_PLUGINS;
        if ($isPro) {
            $expectedPlugins = array_merge($expectedPlugins, self::PRO_SYSTEM_PLUGINS);
        }

        $rows = self::scanRows($db);

        $ok       = [];
        $missing  = [];
        $disabled = [];
        $orphan   = [];
        $mismatch = [];

        // 1. Expected system plugins.
        foreach ($expectedPlugins as $element) {
            $row = $rows['plugin'][$element] ?? null;
            if ($row === null) {
                $missing[] = $element;
                continue;
            }
            if ((int) ($row['enabled'] ?? 0) !== 1) {
                $disabled[] = $element;
                continue;
            }
            $rowVer = self::manifestVersion($row);
            if ($version !== '' && $rowVer !== '' && $rowVer !== $version) {
                $mismatch[] = ['element' => $element, 'version' => $rowVer];
                continue;
            }
            $ok[] = $element;
        }

        // 2. Component + health module (every edition).
        foreach ([
            'component' => self::COMPONENT_ELEMENT,
            'module'    => self::MODULE_ELEMENT,
        ] as $type => $element) {
            $row = $rows[$type][$element] ?? null;
            if ($row === null) {
                $missing[] = $element;
                continue;
            }
            // The component is always "enabled"; the module may be unpublished but
            // that is an admin layout choice, not an integrity failure, so we only
            // version-check these two.
            $rowVer = self::manifestVersion($row);
            if ($version !== '' && $rowVer !== '' && $rowVer !== $version) {
                $mismatch[] = ['element' => $element, 'version' => $rowVer];
                continue;
            }
            $ok[] = $element;
        }

        // 3. Orphans — any aiboost_* system plugin present but not expected.
        //    Integration bridges (aiboost_int_*) are separate products: skip them.
        foreach (array_keys($rows['plugin']) as $element) {
            if (in_array($element, $expectedPlugins, true)) {
                continue;
            }
            if (str_starts_with($element, 'aiboost_int_')) {
                continue;
            }
            $orphan[] = $element;
        }

        sort($missing);
        sort($disabled);
        sort($orphan);

        return [
            'edition'        => $isPro ? 'Pro' : 'Free',
            'version'        => $version,
            'ok'             => array_values($ok),
            'missing'        => array_values($missing),
            'disabled'       => array_values($disabled),
            'orphan'         => array_values($orphan),
            'mismatch'       => array_values($mismatch),
            'active_count'   => count($ok),
            'expected_count' => count($expectedPlugins) + 2,
        ];
    }

    /**
     * @return array{plugin: array<string,array<string,mixed>>, component: array<string,array<string,mixed>>, module: array<string,array<string,mixed>>}
     */
    private static function scanRows(DatabaseInterface $db): array
    {
        $out = ['plugin' => [], 'component' => [], 'module' => []];
        try {
            // No SQL LIKE — the `aiboost_` prefix is filtered in PHP so the scan
            // is portable across sql_mode settings (NO_BACKSLASH_ESCAPES etc.).
            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('element'),
                    $db->quoteName('type'),
                    $db->quoteName('folder'),
                    $db->quoteName('enabled'),
                    $db->quoteName('manifest_cache'),
                ])
                ->from($db->quoteName('#__extensions'))
                ->where(
                    '(' . $db->quoteName('type') . ' = ' . $db->quote('plugin')
                    . ' AND ' . $db->quoteName('folder') . ' = ' . $db->quote('system') . ')'
                    . ' OR (' . $db->quoteName('type') . ' = ' . $db->quote('component')
                    . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote(self::COMPONENT_ELEMENT) . ')'
                    . ' OR (' . $db->quoteName('type') . ' = ' . $db->quote('module')
                    . ' AND ' . $db->quoteName('element') . ' = ' . $db->quote(self::MODULE_ELEMENT) . ')'
                );
            foreach ((array) $db->setQuery($query)->loadAssocList() as $row) {
                $type = (string) ($row['type'] ?? '');
                $el   = (string) ($row['element'] ?? '');
                if ($el === '' || !isset($out[$type])) {
                    continue;
                }
                // Only AI Boost system plugins are relevant; skip every other
                // third-party / core system plugin returned by the broad query.
                if ($type === 'plugin' && !str_starts_with($el, 'aiboost_')) {
                    continue;
                }
                $out[$type][$el] = $row;
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost InstallIntegrity] scan failed: ' . $e->getMessage());
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function manifestVersion(array $row): string
    {
        $cache = json_decode((string) ($row['manifest_cache'] ?? '{}'), true) ?: [];
        return trim((string) ($cache['version'] ?? ''));
    }

    /**
     * Build a one-line plain-text summary used by the postflight install message.
     *
     * @param array<string,mixed> $audit  Result of audit()
     */
    public static function summaryLine(array $audit): string
    {
        $parts = [
            sprintf('%d/%d AI Boost extensions active', (int) $audit['active_count'], (int) $audit['expected_count']),
        ];
        if (!empty($audit['missing'])) {
            $parts[] = count($audit['missing']) . ' missing';
        }
        if (!empty($audit['disabled'])) {
            $parts[] = count($audit['disabled']) . ' disabled';
        }
        if (!empty($audit['orphan'])) {
            $parts[] = count($audit['orphan']) . ' leftover';
        }
        if (!empty($audit['mismatch'])) {
            $parts[] = count($audit['mismatch']) . ' version mismatch';
        }
        return implode('; ', $parts) . '.';
    }
}
