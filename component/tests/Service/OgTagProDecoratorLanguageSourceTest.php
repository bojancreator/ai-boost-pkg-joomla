<?php

declare(strict_types=1);

namespace AiBoost\Tests\Service;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Page\PageContext;
use AiBoost\Lib\Page\PageType;
use AiBoost\Plugin\System\AiBoostSocial\Service\OgTagProDecorator;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * T1 · S6 — the Social Pro decorator reads the active page language from the
 * single resolver source (PageContext::language), not ad-hoc from
 * AppContextInterface::getActiveLanguage().
 *
 * og:locale is the observable language-driven output. Here the resolved
 * PageContext carries 'de-DE' while the raw $ctx active language is 'en-GB';
 * the emitted og:locale must follow the PageContext (de_DE). On the pre-S6 code
 * (which read $ctx->getActiveLanguage()) this would be en_GB → the test fails,
 * which is the red-green proof that the consumer now sources language from the
 * resolver. With pageContext null (unit/standalone callers) it byte-identically
 * falls back to $ctx, so the existing standalone test-og-pro-decorator is
 * unaffected.
 */
final class OgTagProDecoratorLanguageSourceTest extends TestCase
{
    /** @return array<string,mixed> */
    private function baseProps(): array
    {
        return [
            'og' => [
                'og:title'       => 'T',
                'og:description' => 'D',
                'og:type'        => 'website',
                'og:url'         => 'https://site.test/',
            ],
            'tw'             => ['twitter:card' => 'summary'],
            'enable_twitter' => true,
            'context'        => ['option' => '', 'view' => '', 'id' => 0],
        ];
    }

    private function pageContext(string $language): PageContext
    {
        return new PageContext(
            type:                  PageType::UNKNOWN,
            entityKind:            '',
            entityId:              0,
            option:                '',
            view:                  '',
            rawId:                 0,
            isHomepage:            false,
            language:              $language,
            siteDefaultLanguage:   'en-GB',
            globalDefaultLanguage: 'en-GB',
            canonical:             'https://site.test/',
            indexable:             true,
            noindexReason:         '',
        );
    }

    public function testOgLocaleFollowsPageContextLanguageNotCtx(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getActiveLanguage')->willReturn('en-GB');   // raw ctx says en-GB
        $ctx->method('getSiteName')->willReturn('Site');
        $ctx->method('getBaseUrl')->willReturn('https://site.test');
        $db = $this->createMock(DatabaseInterface::class);

        $decorator = new OgTagProDecorator($ctx, $db, null, $this->pageContext('de-DE'));
        $out       = $decorator->decorate($this->baseProps(), ['enable_og_locale' => 1]);

        // PageContext::language (de-DE) wins → de_DE, NOT en_GB from $ctx.
        $this->assertSame('de_DE', $out['og']['og:locale'] ?? null);
    }

    public function testOgLocaleFallsBackToCtxWhenNoPageContext(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getActiveLanguage')->willReturn('de-DE');
        $ctx->method('getSiteName')->willReturn('Site');
        $ctx->method('getBaseUrl')->willReturn('https://site.test');
        $db = $this->createMock(DatabaseInterface::class);

        // No PageContext (null) → byte-identical to pre-S6: read $ctx.
        $decorator = new OgTagProDecorator($ctx, $db, null, null);
        $out       = $decorator->decorate($this->baseProps(), ['enable_og_locale' => 1]);

        $this->assertSame('de_DE', $out['og']['og:locale'] ?? null);
    }
}
