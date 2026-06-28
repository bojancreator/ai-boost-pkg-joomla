<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Page;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Lib\Cms\DatabaseAdapter;
use AiBoost\Lib\Page\IndexabilityPolicy;
use AiBoost\Lib\Page\PageResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * T1 · S1 — characterization: the S0 PageResolver reproduces the CURRENT inline
 * decisions, so the behaviour-preserving migration slices (S2/S3/S5) can replace
 * the scattered gates with `PageContext` and stay output-identical.
 *
 * Each test locks "the resolver agrees with what the code decides today" for one
 * mapped decision, across an input matrix. These tests are designed to STAY GREEN
 * through every behaviour-preserving migration (the gate condition is identical).
 *
 * COVERS (design §4 migration map):
 *  - Article gate — P3 (SchemaProBuilder ctor), P4 (collectFaqItems :313),
 *    P5 (buildArticle :373), P6 (buildEvent :497), P7 (buildHowTo :575),
 *    P8 (resolveArticleType), P12 (OgTagProDecorator :123). The live rule at all
 *    seven is the SAME triple: option==='com_content' && view==='article' && id>0.
 *  - Homepage gate — P10 (SchemaBuilder::buildWebSite :769, gated on isHomepage)
 *    and H1 (the authoritative menu-home truth the resolver delegates to).
 *  - Category gate — P2 (AiBoostCore::resolveCategoryToken article-triple gate;
 *    the {category} token only resolves on an article page).
 *  - Canonical — C1 (AiBoostCore::resolveCanonical bare-URL branch; the resolver's
 *    S0 canonical == getCurrentUrl(); the URL-map branch migrates at S5).
 *
 * FINDING captured below: the inline article gate is HOMEPAGE-AGNOSTIC, while the
 * resolver classifies homepage-first. They diverge ONLY on a single-article
 * homepage — see testArticleGateDivergesOnlyOnSingleArticleHome(). S2 must
 * preserve today's behaviour there (or take an explicit decision).
 */
final class T1ResolverEquivalenceTest extends TestCase
{
    /** The literal article-gate triple copy-pasted at P3–P8 and P12 today. */
    private function inlineArticleGate(string $option, string $view, int $id): bool
    {
        return $option === 'com_content' && $view === 'article' && $id > 0;
    }

    /**
     * @param array<string,mixed> $cfg
     */
    private function resolve(array $cfg): \AiBoost\Lib\Page\PageContext
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getCurrentOption')->willReturn($cfg['option'] ?? '');
        $ctx->method('getCurrentView')->willReturn($cfg['view'] ?? '');
        $ctx->method('getCurrentId')->willReturn($cfg['id'] ?? 0);
        $ctx->method('isHomepage')->willReturn($cfg['home'] ?? false);
        $ctx->method('getActiveLanguage')->willReturn($cfg['lang'] ?? 'en-GB');
        $ctx->method('getDefaultLanguage')->willReturn($cfg['default'] ?? 'en-GB');
        $ctx->method('getCurrentUrl')->willReturn($cfg['url'] ?? 'https://example.com/x');

        $db = $this->createMock(DatabaseAdapter::class);
        $db->method('getConnection')->willThrowException(new \RuntimeException('no db in unit test'));

        return (new PageResolver($ctx, new IndexabilityPolicy(), $db))->resolve();
    }

    /** @return iterable<string,array{0:string,1:string,2:int}> */
    public static function nonHomeMatrix(): iterable
    {
        yield 'article'           => ['com_content', 'article', 5];
        yield 'article no id'     => ['com_content', 'article', 0];
        yield 'category'          => ['com_content', 'category', 3];
        yield 'categories'        => ['com_content', 'categories', 3];
        yield 'featured'          => ['com_content', 'featured', 0];
        yield 'tag'               => ['com_tags', 'tag', 7];
        yield 'finder'            => ['com_finder', 'search', 0];
        yield 'contact'           => ['com_contact', 'contact', 2];
        yield 'other component'   => ['com_users', 'login', 0];
        yield 'nothing'           => ['', '', 0];
    }

    /**
     * P3–P8, P12 — on every NON-homepage request the resolver's isArticle()
     * matches the inline article triple exactly. (The migration target is safe.)
     */
    #[DataProvider('nonHomeMatrix')]
    public function testArticleGateEquivalenceOffHomepage(string $option, string $view, int $id): void
    {
        $pc = $this->resolve(['option' => $option, 'view' => $view, 'id' => $id, 'home' => false]);
        $this->assertSame(
            $this->inlineArticleGate($option, $view, $id),
            $pc->isArticle(),
            "PageContext::isArticle() must equal the inline article gate for option=$option view=$view id=$id"
        );
    }

    /**
     * THE divergence S2 must preserve: a single-article HOMEPAGE
     * (option=com_content, view=article, id>0, AND the menu item is home=1).
     * The inline gate fires (→ Article schema emits today); the resolver
     * classifies homepage-first (isArticle()=false). Captured so S2 does not
     * silently drop Article schema on a single-article home.
     */
    public function testArticleGateDivergesOnlyOnSingleArticleHome(): void
    {
        $cfg = ['option' => 'com_content', 'view' => 'article', 'id' => 9, 'home' => true];
        $this->assertTrue(
            $this->inlineArticleGate('com_content', 'article', 9),
            'Today the inline gate treats a single-article home AS an article (Article schema emits).'
        );
        $pc = $this->resolve($cfg);
        $this->assertFalse(
            $pc->isArticle(),
            'The resolver classifies a single-article home as HOMEPAGE (isArticle()=false) — divergence S2 must reconcile.'
        );
        $this->assertTrue($pc->isHomepage);
    }

    /**
     * P10 + H1 — the homepage gate. The resolver mirrors the injected
     * isHomepage (= the authoritative menu-home flag, JoomlaAppContext).
     */
    public function testHomepageGateEquivalence(): void
    {
        $this->assertTrue($this->resolve(['home' => true])->isHomepage);
        $this->assertFalse($this->resolve(['home' => false, 'option' => 'com_content', 'view' => 'article', 'id' => 1])->isHomepage);
    }

    /**
     * P2 — AiBoostCore::resolveCategoryToken() only resolves the {category}
     * token on an article page (its gate is the same article triple). Off an
     * article the resolver agrees there is no article entity to read a category
     * from.
     */
    public function testCategoryTokenGateMatchesArticleGate(): void
    {
        // On an article → the token gate is open (isArticle) today.
        $this->assertTrue($this->resolve(['option' => 'com_content', 'view' => 'article', 'id' => 4])->isArticle());
        // On a category page → not an article → the {category} token gate stays closed.
        $this->assertFalse($this->resolve(['option' => 'com_content', 'view' => 'category', 'id' => 4])->isArticle());
    }

    /**
     * C1 — AiBoostCore::resolveCanonical() with no canonical_url_map returns the
     * bare current URL (scheme://host/path). The resolver's S0 canonical equals
     * getCurrentUrl() — the same bare value. (The URL-map branch migrates at S5.)
     */
    public function testCanonicalBareUrlEquivalence(): void
    {
        $pc = $this->resolve(['url' => 'https://example.com/de/artikel']);
        $this->assertSame('https://example.com/de/artikel', $pc->canonical);
    }

    /**
     * L1 — the active request language is surfaced unchanged on the context.
     */
    public function testActiveLanguageSurfacedUnchanged(): void
    {
        $this->assertSame('de-DE', $this->resolve(['lang' => 'de-DE'])->language);
    }
}
