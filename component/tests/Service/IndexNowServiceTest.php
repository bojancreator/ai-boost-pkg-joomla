<?php

namespace AiBoost\Tests\Service;

use AiBoost\Plugin\System\AiBoostAeo\Service\IndexNowService;
use PHPUnit\Framework\TestCase;

/**
 * Confirms IndexNowService can be instantiated with scalar injected values only.
 * No Joomla bootstrap, no Factory:: or Uri:: calls.
 */
final class IndexNowServiceTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedValues(): void
    {
        $service = new IndexNowService('test-api-key-abc123', 'https://example.com');

        $this->assertInstanceOf(IndexNowService::class, $service);
    }

    public function testAcceptsEmptyApiKey(): void
    {
        $service = new IndexNowService('', 'https://example.com');

        $this->assertInstanceOf(IndexNowService::class, $service);
    }
}
