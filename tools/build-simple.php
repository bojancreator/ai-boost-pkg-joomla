<?php

/**
 * Simple PHP build script for JoomlaBoost plugin
 */

$pluginDir = __DIR__ . '/../plugin';
$buildDir = __DIR__ . '/../build';
$version = '0.1.18';
$pluginName = 'joomlaboost';

// Ensure build directory exists
if (!is_dir($buildDir)) {
  mkdir($buildDir, 0755, true);
}

$zipFileName = "{$pluginName}-{$version}.zip";
$zipPath = $buildDir . '/' . $zipFileName;

// Remove existing ZIP if it exists
if (file_exists($zipPath)) {
  unlink($zipPath);
}

echo "Building JoomlaBoost Plugin v{$version}\n";
echo str_repeat("=", 50) . "\n";

// Check if plugin directory exists
if (!is_dir($pluginDir)) {
  echo "Error: Plugin directory not found: {$pluginDir}\n";
  exit(1);
}

// Create ZIP archive
$zip = new ZipArchive();
if (!class_exists('ZipArchive')) {
  echo "Error: ZipArchive class not available. Please enable zip extension.\n";
  exit(1);
}

$result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

if ($result !== TRUE) {
  echo "Error: Cannot create ZIP file: {$zipPath}\n";
  exit(1);
}

// Function to add files recursively
function addFilesToZip($zip, $path, $relativePath = '')
{
  $files = scandir($path);

  foreach ($files as $file) {
    if ($file === '.' || $file === '..') {
      continue;
    }

    $fullPath = $path . '/' . $file;
    $zipPath = $relativePath ? $relativePath . '/' . $file : $file;

    if (is_dir($fullPath)) {
      $zip->addEmptyDir($zipPath);
      addFilesToZip($zip, $fullPath, $zipPath);
    } else {
      $zip->addFile($fullPath, $zipPath);
      echo "Added: {$zipPath}\n";
    }
  }
}

// Add all plugin files
addFilesToZip($zip, $pluginDir);

// Close ZIP
$zip->close();

// Verify ZIP was created
if (file_exists($zipPath)) {
  $fileSize = round(filesize($zipPath) / 1024, 2);
  echo "\n" . str_repeat("=", 50) . "\n";
  echo "SUCCESS: Plugin built successfully!\n";
  echo "File: {$zipFileName}\n";
  echo "Size: {$fileSize} KB\n";
  echo "Location: {$zipPath}\n";
  echo "\nInstallation Instructions:\n";
  echo "1. Login to Joomla Admin\n";
  echo "2. Go to Extensions > Manage > Install\n";
  echo "3. Upload {$zipFileName}\n";
  echo "4. Enable the plugin in Extensions > Plugins > System\n";
} else {
  echo "Error: Failed to create ZIP file\n";
  exit(1);
}
