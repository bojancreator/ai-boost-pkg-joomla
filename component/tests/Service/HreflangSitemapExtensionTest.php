<?php

namespace AiBoost\Tests\Service;

use AiBoost\Plugin\System\AiBoostSitemap\Service\HreflangSitemapExtension;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Confirms HreflangSitemapExtension can be instantiated with injected DatabaseInterface.
 * No Joomla bootstrap, no Factory:: calls.
 */
final class HreflangSitemapExtensionTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedDependencies(): void
    {
        $db = $this->createMock(DatabaseInterface::class);

        $service = new HreflangSitemapExtension('https://example.com', $db);

        $this->assertInstanceOf(HreflangSitemapExtension::class, $service);
    }

    public function testAcceptsCustomDefaultLanguage(): void
    {
        $db = $this->createMock(DatabaseInterface::class);

        $service = new HreflangSitemapExtension('https://example.com', $db, 'de-DE');

        $this->assertInstanceOf(HreflangSitemapExtension::class, $service);
    }
}
