<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 *
 */

namespace Weeblr\Wblib\Forsef\Api;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\V_SH4_4249\Api\Controller;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Parse and manage the named parameters of an API request.
 *
 */
class Helper extends Base\Base
{
	/**
	 * Builds the root of any API call URL. Will default to:
	 *   /index.php/apiSlug on frontend
	 *   /index.php?apiSlug in admin
	 *
	 * if $endPointType is set to auto. If set to folder or file,
	 * the first or second option will be applied, respectively.
	 *
	 * This is to increase chances to avoid blocking by various security and redirect mechanisms.
	 *
	 * On frontend, /index.php?xxx will often be rewritten to /, even if with a query string
	 * In admin, AdminTools will block /administrator/index.php/?apiSlug
	 *
	 * On frontend or admin, various mod_security setup will block /?apislig=xxx,
	 * so having /index.php/apiSlug?xxx does help.
	 *
	 * @param           $apiSlug
	 * @param   bool    $bypassLanguageFilter
	 * @param   string  $endPointType  // file | folder | auto
	 * @param   string  $format  Optional format query var
	 *
	 * @return string
	 */
	public function buildBaseUrl($apiSlug, $bypassLanguageFilter = true, $endPointType = 'auto', $format = null)
	{
		if ('auto' == $endPointType)
		{
			$endPointType = $this->platform->isFrontend()
				? 'folder'
				: 'file';
		}

		return '/index.php' . ('file' === $endPointType ? '?' : '/' . $apiSlug . '?')
			. ($bypassLanguageFilter ? 'nolangfilter=1' : '')
			. ($format ? '&format=' . $format : '')
			. '&' . $apiSlug . '=';
	}

	/**
	 * Handles an API request, calling the controller method that the route
	 * found suited for that request.
	 *
	 * @param $controllerInstanceOrClass
	 * @param $controllerMethod
	 * @param $request
	 *
	 * @return mixed
	 */
	public function handle($controllerInstanceOrClass, $controllerMethod, $request)
	{
		$options = array_merge(
			$request->getParameters()->getArray(),
			$request->getQuery()->getArray()
		);

		$controller = is_object($controllerInstanceOrClass)
			? $controllerInstanceOrClass
			: $this->factory->getA($controllerInstanceOrClass);
		$data       = $controller->{$controllerMethod}($request, $options);

		if (
			$data instanceof \Throwable
			||
			$data instanceof \Exception
		)
		{
			$request->setResponseStatus(
				$data->getCode()
			)->addResponseErrors(
				array(
					array(
						'code'    => $data->getCode(),
						'message' => $data->getMessage()
					)
				)
			)->addResponseMeta(
				array(
					'count' => 0,
					'total' => 0
				)
			);

			return $request;
		}

		$data         = Wb\arrayEnsure($data);
		$data['data'] = Wb\arrayGet($data, 'data', array());
		// legacy
		$data['count'] = Wb\arrayGet($data, 'count', 0);
		$data['total'] = Wb\arrayGet($data, 'total', 0);
		// meta data
		$meta         = array(
			'count' => $data['count'],
			'total' => $data['total']
		);
		$responseMeta = Wb\arrayGet($data, 'meta', array());
		$meta         = array_merge(
			$meta,
			$responseMeta
		);

		// links
		$data['links'] = Wb\arrayGet($data, 'links', array());

		$responseLinks = $this->getPagination(
			$request,
			$options,
			$data['total']
		);
		$responseLinks = array_merge(
			$responseLinks,
			$data['links']
		);

		$status = Wb\arrayGet($data, 'status', System\Http::RETURN_OK);
		unset($data['status']);
		$runAfterResponse = Wb\arrayGet($data, 'runAfterResponse', null);
		unset($data['runAfterResponse']);
		$request
			->setResponseStatus(
				$status
			)->setResponseData(
				$data['data']
			)->setRunAfterResponse(
				$runAfterResponse
			)->addResponseLinks(
				$responseLinks
			)->addResponseMeta(
				$meta
			);

		if (
			System\Http::RETURN_OK != $status
			&&
			!empty($data['errors'])
		)
		{
			$request->setResponseErrors(
				$data['errors']
			);
		}

		return $request;
	}

	/**
	 * Computes an array holding links to current, next, prev, first and last
	 * pages of a list.
	 *
	 * @param   Request  $request
	 * @param   array    $options  Parameters passed in request.
	 * @param   int      $total    Total number of items existing.
	 *
	 * @return array
	 */
	public function getPagination($request, $options, $total)
	{
		// at least link to self
		$responseLinks = array(
			'self' => $request->routeLink(),
		);

		$perPage    = (int) Wb\arrayGet($options, 'per_page', 10);
		$perPage    = min(100, $perPage);
		$perPage    = empty($pePage) ? 10 : $perPage;
		$totalPages = ceil($total / $perPage);

		$page = (int) Wb\arrayGet($options, 'page', 1);
		// validate page requested
		$page = $page < 1 ? 1 : $page;

		// first
		if ($totalPages > 1 && $page > 1)
		{
			$responseLinks['first'] = $request->routeLink(
				null,
				array(
					'page'     => 1,
					'per_page' => $perPage
				)
			);
		}

		// next
		if ($page < $totalPages)
		{
			$responseLinks['next'] = $request->routeLink(
				null,
				array(
					'page'     => $page + 1,
					'per_page' => $perPage
				)
			);
		}

		// previous
		if ($page > 1)
		{
			$responseLinks['prev'] = $request->routeLink(
				null,
				array(
					'page'     => $page - 1,
					'per_page' => $perPage
				)
			);
		}

		// last
		if ($totalPages > 1 && $page < $totalPages)
		{
			$responseLinks['last'] = $request->routeLink(
				null,
				array(
					'page'     => $totalPages,
					'per_page' => $perPage
				)
			);
		}

		return $responseLinks;
	}
}
