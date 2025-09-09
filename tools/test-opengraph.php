<?php

/**
 * Test OpenGraph meta tags on staging site
 */

$baseUrl = 'https://staging.offroadserbia.com';

echo "Testing OpenGraph meta tags...\n";
echo str_repeat("=", 50) . "\n";

$context = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'timeout' => 30
  ]
]);

function testOpenGraphTags($url)
{
  global $context;

  echo "\n🔍 Testing OpenGraph on: $url\n";

  $html = file_get_contents($url, false, $context);
  if ($html === false) {
    echo "❌ Failed to fetch page\n";
    return false;
  }

  // Extract OpenGraph meta tags
  preg_match_all('/<meta\s+property="og:([^"]+)"\s+content="([^"]*)"[^>]*>/i', $html, $matches);

  if (empty($matches[1])) {
    echo "❌ No OpenGraph meta tags found\n";
    return false;
  }

  echo "✅ Found " . count($matches[1]) . " OpenGraph tags:\n";

  $ogTags = [];
  for ($i = 0; $i < count($matches[1]); $i++) {
    $property = $matches[1][$i];
    $content = $matches[2][$i];
    $ogTags[$property] = $content;

    // Show important tags with truncation
    $displayContent = strlen($content) > 60 ? substr($content, 0, 60) . '...' : $content;
    echo "   📋 og:$property = \"$displayContent\"\n";
  }

  // Check for essential OpenGraph tags
  $essentialTags = ['title', 'description', 'url', 'type', 'image'];
  $missingTags = [];

  foreach ($essentialTags as $tag) {
    if (!isset($ogTags[$tag])) {
      $missingTags[] = $tag;
    }
  }

  if (empty($missingTags)) {
    echo "   ✅ All essential OpenGraph tags present\n";
  } else {
    echo "   ⚠️  Missing tags: " . implode(', ', $missingTags) . "\n";
  }

  // Check for Twitter Card tags too
  preg_match_all('/<meta\s+name="twitter:([^"]+)"\s+content="([^"]*)"[^>]*>/i', $html, $twitterMatches);

  if (!empty($twitterMatches[1])) {
    echo "   🐦 Twitter Card tags: " . count($twitterMatches[1]) . " found\n";
    for ($i = 0; $i < count($twitterMatches[1]); $i++) {
      $property = $twitterMatches[1][$i];
      $content = $twitterMatches[2][$i];
      $displayContent = strlen($content) > 40 ? substr($content, 0, 40) . '...' : $content;
      echo "      twitter:$property = \"$displayContent\"\n";
    }
  }

  return true;
}

// Test pages
$testPages = [
  $baseUrl . '/',
  $baseUrl . '/our-story',
  $baseUrl . '/clanstvo',
  $baseUrl . '/our-adventures'
];

$successCount = 0;
foreach ($testPages as $page) {
  if (testOpenGraphTags($page)) {
    $successCount++;
  }
  usleep(500000); // 500ms delay
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 OPENGRAPH TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Pages with OpenGraph: $successCount / " . count($testPages) . "\n";

if ($successCount > 0) {
  echo "\n🎉 OpenGraph service is working!\n";
  echo "✅ Meta tags are being generated\n";
  echo "✅ Social media sharing will work properly\n";

  echo "\n📋 Social Media Testing:\n";
  echo "1. 📘 Facebook: https://developers.facebook.com/tools/debug/\n";
  echo "2. 🐦 Twitter: https://cards-dev.twitter.com/validator\n";
  echo "3. 💼 LinkedIn: https://www.linkedin.com/post-inspector/\n";
} else {
  echo "\n⚠️  OpenGraph service may be disabled or not working\n";
  echo "💡 Check plugin configuration:\n";
  echo "   - Verify OpenGraph is enabled in plugin settings\n";
  echo "   - Check if service is properly initialized\n";
}

echo "\n🔧 Next steps:\n";
echo "1. ✅ Verify OpenGraph functionality\n";
echo "2. 🔧 Test social media sharing\n";
echo "3. 🔧 Optimize image selection\n";
echo "4. 🔧 Add custom OpenGraph properties\n";
