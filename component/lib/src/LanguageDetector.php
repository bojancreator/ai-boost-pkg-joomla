<?php
/**
 * AI Boost — Language Detector
 *
 * Returns a normalized view of every multilingual source on the site:
 *   - Joomla native (#__languages + #__menu language column)
 *   - Falang (#__falang_content)
 *   - JoomFish (#__jf_content) — legacy support
 *
 * Lets the rest of the package make consistent decisions about which set of
 * translations is authoritative when the site mixes more than one source.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\AdapterRegistry;

final class LanguageDetector
{
    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{
     *   joomla_native: array<int,array<string,string>>,
     *   falang:        array<int,array<string,string>>,
     *   joomfish:      array<int,array<string,string>>,
     *   overlap:       array<int,string>,
     *   preferred_source: string,
     *   sources_active: array<int,string>
     * }
     */
    public static function detect(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $native   = self::loadJoomlaNative();
        $falang   = self::loadFalang();
        $joomfish = self::loadJoomFish();

        $codes  = static fn(array $rows) => array_values(array_unique(array_map(
            static fn($r) => strtolower((string) ($r['lang_code'] ?? '')),
            $rows
        )));
        $overlap = array_values(array_intersect($codes($native), $codes($falang)));

        $sources = [];
        if (!empty($native))   { $sources[] = 'joomla_native'; }
        if (!empty($falang))   { $sources[] = 'falang'; }
        if (!empty($joomfish)) { $sources[] = 'joomfish'; }

        return self::$cache = [
            'joomla_native'    => $native,
            'falang'           => $falang,
            'joomfish'         => $joomfish,
            'overlap'          => $overlap,
            'preferred_source' => self::resolvePreferred($sources),
            'sources_active'   => $sources,
        ];
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    // ── Internals ────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string,string>>
     */
    private static function loadJoomlaNative(): array
    {
        $out = [];
        try {
            $db = AdapterRegistry::database()->getConnection();
            $q  = $db->getQuery(true)
                ->select(['lang_id', 'lang_code', 'sef', 'title'])
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('published') . ' = 1')
                ->order('ordering ASC');
            $db->setQuery($q);
            foreach ((array) $db->loadAssocList() as $row) {
                $out[] = [
                    'lang_id'   => (string) ($row['lang_id']   ?? ''),
                    'lang_code' => (string) ($row['lang_code'] ?? ''),
                    'sef'       => (string) ($row['sef']       ?? ''),
                    'title'     => (string) ($row['title']     ?? ''),
                ];
            }
        } catch (\Throwable) {
            // Table missing or DB error — leave empty.
        }
        return $out;
    }

    /**
     * @return array<int, array<string,string>>
     */
    private static function loadFalang(): array
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')
            || !\AiBoost\Lib\BridgeDetector::tableExists('#__falang_content')) {
            return [];
        }
        // Falang reuses the Joomla #__languages table; we surface only the
        // codes that actually have at least one translation row.
        $out = [];
        try {
            $db = AdapterRegistry::database()->getConnection();
            $q  = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('language_id'))
                ->from($db->quoteName('#__falang_content'));
            $db->setQuery($q);
            $ids = array_filter(array_map('intval', (array) $db->loadColumn()));
            if (empty($ids)) {
                return $out;
            }
            $q2 = $db->getQuery(true)
                ->select(['lang_id', 'lang_code', 'sef', 'title'])
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('lang_id') . ' IN (' . implode(',', $ids) . ')');
            $db->setQuery($q2);
            foreach ((array) $db->loadAssocList() as $row) {
                $out[] = [
                    'lang_id'   => (string) ($row['lang_id']   ?? ''),
                    'lang_code' => (string) ($row['lang_code'] ?? ''),
                    'sef'       => (string) ($row['sef']       ?? ''),
                    'title'     => (string) ($row['title']     ?? ''),
                ];
            }
        } catch (\Throwable) {
            // Silent.
        }
        return $out;
    }

    /**
     * @return array<int, array<string,string>>
     */
    private static function loadJoomFish(): array
    {
        if (!class_exists('AiBoost\\Lib\\BridgeDetector')
            || !\AiBoost\Lib\BridgeDetector::tableExists('#__jf_content')) {
            return [];
        }
        return self::loadJoomlaNative();
    }

    /**
     * Determine which source AI Boost should treat as authoritative when
     * multiple are present. Honours the admin's setting
     * `translation_source_priority` (free, in General tab).
     *
     * @param array<int,string> $active
     */
    private static function resolvePreferred(array $active): string
    {
        if (empty($active)) {
            return 'none';
        }
        $pref = 'auto';
        if (class_exists('AiBoost\\Lib\\PluginSettings')) {
            $pref = strtolower(trim((string) (\AiBoost\Lib\PluginSettings::get('translation_source_priority') ?? 'auto')));
        }

        if ($pref !== 'auto' && in_array($pref, $active, true)) {
            return $pref;
        }
        // Auto: prefer Joomla native when both Joomla native and Falang are
        // present; otherwise return whichever single source is active.
        if (in_array('joomla_native', $active, true)) {
            return 'joomla_native';
        }
        return $active[0];
    }
}
