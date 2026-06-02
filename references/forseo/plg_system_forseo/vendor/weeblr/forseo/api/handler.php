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
 *
 */

namespace Weeblr\Forseo\Api;

use Weeblr\Wblib\Forseo\Api,
	Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * API class, makes API available.
 */
class Handler extends Api\Handler
{
	protected $namespace = 'forseo';
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
		$byPassAuth = function ($apiRequest, $authorization)
		{

			$authorization = [
				'status' => System\Http::RETURN_OK
			];

			return $authorization;
		};

		$defaultOptions = $this->buildRouteOptions(
			[
				'authorizations' => [
					[
						'asset'  => 'com_forseo',
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
					'Weeblr\Forseo\Api\Controller\Config',
					'get',
				],
				$defaultOptions
			)
			->put(
				$this->namespace,
				$this->version,
				'/configs/{name}',
				[
					'Weeblr\Forseo\Api\Controller\Config',
					'put',
				],
				$defaultOptions
			)->patch(
				$this->namespace,
				$this->version,
				'/configs/{name}/{key}',
				[
					'Weeblr\Forseo\Api\Controller\Config',
					'patch',
				],
				$defaultOptions
			)
			//
			// Cron ------------------------------------------------------------------------------
			//
			->get(
				$this->namespace,
				$this->version,
				'/cron/{type}{?id}',
				[
					'Weeblr\Forseo\Api\Controller\Cron',
					'run',
				],
				$this->buildRouteOptions(
					[
						'query_vars_whitelist' => [
							'k'
						],
						'auth_callback'        => $byPassAuth,
					]
				)
			)
			//
			// Performance -----------------------------------------------------------------------
			//
			->post(
				$this->namespace,
				$this->version,
				'/perf/data',
				[
					'Weeblr\Forseo\Api\Controller\Perf',
					'data',
				],
				$this->buildRouteOptions(
					[
						'query_vars_whitelist' => [
							'u',
							'f'
						],
						'auth_type'            => Api\Authorizer::AUTH_PUBLIC
					]
				)
			)->delete(
				$this->namespace,
				$this->version,
				'/perf',
				[
					'Weeblr\Forseo\Api\Controller\Perf',
					'reset',
				],
				$defaultOptions
			//
			// Ping ------------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/kpa',
				function ($request)
				{
					$request->setResponseStatus(
						System\Http::RETURN_NO_CONTENT
					);

					return $request;
				},
				$defaultOptions
			)
			//
			// File ------------------------------------------------------------------------------
			//
			->get(
				$this->namespace,
				$this->version,
				'/file',
				[
					'Weeblr\Forseo\Api\Controller\File',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'path',
						],
						'auth_callback'        => null
					]
				)
			)->put(
				$this->namespace,
				$this->version,
				'/file',
				[
					'Weeblr\Forseo\Api\Controller\File',
					'put',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'path',
						],
						'auth_callback'        => null
					]
				)
			//
			// Platform password validation ------------------------------------------------------
			//
			)->post(
				$this->namespace,
				$this->version,
				'/validpassword',
				[
					'Weeblr\Forseo\Api\Controller\Password',
					'validate',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [],
						'auth_callback'        => null
					]
				)
			)
			//
			// Files -----------------------------------------------------------------------------
			//
			->get(
				$this->namespace,
				$this->version,
				'/files',
				[
					'Weeblr\Forseo\Api\Controller\Files',
					'files',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							],
							[
								'asset'  => 'com_forseo',
								'action' => 'forseo.edit.frontend'
							]
						],
						'query_vars_whitelist' => [
							'path',
							'only',
							'search',
							'count_only'
						],
						'auth_callback'        => null
					]
				)
			)
			//
			// Crawler ---------------------------------------------------------------------------
			//
			->get(
				$this->namespace,
				$this->version,
				'/crawler',
				[
					'Weeblr\Forseo\Api\Controller\Crawler',
					'get',
				],
				$defaultOptions
			)->patch(
				$this->namespace,
				$this->version,
				'/crawler/crawl',
				[
					'Weeblr\Forseo\Api\Controller\Crawler',
					'crawl',
				],
				$defaultOptions
			)->patch(
				$this->namespace,
				$this->version,
				'/crawler',
				[
					'Weeblr\Forseo\Api\Controller\Crawler',
					'patch',
				],
				$defaultOptions
			)->delete(
				$this->namespace,
				$this->version,
				'/crawler',
				[
					'Weeblr\Forseo\Api\Controller\Crawler',
					'delete',
				],
				$defaultOptions
			//
			// Pages -----------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/pages{?id}',
				[
					'Weeblr\Forseo\Api\Controller\Pages',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							],
							[
								'asset'  => 'com_forseo',
								'action' => 'forseo.edit.frontend'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'order_by',
							'status',
							'search',
							'exact_search',
							'with_meta',
							'with_aliases',
							'with_perf',
							'filter_meta',
							'filter_canonical',
							'filter_sort',
							'filter_lang',
							'filter_perf',
							'count_only'
						],
						'auth_callback'        => null
					]
				)
			)->patch(
				$this->namespace,
				$this->version,
				'/pages/{id}',
				[
					'Weeblr\Forseo\Api\Controller\Pages',
					'patch',
				],
				$this->buildRouteOptions(
					[
						'authorizations' => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							],
							[
								'asset'  => 'com_forseo',
								'action' => 'forseo.edit.frontend'
							]
						],
						'auth_callback'  => null
					]
				)
			)->delete(
				$this->namespace,
				$this->version,
				'/pages',
				[
					'Weeblr\Forseo\Api\Controller\Pages',
					'delete',
				],
				$defaultOptions
			//
			// Meta data -------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/meta/{id}',
				[
					'Weeblr\Forseo\Api\Controller\Meta',
					'get',
				],
				$defaultOptions
			)->patch(
				$this->namespace,
				$this->version,
				'/meta',
				[
					'Weeblr\Forseo\Api\Controller\Meta',
					'import',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'from'
						],
						'auth_callback'        => null
					]
				)
			)->patch(
				$this->namespace,
				$this->version,
				'/meta/{id}/{item}',
				[
					'Weeblr\Forseo\Api\Controller\Meta',
					'patch',
				],
				$this->buildRouteOptions(
					[
						'authorizations' => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							],
							[
								'asset'  => 'com_forseo',
								'action' => 'forseo.edit.frontend'
							]
						],
						'auth_callback'  => null
					]
				)
			)->patch(
				$this->namespace,
				$this->version,
				'/meta/{id}/data/{item}',
				[
					'Weeblr\Forseo\Api\Controller\Meta',
					'patchData',
				],
				$this->buildRouteOptions(
					[
						'authorizations' => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							],
							[
								'asset'  => 'com_forseo',
								'action' => 'forseo.edit.frontend'
							]
						],
						'auth_callback'  => null
					]
				)
			)->delete(
				$this->namespace,
				$this->version,
				'/meta/{id}',
				[
					'Weeblr\Forseo\Api\Controller\Meta',
					'delete',
				],
				$defaultOptions
			//
			// Links -----------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/links{?id}',
				[
					'Weeblr\Forseo\Api\Controller\Links',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'order_by',
							'status',
							'search',
							'exact_search',
							'count_only',
							'target',
							'type',
						],
						'auth_callback'        => null
					]
				)
			)->get(
				$this->namespace,
				$this->version,
				'/links/{id}/referrers',
				[
					'Weeblr\Forseo\Api\Controller\Referrers',
					'links',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'search',
							'exact_search',
							'count_only',
						],
						'auth_callback'        => null
					]
				)
			)->delete(
				$this->namespace,
				$this->version,
				'/links',
				[
					'Weeblr\Forseo\Api\Controller\Links',
					'delete',
				],
				$defaultOptions
			//
			// Aliases ---------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/aliases',
				[
					'Weeblr\Forseo\Api\Controller\Aliases',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'search',
							'exact_search',
							'count_only',
						],
						'auth_callback'        => null
					]
				)
			)->patch(
				$this->namespace,
				$this->version,
				'/aliases{?id}',
				[
					'Weeblr\Forseo\Api\Controller\Aliases',
					'patch',
				],
				$this->buildRouteOptions(
					[
						'authorizations' => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'auth_callback'  => null
					]
				)
			)->delete(
				$this->namespace,
				$this->version,
				'/aliases',
				[
					'Weeblr\Forseo\Api\Controller\Aliases',
					'delete',
				],
				$defaultOptions
			//
			// Errors ----------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/errors{?id}',
				[
					'Weeblr\Forseo\Api\Controller\Errors',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'order_by',
							'status',
							'search',
							'exact_search',
							'count_only',
							'last_hit'
						],
						'auth_callback'        => null
					]
				)
			)->get(
				$this->namespace,
				$this->version,
				'/errors/{id}/referrers',
				[
					'Weeblr\Forseo\Api\Controller\Referrers',
					'errors',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'search',
							'exact_search',
							'count_only',
						],
						'auth_callback'        => null
					]
				)
			)->delete(
				$this->namespace,
				$this->version,
				'/errors',
				[
					'Weeblr\Forseo\Api\Controller\Errors',
					'delete',
				],
				$defaultOptions
			//
			// Rules -----------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/rules{?id}',
				[
					'Weeblr\Forseo\Api\Controller\Rules',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'order_by',
							'rulesType', // redirects | replacer | seo
							'type',
							'search',
							'exact_search',
							'count_only',
							'format'
						],
						'auth_callback'        => null
					]
				)
			)->put(
				$this->namespace,
				$this->version,
				'/rules/{id}',
				[
					'Weeblr\Forseo\Api\Controller\Rules',
					'put',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [],
						'auth_callback'        => null
					]
				)
			)->patch(
				$this->namespace,
				$this->version,
				'/rules',
				[
					'Weeblr\Forseo\Api\Controller\Rules',
					'import',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'page',
							'per_page',
							'from'
						],
						'auth_callback'        => null
					]
				)
			)->patch(
				$this->namespace,
				$this->version,
				'/rules/{id}',
				[
					'Weeblr\Forseo\Api\Controller\Rules',
					'patch',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [],
						'auth_callback'        => null
					]
				)
			)->post(
				$this->namespace,
				$this->version,
				'/rules',
				[
					'Weeblr\Forseo\Api\Controller\Rules',
					'post',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [],
						'auth_callback'        => null
					]
				)
			)->delete(
				$this->namespace,
				$this->version,
				'/rules',
				[
					'Weeblr\Forseo\Api\Controller\Rules',
					'delete',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'source'
						],
						'auth_callback'        => null
					]
				)
			)->put(
				$this->namespace,
				$this->version,
				'/rules/ordering',
				[
					'Weeblr\Forseo\Api\Controller\Rules',
					'orderRules',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
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
					'Weeblr\Forseo\Api\Controller\Extensions',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
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
					Controller\Extensions::class,
					'patch',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [],
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
					'Weeblr\Forseo\Api\Controller\Languages',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations' => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'auth_callback'  => null
					]
				)
			//
			// Platform users groups -------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/usersgroups',
				[
					'Weeblr\Forseo\Api\Controller\Usersgroups',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations' => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'auth_callback'  => null
					]
				)
			//
			// Platform menu items ---------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/menu',
				[
					'Weeblr\Forseo\Api\Controller\Menu',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations' => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'auth_callback'  => null
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
					Controller\Platform::class,
					'patch',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
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
					'Weeblr\Forseo\Api\Controller\Categories',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
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
			// Sitemaps --------------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/sitemaps/{type}',
				[
					'Weeblr\Forseo\Api\Controller\Sitemaps',
					'get',
				],
				$defaultOptions
			)->delete(
				$this->namespace,
				$this->version,
				'/sitemaps/{type}',
				[
					'Weeblr\Forseo\Api\Controller\Sitemaps',
					'delete'
				],
				$defaultOptions
			//
			// Global status ---------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/status',
				[
					'Weeblr\Forseo\Api\Controller\Status',
					'get',
				],
				$defaultOptions
			//
			// Geo coordinates -------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/geo',
				[
					'Weeblr\Forseo\Api\Controller\Geo',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'streetAddress',
							'postalCode',
							'addressLocality',
							'addressCountry',
						],
						'auth_callback'        => null
					]
				)
			//
			// Platform custom fields definitions ------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/customfields',
				[
					'Weeblr\Forseo\Api\Controller\Customfields',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'exact_match',
							'context'
						],
						'auth_callback'        => null
					]
				)
			//
			// Frontend edit ---------------------------------------------------------------------
			//
			)->get(
				$this->namespace,
				$this->version,
				'/fe/edit/{id}',
				[
					'Weeblr\Forseo\Api\Controller\Feedit',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'forseo.edit.frontend'
							]
						],
						'query_vars_whitelist' => [],
						'auth_callback'        => null
					]
				)
			)
			//
			// Oauth data ------------------------------------------------------------------------
			//
			->get(
				$this->namespace,
				$this->version,
				'/oauth/{service}',
				[
					'Weeblr\Forseo\Api\Controller\Oauth',
					'get',
				],
				$defaultOptions
			)->delete(
				$this->namespace,
				$this->version,
				'/oauth/{service}',
				[
					'Weeblr\Forseo\Api\Controller\Oauth',
					'delete'
				],
				$defaultOptions
			)->delete(
				$this->namespace,
				$this->version,
				'/oauth/{service}/request',
				[
					'Weeblr\Forseo\Api\Controller\Oauth',
					'deleteRequest'
				],
				$defaultOptions
			)->put(
				$this->namespace,
				$this->version,
				'/oauth/{service}',
				[
					'Weeblr\Forseo\Api\Controller\Oauth',
					'put',
				],
				$this->buildRouteOptions(
					[
						'query_vars_whitelist' => [],
						'auth_type'            => Api\Authorizer::AUTH_PUBLIC
					]
				)
			)->put(
				$this->namespace,
				$this->version,
				'/oauth/{service}/request',
				[
					'Weeblr\Forseo\Api\Controller\Oauth',
					'putRequest',
				],
				$defaultOptions
			)
			//
			// External services -----------------------------------------------------------------
			//
			->get(
				$this->namespace,
				$this->version,
				'/services/{service}/{type}',
				[
					'Weeblr\Forseo\Api\Controller\Services',
					'get',
				],
				$defaultOptions
			)->post(
				$this->namespace,
				$this->version,
				'/services/{service}/{type}',
				[
					'Weeblr\Forseo\Api\Controller\Services',
					'post',
				],
				$defaultOptions
			)->put(
				$this->namespace,
				$this->version,
				'/services/{service}/{type}',
				[
					'Weeblr\Forseo\Api\Controller\Services',
					'put',
				],
				$defaultOptions
			)
			//
			// Google Search Console data --------------------------------------------------------
			//
			->get(
				$this->namespace,
				$this->version,
				'/services/{service}/data/{type}',
				[
					'Weeblr\Forseo\Api\Controller\Servicesdata',
					'get',
				],
				$this->buildRouteOptions(
					[
						'authorizations'       => [
							[
								'asset'  => 'com_forseo',
								'action' => 'core.manage'
							]
						],
						'query_vars_whitelist' => [
							'url',
							'startDate',
							'endDate',
							'dimensions',
							'targetType',
							'dimensionFilterGroups',
							'aggregationType',
							'rowLimit',
							'startRow',
							'dataState',
							'page',
							'forceRefresh'
						],
						'auth_callback'        => null
					]
				)
			);

		return $this;
	}
}
