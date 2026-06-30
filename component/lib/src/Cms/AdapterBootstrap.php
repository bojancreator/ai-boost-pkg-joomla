<?php
/**
 * AI Boost — AdapterBootstrap
 *
 * Explicit Joomla-side wiring for the CMS adapter layer. Called from the
 * com_aiboost service provider and the aiboost_core plugin so the
 * AdapterRegistry is populated with adapters that wrap the *current*
 * CMSApplication (rather than relying on AdapterRegistry's lazy
 * Factory::getApplication() fallback).
 *
 * On WordPress the v2.0 loader will provide its own bootstrap that
 * registers WpXxxAdapter instances instead.
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
use AiBoost\Lib\JoomlaAppContext;
use AiBoost\Lib\Page\IndexabilityPolicy;
use AiBoost\Lib\Page\PageResolver;
use Joomla\CMS\Application\CMSApplication;

final class AdapterBootstrap
{
    private static bool $registered = false;

    /**
     * Register Joomla adapter implementations with AdapterRegistry.
     * Idempotent — safe to call from every plugin onAfterInitialise and
     * from the component service provider.
     *
     * @param CMSApplication|null $app Current application; pass null to
     *                                  let JoomlaApplicationAdapter resolve
     *                                  it lazily via Factory.
     */
    public static function registerJoomla(?CMSApplication $app = null): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        AdapterRegistry::setDatabase(new JoomlaDatabaseAdapter());
        AdapterRegistry::setHttp(new JoomlaHttpAdapter());
        AdapterRegistry::setFilesystem(new JoomlaFilesystemAdapter());
        AdapterRegistry::setApplication(new JoomlaApplicationAdapter($app));
        AdapterRegistry::setClock(new JoomlaClockAdapter());
        AdapterRegistry::setEvents(new JoomlaEventDispatcherAdapter());
        AdapterRegistry::setDocument(new JoomlaDocumentAdapter());
        AdapterRegistry::setRouter(new JoomlaRouterAdapter());

        // T1 page resolver (slice S0) — wired but consumed by nobody yet.
        AdapterRegistry::setPageResolver(new PageResolver(
            new JoomlaAppContext(),
            new IndexabilityPolicy(),
            AdapterRegistry::database()
        ));
    }

    /** Reset the "already registered" flag (for tests). */
    public static function reset(): void
    {
        self::$registered = false;
    }
}
