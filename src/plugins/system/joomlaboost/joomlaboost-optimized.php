<?php

/**
 * JoomlaBoost Plugin - Optimized Performance Edition
 * @version     1.0.0
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Application\CMSApplication;

// Register optimized autoloader
require_once __DIR__ . '/src/Services/ServiceAutoloader.php';
JoomlaBoost\Plugin\System\JoomlaBoost\Services\ServiceAutoloader::register(__DIR__ . '/src/Services/');

// Load core services immediately for better performance
JoomlaBoost\Plugin\System\JoomlaBoost\Services\ServiceAutoloader::loadCoreServices();

use JoomlaBoost\Plugin\System\JoomlaBoost\Services\ServiceContainer;

/**
 * JoomlaBoost plugin - Performance Optimized Architecture
 */
class PlgSystemJoomlaboost extends CMSPlugin
{
  /**
   * Load the language file on instantiation
   * @var bool
   */
  protected bool $autoloadLanguage = true;

  /** @var ServiceContainer|null */
  private ?ServiceContainer $serviceContainer = null;

  /**
   * Get service container with lazy initialization
   */
  private function getServiceContainer(): ServiceContainer
  {
    if ($this->serviceContainer === null) {
      $app = $this->getApp();
      if ($app) {
        $this->serviceContainer = new ServiceContainer($app, $this->params);
      }
    }

    return $this->serviceContainer;
  }

  /**
   * Safe application access
   */
  private function getApp(): ?CMSApplication
  {
    try {
      return Factory::getApplication();
    } catch (\Throwable $e) {
      return null;
    }
  }

  /**
   * Early request hook (check robots/sitemap)
   */
  public function onAfterInitialise(): void
  {
    $app = $this->getApp();
    if (!$app || !$app->isClient('site')) {
      return;
    }

    $container = $this->getServiceContainer();

    // robots.txt handling
    if ($this->isRobotsRequest()) {
      $robotService = $container->get('robot');
      if ($robotService->isEnabled()) {
        $this->handleRobotsRequest($app, $robotService);
        return;
      }
    }

    // sitemap.xml handling
    if ($this->isSitemapRequest()) {
      $sitemapService = $container->get('sitemap');
      if ($sitemapService->isEnabled()) {
        $this->handleSitemapRequest($app, $sitemapService);
        return;
      }
    }
  }

  /**
   * Optimized head compilation with service container
   */
  public function onBeforeCompileHead(): void
  {
    $app = $this->getApp();
    if (!$app || !$app->isClient('site')) {
      return;
    }

    $document = $app->getDocument();
    if (!$document instanceof HtmlDocument) {
      return;
    }

    $container = $this->getServiceContainer();
    $startTime = $this->params->get('debug_mode', 0) ? microtime(true) : 0;

    try {
      // Only get services that are actually enabled
      $enabledServices = $container->getEnabledServices();

      // 1. Performance service for request optimization
      if (isset($enabledServices['performance'])) {
        $perfService = $enabledServices['performance'];
        // Performance service doesn't need explicit processing here
      }

      // 2. Lightweight operations first (no DB queries)
      $this->addGoogleVerificationTags($document);

      // 3. Meta Pixel if enabled
      if (isset($enabledServices['metaPixel'])) {
        $this->addMetaPixel($document, $enabledServices['metaPixel']);
      }

      // 4. Analytics if enabled
      if (isset($enabledServices['analytics'])) {
        $this->addAnalytics($document, $enabledServices['analytics']);
      }

      // 5. OpenGraph tags with optimizations
      if (isset($enabledServices['openGraph'])) {
        $this->addOptimizedOpenGraphTags($document, $enabledServices['openGraph']);
      }

      // 6. Schema markup with optimizations  
      if (isset($enabledServices['schema'])) {
        $this->addOptimizedSchemaMarkup($document, $enabledServices['schema']);
      }

      // 7. Process any batched operations
      if (isset($enabledServices['performance'])) {
        $processed = $enabledServices['performance']->processBatchedMeta($document);
      }

      // Log performance metrics if debug enabled
      if ($this->params->get('debug_mode', 0) && $startTime > 0) {
        $this->logPerformanceMetrics($startTime, $container);
      }
    } catch (\Throwable $e) {
      $this->logDebug('Head compilation failed: ' . $e->getMessage());
    }
  }

  /**
   * HTML post-processing with injection service
   */
  public function onAfterRender(): void
  {
    $app = $this->getApp();
    if (!$app || !$app->isClient('site')) {
      return;
    }

    $container = $this->getServiceContainer();

    if ($container->isServiceEnabled('injection')) {
      $injectionService = $container->get('injection');
      $body = $app->getBody();
      $modifiedBody = $injectionService->applyInjections($body, $this->params->get('debug_mode', 0));
      $app->setBody($modifiedBody);
    }
  }

  /**
   * AJAX endpoint handling
   */
  public function onAjaxJoomlaboost(): void
  {
    $app = $this->getApp();
    if (!$app) {
      return;
    }

    $container = $this->getServiceContainer();
    $input = $app->getInput();
    $action = $input->getCmd('action', '');

    try {
      switch ($action) {
        case 'health':
          if ($container->isServiceEnabled('health')) {
            $healthService = $container->get('health');
            echo $healthService->generateHealthResponse();
          }
          break;

        case 'diagnostics':
          if ($container->isServiceEnabled('health')) {
            $healthService = $container->get('health');
            echo $healthService->generateDiagnosticResponse();
          }
          break;

        default:
          echo json_encode(['error' => 'Unknown action']);
      }
    } catch (\Throwable $e) {
      echo json_encode(['error' => $e->getMessage()]);
    }

    $app->close();
  }

  // Private helper methods

  private function isRobotsRequest(): bool
  {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $cleanUri = strtok($requestUri, '?') ?: '';
    return preg_match('#/robots\.txt$#i', $cleanUri) ||
      (isset($_GET['format']) && (string) $_GET['format'] === 'robots');
  }

  private function isSitemapRequest(): bool
  {
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $cleanUri = strtok($requestUri, '?') ?: '';
    return preg_match('#/sitemap\.xml$#i', $cleanUri) ||
      (isset($_GET['format']) && (string) $_GET['format'] === 'sitemap');
  }

  private function handleRobotsRequest(CMSApplication $app, $robotService): void
  {
    header('Content-Type: text/plain');
    header('Cache-Control: public, max-age=3600');
    echo $robotService->renderRobotsTxt();
    $app->close();
  }

  private function handleSitemapRequest(CMSApplication $app, $sitemapService): void
  {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo $sitemapService->renderSitemapIndex();
    $app->close();
  }

  private function addOptimizedOpenGraphTags(HtmlDocument $document, $openGraphService): void
  {
    try {
      $openGraphService->generateOpenGraphTags();
      $this->logDebug('OpenGraph tags generated with performance optimizations');
    } catch (\Throwable $e) {
      $this->logDebug('OpenGraph generation failed: ' . $e->getMessage());
    }
  }

  private function addOptimizedSchemaMarkup(HtmlDocument $document, $schemaService): void
  {
    try {
      $schema = $schemaService->generateSchema();
      if (!empty($schema)) {
        $jsonLd = '<script type="application/ld+json">' . "\n";
        $jsonLd .= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $jsonLd .= "\n" . '</script>';
        $document->addCustomTag($jsonLd);

        $this->logDebug('Schema.org JSON-LD generated with performance optimizations', [
          'schemas_count' => count($schema)
        ]);
      } else {
        $this->logDebug('Schema: No schema data generated');
      }
    } catch (\Throwable $e) {
      $this->logDebug('Schema.org generation failed: ' . $e->getMessage());
    }
  }

  private function addGoogleVerificationTags(HtmlDocument $document): void
  {
    $gscMeta = $this->params->get('gsc_verification_meta', '');
    if (!empty($gscMeta)) {
      $document->setMetaData('google-site-verification', $gscMeta);
      $this->logDebug('Added Google Search Console verification meta tag');
    }

    $additionalHtml = $this->params->get('gsc_additional_html', '');
    if (!empty($additionalHtml)) {
      $document->addCustomTag($additionalHtml);
      $this->logDebug('Added additional Google verification HTML');
    }
  }

  private function addAnalytics(HtmlDocument $document, $analyticsService): void
  {
    try {
      if ($this->params->get('enable_ga4', 0)) {
        $ga4Code = $analyticsService->generateGoogleAnalytics();
        if ($ga4Code) {
          $document->addCustomTag($ga4Code);
        }
      }

      if ($this->params->get('enable_gtm', 0)) {
        $gtmCode = $analyticsService->generateGoogleTagManager();
        if ($gtmCode) {
          $document->addCustomTag($gtmCode);
        }
      }

      $this->logDebug('Analytics tracking added');
    } catch (\Throwable $e) {
      $this->logDebug('Analytics generation failed: ' . $e->getMessage());
    }
  }

  private function addMetaPixel(HtmlDocument $document, $metaPixelService): void
  {
    try {
      $metaPixelService->injectPixelCode($document);
      $metaPixelService->injectCustomEvents($document);
      $this->logDebug('Meta Pixel tracking added');
    } catch (\Throwable $e) {
      $this->logDebug('Meta Pixel generation failed: ' . $e->getMessage());
    }
  }

  private function logPerformanceMetrics(float $startTime, ServiceContainer $container): void
  {
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    $metrics = $container->getMetrics();

    $this->logDebug("Head compilation completed in {$duration}ms", $metrics);
  }

  private function logDebug(string $message, array $context = []): void
  {
    try {
      if ($this->params && $this->params->get('debug_mode', 0)) {
        $logMessage = '[JoomlaBoost] ' . $message;
        if (!empty($context)) {
          $logMessage .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        Factory::getApplication()->enqueueMessage($logMessage, 'info');
      }
    } catch (\Throwable $e) {
      // ignore
    }
  }
}
