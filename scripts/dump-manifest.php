<?php
/**
 * AI Boost — Manifest dump shim for codegen pipeline.
 *
 * Invoked by scripts/codegen-from-manifest.py and the build script. Defines
 * the minimum stubs the per-tab manifest files expect so they can be
 * `require`d outside of a Joomla request, then emits the merged static
 * manifest as JSON on stdout.
 *
 * Runtime fields contributed via the onAiBoostRegisterFields event are
 * intentionally NOT included — codegen only ever works against the static
 * source-of-truth files in component/lib/src/Manifest/.
 *
 * Usage:  php scripts/dump-manifest.php > /tmp/manifest.json
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "dump-manifest.php is CLI only\n");
    exit(2);
}

// Joomla guard token expected at the top of every manifest file.
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

$root      = dirname(__DIR__);
$mfDir     = $root . '/component/lib/src/Manifest';
$tabs      = ['core', 'schema', 'aeo', 'og', 'hreflang', 'code'];
$allFields = [];

foreach ($tabs as $tab) {
    $path = $mfDir . '/' . $tab . '.php';
    if (!file_exists($path)) {
        continue;
    }
    /** @var array<int, array<string,mixed>> $entries */
    $entries = require $path;
    if (!is_array($entries)) {
        continue;
    }
    foreach ($entries as $entry) {
        if (!is_array($entry) || empty($entry['key'])) {
            continue;
        }
        $allFields[] = array_merge([
            'key'           => '',
            'tab'           => $tab,
            'section'       => 'general',
            'label'         => '',
            'type'          => 'toggle',
            'default'       => '',
            'tier'          => 'free',
            'sku'           => 'core',
            'integration'   => null,
            'description'   => '',
            'dependsOn'     => null,
            'options'       => null,
            'feature_class' => null,
            'health'        => null,
            'i18n'          => null,
        ], $entry);
    }
}

echo json_encode($allFields, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
