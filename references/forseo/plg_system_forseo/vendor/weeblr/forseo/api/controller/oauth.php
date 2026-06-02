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

namespace Weeblr\Forseo\Api\Controller;

use Weeblr\Forseo\Model;
use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Oauth extends Api\Controller
{
	private $allowedServices = [
		'google.search_console',
		'google.analytics_v4',
		'google.analytics_u',
		'matomo.analytics'
	];

	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param array $options
	 *
	 * @return array| \Exception
	 */
	public function get($request, $options)
	{
		$service = Wb\arrayGet($options, 'service');
		$valid   = $this->validateService($service);
		if ($valid instanceof \Throwable)
		{
			return $valid;
		}

		return [
			'data'  => $this->factory
				->getA(Model\Oauth::class)
				->status($service),
			'count' => 1,
			'total' => 1,
		];
	}

	/**
	 * Update OAuth token received from provider into keystore.
	 *
	 * @param      $request
	 * @param      $options
	 * @param bool $requestStart
	 *
	 * @return array | \Exception
	 */
	public function put($request, $options, $requestStart = false)
	{
		$service = Wb\arrayGet($options, 'service');
		$valid   = $this->validateService($service);
		if ($valid instanceof \Throwable)
		{
			return $valid;
		}

		try
		{
			$oauthData = $request->getBody();
			if (!$requestStart)
			{
				$this->validateToken($oauthData);
			}

			$this->factory
				->getA(Model\Oauth::class)
				->put(
					$service,
					$oauthData,
					$requestStart
				);
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception('Full error message has been stored to the 4SEO log file on the server', 500);
		}

		return [
			'status' => System\Http::RETURN_NO_CONTENT
		];
	}

	/**
	 * * Wrapper around put for an OAuth connection start.
	 *
	 * @param $request
	 * @param $options
	 * @return array|\Exception
	 */
	public function putRequest($request, $options)
	{
		return $this->put($request, $options, true);
	}

	/**
	 * Computes incoming OAuth data signature, computed with the secret key provided in the OAuth request,
	 * with the incoming data signature computed with current configured secret key.
	 *
	 * @param $oauthData
	 * @return void
	 * @throws \Exception
	 */
	private function validateToken($oauthData)
	{
		// check signature
		$incomingSignature = Wb\arrayGet($oauthData, 'sig');
		unset($oauthData['sig']);
		$updateKey         = trim($this->factory->getThis('forseo.config', 'system')->get('dlid'));
		$computedSignature = hash(
			'sha256',
			$updateKey . json_encode($oauthData)
		);

		if ($incomingSignature !== $computedSignature)
		{
			$this->factory->getThe('forseo.logger')->custom(
				'oauth',
				__METHOD__ . " Invalid OAuth token signature, incoming: " . $incomingSignature . ', computed: ' . $computedSignature
				. "\noauthData\n" . print_r($oauthData, true)
				. "\nUpdate key\n" . print_r($updateKey, true)
				. "\nSignature base\n" . print_r($updateKey . json_encode($oauthData), true)
			);

			throw new \Exception('Invalid signature', 400);
		}

		// check nonce, that is: we're the ones who started the request
		$storedNonce = $this->factory->getThe('forseo.keystore')->get(
			Data\Services::STORE_PREFIX_OAUTH . Wb\arrayGet($oauthData, 'service') . '.request'
		);

		$incomingNonce = Wb\arrayGet($oauthData, 'nonce');

		if (
			empty($incomingNonce)
			||
			$incomingNonce !== $storedNonce
		) {
			$this->factory->getThe('forseo.logger')->custom('oauth', __METHOD__ . "Invalid OAuth nonce \n" . print_r($oauthData, true));

			throw new \Exception('Invalid nonce', 400);
		}
	}

	/**
	 * Delete Oauth connection information for a provider.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return array | \Throwable
	 */
	public function delete($request, $options, $requestStart = false)
	{
		$service = Wb\arrayGet($options, 'service');
		$valid   = $this->validateService($service);
		if ($valid instanceof \Throwable)
		{
			return $valid;
		}

		return [
			'status' => System\Http::RETURN_NO_CONTENT,
			'data'   => $this->factory
				->getA(Model\Oauth::class)
				->delete(
					$service,
					$requestStart
				),
			'count'  => 1,
			'total'  => 1
		];
	}

	/**
	 * Wrapper around delete for an OAuth connection start.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return array
	 */
	public function deleteRequest($request, $options)
	{
		return $this->delete(
			$request,
			$options,
			true
		);
	}

	/**
	 * Check the requested service name is in allowed range.
	 *
	 * @param string $service
	 *
	 * @return bool | \Exception
	 */
	private function validateService($service)
	{
		if (!in_array(
			$service,
			$this->allowedServices
		))
		{
			return new \Exception(
				'Invalid oauth service name ' . print_r($service, true),
				System\Http::RETURN_NOT_FOUND
			);
		}

		return true;
	}
}
