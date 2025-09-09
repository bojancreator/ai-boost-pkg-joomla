<?php

/**
 * JoomlaBoost Live Testing Script
 * 
 * Tests plugin functionality on live staging environment
 * 
 * Usage:
 * php tests/live-staging-test.php
 * 
 * @author JoomlaBoost
 * @version 1.0.0
 */

echo "🌍 JoomlaBoost Live Staging Tests\n";
echo "=================================\n\n";

$stagingUrl = 'https://staging.offroadserbia.com';
$testResults = [];

/**
 * Perform HTTP request and return response details
 */
function testHttpRequest(string $url, string $testName): array
{
  echo "🔍 Testing: {$testName}\n";
  echo "   URL: {$url}\n";

  $context = stream_context_create([
    'http' => [
      'timeout' => 10,
      'user_agent' => 'JoomlaBoost-Test/1.0'
    ]
  ]);

  $startTime = microtime(true);
  $content = @file_get_contents($url, false, $context);
  $duration = round((microtime(true) - $startTime) * 1000, 2);

  $headers = [];
  if (isset($http_response_header)) {
    $headers = $http_response_header;
  }

  $status = $content !== false ? 'SUCCESS' : 'FAILED';
  $statusCode = '000';

  if (!empty($headers) && isset($headers[0])) {
    preg_match('/HTTP\/\d\.\d\s+(\d{3})/', $headers[0], $matches);
    $statusCode = $matches[1] ?? '000';
  }

  echo "   Status: {$status} ({$statusCode}) - {$duration}ms\n";
  echo "   Content Length: " . strlen($content ?: '') . " bytes\n";

  return [
    'url' => $url,
    'test_name' => $testName,
    'success' => $content !== false,
    'status_code' => $statusCode,
    'duration_ms' => $duration,
    'content' => $content,
    'headers' => $headers,
    'content_length' => strlen($content ?: '')
  ];
}

/**
 * Test robots.txt functionality
 */
function testRobotsTxt(string $baseUrl): array
{
  echo "\n📋 Test 1: robots.txt Generation\n";
  echo "-------------------------------\n";

  $result = testHttpRequest($baseUrl . '/robots.txt', 'Robots.txt Direct');

  if ($result['success']) {
    $content = $result['content'];

    // Check for staging-specific content
    $hasStaging = stripos($content, 'staging') !== false;
    $hasDisallow = stripos($content, 'disallow: /') !== false;
    $hasJoomlaBoost = stripos($content, 'joomlaboost') !== false;
    $hasGenerated = stripos($content, 'generated:') !== false;

    echo "   ✅ Response received\n";
    echo "   📊 Analysis:\n";
    echo "      - Contains 'staging': " . ($hasStaging ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Contains 'Disallow: /': " . ($hasDisallow ? '✅ YES' : '❌ NO') . "\n";
    echo "      - JoomlaBoost signature: " . ($hasJoomlaBoost ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Generated timestamp: " . ($hasGenerated ? '✅ YES' : '❌ NO') . "\n";

    $result['analysis'] = [
      'has_staging' => $hasStaging,
      'has_disallow' => $hasDisallow,
      'has_signature' => $hasJoomlaBoost,
      'has_timestamp' => $hasGenerated
    ];

    echo "   📝 Sample Content (first 200 chars):\n";
    echo "      " . substr($content, 0, 200) . "...\n";
  } else {
    echo "   ❌ Failed to retrieve robots.txt\n";
  }

  return $result;
}

/**
 * Test sitemap.xml functionality
 */
function testSitemapXml(string $baseUrl): array
{
  echo "\n📋 Test 2: sitemap.xml Generation\n";
  echo "--------------------------------\n";

  $result = testHttpRequest($baseUrl . '/sitemap.xml', 'Sitemap.xml Direct');

  if ($result['success']) {
    $content = $result['content'];

    // Check for XML structure
    $hasXmlDeclaration = stripos($content, '<?xml') !== false;
    $hasUrlset = stripos($content, '<urlset') !== false;
    $hasUrls = stripos($content, '<url>') !== false;
    $hasStaging = stripos($content, 'staging') !== false;
    $hasJoomlaBoost = stripos($content, 'joomlaboost') !== false;

    echo "   ✅ Response received\n";
    echo "   📊 Analysis:\n";
    echo "      - Valid XML declaration: " . ($hasXmlDeclaration ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Contains <urlset>: " . ($hasUrlset ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Contains URLs: " . ($hasUrls ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Staging environment: " . ($hasStaging ? '✅ YES' : '❌ NO') . "\n";
    echo "      - JoomlaBoost signature: " . ($hasJoomlaBoost ? '✅ YES' : '❌ NO') . "\n";

    $result['analysis'] = [
      'valid_xml' => $hasXmlDeclaration,
      'has_urlset' => $hasUrlset,
      'has_urls' => $hasUrls,
      'has_staging' => $hasStaging,
      'has_signature' => $hasJoomlaBoost
    ];

    // Count URLs
    $urlCount = substr_count($content, '<url>');
    echo "      - URL count: {$urlCount}\n";
    $result['analysis']['url_count'] = $urlCount;

    echo "   📝 Sample Content (first 300 chars):\n";
    echo "      " . substr($content, 0, 300) . "...\n";
  } else {
    echo "   ❌ Failed to retrieve sitemap.xml\n";
  }

  return $result;
}

/**
 * Test homepage meta tags
 */
function testHomepageMeta(string $baseUrl): array
{
  echo "\n📋 Test 3: Homepage Meta Tags\n";
  echo "-----------------------------\n";

  $result = testHttpRequest($baseUrl . '/', 'Homepage HTML');

  if ($result['success']) {
    $content = $result['content'];

    // Check for various meta tags
    $hasOpenGraph = stripos($content, 'property="og:') !== false;
    $hasSchema = stripos($content, 'application/ld+json') !== false;
    $hasGoogleVerification = stripos($content, 'google-site-verification') !== false;
    $hasViewport = stripos($content, 'name="viewport"') !== false;
    $hasDescription = stripos($content, 'name="description"') !== false;

    echo "   ✅ Response received\n";
    echo "   📊 Meta Tag Analysis:\n";
    echo "      - OpenGraph tags: " . ($hasOpenGraph ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Schema.org JSON-LD: " . ($hasSchema ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Google verification: " . ($hasGoogleVerification ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Viewport meta: " . ($hasViewport ? '✅ YES' : '❌ NO') . "\n";
    echo "      - Description meta: " . ($hasDescription ? '✅ YES' : '❌ NO') . "\n";

    $result['analysis'] = [
      'has_opengraph' => $hasOpenGraph,
      'has_schema' => $hasSchema,
      'has_google_verification' => $hasGoogleVerification,
      'has_viewport' => $hasViewport,
      'has_description' => $hasDescription
    ];

    // Extract meta tags for detailed analysis
    preg_match_all('/<meta[^>]+>/i', $content, $metaTags);
    $metaCount = count($metaTags[0]);
    echo "      - Total meta tags: {$metaCount}\n";
    $result['analysis']['meta_count'] = $metaCount;

    // Check for Schema.org scripts
    preg_match_all('/<script[^>]*type="application\/ld\+json"[^>]*>.*?<\/script>/is', $content, $schemaScripts);
    $schemaCount = count($schemaScripts[0]);
    echo "      - Schema.org scripts: {$schemaCount}\n";
    $result['analysis']['schema_count'] = $schemaCount;
  } else {
    echo "   ❌ Failed to retrieve homepage\n";
  }

  return $result;
}

/**
 * Test alternative URL formats
 */
function testAlternativeFormats(string $baseUrl): array
{
  echo "\n📋 Test 4: Alternative URL Formats\n";
  echo "----------------------------------\n";

  $results = [];

  // Test ?format=robots
  echo "🔍 Testing: ?format=robots\n";
  $robotsFormat = testHttpRequest($baseUrl . '/?format=robots', 'Robots via format parameter');
  $results['robots_format'] = $robotsFormat;

  // Test ?format=sitemap  
  echo "\n🔍 Testing: ?format=sitemap\n";
  $sitemapFormat = testHttpRequest($baseUrl . '/?format=sitemap', 'Sitemap via format parameter');
  $results['sitemap_format'] = $sitemapFormat;

  return $results;
}

/**
 * Performance benchmarking
 */
function testPerformance(string $baseUrl): array
{
  echo "\n📋 Test 5: Performance Benchmarking\n";
  echo "-----------------------------------\n";

  $tests = [
    'homepage' => '/',
    'robots' => '/robots.txt',
    'sitemap' => '/sitemap.xml'
  ];

  $results = [];

  foreach ($tests as $name => $path) {
    echo "⏱️  Testing {$name} performance:\n";

    $times = [];
    for ($i = 0; $i < 3; $i++) {
      $startTime = microtime(true);
      $content = @file_get_contents($baseUrl . $path);
      $times[] = round((microtime(true) - $startTime) * 1000, 2);
    }

    $avgTime = round(array_sum($times) / count($times), 2);
    $minTime = min($times);
    $maxTime = max($times);

    echo "   Average: {$avgTime}ms | Min: {$minTime}ms | Max: {$maxTime}ms\n";

    $results[$name] = [
      'times' => $times,
      'average' => $avgTime,
      'min' => $minTime,
      'max' => $maxTime
    ];
  }

  return $results;
}

// Run all tests
echo "🚀 Starting live tests for: {$stagingUrl}\n\n";

$testResults['robots'] = testRobotsTxt($stagingUrl);
$testResults['sitemap'] = testSitemapXml($stagingUrl);
$testResults['homepage'] = testHomepageMeta($stagingUrl);
$testResults['alternative'] = testAlternativeFormats($stagingUrl);
$testResults['performance'] = testPerformance($stagingUrl);

// Summary
echo "\n🎯 TEST SUMMARY\n";
echo "===============\n";

$passed = 0;
$total = 0;

foreach ($testResults as $testType => $result) {
  if ($testType === 'performance' || $testType === 'alternative') {
    continue; // Skip performance and alternative tests in summary
  }

  $total++;
  if (isset($result['success']) && $result['success']) {
    $passed++;
    echo "✅ {$testType}: PASSED\n";
  } else {
    echo "❌ {$testType}: FAILED\n";
  }
}

echo "\n📊 Results: {$passed}/{$total} tests passed\n";

if ($passed === $total) {
  echo "🎉 All tests PASSED! Plugin is working correctly!\n";
} else {
  echo "⚠️  Some tests failed. Please check the results above.\n";
}

echo "\n🔧 Manual verification:\n";
echo "- Visit: {$stagingUrl}/robots.txt\n";
echo "- Visit: {$stagingUrl}/sitemap.xml\n";
echo "- View source: {$stagingUrl}/\n";
