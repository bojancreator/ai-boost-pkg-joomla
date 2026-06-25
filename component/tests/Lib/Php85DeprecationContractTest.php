<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * PHP 8.5 readiness — our shipped code must not itself emit a deprecation/warning
 * on PHP 8.5 (we support PHP 8.1–8.5). Two language-level patterns are guarded
 * here because PHP 8.5 was observed actively warning on them on a live host:
 *
 *  1. ReflectionProperty::setAccessible() / ReflectionMethod::setAccessible() —
 *     a no-op since PHP 8.1 (reflection grants access automatically) and now
 *     *deprecated* in PHP 8.5 (E_DEPRECATED). A try/catch does NOT swallow it
 *     (an E_DEPRECATED diagnostic is not a \Throwable). This was a LIVE finding:
 *     AiBoostAeo::detectMarkdownRequest() called $prop->setAccessible(true) before
 *     clearing the Uri instance cache on a `.md` request — removed (the no-op is
 *     unnecessary on 8.1+). See .agents/memory/php85-no-setaccessible.md.
 *
 *  2. Non-canonical casts — (boolean) (integer) (double) (real) (binary) — are
 *     deprecated in PHP 8.5 in favour of the canonical (bool) (int) (float)
 *     (string) forms. (The canonical forms are fine and are NOT flagged.)
 *
 * WHY a SOURCE guard: PHP 8.5 is not the CI runtime, and the offending paths are
 * conditionally reached (e.g. the markdown route only fires on a `.md` request),
 * so a behavioural test would not reliably surface either pattern. The reliable,
 * runtime-independent invariant is asserted over the SOURCE.
 *
 * Red-green: re-add `$prop->setAccessible(true);` (recreate the AeoExtension bug)
 * or write a `(boolean)` cast and the matching test goes red naming the file:line.
 */
final class Php85DeprecationContractTest extends TestCase
{
    private const COMPONENT = __DIR__ . '/../../';

    /** Any `->setAccessible(` call (ReflectionProperty/Method). */
    private const SET_ACCESSIBLE = '/->\s*setAccessible\s*\(/';

    /** A non-canonical cast operator: (boolean) (integer) (double) (real) (binary). */
    private const NON_CANONICAL_CAST = '/\(\s*(?:boolean|integer|double|real|binary)\s*\)/';

    public function testNoSetAccessibleCallsInShippedCode(): void
    {
        $offenders = $this->scan(self::SET_ACCESSIBLE);

        $this->assertSame(
            [],
            $offenders,
            "ReflectionProperty/Method::setAccessible() is deprecated in PHP 8.5 (and a no-op since 8.1). "
            . "Remove the call — reflection on a private/protected member already works without it on PHP 8.1+. "
            . "Offenders:\n  - " . implode("\n  - ", $offenders)
        );
    }

    public function testNoNonCanonicalCastsInShippedCode(): void
    {
        $offenders = $this->scan(self::NON_CANONICAL_CAST);

        $this->assertSame(
            [],
            $offenders,
            "Non-canonical casts (boolean)/(integer)/(double)/(real)/(binary) are deprecated in PHP 8.5. "
            . "Use the canonical forms (bool)/(int)/(float)/(string). Offenders:\n  - "
            . implode("\n  - ", $offenders)
        );
    }

    /**
     * Guard against a silent false-green: if either detection regex stopped
     * matching, the offender scans above would pass vacuously. Assert both regexes
     * still match their canonical example strings.
     */
    public function testDetectionRegexesActuallyMatch(): void
    {
        $this->assertSame(1, preg_match(self::SET_ACCESSIBLE, '$prop->setAccessible(true);'));
        $this->assertSame(1, preg_match(self::NON_CANONICAL_CAST, '$x = (boolean) $y;'));
        // Canonical casts must NOT be flagged.
        $this->assertSame(0, preg_match(self::NON_CANONICAL_CAST, '$x = (bool) $y;'));
        $this->assertSame(0, preg_match(self::NON_CANONICAL_CAST, '$x = (int) $y;'));
    }

    /** @return list<string> sorted "rel/path.php:line" offenders for $regex */
    private function scan(string $regex): array
    {
        $offenders = [];
        foreach ($this->phpFiles() as $rel => $abs) {
            $code = $this->codeWithoutComments((string) file_get_contents($abs));
            if (preg_match_all($regex, $code, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $offenders[] = $rel . ':' . (1 + substr_count(substr($code, 0, (int) $hit[1]), "\n"));
                }
            }
        }
        sort($offenders);
        return $offenders;
    }

    /** @return array<string,string> relative path => absolute path */
    private function phpFiles(): array
    {
        $base  = realpath(self::COMPONENT);
        $files = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if (preg_match('#/(tests|vendor|node_modules)/#', $path)) {
                continue;
            }
            $files[ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($base))), '/')] = $file->getPathname();
        }
        return $files;
    }

    /** Source with comment/doc-comment tokens blanked (newlines preserved). */
    private function codeWithoutComments(string $src): string
    {
        $out = '';
        foreach (token_get_all($src) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $out .= str_repeat("\n", substr_count($token[1], "\n"));
                    continue;
                }
                $out .= $token[1];
            } else {
                $out .= $token;
            }
        }
        return $out;
    }
}
