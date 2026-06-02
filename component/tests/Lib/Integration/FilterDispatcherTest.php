<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Integration;

use AiBoost\Lib\Cms\AdapterRegistry;
use AiBoost\Lib\Integration\FilterDispatcher;
use AiBoost\Lib\Integration\FilterResult;
use AiBoost\Lib\Integration\Sdk;
use AiBoost\Tests\Lib\Integration\Support\InMemoryEventDispatcher;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/InMemoryEventDispatcher.php';

final class FilterDispatcherTest extends TestCase
{
    private InMemoryEventDispatcher $bus;

    protected function setUp(): void
    {
        $this->bus = new InMemoryEventDispatcher();
        AdapterRegistry::setEvents($this->bus);
    }

    protected function tearDown(): void
    {
        AdapterRegistry::setEvents(null);
    }

    public function testInputIsImmutableForListeners(): void
    {
        $captured = null;
        $this->bus->on(
            Sdk::EVENT_FILTER_OG_TAGS,
            static function (array $input, FilterResult $r) use (&$captured): void {
                // Mutate the input array — listener must not be able to
                // affect the result through this reference.
                $local = $input;
                $local['tags'][] = '<meta property="og:title" content="HACKED">';
                $captured = $r->input;
            }
        );

        $out = FilterDispatcher::dispatch(Sdk::EVENT_FILTER_OG_TAGS, ['tags' => ['<meta property="og:title" content="Original">']]);

        self::assertSame(['tags' => ['<meta property="og:title" content="Original">']], $captured);
        self::assertSame(['tags' => ['<meta property="og:title" content="Original">']], $out);
    }

    public function testListenersExecuteInPriorityThenNameOrder(): void
    {
        $log = [];
        $append = static function (string $tag) use (&$log) {
            return static function (array $in, FilterResult $r) use (&$log, $tag) {
                $log[] = $tag;
                $cur = $r->getOutput();
                $cur['html'] = ($cur['html'] ?? '') . $tag;
                $r->setOutput($cur, $tag, 'append');
            };
        };

        // Insertion order intentionally jumbled.
        $this->bus->on(Sdk::EVENT_FILTER_HEAD_OUTPUT, $append('[B]'),  priority: 10, name: 'plg_b');
        $this->bus->on(Sdk::EVENT_FILTER_HEAD_OUTPUT, $append('[Z]'),  priority: 20, name: 'plg_z');
        $this->bus->on(Sdk::EVENT_FILTER_HEAD_OUTPUT, $append('[A]'),  priority: 10, name: 'plg_a');
        $this->bus->on(Sdk::EVENT_FILTER_HEAD_OUTPUT, $append('[!]'),  priority: 1,  name: 'plg_first');

        $out = FilterDispatcher::dispatch(Sdk::EVENT_FILTER_HEAD_OUTPUT, ['html' => '']);

        self::assertSame(['[!]', '[A]', '[B]', '[Z]'], $log);
        self::assertSame('[!][A][B][Z]', $out['html']);
    }

    public function testPartialMutationsArePreservedWhenLaterListenerThrows(): void
    {
        $this->bus->on(
            Sdk::EVENT_FILTER_ROBOTS_RULES,
            static function (array $in, FilterResult $r): void {
                $r->setOutput(['rules' => "User-agent: *\nDisallow: /admin\n"], 'plg_good', 'append');
            },
            priority: 10,
            name: 'plg_good'
        );
        $this->bus->on(
            Sdk::EVENT_FILTER_ROBOTS_RULES,
            static function (): void {
                throw new \RuntimeException('boom');
            },
            priority: 20,
            name: 'plg_bad'
        );

        $out = FilterDispatcher::dispatch(Sdk::EVENT_FILTER_ROBOTS_RULES, ['rules' => '']);

        // Mutation by the good listener must survive even though the
        // later listener crashed — otherwise downstream consumers (the
        // ConflictManager claim log, in particular) would disagree with
        // the rendered output.
        self::assertSame("User-agent: *\nDisallow: /admin\n", $out['rules']);
    }

    public function testDispatchWithLogExposesMutationCount(): void
    {
        $this->bus->on(
            Sdk::EVENT_FILTER_SITEMAP_URL_SET,
            static function (array $in, FilterResult $r): void {
                $cur = $r->getOutput();
                $cur['entries'][] = ['loc' => 'https://example.com/x'];
                $r->setOutput($cur, 'plg_aiboost_int_falang', 'alternates');
            }
        );

        $result = FilterDispatcher::dispatchWithLog(
            Sdk::EVENT_FILTER_SITEMAP_URL_SET,
            ['entries' => []]
        );

        self::assertSame(1, $result->mutationCount());
        self::assertCount(1, $result->getOutput()['entries']);
    }
}
