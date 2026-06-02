<?php

namespace AiBoost\Tests\Service;

use AiBoost\Plugin\System\AiBoostSitemap\Service\ImageSitemapExtension;
use PHPUnit\Framework\TestCase;

/**
 * Confirms ImageSitemapExtension can be instantiated with an injected base URL.
 * No Joomla bootstrap, no Uri:: calls.
 */
final class ImageSitemapExtensionTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedBaseUrl(): void
    {
        $service = new ImageSitemapExtension('https://example.com');

        $this->assertInstanceOf(ImageSitemapExtension::class, $service);
    }

    public function testAcceptsBaseUrlWithSubdirectory(): void
    {
        $service = new ImageSitemapExtension('https://example.com/joomla');

        $this->assertInstanceOf(ImageSitemapExtension::class, $service);
    }
}
