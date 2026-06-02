<?php
/**
 * AI Boost — JoomlaRouterAdapter
 *
 * Joomla implementation of RouterAdapter. Wraps Uri / Route::_().
 *
 * @package     AiBoost\Lib\Cms\Joomla
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Joomla;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\RouterAdapter;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

final class JoomlaRouterAdapter implements RouterAdapter
{
    public function getSiteRoot(): string
    {
        try {
            return rtrim((string) Uri::root(), '/');
        } catch (\Throwable) {
            return '';
        }
    }

    public function getCurrentUrl(): string
    {
        try {
            return (string) Uri::getInstance()->toString();
        } catch (\Throwable) {
            return '';
        }
    }

    public function buildUrl(string $pathOrQuery, bool $absolute = true): string
    {
        try {
            $url = (string) Route::_($pathOrQuery, false, 0, true);
            if (!$absolute) {
                return $url;
            }
            if (preg_match('#^https?://#i', $url)) {
                return $url;
            }
            return $this->getSiteRoot() . '/' . ltrim($url, '/');
        } catch (\Throwable) {
            return $pathOrQuery;
        }
    }
}
