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

namespace Weeblr\Forseo\Model;

use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Oauth extends Base\Base
{
	public const CONNECTED    = 'connected';
	public const DISCONNECTED = 'disconnected';

	/**
	 * @var Keystore General purpose storage.
	 */
	private $keystore = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * Stores factory instance.
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->keystore = $this->factory->getThe('forseo.keystore');
		$this->logger   = $this->factory->getThe('forseo.logger');
	}

	/**
	 * Retrieves connection status for a given service.
	 *
	 * @param string $service
	 * @return array|\Exception
	 */
	public function status($service)
	{
		try
		{
			$oauthData = $this->keystore->get(Data\Services::STORE_PREFIX_OAUTH . $service);

			return [
				'status' => empty($oauthData)
					? self::DISCONNECTED
					: self::CONNECTED,
				'scope'  => Wb\arrayEnsure(
					explode(
						' ',
						Wb\arrayGet(
							$oauthData,
							['token', 'scope'],
							''
						)
					)
				)
			];

		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Delete any available token for the given service.
	 *
	 * @param string $service
	 * @param bool   $requestStart
	 *
	 * @return array|\Exception
	 */
	public function delete($service, $requestStart = false)
	{
		try
		{
			if (!$requestStart)
			{
				$this->revokeToken($service);
				$this->keystore->delete(
					Data\Services::STORE_PREFIX_OAUTH . $service
				);
				$this->keystore->delete(
					Data\Services::STORE_PREFIX_SITE_VERIFICATION . 'token'
				);
			}

			// always delete any start request nonce that may be lying around.
			$this->keystore->delete(
				Data\Services::STORE_PREFIX_OAUTH . $service . '.request'
			);

			/**
			 * Trigger post-disconnection actions per service.
			 *
			 * @api     forseo
			 * @package 4SEO\action\integrations
			 * @var forseo_integrations_service_disconnected
			 * @since   3.0.3
			 *
			 * @param string $service
			 *
			 * @return bool
			 *
			 */
			$this->factory->getThe('hook')->run(
				'forseo_integrations_service_disconnected',
				$service
			);

			return [];

		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Use Google API to revoke an access token if any is stored.
	 *
	 * @param string $service
	 * @return void | \Exception
	 */
	private function revokeToken($service)
	{
		try
		{
			$oauthData = $this->keystore->get(Data\Services::STORE_PREFIX_OAUTH . $service);
			if (!empty($oauthData))
			{
				$revokeEndpoint = 'https://oauth2.googleapis.com/revoke?token=' . urlencode(Wb\arrayGet($oauthData, ['token', 'access_token']));
				$response       = $this->platform->getHttpClient()
												 ->post(
													 $revokeEndpoint,
													 '',
													 [
														 'Content-type' => 'application/x-www-form-urlencoded',
													 ]
												 );

				$response = $this->processApiResponse(
					$response,
					$service,
					'Error revoking OAuth token with %s: %s',
					$revokeEndpoint
				);

				if ($response instanceof \Exception)
				{
					return $response;
				}
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Store token received from provider.
	 *
	 * @param string $service
	 * @param array  $oauthData
	 * @param bool   $requestStart
	 *
	 * @return array|\Exception
	 */
	public function put($service, $oauthData, $requestStart = false)
	{
		try
		{
			if (!$requestStart)
			{
				$expiresIn               = Wb\ArrayGet($oauthData, ['token', 'expires_in']);
				$oauthData['expires_on'] = time() + $expiresIn;
			}

			$this->logger->custom('oauth', __METHOD__ . ': request start: ' . ($requestStart ? 'Yes' : 'No') . ', model receiving oAuthData to store ' . print_r($oauthData, true));

			// We can store after making sure we have a refresh token
			$this->keystore->put(
				Data\Services::STORE_PREFIX_OAUTH . $service
				. (
				$requestStart
					? '.request'
					: ''
				),
				$oauthData,
				Db\Keystore::DEFAULT_SCOPE,
				Db\Keystore::FORMAT_JSON_ARRAY
			);


			if (!$requestStart)
			{
				// added a valid token, can remove nonce used to request it.
				$this->keystore->delete(
					Data\Services::STORE_PREFIX_OAUTH . $service . '.request'
				);
			}

			return [];

		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Read possibly stored access token from the keystore, and refresh it if needed.
	 * Returns a 401 exception if no valid token found or refresh failed.
	 *
	 * @param string $service
	 * @return string|\Exception
	 */
	public function getAccessToken($service)
	{
		if (!in_array($service, Data\Services::ALLOWED_SERVICES))
		{
			return new \Exception('Invalid service passed trying to refresh an oAuth token: ' . $service, System\Http::RETURN_BAD_REQUEST);
		}

		$oauthData   = $this->keystore->get(Data\Services::STORE_PREFIX_OAUTH . $service);
		$accessToken = Wb\arrayGet($oauthData, ['token', 'access_token']);
		if (empty($oauthData) || empty($accessToken))
		{
			return new \Exception('No or invalid access/refresh tokens found trying to refresh an oAuth token.', System\Http::RETURN_BAD_REQUEST);
		}

		$expiresOn = Wb\arrayGet($oauthData, 'expires_on');
		if (time() > ($expiresOn - 300))
		{
			// refresh 5mn before expiration
			return $this->refreshToken($oauthData);
		}

		return $accessToken;
	}

	/**
	 * Performs an access token refresh, storing the resulting access token to the keystore.
	 *
	 * Returns a 400 exception if passed token is invalid (no access_token or no refresh_token)
	 * Returns a 401 exception if refresh was denied.
	 * Returns a 500 if another error occured
	 *
	 * @param array $oauthData existing token record
	 * @return string|\Exception
	 */
	public function refreshToken($oauthData)
	{
		try
		{
			$service = Wb\arrayGet($oauthData, 'service');
			if (!in_array($service, Data\Services::ALLOWED_SERVICES))
			{
				return new \Exception('Invalid service passed trying to refresh an oAuth token: ' . $service, System\Http::RETURN_BAD_REQUEST);
			}

			$accessToken  = Wb\arrayGet($oauthData, ['token', 'access_token']);
			$refreshToken = Wb\arrayGet($oauthData, ['token', 'refresh_token']);
			if (
				empty($accessToken)
				||
				empty($refreshToken)
			) {
				return new \Exception('No or invalid access/refresh tokens found trying to refresh an oAuth token.', System\Http::RETURN_BAD_REQUEST);
			}

			$refreshEndpoint = Wb\slashTrimJoin(
				$this->factory->getThis('forseo.config', 'integrations')->get('oAuthProxyEndpoint'),
				'refresh',
				'forseo',
				$service
			);
			$refreshEndpoint .= '?key=' . trim($this->factory->getThis('forseo.config', 'system')->get('dlid'))
								. '&googleAppNumber=' . Wb\arrayGet($oauthData, 'googleAppNumber', 0)
								. '&ts=' . time()
								. '&refresh_token=' . $refreshToken;

			$refreshData = [];
			$response    = $this->platform->getHttpClient()
										  ->post(
											  $refreshEndpoint,
											  $refreshData,
											  [
												  'Content-type' => 'application/x-www-form-urlencoded',
											  ]
										  );

			$processedResponse = $this->processApiResponse(
				$response,
				$service,
				'Error refreshing OAuth token with %s: %s',
				$refreshEndpoint
			);

			if ($processedResponse instanceof \Exception)
			{
				return $processedResponse;
			}

			$decodedResponse = json_decode($processedResponse->body, true);
			if (
				empty($decodedResponse)
				||
				empty(Wb\arrayGet($decodedResponse, 'access_token'))
			) {
				return new \Exception('No or invalid access/refresh tokens obtained from provider. Maybe you revoked your access?.', System\Http::RETURN_UNAUTHORIZED);
			}

			$oauthData['token']['access_token'] = $decodedResponse['access_token'];
			$oauthData['token']['expires_in']   = $decodedResponse['expires_in'];

			$this->put(
				$service,
				$oauthData
			);

			return $oauthData['token']['access_token'];
		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return new \Exception('Internal error. See error log file.', System\Http::RETURN_INTERNAL_ERROR);
		}
	}

	/**
	 * Utility to detect error in OAUTH api response and build an exception
	 *  if any.
	 *
	 * @param object $response
	 * @param string $service
	 * @param string $errorMessage
	 * @param string details
	 * @return object|\Exception
	 */
	private function processApiResponse($response, $service, $errorMessage, $details = '')
	{
		if (!$response)
		{
			$error  = 'No response from ' . $service;
			$status = System\Http::RETURN_NOT_FOUND;
		}
		else
		{
			if (System\Http::RETURN_OK !== $response->code)
			{
				$error = 'Got a ' . $response->code . ' response code';
			}
		}

		if (!empty($error))
		{
			$msg = sprintf($errorMessage, $service, $error);
			$this->logger->error(__METHOD__ . ' ' . $msg);
			$this->logger->error(__METHOD__ . ' ' . $details);

			return new \Exception($msg, empty($status) ? System\Http::RETURN_INTERNAL_ERROR : $status);
		}

		return $response;
	}
}
