<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Page;

use AiBoost\Lib\Page\IndexabilityPolicy;
use AiBoost\Lib\Page\PageType;
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
}
