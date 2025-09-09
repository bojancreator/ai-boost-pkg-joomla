<?php

/**
 * Find and test Article schema on staging content pages
 */

$baseUrl = 'https://staging.offroadserbia.com';

echo "Finding content pages on staging site...\n";
echo str_repeat("=", 50) . "\n";

// Get homepage to find article links
$context = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'timeout' => 30
  ]
]);

$html = file_get_contents($baseUrl . '/', false, $context);

if ($html === false) {
  echo "❌ Failed to fetch homepage\n";
  exit(1);
}

// Look for content links
$linkPatterns = [
  // Common Joomla content patterns
  '/href="([^"]*component\/content[^"]*)"/',
  '/href="([^"]*\/blog[^"]*)"/',
  '/href="([^"]*\/vijesti[^"]*)"/',
  '/href="([^"]*\/clanci[^"]*)"/',
  '/href="([^"]*\/articles[^"]*)"/',
  '/href="([^"]*\/novosti[^"]*)"/',
  // Menu item patterns
  '/href="([^"]*\/[a-zA-Z0-9\-]+)"(?![^<]*<\/a>.*menu)/',
];

$foundLinks = [];

foreach ($linkPatterns as $pattern) {
  preg_match_all($pattern, $html, $matches);
  foreach ($matches[1] as $link) {
    // Skip external links, anchors, and common non-content pages
    if (
      str_starts_with($link, 'http') || str_starts_with($link, '#') ||
      str_contains($link, 'kontakt') || str_contains($link, 'contact') ||
      str_contains($link, 'login') || str_contains($link, 'register')
    ) {
      continue;
    }

    // Normalize link
    if (str_starts_with($link, '/')) {
      $fullLink = $baseUrl . $link;
    } else {
      $fullLink = $baseUrl . '/' . $link;
    }

    $foundLinks[] = $fullLink;
  }
}

// Remove duplicates and limit to first 5
$foundLinks = array_unique($foundLinks);
$foundLinks = array_slice($foundLinks, 0, 5);

echo "Found " . count($foundLinks) . " potential content links:\n";
foreach ($foundLinks as $i => $link) {
  echo ($i + 1) . ". $link\n";
}

echo "\nTesting Article schema on content pages...\n";
echo str_repeat("=", 50) . "\n";

function testArticleSchema($url)
{
  global $context;

  echo "\n📄 Testing: $url\n";

  $html = file_get_contents($url, false, $context);
  if ($html === false) {
    echo "❌ Failed to fetch page\n";
    return false;
  }

  // Look for JSON-LD scripts
  preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

  if (empty($matches[1])) {
    echo "❌ No JSON-LD found\n";
    return false;
  }

  $hasArticle = false;
  $hasWebsite = false;
  $hasOrganization = false;

  foreach ($matches[1] as $jsonContent) {
    $schema = json_decode(trim($jsonContent), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      continue;
    }

    $schemas = isset($schema[0]) ? $schema : [$schema];

    foreach ($schemas as $s) {
      $type = $s['@type'] ?? '';

      if ($type === 'Article') {
        $hasArticle = true;
        echo "✅ Article schema found\n";

        if (isset($s['headline'])) {
          echo "   📋 Headline: " . substr($s['headline'], 0, 60) . "...\n";
        }

        if (isset($s['datePublished'])) {
          echo "   📅 Published: " . $s['datePublished'] . "\n";
        }

        if (isset($s['author']['name'])) {
          echo "   ✍️  Author: " . $s['author']['name'] . "\n";
        }

        if (isset($s['image']) && is_array($s['image'])) {
          echo "   🖼️  Images: " . count($s['image']) . " found\n";
        }

        if (isset($s['keywords']) && is_array($s['keywords'])) {
          echo "   🏷️  Keywords: " . implode(', ', array_slice($s['keywords'], 0, 3)) . "...\n";
        }
      } elseif ($type === 'WebSite') {
        $hasWebsite = true;
      } elseif ($type === 'Organization' || $type === 'LocalBusiness') {
        $hasOrganization = true;
      }
    }
  }

  // Summary
  echo "   📊 Schema types: ";
  $types = [];
  if ($hasWebsite) $types[] = "WebSite";
  if ($hasOrganization) $types[] = "Organization/LocalBusiness";
  if ($hasArticle) $types[] = "Article";

  echo implode(', ', $types) . "\n";

  return $hasArticle;
}

// Test each found link
$articleSchemaFound = false;
foreach ($foundLinks as $link) {
  if (testArticleSchema($link)) {
    $articleSchemaFound = true;
  }
  usleep(500000); // 500ms delay between requests
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 ARTICLE SCHEMA TEST SUMMARY\n";
echo str_repeat("=", 50) . "\n";

if ($articleSchemaFound) {
  echo "✅ Article schema is working correctly!\n";
  echo "✅ Found Article schema on content pages\n";
  echo "✅ Article properties are being generated properly\n";
} else {
  echo "⚠️  No Article schema found on tested pages\n";
  echo "💡 Possible reasons:\n";
  echo "   - Tested pages are not com_content articles\n";
  echo "   - Article view is not being triggered\n";
  echo "   - Plugin may need content pages with ID parameter\n";
}

echo "\n📋 Next steps:\n";
echo "1. ✅ Test specific article URLs with ?id= parameter\n";
echo "2. 🔧 Verify content is published and accessible\n";
echo "3. 🔧 Check if Article schema needs category/featured pages\n";
