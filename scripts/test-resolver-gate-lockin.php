<?php
/**
 * AI Boost — T1·S9 resolver gate lock-in (regression-lock contract test)
 *
 * The T1 refactor moved "which page am I on?" into the ONE CMS-neutral
 * PageResolver (`component/lib/src/Page/`). Every consumer now reads
 * `AdapterRegistry::pageResolver()->resolve()` → `PageContext`; the old inline
 * `$option === 'com_content' && $view === 'article'` (and `featured`/home) gates
 * survive ONLY as a small, known set of intentional guarded fallbacks /
 * not-yet-migrated helpers.
 *
 * This test locks that in: it scans the ACTIVE codebase (`component/`, excluding
 * the authority `component/lib/src/Page/` and the test tree) for inline page-type
 * classification gates and asserts the found set EQUALS an explicit allowlist. A
 * NEW inline gate anywhere else makes this FAIL — forcing the next developer to
 * use the resolver, or to DELIBERATELY extend the allowlist with a justification.
 *
 * Standalone contract (runs in CI via run-standalone-tests.php):
 *   php scripts/test-resolver-gate-lockin.php   → exit 0 pass / non-zero fail
 *
 * Scope note: the legacy top-level `plugins/` tree (the frozen pre-component
 * standalone plugins, per CLAUDE.md) is intentionally NOT scanned — it predates
 * the resolver and receives no new development; locking it would only pin dead
 * code. New work happens under `component/`, which is what this guards.
 */

declare(strict_types=1);

$componentDir = realpath(__DIR__ . '/../component');
if ($componentDir === false) {
    fwrite(STDERR, "[FAIL] component/ directory not found\n");
    exit(1);
}

/**
 * The KNOWN, allowed inline page-classification gates in the active codebase,
 * keyed by component-relative path → exact occurrence count. Each is an
 * intentional guarded fallback or a not-yet-migrated helper, NOT a re-derivation
 * that competes with the resolver:
 *
 *   - aiboost_core/.../AiBoostCore.php (3):
 *       • detectPageType() `featured`→home + `article` gates — the absent-resolver
 *         FALLBACK for resolvePageType() (which calls the resolver first; T1·S7).
 *       • resolveCategoryToken() `article` gate — a {category} title-token helper
 *         that needs the article id; not one of the S2–S8 migrated emitters.
 *   - aiboost_schema/.../SchemaProBuilder.php (1):
 *       • the article gate reading the RAW primitives ($this->view) that are
 *         sourced from PageContext (with a $ctx fallback) — the S2 guarded
 *         consumption pattern, deliberately NOT homepage-first isArticle().
 *   - aiboost_social/.../OgTagProDecorator.php (1):
 *       • the same S3 guarded-consumer article gate.
 *
 * To add a NEW gate you MUST either route it through the resolver, or add it here
 * with a one-line justification (a deliberate, reviewed decision).
 *
 * @var array<string,int>
 */
$ALLOWLIST = [
    'plugins/system/aiboost_core/src/Extension/AiBoostCore.php'        => 3,
    'plugins/system/aiboost_schema/src/Service/SchemaProBuilder.php'   => 1,
    'plugins/system/aiboost_social/src/Service/OgTagProDecorator.php'  => 1,
];

/** A line is a page-classification gate when it compares the current VIEW to a
 *  page-type literal ('article'/'featured'). Requiring the `view` token on the
 *  line excludes sitemap-entry type checks (`$entry['type'] === 'article'`) and
 *  URL-builder strings (`'...&view=article&id='`), which are not gates.
 *
 *  Matches any equality/inequality comparison operator (===, !==, ==, !=) so a gate
 *  written with `!==`/`==` cannot slip past the lock-in; and requires the `view`
 *  TOKEN as a whole word (not a plain substring) so `preview`/`review`/`overview`
 *  no longer false-match. */
function ab_is_gate_line(string $line): bool
{
    if (!preg_match("/(?:===?|!==?)\\s*['\"](article|featured)['\"]|['\"](article|featured)['\"]\\s*(?:===?|!==?)/", $line)) {
        return false;
    }
    return preg_match('/\\bview\\b/', $line) === 1;
}

/** @return array<string,int> component-relative path → gate count */
function ab_scan(string $root): array
{
    $found = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $abs = str_replace('\\', '/', $file->getPathname());
        // Exempt the resolver authority + the test tree.
        if (strpos($abs, '/lib/src/Page/') !== false || strpos($abs, '/tests/') !== false) {
            continue;
        }
        $rel = ltrim(str_replace(str_replace('\\', '/', realpath($root)) ?: '', '', $abs), '/');
        $count = 0;
        foreach (file($abs, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (ab_is_gate_line($line)) {
                $count++;
            }
        }
        if ($count > 0) {
            $found[$rel] = $count;
        }
    }
    ksort($found);
    return $found;
}

$found = ab_scan($componentDir);
ksort($ALLOWLIST);

$pass   = 0;
$fail   = 0;
$errors = [];

// 1. Every found gate must be allowlisted with the exact count.
foreach ($found as $rel => $count) {
    if (!array_key_exists($rel, $ALLOWLIST)) {
        $fail++;
        $errors[] = "NEW inline page-type gate(s) in NON-allowlisted file: {$rel} ({$count}). "
            . "Use AdapterRegistry::pageResolver()->resolve() instead, or deliberately allowlist it.";
        continue;
    }
    if ($ALLOWLIST[$rel] !== $count) {
        $fail++;
        $errors[] = "Gate count changed in {$rel}: expected {$ALLOWLIST[$rel]}, found {$count}. "
            . "If this is a deliberate new/removed gate, update the allowlist with a justification.";
        continue;
    }
    $pass++;
    echo "  PASS  {$rel} ({$count} known gate(s))\n";
}

// 2. Every allowlisted file must still exist + still contain its gates (so a
//    stale allowlist entry can't mask a future re-introduction elsewhere).
foreach ($ALLOWLIST as $rel => $count) {
    if (!array_key_exists($rel, $found)) {
        $fail++;
        $errors[] = "Allowlisted file no longer contains its gate(s): {$rel} (expected {$count}). "
            . "If the gate was removed/migrated, delete this allowlist entry.";
    }
}

echo "\n" . str_repeat('-', 60) . "\n";
if ($fail === 0) {
    echo "[PASS] resolver gate lock-in: {$pass} allowlisted file(s), no rogue inline gates.\n";
    exit(0);
}

foreach ($errors as $e) {
    echo "  FAIL  {$e}\n";
}
echo "[FAIL] resolver gate lock-in: {$fail} problem(s).\n";
exit(1);
