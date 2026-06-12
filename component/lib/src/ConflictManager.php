<?php
/**
 * AI Boost — ConflictManager
 *
 * Static registry that tracks which plugin has claimed ownership of a feature
 * slot. When two AI Boost plugins are both active and would inject the same
 * output (e.g. robots.txt, canonical, hreflang headers, og_tags), only the
 * first plugin to call claim() wins; subsequent callers receive false and must
 * skip their injection silently.
 *
 * Task #486 hardening:
 *   - SLOTS catalogue is exposed as a class constant for SDK doc + tests.
 *   - Every claim is logged (slot, claimant, granted, reason, microtime) so
 *     Health and the SDK doc can render "who owns what" on a live site.
 *   - report() returns a structured snapshot for the Debug tab.
 *
 * Usage inside a plugin:
 *   if (!ConflictManager::claim('robots_txt', 'aiboost_aeo')) {
 *       return; // another plugin already owns this slot
 *   }
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

if (!class_exists('AiBoost\\Lib\\ConflictManager', false)) :

final class ConflictManager
{
    /**
     * Registry of claimed feature slots.
     * Key = slot name, Value = plugin name that owns it.
     *
     * @var array<string, string>
     */
    private static array $registry = [];

    /**
     * Append-only audit log of every claim() call.
     *
     * @var list<array{slot:string,claimant:string,granted:bool,reason:string,at:float}>
     */
    private static array $log = [];

    /**
     * Known feature slots across all AI Boost plugins.
     * Plugins should use these constants as the $slot argument.
     */
    public const SLOT_ROBOTS_TXT    = 'robots_txt';
    public const SLOT_CANONICAL     = 'canonical';
    public const SLOT_HREFLANG      = 'hreflang';
    public const SLOT_OG_TAGS       = 'og_tags';
    public const SLOT_SCHEMA_ORG    = 'schema_org';
    public const SLOT_SCHEMA_FAQ    = 'schema_faq';
    public const SLOT_SITEMAP       = 'sitemap';
    public const SLOT_SITEMAP_XML   = 'sitemap_xml';
    public const SLOT_LLMS_TXT      = 'llms_txt';
    public const SLOT_INDEX_NOW     = 'index_now';
    public const SLOT_META_ROBOTS   = 'meta_robots';

    /**
     * Canonical list of every known slot. Tests assert that every SLOT_*
     * constant appears here; the SDK doc renders this verbatim.
     *
     * @var array<int,string>
     */
    public const SLOTS = [
        self::SLOT_ROBOTS_TXT,
        self::SLOT_CANONICAL,
        self::SLOT_HREFLANG,
        self::SLOT_OG_TAGS,
        self::SLOT_SCHEMA_ORG,
        self::SLOT_SCHEMA_FAQ,
        self::SLOT_SITEMAP,
        self::SLOT_SITEMAP_XML,
        self::SLOT_LLMS_TXT,
        self::SLOT_INDEX_NOW,
        self::SLOT_META_ROBOTS,
    ];

    /**
     * Claim a feature slot for the given plugin.
     *
     * Returns true if the slot was successfully claimed (i.e. no other plugin
     * has claimed it yet, OR the same plugin is re-claiming its own slot).
     * Returns false if a different plugin already owns this slot.
     */
    public static function claim(string $slot, string $pluginName, string $reason = ''): bool
    {
        if (!isset(self::$registry[$slot])) {
            self::$registry[$slot] = $pluginName;
            self::record($slot, $pluginName, true, $reason !== '' ? $reason : 'first claim');
            return true;
        }

        if (self::$registry[$slot] === $pluginName) {
            self::record($slot, $pluginName, true, $reason !== '' ? $reason : 're-claim');
            return true;
        }

        self::record(
            $slot,
            $pluginName,
            false,
            sprintf('rejected — owned by "%s"', self::$registry[$slot])
        );
        return false;
    }

    public static function isClaimed(string $slot): bool
    {
        return isset(self::$registry[$slot]);
    }

    public static function getOwner(string $slot): ?string
    {
        return self::$registry[$slot] ?? null;
    }

    public static function release(string $slot, string $pluginName): bool
    {
        if ((self::$registry[$slot] ?? null) === $pluginName) {
            unset(self::$registry[$slot]);
            self::record($slot, $pluginName, true, 'released');
            return true;
        }
        return false;
    }

    /** Intended for use in tests only. */
    public static function reset(): void
    {
        self::$registry = [];
        self::$log      = [];
    }

    /** @return array<string, string> */
    public static function getRegistry(): array
    {
        return self::$registry;
    }

    /** @return list<array{slot:string,claimant:string,granted:bool,reason:string,at:float}> */
    public static function getLog(): array
    {
        return self::$log;
    }

    /**
     * Detect slots that were claimed by one plugin AND attempted by another
     * during this request — used by HealthCheckService to surface
     * warning_bridge_slot_collision when two bridges fight for the same slot.
     *
     * @return list<array{slot:string,owner:string,rejected:list<string>}>
     */
    public static function collisions(): array
    {
        $rejected = [];
        foreach (self::$log as $entry) {
            if ($entry['granted']) {
                continue;
            }
            $rejected[$entry['slot']][] = $entry['claimant'];
        }

        $out = [];
        foreach ($rejected as $slot => $claimants) {
            $owner = self::$registry[$slot] ?? '';
            $unique = array_values(array_unique(array_filter($claimants, static fn ($c) => $c !== $owner)));
            if ($unique === []) {
                continue;
            }
            $out[] = [
                'slot'     => $slot,
                'owner'    => $owner,
                'rejected' => $unique,
            ];
        }
        return $out;
    }

    /**
     * Structured snapshot for the Debug tab / SDK doc.
     *
     * @return array{slots:array<string,string>,log:list<array<string,mixed>>,collisions:list<array<string,mixed>>}
     */
    public static function report(): array
    {
        return [
            'slots'      => self::$registry,
            'log'        => self::$log,
            'collisions' => self::collisions(),
        ];
    }

    private static function record(string $slot, string $claimant, bool $granted, string $reason): void
    {
        self::$log[] = [
            'slot'     => $slot,
            'claimant' => $claimant,
            'granted'  => $granted,
            'reason'   => $reason,
            'at'       => microtime(true),
        ];
    }
}

endif;
