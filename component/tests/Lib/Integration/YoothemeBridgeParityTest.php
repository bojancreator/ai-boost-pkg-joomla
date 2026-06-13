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
 * Parity + tier-fence snapshot for the single-plugin YOOtheme integration
 * (Plan 2a, one-plugin model). ONE element `aiboost_int_yootheme` carries:
 *   - FREE: OpenGraph/meta override (gate isActive() only);
 *   - PRO (int_yootheme): Schema.org (FAQ/gallery/mapping) + sitemap exclusion,
 *     fenced with the build's Pro-strip markers and gated on hasPro('int_yootheme').
 *
 * The build ships this element TWICE: free (Pro blocks stripped) and full (the
 * Lemon Squeezy product that upgrades it in place). These tests assert the full
 * source has both tiers AND that stripping the Pro fences yields a clean,
 * schema-free, still-valid free build.
 */
final class YoothemeBridgeParityTest extends TestCase
{
    private function classFile(): string
    {
        return dirname(__DIR__, 3)
            . '/plugins/system/aiboost_int_yootheme/src/Extension/AiBoostIntYootheme.php';
    }

    private function source(): string
    {
        return (string) file_get_contents($this->classFile());
    }

    /** Apply the build's Pro-strip regex (mirrors scripts/build-package-zip.py). */
    private function strippedSource(): string
    {
        return (string) preg_replace(
            '/^[ \t]*\/\/[ \t]*@pro:start.*?^[ \t]*\/\/[ \t]*@pro:end[^\n]*\n?/sm',
            '',
            $this->source()
        );
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
        self::assertSame('template', $desc->hostType);
        self::assertSame('yootheme', $desc->hostElement);
        self::assertSame(Sdk::SDK_VERSION, $desc->sdkVersion);
        self::assertContains(ConflictManager::SLOT_SCHEMA_FAQ, $desc->claimsSlots);
    }

    public function testFullPluginContributesFreeOgFieldPlusFiveProFields(): void
    {
        $rc       = new ReflectionClass(\AiBoost\Plugin\System\AiBoostIntYootheme\Extension\AiBoostIntYootheme::class);
        $instance = $rc->newInstanceWithoutConstructor();
        $fields   = $instance->onAiBoostRegisterFields();

        $byKey = [];
        foreach ($fields as $f) {
            $byKey[$f['key']] = $f;
        }

        // Free OG override.
        self::assertArrayHasKey('yootheme_meta_override', $byKey);
        self::assertSame('free', $byKey['yootheme_meta_override']['tier']);

        // Five Pro schema fields, all tier=pro + sku=int_yootheme.
        $proKeys = [
            'yootheme_faq_enabled',
            'yootheme_gallery_enabled',
            'yootheme_schema_mapping',
            'yootheme_accordion_selector',
            'yootheme_sitemap_exclude_builder',
        ];
        foreach ($proKeys as $k) {
            self::assertArrayHasKey($k, $byKey, "missing Pro field {$k}");
            self::assertSame('pro', $byKey[$k]['tier'], "{$k} must be tier=pro");
            self::assertSame('int_yootheme', $byKey[$k]['sku'], "{$k} must be SKU int_yootheme");
            self::assertSame('yootheme', $byKey[$k]['integration']);
        }
        self::assertCount(6, $fields);
    }

    public function testFullSourceRoutesSchemaThroughHeadBlockAndGatesOnPro(): void
    {
        $code = php_strip_whitespace($this->classFile());

        self::assertStringContainsString("hasPro('int_yootheme')", $code, 'Pro schema gates on the per-integration licence');
        self::assertStringContainsString('HeadBlockBuilder::pushSection', $code, 'menu-param schema flows through the head block');
        self::assertStringContainsString('onAiBoostFilterHeadOutput', $code, 'body-dependent schema uses the SDK filter');
        self::assertStringNotContainsString('license_tier', $code, 'no dead per-tier licence model');
        self::assertDoesNotMatchRegularExpression('/str_replace\s*\(\s*[\'"]<\/head>/i', $code, 'never splice </head>');
        self::assertDoesNotMatchRegularExpression('/addCustomTag\s*\(/', $code, 'never addCustomTag()');
    }

    public function testProStripFencesAreBalanced(): void
    {
        $src = $this->source();
        self::assertSame(
            substr_count($src, '// @pro:start'),
            substr_count($src, '// @pro:end'),
            'every // @pro:start must have a matching // @pro:end'
        );
        self::assertGreaterThan(0, substr_count($src, '// @pro:start'), 'Pro code must be fenced');
    }

    public function testStrippedFreeBuildHasNoProTokensOrSchema(): void
    {
        $stripped = $this->strippedSource();

        // The verifier's criterion: zero @pro tokens survive in the free build.
        self::assertDoesNotMatchRegularExpression('/@pro\b/', $stripped, 'free build must carry no @pro tokens');

        // Schema layer is gone…
        self::assertStringNotContainsString('function proOn', $stripped);
        self::assertStringNotContainsString('function onBeforeCompileHead', $stripped);
        self::assertStringNotContainsString('function onAiBoostFilterHeadOutput', $stripped);
        self::assertStringNotContainsString('buildAccordionFaqSchema', $stripped);
        self::assertStringNotContainsString("hasPro('int_yootheme')", $stripped);

        // …but the free OG override remains.
        self::assertStringContainsString('function onAfterRoute', $stripped);
        self::assertStringContainsString('yootheme_meta_override', $stripped);
        self::assertStringNotContainsString('yootheme_faq_enabled', $stripped);

        // And the stripped result is still syntactically valid PHP.
        $tmp = tempnam(sys_get_temp_dir(), 'ab_strip_') . '.php';
        file_put_contents($tmp, $stripped);
        $out = [];
        $rc  = 0;
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
        @unlink($tmp);
        self::assertSame(0, $rc, 'stripped free build must be valid PHP: ' . implode("\n", $out));
    }
}
