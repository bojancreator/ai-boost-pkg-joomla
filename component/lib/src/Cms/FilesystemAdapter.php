<?php
/**
 * AI Boost — FilesystemAdapter
 *
 * Thin boundary around the host CMS's filesystem layout. Lib services
 * need to read site-relative files (robots.txt, sitemap.xml,
 * configuration.php) and admin-relative files (LicenseValidator,
 * ConflictManager) without hard-coding JPATH_ROOT / JPATH_ADMINISTRATOR
 * constants. The Joomla impl maps to those constants; the WP port will
 * map to ABSPATH and the plugin directory respectively.
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

interface FilesystemAdapter
{
    /**
     * Absolute path to the public site root (JPATH_ROOT on Joomla,
     * ABSPATH on WordPress).
     */
    public function siteRoot(): string;

    /**
     * Absolute path to the admin/plugin root that ships AI Boost code
     * (JPATH_ADMINISTRATOR on Joomla, WP_PLUGIN_DIR on WordPress).
     */
    public function adminRoot(): string;

    /**
     * Resolve a path relative to siteRoot().
     */
    public function sitePath(string $relative): string;

    /**
     * Resolve a path relative to adminRoot().
     */
    public function adminPath(string $relative): string;

    /**
     * file_exists() against a path relative to siteRoot().
     */
    public function siteFileExists(string $relative): bool;

    /**
     * file_get_contents() against a path relative to siteRoot();
     * returns null on failure rather than throwing.
     */
    public function readSiteFile(string $relative): ?string;
}
