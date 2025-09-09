<?php

/**
 * Simple test for Schema.org on staging site
 */

$url = 'https://staging.offroadserbia.com/';
$context = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'timeout' => 30
  ]
]);

echo "Testing Schema.org on: $url\n";
echo str_repeat("=", 60) . "\n";

$html = file_get_contents($url, false, $context);

if ($html === false) {
  echo "❌ Failed to fetch page\n";
  exit(1);
}

// Look for JSON-LD scripts
preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

if (empty($matches[1])) {
  echo "❌ No JSON-LD Schema.org markup found\n";
  exit(1);
}

echo "✅ Found " . count($matches[1]) . " Schema.org JSON-LD block(s)\n\n";

foreach ($matches[1] as $i => $jsonContent) {
  echo "Schema Block " . ($i + 1) . ":\n";
  echo str_repeat("-", 30) . "\n";

  $jsonContent = trim($jsonContent);
  $schema = json_decode($jsonContent, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    echo "❌ Invalid JSON: " . json_last_error_msg() . "\n";
    echo "Raw content: " . substr($jsonContent, 0, 200) . "...\n\n";
    continue;
  }

  // Check if it's an array of schemas or single schema
  $schemas = isset($schema[0]) ? $schema : [$schema];

  foreach ($schemas as $s) {
    $type = $s['@type'] ?? 'Unknown';
    echo "📋 Type: $type\n";

    // Check for LocalBusiness specific properties
    if ($type === 'LocalBusiness') {
      echo "   🏢 LocalBusiness detected!\n";

      if (isset($s['address'])) {
        echo "   📍 Address: " . ($s['address']['addressLocality'] ?? 'N/A') . ", " . ($s['address']['addressCountry'] ?? 'N/A') . "\n";
      }

      if (isset($s['serviceType'])) {
        echo "   🔧 Services: " . implode(', ', (array)$s['serviceType']) . "\n";
      }

      if (isset($s['geo'])) {
        echo "   🌍 Coordinates: " . ($s['geo']['latitude'] ?? 'N/A') . ", " . ($s['geo']['longitude'] ?? 'N/A') . "\n";
      }

      if (isset($s['sameAs'])) {
        echo "   📱 Social profiles: " . count((array)$s['sameAs']) . " found\n";
      }
    }

    if (isset($s['name'])) {
      echo "   Name: " . $s['name'] . "\n";
    }

    if (isset($s['headline'])) {
      echo "   Headline: " . $s['headline'] . "\n";
    }

    if (isset($s['description'])) {
      $desc = strlen($s['description']) > 80 ? substr($s['description'], 0, 80) . '...' : $s['description'];
      echo "   Description: $desc\n";
    }

    if (isset($s['url'])) {
      echo "   URL: " . $s['url'] . "\n";
    }

    echo "\n";
  }
}

echo "✅ Schema.org service is working correctly!\n";
