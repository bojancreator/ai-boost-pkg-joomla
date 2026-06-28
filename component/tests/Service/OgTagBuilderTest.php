<?php

namespace AiBoost\Tests\Service;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Plugin\System\AiBoostSocial\Service\OgTagBuilder;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * Confirms OgTagBuilder can be instantiated with injected interfaces only.
 * No Joomla bootstrap, no Factory:: calls.
 *
 * Note: the Free OgTagBuilder ctor no longer accepts an $isPro flag or a
 * TranslationService — Pro enrichment has moved to aiboost_social_pro's
 * OgTagProDecorator which is invoked via `EVENT_FILTER_SOCIAL_PROPS`.
 */
final class OgTagBuilderTest extends TestCase
{
    public function testCanBeInstantiatedWithInjectedDependencies(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $db  = $this->createMock(DatabaseInterface::class);

        $service = new OgTagBuilder([], $ctx, $db);

        $this->assertInstanceOf(OgTagBuilder::class, $service);
    }

    public function testBuildPropsReturnsStructuredArray(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $db  = $this->createMock(DatabaseInterface::class);

        $ctx->method('getCurrentOption')->willReturn('com_content');
        $ctx->method('getCurrentView')->willReturn('featured');
        $ctx->method('getCurrentId')->willReturn(0);
        $ctx->method('getSiteName')->willReturn('Test Site');
        $ctx->method('getPageTitle')->willReturn('Test Page');
        $ctx->method('getPageDescription')->willReturn('Test description');
        $ctx->method('getCurrentUrl')->willReturn('https://example.test/');
        $ctx->method('getBaseUrl')->willReturn('https://example.test');
        $ctx->method('getActiveLanguage')->willReturn('en-GB');

        $service = new OgTagBuilder(['site_name' => 'My Site'], $ctx, $db);
        $props   = $service->buildProps();

        $this->assertArrayHasKey('og', $props);
        $this->assertArrayHasKey('tw', $props);
        $this->assertArrayHasKey('context', $props);
        $this->assertSame('website', $props['og']['og:type']);
        $this->assertSame('My Site', $props['og']['og:site_name']);
    }

    /**
     * The default OG image alt text is emitted as og:image:alt when both the
     * image and the alt setting are present (Free, sitewide).
     */
    public function testDefaultOgImageAltEmittedAsImageAlt(): void
    {
        $ctx = $this->stubContext();
        $db  = $this->createMock(DatabaseInterface::class);

        $props = (new OgTagBuilder([
            'default_og_image'     => 'images/og-default.png',
            'default_og_image_alt' => 'Our brand banner',
        ], $ctx, $db))->buildProps();

        $this->assertArrayHasKey('og:image', $props['og']);
        $this->assertSame('Our brand banner', $props['og']['og:image:alt'] ?? null);

        // No alt set → no og:image:alt tag (addProp skips empty values).
        $bare = (new OgTagBuilder(['default_og_image' => 'images/og-default.png'], $ctx, $db))->buildProps();
        $this->assertArrayNotHasKey('og:image:alt', $bare['og']);
    }

    /**
     * `enable_opengraph` defaults to on; `enable_og` is exposed so renderProps
     * can gate the og:* block.
     */
    public function testEnableOgDefaultsOnAndReflectsSetting(): void
    {
        $ctx = $this->stubContext();
        $db  = $this->createMock(DatabaseInterface::class);

        $on = (new OgTagBuilder([], $ctx, $db))->buildProps();
        $this->assertTrue($on['enable_og'], 'enable_og should default to true when enable_opengraph is unset');

        $off = (new OgTagBuilder(['enable_opengraph' => '0'], $ctx, $db))->buildProps();
        $this->assertFalse($off['enable_og'], 'enable_og should be false when enable_opengraph = 0');
    }

    /**
     * The master OG switch: when enable_og is false, renderProps emits no og:*
     * tags but still emits Twitter Card tags (governed separately).
     */
    public function testRenderPropsMasterOgSwitchSuppressesOnlyOgTags(): void
    {
        $props = [
            'og'             => ['og:type' => 'website', 'og:title' => 'Hello'],
            'tw'             => ['twitter:card' => 'summary_large_image', 'twitter:title' => 'Hello'],
            'enable_og'      => false,
            'enable_twitter' => true,
        ];

        $tags = OgTagBuilder::renderProps($props);
        $joined = implode("\n", $tags);

        $this->assertStringNotContainsString('property="og:', $joined, 'og:* tags must be suppressed when enable_og is false');
        $this->assertStringContainsString('name="twitter:card"', $joined, 'Twitter Card tags must still render');
    }

    /**
     * Sanity: with both master switches on, og:* and twitter:* both render.
     */
    public function testRenderPropsEmitsBothWhenEnabled(): void
    {
        $props = [
            'og'             => ['og:title' => 'Hello'],
            'tw'             => ['twitter:title' => 'Hello'],
            'enable_og'      => true,
            'enable_twitter' => true,
        ];

        $joined = implode("\n", OgTagBuilder::renderProps($props));

        $this->assertStringContainsString('property="og:title"', $joined);
        $this->assertStringContainsString('name="twitter:title"', $joined);
    }

    /**
     * B8 (order 0006) — Joomla 4+ media fields append a "#joomlaImage://…?width=…"
     * fragment. normaliseImagePath() must strip it so the og:image URL is clean and
     * getimagesize() can read the real file.
     *
     * Red-green: before the fix the fragment survived into the path (and into the
     * emitted og:image URL); this asserts it is gone.
     */
    public function testNormaliseImagePathStripsJoomlaImageFragment(): void
    {
        $raw = 'images/galrija-ture/petrus2025/petrus2025.jpg'
            . '#joomlaImage:/local-images/galrija-ture/petrus2025/petrus2025.jpg?width=1080&height=1350';

        $clean = OgTagBuilder::normaliseImagePath($raw);

        $this->assertSame('images/galrija-ture/petrus2025/petrus2025.jpg', $clean);
        $this->assertStringNotContainsString('#joomlaImage', $clean);
        $this->assertStringNotContainsString('?width=', $clean);

        // Clean paths pass through unchanged.
        $this->assertSame('images/x.jpg', OgTagBuilder::normaliseImagePath('images/x.jpg'));

        // The fragment is also stripped when the value arrives in the JSON
        // media-field shape {"imagefile":"…#joomlaImage:…"}.
        $json = '{"imagefile":"images/x.jpg#joomlaImage:/local-images/x.jpg?width=1#"}';
        $this->assertSame('images/x.jpg', OgTagBuilder::normaliseImagePath($json));
    }

    /**
     * B8 end-to-end: a default_og_image carrying the joomlaImage fragment must
     * emit a clean og:image / twitter:image URL (no fragment leaks into output).
     */
    public function testOgImageUrlCarriesNoJoomlaImageFragment(): void
    {
        $ctx = $this->stubContext();
        $db  = $this->createMock(DatabaseInterface::class);

        $props = (new OgTagBuilder([
            'default_og_image' => 'images/x.jpg#joomlaImage:/local-images/x.jpg?width=1080&height=1350',
        ], $ctx, $db))->buildProps();

        $this->assertArrayHasKey('og:image', $props['og']);
        $this->assertStringNotContainsString('#joomlaImage', $props['og']['og:image']);
        $this->assertStringNotContainsString('#joomlaImage', $props['tw']['twitter:image'] ?? '');
    }

    private function stubContext(): AppContextInterface
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getCurrentOption')->willReturn('com_content');
        $ctx->method('getCurrentView')->willReturn('featured');
        $ctx->method('getCurrentId')->willReturn(0);
        $ctx->method('getSiteName')->willReturn('Test Site');
        $ctx->method('getPageTitle')->willReturn('Test Page');
        $ctx->method('getPageDescription')->willReturn('Test description');
        $ctx->method('getCurrentUrl')->willReturn('https://example.test/');
        $ctx->method('getBaseUrl')->willReturn('https://example.test');
        $ctx->method('getActiveLanguage')->willReturn('en-GB');

        return $ctx;
    }
}
