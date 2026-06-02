<?php
/**
 * AI Boost — FilterResult
 *
 * Mutable carrier passed by core plugins through `onAiBoostFilter*` events.
 * Listeners receive the immutable input payload (read-only array) AND the
 * shared FilterResult instance. Every mutation is recorded so we can
 * surface a deterministic audit trail in Debug / Health for "who changed
 * what right before output".
 *
 * Filter dispatch contract:
 *
 *   $result = new FilterResult($input);
 *   AdapterRegistry::events()->trigger($eventName, [$input, $result]);
 *   // core consumes $result->getOutput() instead of $input.
 *
 * Listener contract:
 *
 *   public function onAiBoostFilterFoo(array $input, FilterResult $r): void
 *   {
 *       $out = $r->getOutput();
 *       $out['fizz'] = 'buzz';
 *       $r->setOutput($out, $this->getName(), 'attach Falang hreflang');
 *   }
 *
 * Ordering: listeners run in (priority ASC, plugin-name ASC) order — same
 * rule as Joomla's plugin manager, just made explicit so test assertions
 * stay stable.
 *
 * @package     AiBoost\Lib\Integration
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Integration;

defined('_JEXEC') or defined('ABSPATH') or die;

final class FilterResult
{
    /** @var array<int|string,mixed> */
    private array $output;

    /** @var list<array{at:string,by:string,reason:string}> */
    private array $mutations = [];

    /** @param array<int|string,mixed> $input */
    public function __construct(public readonly array $input)
    {
        $this->output = $input;
    }

    /** @return array<int|string,mixed> */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * Replace the entire output. The (by, reason) pair is logged so
     * Debug / Health can show "who changed what".
     *
     * @param array<int|string,mixed> $output
     */
    public function setOutput(array $output, string $by, string $reason = ''): void
    {
        $this->output = $output;
        $this->mutations[] = [
            'at'     => gmdate('Y-m-d\TH:i:s\Z'),
            'by'     => $by,
            'reason' => $reason,
        ];
    }

    /** @return list<array{at:string,by:string,reason:string}> */
    public function getMutations(): array
    {
        return $this->mutations;
    }

    /** Number of listeners that actually mutated the output. */
    public function mutationCount(): int
    {
        return count($this->mutations);
    }
}
