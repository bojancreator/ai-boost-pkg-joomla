<?php
/**
 * AI Boost — JoomlaAppContext
 *
 * Joomla implementation of AppContextInterface.
 * All Factory / Uri / CMSApplication calls are encapsulated here so that
 * Service classes remain free of Joomla dependencies.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class JoomlaAppContext implements AppContextInterface
{
    public function getCurrentUrl(): string
    {
        try {
            $uri = Uri::getInstance();
            return $uri->toString();
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getBaseUrl(): string
    {
        return rtrim((string) Uri::root(), '/');
    }

    public function getSiteName(): string
    {
        try {
            return (string) Factory::getApplication()->get('sitename', '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getActiveLanguage(): string
    {
        try {
            return Factory::getApplication()->getLanguage()->getTag();
        } catch (\Throwable $e) {
            // Inherit the site's configured default rather than hardcoding English.
            return $this->getDefaultLanguage();
        }
    }

    public function getDefaultLanguage(): string
    {
        try {
            return (string) Factory::getApplication()->get('language', 'en-GB');
        } catch (\Throwable $e) {
            return 'en-GB';
        }
    }

    public function isAdmin(): bool
    {
        try {
            return Factory::getApplication()->isClient('administrator');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isHomepage(): bool
    {
        try {
            $app = Factory::getApplication();

            // Authoritative signal: the ACTIVE menu item flagged as the site/
            // language home (`#__menu.home = 1`). This is the reliable Joomla way
            // — each (language) home is explicitly flagged, so it is correct on
            // SEF-off sites and on content pages of a site whose home is a
            // Featured / Single-Article menu item. The old path/featured heuristic
            // mis-fired site-wide (Uri::getPath()==='index.php' on non-SEF routes;
            // view=featured matches any blog page), emitting homepage-only schema
            // (WebSite/SearchAction) on every page.
            $menu = $app->getMenu();
            if ($menu !== null) {
                $active = $menu->getActive();
                if ($active !== null) {
                    return (int) ($active->home ?? 0) === 1;
                }
            }

            // Fallback ONLY when there is no active menu item (e.g. a component
            // route with no menu match): treat the bare site root as home.
            $path = ltrim(Uri::getInstance()->getPath(), '/');
            return $path === '' || $path === 'index.php';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getCurrentView(): string
    {
        try {
            return (string) Factory::getApplication()->getInput()->get('view', '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getCurrentOption(): string
    {
        try {
            return (string) Factory::getApplication()->getInput()->get('option', '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getCurrentId(): int
    {
        try {
            return (int) Factory::getApplication()->getInput()->get('id', 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getPageTitle(): string
    {
        try {
            $doc = Factory::getApplication()->getDocument();
            return $doc ? (string) $doc->getTitle() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getPageDescription(): string
    {
        try {
            $doc = Factory::getApplication()->getDocument();
            return $doc ? (string) $doc->getDescription() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function translate(string $key): string
    {
        try {
            return Text::_($key);
        } catch (\Throwable $e) {
            return $key;
        }
    }

    public function getPathway(): array
    {
        try {
            $pathway = Factory::getApplication()->getPathway();
            $result  = [];
            foreach ($pathway->getPathwayNames() as $i => $name) {
                $result[] = [
                    'name' => $name,
                    'link' => $pathway->getPathwayLinks()[$i] ?? '',
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getConfigValue(string $key, string $default = ''): string
    {
        try {
            return (string) Factory::getApplication()->get($key, $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function getUserTimezone(): string
    {
        try {
            $app  = Factory::getApplication();
            $user = $app->getIdentity();
            return $user
                ? (string) $user->getParam('timezone', $app->get('offset', 'UTC'))
                : (string) $app->get('offset', 'UTC');
        } catch (\Throwable $e) {
            return 'UTC';
        }
    }
}
