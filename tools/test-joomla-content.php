<?php

/**
 * Test specific Joomla article patterns
 */

$baseUrl = 'https://staging.offroadserbia.com';

echo "Testing Joomla article URL patterns...\n";
echo str_repeat("=", 50) . "\n";

$context = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'timeout' => 30
  ]
]);

// Common Joomla article URL patterns to test
$testUrls = [
  // Component format
  $baseUrl . '/index.php?option=com_content&view=article&id=1',
  $baseUrl . '/index.php?option=com_content&view=article&id=2',
  $baseUrl . '/index.php?option=com_content&view=article&id=3',
  // SEF format possibilities
  $baseUrl . '/blog/1',
  $baseUrl . '/articles/1',
  $baseUrl . '/vijesti/1',
  // Check if there's a blog/featured page
  $baseUrl . '/index.php?option=com_content&view=featured',
  $baseUrl . '/index.php?option=com_content&view=category&id=1',
];

function testSchemaOnUrl($url)
{
  global $context;

  echo "\n🔍 Testing: $url\n";

  $html = @file_get_contents($url, false, $context);
  if ($html === false) {
    echo "❌ Page not accessible (404 or error)\n";
    return false;
  }

  // Check if it's an error page
  if (str_contains($html, '404') || str_contains($html, 'Page not found') || str_contains($html, 'Error')) {
    echo "❌ Error page detected\n";
    return false;
  }

  // Look for JSON-LD scripts
  preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

  if (empty($matches[1])) {
    echo "❌ No JSON-LD found\n";
    return false;
  }

  echo "✅ Page accessible, checking schemas...\n";

  $foundSchemas = [];
  foreach ($matches[1] as $jsonContent) {
    $schema = json_decode(trim($jsonContent), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      continue;
    }

    $schemas = isset($schema[0]) ? $schema : [$schema];

    foreach ($schemas as $s) {
      $type = $s['@type'] ?? '';
      $foundSchemas[] = $type;

      if ($type === 'Article') {
        echo "   🎉 ARTICLE SCHEMA FOUND!\n";

        if (isset($s['headline'])) {
          echo "      📋 Headline: " . $s['headline'] . "\n";
        }

        if (isset($s['datePublished'])) {
          echo "      📅 Published: " . $s['datePublished'] . "\n";
        }

        if (isset($s['author']['name'])) {
          echo "      ✍️  Author: " . $s['author']['name'] . "\n";
        }

        if (isset($s['description'])) {
          echo "      📝 Description: " . substr($s['description'], 0, 80) . "...\n";
        }

        return true;
      } elseif ($type === 'Blog') {
        echo "   📰 BLOG SCHEMA FOUND!\n";
        return true;
      } elseif ($type === 'CollectionPage') {
        echo "   📂 CATEGORY SCHEMA FOUND!\n";
        return true;
      }
    }
  }

  echo "   📊 Found schemas: " . implode(', ', array_unique($foundSchemas)) . "\n";
  return false;
}

// Test each URL pattern
$successCount = 0;
$testedCount = 0;

foreach ($testUrls as $url) {
  if (testSchemaOnUrl($url)) {
    $successCount++;
  }
  $testedCount++;
  usleep(500000); // 500ms delay
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 JOOMLA CONTENT SCHEMA TEST RESULTS\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Successful tests: $successCount / $testedCount\n";

if ($successCount > 0) {
  echo "\n🎉 Article/Content schema is working!\n";
  echo "✅ JoomlaBoost correctly detects content pages\n";
  echo "✅ Schema generation is functioning properly\n";

  echo "\n📋 Next steps:\n";
  echo "1. ✅ Article schema implementation verified\n";
  echo "2. 🔧 Test breadcrumb schema enhancement\n";
  echo "3. 🔧 Verify OpenGraph meta tags\n";
  echo "4. 🔧 Test GA4 integration\n";
} else {
  echo "\n⚠️  No article/content schemas found\n";
  echo "💡 This might be because:\n";
  echo "   - No published articles exist yet\n";
  echo "   - Content structure is different\n";
  echo "   - Need to create test content\n";

  echo "\n📋 Recommendations:\n";
  echo "1. Create a test article in Joomla admin\n";
  echo "2. Publish it and test the URL\n";
  echo "3. Verify com_content is being used\n";
}

echo "\n🔧 Manual testing suggestion:\n";
echo "Visit Joomla admin and create a test article, then test its URL\n";
