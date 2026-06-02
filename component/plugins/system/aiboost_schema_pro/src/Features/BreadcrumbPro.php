<?php
/**
 * AI Boost — Pro feature handler: BreadcrumbPro
 *
 * Auto-generated stub by scripts/codegen-from-manifest.py for manifest
 * key `schema_breadcrumb_pro` (tier=pro, sku=schema). The codegen script will NEVER
 * overwrite this file once it exists, so it is safe to fill in real
 * Pro logic below the `// @pro:start` marker.
 *
 * Label  : Enhanced BreadcrumbList (Pro)
 * Purpose: Emit a richer BreadcrumbList with per-item images and structured position metadata. Free tier emits the basic BreadcrumbList.
 *
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Plugin\System\AiBoostSchemaPro\Features;

defined('_JEXEC') or die;

final class BreadcrumbPro
{
    /** Manifest key that gates this handler. */
    public const SETTING_KEY = 'schema_breadcrumb_pro';

    /**
     * Return true if this Pro feature is enabled in #__aiboost_settings.
     *
     * @param array<string,mixed> $settings  Decoded settings_json blob.
     */
    public static function isEnabled(array $settings): bool
    {
        return !empty($settings[self::SETTING_KEY]);
    }

    // @pro:start
    /**
     * Apply this feature's effect. Called by the parent Pro plugin's
     * event handler (e.g. onBeforeCompileHead). Replace this stub with
     * real logic; the // @pro:start ... // @pro:end markers guarantee
     * the block is stripped out of the free package by build-package-zip.py.
     *
     * @param array<string,mixed> $settings
     */
    public static function apply(array $settings): void
    {
        // TODO: implement Pro logic for schema_breadcrumb_pro.
    }
    // @pro:end
}
