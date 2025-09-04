<?php

/**
 * Test verzija pluginova
 */

require_once __DIR__ . '/src/plugins/system/joomlaboost/src/Version.php';

use JoomlaBoost\Plugin\System\JoomlaBoost\Version as JoomlaBoostVersion;

echo "=== TEST VERZIJE JOOMLABOOST PLUGINA ===\n\n";
echo "JoomlaBoost Plugin:\n";
echo "- Ime: " . JoomlaBoostVersion::PLUGIN_NAME . "\n";
echo "- Verzija: " . JoomlaBoostVersion::PLUGIN_VERSION . "\n";
echo "- Debug string: " . JoomlaBoostVersion::getDebugString() . "\n";
echo "- Release datum: " . JoomlaBoostVersion::RELEASE_DATE . "\n\n";

echo "=== KOMPLETNE INFORMACIJE ===\n\n";
print_r(JoomlaBoostVersion::getVersionInfo());
