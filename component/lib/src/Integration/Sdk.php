<?php
/**
 * AI Boost — Integration SDK constants & helpers.
 *
 * The SDK version is the single number a bridge advertises in its
 * IntegrationDescriptor::sdkVersion field. Bumping this number is a
 * promise to existing bridges: anything ≤ the bridge's declared
 * sdkVersion is still wired up, anything newer than core is refused
 * with a Health warning (warning_bridge_sdk_mismatch).
 *
 * Semantics:
 *   - SDK_VERSION starts at 1 (initial release shipped with core 0.58.0).
 *   - Backwards-compatible additions (new optional descriptor fields,
 *     new filter events) DO NOT bump SDK_VERSION.
 *   - Breaking removals or renames in the SDK contract bump SDK_VERSION.
 *
 * @package     AiBoost\Lib\Integration
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Integration;

defined('_JEXEC') or defined('ABSPATH') or die;

final class Sdk
{
    /** Current SDK version exposed by core. */
    public const SDK_VERSION = 1;

    /** Minimum SDK version core still supports. */
    public const MIN_SDK_VERSION = 1;

    /** Named filter events (single source of truth for tests & docs). */
    public const EVENT_FILTER_HEAD_OUTPUT    = 'onAiBoostFilterHeadOutput';
    public const EVENT_FILTER_SITEMAP_URL_SET = 'onAiBoostFilterSitemapUrlSet';
    public const EVENT_FILTER_ROBOTS_RULES   = 'onAiBoostFilterRobotsRules';
    public const EVENT_FILTER_OG_TAGS        = 'onAiBoostFilterOgTags';
    public const EVENT_FILTER_SOCIAL_PROPS   = 'onAiBoostFilterSocialProps';
    public const EVENT_FILTER_SCHEMA_BLOCKS  = 'onAiBoostFilterSchemaBlocks';
    public const EVENT_FILTER_LLMS_TXT       = 'onAiBoostFilterLlmsTxt';

    /** Discovery event — bridges return IntegrationDescriptor instances. */
    public const EVENT_REGISTER_INTEGRATION  = 'onAiBoostRegisterIntegration';

    /**
     * Check whether a bridge's declared SDK version is supported by this core.
     *
     * @param int $bridgeSdkVersion
     * @return bool True when core can talk to this bridge.
     */
    public static function isCompatible(int $bridgeSdkVersion): bool
    {
        return $bridgeSdkVersion >= self::MIN_SDK_VERSION
            && $bridgeSdkVersion <= self::SDK_VERSION;
    }
}
