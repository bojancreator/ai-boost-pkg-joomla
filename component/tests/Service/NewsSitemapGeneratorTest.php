<?php

namespace AiBoost\Tests\Service;

use AiBoost\Plugin\System\AiBoostSitemap\Service\NewsSitemapGenerator;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Confirms NewsSitemapGenerator can be instantiated with injected DatabaseInterface.
 * No Joomla bootstrap, no Factory:: calls.
 */
final class NewsSitemapGeneratorTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedDependencies(): void
    {
        $db = $this->createMock(DatabaseInterface::class);

        $service = new NewsSitemapGenerator(
            'https://example.com',
            5,
            'My Publication',
            $db
        );

        $this->assertInstanceOf(NewsSitemapGenerator::class, $service);
    }

    public function testAcceptsZeroCategoryId(): void
    {
        $db = $this->createMock(DatabaseInterface::class);

        $service = new NewsSitemapGenerator(
            'https://example.com',
            0,
            'AI Boost News',
            $db
        );

        $this->assertInstanceOf(NewsSitemapGenerator::class, $service);
    }
}
