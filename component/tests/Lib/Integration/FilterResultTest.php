<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Integration;

use AiBoost\Lib\Integration\FilterResult;
use PHPUnit\Framework\TestCase;

final class FilterResultTest extends TestCase
{
    public function testInitialOutputEqualsInputAndZeroMutations(): void
    {
        $in  = ['html' => '<head></head>', 'context' => 'home'];
        $r   = new FilterResult($in);
        self::assertSame($in, $r->getOutput());
        self::assertSame($in, $r->input);
        self::assertSame(0, $r->mutationCount());
        self::assertSame([], $r->getMutations());
    }

    public function testSetOutputRecordsMutation(): void
    {
        $r = new FilterResult(['html' => '']);
        $r->setOutput(['html' => '<x/>'], 'plg_aiboost_int_demo', 'inject demo');
        $r->setOutput(['html' => '<y/>'], 'plg_aiboost_int_demo', 'rewrite demo');

        self::assertSame(['html' => '<y/>'], $r->getOutput());
        self::assertSame(2, $r->mutationCount());
        $log = $r->getMutations();
        self::assertSame('plg_aiboost_int_demo', $log[0]['by']);
        self::assertSame('inject demo', $log[0]['reason']);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $log[0]['at']
        );
        self::assertSame('rewrite demo', $log[1]['reason']);
    }

    public function testInputRemainsImmutableEvenAfterMutation(): void
    {
        $in = ['urls' => [['loc' => '/a']]];
        $r  = new FilterResult($in);
        $r->setOutput(['urls' => [['loc' => '/a'], ['loc' => '/b']]], 'x', '');
        self::assertSame($in, $r->input);
    }
}
