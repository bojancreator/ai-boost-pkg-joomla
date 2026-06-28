<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Page;

use AiBoost\Lib\Page\IndexabilityPolicy;
use AiBoost\Lib\Page\PageType;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;

/**
 * T1 · S0 — the single indexability authority.
 *
 * forRenderedPage() preserves today's behaviour (the per-page noindex capability
 * is OFF by default, decision D2). isIndexableItem() is the one rule the bulk
 * enumerators will adopt at slice S4 — proven here, including the
 * restricted/unpublished → noindex case the design calls out.
 */
final class IndexabilityPolicyTest extends TestCase
{
    private IndexabilityPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new IndexabilityPolicy();
    }

    public function testRenderedPageIndexableByDefault(): void
    {
        [$ok, $reason] = $this->policy->forRenderedPage(PageType::ARTICLE);
        $this->assertTrue($ok);
        $this->assertSame('', $reason);
    }

    public function testRenderedPageNoindexWhenFlagged(): void
    {
        [$ok, $reason] = $this->policy->forRenderedPage(PageType::ARTICLE, true);
        $this->assertFalse($ok);
        $this->assertSame('per-page noindex requested', $reason);
    }

    public function testPublishedGuestVisibleItemIsIndexable(): void
    {
        [$ok, $reason] = $this->policy->isIndexableItem(true, true, true);
        $this->assertTrue($ok);
        $this->assertSame('', $reason);
    }

    public function testUnpublishedItemIsNotIndexable(): void
    {
        [$ok, $reason] = $this->policy->isIndexableItem(false, true, true);
        $this->assertFalse($ok);
        $this->assertSame('unpublished', $reason);
    }

    public function testOutsidePublishWindowIsNotIndexable(): void
    {
        [$ok, $reason] = $this->policy->isIndexableItem(true, true, false);
        $this->assertFalse($ok);
        $this->assertSame('outside publish window', $reason);
    }

    public function testRestrictedAccessIsNotIndexable(): void
    {
        [$ok, $reason] = $this->policy->isIndexableItem(true, false, true);
        $this->assertFalse($ok);
        $this->assertSame('restricted access (not guest-visible)', $reason);
    }

    public function testExcludedTypeIsNotIndexable(): void
    {
        [$ok, $reason] = $this->policy->isIndexableItem(true, true, true, true);
        $this->assertFalse($ok);
        $this->assertSame('excluded page type', $reason);
    }

    // ── itemWhereClauses() — the S4 bulk SQL-constraint contributor ────────────
    // A db stub whose quoteName is identity and quote wraps in single quotes, so
    // the produced clauses are deterministic and readable for assertions.

    private function db(): DatabaseInterface
    {
        // quoteName is identity and quote wraps in single quotes, so the produced
        // clauses are deterministic and readable. The DB null-date is passed in as
        // a parameter (the policy never calls $db->getNullDate()).
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('quoteName')->willReturnCallback(static fn($n) => (string) $n);
        $db->method('quote')->willReturnCallback(static fn($v) => "'" . $v . "'");
        return $db;
    }

    public function testItemClausesStateOnly(): void
    {
        // I4a (llms-Pro recent): state only — exactly one clause.
        $c = $this->policy->itemWhereClauses($this->db(), 'a.state');
        $this->assertSame(['a.state = 1'], $c);
    }

    public function testItemClausesSitemapWindowPlusGuestAccess(): void
    {
        // I1 (sitemap): state + sitemap publish-window + guest access on article AND category.
        $c = $this->policy->itemWhereClauses(
            $this->db(),
            publishedExpr: 'a.state',
            window: 'sitemap',
            timeColumnPrefix: 'a',
            now: 'NOW',
            nullDate: '0000-00-00 00:00:00',
            guestLevels: [1, 5],
            accessExpr: 'a.access',
            requireCategoryGuest: true,
        );
        $this->assertContains('a.state = 1', $c);
        $this->assertContains("(a.publish_up IS NULL OR a.publish_up <= 'NOW')", $c);
        $this->assertContains("(a.publish_down IS NULL OR a.publish_down = '0000-00-00 00:00:00' OR a.publish_down > 'NOW')", $c);
        $this->assertContains('a.access IN (1,5)', $c);
        $this->assertContains('c.access IN (1,5)', $c);
        $this->assertContains('c.published = 1', $c);
    }

    public function testItemClausesSitemapWithoutGuestLevelsHasNoAccessClause(): void
    {
        // I1 with no guest levels resolved → state + window only (no access clauses).
        $c = $this->policy->itemWhereClauses($this->db(), 'a.state', 'sitemap', 'a', 'NOW');
        $this->assertContains('a.state = 1', $c);
        $this->assertCount(3, $c); // state + 2 window clauses
        foreach ($c as $w) {
            $this->assertStringNotContainsStringIgnoringCase('access', $w);
        }
    }

    public function testItemClausesLlmsWindowNoAccess(): void
    {
        // I3 (llms.txt): state + llms publish-window (publish_down >= now), no access.
        $c = $this->policy->itemWhereClauses($this->db(), 'state', 'llms', '', 'NOW');
        $this->assertContains('state = 1', $c);
        $this->assertContains("(publish_up IS NULL OR publish_up <= 'NOW')", $c);
        $this->assertContains("(publish_down IS NULL OR publish_down >= 'NOW')", $c);
        $this->assertCount(3, $c);
    }

    public function testItemClausesRecentWindow(): void
    {
        // I2 (news): state + recent window (publish_up NOT NULL, >= cutoff, <= now).
        $c = $this->policy->itemWhereClauses(
            $this->db(),
            publishedExpr: 'a.state',
            window: 'recent',
            timeColumnPrefix: 'a',
            now: 'NOW',
            recentCutoff: 'CUT',
        );
        $this->assertContains('a.state = 1', $c);
        $this->assertContains('a.publish_up IS NOT NULL', $c);
        $this->assertContains("a.publish_up >= 'CUT'", $c);
        $this->assertContains("a.publish_up <= 'NOW'", $c);
    }

    public function testItemClausesPublicAccessOnly(): void
    {
        // I4b/I4c (llms-full): published/state + access = 1, no window.
        $c = $this->policy->itemWhereClauses(
            $this->db(),
            publishedExpr: 'published',
            accessExpr: 'access',
            publicAccessOnly: true,
        );
        $this->assertSame(['published = 1', 'access = 1'], $c);
    }
}
