<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Integration;

use AiBoost\Lib\Integration\IntegrationDescriptor;
use AiBoost\Lib\Integration\Sdk;
use PHPUnit\Framework\TestCase;

final class IntegrationDescriptorTest extends TestCase
{
    public function testFromArrayDefaultsArePopulated(): void
    {
        $d = IntegrationDescriptor::fromArray(['key' => 'foo']);
        self::assertSame('foo', $d->key);
        self::assertSame('aiboost_int_foo', $d->pluginElement);
        self::assertSame(Sdk::SDK_VERSION, $d->sdkVersion);
        self::assertSame('plugin', $d->hostType);
        self::assertSame('icon-puzzle', $d->icon);
        self::assertSame([], $d->claimsSlots);
    }

    public function testFromArrayPreservesExplicitFields(): void
    {
        $d = IntegrationDescriptor::fromArray([
            'key'           => 'falang',
            'pluginElement' => 'aiboost_int_falang',
            'label'         => 'Falang Pro',
            'vendor'        => 'Falang',
            'category'      => 'Multilingual',
            'hostType'      => 'component',
            'hostElement'   => 'com_falang',
            'sdkVersion'    => 1,
            'claimsSlots'   => ['hreflang', 'canonical', 42, null],
        ]);
        self::assertSame('Falang Pro', $d->label);
        self::assertSame('Multilingual', $d->category);
        self::assertSame('com_falang', $d->hostElement);
        // claimsSlots filters out non-string entries.
        self::assertSame(['hreflang', 'canonical'], $d->claimsSlots);
    }

    public function testToLegacyArrayShape(): void
    {
        $d = IntegrationDescriptor::fromArray([
            'key'         => 'demo',
            'label'       => 'Demo',
            'vendor'      => 'Acme',
            'category'    => 'Other',
            'description' => 'd',
            'hostType'    => 'plugin',
            'hostElement' => 'demo',
            'hostFolder'  => 'system',
        ]);
        $legacy = $d->toLegacyArray();
        self::assertSame('Demo', $legacy['name']);
        self::assertSame('Acme', $legacy['vendor']);
        self::assertSame('plugin', $legacy['type']);
        self::assertSame('demo', $legacy['element']);
        self::assertSame('system', $legacy['folder']);
        self::assertSame('addon', $legacy['status_type']);
    }

    public function testSdkCompatibility(): void
    {
        self::assertTrue(Sdk::isCompatible(Sdk::SDK_VERSION));
        self::assertTrue(Sdk::isCompatible(Sdk::MIN_SDK_VERSION));
        self::assertFalse(Sdk::isCompatible(Sdk::SDK_VERSION + 1));
        self::assertFalse(Sdk::isCompatible(0));
    }
}
