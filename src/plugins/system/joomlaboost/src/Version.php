<?php

/**
 * JoomlaBoost Version
 *
 * Single source of truth for plugin version.
 * Updated automatically by the build script.
 *
 * @package     JoomlaBoost
 * @since       0.20.0
 * @copyright   (C) 2025 JoomlaBoost
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost;

final class Version
{
    /**
     * Plugin version — kept in sync with joomlaboost.xml <version> tag.
     * The build script (_build_zip.ps1) auto-updates this constant.
     */
    public const VERSION = '0.21.5';

    /**
     * Get the version string.
     */
    public static function get(): string
    {
        return self::VERSION;
    }
}
