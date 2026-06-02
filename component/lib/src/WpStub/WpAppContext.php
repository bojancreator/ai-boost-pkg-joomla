<?php
/**
 * AI Boost — WpAppContext (WordPress placeholder)
 *
 * Stub implementation of AppContextInterface for the future WordPress port.
 * All methods return safe defaults — real implementation is v2.0 work.
 *
 * @package     AiBoost\Lib\WpStub
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib\WpStub;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\AppContextInterface;

class WpAppContext implements AppContextInterface
{
    // TODO: WP port — implement all methods using WordPress APIs ($wp, get_bloginfo, etc.)

    public function getCurrentUrl(): string      { return ''; }
    public function getBaseUrl(): string         { return ''; }
    public function getSiteName(): string        { return ''; }
    public function getActiveLanguage(): string  { return 'en-GB'; }
    public function getDefaultLanguage(): string { return 'en-GB'; }
    public function isAdmin(): bool              { return false; }
    public function isHomepage(): bool           { return false; }
    public function getCurrentView(): string     { return ''; }
    public function getCurrentOption(): string   { return ''; }
    public function getCurrentId(): int          { return 0; }
    public function getPageTitle(): string       { return ''; }
    public function getPageDescription(): string { return ''; }
    public function translate(string $key): string { return $key; }
    public function getPathway(): array          { return []; }
    public function getConfigValue(string $key, string $default = ''): string { return $default; }
    public function getUserTimezone(): string    { return 'UTC'; }
}
