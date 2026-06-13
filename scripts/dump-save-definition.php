<?php
/**
 * AI Boost — SettingsSaveDefinition dump shim (Plan 3 verification matrix).
 *
 * Emits, as JSON on stdout, the offline-knowable save-surface constants so the
 * Python verification-matrix generator can classify every settings key without
 * a running Joomla:
 *   - system_preserved : SYSTEM_PRESERVED_KEYS (= ImportController IMPORT_DENYLIST;
 *                        whitelisted-but-DB-only — never client/import writable)
 *   - compatibility    : COMPATIBILITY_KEYS (legacy save whitelist; private const)
 *   - legacy           : legacyKeys() (compatibility ∪ expanded opening-hours aliases)
 *   - save_only        : saveOnlyKeys()
 *
 * Only methods/constants that do NOT touch ManifestRegistry/AdapterRegistry are
 * used, so this runs standalone (CLI) exactly like dump-manifest.php.
 *
 * Usage:  php scripts/dump-save-definition.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "dump-save-definition.php is CLI only\n");
    exit(2);
}

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

require dirname(__DIR__) . '/component/lib/src/SettingsSaveDefinition.php';

use AiBoost\Lib\SettingsSaveDefinition as SSD;

$ref          = new ReflectionClass(SSD::class);
$compatibility = $ref->getReflectionConstant('COMPATIBILITY_KEYS')->getValue();

echo json_encode([
    'system_preserved' => array_values(SSD::SYSTEM_PRESERVED_KEYS),
    'compatibility'    => array_values($compatibility),
    'legacy'           => array_values(SSD::legacyKeys()),
    'save_only'        => array_values(SSD::saveOnlyKeys()),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
