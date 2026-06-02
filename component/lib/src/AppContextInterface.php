<?php
/**
 * AI Boost — AppContextInterface
 *
 * Contracts for accessing the host CMS application context.
 * Implemented by JoomlaAppContext (v1.0) and WpAppContext (v2.0).
 * All Service classes must depend on this interface — never on Joomla or WP APIs directly.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or defined('ABSPATH') or die;

interface AppContextInterface
{
    public function getCurrentUrl(): string;

    public function getBaseUrl(): string;

    public function getSiteName(): string;

    public function getActiveLanguage(): string;

    /** Site default language as configured in CMS settings (e.g. 'en-GB'). */
    public function getDefaultLanguage(): string;

    public function isAdmin(): bool;

    public function isHomepage(): bool;

    public function getCurrentView(): string;

    public function getCurrentOption(): string;

    public function getCurrentId(): int;

    public function getPageTitle(): string;

    /** Meta description of the current page as set by the CMS document layer. */
    public function getPageDescription(): string;

    /**
     * Translate a language constant (e.g. 'HOME') using the CMS i18n layer.
     * Falls back to the key itself when no translation is found.
     */
    public function translate(string $key): string;

    /** @return array<int,array{name:string,link:string}> */
    public function getPathway(): array;

    /**
     * Read a global CMS configuration value (e.g. 'live_site', 'debug', 'offset').
     * Falls back to $default when the key is not found.
     */
    public function getConfigValue(string $key, string $default = ''): string;

    /**
     * Return the timezone identifier for the current user (e.g. 'America/New_York').
     * Falls back to the site-wide timezone or 'UTC' when not set.
     */
    public function getUserTimezone(): string;
}
