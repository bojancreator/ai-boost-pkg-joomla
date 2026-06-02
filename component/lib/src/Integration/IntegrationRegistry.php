<?php
/**
 * AI Boost — IntegrationRegistry
 *
 * Request-cached registry of every IntegrationDescriptor returned by an
 * `onAiBoostRegisterIntegration` listener. Replaces the hardcoded
 * INTEGRATIONS map that lived in PluginRegistry / IntegrationDetectorService
 * so a new `plg_system_aiboost_int_<key>` bridge ZIP appears in the
 * Integrations dashboard immediately — no core release needed.
 *
 * Bridges contribute either a real `IntegrationDescriptor` instance or
 * the array shorthand consumed by `IntegrationDescriptor::fromArray()`.
 * Invalid contributions (missing key / wrong type / unsupported SDK
 * version) are discarded and surfaced via getSdkMismatches().
 *
 * @package     AiBoost\Lib\Integration
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Integration;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\AdapterRegistry;

final class IntegrationRegistry
{
    /** @var array<string, IntegrationDescriptor>|null */
    private static ?array $cache = null;

    /** @var list<array{key:string,sdk_version:int,core_sdk_version:int,reason:string}> */
    private static array $sdkMismatches = [];

    /**
     * @return array<string, IntegrationDescriptor>
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$sdkMismatches = [];
        $out = [];

        try {
            $payloads = AdapterRegistry::events()
                ->trigger(Sdk::EVENT_REGISTER_INTEGRATION, []);
        } catch (\Throwable $e) {
            error_log('[AI Boost IntegrationRegistry] discover failed: ' . $e->getMessage());
            $payloads = [];
        }

        foreach ($payloads as $payload) {
            // Listeners may return a single descriptor or a list of them.
            $candidates = self::flatten($payload);
            foreach ($candidates as $candidate) {
                $desc = self::coerce($candidate);
                if ($desc === null) {
                    continue;
                }

                if (!Sdk::isCompatible($desc->sdkVersion)) {
                    self::$sdkMismatches[] = [
                        'key'              => $desc->key,
                        'sdk_version'      => $desc->sdkVersion,
                        'core_sdk_version' => Sdk::SDK_VERSION,
                        'reason'           => sprintf(
                            'Bridge "%s" declares SDK v%d, core supports v%d–v%d.',
                            $desc->key,
                            $desc->sdkVersion,
                            Sdk::MIN_SDK_VERSION,
                            Sdk::SDK_VERSION
                        ),
                    ];
                    continue;
                }

                // First registration wins; subsequent dupes are silently ignored
                // so the bridge gets a stable identity even if a quirky third
                // party fires the discovery event twice.
                if (!isset($out[$desc->key])) {
                    $out[$desc->key] = $desc;
                }
            }
        }

        return self::$cache = $out;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function get(string $key): ?IntegrationDescriptor
    {
        return self::all()[$key] ?? null;
    }

    /** @return list<array{key:string,sdk_version:int,core_sdk_version:int,reason:string}> */
    public static function getSdkMismatches(): array
    {
        // Force discovery so the mismatch list is populated.
        self::all();
        return self::$sdkMismatches;
    }

    public static function reset(): void
    {
        self::$cache         = null;
        self::$sdkMismatches = [];
    }

    /**
     * @param  mixed $payload
     * @return list<mixed>
     */
    private static function flatten(mixed $payload): array
    {
        if ($payload instanceof IntegrationDescriptor) {
            return [$payload];
        }
        if (is_array($payload)) {
            // Single descriptor as array vs. list of descriptors.
            if (isset($payload['key']) || isset($payload['label'])) {
                return [$payload];
            }
            return array_values($payload);
        }
        return [];
    }

    private static function coerce(mixed $candidate): ?IntegrationDescriptor
    {
        if ($candidate instanceof IntegrationDescriptor) {
            return $candidate->key !== '' ? $candidate : null;
        }
        if (is_array($candidate) && !empty($candidate['key'])) {
            try {
                return IntegrationDescriptor::fromArray($candidate);
            } catch (\Throwable $e) {
                error_log('[AI Boost IntegrationRegistry] coerce failed: ' . $e->getMessage());
            }
        }
        return null;
    }
}
