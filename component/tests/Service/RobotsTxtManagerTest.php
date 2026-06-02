<?php

namespace AiBoost\Tests\Service;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Plugin\System\AiBoostAeo\Service\RobotsTxtManager;
use PHPUnit\Framework\TestCase;

/**
 * Confirms RobotsTxtManager can be instantiated with injected AppContextInterface.
 * No Joomla bootstrap, no Factory:: calls.
 */
final class RobotsTxtManagerTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedDependencies(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getBaseUrl')->willReturn('https://example.com');

        $service = new RobotsTxtManager([], $ctx);

        $this->assertInstanceOf(RobotsTxtManager::class, $service);
    }

    public function testAcceptsScraperSettings(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getBaseUrl')->willReturn('https://example.com');

        $settings = [
            'scraper_ahrefsbot' => '1',
            'scraper_dotbot'    => '1',
        ];

        $service = new RobotsTxtManager($settings, $ctx);

        $this->assertInstanceOf(RobotsTxtManager::class, $service);
    }
}
