<?php

declare(strict_types=1);

namespace AiBoost\Tests\Lib;

use AiBoost\Lib\ConflictDetector;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Regression coverage for the Admin Tools (Akeeba) conflict check.
 *
 * The check used to look for #__extensions.element 'plg_system_admintools'
 * (the install-directory name, not the element value), so it NEVER fired.
 * The fix detects the real element ('admintools' system plugin OR the
 * 'com_admintools' component) and only warns when AI Boost is itself
 * managing robots.txt — the only file the two tools actually contend for.
 */
final class ConflictDetectorAdminToolsTest extends TestCase
{
    public function testFiresWhenAdminToolsPluginEnabledAndRobotsManaged(): void
    {
        $results = $this->runAdminToolsCheck(
            [['type' => 'plugin', 'element' => 'admintools', 'folder' => 'system']],
            ['enable_robots' => '1']
        );

        self::assertCount(1, $results);
        self::assertSame('conflict_admintools', $results[0]['id']);
        self::assertSame('warning', $results[0]['status']);
    }

    public function testFiresWhenAdminToolsComponentEnabled(): void
    {
        $results = $this->runAdminToolsCheck(
            [['type' => 'component', 'element' => 'com_admintools', 'folder' => '']],
            ['enable_robots' => '1']
        );

        self::assertCount(1, $results);
        self::assertSame('conflict_admintools', $results[0]['id']);
    }

    public function testSilentWhenRobotsManagementOff(): void
    {
        $results = $this->runAdminToolsCheck(
            [['type' => 'plugin', 'element' => 'admintools', 'folder' => 'system']],
            ['enable_robots' => '0']
        );

        self::assertSame([], $results, 'No robots.txt management means no robots.txt conflict.');
    }

    public function testSilentWhenAdminToolsNotInstalled(): void
    {
        $results = $this->runAdminToolsCheck([], ['enable_robots' => '1']);

        self::assertSame([], $results);
    }

    public function testDoesNotMatchLegacyPluginDirectoryName(): void
    {
        // The bogus legacy element 'plg_system_admintools' must NOT be detected;
        // only the real #__extensions.element 'admintools' counts.
        $results = $this->runAdminToolsCheck(
            [['type' => 'plugin', 'element' => 'plg_system_admintools', 'folder' => 'system']],
            ['enable_robots' => '1']
        );

        self::assertSame([], $results);
    }

    public function testDefaultsToRobotsManagedWhenSettingMissing(): void
    {
        // enable_robots defaults to '1' in the manifest, so a settings blob
        // without the key is still treated as managing robots.txt.
        $results = $this->runAdminToolsCheck(
            [['type' => 'plugin', 'element' => 'admintools', 'folder' => 'system']],
            []
        );

        self::assertCount(1, $results);
    }

    // ── Harness ─────────────────────────────────────────────────────────────

    /**
     * @param list<array{type:string,element:string,folder:string}> $enabledExtensions
     * @param array<string,mixed> $settings
     * @return list<array<string,mixed>>
     */
    private function runAdminToolsCheck(array $enabledExtensions, array $settings): array
    {
        $detector = new ConflictDetector($this->makeDb($enabledExtensions), $settings, []);

        // checkAdminTools() is private; invoke it directly so the rest of
        // scan() (which touches the filesystem via checkJoomlaOg) stays out
        // of the way.
        $method = (new ReflectionClass(ConflictDetector::class))->getMethod('checkAdminTools');
        $method->setAccessible(true);

        $results = [];
        $method->invokeArgs($detector, [&$results]);

        return $results;
    }

    /**
     * Minimal #__extensions fake: an extension "exists + enabled" when a query's
     * type/element/folder conditions match one of the configured rows.
     *
     * @param list<array{type:string,element:string,folder:string}> $enabledExtensions
     */
    private function makeDb(array $enabledExtensions): DatabaseInterface
    {
        return new class ($enabledExtensions) implements DatabaseInterface {
            /** @var list<array{type:string,element:string,folder:string}> */
            private array $enabled;
            private ?object $query = null;

            /** @param list<array{type:string,element:string,folder:string}> $enabled */
            public function __construct(array $enabled)
            {
                $this->enabled = $enabled;
            }

            public function getQuery(bool $new = false): object
            {
                return new class {
                    /** @var list<string> */
                    public array $conds = [];
                    public function select($columns)
                    {
                        return $this;
                    }
                    public function from($tables)
                    {
                        return $this;
                    }
                    public function where($conditions, $glue = 'AND')
                    {
                        foreach ((array) $conditions as $c) {
                            $this->conds[] = (string) $c;
                        }
                        return $this;
                    }
                };
            }

            public function setQuery(object $query, int $offset = 0, int $limit = 0): static
            {
                $this->query = $query;
                return $this;
            }

            public function loadResult(): mixed
            {
                $vals = [];
                foreach ((array) ($this->query->conds ?? []) as $cond) {
                    if (preg_match('/^(type|element|folder|enabled)\s*=\s*(.*)$/', (string) $cond, $m)) {
                        $vals[$m[1]] = $m[2];
                    }
                }

                foreach ($this->enabled as $ext) {
                    $folderOk = ($ext['folder'] ?? '') === ''
                        || ($vals['folder'] ?? '') === $ext['folder'];
                    if (($vals['type'] ?? '') === $ext['type']
                        && ($vals['element'] ?? '') === $ext['element']
                        && $folderOk) {
                        return 1;
                    }
                }
                return 0;
            }

            public function loadAssocList(?string $key = null): array
            {
                return [];
            }
            public function loadObjectList(?string $key = null): array
            {
                return [];
            }
            public function quote(mixed $text, bool $escape = true): string
            {
                return (string) $text;
            }
            public function quoteName(mixed $name, mixed $as = null): mixed
            {
                return (string) $name;
            }
            public function execute(): bool
            {
                return true;
            }
            public function insertObject(string $table, object &$object, ?string $key = null): bool
            {
                return true;
            }
            public function updateObject(string $table, object &$object, mixed $key, bool $nulls = false): bool
            {
                return true;
            }
        };
    }
}
