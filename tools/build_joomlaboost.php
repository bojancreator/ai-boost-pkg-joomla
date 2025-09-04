<?php

/**
 * JoomlaBoost Plugin Builder
 * 
 * Creates installable ZIP package for JoomlaBoost plugin
 */

declare(strict_types=1);

echo "📦 JoomlaBoost Plugin Builder\n";
echo "============================\n\n";

$baseDir = __DIR__ . '/..';
$sourceDir = $baseDir . '/src/plugins/system/joomlaboost';
$buildDir = $baseDir . '/tools/__build';
$version = '0.1.17';
$timestamp = date('Y-m-d_H-i-s');
$zipName = "joomlaboost-{$version}.zip";

// Check source directory
if (!is_dir($sourceDir)) {
  echo "❌ Source directory not found: $sourceDir\n";
  exit(1);
}

echo "📁 Source directory: $sourceDir\n";
echo "📦 Building version: $version\n";
echo "🕒 Timestamp: $timestamp\n\n";

// Create build directory
if (!is_dir($buildDir)) {
  mkdir($buildDir, 0755, true);
}

// Clean previous builds
$existingZip = $buildDir . '/' . $zipName;
if (file_exists($existingZip)) {
  unlink($existingZip);
  echo "🗑️ Removed existing build\n";
}

// Create ZIP archive
$zip = new ZipArchive();
$result = $zip->open($existingZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($result !== TRUE) {
  echo "❌ Failed to create ZIP archive: $result\n";
  exit(1);
}

echo "📦 Creating ZIP archive...\n";

// Function to add directory to zip
function addDirectoryToZip($zip, $sourceDir, $zipPath = '')
{
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
  );

  foreach ($iterator as $file) {
    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, strlen($sourceDir) + 1);

    if ($zipPath) {
      $relativePath = $zipPath . '/' . $relativePath;
    }

    // Convert Windows paths to Unix paths for ZIP
    $relativePath = str_replace('\\', '/', $relativePath);

    if ($file->isDir()) {
      $zip->addEmptyDir($relativePath);
    } else {
      $zip->addFile($filePath, $relativePath);
    }
  }
}

// Add all plugin files to ZIP
addDirectoryToZip($zip, $sourceDir);

// Close ZIP
$zip->close();

// Verify ZIP was created
if (!file_exists($existingZip)) {
  echo "❌ Failed to create ZIP file\n";
  exit(1);
}

$fileSize = filesize($existingZip);
$fileSizeKB = round($fileSize / 1024, 1);

echo "✅ ZIP archive created successfully!\n";
echo "📊 File size: {$fileSizeKB} KB\n";
echo "📂 Location: $existingZip\n\n";

// List contents
echo "📋 Package contents:\n";
$zip = new ZipArchive();
if ($zip->open($existingZip) === TRUE) {
  for ($i = 0; $i < $zip->numFiles; $i++) {
    $fileInfo = $zip->statIndex($i);
    echo "   " . $fileInfo['name'] . "\n";
  }
  $zip->close();
}

echo "\n🎯 Installation Instructions:\n";
echo "=============================\n";
echo "1. 📥 Download: $zipName\n";
echo "2. 🌐 Go to Joomla Administrator\n";
echo "3. 🔧 Navigate to Extensions > Manage > Install\n";
echo "4. 📤 Upload the ZIP file\n";
echo "5. ✅ Enable the plugin in System Plugins\n";
echo "6. ⚙️ Configure settings as needed\n";

echo "\n🧪 Testing Checklist:\n";
echo "=====================\n";
echo "□ Install plugin on staging site\n";
echo "□ Enable plugin and configure settings\n";
echo "□ Test robots.txt endpoint\n";
echo "□ Test sitemap.xml endpoint\n";
echo "□ Test health check endpoint\n";
echo "□ Verify domain detection works\n";
echo "□ Check SEO meta tags\n";
echo "□ Test on different environments\n";

echo "\n✨ Build completed successfully!\n";
