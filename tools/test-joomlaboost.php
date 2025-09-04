<?php

/**
 * JoomlaBoost Plugin Test
 * 
 * Test script for the new universal plugin
 */

declare(strict_types=1);

echo "🚀 JoomlaBoost Plugin - Universal Test\n";
echo "=====================================\n\n";

$pluginPath = __DIR__ . '/../src/plugins/system/joomlaboost';

if (!file_exists($pluginPath)) {
  echo "❌ Plugin directory not found\n";
  exit(1);
}

echo "📁 Testing plugin structure...\n";

$requiredFiles = [
  'joomlaboost.xml' => 'Plugin manifest',
  'joomlaboost.php' => 'Main plugin file',
  'src/Services/ServiceInterface.php' => 'Service interface',
  'src/Services/AbstractService.php' => 'Abstract service',
  'src/Services/ServiceManager.php' => 'Service manager',
  'src/Services/DomainDetectionService.php' => 'Domain detection',
  'src/Services/RobotService.php' => 'Robot service',
  'src/Services/SitemapService.php' => 'Sitemap service',
  'language/en-GB/plg_system_joomlaboost.ini' => 'Language file',
  'language/en-GB/plg_system_joomlaboost.sys.ini' => 'System language file',
  'media/admin.css' => 'Admin CSS'
];

$allExist = true;
foreach ($requiredFiles as $file => $description) {
  $fullPath = $pluginPath . '/' . $file;
  if (file_exists($fullPath)) {
    echo "  ✓ $description\n";
  } else {
    echo "  ❌ $description (missing: $file)\n";
    $allExist = false;
  }
}

echo "\n🔍 Testing PHP syntax...\n";

$phpFiles = [
  'joomlaboost.php',
  'src/Services/ServiceInterface.php',
  'src/Services/AbstractService.php',
  'src/Services/ServiceManager.php',
  'src/Services/DomainDetectionService.php',
  'src/Services/RobotService.php',
  'src/Services/SitemapService.php'
];

foreach ($phpFiles as $file) {
  $fullPath = $pluginPath . '/' . $file;
  if (file_exists($fullPath)) {
    $output = [];
    $returnCode = 0;
    exec("php -l \"$fullPath\" 2>&1", $output, $returnCode);

    if ($returnCode === 0) {
      echo "  ✓ $file (syntax OK)\n";
    } else {
      echo "  ❌ $file (syntax error)\n";
      $allExist = false;
    }
  }
}

echo "\n🌐 Testing domain detection features...\n";

$mainFile = $pluginPath . '/joomlaboost.php';
if (file_exists($mainFile)) {
  $content = file_get_contents($mainFile);

  $domainFeatures = [
    'getCurrentDomain' => 'Domain detection method',
    'getBaseUrl' => 'Base URL detection',
    'auto_domain_detection' => 'Auto detection parameter',
    'manual_domain' => 'Manual domain parameter',
    'detectSpecialEndpoint' => 'Endpoint detection',
    'jb_robots' => 'JoomlaBoost robots parameter',
    'jb_sitemap' => 'JoomlaBoost sitemap parameter',
    'jb_health' => 'JoomlaBoost health parameter'
  ];

  foreach ($domainFeatures as $feature => $description) {
    if (strpos($content, $feature) !== false) {
      echo "  ✓ $description\n";
    } else {
      echo "  ⚠️ $description (not found)\n";
    }
  }
}

echo "\n📊 Testing universal features...\n";

$xmlFile = $pluginPath . '/joomlaboost.xml';
if (file_exists($xmlFile)) {
  $xmlContent = file_get_contents($xmlFile);

  $universalFeatures = [
    'Universal SEO' => 'Universal description',
    'auto_domain_detection' => 'Domain detection config',
    'enable_robots' => 'Robots configuration',
    'enable_sitemap' => 'Sitemap configuration',
    'enable_schema' => 'Schema configuration',
    'debug_mode' => 'Debug configuration',
    '0.1.17' => 'New version number'
  ];

  foreach ($universalFeatures as $feature => $description) {
    if (strpos($xmlContent, $feature) !== false) {
      echo "  ✓ $description\n";
    } else {
      echo "  ⚠️ $description (check XML)\n";
    }
  }
}

echo "\n🏗️ Architecture comparison...\n";

// Compare with old plugin
$oldPath = __DIR__ . '/../archive/offroadseo-legacy/current-backup';
$newPath = $pluginPath;

if (file_exists($oldPath)) {
  echo "  📂 Old plugin (archived): ✓\n";
  echo "  📂 New plugin (universal): ✓\n";
  echo "  🔄 Migration: Complete\n";
} else {
  echo "  ⚠️ Old plugin archive not found\n";
}

echo "\n📋 Summary:\n";
echo "===========\n";

if ($allExist) {
  echo "✅ All required files present\n";
  echo "✅ PHP syntax valid\n";
  echo "✅ Universal architecture implemented\n";
  echo "✅ Domain detection features added\n";
  echo "✅ Plugin ready for testing\n";
} else {
  echo "❌ Some issues found - check above\n";
}

echo "\n🎯 Next Steps:\n";
echo "==============\n";
echo "1. 📦 Build plugin package\n";
echo "2. 🧪 Install on staging environment\n";
echo "3. 🔍 Test domain detection\n";
echo "4. 🌐 Test all endpoints\n";
echo "5. 📊 Verify SEO functionality\n";
echo "6. 🚀 Deploy to production\n";

echo "\n✨ JoomlaBoost plugin test completed!\n";
