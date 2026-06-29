<?php

declare(strict_types=1);

namespace AiBoost\Tests\Service;

use AiBoost\Lib\AppContextInterface;
use AiBoost\Plugin\System\AiBoostSchema\Service\SchemaProBuilder;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * T1 · S1 — characterization (behavioural): the SchemaProBuilder article gate
 * (design map P3–P8) is wired and EXCLUDES non-article pages. On a category page
 * — with FAQ auto-detect / HowTo / Event / Article all enabled — none of the
 * article-scoped blocks emit, because every gated builder early-returns on the
 * `option==='com_content' && view==='article' && id>0` triple.
 *
 * This is the output-level counterpart to T1ResolverEquivalenceTest: it pins what
 * the builder DOES today (no article blocks off an article), so the S2 migration
 * onto PageContext::isArticle() can prove "no behaviour change".
 *
 * The article-POSITIVE side is locked by the resolver-equivalence test + the live
 * schema golden-diff each migration slice runs on staging (as in S0); it is not
 * re-exercised here because it requires a full article-row DB fixture.
 */
final class SchemaProBuilderGateCharacterizationTest extends TestCase
{
    private function ctx(string $option, string $view, int $id): AppContextInterface
    {
        $ctx = $this->createMock(AppContextInterface::class);
        $ctx->method('getCurrentOption')->willReturn($option);
        $ctx->method('getCurrentView')->willReturn($view);
        $ctx->method('getCurrentId')->willReturn($id);
        $ctx->method('getActiveLanguage')->willReturn('en-GB');
        $ctx->method('getDefaultLanguage')->willReturn('en-GB');
        $ctx->method('getBaseUrl')->willReturn('https://example.com');
        return $ctx;
    }

    /** Article-scoped @type values that must NOT appear off an article page. */
    private const ARTICLE_SCOPED_TYPES = [
        'Article', 'BlogPosting', 'NewsArticle', 'TechArticle',
        'FAQPage', 'QAPage', 'HowTo', 'Event',
    ];

    /**
     * T1·S7 (order 0028) — "the homepage is ALWAYS the homepage". On a
     * SINGLE-ARTICLE homepage (article primitives BUT the menu item is home=1),
     * the article gate is now closed (isArticlePage() == PageContext::isArticle()
     * is false on a home), so NO article-scoped schema emits — the homepage takes
     * the Free WebSite/home graph instead. Pre-S7 the gate was homepage-agnostic
     * and Article schema emitted here; the behavioural before/after is captured
     * live on staging (the design §3 reviewed diff).
     */
    public function testNoArticleScopedBlocksOnSingleArticleHomepage(): void
    {
        $settings = [
            'article_schema_enabled' => '1',
            'faq_auto_detect'        => '1',
            'schema_faq_output_type' => 'both',
            'schema_howto_enabled'   => '1',
            'schema_howto'           => '{"name":"X","steps":["a","b"]}',
            'events_enabled'         => '1',
            'events_category_id'     => '2',
        ];

        // Article primitives, BUT this is the menu home=1 page.
        $ctx = $this->ctx('com_content', 'article', 5);
        $ctx->method('isHomepage')->willReturn(true);
        $db = $this->createMock(DatabaseInterface::class);

        $builder      = new SchemaProBuilder($settings, $ctx, $db);
        $blocks       = $builder->decorateAll([]);
        $emittedTypes = array_map(static fn(array $b): string => (string) ($b['@type'] ?? ''), $blocks);
        foreach (self::ARTICLE_SCOPED_TYPES as $type) {
            $this->assertNotContains(
                $type,
                $emittedTypes,
                "S7: article-scoped @type '$type' must NOT emit on a single-article HOMEPAGE."
            );
        }
    }

    public function testNoArticleScopedBlocksOnCategoryPage(): void
    {
        $settings = [
            'article_schema_enabled' => '1',
            'faq_auto_detect'        => '1',
            'schema_faq_output_type' => 'both',
            'schema_howto_enabled'   => '1',
            'schema_howto'           => '{"name":"X","steps":["a","b"]}',
            'events_enabled'         => '1',
            'events_category_id'     => '2',
            // No manual faq_items — so FAQ can only come from article auto-detect,
            // which is itself article-gated.
        ];

        $ctx = $this->ctx('com_content', 'category', 5);   // NON-article
        $db  = $this->createMock(DatabaseInterface::class);

        $builder = new SchemaProBuilder($settings, $ctx, $db);
        $blocks  = $builder->decorateAll([]);

        $emittedTypes = array_map(static fn(array $b): string => (string) ($b['@type'] ?? ''), $blocks);
        foreach (self::ARTICLE_SCOPED_TYPES as $type) {
            $this->assertNotContains(
                $type,
                $emittedTypes,
                "Article-scoped @type '$type' must NOT emit on a category page (the article gate excludes it)."
            );
        }
    }

    public function testNoArticleScopedBlocksOnHomepageComponentRoot(): void
    {
        // A non-content route (e.g. com_users) — the gate closes the same way.
        $ctx     = $this->ctx('com_users', 'login', 0);
        $db      = $this->createMock(DatabaseInterface::class);
        $builder = new SchemaProBuilder(
            ['article_schema_enabled' => '1', 'schema_howto_enabled' => '1', 'events_enabled' => '1'],
            $ctx,
            $db
        );

        $blocks       = $builder->decorateAll([]);
        $emittedTypes = array_map(static fn(array $b): string => (string) ($b['@type'] ?? ''), $blocks);
        foreach (self::ARTICLE_SCOPED_TYPES as $type) {
            $this->assertNotContains($type, $emittedTypes);
        }
    }
}
