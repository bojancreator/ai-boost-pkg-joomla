<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib\Integration\Support;

use AiBoost\Lib\Cms\EventDispatcherAdapter;

/**
 * Test double — Joomla-compatible event dispatcher that orders listeners
 * by (priority ASC, name ASC) the same way PluginHelper::importPlugin
 * does in production, and exposes a public callback queue for tests.
 */
final class InMemoryEventDispatcher implements EventDispatcherAdapter
{
    /** @var array<string, array<int, array{priority:int,name:string,cb:callable}>> */
    public array $listeners = [];

    public function on(string $event, callable $cb, int $priority = 10, string $name = 'plg_anon'): void
    {
        $this->listeners[$event][] = ['priority' => $priority, 'name' => $name, 'cb' => $cb];
    }

    public function trigger(string $event, array $args = []): array
    {
        $bucket = $this->listeners[$event] ?? [];
        usort(
            $bucket,
            static fn ($a, $b) => $a['priority'] <=> $b['priority']
                ?: strcmp($a['name'], $b['name'])
        );

        $out = [];
        foreach ($bucket as $row) {
            $out[] = ($row['cb'])(...$args);
        }
        return $out;
    }
}
