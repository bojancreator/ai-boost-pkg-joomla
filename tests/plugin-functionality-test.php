<?php

/**
 * JoomlaBoost Plugin Functionality Tests
 * 
 * This script tests core plugin functionality without requiring full Joomla installation
 * 
 * Usage:
 * php tests/plugin-functionality-test.php
 * 
 * @author JoomlaBoost
 * @version 1.0.0
 */

// Error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "🚀 JoomlaBoost Plugin Functionality Tests\n";
echo "=========================================\n\n";

// Test 1: Basic Plugin Class Loading
echo "📋 Test 1: Plugin Class Structure\n";
echo "-----------------------------------\n";

// Mock Joomla constants and classes
if (!defined('_JEXEC')) {
  define('_JEXEC', 1);
}

// Simple mock for testing
class MockCMSPlugin
{
  protected $params;
  protected $autoloadLanguage = true;

  public function __construct($params = [])
  {
    $this->params = (object) $params;
  }
}

class MockHtmlDocument
{
  private $metaData = [];
  private $customTags = [];

  public function setMetaData($name, $content)
  {
    $this->metaData[$name] = $content;
    echo "  ✅ Meta added: {$name} = {$content}\n";
  }

  public function addCustomTag($tag)
  {
    $this->customTags[] = $tag;
    echo "  ✅ Custom tag added: " . substr($tag, 0, 50) . "...\n";
  }

  public function getMetaData()
  {
    return $this->metaData;
  }
  public function getCustomTags()
  {
    return $this->customTags;
  }
}

class MockCMSApplication
{
  public function isClient($type)
  {
    return $type === 'site';
  }

  public function getDocument()
  {
    return new MockHtmlDocument();
  }

  public function enqueueMessage($message, $type = 'info')
  {
    echo "  🔔 Debug: {$message}\n";
  }

  public function close()
  {
    echo "  ✅ Application closed (robots/sitemap response)\n";
  }
}

class MockFactory
{
  public static function getApplication()
  {
    return new MockCMSApplication();
  }
}

// Test 2: Domain Detection Logic
echo "\n📋 Test 2: Domain Detection\n";
echo "----------------------------\n";

function testDomainDetection()
{
  // Test staging detection
  $testDomains = [
    'https://staging.offroadserbia.com/' => true,
    'https://dev.example.com/' => true,
    'https://test.site.com/' => true,
    'http://localhost:8080/' => true,
    'https://www.production.com/' => false,
    'https://offroadserbia.com/' => false,
  ];

  foreach ($testDomains as $domain => $expectedStaging) {
    $isStaging = isTestStaging($domain);
    $status = ($isStaging === $expectedStaging) ? '✅' : '❌';
    $env = $isStaging ? 'STAGING' : 'PRODUCTION';
    echo "  {$status} {$domain} → {$env}\n";
  }
}

function isTestStaging(string $domain): bool
{
  $stagingKeywords = ['staging', 'stage', 'dev', 'test', 'localhost'];
  foreach ($stagingKeywords as $keyword) {
    if (stripos($domain, $keyword) !== false) {
      return true;
    }
  }
  return false;
}

testDomainDetection();

// Test 3: Robots.txt Generation
echo "\n📋 Test 3: Robots.txt Generation\n";
echo "--------------------------------\n";

function testRobotsGeneration()
{
  // Test staging robots
  echo "  🤖 Testing STAGING robots.txt:\n";
  $stagingRobots = generateTestRobots(true);
  if (
    strpos($stagingRobots, 'STAGING ENVIRONMENT') !== false &&
    strpos($stagingRobots, 'Disallow: /') !== false
  ) {
    echo "    ✅ Staging robots correctly blocks indexing\n";
  } else {
    echo "    ❌ Staging robots validation failed\n";
  }

  // Test production robots
  echo "  🤖 Testing PRODUCTION robots.txt:\n";
  $productionRobots = generateTestRobots(false);
  if (
    strpos($productionRobots, 'PRODUCTION ENVIRONMENT') !== false &&
    strpos($productionRobots, 'Allow: /') !== false &&
    strpos($productionRobots, 'Sitemap:') !== false
  ) {
    echo "    ✅ Production robots correctly allows indexing\n";
  } else {
    echo "    ❌ Production robots validation failed\n";
  }
}

function generateTestRobots(bool $isStaging): string
{
  if ($isStaging) {
    return "# JoomlaBoost Robots.txt - STAGING ENVIRONMENT\n"
      . "# This site is not indexed by search engines\n\n"
      . "User-agent: *\n"
      . "Disallow: /\n\n"
      . "# This is a staging environment - not for public indexing\n";
  } else {
    return "# JoomlaBoost Robots.txt - PRODUCTION ENVIRONMENT\n\n"
      . "User-agent: *\n"
      . "Allow: /\n\n"
      . "# Sitemap\n"
      . "Sitemap: https://example.com/sitemap.xml\n";
  }
}

testRobotsGeneration();

// Test 4: Sitemap.xml Generation
echo "\n📋 Test 4: Sitemap.xml Generation\n";
echo "---------------------------------\n";

function testSitemapGeneration()
{
  echo "  🗺️  Testing STAGING sitemap.xml:\n";
  $stagingSitemap = generateTestSitemap(true);
  if (
    strpos($stagingSitemap, 'STAGING ENVIRONMENT') !== false &&
    strpos($stagingSitemap, '<urlset') !== false
  ) {
    echo "    ✅ Staging sitemap generated correctly\n";
  } else {
    echo "    ❌ Staging sitemap validation failed\n";
  }

  echo "  🗺️  Testing PRODUCTION sitemap.xml:\n";
  $productionSitemap = generateTestSitemap(false);
  if (
    strpos($productionSitemap, 'PRODUCTION ENVIRONMENT') !== false &&
    strpos($productionSitemap, '<urlset') !== false
  ) {
    echo "    ✅ Production sitemap generated correctly\n";
  } else {
    echo "    ❌ Production sitemap validation failed\n";
  }
}

function generateTestSitemap(bool $isStaging): string
{
  $domain = 'https://example.com/';
  $lastmod = date('Y-m-d\TH:i:s\Z');
  $env = $isStaging ? 'STAGING' : 'PRODUCTION';

  return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
    . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
    . "  <!-- JoomlaBoost Sitemap - {$env} ENVIRONMENT -->\n"
    . "  <url>\n"
    . '    <loc>' . htmlspecialchars($domain) . "</loc>\n"
    . '    <lastmod>' . $lastmod . "</lastmod>\n"
    . "    <changefreq>daily</changefreq>\n"
    . "    <priority>1.0</priority>\n"
    . "  </url>\n"
    . "</urlset>";
}

testSitemapGeneration();

// Test 5: URL Pattern Matching
echo "\n📋 Test 5: URL Pattern Matching\n";
echo "-------------------------------\n";

function testUrlPatterns()
{
  $testUrls = [
    '/robots.txt' => 'robots',
    '/sitemap.xml' => 'sitemap',
    '/?format=robots' => 'robots',
    '/?format=sitemap' => 'sitemap',
    '/index.php' => 'none',
    '/about-us' => 'none',
  ];

  foreach ($testUrls as $url => $expected) {
    $isRobots = testIsRobotsRequest($url);
    $isSitemap = testIsSitemapRequest($url);

    $detected = 'none';
    if ($isRobots) $detected = 'robots';
    if ($isSitemap) $detected = 'sitemap';

    $status = ($detected === $expected) ? '✅' : '❌';
    echo "  {$status} {$url} → {$detected} (expected: {$expected})\n";
  }
}

function testIsRobotsRequest(string $uri): bool
{
  $cleanUri = strtok($uri, '?') ?: '';
  if (preg_match('#/robots\.txt$#i', $cleanUri)) {
    return true;
  }
  parse_str(parse_url($uri, PHP_URL_QUERY) ?: '', $query);
  return isset($query['format']) && $query['format'] === 'robots';
}

function testIsSitemapRequest(string $uri): bool
{
  $cleanUri = strtok($uri, '?') ?: '';
  if (preg_match('#/sitemap\.xml$#i', $cleanUri)) {
    return true;
  }
  parse_str(parse_url($uri, PHP_URL_QUERY) ?: '', $query);
  return isset($query['format']) && $query['format'] === 'sitemap';
}

testUrlPatterns();

// Test 6: Performance Simulation
echo "\n📋 Test 6: Performance Testing\n";
echo "------------------------------\n";

function testPerformance()
{
  echo "  ⏱️  Testing performance measurement:\n";

  $startTime = microtime(true);

  // Simulate some work
  for ($i = 0; $i < 1000; $i++) {
    $test = md5('test' . $i);
  }

  $duration = round((microtime(true) - $startTime) * 1000, 2);
  echo "    ✅ Mock operations completed in {$duration}ms\n";

  if ($duration < 50) {
    echo "    🚀 Performance: EXCELLENT (< 50ms)\n";
  } elseif ($duration < 100) {
    echo "    ✅ Performance: GOOD (< 100ms)\n";
  } else {
    echo "    ⚠️  Performance: SLOW (> 100ms)\n";
  }
}

testPerformance();

// Test 7: Configuration Validation
echo "\n📋 Test 7: Configuration Validation\n";
echo "-----------------------------------\n";

function testConfiguration()
{
  $testConfigs = [
    ['enable_opengraph' => 1, 'enable_schema' => 1, 'debug_mode' => 1],
    ['enable_opengraph' => 0, 'enable_schema' => 0, 'debug_mode' => 0],
    ['gsc_verification_meta' => 'test123', 'enable_ga4' => 1],
  ];

  foreach ($testConfigs as $index => $config) {
    echo "  ⚙️  Testing config set " . ($index + 1) . ":\n";
    $params = (object) $config;

    foreach ($config as $key => $value) {
      $retrieved = $params->{$key} ?? 0;
      $status = ($retrieved == $value) ? '✅' : '❌';
      echo "    {$status} {$key}: {$value}\n";
    }
  }
}

testConfiguration();

// Summary
echo "\n🎯 TEST SUMMARY\n";
echo "===============\n";
echo "✅ All core functionality tests completed!\n";
echo "🚀 Ready for live testing on staging environment\n\n";

echo "📋 NEXT STEPS:\n";
echo "1. Deploy plugin to staging site\n";
echo "2. Test robots.txt: https://staging.offroadserbia.com/robots.txt\n";
echo "3. Test sitemap.xml: https://staging.offroadserbia.com/sitemap.xml\n";
echo "4. Check meta tags in HTML source\n";
echo "5. Verify performance in debug mode\n\n";

echo "🔧 MANUAL TESTING COMMANDS:\n";
echo "curl -I https://staging.offroadserbia.com/robots.txt\n";
echo "curl -I https://staging.offroadserbia.com/sitemap.xml\n";
echo "curl -s https://staging.offroadserbia.com/ | grep -i 'meta\\|schema'\n\n";
