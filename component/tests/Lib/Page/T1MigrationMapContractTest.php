<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Page;

use PHPUnit\Framework\TestCase;

/**
 * T1 · S1 — characterization (source contract) for the migration-map sites that
 * the PHPUnit suite cannot exercise at runtime because they read the live Joomla
 * application (Factory/Uri) or build private SQL with no DI seam. Following the
 * established repo idiom (HomepageDetectionContractTest, EmissionProGateSource-
 * ContractTest, Php85DeprecationContractTest), each test pins the DECISION/FILTER
 * the current code makes AT that site, so accidental drift before its migration
 * slice is caught — and so the slice that migrates it has a precise "this is what
 * was here" record. (Per-slice output-equivalence is proven by the live BEFORE↔
 * AFTER golden diff on staging, as demonstrated in S0.)
 *
 * COVERAGE (design §4 migration map) — every site accounted for:
 *   H1  — JoomlaAppContext::isHomepage  → already locked by HomepageDetectionContractTest (asserted present here)
 *   H2/P1 — AiBoostCore::detectPageType  → testDetectPageTypeHeuristic
 *   title/meta (H2) — applyTitle/MetaDescTemplate call detectPageType → testTitleMetaTemplatesUseDetectPageType
 *   P2  — AiBoostCore::resolveCategoryToken → testResolveCategoryTokenArticleGate
 *   P9  — AiBoostSchema dispatch feeds option/view/id to the builder → testSchemaDispatchPassesPageContextPrimitives
 *   P11 — OgTagBuilder (Free) page-type → testOgTagBuilderReadsPageType
 *   C1  — AiBoostCore::resolveCanonical → testCanonicalMapAndBareUrl
 *   I1  — SitemapGenerator indexability filter → testSitemapIndexabilityFilter
 *   I2  — NewsSitemapGenerator filter → testNewsSitemapIndexabilityFilter
 *   I3  — LlmsTxtGenerator filter → testLlmsTxtIndexabilityFilter
 *   I4  — LlmsTxtProGenerator filters → testLlmsFullIndexabilityFilters
 *   I5  — AiBoostAeo X-Robots header → testXRobotsHeaderEmitter
 *   I6  — AiBoostAeo Markdown discovery → testMarkdownDiscoveryEmitter
 *   L1  — JoomlaAppContext::getActiveLanguage → testActiveLanguageSource
 *   L2  — AiBoostIntFalang site-default language → testSiteDefaultLanguageSource
 *   L3  — LanguageDetector (translation-source authority) is OUT of T1 scope → testL3StaysOutOfScope
 *   (P3–P8, P10, P12 are locked behaviourally + by resolver-equivalence — see
 *    T1ResolverEquivalenceTest and SchemaProBuilderGateCharacterizationTest.)
 */
final class T1MigrationMapContractTest extends TestCase
{
    private const PLUGINS = __DIR__ . '/../../../plugins/system';
    private const LIB     = __DIR__ . '/../../../lib/src';
    private const TESTS   = __DIR__ . '/..';

    private function src(string $path): string
    {
        $full = realpath($path);
        $this->assertNotFalse($full, "Migration-map site not found (did the code move? STOP + report): $path");
        return (string) file_get_contents($full);
    }

    private function assertAllContain(string $src, array $needles, string $site): void
    {
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $src, "[$site] expected current logic marker: $needle");
        }
    }

    // ── Homepage (H1) ─────────────────────────────────────────────────────────
    public function testH1LockedByHomepageContractTest(): void
    {
        $this->assertNotFalse(
            realpath(self::TESTS . '/HomepageDetectionContractTest.php'),
            'H1 (isHomepage menu-home truth) must stay locked by HomepageDetectionContractTest.'
        );
    }

    // ── detectPageType heuristic (H2 / P1) — POST-S7: now the absent-resolver fallback ──
    // S7 (order 0028) retired detectPageType() as the live path: title/meta now select
    // via resolvePageType() (the resolver, homepage-first). detectPageType() is kept
    // ONLY as resolvePageType()'s fallback for a partial uninstall — its heuristic body
    // is therefore still present (this contract still pins it), but it no longer drives
    // production behaviour. See testTitleMetaTemplatesSelectViaResolverHomepageFirst.
    public function testDetectPageTypeHeuristicRetainedAsFallback(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_core/src/Extension/AiBoostCore.php');
        $this->assertAllContain($src, [
            'function detectPageType',
            "view === 'featured'",   // still in the fallback body
            "return 'article';",
            "return 'category';",
            "return 'search';",
            "return 'tag';",
        ], 'H2/P1 detectPageType (fallback)');
    }

    /**
     * S7 — title/meta template selection is now homepage-first via the resolver:
     * applyTitleTemplate + applyMetaDescTemplate call resolvePageType(), which reads
     * AdapterRegistry::pageResolver()->resolve() and returns 'home' for the menu
     * home=1 page WHATEVER it is built from. detectPageType() is no longer called by
     * the template methods (only by resolvePageType()'s fallback).
     */
    public function testTitleMetaTemplatesSelectViaResolverHomepageFirst(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_core/src/Extension/AiBoostCore.php');
        // def + the two template call sites all use resolvePageType().
        $this->assertGreaterThanOrEqual(
            3,
            substr_count($src, 'resolvePageType('),
            'S7: title/meta must select page type via resolvePageType().'
        );
        $this->assertAllContain($src, [
            'function resolvePageType',
            'pageResolver()',          // resolvePageType reads the shared resolver
            '$pc->isHomepage',         // homepage-first: menu home=1 wins
            "return 'home';",
        ], 'S7 resolvePageType');
        // The template methods no longer call the legacy detector directly — its only
        // caller is resolvePageType()'s fallback (so exactly ONE $this->detectPageType()).
        $this->assertSame(
            1,
            substr_count($src, '$this->detectPageType()'),
            'S7: detectPageType() must be called only as resolvePageType()\'s fallback.'
        );
    }

    // ── resolveCategoryToken (P2) ─────────────────────────────────────────────
    public function testResolveCategoryTokenArticleGate(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_core/src/Extension/AiBoostCore.php');
        $this->assertAllContain($src, [
            'function resolveCategoryToken',
            "=== 'article'",
            '#__categories',
        ], 'P2 resolveCategoryToken');
    }

    // ── canonical (C1) ────────────────────────────────────────────────────────
    public function testCanonicalMapAndBareUrl(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_core/src/Extension/AiBoostCore.php');
        $this->assertAllContain($src, [
            'function resolveCanonical',
            'canonical_url_map',
            '$uri->getScheme()',
            '$uri->getHost()',
        ], 'C1 resolveCanonical');
    }

    // ── schema dispatch (P9) + OG (P11) ───────────────────────────────────────
    public function testSchemaDispatchPassesPageContextPrimitives(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_schema/src/Extension/AiBoostSchema.php');
        $this->assertStringContainsString('SchemaProBuilder', $src, 'P9: dispatch must feed the Pro builder.');
    }

    public function testOgTagBuilderReadsPageType(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_social/src/Service/OgTagBuilder.php');
        $this->assertAllContain($src, [
            'getCurrentOption()',   // reads the page-type primitives today
            "'website'",            // Free baseline og:type
        ], 'P11 OgTagBuilder');
    }

    // ── indexability filters (I1–I4) — MIGRATED in S4 (order 0024) ─────────────
    // The four enumerators no longer hand-write their own state/window/access SQL;
    // each now delegates the item-indexability decision to the shared
    // IndexabilityPolicy::itemWhereClauses() (called with exactly its former
    // parameters → identical rows). The actual rule/SQL is now pinned in
    // IndexabilityPolicyTest; here we lock that each enumerator DELEGATES.
    public function testSitemapIndexabilityFilter(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_sitemap/src/Service/SitemapGenerator.php');
        $this->assertAllContain($src, ['IndexabilityPolicy', 'itemWhereClauses', "window: 'sitemap'"], 'I1 SitemapGenerator delegates');
    }

    public function testNewsSitemapIndexabilityFilter(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_sitemap/src/Service/NewsSitemapGenerator.php');
        $this->assertAllContain($src, ['IndexabilityPolicy', 'itemWhereClauses', "window: 'recent'"], 'I2 NewsSitemapGenerator delegates');
    }

    public function testLlmsTxtIndexabilityFilter(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_aeo/src/Service/LlmsTxtGenerator.php');
        $this->assertAllContain($src, ['IndexabilityPolicy', 'itemWhereClauses', "window: 'llms'"], 'I3 LlmsTxtGenerator delegates');
    }

    public function testLlmsFullIndexabilityFilters(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_aeo/src/Service/LlmsTxtProGenerator.php');
        $this->assertAllContain($src, ['IndexabilityPolicy', 'itemWhereClauses', 'publicAccessOnly: true'], 'I4 LlmsTxtProGenerator delegates');
    }

    // ── AEO per-request (I5 / I6) ─────────────────────────────────────────────
    public function testXRobotsHeaderEmitter(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_aeo/src/Extension/AiBoostAeo.php');
        $this->assertAllContain($src, [
            'enable_x_robots_header',
            'X-Robots-Tag: index, follow',
            'isProActive',
        ], 'I5 X-Robots-Tag');
    }

    public function testMarkdownDiscoveryEmitter(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_aeo/src/Extension/AiBoostAeo.php');
        $this->assertAllContain($src, ['markdown_pages_enabled', 'type="text/markdown"'], 'I6 Markdown discovery');
    }

    // ── language (L1 / L2 / L3) ───────────────────────────────────────────────
    public function testActiveLanguageSource(): void
    {
        $src = $this->src(self::LIB . '/JoomlaAppContext.php');
        $this->assertAllContain($src, ['function getActiveLanguage', 'getLanguage()->getTag()'], 'L1 active language');
    }

    public function testSiteDefaultLanguageSource(): void
    {
        $src = $this->src(self::PLUGINS . '/aiboost_int_falang/src/Extension/AiBoostIntFalang.php');
        $this->assertStringContainsString(
            "getParams('com_languages')->get('site'",
            $src,
            'L2: the site-default content language is read ad-hoc from com_languages today (the resolver centralises it at S6).'
        );
    }

    public function testL3StaysOutOfScope(): void
    {
        // L3 (translation-source authority) is a DIFFERENT concern and is explicitly
        // NOT migrated by T1 (design §4.5 L3 / §7). Lock that LanguageDetector still
        // exists as the separate silo it is.
        $this->assertNotFalse(
            realpath(self::LIB . '/LanguageDetector.php'),
            'L3 LanguageDetector must remain a separate concern (out of T1 scope).'
        );
    }
}
