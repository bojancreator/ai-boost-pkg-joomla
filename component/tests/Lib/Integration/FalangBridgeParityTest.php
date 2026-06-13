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
 * Parity + tier-fence snapshot for the single-plugin "Multilang" integration
 * (Plan 2a Workstream C). ONE element `aiboost_int_falang` is PURE Pro:
 *
 *   - The six falang_* settings keys are registered ALWAYS (outside the Pro
 *     fence) so a plain Settings save never drops them, all tier='pro',
 *     sku='int_falang' (Manifest\Registry locks them until Multilang is active).
 *   - All runtime HEAD/sitemap emission is fenced with the build's Pro-strip
 *     markers and gated on hasPro('int_falang').
 *
 * The build ships this element TWICE: free (Pro stripped → discovery shell that
 * emits nothing but KEEPS the six keys) and full (the Lemon Squeezy product that
 * upgrades it in place). These tests assert both halves of that contract.
 */
final class FalangBridgeParityTest extends TestCase
{
    private function classFile(): string
    {
        return dirname(__DIR__, 3)
            . '/plugins/system/aiboost_int_falang/src/Extension/AiBoostIntFalang.php';
    }

    private function source(): string
    {
        return (string) file_get_contents($this->classFile());
    }

    /** Apply the build's Pro-strip regex (mirrors scripts/build-package-zip.py). */
    private function strippedSource(): string
    {
        $out = (string) preg_replace(
            '/^[ \t]*\/\/[ \t]*@pro:start.*?^[ \t]*\/\/[ \t]*@pro:end[^\n]*\n?/sm',
            '',
            $this->source()
        );
        return (string) preg_replace('/^[ \t]*\/\/[ \t]*@pro:start.*\z/sm', '', $out);
    }

    public function testExtendsAbstractIntegrationPlugin(): void
    {
        self::assertTrue(
            is_subclass_of(
                \AiBoost\Plugin\System\AiBoostIntFalang\Extension\AiBoostIntFalang::class,
                AbstractIntegrationPlugin::class
            )
        );
        self::assertStringContainsString(
            'protected function describe(): IntegrationDescriptor',
            $this->source()
        );
    }

    public function testDescriptorIdentity(): void
    {
        $rc       = new ReflectionClass(\AiBoost\Plugin\System\AiBoostIntFalang\Extension\AiBoostIntFalang::class);
        $method   = $rc->getMethod('describe');
        $method->setAccessible(true);
        $instance = $rc->newInstanceWithoutConstructor();
        /** @var IntegrationDescriptor $desc */
        $desc = $method->invoke($instance);

        // Internal slug / host identity is FROZEN; only the display label moved.
        self::assertSame('falang', $desc->key);
        self::assertSame('aiboost_int_falang', $desc->pluginElement);
        self::assertSame('Multilang', $desc->label, 'Display name re-tiered to "Multilang".');
        self::assertSame('component', $desc->hostType);
        self::assertSame('com_falang', $desc->hostElement);
        self::assertSame(Sdk::SDK_VERSION, $desc->sdkVersion);
        self::assertContains(ConflictManager::SLOT_HREFLANG, $desc->claimsSlots);
    }

    public function testContributesSixPureProFields(): void
    {
        $rc       = new ReflectionClass(\AiBoost\Plugin\System\AiBoostIntFalang\Extension\AiBoostIntFalang::class);
        $instance = $rc->newInstanceWithoutConstructor();
        $fields   = $instance->onAiBoostRegisterFields();

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

        // Multilang is pure Pro — every field is tier='pro', sku='int_falang'.
        foreach ($fields as $f) {
            self::assertSame('falang', $f['integration']);
            self::assertSame('pro', $f['tier'], "{$f['key']} must be tier=pro");
            self::assertSame('int_falang', $f['sku'], "{$f['key']} must be SKU int_falang");
            self::assertArrayHasKey('label', $f);
            self::assertArrayHasKey('default', $f);
        }
    }

    public function testFullSourceGatesOnProAndKeepsDiscipline(): void
    {
        $code = php_strip_whitespace($this->classFile());

        self::assertStringContainsString("hasPro('int_falang')", $code, 'Emission gates on the Multilang licence.');
        self::assertStringContainsString('$this->isActive()', $code, 'Master toggle + host gate is honoured.');
        self::assertStringContainsString('addHeadLink', $code, 'hreflang flows through the native head stream.');
        self::assertStringContainsString("noteNative('hreflang')", $code, 'native head ownership is registered for ConflictManager.');
        self::assertDoesNotMatchRegularExpression('/addCustomTag\s*\(/', $code, 'never addCustomTag() for head content.');
        self::assertDoesNotMatchRegularExpression('/str_replace\s*\(\s*[\'"]<\/head>/i', $code, 'never splice </head>.');
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

    public function testStrippedFreeBuildKeepsSixKeysButEmitsNothing(): void
    {
        $stripped = $this->strippedSource();

        // The verifier's criterion: zero @pro tokens survive in the free build.
        self::assertDoesNotMatchRegularExpression('/@pro\b/', $stripped, 'free build must carry no @pro tokens');

        // CRITICAL save-integrity property: a pure-Pro integration must STILL
        // register all six keys in the stripped Free build, so a Settings save
        // (whitelist built from a live onAiBoostRegisterFields dispatch) never
        // drops them. This is the opposite of YOOtheme (which has a free field).
        self::assertStringContainsString('function onAiBoostRegisterFields', $stripped);
        foreach ([
            'falang_hreflang_head', 'falang_hreflang_sitemap', 'falang_hreflang_mode',
            'falang_schema_translate', 'falang_og_translate', 'falang_primary_language',
        ] as $key) {
            self::assertStringContainsString("'" . $key . "'", $stripped, "free build must keep key {$key}");
        }

        // …but every runtime emission method is gone.
        self::assertStringNotContainsString('function proOn', $stripped);
        self::assertStringNotContainsString('function onBeforeCompileHead', $stripped);
        self::assertStringNotContainsString('function onAiBoostBeforeSitemapBuild', $stripped);
        self::assertStringNotContainsString('function injectHreflangTags', $stripped);
        self::assertStringNotContainsString('addHeadLink', $stripped);
        self::assertStringNotContainsString("hasPro('int_falang')", $stripped);

        // And the stripped result is still syntactically valid PHP.
        $tmp = tempnam(sys_get_temp_dir(), 'ab_falang_strip_') . '.php';
        file_put_contents($tmp, $stripped);
        $out = [];
        $rc  = 0;
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
        @unlink($tmp);
        self::assertSame(0, $rc, 'stripped free build must be valid PHP: ' . implode("\n", $out));
    }

    /**
     * Three-way alignment: the visible `falang_hreflang_head` field must
     * actually gate the head hreflang output (Plan 1 fix). Comment-stripped so
     * the docblock naming the legacy param can't mask a regression.
     */
    public function testHeadPathConsumesHreflangHeadSetting(): void
    {
        $code = php_strip_whitespace($this->classFile());
        self::assertStringContainsString(
            "aiBoostSetting('falang_hreflang_head'",
            $code,
            'The head hreflang gate must read the manifest-backed falang_hreflang_head setting.'
        );
    }
}
