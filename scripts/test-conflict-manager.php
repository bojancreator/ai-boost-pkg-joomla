<?php
/**
 * AI Boost — ConflictManager Integration Test
 *
 * Standalone CLI-runnable test. Does not require PHPUnit or Joomla bootstrap.
 * Tests that ConflictManager correctly prevents two plugins from claiming the
 * same feature slot.
 *
 * Usage:
 *   php scripts/test-conflict-manager.php
 *
 * Exit code 0 = all tests passed. Exit code 1 = one or more tests failed.
 */

define('_JEXEC', 1);

// Load ConflictManager without Joomla bootstrap
require_once __DIR__ . '/../component/lib/src/ConflictManager.php';

use AiBoost\Lib\ConflictManager;

$passed = 0;
$failed = 0;

function assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  PASS  {$label}\n";
        $passed++;
    } else {
        echo "  FAIL  {$label}\n";
        $failed++;
    }
}

function assert_false(bool $condition, string $label): void
{
    assert_true(!$condition, $label);
}

function assert_equals(mixed $expected, mixed $actual, string $label): void
{
    assert_true($expected === $actual, "{$label} (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")");
}

echo "\nAI Boost — ConflictManager Integration Test\n";
echo str_repeat('=', 50) . "\n\n";

// ── Test 1: First plugin claims a slot successfully ──────────────────────────
echo "Test 1: First plugin claims a slot\n";
ConflictManager::reset();
$result = ConflictManager::claim(ConflictManager::SLOT_ROBOTS_TXT, 'aiboost_aeo');
assert_true($result, "claim() returns true for first plugin");
assert_equals('aiboost_aeo', ConflictManager::getOwner(ConflictManager::SLOT_ROBOTS_TXT), "getOwner() returns correct plugin name");

// ── Test 2: Second plugin is rejected ────────────────────────────────────────
echo "\nTest 2: Second plugin is rejected for the same slot\n";
$result2 = ConflictManager::claim(ConflictManager::SLOT_ROBOTS_TXT, 'aiboost_sitemap');
assert_false($result2, "claim() returns false for second plugin on same slot");
assert_equals('aiboost_aeo', ConflictManager::getOwner(ConflictManager::SLOT_ROBOTS_TXT), "Slot owner unchanged after rejected claim");

// ── Test 3: Same plugin can re-claim its own slot ────────────────────────────
echo "\nTest 3: Same plugin can re-claim its own slot\n";
$result3 = ConflictManager::claim(ConflictManager::SLOT_ROBOTS_TXT, 'aiboost_aeo');
assert_true($result3, "Owner plugin can re-claim its own slot");

// ── Test 4: Different slots are independent ──────────────────────────────────
echo "\nTest 4: Different plugins can claim different slots\n";
$r4a = ConflictManager::claim(ConflictManager::SLOT_SITEMAP, 'aiboost_sitemap');
$r4b = ConflictManager::claim(ConflictManager::SLOT_OG_TAGS,  'aiboost_social');
assert_true($r4a, "aiboost_sitemap claims SLOT_SITEMAP");
assert_true($r4b, "aiboost_social claims SLOT_OG_TAGS");
assert_equals('aiboost_sitemap', ConflictManager::getOwner(ConflictManager::SLOT_SITEMAP), "SLOT_SITEMAP owner");
assert_equals('aiboost_social',  ConflictManager::getOwner(ConflictManager::SLOT_OG_TAGS),  "SLOT_OG_TAGS owner");

// ── Test 5: isClaimed() reflects state accurately ───────────────────────────
echo "\nTest 5: isClaimed() accuracy\n";
assert_true(ConflictManager::isClaimed(ConflictManager::SLOT_ROBOTS_TXT), "SLOT_ROBOTS_TXT is claimed");
assert_false(ConflictManager::isClaimed(ConflictManager::SLOT_SCHEMA_ORG), "SLOT_SCHEMA_ORG is not yet claimed");

// ── Test 6: release() works correctly ───────────────────────────────────────
echo "\nTest 6: release() works correctly\n";
$released = ConflictManager::release(ConflictManager::SLOT_SITEMAP, 'aiboost_sitemap');
assert_true($released, "Owner can release its own slot");
assert_false(ConflictManager::isClaimed(ConflictManager::SLOT_SITEMAP), "Slot is unclaimed after release");

$badRelease = ConflictManager::release(ConflictManager::SLOT_OG_TAGS, 'aiboost_schema');
assert_false($badRelease, "Non-owner cannot release a slot");
assert_true(ConflictManager::isClaimed(ConflictManager::SLOT_OG_TAGS), "Slot still claimed after failed release");

// ── Test 7: reset() clears all claims ───────────────────────────────────────
echo "\nTest 7: reset() clears all claims\n";
ConflictManager::reset();
assert_false(ConflictManager::isClaimed(ConflictManager::SLOT_ROBOTS_TXT), "All slots cleared after reset");
assert_equals([], ConflictManager::getRegistry(), "Registry is empty after reset");

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n" . str_repeat('-', 50) . "\n";
echo "Results: {$passed} passed, {$failed} failed\n\n";

if ($failed > 0) {
    echo "[FAIL] Some tests failed.\n";
    exit(1);
}

echo "[PASS] All tests passed.\n";
exit(0);
