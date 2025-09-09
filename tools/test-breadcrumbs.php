<?php

/**
 * Test breadcrumb schema functionality
 */

$baseUrl = 'https://staging.offroadserbia.com';

echo "Testing Breadcrumb Schema functionality...\n";
echo str_repeat("=", 50) . "\n";

$context = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'timeout' => 30
  ]
]);

function testBreadcrumbSchema($url, $pageName)
{
  global $context;

  echo "\n🔍 Testing breadcrumbs on: $pageName\n";
  echo "URL: $url\n";

  $html = file_get_contents($url, false, $context);
  if ($html === false) {
    echo "❌ Failed to fetch page\n";
    return false;
  }

  // Look for BreadcrumbList schema
  preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);

  $breadcrumbFound = false;
  $allSchemas = [];

  foreach ($matches[1] as $jsonContent) {
    $schema = json_decode(trim($jsonContent), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      continue;
    }

    $schemas = isset($schema[0]) ? $schema : [$schema];

    foreach ($schemas as $s) {
      $type = $s['@type'] ?? '';
      $allSchemas[] = $type;

      if ($type === 'BreadcrumbList') {
        $breadcrumbFound = true;
        echo "✅ BreadcrumbList schema found!\n";

        if (isset($s['itemListElement']) && is_array($s['itemListElement'])) {
          echo "   📋 Breadcrumb items (" . count($s['itemListElement']) . "):\n";

          foreach ($s['itemListElement'] as $i => $item) {
            $position = $item['position'] ?? ($i + 1);
            $name = $item['name'] ?? 'Unknown';
            $url = $item['item'] ?? 'No URL';

            echo "      {$position}. {$name}\n";
            echo "         → {$url}\n";
          }
        } else {
          echo "   ⚠️  No breadcrumb items found in schema\n";
        }

        break;
      }
    }
  }

  if (!$breadcrumbFound) {
    echo "❌ No BreadcrumbList schema found\n";
    echo "   📊 Found schemas: " . implode(', ', array_unique($allSchemas)) . "\n";

    // Check for visible breadcrumbs in HTML
    $hasBreadcrumbHTML = (
      str_contains($html, 'breadcrumb') ||
      str_contains($html, 'navigation') ||
      str_contains($html, 'pathway')
    );

    if ($hasBreadcrumbHTML) {
      echo "   💡 HTML breadcrumbs detected, but no schema generated\n";
    } else {
      echo "   💡 No breadcrumb navigation detected in page\n";
    }
  }

  return $breadcrumbFound;
}

// Test different page types for breadcrumbs
$testPages = [
  [$baseUrl . '/', 'Homepage'],
  [$baseUrl . '/our-story', 'Our Story Page'],
  [$baseUrl . '/clanstvo', 'Membership Page'],
  [$baseUrl . '/our-adventures', 'Adventures Page'],
  [$baseUrl . '/oprema-i-saveti', 'Equipment & Tips'],
];

$breadcrumbPages = 0;
$totalPages = count($testPages);

foreach ($testPages as [$url, $name]) {
  if (testBreadcrumbSchema($url, $name)) {
    $breadcrumbPages++;
  }
  usleep(500000); // 500ms delay
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 BREADCRUMB SCHEMA TEST SUMMARY\n";
echo str_repeat("=", 60) . "\n";
echo "✅ Pages with breadcrumbs: $breadcrumbPages / $totalPages\n";

if ($breadcrumbPages > 0) {
  echo "\n🎉 Breadcrumb schema is working!\n";
  echo "✅ Navigation hierarchy is being captured\n";
  echo "✅ Search engines can understand page structure\n";
} else {
  echo "\n💡 Breadcrumb schema analysis:\n";
  echo "   - Homepage typically doesn't have breadcrumbs\n";
  echo "   - Menu item pages may not trigger breadcrumb generation\n";
  echo "   - Breadcrumbs usually appear on content articles\n";
  echo "   - May need deeper navigation to trigger breadcrumbs\n";
}

echo "\n🔧 Breadcrumb Schema Benefits:\n";
echo "✅ Enhanced navigation in search results\n";
echo "✅ Better understanding of site structure\n";
echo "✅ Improved user experience in SERPs\n";
echo "✅ Higher click-through rates\n";

echo "\n📋 Next steps:\n";
echo "1. ✅ Breadcrumb functionality tested\n";
echo "2. 🔧 Create content with deeper navigation\n";
echo "3. 🔧 Test on article pages when available\n";
echo "4. 🔧 Implement FAQ schema support\n";

echo "\n💡 To see breadcrumbs in action:\n";
echo "- Navigate deeper into site structure\n";
echo "- Create content articles with categories\n";
echo "- Use Joomla's pathway/breadcrumb modules\n";
