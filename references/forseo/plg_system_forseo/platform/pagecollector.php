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

namespace Weeblr\Forseo\Platform;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Platform\Helpers;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Collects page data per platform.
 *
 * @package Weeblr\Forseo\Platform
 */
class Pagecollector extends Base\Base
{
	/**
	 * @var Uri\Uri Stores current request URI.
	 */
	private $uri = null;

	/**
	 * @var Helpers\Route Helper for anything routing-related.
	 */
	private $routeHelper = null;

	public function __construct()
	{
		parent::__construct();
		$this->uri         = Uri\Uri::getInstance();
		$this->routeHelper = $this->factory->getA(Helpers\Route::class);
	}

	/**
	 * Augment known data about the current request.
	 * Should only be called for suitable requests, ie GET, guest user, frontend, html.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	public function onAfterRoute(Data\Page $pageData)
	{
		$pageData = $this->collectRequestUrls($pageData);
		$pageData = $this->collectRequestVars($pageData);

		$pageData
			->set(
				[
					'enabled' => Data\Url::ENABLED,
					'scheme'  => $this->uri->getScheme(),
					'host'    => $this->uri->getHost(),
					'lang'    => $this->platform->getCurrentLanguageTag()
				]
			)->timestamp('crawled_at');

		return $pageData;
	}

	/**
	 * Augment known data about the current request.
	 * Should only be called for suitable requests, ie GET, guest user, frontend, html.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	public function onAfterRender(Data\Page $pageData)
	{
		$status = $pageData->get('status');
		if (
			empty($status)
			||
			$status === System\Http::RETURN_OK
		) {
			$platformStatus = $this->platform->getHttpStatus();
			if (
				!empty($platformStatus)
				&&
				$platformStatus !== System\Http::RETURN_OK
			) {
				$status = $platformStatus;
			}
		}

		$pageData->set('status', $status);
		if (!$this->isSuccessResponse($status))
		{
			$pageData->set('ignore', true);

			return $pageData;
		}

		return $pageData;
	}

	/**
	 * Collects various URLs of the current request.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	public function collectRequestUrls($pageData)
	{
		// raw url
		$url = $this->factory
			->getA(System\Php::class)
			->getProtectedProperty('\Joomla\CMS\Uri\Uri', 'uri', $this->uri);

		// Browsers will url encode non-latin URLs
		// Also some sites use a mixture of URL encoded and Unicode slugs
		// which are then not recognized as same URL when crawling.
		// Therefore always URL decode to have a single version of all URLs.
		$url = rawurldecode($url);
		$url = preg_replace('/\s/u', '%20', $url);

		// Assign main URL to the current page data. Will be shortened if needed by Data\Page object.
		$pageData->set(
			[
				'full_url' => $this->platform->normalizeUrl(
					System\Route::removeQueryVarFromUrl(
						$url,
						FORSEO_CRAWLER_CDN_BUST_VAR
					)
				)
			]
		);

		return $pageData;
	}

	/**
	 * Collects internal non-sef variables associated with
	 * the current request.
	 *
	 * @param Data\Page $pageData
	 *
	 * @return Data\Page
	 * @throws \Exception
	 */
	public function collectRequestVars($pageData)
	{
		$appInput   = $this->platform->getHttpInput();
		$pageHelper = $this->factory->getThe('forseo.pageHelper');

		$inputVars = $pageHelper->cleanQueryVar(
			$appInput->getArray(),
			FORSEO_CRAWLER_CDN_BUST_VAR
		);

		$queryVars = $pageHelper->cleanQueryVar(
			$this->uri->getQuery(true),
			FORSEO_CRAWLER_CDN_BUST_VAR
		);

		$pathVars          = $this->factory
			->getA(System\Php::class)
			->arrayDiffAssocRecursive(
				$inputVars,
				$queryVars
			);
		$filteredQueryVars = $pageHelper->filterQueryVarsForId($queryVars);
		$Itemid            = $appInput->getInt('Itemid', 0);

		// Figure out GET only variables
		$cookies = $appInput->cookie->getArray();
		$cookies = empty($cookies)
			? []
			: $cookies;

		// POST must be subsstracted even if request is not POST due to kinda of
		// bug in Joomla: if any code before parsing (ie in an onAfterInitialise handler)
		// tries to use $app->input->post, this will cause the post input to be created with
		// the content of $_REQUEST. But $_REQUEST may contain query variables and so these
		// end up in $app->input->post even if request is not POST.
		// The language filter does this (it counts the number of post variables) and likely
		// other 3rd-party plugins.
		// Don't see how this can be prevented at Joomla level, at least no in a B/C way, as it is
		// expected that ->input is created with the content of $_REQUEST.
		$post = $appInput->post->getArray();
		$post = empty($post)
			? []
			: $post;

		$fromInput = array_diff_key(
			array_merge(
				$inputVars,
				$filteredQueryVars
			),
			$cookies,
			$post
		);

		$extension = strtolower(
			Wb\arrayGet($fromInput, 'option', '')
		);
		$extension = $this->getVarFromMenuIfEmpty(
			'option',
			$extension,
			$Itemid
		);

		$view = Wb\arrayGet($fromInput, 'view', '');
		$view = $this->getVarFromMenuIfEmpty(
			'view',
			$view,
			$Itemid
		);

		$layout = Wb\arrayGet($fromInput, 'layout', '');
		$layout = $this->getVarFromMenuIfEmpty(
			'layout',
			$layout,
			$Itemid
		);

		// id can be an array, for instance com_tags
		$id = Wb\arrayGet($fromInput, 'id', '');
		$id = $this->getVarFromMenuIfEmpty(
			'id',
			$id,
			$Itemid
		);

		return $pageData->set(
			[
				'non_sef_vars' => $pathVars,
				'query'        => $queryVars,
				'page'         => $Itemid,
				'input_vars'   => $fromInput,
				'extension'    => $extension,
				'view'         => $view,
				'layout'       => $layout,
				'item_id'      => $id,
			]
		);
	}

	/**
	 * Whether current request has a success HTTP status.
	 *
	 * @param int $status
	 *
	 * @return bool
	 */
	private function isSuccessResponse($status)
	{
		return
			empty($status)
			||
			System\Http::isSuccess($status);
	}

	/**
	 * Search a menu item query for the value of a variable
	 * if that variable is empty.
	 *
	 * @param string $varName
	 * @param mixed  $varValue
	 * @param int    $menuItemId
	 *
	 * @return mixed|string|null
	 */
	private function getVarFromMenuIfEmpty($varName, $varValue, $menuItemId)
	{
		return empty($varValue)
			? $this->routeHelper->getVarFromMenuItem(
				$menuItemId,
				$varName,
				''
			)
			: $varValue;
	}
}
