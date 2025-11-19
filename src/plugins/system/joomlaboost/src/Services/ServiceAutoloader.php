<?php

/**
 * Service Autoloader for JoomlaBoost Plugin
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

\defined('_JEXEC') or die;

/**
 * Optimized Service Autoloader
 *
 * Features:
 * - PSR-4 autoloading
 * - Performance optimized file loading
 * - Dependency resolution
 * - Memory efficient
 */
class ServiceAutoloader
{
    /** @var array<string, string> Service class map for performance */
    private static array $classMap = [
        'ServiceInterface' => 'ServiceInterface.php',
        'AbstractService' => 'AbstractService.php',
        'ServiceContainer' => 'ServiceContainer.php',
        'PerformanceService' => 'PerformanceService.php',
        'DomainDetectionService' => 'DomainDetectionService.php',
        'RobotService' => 'RobotService.php',
        'SitemapService' => 'SitemapService.php',
        'SchemaService' => 'SchemaService.php',
        'OpenGraphService' => 'OpenGraphService.php',
        'AnalyticsService' => 'AnalyticsService.php',
        'HreflangService' => 'HreflangService.php',
        'InjectionService' => 'InjectionService.php',
        'HealthService' => 'HealthService.php',
        'MetaPixelService' => 'MetaPixelService.php'
    ];

    /** @var string Base services directory */
    private static string $baseDir;

    /** @var bool Whether autoloader is registered */
    private static bool $registered = false;

    /**
     * Register autoloader
     */
    public static function register(string $baseDir): void
    {
        if (self::$registered) {
            return;
        }

        self::$baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register([self::class, 'autoload'], true, true);
        self::$registered = true;
    }

    /**
     * Autoload service classes
     */
    public static function autoload(string $className): bool
    {
        $prefix = 'JoomlaBoost\\Plugin\\System\\JoomlaBoost\\Services\\';

        // Check if this is our namespace
        if (strpos($className, $prefix) !== 0) {
            return false;
        }

        // Extract the relative class name
        $relativeClass = substr($className, strlen($prefix));

        // Check if we have this class in our map
        if (!isset(self::$classMap[$relativeClass])) {
            return false;
        }

        $file = self::$baseDir . self::$classMap[$relativeClass];

        // Load the file if it exists
        if (file_exists($file)) {
            require_once $file;
            return true;
        }

        return false;
    }

    /**
     * Load core services (performance critical ones first)
     */
    public static function loadCoreServices(): void
    {
        $coreServices = [
            'ServiceInterface',
            'AbstractService',
            'ServiceContainer',
            'PerformanceService',
            'DomainDetectionService'
        ];

        foreach ($coreServices as $service) {
            $className = 'JoomlaBoost\\Plugin\\System\\JoomlaBoost\\Services\\' . $service;
            if (!class_exists($className, false)) {
                self::autoload($className);
            }
        }
    }

    /**
     * Get loaded services count for debugging
     */
    public static function getLoadedServicesCount(): int
    {
        $count = 0;
        $prefix = 'JoomlaBoost\\Plugin\\System\\JoomlaBoost\\Services\\';

        foreach (self::$classMap as $service => $file) {
            $className = $prefix . $service;
            if (class_exists($className, false)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Unregister autoloader (for testing)
     */
    public static function unregister(): void
    {
        if (self::$registered) {
            spl_autoload_unregister([self::class, 'autoload']);
            self::$registered = false;
        }
    }
}
