<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 *
 */

namespace Weeblr\Forsef\Api;

use Weeblr\Wblib\Forsef\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * API class, makes API available.
 */
class Handler extends Api\Handler
{
	protected $namespace = 'forsef';
	protected $version   = 'v1';

	/**
	 * Register all routes with the API layer.
	 */
	public function register()
	{
		/**
		 * Set this function as the "auth_callback" parameter
		 * when add a router handler to bypass auhtorization.
		 * DANGEROUS: only use for dev!
		 *
		 * @param $apiRequest
		 * @param $authorization
		 *
		 * @return array
		 */
		$byPassAuth = function ($apiRequest, $authorization) {

			$authorization = [
				'status' => 200 // System\Http::RETURN_OK
			];

			return $authorization;
		};

		$defaultOptions = $this->buildRouteOptions(
			[
				'authorizations' => [
					[
						'asset'  => 'com_forsef',
						'action' => 'core.manage'
					]
				],
				'auth_callback'  => null
			]
		);

		$this->api
			//
			// Configuration ---------------------------------------------------------------------
			//
			->get(
				$this->namespace,
				$this->version,
				'/configs/{name}',
				[
					'Weeblr\Forsef\Api\Controller\Config',
					'get',
				],
				$defaultOptions
			)
			->put(
				$this->namespace,
				$this->version,
				'/configs/{name}',
				[
					'Weeblr\Forsef\Api\Controller\Config',
					'put',
				],
				$defaultOptions
			)->patch(
				$this->namespace,
				$this->version,
				'/configs/{name}/{key}',
				[
					'Weeblr\Forsef\Api\Controller\Config',
					'patch',
				],
				$defaultOptions
			)->patch(
				$this->namespace,
				$this->version,
				'/configs',
				[
					'Weeblr\Forsef\Api\Controller\Config',
					'import',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'from'
						],
						'auth_callback'        => null
					]
				)
			//
			// Ping ------------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/kpa',
				function ($request) {
					$request->setResponseStatus(
						204 // System\Http::RETURN_NO_CONTENT
					);

					return $request;
				},
				$defaultOptions
			//
			// URL pairs -------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/urls{?id}',
				[
					'Weeblr\Forsef\Api\Controller\Urls',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'order_by',
							'search',
							'exact_search',
							'filter_custom',
							'count_only',
							'with_duplicates_count',
							'duplicates_only',
							'sef',
						],
						'auth_callback'        => null
					]
				)
			)->post(
				$this->namespace,
				$this->version,
				'/urls',
				[
					'Weeblr\Forsef\Api\Controller\Urls',
					'post',
				],
				$defaultOptions
			)->patch(
				$this->namespace,
				$this->version,
				'/urls/{id}',
				[
					'Weeblr\Forsef\Api\Controller\Urls',
					'patch',
				],
				$defaultOptions
			)->patch(
				$this->namespace,
				$this->version,
				'/urls',
				[
					'Weeblr\Forsef\Api\Controller\Urls',
					'import',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'from',
							'page',
							'per_page',
							'custom_only'
						],
						'auth_callback'        => null
					]
				)
			)->delete(
				$this->namespace,
				$this->version,
				'/urls',
				[
					'Weeblr\Forsef\Api\Controller\Urls',
					'delete',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'with_duplicates',
							'with_custom',
							'with_preserve_imported_count'
						],
						'auth_callback'        => null
					]
				)
			//
			// Platform --------------------------------------------------------------------------
			//
			)->patch(
				$this->namespace,
				$this->version,
				'/platform',
				[
					'Weeblr\Forsef\Api\Controller\Platform',
					'patch',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [],
						'auth_callback'        => null
					]
				)
			//
			// Extensions ------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/extensions/{type}',
				[
					'Weeblr\Forsef\Api\Controller\Extensions',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'search',
							'count_only',
						],
						'auth_callback'        => null
					]
				)
			)->patch(
				$this->namespace,
				$this->version,
				'/extensions/{type}',
				[
					'Weeblr\Forsef\Api\Controller\Extensions',
					'patch',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [],
						'auth_callback'        => null
					]
				)
			//
			// Categories ------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/categories',
				[
					'Weeblr\Forsef\Api\Controller\Categories',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'search',
							'language',
							'extension',
							'format'
						],
						'auth_callback'        => null
					]
				)
			//
			// Platform languages ----------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/languages',
				[
					'Weeblr\Forsef\Api\Controller\Languages',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations' => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'auth_callback'  => null
					]
				)
			//
			// Global status ---------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/status',
				[
					'Weeblr\Forsef\Api\Controller\Status',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forsef',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'force_refresh'
						],
						'auth_callback'        => null
					]
				)
			//
			// Statistics ------------------------------------------------------------------------
			//
			)->delete(
				$this->namespace,
				$this->version,
				'/stats',
				[
					'Weeblr\Forsef\Api\Controller\Stats',
					'delete',
				],
				$defaultOptions
			);

		return $this;
	}
}
