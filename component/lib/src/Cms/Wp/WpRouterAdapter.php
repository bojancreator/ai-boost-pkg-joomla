<?php
/**
 * AI Boost — WpRouterAdapter (placeholder)
 *
 * v2.0 WordPress implementation. Routes through home_url() / add_query_arg()
 * and $_SERVER['REQUEST_URI'] when WordPress functions are loaded; falls
 * back to safe defaults otherwise so it does not crash the WP loader.
 *
 * @package     AiBoost\Lib\Cms\Wp
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Wp;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\RouterAdapter;

final class WpRouterAdapter implements RouterAdapter
{
    public function getSiteRoot(): string
    {
        if (function_exists('home_url')) {
            return rtrim((string) \home_url('/'), '/');
        }
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        return $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? '');
    }

    public function getCurrentUrl(): string
    {
        return $this->getSiteRoot() . (string) ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public function buildUrl(string $pathOrQuery, bool $absolute = true): string
    {
        if (function_exists('home_url')) {
            $url = (string) \home_url('/' . ltrim($pathOrQuery, '/'));
            return $absolute ? $url : (string) parse_url($url, PHP_URL_PATH);
        }
        return $absolute ? ($this->getSiteRoot() . '/' . ltrim($pathOrQuery, '/')) : ('/' . ltrim($pathOrQuery, '/'));
    }
}
