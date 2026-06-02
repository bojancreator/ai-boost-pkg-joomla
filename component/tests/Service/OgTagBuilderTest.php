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
}
