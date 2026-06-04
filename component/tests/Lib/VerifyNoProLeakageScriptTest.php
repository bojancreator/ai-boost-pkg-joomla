<?php

namespace AiBoost\Tests\Lib;

use PHPUnit\Framework\TestCase;

/**
 * Smoke-test the scripts/verify-no-pro-leakage.py script by feeding it
 * synthetic ZIPs and asserting the documented exit codes.
 *
 * The script is the last line of defence against Pro logic shipping in the
 * Free package — but it's only ever run from build-package-zip.py. If the
 * script silently stops detecting `@pro` markers (e.g. someone widens the
 * ALLOW_FILES list or flips ADVISORY_MODE back to True) every future Free
 * ZIP would leak Pro logic without anyone noticing.
 *
 * The test runs Python via exec(). If no usable interpreter is available we
 * skip rather than fail — CI installs Python unconditionally, this is only
 * protective for very stripped-down local dev environments.
 */
final class VerifyNoProLeakageScriptTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../../scripts/verify-no-pro-leakage.py';

    private string $tmpDir;
     private string $python;

    protected function setUp(): void
    {
        if (!is_file(self::SCRIPT)) {
            $this->markTestSkipped('verify-no-pro-leakage.py not present.');
        }
        $this->python = $this->findPython();
        putenv('PYTHONIOENCODING=utf-8');
        $this->tmpDir = sys_get_temp_dir() . '/aiboost-leakage-' . uniqid('', true);
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
            $this->rrmdir($this->tmpDir);
        }
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            if (is_dir($p)) {
                $this->rrmdir($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }

    private function buildZip(string $name, array $files): string
    {
        $path = $this->tmpDir . '/' . $name;
        $source = $this->tmpDir . '/source-' . uniqid('', true);
        mkdir($source, 0777, true);

        foreach ($files as $rel => $contents) {
            $filePath = $source . '/' . str_replace('\\', '/', (string) $rel);
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($filePath, $contents);
        }

        $zipper = $this->tmpDir . '/make-zip-' . uniqid('', true) . '.py';
        file_put_contents($zipper, <<<'PY'
import os
import sys
import zipfile

root, out = sys.argv[1], sys.argv[2]
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as archive:
    for current, _, files in os.walk(root):
        for name in files:
            full = os.path.join(current, name)
            archive.write(full, os.path.relpath(full, root).replace(os.sep, '/'))
PY);
        $cmd = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg($this->python),
            escapeshellarg($zipper),
            escapeshellarg($source),
            escapeshellarg($path)
        );
        exec($cmd, $output, $exit);
        $this->assertSame(0, $exit, "Failed to create test ZIP. Output:\n" . implode("\n", $output));
        $this->assertFileExists($path, 'Python ZIP helper did not create the expected archive.');

        return $path;
    }

    private function runScript(string $zipPath): array
    {
        $cmd = sprintf('%s %s %s 2>&1', escapeshellarg($this->python), escapeshellarg(self::SCRIPT), escapeshellarg($zipPath));
        exec($cmd, $output, $exit);
        return ['exit' => $exit, 'output' => implode("\n", $output)];
    }

    private function findPython(): string
    {
        foreach ($this->pythonCandidates() as $candidate) {
            exec(escapeshellarg($candidate) . ' --version 2>&1', $output, $exit);
            if ($exit === 0) {
                return $candidate;
            }
        }
        $this->markTestSkipped('Python not available in PATH.');
    }

    /** @return list<string> */
    private function pythonCandidates(): array
    {
        return PHP_OS_FAMILY === 'Windows' ? ['python', 'python3'] : ['python3', 'python'];
    }

    public function testCleanPackagePassesWithExitZero(): void
    {
        $zip = $this->buildZip('pkg_clean.zip', [
            'admin/foo.php' => "<?php\n// no Pro tokens here.\nclass Foo {}\n",
            'admin/bar.php' => "<?php\necho 'just a free helper';\n",
        ]);

        $result = $this->runScript($zip);

        $this->assertSame(0, $result['exit'], "Clean package must exit 0. Output:\n" . $result['output']);
        $this->assertStringContainsString('No Pro tokens found', $result['output']);
    }

    public function testProTokenInRandomFileFailsWithExitOne(): void
    {
        $zip = $this->buildZip('pkg_pro_token.zip', [
            'admin/leaky.php' => "<?php\n// @pro marker that should never ship in free\nclass Leaky {}\n",
        ]);

        $result = $this->runScript($zip);

        $this->assertSame(
            1,
            $result['exit'],
            "Pro token in a non-allowlisted file must fail with exit 1. Output:\n" . $result['output']
        );
        $this->assertStringContainsString('@pro', $result['output']);
    }

    public function testProGateCallInPlainFileFailsWithExitOne(): void
    {
        $zip = $this->buildZip('pkg_progate.zip', [
            'admin/whatever.php' => "<?php\nif (ProGate::isPro()) { echo 'pro'; }\n",
        ]);

        $result = $this->runScript($zip);

        $this->assertSame(1, $result['exit'], "ProGate:: call outside the allowlist must fail. Output:\n" . $result['output']);
        $this->assertStringContainsString('ProGate::', $result['output']);
    }

    public function testProTokenInsideBundledProPluginZipFailsWithExitOne(): void
    {
        // Build an inner Pro sub-ZIP whose ProGate.php uses @pro markers —
        // it goes inside packages/ of the outer free ZIP. The script
        // recursively inspects packages/*.zip and any Pro token there fails.
        $innerPath = $this->buildZip('plg_aiboost_random_pro-1.0.0.zip', [
            // Use a path NOT in ALLOW_FILES so the script reports it.
            'plugins/system/aiboost_random_pro/src/Features/Foo.php' => "<?php\n// @pro start\nclass Foo {}\n",
        ]);

        $zip = $this->buildZip('pkg_with_inner_pro.zip', [
            'manifest.xml'                   => '<extension/>',
            'packages/' . basename($innerPath) => file_get_contents($innerPath),
        ]);

        $result = $this->runScript($zip);

        $this->assertSame(
            1,
            $result['exit'],
            "Pro token inside packages/*.zip must fail. Output:\n" . $result['output']
        );
        $this->assertStringContainsString('@pro', $result['output']);
    }
}
