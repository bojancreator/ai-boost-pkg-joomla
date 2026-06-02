<?php
/**
 * @package     AiBoost\Plugin\System\AiBoostHreflang
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostHreflang\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Shared helper utilities for the AI Boost Hreflang plugin.
 */
final class PluginHelper
{
    public static function siteBaseUrl(): string
    {
        $uri = Uri::getInstance();
        return $uri->getScheme() . '://' . $uri->getHost();
    }

    public static function isStagingMode(\Joomla\Registry\Registry $params): bool
    {
        return (bool) $params->get('staging_mode', 0);
    }

    public static function isEnabled(\Joomla\Registry\Registry $params, string $key = 'enabled'): bool
    {
        return (bool) $params->get($key, 1);
    }

    /**
     * Returns published Joomla site languages as [{lang_id, lang_code, sef, title}].
     *
     * @return array<array{lang_id:string,lang_code:string,sef:string,title:string}>
     */
    public static function detectedLanguages(): array
    {
        try {
            $result = [];
            foreach (LanguageHelper::getLanguages('published') as $lang) {
                $code = (string) ($lang->lang_code ?? '');
                $sef  = strtolower(trim((string) ($lang->sef ?? '')));
                if (!$code || !$sef) {
                    continue;
                }
                $result[] = [
                    'lang_id'   => (string) ($lang->lang_id ?? ''),
                    'lang_code' => $code,
                    'sef'       => $sef,
                    'title'     => (string) ($lang->title ?? $code),
                ];
            }
            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Strips the SEF language prefix from the given URL path.
     *
     * @param  string   $path  Absolute URL path (e.g. /en/my-article)
     * @param  array    $langs Language list from detectedLanguages()
     * @return string          Path without language prefix (e.g. /my-article)
     */
    public static function stripLangPrefix(string $path, array $langs): string
    {
        $path = '/' . ltrim($path, '/');
        foreach (array_column($langs, 'sef') as $sef) {
            if (!$sef) {
                continue;
            }
            if (str_starts_with($path, '/' . $sef . '/')) {
                return substr($path, strlen('/' . $sef));
            }
            if ($path === '/' . $sef) {
                return '/';
            }
        }
        return $path;
    }
}
