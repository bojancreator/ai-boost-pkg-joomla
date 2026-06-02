<?php

namespace AiBoost\Tests\Service;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Plugin\System\AiBoostAeo\Service\LlmsTxtGenerator;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Confirms LlmsTxtGenerator can be instantiated with injected interfaces only.
 * No Joomla bootstrap, no Factory:: calls.
 */
final class LlmsTxtGeneratorTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedDependencies(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getBaseUrl')->willReturn('https://example.com');

        $db      = $this->createMock(DatabaseInterface::class);
        $service = new LlmsTxtGenerator([], $ctx, $db);

        $this->assertInstanceOf(LlmsTxtGenerator::class, $service);
    }

    public function testAcceptsSettingsArray(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getBaseUrl')->willReturn('https://example.com');

        $db = $this->createMock(DatabaseInterface::class);

        $settings = [
            'org_name'        => 'ACME Corp',
            'org_description' => 'We build things',
        ];

        $service = new LlmsTxtGenerator($settings, $ctx, $db);

        $this->assertInstanceOf(LlmsTxtGenerator::class, $service);
    }
}
