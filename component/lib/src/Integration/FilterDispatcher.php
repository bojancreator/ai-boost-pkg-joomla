<?php
/**
 * AI Boost — FilterDispatcher
 *
 * Thin façade core plugins call right before they emit output:
 *
 *   $out = FilterDispatcher::dispatch(
 *       Sdk::EVENT_FILTER_HEAD_OUTPUT,
 *       ['html' => $headHtml, 'context' => $ctx]
 *   );
 *
 * It builds the FilterResult, fires the named event through the existing
 * AdapterRegistry::events() bus (so the WP adapter Just Works), records
 * deterministic ordering metadata in the result, and returns the final
 * output array. Listeners that throw are logged but never break core
 * output — the input is returned unchanged.
 *
 * @package     AiBoost\Lib\Integration
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Integration;

defined('_JEXEC') or defined('ABSPATH') or die;

use AiBoost\Lib\Cms\AdapterRegistry;

final class FilterDispatcher
{
    /**
     * @param  string                  $event Event name (use Sdk::EVENT_FILTER_*).
     * @param  array<int|string,mixed> $input Immutable input payload.
     * @return array<int|string,mixed>        Final output (input unchanged on error).
     */
    public static function dispatch(string $event, array $input): array
    {
        $result = new FilterResult($input);

        try {
            // Joomla orders listeners by (priority ASC, plugin-name ASC) inside
            // PluginHelper::importPlugin(); we rely on that — the SDK doc spells
            // it out so third parties can tune their priority when they need to
            // run after Falang's mutation.
            AdapterRegistry::events()->trigger($event, [$input, $result]);
        } catch (\Throwable $e) {
            // Preserve mutations from listeners that completed BEFORE the
            // throw — losing them silently would be worse than the original
            // input, since downstream listeners may have already taken action
            // (e.g. ConflictManager::claim) on the partial state.
            error_log('[AI Boost FilterDispatcher] ' . $event . ' threw: ' . $e->getMessage());
        }

        return $result->getOutput();
    }

    /**
     * Dispatch and also return the FilterResult so the caller can inspect
     * the mutation log (e.g. Debug tab "Filter mutations" table).
     *
     * @param  array<int|string,mixed> $input
     */
    public static function dispatchWithLog(string $event, array $input): FilterResult
    {
        $result = new FilterResult($input);
        try {
            AdapterRegistry::events()->trigger($event, [$input, $result]);
        } catch (\Throwable $e) {
            error_log('[AI Boost FilterDispatcher] ' . $event . ' threw: ' . $e->getMessage());
        }
        return $result;
    }
}
