<?php
/**
 * AI Boost Shared Library — Service Autoloader
 *
 * Convenience wrapper for registering multiple services into a ServiceManager
 * from a structured map. Plugins call ServiceAutoloader::register() once at
 * boot to avoid repeated boilerplate.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or die;

class ServiceAutoloader
{
    /**
     * Register multiple services from a map of id → factory callable.
     *
     * @param ServiceManager          $manager  The target service manager.
     * @param array<string, callable> $services Map of service id to factory.
     */
    public static function register(ServiceManager $manager, array $services): void
    {
        foreach ($services as $id => $factory) {
            if (!is_callable($factory)) {
                error_log(sprintf('[AiBoost] ServiceAutoloader: factory for "%s" is not callable — skipped.', $id));
                continue;
            }

            $manager->register($id, $factory);
        }
    }
}
