<?php
/**
 * AI Boost — Standalone Safety Test Runner (Task #550)
 *
 * Auto-discovers and runs every standalone CLI test in `scripts/test-*.php`,
 * each in its own PHP subprocess so that constant/namespace/state defined by
 * one test cannot leak into another. Used as the regression gate in CI so a
 * future refactor that breaks a guarded behaviour fails the build instead of
 * slipping through to staging.
 *
 * Each discovered test must follow the standalone contract:
 *   - runnable as `php scripts/test-<name>.php`
 *   - exit code 0 = all assertions passed, non-zero = at least one failed
 *
 * Usage:
 *   php scripts/run-standalone-tests.php
 *
 * Exit code 0 = every standalone test passed.
 * Exit code 1 = one or more standalone tests failed (or none were found).
 */

declare(strict_types=1);

$scriptsDir = __DIR__;
$self       = basename(__FILE__);

$tests = glob($scriptsDir . '/test-*.php');
sort($tests);

if (!$tests) {
    fwrite(STDERR, "[FAIL] No standalone tests matched scripts/test-*.php\n");
    exit(1);
}

$phpBinary = PHP_BINARY ?: 'php';

echo str_repeat('=', 60) . "\n";
echo "AI Boost — running " . count($tests) . " standalone safety test(s)\n";
echo "PHP " . PHP_VERSION . " (" . $phpBinary . ")\n";
echo str_repeat('=', 60) . "\n\n";

$failures = [];

foreach ($tests as $test) {
    $name = basename($test);
    if ($name === $self) {
        continue;
    }

    echo "▶ {$name}\n";
    echo str_repeat('-', 60) . "\n";

    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($test);
    passthru($command, $exitCode);

    echo "\n";
    if ($exitCode === 0) {
        echo "✔ {$name} passed\n\n";
    } else {
        echo "✘ {$name} FAILED (exit {$exitCode})\n\n";
        $failures[] = $name;
    }
}

echo str_repeat('=', 60) . "\n";
$total  = count($tests);
$passed = $total - count($failures);
echo "Standalone test summary: {$passed}/{$total} passed\n";

if ($failures) {
    echo "[FAIL] Failing tests: " . implode(', ', $failures) . "\n";
    exit(1);
}

echo "[PASS] All standalone safety tests passed.\n";
exit(0);
