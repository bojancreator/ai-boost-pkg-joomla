<?php
/**
 * AI Boost — WpFilesystemAdapter (WordPress placeholder)
 *
 * Stub implementation of FilesystemAdapter. Maps siteRoot() to ABSPATH
 * and adminRoot() to WP_PLUGIN_DIR/aiboost when those constants are
 * available; otherwise returns empty strings and treats every file as
 * missing. Real impl is v2.0 WordPress port work.
 *
 * @package     AiBoost\Lib\Cms\Wp
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Wp;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\FilesystemAdapter;

final class WpFilesystemAdapter implements FilesystemAdapter
{
    public function siteRoot(): string
    {
        return defined('ABSPATH') ? rtrim((string) constant('ABSPATH'), '/') : '';
    }

    public function adminRoot(): string
    {
        if (defined('WP_PLUGIN_DIR')) {
            return rtrim((string) constant('WP_PLUGIN_DIR'), '/') . '/aiboost';
        }
        return '';
    }

    public function sitePath(string $relative): string
    {
        return $this->siteRoot() . '/' . ltrim($relative, '/');
    }

    public function adminPath(string $relative): string
    {
        return $this->adminRoot() . '/' . ltrim($relative, '/');
    }

    public function siteFileExists(string $relative): bool
    {
        $root = $this->siteRoot();
        return $root !== '' && file_exists($this->sitePath($relative));
    }

    public function readSiteFile(string $relative): ?string
    {
        $path = $this->sitePath($relative);
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        return $contents === false ? null : $contents;
    }
}
