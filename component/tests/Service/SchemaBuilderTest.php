<?php

namespace AiBoost\Tests\Service;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Plugin\System\AiBoostSchema\Service\SchemaBuilder;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Confirms SchemaBuilder can be instantiated with injected interfaces only.
 * No Joomla bootstrap, no Factory:: calls.
 */
final class SchemaBuilderTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedDependencies(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $db  = $this->createMock(DatabaseInterface::class);

        $service = new SchemaBuilder([], $ctx, $db);

        $this->assertInstanceOf(SchemaBuilder::class, $service);
    }

    public function testFreeBaselineWithOrgName(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getBaseUrl')->willReturn('https://example.com');
        $ctx->method('isHomepage')->willReturn(false);
        $ctx->method('getPathway')->willReturn([]);
        $db  = $this->createMock(DatabaseInterface::class);

        $service = new SchemaBuilder(['org_name' => 'Test Org'], $ctx, $db);
        $blocks  = $service->buildAll();

        $this->assertNotEmpty($blocks);
        $this->assertSame('Organization', $blocks[0]['@type']);
        $this->assertSame('Test Org', $blocks[0]['name']);
    }
}
