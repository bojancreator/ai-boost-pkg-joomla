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

namespace Weeblr\Forseo\Model\Integrations\Google;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Helper\Integrations as IntegrationsHelper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;

use Weeblr\Wblib\Forseo\Integrations;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\Google;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\Google\Service;
use Weeblr\Wblib\Forseo\Integrations\Googleapis\v1\Google\Service\SiteVerification;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Searchconsole extends Base\Base
{
	/**
	 * Internal id for this service
	 */
	public const SERVICE_ID = 'google.search_console';

	/**
	 * Name of app registered with Google. We may have several of them
	 * to spread the requests load in the future.
	 */
	public const APPLICATION_NAME = '4SEO Google APIs access';

	/**
	 * @var Service\SearchConsole An object to communicate with Google search console.
	 */
	private $searchConsoleService;

	/**
	 * @var Service\SiteVerification An object to communicate to verify sites with Google.
	 */
	private $siteVerificationService;

	/**
	 * @var Keystore General purpose storage.
	 */
	private $keystore = null;

	/**
	 * @var IntegrationsHelper\Googlesearchconsole
	 */
	private $integrationHelper = null;

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

		$this->searchConsoleService    = $this->factory->getThe('forseo.google.searchConsoleService');
		$this->siteVerificationService = $this->factory->getThe('forseo.google.siteVerificationService');

		$this->keystore          = $this->factory->getThe('forseo.keystore');
		$this->logger            = $this->factory->getThe('forseo.logger');
		$this->integrationHelper = $this->factory->getA(IntegrationsHelper\Googlesearchconsole::class);
	}

	/**
	 * Dispatch API data requests per data type method.
	 *
	 * @param array $options
	 * @return mixed
	 * @throws \Exception
	 */
	public function get($options)
	{
		$dataType   = Wb\arrayGet($options, 'type', '');
		$methodName = 'get' . ucfirst($dataType);
		if (!is_callable([$this, $methodName]))
		{
			throw new \Exception('Getting invalid data type in ' . __METHOD__ . ': ' . $dataType, 500);
		}

		return $this->{$methodName}($options);
	}

	/**
	 * Dispatch API data requests per data type method.
	 *
	 * @param mixed $data
	 * @param array $options
	 * @return mixed
	 * @throws \Exception
	 */
	public function post($data, $options)
	{
		$dataType   = Wb\arrayGet($options, 'type', '');
		$methodName = 'create' . ucfirst($dataType);
		if (!is_callable([$this, $methodName]))
		{
			throw new \Exception('Getting invalid data type in ' . __METHOD__ . ': ' . $dataType, 500);
		}

		return $this->{$methodName}($data, $options);
	}

	/**
	 * Dispatch API data requests per data type method.
	 *
	 * @param mixed $data
	 * @param array $options
	 * @return mixed
	 * @throws \Exception
	 */
	public function put($data, $options)
	{
		$dataType   = Wb\arrayGet($options, 'type', '');
		$methodName = 'update' . ucfirst($dataType);
		if (!is_callable([$this, $methodName]))
		{
			throw new \Exception('Getting invalid data type in ' . __METHOD__ . ': ' . $dataType, 500);
		}

		return $this->{$methodName}($data, $options);
	}

	/**
	 * Dispatch API data requests per data type method.
	 *
	 * @param array $options
	 * @return mixed
	 * @throws \Exception
	 */
	public function delete($options)
	{
		$dataType   = Wb\arrayGet($options, 'type', '');
		$methodName = 'delete' . ucfirst($dataType);
		if (!is_callable([$this, $methodName]))
		{
			throw new \Exception('Deleting invalid data type in ' . __METHOD__ . ': ' . $dataType, 500);
		}

		return $this->{$methodName}($options);
	}

	/**
	 * Delete a sitemap for the current property from the Google Search console.
	 * NB: this does not delete the sitemap itself, just the reference to it in the Google Search console reports.
	 * Google can/will still crawl the sitemap and the URLs it contains.
	 *
	 * @param array $options
	 * @return array|\Exception
	 */
	public function deleteSitemaps($options)
	{
		try
		{
			$property    = $this->integrationHelper->getCurrentProperty();
			$sitemapPath = Wb\arrayGet($options, 'sitemapPath');
			if (empty($sitemapPath))
			{
				throw new \Exception('Missing or invalid sitemap address trying to delete a sitemap.', System\Http::RETURN_BAD_REQUEST);
			}

			$this->searchConsoleService->sitemaps->delete($property);

			return [
				'status' => System\Http::RETURN_NO_CONTENT
			];

		}
		catch (Service\Exception $exception)
		{
			return $this->integrationHelper->logConsoleError(
				$exception,
				'Error deleting a sitemap list with Google. More details in log files on the server.'
			);
		}
		catch (\Exception $e)
		{
			return $e;
		}
	}

	/**
	 * Submit sitemap to Google Search console.
	 *
	 * @param array $options
	 * @return bool|\Exception
	 */
	public function updateSitemaps($options)
	{
		try
		{
			// if not connected, bail and return false
			if (!$this->factory->getThis('forseo.config', 'integrations')->isGoogleSearchConsoleActive())
			{
				return false;
			}

			$property    = $this->integrationHelper->getCurrentProperty();
			$sitemapPath = Wb\arrayGet($options, 'sitemapPath');
			if (empty($sitemapPath))
			{
				$sitemapPath = $this->factory
					->getA(Helper\Sitemaps::class)
					->xmlUrl();
			}
			if (empty($sitemapPath))
			{
				throw new \Exception('Missing or invalid sitemap address trying to submit a sitemap.', System\Http::RETURN_BAD_REQUEST);
			}

			$this->searchConsoleService->sitemaps->submit(
				$property,
				$sitemapPath
			);

			return true;
		}
		catch (Service\Exception $exception)
		{
			return $this->integrationHelper->logConsoleError(
				$exception,
				'Error submitting a sitemap list with Google. More details in log files on the server.'
			);
		}
		catch (\Exception $exception)
		{
			return $exception;
		}
	}

	/**
	 * Adds a new site to connected Search console account. Can only add site it's running on.
	 *
	 * @param $data
	 * @param $options
	 * @return array | \Exception
	 */
	public function createSites($data, $options)
	{
		$canonicalRootUrl = $this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl');

		// @todo: normalize, or this won't work on an international domain
		// Can use Joomla API punycode converter

		try
		{
			// If the site does not exist in the account, an exception will be thrown.
			$this->searchConsoleService->sites->get($canonicalRootUrl);
		}
		catch (Service\Exception $exception)
		{
			// If we got here, the site does not exist in the account, so we will add it.
			$response = $this->searchConsoleService->sites->add($canonicalRootUrl);

			if (System\Http::RETURN_NO_CONTENT !== $response->getStatusCode())
			{
				return new \Exception($response->getReasonPhrase(), $response->getCode());
			}
		}
		catch (\Exception $e)
		{
			return $e;
		}

		return [
			'status' => System\Http::RETURN_NO_CONTENT
		];
	}

	/**
	 * Verify a site to connected Search console account. Can only verify site it's running on.
	 *
	 * @param $data
	 * @param $options
	 * @return array | \Exception
	 */
	public function createVerifiedsites($data, $options)
	{
		$canonicalRootUrl = $this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl');

		// @todo: normalize, or this won't work on an international domain
		// Can use Joomla API punycode converter

		try
		{
			$site = new SiteVerification\SiteVerificationWebResourceGettokenRequestSite;
			$site->setType('SITE');
			$site->setIdentifier($canonicalRootUrl);

			$token = $this->keystore->get(
				Data\Services::STORE_PREFIX_SITE_VERIFICATION . 'token'
			);

			if (!empty($token))
			{
				$this->logger->debug('Verifying ' . $canonicalRootUrl . ', found token in DB: ' . print_r($token, true));
			}

			if (empty($token))
			{
				$token = $this->getAndStoreNewSiteVerificationToken($site);
				$this->logger->debug('Verifying ' . $canonicalRootUrl . ', obtained and stored new token from G! api: ' . print_r($token, true));
			}

			// we have a token. This will be picked up by a onBeforeCompileHead hook handler which will
			// inject it into the HEAD element of any HTML page.

			// we can now start verifying
			$resource = new SiteVerification\SiteVerificationWebResourceResource();
			$site     = new SiteVerification\SiteVerificationWebResourceResourceSite();
			$site->setType('SITE');
			$site->setIdentifier($canonicalRootUrl);
			$resource->setSite($site);
			$this->logger->debug('Verifying ' . $canonicalRootUrl . ', ready to verify site');
			$webResource = $this->siteVerificationService->webResource->insert('META', $resource);
			$this->logger->debug('Verifying ' . $canonicalRootUrl . ', after site verification, response' . print_r($webResource, true));
		}
		catch (Service\Exception $exception)
		{
			$this->logger->error('Google Search console verification failed. ' . print_r($exception->getErrors(), true));
			foreach ($exception->getErrors() as $error)
			{
				$singleMessage = Wb\arrayGet($error, 'message');
				if (!empty($singleMessage))
				{
					$message = $singleMessage;
					break;
				}
			}
			$message = empty($message)
				? 'Error verifying site with Google. More details in log files on the server.'
				: $message;
			return new \Exception($message, $exception->getCode());
		}
		catch (\Exception $e)
		{
			return $e;
		}

		return [
			'status' => System\Http::RETURN_NO_CONTENT
		];
	}

	/**
	 * Request a new site verification token from Google API and store it to the keystore.
	 *
	 * @param SiteVerification\SiteVerificationWebResourceGettokenRequestSite $site
	 * @return mixed
	 */
	private function getAndStoreNewSiteVerificationToken($site)
	{
		$request = new SiteVerification\SiteVerificationWebResourceGettokenRequest();
		$request->setSite($site);
		$request->setVerificationMethod('META');

		$token = $this->siteVerificationService->webResource->getToken($request);
		$this->logger->debug('Got site verification token:' . print_r($token, true));
		$tokenValue = str_replace(
			'/>',
			' class="4SEO_google_site_verification_tag" />',
			$token->token
		);
		$this->keystore->put(
			Data\Services::STORE_PREFIX_SITE_VERIFICATION . 'token',
			$tokenValue
		);

		return $token;
	}

	/**
	 * A method to fetch properties associated with a Google Search console.
	 *
	 * @param array $options
	 * @return array|\Exception
	 */
	public function getProperties($options)
	{
		try
		{
			$rawProperties = $this->searchConsoleService->sites->listSites();
			if ($rawProperties instanceof \Exception)
			{
				return $rawProperties;
			}

			$this->logger->debug('Properties raw data' . print_r($rawProperties, true));
			$sites = $rawProperties->getSiteEntry();
			if (empty($sites))
			{
				return [
					'data' => [],
					'meta' => [
						'count' => 0,
						'total' => 0
					]

				];
			}

			// filter out domains other than current, except if development mode
			if ('dev' !== WBLIB_Forseo_OP_MODE)
			{
				$canonicalRootUrl = $this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl');
				$sites            = array_filter(
					$sites,
					function ($site) use ($canonicalRootUrl)
					{
						return $this->isAllowedRootUrl($site, $canonicalRootUrl);
					}

				);
			}

			// filter the list: keep only sites with proper permissions
			$sites = array_filter(
				$sites,
				function ($site)
				{
					return in_array(
						$site->getPermissionLevel(),
						[
							'siteFullUser',
							'siteOwner',
							'siteUnverifiedUser'
						]
					);
				}
			);
			$this->logger->debug('Sites with suitable permissions' . print_r($sites, true));
			if (empty($sites))
			{
				return [
					'data' => [],
					'meta' => [
						'count' => 0,
						'total' => 0
					]

				];
			}

			$propertiesOptions = [];
			foreach ($sites as $site)
			{
				$rawPropertyName = $site->getsiteUrl();
				$isDomainLevel   = Wb\startsWith($rawPropertyName, 'sc-domain:');
				$propertyName    = $isDomainLevel
					? Wb\lTrim($rawPropertyName, 'sc-domain:') . ' ( Domain )'
					: $rawPropertyName;
				$verified        = 'siteUnverifiedUser' !== $site->getPermissionLevel();
				if (!$verified)
				{
					$propertyName .= ' ( Not verified )';
				}
				$propertiesOptions[] = [
					'value'       => $rawPropertyName,
					'text'        => $propertyName,
					'verified'    => $verified,
					'domainLevel' => $isDomainLevel
				];
			}

			usort(
				$propertiesOptions,
				function ($o1, $o2)
				{
					if ($o1['text'] < $o2['text'])
					{
						return -1;
					};
					if ($o1['text'] > $o2['text'])
					{
						return 1;
					}
					return 0;
				}
			);

			return [
				'data' => $propertiesOptions,
				'meta' => [
					'count' => count($propertiesOptions),
					'total' => count($propertiesOptions)
				]

			];

		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Whether a passed site root URL matches one of the Search console properties passed as well.
	 *
	 * Takes into account both URL-prefix properties and domain-level properties.
	 *
	 * @param $site
	 * @param $canonicalRootUrl
	 * @return bool
	 */
	public function isAllowedRootUrl($site, $canonicalRootUrl)
	{
		$siteUrl          = $site->getSiteUrl();
		$isDomainProperty = Wb\startsWith(
			$siteUrl,
			'sc-domain:'
		);
		if ($isDomainProperty)
		{
			// domain-level prop are naked hosts, without any trailing slashes
			$canonicalRootUrl = Wb\rTrim(
				$canonicalRootUrl,
				'/'
			);
		}

		$siteUrl = Wb\lTrim(
			$siteUrl,
			'sc-domain:'
		);

		return $isDomainProperty
			? System\Route::hostMatch(
				$siteUrl,
				$canonicalRootUrl,
				false // strict
			)
			: $canonicalRootUrl === $siteUrl;
	}
}
