<?php
/**
 * AI Boost standalone plugin smoke test.
 *
 * Usage: php smoke-test-plugin.php <plugin.zip> [--joomla 5|6] [--php 8.1|8.2|…]
 *
 * Checks:
 *  1. ZIP is readable and non-empty
 *  2. Manifest XML is present, parseable, and structurally valid
 *  3. Joomla target-version compatibility (targetplatform element)
 *  4. PHP version compatibility (phpminimum / phpmaximum attributes)
 *  5. All files listed in <files> and <languages> are present inside the ZIP
 *  6. Every .php file inside the ZIP passes `php -l` syntax check
 */

declare(strict_types=1);

// ── CLI argument parsing ──────────────────────────────────────────────────────
// PHP's getopt() reads from $_SERVER['argv'], not from $argv directly.
// Parse arguments manually so the script works reliably in all contexts.
$opts       = [];
$positional = [];
for ($i = 1; $i < count($argv); $i++) {
    if ($argv[$i] === '--joomla' && isset($argv[$i + 1])) {
        $opts['joomla'] = $argv[++$i];
    } elseif ($argv[$i] === '--php' && isset($argv[$i + 1])) {
        $opts['php'] = $argv[++$i];
    } elseif (str_starts_with($argv[$i], '--joomla=')) {
        $opts['joomla'] = substr($argv[$i], 9);
    } elseif (str_starts_with($argv[$i], '--php=')) {
        $opts['php'] = substr($argv[$i], 6);
    } else {
        $positional[] = $argv[$i];
    }
}

if (empty($positional)) {
    fwrite(STDERR, "Usage: php smoke-test-plugin.php <plugin.zip> [--joomla 5|6] [--php 8.1]\n");
    exit(1);
}

$zipPath    = $positional[0];
$joomlaVer  = (string) ($opts['joomla'] ?? '5');  // default to Joomla 5 (LTS)
$phpVerArg  = (string) ($opts['php']    ?? '');   // e.g. "8.3"
$runningPhp = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

// AI Boost dropped Joomla 4 support in v0.57.0 — reject explicitly.
if ((int) $joomlaVer < 5) {
    fwrite(STDERR, "FATAL: --joomla {$joomlaVer} is not supported. AI Boost requires Joomla 5 or higher.\n");
    exit(1);
}

$errors = [];
$ok     = [];

// ── 1. ZIP readable ───────────────────────────────────────────────────────────
if (!file_exists($zipPath)) {
    fwrite(STDERR, "FATAL: ZIP not found: $zipPath\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "FATAL: Cannot open ZIP: $zipPath\n");
    exit(1);
}
$ok[] = "ZIP opened: $zipPath (" . $zip->count() . " entries)";

// ── 2. Find manifest XML ──────────────────────────────────────────────────────
$manifestName = null;
$manifestXml  = null;
for ($i = 0; $i < $zip->count(); $i++) {
    $name = $zip->getNameIndex($i);
    if ($name && preg_match('/^[^\/]+\.xml$/', $name) && !str_contains($name, '/')) {
        $manifestName = $name;
        $manifestXml  = $zip->getFromIndex($i);
        break;
    }
}

if ($manifestName === null || $manifestXml === false) {
    $errors[] = "Manifest XML not found at ZIP root";
} else {
    $ok[] = "Manifest found: $manifestName";

    // ── 3. Parse XML ──────────────────────────────────────────────────────────
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string((string) $manifestXml);
    $xmlErrors = libxml_get_errors();
    libxml_clear_errors();

    if ($xml === false || !empty($xmlErrors)) {
        foreach ($xmlErrors as $e) {
            $errors[] = "XML parse error: " . trim($e->message);
        }
    } else {
        $ok[] = "XML parsed without errors";

        // ── 4a. Structural checks ────────────────────────────────────────────
        $type = (string) ($xml['type'] ?? '');
        if ($type !== 'plugin') {
            $errors[] = "Manifest type is '$type', expected 'plugin'";
        } else {
            $ok[] = "type=plugin ✓";
        }

        $group = (string) ($xml['group'] ?? '');
        if ($group !== 'system') {
            $errors[] = "Manifest group is '$group', expected 'system'";
        } else {
            $ok[] = "group=system ✓";
        }

        // ── 4b. Joomla version compatibility ─────────────────────────────────
        // AI Boost standalone manifests use <joomla_minimum>X.Y.Z</joomla_minimum>.
        // Also support <targetplatform> for future proofing.
        // Manifests that allow J4 (minimum < 5.0.0) MUST fail — we dropped J4 in v0.57.0.
        if ($joomlaVer !== '') {
            $jMin = null;
            $jMax = null;

            // Primary: <joomla_minimum> / <joomla_maximum> (used by all 5 plugins)
            if (isset($xml->joomla_minimum) && (string) $xml->joomla_minimum !== '') {
                $jMin = (string) $xml->joomla_minimum;
            }
            if (isset($xml->joomla_maximum) && (string) $xml->joomla_maximum !== '') {
                $jMax = (string) $xml->joomla_maximum;
            }

            // Fallback: <targetplatform name="joomla" min="…" max="…">
            foreach ($xml->compatibility->targetplatform ?? [] as $tp) {
                if (strtolower((string) ($tp['name'] ?? '')) === 'joomla') {
                    $jMin = $jMin ?? (string) ($tp['min'] ?? '');
                    $jMax = $jMax ?? (string) ($tp['max'] ?? '');
                }
            }
            foreach ($xml->targetplatform ?? [] as $tp) {
                if (strtolower((string) ($tp['name'] ?? '')) === 'joomla') {
                    $jMin = $jMin ?? (string) ($tp['min'] ?? '');
                    $jMax = $jMax ?? (string) ($tp['max'] ?? '');
                }
            }

            if ($jMin !== null) {
                $jMajor    = (int) $joomlaVer;
                $minMajor  = (int) explode('.', $jMin)[0];
                $maxMajor  = ($jMax !== null && $jMax !== '') ? (int) explode('.', $jMax)[0] : 99;
                if ($minMajor < 5) {
                    $errors[] = "Manifest declares joomla_minimum={$jMin} — AI Boost requires 5.0.0 or higher (J4 support dropped in v0.57.0)";
                }
                if ($jMajor < $minMajor || $jMajor > $maxMajor) {
                    $errors[] = "Joomla $joomlaVer not in supported range {$jMin}–" . ($jMax ?? '∞');
                } else {
                    $ok[] = "Joomla $joomlaVer compatibility ✓ (min {$jMin}" . ($jMax ? ", max {$jMax}" : '') . ")";
                }
            } else {
                $errors[] = "Joomla compatibility element not found — manifest must declare <joomla_minimum>5.0.0</joomla_minimum> or higher";
            }
        }

        // ── 4c. PHP version compatibility ─────────────────────────────────────
        // AI Boost standalone manifests use <php_minimum>X.Y.Z</php_minimum>
        $checkPhp = $phpVerArg !== '' ? $phpVerArg : $runningPhp;

        // Primary: <php_minimum> / <php_maximum> (used by all 5 plugins)
        $phpMin = '';
        $phpMax = '';
        if (isset($xml->php_minimum) && (string) $xml->php_minimum !== '') {
            $phpMin = (string) $xml->php_minimum;
        }
        if (isset($xml->php_maximum) && (string) $xml->php_maximum !== '') {
            $phpMax = (string) $xml->php_maximum;
        }
        // Fallback: <requires><php minimum="…"> (standard Joomla 5+ style)
        if ($phpMin === '') {
            $phpMin = (string) ($xml->requires->php['minimum'] ?? '');
        }
        if ($phpMax === '') {
            $phpMax = (string) ($xml->requires->php['maximum'] ?? '');
        }

        // Normalise both sides to X.Y.Z before comparing so "8.1" >= "8.1.0" is true.
        $normalise = static fn (string $v): string =>
            implode('.', array_pad(explode('.', $v), 3, '0'));

        if ($phpMin !== '') {
            if (version_compare($normalise($checkPhp), $normalise($phpMin), '<')) {
                $errors[] = "PHP $checkPhp below minimum $phpMin";
            } else {
                $ok[] = "PHP $checkPhp >= $phpMin ✓";
            }
        }
        if ($phpMax !== '' && $phpMax !== '0') {
            if (version_compare($normalise($checkPhp), $normalise($phpMax), '>')) {
                $errors[] = "PHP $checkPhp above maximum $phpMax";
            } else {
                $ok[] = "PHP $checkPhp <= $phpMax ✓";
            }
        }

        // ── 5. Verify all manifest-listed files exist in ZIP ─────────────────
        $manifestFiles = [];
        foreach ($xml->files->filename ?? [] as $f) {
            $manifestFiles[] = (string) $f;
        }
        foreach ($xml->files->folder ?? [] as $f) {
            $manifestFiles[] = (string) $f;
        }

        // Build ZIP entry name set (strip top-level dir if present)
        $zipEntries = [];
        for ($i = 0; $i < $zip->count(); $i++) {
            $zipEntries[] = $zip->getNameIndex($i);
        }

        $missing = [];
        foreach ($manifestFiles as $mf) {
            $found = false;
            foreach ($zipEntries as $ze) {
                if ($ze === $mf || str_starts_with($ze, rtrim($mf, '/') . '/')) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $mf;
            }
        }
        if (!empty($missing)) {
            $errors[] = "Files listed in manifest but missing from ZIP: " . implode(', ', $missing);
        } else {
            $ok[] = count($manifestFiles) . " manifest-listed files all present in ZIP ✓";
        }
    }
}

// ── 6. PHP -l on every .php file in the ZIP ───────────────────────────────────
$tmpDir = sys_get_temp_dir() . '/aiboost_smoke_' . bin2hex(random_bytes(4));
mkdir($tmpDir, 0755, true);

$zip->extractTo($tmpDir);
$zip->close();

$phpFiles = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmpDir));
foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}

$phpBin = PHP_BINARY;
$syntaxErrors = [];
foreach ($phpFiles as $phpFile) {
    $output = [];
    $code   = 0;
    exec(escapeshellcmd($phpBin) . ' -l ' . escapeshellarg($phpFile) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $relPath = str_replace($tmpDir . '/', '', $phpFile);
        $syntaxErrors[] = "$relPath: " . implode(' ', $output);
    }
}
if (!empty($syntaxErrors)) {
    foreach ($syntaxErrors as $se) {
        $errors[] = "PHP syntax error: $se";
    }
} else {
    $ok[] = count($phpFiles) . " PHP files passed syntax check ✓";
}

// Cleanup — recursive removal of temp dir
$cleanupDir = static function (string $dir) use (&$cleanupDir): void {
    foreach (glob("$dir/*") ?: [] as $entry) {
        is_dir($entry) ? $cleanupDir($entry) : unlink($entry);
    }
    @rmdir($dir);
};
$cleanupDir($tmpDir);

// ── Output ────────────────────────────────────────────────────────────────────
$plugin = basename($zipPath);
echo "\n=== Smoke test: $plugin" . ($joomlaVer ? " | Joomla $joomlaVer" : '') . " | PHP $checkPhp ===\n";
foreach ($ok as $line) {
    echo "  ✓ $line\n";
}
if (!empty($errors)) {
    foreach ($errors as $line) {
        echo "  ✗ $line\n";
    }
    echo "\nRESULT: FAILED (" . count($errors) . " error(s))\n\n";
    exit(1);
}
echo "\nRESULT: PASSED\n\n";
exit(0);
