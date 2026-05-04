<?php

/**
 * JoomlaBoost - IndexNow Service
 *
 * Notifies search engines (Bing, Yandex, Seznam) instantly when
 * content is published or updated, using the IndexNow protocol.
 *
 * @copyright   (C) 2024 emarket1ng.NET
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

// phpcs:disable
if (!defined('JPATH_ROOT')) {
    define('JPATH_ROOT', dirname(__DIR__, 6));
}
// phpcs:enable

/**
 * IndexNow Service
 *
 * Implements the IndexNow protocol for instant search engine notification.
 * A single API key file at domain root verifies site ownership.
 * One ping to api.indexnow.org reaches Bing, Yandex and Seznam.
 */
class IndexNowService extends AbstractService
{
    /** @var string IndexNow universal endpoint */
    private const API_ENDPOINT = 'https://api.indexnow.org/indexnow';

    /**
     * Required by AbstractService
     */
    protected function getServiceKey(): string
    {
        return 'indexnow';
    }

    /**
     * Check if IndexNow is enabled and configured.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->params->get('indexnow_enabled', 0)
            && !empty(trim((string) $this->params->get('indexnow_api_key', '')));
    }

    /**
     * Ping IndexNow for the given URL.
     * Returns true on success (HTTP 200/202), false on failure.
     *
     * @param string $url Full URL of the updated page
     */
    public function pingUrl(string $url): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $apiKey      = trim((string) $this->params->get('indexnow_api_key', ''));
        $siteBase    = rtrim((string) Uri::base(), '/');
        $keyLocation = $siteBase . '/' . $apiKey . '.txt';

        $apiUrl = self::API_ENDPOINT . '?' . http_build_query([
            'url'         => $url,
            'key'         => $apiKey,
            'keyLocation' => $keyLocation,
        ]);

        try {
            $http     = \Joomla\CMS\Http\HttpFactory::getHttp();
            $response = $http->get($apiUrl, [], 10);
            $success  = in_array($response->code, [200, 202], true);

            $this->logDebug('IndexNow ping ' . ($success ? 'OK' : 'FAILED') . ' [' . $response->code . ']: ' . $url);

            return $success;
        } catch (\Throwable $e) {
            $this->logDebug('IndexNow ping exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create the API key verification file at the site root.
     * File: {JPATH_SITE}/{apiKey}.txt containing exactly the API key.
     * Must be publicly accessible at https://domain.com/{apiKey}.txt
     *
     * Returns true if file exists/created, false otherwise.
     */
    public function ensureKeyFile(): bool
    {
        $apiKey = trim((string) $this->params->get('indexnow_api_key', ''));

        if (empty($apiKey)) {
            return false;
        }

        // Validate key: alphanumeric + hyphens only (IndexNow spec)
        if (!preg_match('/^[a-zA-Z0-9\-]{8,128}$/', $apiKey)) {
            $this->logDebug('IndexNow: invalid API key format');
            return false;
        }

        $keyFile = JPATH_SITE . DIRECTORY_SEPARATOR . $apiKey . '.txt';

        if (!file_exists($keyFile)) {
            $result = file_put_contents($keyFile, $apiKey);
            $this->logDebug('IndexNow: key file ' . ($result !== false ? 'created' : 'FAILED') . ': ' . $keyFile);
            return $result !== false;
        }

        return true;
    }

    /**
     * Build the public URL for a Joomla article by its ID.
     *
     * @param int|string $articleId Joomla article ID
     */
    public function buildArticleUrl($articleId): string
    {
        try {
            $route = \Joomla\CMS\Router\Route::_(
                'index.php?option=com_content&view=article&id=' . (int) $articleId
            );

            $base = rtrim((string) Uri::base(), '/');
            return $base . '/' . ltrim($route, '/');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
