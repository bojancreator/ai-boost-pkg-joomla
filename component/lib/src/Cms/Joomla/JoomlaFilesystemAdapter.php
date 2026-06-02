<?php
/**
 * AI Boost — JoomlaFilesystemAdapter
 *
 * Joomla implementation of FilesystemAdapter. Maps siteRoot() to
 * JPATH_ROOT and adminRoot() to JPATH_ADMINISTRATOR.
 *
 * @package     AiBoost\Lib\Cms\Joomla
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Joomla;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\FilesystemAdapter;

final class JoomlaFilesystemAdapter implements FilesystemAdapter
{
    public function siteRoot(): string
    {
        return defined('JPATH_ROOT') ? JPATH_ROOT : '';
    }

    public function adminRoot(): string
    {
        return defined('JPATH_ADMINISTRATOR') ? JPATH_ADMINISTRATOR : '';
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
        return file_exists($this->sitePath($relative));
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
