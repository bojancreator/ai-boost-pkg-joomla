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
 * Parity snapshot — the Falang bridge refactor onto AbstractIntegrationPlugin
 * (Task #486) must preserve the exact field surface and descriptor identity
 * the legacy hardcoded bridge exposed. If any of these change, downstream
 * code (Health, Schema, OG, sitemap hreflang) silently drops fields.
 */
final class FalangBridgeParityTest extends TestCase
{
    public function testFalangPluginExtendsAbstractIntegrationPlugin(): void
    {
        $classFile = dirname(__DIR__, 3) . '/plugins/system/aiboost_int_falang/src/Extension/AiBoostIntFalang.php';
        self::assertFileExists($classFile);
        $contents = file_get_contents($classFile);

        self::assertStringContainsString(
            'extends AbstractIntegrationPlugin',
            $contents,
            'Falang must inherit from the SDK base so discovery + slot claim flow through one code path.'
        );
        self::assertStringContainsString(
            'protected function describe(): IntegrationDescriptor',
            $contents,
            'Falang must implement describe() per the SDK contract.'
        );
    }

    public function testFalangDescriptorIdentityMatchesLegacy(): void
    {
        $rc = new ReflectionClass(\AiBoost\Plugin\System\AiBoostIntFalang\Extension\AiBoostIntFalang::class);
        // describe() is protected; reflect to invoke without booting the
        // full plugin (which needs Joomla DI).
        $method = $rc->getMethod('describe');
        $method->setAccessible(true);

        // Build a bare instance via reflection to avoid the
        // Joomla\Event\DispatcherInterface constructor dependency.
        $instance = $rc->newInstanceWithoutConstructor();
        /** @var IntegrationDescriptor $desc */
        $desc = $method->invoke($instance);

        self::assertSame('falang', $desc->key);
        self::assertSame('aiboost_int_falang', $desc->pluginElement);
        self::assertSame('Falang', $desc->vendor);
        self::assertSame('Multilingual', $desc->category);
        self::assertSame('component', $desc->hostType);
        self::assertSame('com_falang', $desc->hostElement);
        self::assertSame(Sdk::SDK_VERSION, $desc->sdkVersion);
        self::assertContains(ConflictManager::SLOT_HREFLANG, $desc->claimsSlots);
    }

    public function testFalangContributesExpectedManifestFields(): void
    {
        $rc = new ReflectionClass(\AiBoost\Plugin\System\AiBoostIntFalang\Extension\AiBoostIntFalang::class);
        $instance = $rc->newInstanceWithoutConstructor();
        $fields = $instance->onAiBoostRegisterFields();

        self::assertCount(6, $fields);

        $expectedKeys = [
            'falang_hreflang_head',
            'falang_hreflang_sitemap',
            'falang_hreflang_mode',
            'falang_schema_translate',
            'falang_og_translate',
            'falang_primary_language',
        ];
        self::assertSame($expectedKeys, array_column($fields, 'key'));

        foreach ($fields as $f) {
            self::assertSame('falang', $f['integration']);
            self::assertSame('free', $f['tier']);
            self::assertSame('core', $f['sku']);
            self::assertArrayHasKey('label', $f);
            self::assertArrayHasKey('default', $f);
        }
    }

    public function testFalangIsAnAbstractIntegrationPluginSubclass(): void
    {
        self::assertTrue(
            is_subclass_of(
                \AiBoost\Plugin\System\AiBoostIntFalang\Extension\AiBoostIntFalang::class,
                AbstractIntegrationPlugin::class
            )
        );
    }

    /**
     * Three-way alignment: the `falang_hreflang_head` field the admin sees must
     * actually gate the head hreflang output (Plan 1 fix). Before, the head
     * path read only the legacy `falang_hreflang_enabled` plugin param, so the
     * visible toggle was dead. Check the comment-stripped code so the
     * explanatory docblock that names the legacy param can't mask a regression.
     */
    public function testHeadPathConsumesHreflangHeadSetting(): void
    {
        $file = dirname(__DIR__, 3) . '/plugins/system/aiboost_int_falang/src/Extension/AiBoostIntFalang.php';
        $code = php_strip_whitespace($file);

        self::assertStringContainsString(
            "aiBoostSetting('falang_hreflang_head'",
            $code,
            'The head hreflang gate must read the manifest-backed falang_hreflang_head setting.'
        );
    }
}
