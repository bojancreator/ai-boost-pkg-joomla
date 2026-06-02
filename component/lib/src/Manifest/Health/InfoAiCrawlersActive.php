<?php
/**
 * AI Boost — Health override: InfoAiCrawlersActive
 *
 * Auto-generated stub by scripts/codegen-from-manifest.py for manifest
 * health id `info_ai_crawlers_active` (field key=`ai_crawlers_enabled`). NEVER overwritten, so it's safe
 * to replace the default `evaluate()` with real pass/fail logic.
 *
 * When this class exists, HealthCheckService::registerFromManifest()
 * calls evaluate($settings, $ctx) and uses the returned struct in place
 * of the always-pass default. Return an associative array with keys:
 *   pass (bool), message (string|null), fix_actions (array|null).
 * Any key omitted falls back to the manifest declaration.
 *
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Manifest\Health;

defined('_JEXEC') or die;

use AiBoost\Lib\AppContextInterface;

final class InfoAiCrawlersActive
{
    public const HEALTH_ID   = 'info_ai_crawlers_active';
    public const SETTING_KEY = 'ai_crawlers_enabled';
    public const CATEGORY    = 'AEO';
    public const LABEL       = 'Enable AI crawler rules';

    /**
     * Evaluate the check. Default: report pass=true whenever the option
     * is enabled (same as the manifest-driven runtime fallback). Replace
     * with real probing logic to validate the expected HTML artifact.
     *
     * @param array<string,mixed>  $settings
     * @return array{pass?: bool, message?: string, fix_actions?: array<int,array<string,string>>}
     */
    public static function evaluate(array $settings, AppContextInterface $ctx): array
    {
        $on = !empty($settings[self::SETTING_KEY]);
        return [
            'pass'    => $on,
            'message' => $on
                ? 'Expected artifact: robots.txt section "# AI Crawler Rules — AI Boost (per-bot configuration)" with User-agent + Allow/Disallow blocks.'
                : 'Option is disabled — no artifact emitted.',
        ];
    }
}
