<?php

/**
 * Nuclear option: Force delete JoomlaBoost plugin folder
 * Run this BEFORE installing new version
 *
 * Usage: Copy to Joomla root, run via browser: yourdomain.com/force-delete-plugin.php
 */

define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Security check
$secret = $_GET['secret'] ?? '';
if ($secret !== 'delete-joomlaboost-now') {
  die('Access denied. Use: ?secret=delete-joomlaboost-now');
}

$pluginPath = JPATH_PLUGINS . '/system/joomlaboost';

echo "<h2>JoomlaBoost Plugin Force Delete</h2>";
echo "<p>Plugin path: <code>$pluginPath</code></p>";

if (!is_dir($pluginPath)) {
  echo "<p style='color:orange'>⚠️  Plugin folder does not exist.</p>";
  exit;
}

// Recursive delete function
function deleteDirectory($dir)
{
  if (!file_exists($dir)) {
    return true;
  }

  if (!is_dir($dir)) {
    return unlink($dir);
  }

  foreach (scandir($dir) as $item) {
    if ($item == '.' || $item == '..') {
      continue;
    }

    if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
      return false;
    }
  }

  return rmdir($dir);
}

echo "<h3>🗑️  Deleting plugin folder...</h3>";
echo "<ul>";

// List files before delete
$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($pluginPath, RecursiveDirectoryIterator::SKIP_DOTS),
  RecursiveIteratorIterator::CHILD_FIRST
);

$files = [];
foreach ($iterator as $file) {
  $files[] = $file->getPathname();
}

echo "<li>Found " . count($files) . " files/folders</li>";

// Delete
if (deleteDirectory($pluginPath)) {
  echo "<li style='color:green'>✅ Successfully deleted!</li>";

  // Clear OPcache
  if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "<li style='color:green'>✅ OPcache cleared</li>";
  }

  // Clear Joomla cache
  try {
    $cache = Factory::getCache();
    $cache->clean();
    echo "<li style='color:green'>✅ Joomla cache cleared</li>";
  } catch (Exception $e) {
    echo "<li style='color:orange'>⚠️  Cache clear failed: {$e->getMessage()}</li>";
  }

  echo "</ul>";
  echo "<h3 style='color:green'>✅ SUCCESS!</h3>";
  echo "<p><strong>Next steps:</strong></p>";
  echo "<ol>";
  echo "<li>Go to Extensions → Manage → Install</li>";
  echo "<li>Upload: joomlaboost-0.1.32.zip</li>";
  echo "<li>Enable plugin</li>";
  echo "<li>Configure settings (will be restored from backup)</li>";
  echo "<li>Delete this file from server!</li>";
  echo "</ol>";
} else {
  echo "<li style='color:red'>❌ Delete failed!</li>";
  echo "</ul>";
  echo "<p>Try manual FTP delete or contact server admin.</p>";
}

echo "<hr>";
echo "<p style='color:red'><strong>IMPORTANT: Delete this file after use!</strong></p>";
