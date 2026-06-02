<?php
/**
 * AI Boost — Health override: InfoSchemaBreadcrumbProActive
 *
 * Auto-generated stub by scripts/codegen-from-manifest.py for manifest
 * health id `info_schema_breadcrumb_pro_active` (field key=`schema_breadcrumb_pro`). NEVER overwritten, so it's safe
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

final class InfoSchemaBreadcrumbProActive
{
    public const HEALTH_ID   = 'info_schema_breadcrumb_pro_active';
    public const SETTING_KEY = 'schema_breadcrumb_pro';
    public const CATEGORY    = 'Schema';
    public const LABEL       = 'Enhanced BreadcrumbList (Pro)';

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
                ? 'Expected artifact: application/ld+json with @type=BreadcrumbList including itemListElement[].image.'
                : 'Option is disabled — no artifact emitted.',
        ];
    }
}
