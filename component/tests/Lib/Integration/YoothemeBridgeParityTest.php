<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Integration;

use AiBoost\Lib\ConflictManager;
use AiBoost\Lib\Integration\AbstractIntegrationPlugin;
use AiBoost\Lib\Integration\IntegrationDescriptor;
use AiBoost\Lib\Integration\Sdk;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Parity snapshot for the SDK YOOtheme bridge (Plan 1). The migration off the
 * legacy `aiboost_yootheme` bridge MUST keep the descriptor identity + field
 * surface, drop the dead `license_tier` gate, and route JSON-LD through the
 * head block (never a regex `</head>` splice).
 */
final class YoothemeBridgeParityTest extends TestCase
{
    private function classFile(): string
    {
        return dirname(__DIR__, 3)
            . '/plugins/system/aiboost_int_yootheme/src/Extension/AiBoostIntYootheme.php';
    }

    /**
     * Source with comments + whitespace removed, so the forbidden-pattern
     * checks below test actual CODE — the docblock legitimately names
     * license_tier / addCustomTag to explain what the bridge avoids.
     */
    private function codeOnly(): string
    {
        return php_strip_whitespace($this->classFile());
    }

    public function testExtendsAbstractIntegrationPlugin(): void
    {
        self::assertTrue(
            is_subclass_of(
                \AiBoost\Plugin\System\AiBoostIntYootheme\Extension\AiBoostIntYootheme::class,
                AbstractIntegrationPlugin::class
            )
        );
    }

    public function testDescriptorIdentity(): void
    {
        $rc       = new ReflectionClass(\AiBoost\Plugin\System\AiBoostIntYootheme\Extension\AiBoostIntYootheme::class);
        $method   = $rc->getMethod('describe');
        $method->setAccessible(true);
        $instance = $rc->newInstanceWithoutConstructor();
        /** @var IntegrationDescriptor $desc */
        $desc = $method->invoke($instance);

        self::assertSame('yootheme', $desc->key);
        self::assertSame('aiboost_int_yootheme', $desc->pluginElement);
        self::assertSame('YOOtheme', $desc->vendor);
        self::assertSame('Page Builder', $desc->category);
        self::assertSame('template', $desc->hostType);
        self::assertSame('yootheme', $desc->hostElement);
        self::assertSame(Sdk::SDK_VERSION, $desc->sdkVersion);
        self::assertContains(ConflictManager::SLOT_SCHEMA_FAQ, $desc->claimsSlots);
    }

    public function testContributesExpectedManifestFields(): void
    {
        $rc       = new ReflectionClass(\AiBoost\Plugin\System\AiBoostIntYootheme\Extension\AiBoostIntYootheme::class);
        $instance = $rc->newInstanceWithoutConstructor();
        $fields   = $instance->onAiBoostRegisterFields();

        self::assertCount(6, $fields);

        $expectedKeys = [
            'yootheme_faq_enabled',
            'yootheme_gallery_enabled',
            'yootheme_schema_mapping',
            'yootheme_accordion_selector',
            'yootheme_meta_override',
            'yootheme_sitemap_exclude_builder',
        ];
        self::assertSame($expectedKeys, array_column($fields, 'key'));

        foreach ($fields as $f) {
            self::assertSame('yootheme', $f['integration']);
            self::assertArrayHasKey('label', $f);
            self::assertArrayHasKey('default', $f);
        }
    }

    public function testDeadLicenseTierGateIsGone(): void
    {
        $code = $this->codeOnly();

        self::assertStringNotContainsString(
            'license_tier',
            $code,
            'The SDK bridge must not resurrect the dead per-tier licence model.'
        );
        self::assertStringContainsString(
            "hasPro('int_yootheme')",
            $code,
            'Runtime emission must gate on the canonical perpetual-activation Pro check.'
        );
    }

    public function testJsonLdRoutesThroughHeadBlockNeverRegexHead(): void
    {
        $code = $this->codeOnly();

        self::assertStringContainsString(
            'HeadBlockBuilder::pushSection',
            $code,
            'Menu-param schema must flow through the consolidated head block.'
        );
        self::assertStringContainsString(
            'onAiBoostFilterHeadOutput',
            $code,
            'Body-dependent schema must use the SDK head-output filter.'
        );
        self::assertDoesNotMatchRegularExpression(
            '/str_replace\s*\(\s*[\'"]<\/head>/i',
            $code,
            'The bridge must never splice into </head> directly.'
        );
        self::assertDoesNotMatchRegularExpression(
            '/addCustomTag\s*\(/',
            $code,
            'The bridge must not call addCustomTag() for head content.'
        );
    }
}
