<?php

/**
 * Canonical tag stripping regex — standalone verification script
 *
 * Verifies that CanonicalService::injectIntoBuffer() correctly removes every
 * real-world <link rel="canonical"> variant while leaving unrelated tags alone.
 *
 * Run: php plugin/src/plugins/system/joomlaboost/tests/canonical-regex-test.php
 *
 * Exit 0 = all assertions passed.
 * Exit 1 = one or more assertions failed (details printed to stdout).
 *
 * @package JoomlaBoost
 * @since   0.37.1
 */

declare(strict_types=1);

/**
 * The exact regex used in CanonicalService::injectIntoBuffer() (v0.37.1).
 *
 * Pattern breakdown:
 *   <link\b                 — opening tag; word-boundary avoids false <linkfoo matches
 *   (?:                    — non-capturing group: attributes before rel=
 *     "[^">]*"             —   double-quoted attr value; [^">] excludes BOTH " and >
 *                               Critical: excluding > prevents this alternation from
 *                               spanning across tag boundaries in the HTML buffer
 *     |'[^'>]*'            —   same boundary protection for single-quoted values
 *     |[^>]                —   or any single non-> char (spaces, attr names, =, etc.)
 *   )*
 *   \brel\s*=\s*           — rel attribute; \s*=\s* tolerates spaces around =
 *   ["']canonical["']      — value "canonical" or 'canonical' (mixed quotes allowed)
 *   (?:"[^">]*"|'[^'>]*'|[^>])* — same boundary-safe group after rel=
 *   \s*\/?>                — optional self-closing slash (with optional space) + >
 *   \s*                    — trailing whitespace/newline consumed after the tag
 *
 * Flags: i = case-insensitive, s = DOTALL (handles multiline tags)
 */
const CANONICAL_REGEX = '/<link\b(?:"[^">]*"|\'[^\'>]*\'|[^>])*\brel\s*=\s*["\']canonical["\'](?:"[^">]*"|\'[^\'>]*\'|[^>])*\s*\/?>\s*/is';

$passed = 0;
$failed = 0;
$errors = [];

/**
 * Assert that after applying the regex the $input matches $expected.
 *
 * @param string $label    Human-readable test label
 * @param string $input    HTML snippet that may contain a canonical tag
 * @param string $expected What the snippet should look like after stripping
 */
function assertStrips(string $label, string $input, string $expected): void
{
    global $passed, $failed, $errors;
    $result = preg_replace(CANONICAL_REGEX, '', $input) ?? $input;
    if ($result === $expected) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        $errors[] = $label;
        echo "  FAIL  $label\n";
        echo "        Input:    " . json_encode($input) . "\n";
        echo "        Expected: " . json_encode($expected) . "\n";
        echo "        Got:      " . json_encode($result) . "\n";
    }
}

// ---------------------------------------------------------------------------
// GROUP 1 — Variants that MUST be stripped to an empty string
// ---------------------------------------------------------------------------
echo "\n--- GROUP 1: must strip ---\n";

assertStrips(
    '1. Standard: rel before href, double quotes',
    '<link rel="canonical" href="https://example.com/">',
    ''
);

assertStrips(
    '2. Single quotes',
    "<link rel='canonical' href='https://example.com/'>",
    ''
);

assertStrips(
    '3. Self-closing, double quotes',
    '<link rel="canonical" href="https://example.com/" />',
    ''
);

assertStrips(
    '4. Self-closing, single quotes',
    "<link rel='canonical' href='https://example.com/' />",
    ''
);

assertStrips(
    '5. href before rel (YOOtheme / Warp output)',
    '<link href="https://example.com/" rel="canonical">',
    ''
);

assertStrips(
    '6. href before rel, single quotes, self-closing',
    "<link href='https://example.com/' rel='canonical' />",
    ''
);

assertStrips(
    '7. Extra attribute before rel (id)',
    '<link id="canon" rel="canonical" href="https://example.com/">',
    ''
);

assertStrips(
    '8. Extra attribute after href (data-plugin)',
    '<link rel="canonical" href="https://example.com/" data-plugin="yootheme">',
    ''
);

assertStrips(
    '9. Multiple extra attributes',
    '<link rel="canonical" href="https://example.com/" data-joomla="1" id="jcanon">',
    ''
);

assertStrips(
    '10. Uppercase tag and attributes',
    '<LINK REL="CANONICAL" HREF="https://example.com/">',
    ''
);

assertStrips(
    '11. Mixed case tag/attributes',
    '<Link Rel="canonical" Href="https://example.com/">',
    ''
);

assertStrips(
    '12. Spaces around = sign',
    '<link rel = "canonical" href = "https://example.com/">',
    ''
);

assertStrips(
    '13. Multi-line tag (page-builder style)',
    "<link\n  rel=\"canonical\"\n  href=\"https://example.com/\"\n>",
    ''
);

assertStrips(
    '14. Query string in href',
    '<link href="https://example.com/path?a=1&b=2" rel="canonical">',
    ''
);

assertStrips(
    '15. Trailing whitespace and newline after tag',
    '<link href="https://example.com/" rel="canonical">  ' . "\n",
    ''
);

assertStrips(
    '16. Space before self-closing slash',
    '<link rel="canonical" href="https://example.com/" / >',
    ''
);

assertStrips(
    '17. Mixed quotes on rel vs href',
    '<link rel="canonical" href=\'https://example.com/\'>',
    ''
);

// ---------------------------------------------------------------------------
// GROUP 2 — Tags that must NOT be stripped
// ---------------------------------------------------------------------------
echo "\n--- GROUP 2: must NOT strip ---\n";

assertStrips(
    'A. Stylesheet link — must stay intact',
    '<link rel="stylesheet" href="style.css">',
    '<link rel="stylesheet" href="style.css">'
);

assertStrips(
    'B. Alternate link — must stay intact',
    '<link rel="alternate" href="https://example.com/en/">',
    '<link rel="alternate" href="https://example.com/en/">'
);

assertStrips(
    'C. Anchor tag with rel=canonical — must stay intact',
    '<a href="https://example.com/" rel="canonical">Visit</a>',
    '<a href="https://example.com/" rel="canonical">Visit</a>'
);

assertStrips(
    'D. rel="canonical preconnect" (multi-value) — must stay intact',
    '<link rel="canonical preconnect" href="https://example.com/">',
    '<link rel="canonical preconnect" href="https://example.com/">'
);

// ---------------------------------------------------------------------------
// GROUP 3 — Full buffer simulation (tag inside a real <head>)
// ---------------------------------------------------------------------------
echo "\n--- GROUP 3: realistic buffer simulation ---\n";

$buffer = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="/css/style.css">
  <link rel="canonical" href="https://example.com/old-path">
  <link href="https://example.com/old-path" rel="canonical" />
  <title>Test Page</title>
</head>
<body><p>Hello</p></body>
</html>
HTML;

$stripped = preg_replace(CANONICAL_REGEX, '', $buffer) ?? $buffer;

$stillHasCanonical = (bool) preg_match('/<link[^>]+canonical/i', $stripped);
$stylesheetPreserved = str_contains($stripped, '<link rel="stylesheet"');

if (!$stillHasCanonical && $stylesheetPreserved) {
    $passed++;
    echo "  PASS  Buffer: both canonical tags removed, stylesheet preserved\n";
} else {
    $failed++;
    $errors[] = 'Buffer simulation';
    if ($stillHasCanonical) {
        echo "  FAIL  Buffer: canonical tag NOT removed\n";
    }
    if (!$stylesheetPreserved) {
        echo "  FAIL  Buffer: stylesheet tag was incorrectly removed\n";
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "\n--- Results ---\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if ($failed > 0) {
    echo "\nFailed tests:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}

echo "All tests passed.\n";
exit(0);
