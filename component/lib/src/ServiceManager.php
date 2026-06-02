<?php
/**
 * AI Boost Shared Library — Service Manager
 *
 * Lightweight registry that maps service identifiers to factory callables.
 * Services are instantiated lazily on first get() call and cached thereafter.
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Lib;

defined('_JEXEC') or die;

class ServiceManager
{
    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /**
     * Register a service factory.
     *
     * @param string   $id      Service identifier (typically the FQCN).
     * @param callable $factory Callable that receives this ServiceManager and returns the service.
     */
    public function register(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Resolve a service by identifier.
     *
     * @param string $id Service identifier.
     * @return object
     * @throws \RuntimeException When no factory is registered for $id.
     */
    public function get(string $id): object
    {
        if (!isset($this->instances[$id])) {
            if (!isset($this->factories[$id])) {
                throw new \RuntimeException(
                    sprintf('[AiBoost] ServiceManager: no factory registered for "%s".', $id)
                );
            }

            $instance = ($this->factories[$id])($this);

            if (!is_object($instance)) {
                throw new \RuntimeException(
                    sprintf('[AiBoost] ServiceManager: factory for "%s" did not return an object.', $id)
                );
            }

            $this->instances[$id] = $instance;
        }

        return $this->instances[$id];
    }

    /**
     * Check whether a factory or cached instance exists for the given id.
     */
    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }
}
