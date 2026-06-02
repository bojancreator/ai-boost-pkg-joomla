<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Integration;

use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\Integration\IntegrationDescriptor;
use AiBoost\Lib\Integration\IntegrationRegistry;
use AiBoost\Lib\Integration\Sdk;
use AiBoost\Tests\Lib\Integration\Support\InMemoryEventDispatcher;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/InMemoryEventDispatcher.php';

final class IntegrationRegistryDiscoveryTest extends TestCase
{
    private InMemoryEventDispatcher $bus;

    protected function setUp(): void
    {
        IntegrationRegistry::reset();
        $this->bus = new InMemoryEventDispatcher();
        AdapterRegistry::setEvents($this->bus);
    }

    protected function tearDown(): void
    {
        IntegrationRegistry::reset();
        AdapterRegistry::setEvents(null);
    }

    public function testDiscoversDescriptorObjectAndArrayShorthand(): void
    {
        $this->bus->on(
            Sdk::EVENT_REGISTER_INTEGRATION,
            static fn () => new IntegrationDescriptor(
                key: 'falang',
                pluginElement: 'aiboost_int_falang',
                label: 'Falang',
                vendor: 'Falang',
                category: 'Multilingual',
                description: '',
                hostType: 'component',
                hostElement: 'com_falang',
                sdkVersion: 1
            )
        );
        $this->bus->on(
            Sdk::EVENT_REGISTER_INTEGRATION,
            static fn () => ['key' => 'k2', 'label' => 'K2', 'sdkVersion' => 1, 'hostElement' => 'com_k2']
        );

        $keys = IntegrationRegistry::keys();
        sort($keys);
        self::assertSame(['falang', 'k2'], $keys);
        self::assertTrue(IntegrationRegistry::has('falang'));
        self::assertSame('K2', IntegrationRegistry::get('k2')->label);
    }

    public function testSdkMismatchIsRefusedAndSurfacedForHealth(): void
    {
        $this->bus->on(
            Sdk::EVENT_REGISTER_INTEGRATION,
            static fn () => new IntegrationDescriptor(
                key: 'futurebridge',
                pluginElement: 'aiboost_int_futurebridge',
                label: 'Future Bridge',
                vendor: 'Future',
                category: 'Other',
                description: '',
                hostType: 'plugin',
                hostElement: 'plg_x',
                sdkVersion: Sdk::SDK_VERSION + 5
            )
        );
        $this->bus->on(
            Sdk::EVENT_REGISTER_INTEGRATION,
            static fn () => new IntegrationDescriptor(
                key: 'goodbridge',
                pluginElement: 'aiboost_int_goodbridge',
                label: 'Good Bridge',
                vendor: 'Good',
                category: 'Other',
                description: '',
                hostType: 'plugin',
                hostElement: 'plg_y',
                sdkVersion: 1
            )
        );

        self::assertSame(['goodbridge'], IntegrationRegistry::keys());

        $mismatches = IntegrationRegistry::getSdkMismatches();
        self::assertCount(1, $mismatches);
        self::assertSame('futurebridge', $mismatches[0]['key']);
        self::assertSame(Sdk::SDK_VERSION, $mismatches[0]['core_sdk_version']);
        self::assertStringContainsString('SDK', $mismatches[0]['reason']);
    }

    public function testFirstRegistrationWinsOnDuplicateKey(): void
    {
        $this->bus->on(
            Sdk::EVENT_REGISTER_INTEGRATION,
            static fn () => ['key' => 'falang', 'label' => 'First', 'sdkVersion' => 1, 'hostElement' => 'a']
        );
        $this->bus->on(
            Sdk::EVENT_REGISTER_INTEGRATION,
            static fn () => ['key' => 'falang', 'label' => 'Second', 'sdkVersion' => 1, 'hostElement' => 'b']
        );

        self::assertSame('First', IntegrationRegistry::get('falang')->label);
    }

    public function testResetClearsCacheAndMismatches(): void
    {
        $this->bus->on(
            Sdk::EVENT_REGISTER_INTEGRATION,
            static fn () => new IntegrationDescriptor(
                key: 'x',
                pluginElement: 'aiboost_int_x',
                label: 'X',
                vendor: 'X',
                category: 'Other',
                description: '',
                hostType: 'plugin',
                hostElement: 'plg',
                sdkVersion: 99
            )
        );
        IntegrationRegistry::all();
        self::assertNotEmpty(IntegrationRegistry::getSdkMismatches());

        IntegrationRegistry::reset();
        $this->bus->listeners = [];
        self::assertSame([], IntegrationRegistry::keys());
        self::assertSame([], IntegrationRegistry::getSdkMismatches());
    }
}
