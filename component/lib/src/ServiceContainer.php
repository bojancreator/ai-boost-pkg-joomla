<?php
/**
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class ServiceContainer
{
    /** @var ServiceInterface[] */
    private array $services = [];

    /** @var array<string, class-string<ServiceInterface>> */
    private array $serviceMap = [];

    public function __construct(
        private readonly ?AppContextInterface $ctx,
        private readonly DatabaseInterface    $db,
        private readonly Registry             $params
    ) {}

    public function register(string $key, string $className): void
    {
        $this->serviceMap[$key] = $className;
    }

    public function get(string $key): ServiceInterface
    {
        if (!isset($this->services[$key])) {
            if (!isset($this->serviceMap[$key])) {
                throw new \InvalidArgumentException("Unknown service: {$key}");
            }
            $class                = $this->serviceMap[$key];
            $this->services[$key] = new $class($this->ctx, $this->params);
        }
        return $this->services[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->serviceMap[$key]);
    }

    public function clearCache(): void
    {
        $this->services = [];
    }
}
