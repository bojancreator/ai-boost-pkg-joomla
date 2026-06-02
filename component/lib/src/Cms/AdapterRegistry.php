<?php
/**
 * AI Boost — AdapterRegistry
 *
 * Process-wide registry of CMS adapter instances. Lib services that
 * cannot easily take adapters through their constructor (e.g. static
 * helpers like PluginRegistry, LicenseHeartbeat, LanguageDetector)
 * resolve their CMS dependencies through this registry instead of
 * reaching for Factory:: directly.
 *
 * Defaults to the Joomla implementations — those are lazily constructed
 * on first use. Bootstrap code (admin component, plugin extension
 * classes, unit tests, future WP plugin loader) may call set*Adapter()
 * to inject an alternative.
 *
 * This is the ONLY static touchpoint that hard-codes Joomla\Cms\Joomla
 * class names; everything else in lib/ programs against the interfaces
 * in AiBoost\Lib\Cms.
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\Joomla\JoomlaApplicationAdapter;
use AiBoost\Lib\Cms\Joomla\JoomlaClockAdapter;
use AiBoost\Lib\Cms\Joomla\JoomlaDatabaseAdapter;
use AiBoost\Lib\Cms\Joomla\JoomlaDocumentAdapter;
use AiBoost\Lib\Cms\Joomla\JoomlaEventDispatcherAdapter;
use AiBoost\Lib\Cms\Joomla\JoomlaFilesystemAdapter;
use AiBoost\Lib\Cms\Joomla\JoomlaHttpAdapter;
use AiBoost\Lib\Cms\Joomla\JoomlaRouterAdapter;

final class AdapterRegistry
{
    private static ?DatabaseAdapter        $database    = null;
    private static ?HttpAdapter            $http        = null;
    private static ?FilesystemAdapter      $filesystem  = null;
    private static ?ApplicationAdapter     $application = null;
    private static ?ClockAdapter           $clock       = null;
    private static ?EventDispatcherAdapter $events      = null;
    private static ?DocumentAdapter        $document    = null;
    private static ?RouterAdapter          $router      = null;

    public static function database(): DatabaseAdapter
    {
        return self::$database ??= new JoomlaDatabaseAdapter();
    }

    public static function http(): HttpAdapter
    {
        return self::$http ??= new JoomlaHttpAdapter();
    }

    public static function filesystem(): FilesystemAdapter
    {
        return self::$filesystem ??= new JoomlaFilesystemAdapter();
    }

    public static function application(): ApplicationAdapter
    {
        return self::$application ??= new JoomlaApplicationAdapter();
    }

    public static function clock(): ClockAdapter
    {
        return self::$clock ??= new JoomlaClockAdapter();
    }

    public static function events(): EventDispatcherAdapter
    {
        return self::$events ??= new JoomlaEventDispatcherAdapter();
    }

    public static function document(): DocumentAdapter
    {
        return self::$document ??= new JoomlaDocumentAdapter();
    }

    public static function router(): RouterAdapter
    {
        return self::$router ??= new JoomlaRouterAdapter();
    }

    public static function setDatabase(?DatabaseAdapter $adapter): void
    {
        self::$database = $adapter;
    }

    public static function setHttp(?HttpAdapter $adapter): void
    {
        self::$http = $adapter;
    }

    public static function setFilesystem(?FilesystemAdapter $adapter): void
    {
        self::$filesystem = $adapter;
    }

    public static function setApplication(?ApplicationAdapter $adapter): void
    {
        self::$application = $adapter;
    }

    public static function setClock(?ClockAdapter $adapter): void
    {
        self::$clock = $adapter;
    }

    public static function setEvents(?EventDispatcherAdapter $adapter): void
    {
        self::$events = $adapter;
    }

    public static function setDocument(?DocumentAdapter $adapter): void
    {
        self::$document = $adapter;
    }

    public static function setRouter(?RouterAdapter $adapter): void
    {
        self::$router = $adapter;
    }

    /** Reset all adapters (for tests). */
    public static function reset(): void
    {
        self::$database    = null;
        self::$http        = null;
        self::$filesystem  = null;
        self::$application = null;
        self::$clock       = null;
        self::$events      = null;
        self::$document    = null;
        self::$router      = null;
    }
}
