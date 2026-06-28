<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * #8 — a LIKE pattern that escapes an underscore (`\_`, to match a literal
 * underscore in an `aiboost_…` prefix scan) is sql_mode-fragile: under
 * NO_BACKSLASH_ESCAPES a bare LIKE has NO escape character at all (the default
 * backslash escape is disabled), so the hard-coded backslashes become literal
 * characters to match and the scan silently returns 0 rows.
 *
 * This was a LIVE latent bug: `pkg_script.php`'s Free-package postflight disabled
 * the `aiboost_*_pro` decorators via `LIKE 'aiboost\_%\_pro'` with NO ESCAPE
 * clause, so on a NO_BACKSLASH_ESCAPES host it disabled nothing. Fixed by adding
 * the explicit `ESCAPE '\'` clause already used at the two other sites
 * (PluginRegistry::isProInstall, mod_aiboost_health). The semantics were verified
 * against dev.mysql.com + MySQL Bug #10214 (mysqli real_escape_string does NOT
 * add backslashes under NBE) — see .agents/memory/sql-like-prefix-scan.md.
 *
 * WHY a SOURCE guard (not a behavioural test): the bug only manifests under
 * sql_mode = NO_BACKSLASH_ESCAPES; the standalone test DB (FakeExtensionsDatabase)
 * ignores WHERE/LIKE entirely, and CI has no real MySQL with NBE — so a
 * result-level test would falsely pass for BOTH the broken and fixed code. The
 * reliable, sql_mode-independent invariant is therefore asserted over the SOURCE:
 * every escaped-underscore LIKE literal must carry an explicit ESCAPE clause.
 *
 * Red-green: drop the `ESCAPE '\'` from any escaped-underscore LIKE (e.g. recreate
 * the pkg_script bug) and testEscapedUnderscoreLikeAlwaysCarriesAnExplicitEscapeClause()
 * goes red naming the file:line.
 *
 * BACKLOG (post-1.0): converge all three sites onto the memory's canonical
 * sql_mode-independent form (coarse escape-free WHERE + str_starts_with /
 * str_ends_with filtering in PHP), as a gated change with a real install-path test.
 */
final class SqlLikePrefixEscapeContractTest extends TestCase
{
    private const COMPONENT = __DIR__ . '/../../';

    /** An escaped-underscore LIKE literal: `LIKE ' . $x->quote('…\_…')`. */
    private const ESCAPED_UNDERSCORE_LIKE =
        '/\bLIKE\b\s*\'\s*\.\s*\$\w+->quote\(\s*\'[^\']*\\\\_[^\']*\'\s*\)/';

    /**
     * …the same, but NOT followed by an ` . ' ESCAPE ` clause. The optional
     * whitespace lives INSIDE the lookahead (`(?!\s*\.…)`) — a `\s*` placed
     * before the lookahead would backtrack to zero and falsely flag sites that
     * DO carry ESCAPE.
     */
    private const ESCAPED_UNDERSCORE_LIKE_NO_ESCAPE =
        '/\bLIKE\b\s*\'\s*\.\s*\$\w+->quote\(\s*\'[^\']*\\\\_[^\']*\'\s*\)(?!\s*\.\s*\'\s*ESCAPE\b)/';

    public function testEscapedUnderscoreLikeAlwaysCarriesAnExplicitEscapeClause(): void
    {
        $offenders = [];
        foreach ($this->phpFiles() as $rel => $abs) {
            $code = $this->codeWithoutComments((string) file_get_contents($abs));
            if (preg_match_all(self::ESCAPED_UNDERSCORE_LIKE_NO_ESCAPE, $code, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as $hit) {
                    $offenders[] = $rel . ':' . (1 + substr_count(substr($code, 0, (int) $hit[1]), "\n"));
                }
            }
        }

        sort($offenders);
        $this->assertSame(
            [],
            $offenders,
            "An escaped-underscore LIKE ('aiboost\\_%') has no ESCAPE clause, so it returns 0 rows under "
            . "sql_mode NO_BACKSLASH_ESCAPES (the default backslash escape is disabled). Add  . ' ESCAPE ' "
            . ". \$db->quote('\\\\')  (as PluginRegistry / mod_aiboost_health do), or switch to the coarse "
            . "WHERE + str_starts_with/str_ends_with form. Offenders:\n  - " . implode("\n  - ", $offenders)
        );
    }

    /**
     * Guard against a silent false-green: if the detection regex ever stops
     * matching real code, the offender scan above would pass vacuously. Assert it
     * still sees the known escaped-underscore LIKE sites (pkg_script,
     * PluginRegistry, mod_aiboost_health — all now carrying ESCAPE).
     */
    public function testTheGuardActuallySeesEscapedUnderscoreLikeSites(): void
    {
        $total = 0;
        foreach ($this->phpFiles() as $abs) {
            $code   = $this->codeWithoutComments((string) file_get_contents($abs));
            $total += preg_match_all(self::ESCAPED_UNDERSCORE_LIKE, $code);
        }

        $this->assertGreaterThanOrEqual(
            3,
            $total,
            'Expected at least the 3 known escaped-underscore LIKE sites; the detection regex may be broken '
            . '(which would make the ESCAPE-clause guard pass vacuously).'
        );
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
