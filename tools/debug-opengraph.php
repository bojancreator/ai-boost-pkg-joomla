<?php

/**
 * Debug OpenGraph generation by checking what's happening in the plugin
 */

$baseUrl = 'https://staging.offroadserbia.com';

echo "Debugging OpenGraph meta tag generation...\n";
echo str_repeat("=", 50) . "\n";

$context = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'timeout' => 30
  ]
]);

function debugPageMeta($url)
{
  global $context;

  echo "\n🔍 Debug analysis for: $url\n";

  $html = file_get_contents($url, false, $context);
  if ($html === false) {
    echo "❌ Failed to fetch page\n";
    return;
  }

  // Check for any meta tags
  preg_match_all('/<meta[^>]*>/i', $html, $allMeta);
  echo "📋 Total meta tags: " . count($allMeta[0]) . "\n";

  // Show first few meta tags to see what's there
  echo "🔍 Sample meta tags:\n";
  foreach (array_slice($allMeta[0], 0, 10) as $meta) {
    echo "   " . trim($meta) . "\n";
  }

  // Check specifically for OpenGraph
  preg_match_all('/<meta\s+property="og:([^"]+)"\s+content="([^"]*)"[^>]*>/i', $html, $ogMatches);
  echo "\n📊 OpenGraph tags: " . count($ogMatches[0]) . "\n";

  if (!empty($ogMatches[0])) {
    foreach ($ogMatches[0] as $og) {
      echo "   " . trim($og) . "\n";
    }
  }

  // Check for standard Joomla meta
  preg_match_all('/<meta\s+name="([^"]+)"\s+content="([^"]*)"[^>]*>/i', $html, $standardMeta);
  echo "\n📊 Standard meta tags: " . count($standardMeta[0]) . "\n";

  // Look for generator tag to confirm Joomla
  if (str_contains($html, 'generator') && str_contains($html, 'Joomla')) {
    echo "✅ Joomla detected in meta generator\n";
  }

  // Check if our plugin is running (look for any JoomlaBoost signatures)
  if (str_contains($html, 'JoomlaBoost') || str_contains($html, 'joomlaboost')) {
    echo "✅ JoomlaBoost plugin signatures found\n";
  } else {
    echo "⚠️  No JoomlaBoost signatures detected\n";
  }

  // Look for robots.txt and sitemap.xml references to verify plugin is working
  if (str_contains($html, 'robots.txt') || str_contains($html, 'sitemap.xml')) {
    echo "✅ Plugin-generated content references found\n";
  }
}

// Test homepage
debugPageMeta($baseUrl . '/');

echo "\n" . str_repeat("=", 60) . "\n";
echo "🔧 NEXT DEBUGGING STEPS\n";
echo str_repeat("=", 60) . "\n";

echo "1. 🔍 Check plugin configuration in Joomla admin\n";
echo "   - Extensions > Plugins > System - JoomlaBoost\n";
echo "   - Verify OpenGraph is enabled\n";

echo "\n2. 🔍 Check Joomla error logs\n";
echo "   - administrator/logs/\n";
echo "   - Look for JoomlaBoost related errors\n";

echo "\n3. 🔍 Test robots.txt and sitemap.xml\n";
echo "   - https://staging.offroadserbia.com/robots.txt\n";
echo "   - https://staging.offroadserbia.com/sitemap.xml\n";

echo "\n4. 🔧 Possible issues:\n";
echo "   - Plugin not enabled\n";
echo "   - OpenGraph setting disabled\n";
echo "   - Plugin error preventing execution\n";
echo "   - Document type incompatibility\n";
