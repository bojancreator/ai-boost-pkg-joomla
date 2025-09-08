<?php

/**
 * Service Container for JoomlaBoost Plugin
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

use Joomla\CMS\Application\CMSApplication;
use Joomla\Registry\Registry;

/**
 * Advanced Service Container with Dependency Injection
 * 
 * Features:
 * - Lazy loading with caching
 * - Service dependency resolution
 * - Performance monitoring
 * - Memory optimization
 */
class ServiceContainer
{
    private CMSApplication $app;
    private Registry $params;

    /** @var ServiceInterface[] */
    private array $services = [];

    /** @var array<string, class-string<ServiceInterface>> */
    private array $serviceMap = [
        'domainDetection' => DomainDetectionService::class,
        'performance' => PerformanceService::class,
        'robot' => RobotService::class,
        'sitemap' => SitemapService::class,
        'schema' => SchemaService::class,
        'openGraph' => OpenGraphService::class,
        'analytics' => AnalyticsService::class,
        'hreflang' => HreflangService::class,
        'injection' => InjectionService::class,
        'health' => HealthService::class,
        'metaPixel' => MetaPixelService::class
    ];

    /** @var array<string, array<string>> Service dependencies */
    private array $dependencies = [
        'schema' => ['performance', 'domainDetection'],
        'openGraph' => ['performance', 'domainDetection'],
        'sitemap' => ['domainDetection'],
        'analytics' => ['domainDetection'],
        'hreflang' => ['domainDetection']
    ];

    /** @var int Service creation counter for performance monitoring */
    private int $serviceCreationCount = 0;

    public function __construct(CMSApplication $app, Registry $params)
    {
        $this->app = $app;
        $this->params = $params;
    }

    /**
     * Get service instance with dependency injection
     * 
     * @template T of ServiceInterface
     * @param class-string<T> $serviceKey
     * @return T
     */
    public function get(string $serviceKey): ServiceInterface
    {
        if (!isset($this->services[$serviceKey])) {
            $this->createService($serviceKey);
        }

        return $this->services[$serviceKey];
    }

    /**
     * Create service with dependency resolution
     */
    private function createService(string $serviceKey): void
    {
        if (!isset($this->serviceMap[$serviceKey])) {
            throw new \InvalidArgumentException("Unknown service: {$serviceKey}");
        }

        $className = $this->serviceMap[$serviceKey];

        // Resolve dependencies first
        if (isset($this->dependencies[$serviceKey])) {
            foreach ($this->dependencies[$serviceKey] as $dependency) {
                $this->get($dependency);
            }
        }

        // Create service instance
        /** @var ServiceInterface $service */
        $service = new $className($this->app, $this->params);
        
        // Inject dependencies if service supports it
        if (method_exists($service, 'setServiceContainer')) {
            $service->setServiceContainer($this);
        }

        $this->services[$serviceKey] = $service;
        $this->serviceCreationCount++;
    }

    /**
     * Get all enabled services
     * @return ServiceInterface[]
     */
    public function getEnabledServices(): array
    {
        $enabled = [];

        foreach ($this->serviceMap as $key => $className) {
            $service = $this->get($key);
            if ($service->isEnabled()) {
                $enabled[$key] = $service;
            }
        }

        return $enabled;
    }

    /**
     * Check if service is enabled without creating it
     */
    public function isServiceEnabled(string $serviceKey): bool
    {
        if (!isset($this->serviceMap[$serviceKey])) {
            return false;
        }

        // Quick check from params without instantiating service
        $enableKey = $this->getServiceEnableKey($serviceKey);
        return (bool) $this->params->get($enableKey, true);
    }

    /**
     * Get service enable key from service name
     */
    private function getServiceEnableKey(string $serviceKey): string
    {
        $keyMap = [
            'schema' => 'enable_schema',
            'openGraph' => 'enable_opengraph', 
            'analytics' => 'enable_analytics',
            'robot' => 'enable_robots',
            'sitemap' => 'enable_sitemap',
            'hreflang' => 'enable_hreflang',
            'injection' => 'enable_injection',
            'health' => 'enable_health',
            'metaPixel' => 'enable_meta_pixel',
            'performance' => 'enable_performance',
            'domainDetection' => 'enable_domain_detection'
        ];

        return $keyMap[$serviceKey] ?? "enable_{$serviceKey}";
    }

    /**
     * Get container performance metrics
     */
    public function getMetrics(): array
    {
        return [
            'services_created' => $this->serviceCreationCount,
            'services_cached' => count($this->services),
            'memory_usage' => memory_get_usage(true),
            'services_enabled' => count($this->getEnabledServices())
        ];
    }

    /**
     * Clear all cached services (useful for testing)
     */
    public function clearCache(): void
    {
        $this->services = [];
        $this->serviceCreationCount = 0;
    }

    /**
     * Magic method for convenient service access
     * Example: $container->performance() instead of $container->get('performance')
     */
    public function __call(string $name, array $arguments): ServiceInterface
    {
        if (isset($this->serviceMap[$name])) {
            return $this->get($name);
        }

        throw new \BadMethodCallException("Unknown service method: {$name}");
    }
}
