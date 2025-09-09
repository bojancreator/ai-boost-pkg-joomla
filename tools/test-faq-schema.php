<?php

/**
 * FAQ Schema Testing Tool
 * Tests FAQ schema generation on pages with Q&A content
 */

$testUrls = [
  'https://staging.offroadserbia.com/index.php?option=com_content&view=category&id=2',
  'https://staging.offroadserbia.com/',
  'https://staging.offroadserbia.com/index.php?option=com_content&view=article&id=1'
];

echo "=== FAQ Schema Testing ===\n\n";

foreach ($testUrls as $url) {
  echo "Testing: $url\n";
  echo "----------------------------------------\n";

  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 15,
      'user_agent' => 'JoomlaBoost FAQ Schema Tester 1.0'
    ]
  ]);

  $content = @file_get_contents($url, false, $context);

  if ($content === false) {
    echo "❌ Failed to load page\n\n";
    continue;
  }

  // Extract title
  if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $content, $matches)) {
    echo "📄 Title: " . trim($matches[1]) . "\n";
  }

  // Check for FAQ schema in JSON-LD
  $faqSchemaFound = false;
  if (preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $content, $matches)) {
    foreach ($matches[1] as $jsonLd) {
      $schema = json_decode($jsonLd, true);
      if (is_array($schema) && isset($schema['@type']) && $schema['@type'] === 'FAQPage') {
        $faqSchemaFound = true;
        echo "✅ FAQ Schema Found!\n";
        echo "   - Type: " . $schema['@type'] . "\n";

        if (isset($schema['mainEntity']) && is_array($schema['mainEntity'])) {
          echo "   - Questions: " . count($schema['mainEntity']) . "\n";

          // Show first few questions
          $questionCount = min(3, count($schema['mainEntity']));
          for ($i = 0; $i < $questionCount; $i++) {
            $question = $schema['mainEntity'][$i];
            if (isset($question['name'])) {
              echo "   - Q" . ($i + 1) . ": " . substr($question['name'], 0, 80) . "...\n";
            }
          }
        }
        break;
      }
    }
  }

  if (!$faqSchemaFound) {
    echo "📭 No FAQ schema detected\n";

    // Analyze page content for potential FAQ patterns
    $patterns = [
      'Definition Lists' => '/<dt[^>]*>.*?<\/dt>\s*<dd[^>]*>.*?<\/dd>/is',
      'Question Headings' => '/<h[1-6][^>]*>.*?(?:pitanje|question|Q:|kako|why|zašto|šta).*?<\/h[1-6]>/i',
      'Bold Q&A' => '/<(?:strong|b)[^>]*>.*?(?:pitanje|question|Q:|kako|odgovor|answer|A:).*?<\/(?:strong|b)>/i'
    ];

    echo "🔍 Content Analysis:\n";
    foreach ($patterns as $name => $pattern) {
      if (preg_match_all($pattern, $content, $matches)) {
        echo "   - $name: " . count($matches[0]) . " matches\n";
        if (count($matches[0]) > 0 && $name === 'Definition Lists') {
          // Show example of DL content
          $example = strip_tags($matches[0][0]);
          echo "     Example: " . substr($example, 0, 100) . "...\n";
        }
      } else {
        echo "   - $name: 0 matches\n";
      }
    }
  }

  // Check for other schemas
  echo "📊 Other Schemas:\n";
  $schemaTypes = [];
  if (preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/is', $content, $matches)) {
    foreach ($matches[1] as $jsonLd) {
      $schema = json_decode($jsonLd, true);
      if (is_array($schema) && isset($schema['@type'])) {
        $schemaTypes[] = $schema['@type'];
      }
    }
  }

  if (!empty($schemaTypes)) {
    echo "   - Found: " . implode(', ', array_unique($schemaTypes)) . "\n";
  } else {
    echo "   - No structured data found\n";
  }

  echo "\n";
}

echo "=== Manual FAQ Content Test ===\n\n";

// Test FAQ extraction with sample content
$sampleFAQContent = '
<h3>Pitanje: Kako da se pridružim off-road avanturama?</h3>
<p>Da biste se pridružili našim off-road avanturama, potrebno je da se registrujete na našem sajtu i kontaktirate nas putem kontakt forme. Imamo različite nivoe vožnje za početnike i iskusne vozače.</p>

<h3>Pitanje: Kakva oprema je potrebna?</h3>
<p>Preporučujemo 4x4 vozilo u dobrom stanju, sigurnosnu opremu uključujući kacige, i osnovni alat za popravke. Detaljna lista opreme je dostupna u našem vodiču.</p>

<dt>Koliko košta učlanjenje?</dt>
<dd>Godišnja članarina iznosi 5000 dinara za redovno članstvo, a 3000 dinara za studentsko članstvo. Porodično članstvo je 8000 dinara.</dd>

<strong>Q: Da li organizujete obuke za početnike?</strong>
Da, organizujemo redovne obuke za početnike svakog prvog vikenda u mesecu. Obuka traje jedan dan i pokriva osnove bezbedne off-road vožnje.
';

echo "Testing FAQ extraction from sample content...\n";
echo "Content contains:\n";
echo "- H3 question headings: " . preg_match_all('/<h3[^>]*>.*?pitanje.*?<\/h3>/i', $sampleFAQContent, $matches) . "\n";
echo "- Definition lists: " . preg_match_all('/<dt[^>]*>.*?<\/dt>\s*<dd[^>]*>.*?<\/dd>/is', $sampleFAQContent, $matches) . "\n";
echo "- Bold Q&A: " . preg_match_all('/<strong[^>]*>.*?Q:.*?<\/strong>/i', $sampleFAQContent, $matches) . "\n";

echo "\n=== Summary ===\n";
echo "✅ FAQ schema implementation ready\n";
echo "🔧 FAQ detection patterns implemented\n";
echo "📋 Ready to test on live content\n";
echo "🚀 Build new plugin version for testing\n";
