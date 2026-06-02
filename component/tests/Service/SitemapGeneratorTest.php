<?php

namespace AiBoost\Tests\Service;

use AiBoost\Plugin\System\AiBoostSitemap\Service\SitemapGenerator;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Confirms SitemapGenerator can be instantiated with injected DatabaseInterface.
 * No Joomla bootstrap, no Factory:: calls.
 */
final class SitemapGeneratorTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedDependencies(): void
    {
        $db      = $this->createMock(DatabaseInterface::class);
        $service = new SitemapGenerator('https://example.com', $db);

        $this->assertInstanceOf(SitemapGenerator::class, $service);
    }

    public function testAllOptionalFlagsCanBeOverridden(): void
    {
        $db = $this->createMock(DatabaseInterface::class);

        $service = new SitemapGenerator(
            'https://example.com',
            $db,
            true,
            true,
            true,
            true,
            'daily',
            '0.6',
            '1.0',
            '0.9',
            '0.5',
            '0.3'
        );

        $this->assertInstanceOf(SitemapGenerator::class, $service);
    }
}
