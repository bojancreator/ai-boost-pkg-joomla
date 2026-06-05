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

    /** Legacy add-on plugins (mirror pkg_aiboost_pro.xml / PluginRegistry::PRO_SKUS keys). */
    public const PRO_SYSTEM_PLUGINS = [
        'aiboost_schema_pro',
        'aiboost_aeo_pro',
        'aiboost_social_pro',
        'aiboost_hreflang_pro',
        'aiboost_code_pro',
    ];

    /** Non-plugin extensions audited alongside the package plugins. */
    public const COMPONENT_ELEMENT = 'com_aiboost';
    public const MODULE_ELEMENT    = 'mod_aiboost_health';

    /**
     * True when the legacy add-on package is physically installed. This is the
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
        $expectedPlugins = self::expectedPlugins($isPro);
        $expectedNonPlugins = self::expectedNonPlugins($isPro);
        $rows = self::scanRows($db);
        $ok       = [];
        $missing  = [];
        $disabled = [];
        $mismatch = [];

        foreach ($expectedPlugins as $element) {
            self::collectExpectedResult($rows['plugin'][$element] ?? null, $element, $version, true, $ok, $missing, $disabled, $mismatch);
        }

        foreach ($expectedNonPlugins as $type => $element) {
            self::collectExpectedResult($rows[$type][$element] ?? null, $element, $version, false, $ok, $missing, $disabled, $mismatch);
        }

        $orphan = self::orphanPlugins($rows['plugin'], $expectedPlugins);
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
            'expected_count' => count($expectedPlugins) + count($expectedNonPlugins),
        ];
    }

    /**
     * @return list<string>
     */
    private static function expectedPlugins(bool $isPro): array
    {
        return $isPro ? array_merge(self::FREE_SYSTEM_PLUGINS, self::PRO_SYSTEM_PLUGINS) : self::FREE_SYSTEM_PLUGINS;
    }

    /**
     * @return array<string,string>
     */
    private static function expectedNonPlugins(bool $isPro): array
    {
        $expected = ['component' => self::COMPONENT_ELEMENT];
        if ($isPro) {
            $expected['module'] = self::MODULE_ELEMENT;
        }
        return $expected;
    }

    /**
     * @param array<string,mixed>|null $row
     * @param list<string>             $ok
     * @param list<string>             $missing
     * @param list<string>             $disabled
     * @param list<array{element:string, version:string}> $mismatch
     */
    private static function collectExpectedResult(?array $row, string $element, string $version, bool $requireEnabled, array &$ok, array &$missing, array &$disabled, array &$mismatch): void
    {
        if ($row === null) {
            $missing[] = $element;
            return;
        }
        if (self::expectedRowDisabled($row, $requireEnabled)) {
            $disabled[] = $element;
            return;
        }
        $rowVer = self::mismatchedVersion($row, $version);
        if ($rowVer !== '') {
            $mismatch[] = ['element' => $element, 'version' => $rowVer];
            return;
        }
        $ok[] = $element;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function expectedRowDisabled(array $row, bool $requireEnabled): bool
    {
        return $requireEnabled && (int) ($row['enabled'] ?? 0) !== 1;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function mismatchedVersion(array $row, string $expectedVersion): string
    {
        $rowVer = self::manifestVersion($row);
        return $expectedVersion !== '' && $rowVer !== '' && $rowVer !== $expectedVersion ? $rowVer : '';
    }

    /**
     * @param array<string,array<string,mixed>> $plugins
     * @param list<string>                      $expectedPlugins
     * @return list<string>
     */
    private static function orphanPlugins(array $plugins, array $expectedPlugins): array
    {
        $orphan = [];
        foreach (array_keys($plugins) as $element) {
            if (!in_array($element, $expectedPlugins, true) && !str_starts_with($element, 'aiboost_int_')) {
                $orphan[] = $element;
            }
        }
        return $orphan;
    }

    /**
     * @return array{plugin: array<string,array<string,mixed>>, component: array<string,array<string,mixed>>, module: array<string,array<string,mixed>>}
     */
    private static function scanRows(DatabaseInterface $db): array
    {
        $out = ['plugin' => [], 'component' => [], 'module' => []];
        try {
            foreach ((array) $db->setQuery(self::scanRowsQuery($db))->loadAssocList() as $row) {
                self::collectScannedRow($out, $row);
            }
        } catch (\Throwable $e) {
            error_log('[AI Boost InstallIntegrity] scan failed: ' . $e->getMessage());
        }
        return $out;
    }

    private static function scanRowsQuery(DatabaseInterface $db)
    {
        return $db->getQuery(true)
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
    }

    /**
     * @param array{plugin: array<string,array<string,mixed>>, component: array<string,array<string,mixed>>, module: array<string,array<string,mixed>>} $out
     * @param array<string,mixed> $row
     */
    private static function collectScannedRow(array &$out, array $row): void
    {
        $target = self::scannedRowTarget($row, $out);
        if ($target === null) {
            return;
        }
        [$type, $el] = $target;
        $out[$type][$el] = $row;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $out
     * @return array{string,string}|null
     */
    private static function scannedRowTarget(array $row, array $out): ?array
    {
        $type = (string) ($row['type'] ?? '');
        $el   = (string) ($row['element'] ?? '');
        if (self::unknownScannedTarget($type, $el, $out)) {
            return null;
        }
        return self::irrelevantSystemPlugin($type, $el) ? null : [$type, $el];
    }

    /**
     * @param array<string,mixed> $out
     */
    private static function unknownScannedTarget(string $type, string $element, array $out): bool
    {
        return $element === '' || !isset($out[$type]);
    }

    private static function irrelevantSystemPlugin(string $type, string $element): bool
    {
        return $type === 'plugin' && !str_starts_with($element, 'aiboost_');
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
