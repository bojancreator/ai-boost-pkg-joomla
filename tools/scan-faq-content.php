<?php

/**
 * FAQ Content Detection Tool
 * Scans staging site for FAQ-style content
 */

$testUrls = [
  'https://staging.offroadserbia.com/',
  'https://staging.offroadserbia.com/index.php?option=com_content&view=category&id=2',
  'https://staging.offroadserbia.com/index.php?option=com_content&view=category&id=3',
  'https://staging.offroadserbia.com/index.php?option=com_content&view=category&id=4',
  'https://staging.offroadserbia.com/index.php?option=com_content&view=category&id=5',
  'https://staging.offroadserbia.com/index.php/komponente/kontakt'
];

echo "=== FAQ Content Detection ===\n\n";

foreach ($testUrls as $url) {
  echo "Testing: $url\n";
  echo "----------------------------------------\n";

  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 10,
      'user_agent' => 'JoomlaBoost FAQ Scanner 1.0'
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

  // Look for FAQ patterns
  $faqPatterns = [
    '/\b(FAQ|Frequently Asked Questions|Česta pitanja|Pitanja i odgovori)\b/i',
    '/\b(Pitanje|Question|Q:)/i',
    '/\b(Odgovor|Answer|A:)/i',
    '/\b(Pomoć|Help|Support|Podrška)/i',
    '/\b(Kako|How to|How do)/i'
  ];

  $faqFound = false;
  foreach ($faqPatterns as $pattern) {
    if (preg_match_all($pattern, $content, $matches)) {
      if (!$faqFound) {
        echo "🎯 FAQ Patterns Found:\n";
        $faqFound = true;
      }
      echo "   - Pattern matches: " . count($matches[0]) . " times\n";
    }
  }

  if (!$faqFound) {
    echo "📭 No FAQ patterns detected\n";
  }

  // Look for structured Q&A content
  $qaPatterns = [
    '/<h[1-6][^>]*>.*?(pitanje|question|Q:).*?<\/h[1-6]>/i',
    '/<strong[^>]*>.*?(pitanje|question|Q:).*?<\/strong>/i',
    '/<dt[^>]*>.*?<\/dt>.*?<dd[^>]*>.*?<\/dd>/i'
  ];

  $qaFound = false;
  foreach ($qaPatterns as $pattern) {
    if (preg_match_all($pattern, $content, $matches)) {
      if (!$qaFound) {
        echo "💡 Q&A Structure Found:\n";
        $qaFound = true;
      }
      echo "   - " . count($matches[0]) . " Q&A pairs detected\n";
    }
  }

  if (!$qaFound && $faqFound) {
    echo "ℹ️  FAQ keywords found but no clear Q&A structure\n";
  }

  echo "\n";
}

echo "=== Next Steps ===\n";
echo "1. Implement FAQ schema support in SchemaService\n";
echo "2. Create FAQ detection logic\n";
echo "3. Test on pages with Q&A content\n";
echo "4. Enhance with manual FAQ configuration\n";
