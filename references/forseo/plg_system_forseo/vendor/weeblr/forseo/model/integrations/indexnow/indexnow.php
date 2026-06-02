<?php
/**
 * Project: 4SEO
 *
 * @package          4SEO
 * @copyright        Copyright Weeblr llc - 2020 - 2026
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          6.10.1.2660
 * @date        2026-01-30
 */

namespace Weeblr\Forseo\Model\Integrations\Indexnow;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Indexnow extends Base\Base
{
	/**
	 * @var string The name of the provider.
	 */
	protected $provider = 'IndexNow';

	/**
	 * Default endpoint to submit IndexNow requests
	 */
	protected $endpoint = 'https://api.indexnow.org/indexnow?url={{url}}&key={{key}}';

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger;

	/**
	 * A class to access Google Search console.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->logger = $this->factory->getThe('forseo.logger');
	}

	/**
	 * Handle a request to submit a URL to IndexNow.
	 *
	 * post /forseo/v1/services/indexnow.bing/url
	 *
	 * @param string $request
	 * @param array  $options
	 * @return array|\Exception
	 * @throws \Exception
	 */
	public function post($request, $options)
	{
		try
		{
			$rawUrls = Wb\arrayGet(
				$request,
				'urls',
				[]
			);

			$rawUrls = is_array($rawUrls)
				? $rawUrls
				: [$rawUrls];

			// One URL at a time for now.
			$url = array_shift($rawUrls);
			if (
				empty($url)
				||
				!Wb\startsWith(
					$url,
					'/'
				)
			) {
				throw new \Exception('indexNow.errorInvalidUrl', System\Http::RETURN_BAD_REQUEST);
			}

			return $this->submit($url);

		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception($e->getMessage(), System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Submit a fully qualified URL to IndexNow for indexing.
	 *
	 * @param string $url
	 *
	 * @return array|\Exception
	 */
	protected function submit($url)
	{
		$endpoint = str_replace('{{key}}', $this->getKey(), $this->endpoint);

		$encodedUrl = Wb\slashTrimJoin(
			$this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl'),
			System\Route::encodeUrlForSitemap($url)
		);
		$endpoint   = str_replace('{{url}}', $encodedUrl, $endpoint);

		$client = $this->platform->getHttpClient(
			[
				'follow_location' => true,
				'timeout'         => 10,
				'userAgent'       => FORSEO_CRAWLER_USER_AGENT,
			]
		);

		$this->logger->debug(
			'IndexNow (' . $this->provider . '): submitting request for ' . $url . ' to ' . $endpoint
		);

		$this->logger->custom(
			'indexnow',
			'IndexNow (' . $this->provider . '): submitting request for ' . $url . ' to ' . $endpoint
		);

		$response = $client->get($endpoint);
		switch ($response->code)
		{
			case System\Http::RETURN_OK:
				return [
					'status' => System\Http::RETURN_OK
				];
			default:
				return [
					'data' => [
						'status' => 'error',
						'errors' => [
							'message' => 'indexNow.submissionFailure',
							'details' => 'indexNow.submissionError' . $response->code,
							'code'    => $response->code
						]
					]
				];
		}
	}

	/**
	 * Builds a secret key and associated key file for authentication
	 * with the IndexNow provider.
	 *
	 * @return string
	 */
	protected function getKey()
	{
		static $key = null;

		if (empty($key))
		{
			$key         = $this->buildKey();
			$keyFilePath = $this->platform->getRootPath() . '/' . $key . '.txt';
			if (!file_exists($keyFilePath))
			{
				file_put_contents($keyFilePath, $key);
			}
		}

		return $key;
	}

	/**
	 * Derive an IndexNow key from the website secret value.
	 *
	 * @return string
	 */
	protected function buildKey()
	{
		return 'indexnow-' . sha1($this->platform->getUniqueId());
	}
}
