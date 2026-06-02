<?php
/**
 * AI Boost — SnippetTargetingService
 *
 * Decides whether a given snippet should fire on the current page request.
 *
 * Targeting logic:
 *   all        → always fires
 *   homepage   → fires only on the Joomla default home menu item
 *   articles   → fires only when view=article (com_content)
 *   categories → fires only when view=category (com_content)
 *   specific   → fires only when the active menu item ID is in the
 *                comma-separated snippet_menu_ids list
 *
 * User-state filtering (independent of page target):
 *   all        → everyone
 *   logged_in  → fires only for authenticated users
 *   guests     → fires only for anonymous visitors
 *
 * @package     AiBoost\Plugin\System\AiBoostCode
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostCode\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class SnippetTargetingService
{
    /** Cached request context so it is built only once per request. */
    private ?array $ctx = null;

    /**
     * Build (and cache) the request context array.
     *
     * @return array{option:string, view:string, id:int, Itemid:int, is_home:bool}
     */
    public function buildContext(): array
    {
        if ($this->ctx !== null) {
            return $this->ctx;
        }

        $app   = Factory::getApplication();
        $input = $app->getInput();
        $menu  = $app->getMenu();

        $active  = $menu ? $menu->getActive() : null;
        $isHome  = ($active !== null && (bool) ($active->home ?? false));

        $this->ctx = [
            'option'  => $input->getCmd('option', ''),
            'view'    => $input->getCmd('view', ''),
            'id'      => $input->getInt('id', 0),
            'Itemid'  => $input->getInt('Itemid', 0),
            'is_home' => $isHome,
        ];

        return $this->ctx;
    }

    /**
     * Return true if the snippet's page-targeting rule matches the current request.
     *
     * @param  array $snippet Associative array with at minimum 'snippet_target' and
     *                        optionally 'snippet_menu_ids' (comma-separated integers).
     * @return bool
     */
    public function matchesTarget(array $snippet): bool
    {
        $target = trim((string) ($snippet['snippet_target'] ?? 'all'));
        $ctx    = $this->buildContext();

        switch ($target) {
            case 'all':
                return true;

            case 'homepage':
                return $ctx['is_home'];

            case 'articles':
                return $ctx['option'] === 'com_content' && $ctx['view'] === 'article';

            case 'categories':
                return $ctx['option'] === 'com_content' && $ctx['view'] === 'category';

            case 'specific':
                $ids = $this->parseIds((string) ($snippet['snippet_menu_ids'] ?? ''));
                if (empty($ids)) {
                    return true; // empty list treated as "all"
                }
                // Primary: match Itemid from request input
                // Fallback: match active menu item ID when Itemid is 0 (uncommon routing)
                $itemId = $ctx['Itemid'];
                if ($itemId === 0) {
                    try {
                        $active = \Joomla\CMS\Factory::getApplication()->getMenu()->getActive();
                        if ($active) {
                            $itemId = (int) $active->id;
                        }
                    } catch (\Throwable $e) {}
                }
                return in_array($itemId, $ids, true);

            default:
                return true;
        }
    }

    /**
     * Return true if the snippet's user-state filter matches the current visitor.
     *
     * @param  array $snippet Associative array with 'snippet_user_state'.
     * @return bool
     */
    public function matchesUserState(array $snippet): bool
    {
        $state = trim((string) ($snippet['snippet_user_state'] ?? 'all'));

        if ($state === 'all') {
            return true;
        }

        try {
            $user    = Factory::getApplication()->getIdentity();
            $isGuest = ($user === null || (bool) $user->guest);
        } catch (\Throwable $e) {
            return true; // safe default
        }

        if ($state === 'logged_in') {
            return !$isGuest;
        }
        if ($state === 'guests') {
            return $isGuest;
        }

        return true;
    }

    /**
     * Combined check: fires only when both target and user-state match.
     */
    public function shouldFire(array $snippet): bool
    {
        return $this->matchesTarget($snippet) && $this->matchesUserState($snippet);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Parse a comma/space-separated string of menu item IDs into an int[].
     */
    private function parseIds(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $parts = preg_split('/[\s,]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ids   = [];
        foreach ($parts as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}
