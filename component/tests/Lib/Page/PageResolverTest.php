<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Page;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Cms\DatabaseAdapter;
use AiBoost\Lib\Page\IndexabilityPolicy;
use AiBoost\Lib\Page\PageContext;
use AiBoost\Lib\Page\PageResolver;
use AiBoost\Lib\Page\PageType;
use PHPUnit\Framework\TestCase;

/**
 * T1 · S0 — the dormant PageResolver classifies the current request correctly.
 *
 * The resolver reads ONLY through AppContextInterface + the Cms DatabaseAdapter,
 * so it is exercised here over fakes — no Joomla bootstrap. Indexability defaults
 * to "indexable" (the per-page noindex capability is OFF by default, decision D2),
 * which is why S0 changes no behaviour. The item-level "restricted/unpublished →
 * noindex" decision lives in IndexabilityPolicy and is covered by its own test.
 */
final class PageResolverTest extends TestCase
{
    /**
     * @param array<string,mixed> $cfg option/view/id/home/lang/default/url
     */
    private function resolve(array $cfg): PageContext
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getCurrentOption')->willReturn($cfg['option'] ?? '');
        $ctx->method('getCurrentView')->willReturn($cfg['view'] ?? '');
        $ctx->method('getCurrentId')->willReturn($cfg['id'] ?? 0);
        $ctx->method('isHomepage')->willReturn($cfg['home'] ?? false);
        $ctx->method('getActiveLanguage')->willReturn($cfg['lang'] ?? 'en-GB');
        $ctx->method('getDefaultLanguage')->willReturn($cfg['default'] ?? 'en-GB');
        $ctx->method('getCurrentUrl')->willReturn($cfg['url'] ?? 'https://example.com/x');

        // Force the site-default-language lookup down its documented fallback
        // (= the global default) so the unit stays free of a DB query mock; the
        // real com_languages query is proven later at slice S6.
        $db = $this->createMock(DatabaseAdapter::class);
        $db->method('getConnection')->willThrowException(new \RuntimeException('no db in unit test'));

        return (new PageResolver($ctx, new IndexabilityPolicy(), $db))->resolve();
    }

    public function testHomepageWinsFirst(): void
    {
        $pc = $this->resolve(['home' => true, 'option' => 'com_content', 'view' => 'featured']);
        $this->assertSame(PageType::HOMEPAGE, $pc->type);
        $this->assertTrue($pc->isHomepage);
        $this->assertSame('site', $pc->entityKind);
        $this->assertSame(0, $pc->entityId);
        $this->assertFalse($pc->isArticle());
    }

    public function testArticle(): void
    {
        $pc = $this->resolve(['option' => 'com_content', 'view' => 'article', 'id' => 5]);
        $this->assertSame(PageType::ARTICLE, $pc->type);
        $this->assertSame('article', $pc->entityKind);
        $this->assertSame(5, $pc->entityId);
        $this->assertTrue($pc->isArticle());
        $this->assertTrue($pc->isContentEntity());
    }

    public function testArticleWithoutIdIsNotArticle(): void
    {
        $pc = $this->resolve(['option' => 'com_content', 'view' => 'article', 'id' => 0]);
        $this->assertSame(PageType::COMPONENT_OTHER, $pc->type);
        $this->assertFalse($pc->isArticle());
    }

    public function testCategory(): void
    {
        $pc = $this->resolve(['option' => 'com_content', 'view' => 'category', 'id' => 3]);
        $this->assertSame(PageType::CATEGORY, $pc->type);
        $this->assertSame('category', $pc->entityKind);
        $this->assertSame(3, $pc->entityId);
        $this->assertTrue($pc->isCategory());
    }

    public function testFeaturedWhenNotHome(): void
    {
        $pc = $this->resolve(['option' => 'com_content', 'view' => 'featured']);
        $this->assertSame(PageType::FEATURED, $pc->type);
        $this->assertSame('', $pc->entityKind);
    }

    public function testTag(): void
    {
        $pc = $this->resolve(['option' => 'com_tags', 'view' => 'tag', 'id' => 7]);
        $this->assertSame(PageType::TAG, $pc->type);
        $this->assertSame('tag', $pc->entityKind);
        $this->assertSame(7, $pc->entityId);
    }

    public function testSearch(): void
    {
        $pc = $this->resolve(['option' => 'com_finder', 'view' => 'search']);
        $this->assertSame(PageType::SEARCH, $pc->type);
    }

    public function testContact(): void
    {
        $pc = $this->resolve(['option' => 'com_contact', 'view' => 'contact', 'id' => 2]);
        $this->assertSame(PageType::CONTACT, $pc->type);
        $this->assertSame('contact', $pc->entityKind);
        $this->assertSame(2, $pc->entityId);
    }

    public function testUnknownWhenNoOption(): void
    {
        $pc = $this->resolve([]);
        $this->assertSame(PageType::UNKNOWN, $pc->type);
        $this->assertSame('', $pc->entityKind);
    }

    public function testLanguageCanonicalAndSiteDefaultFallback(): void
    {
        $pc = $this->resolve([
            'option'  => 'com_content', 'view' => 'article', 'id' => 9,
            'lang'    => 'de-DE',
            'default' => 'sr-YU',
            'url'     => 'https://example.com/de/artikel',
        ]);
        $this->assertSame('de-DE', $pc->language);
        $this->assertSame('sr-YU', $pc->globalDefaultLanguage);
        // DB lookup forced to fall back → site default == global default here.
        $this->assertSame('sr-YU', $pc->siteDefaultLanguage);
        $this->assertSame('https://example.com/de/artikel', $pc->canonical);
    }

    public function testRenderedPageIsIndexableByDefault(): void
    {
        // Per-page noindex capability is OFF by default (D2) → no behaviour change.
        $pc = $this->resolve(['option' => 'com_content', 'view' => 'article', 'id' => 1]);
        $this->assertTrue($pc->indexable);
        $this->assertSame('', $pc->noindexReason);
    }

    public function testResolveIsMemoised(): void
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getCurrentOption')->willReturn('com_content');
        $ctx->method('getCurrentView')->willReturn('article');
        $ctx->method('getCurrentId')->willReturn(4);
        $ctx->method('isHomepage')->willReturn(false);
        $ctx->method('getActiveLanguage')->willReturn('en-GB');
        $ctx->method('getDefaultLanguage')->willReturn('en-GB');
        $ctx->method('getCurrentUrl')->willReturn('https://example.com/a');
        $db = $this->createMock(DatabaseAdapter::class);
        $db->method('getConnection')->willThrowException(new \RuntimeException('x'));

        $resolver = new PageResolver($ctx, new IndexabilityPolicy(), $db);
        $this->assertSame($resolver->resolve(), $resolver->resolve());
    }

    // ── T1·S5 — canonical owned by the resolver ─────────────────────────────
    //
    // These pin PageContext::canonical to the byte-for-byte output of the legacy
    // AiBoostCore::resolveCanonical(): the bare scheme://host/path (query/fragment
    // stripped) for the no-map case, and the mapped target for a URL-map hit. The
    // canonical-only consumer (aiboost_core) threads the raw `canonical_url_map`
    // setting into resolve(); every other consumer passes nothing.

    private function resolverFor(string $url): PageResolver
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getCurrentOption')->willReturn('com_content');
        $ctx->method('getCurrentView')->willReturn('article');
        $ctx->method('getCurrentId')->willReturn(5);
        $ctx->method('isHomepage')->willReturn(false);
        $ctx->method('getActiveLanguage')->willReturn('en-GB');
        $ctx->method('getDefaultLanguage')->willReturn('en-GB');
        $ctx->method('getCurrentUrl')->willReturn($url);
        $db = $this->createMock(DatabaseAdapter::class);
        $db->method('getConnection')->willThrowException(new \RuntimeException('no db in unit test'));

        return new PageResolver($ctx, new IndexabilityPolicy(), $db);
    }

    /** No map → bare scheme://host/path; the query string is stripped (legacy parity). */
    public function testCanonicalNoMapIsBareUrlWithoutQuery(): void
    {
        $pc = $this->resolverFor('https://example.com/blog/post?start=20&x=1#frag')->resolve();
        $this->assertSame('https://example.com/blog/post', $pc->canonical);
    }

    /** URL-map exact hit → the mapped target (legacy resolveCanonical parity). */
    public function testCanonicalUrlMapExactHit(): void
    {
        $map = json_encode(['blog/post' => 'https://example.com/the-canonical']);
        $pc  = $this->resolverFor('https://example.com/blog/post?x=1')->resolve($map);
        $this->assertSame('https://example.com/the-canonical', $pc->canonical);
    }

    /** URL-map prefix hit (currentPath starts with pattern) → the mapped target. */
    public function testCanonicalUrlMapPrefixHit(): void
    {
        $map = json_encode(['shop' => 'https://example.com/store']);
        $pc  = $this->resolverFor('https://example.com/shop/item-42?ref=x')->resolve($map);
        $this->assertSame('https://example.com/store', $pc->canonical);
    }

    /** Map present but no pattern matches → bare URL fallback. */
    public function testCanonicalUrlMapNoHitFallsBackToBareUrl(): void
    {
        $map = json_encode(['other' => 'https://example.com/nope']);
        $pc  = $this->resolverFor('https://example.com/blog/post')->resolve($map);
        $this->assertSame('https://example.com/blog/post', $pc->canonical);
    }

    /** Invalid / empty map JSON → bare URL fallback (legacy parity). */
    public function testCanonicalInvalidMapFallsBackToBareUrl(): void
    {
        $resolver = $this->resolverFor('https://example.com/blog/post');
        $this->assertSame('https://example.com/blog/post', $resolver->resolve('not json')->canonical);
        $this->assertSame('https://example.com/blog/post', $resolver->resolve('')->canonical);
    }

    /**
     * A map-threading call must NOT mutate the memoised base: a later no-map
     * resolve() (the other consumers) still sees the bare URL. This makes the
     * canonical answer independent of which consumer resolves first.
     */
    public function testCanonicalMapDoesNotMutateMemoisedBase(): void
    {
        $resolver = $this->resolverFor('https://example.com/blog/post');
        $map      = json_encode(['blog/post' => 'https://example.com/the-canonical']);

        $this->assertSame('https://example.com/the-canonical', $resolver->resolve($map)->canonical);
        // Subsequent no-map resolve (schema/social/indexability) is unaffected.
        $this->assertSame('https://example.com/blog/post', $resolver->resolve()->canonical);
    }
}
