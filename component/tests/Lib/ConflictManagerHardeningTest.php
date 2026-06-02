<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\ConflictManager;
use PHPUnit\Framework\TestCase;

final class ConflictManagerHardeningTest extends TestCase
{
    protected function setUp(): void
    {
        ConflictManager::reset();
    }

    public function testFirstClaimWinsSecondIsRejected(): void
    {
        self::assertTrue(ConflictManager::claim('canonical', 'aiboost_aeo'));
        self::assertFalse(ConflictManager::claim('canonical', 'aiboost_int_demo'));
        self::assertSame('aiboost_aeo', ConflictManager::getOwner('canonical'));
    }

    public function testSameClaimantMayReclaim(): void
    {
        self::assertTrue(ConflictManager::claim('og_tags', 'aiboost_social'));
        self::assertTrue(ConflictManager::claim('og_tags', 'aiboost_social', 'reused'));
    }

    public function testReportIncludesSlotsLogAndCollisions(): void
    {
        ConflictManager::claim('hreflang', 'aiboost_int_falang', 'falang owns');
        ConflictManager::claim('hreflang', 'aiboost_int_template', 'template tries');

        $report = ConflictManager::report();
        self::assertSame(['hreflang' => 'aiboost_int_falang'], $report['slots']);

        // 2 log entries: one granted, one rejected.
        self::assertCount(2, $report['log']);
        self::assertTrue($report['log'][0]['granted']);
        self::assertFalse($report['log'][1]['granted']);

        // collisions surfaces the rejected bridge.
        self::assertCount(1, $report['collisions']);
        self::assertSame('hreflang', $report['collisions'][0]['slot']);
        self::assertSame('aiboost_int_falang', $report['collisions'][0]['owner']);
        self::assertSame(['aiboost_int_template'], $report['collisions'][0]['rejected']);
    }

    public function testSlotsCatalogueExposesEveryConstant(): void
    {
        // Every SLOT_* constant must appear in the SLOTS catalogue so the
        // SDK doc and tests have a single source of truth.
        $ref = new \ReflectionClass(ConflictManager::class);
        $declared = [];
        foreach ($ref->getReflectionConstants() as $c) {
            if (str_starts_with($c->getName(), 'SLOT_')) {
                $declared[] = $c->getValue();
            }
        }
        sort($declared);
        $catalogue = ConflictManager::SLOTS;
        sort($catalogue);
        self::assertSame($declared, $catalogue);
    }

    public function testReleaseEmitsLogEntry(): void
    {
        ConflictManager::claim('robots_txt', 'aiboost_aeo');
        self::assertTrue(ConflictManager::release('robots_txt', 'aiboost_aeo'));
        self::assertFalse(ConflictManager::isClaimed('robots_txt'));
        $log = ConflictManager::getLog();
        self::assertSame('released', end($log)['reason']);
    }
}
