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
            return 'en-GB';
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
            $app   = Factory::getApplication();
            $input = $app->getInput();
            $path  = ltrim(Uri::getInstance()->getPath(), '/');

            if ($path === '' || $path === 'index.php') {
                return true;
            }
            if ($input->get('option') === 'com_content' && $input->get('view') === 'featured') {
                return true;
            }
            return false;
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
