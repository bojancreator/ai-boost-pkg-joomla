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

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Base API class, used by clients to interface with userland models.
 *
 * Specifying routes/handlers is done in the register() method.
 * General syntax:
 * $this->api
 *   ->get(
 *     $this->namespace,
 *     path,
 *     callback,
 *     $options
 * )
 *   ->post()
 *   ->put()
 *   ->delete()
 *   ->patch()
 *
 * namespace: string
 * path: string, with named variables ie: /pages, /pages/{id}, /pages/{id}/meta/{description}
 * callback: function/method to call when the route is triggered, multiple syntaxes:
 *   - function: $callbackName: called directly
 *   - string: 'callbackName': called directly
 *   - array: [$object, 'methodName']: called directly
 *   - array: [ 'className', 'methodName']: the className is supposed to be a descendant of Controller.
 *     With that syntax, the className:methodName is not instantiated and called directly. Instead, an instance of a
 * Helper is created and the Helper method handle($controllerName, $methodName, $request) is called. The
 * Handler::handle() method manages most boilerplate for the Request and Response objects and the Controller method
 * only needs to return the data array. This data array will be wrapped in a Response object by the Helper and the
 * Controller does not need to concern itself with this, it only needs to fetch/manage the data.
 *
 * When called directly (ie not through the Helper::handle() method, the callback is passed a Request object
 * and must return an updated Request object, likely containing a Response object.
 *
 */
abstract class Handler extends Base\Base
{
	/**
	 * @var Api The unique api object.
	 */
	protected $api;

	/**
	 * @var string Namespace fo the client.
	 */
	protected $namespace = '';

	/**
	 * @var string API version for the client.
	 */
	protected $version = 'v1';

	/**
	 * @var array Default set of access options, used when defining routes.
	 */
	protected $defaultApiOptions = [];

	/**
	 * Handler constructor.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->api = $this->factory->getThe('api');

		if (Wb\arrayisTruthy($options, 'namespace'))
		{
			$this->namespace = Wb\arrayGet($options, 'namespace');
		}
		if (Wb\arrayisTruthy($options, 'version'))
		{
			$this->namespace = Wb\arrayGet($options, 'version');
		}

		if (empty($this->namespace) || empty($this->version))
		{
			throw new \Exception(__METHOD__ . ': Missing namespace or version trying to create an API handler');
		}

		if (Wb\arrayisTruthy($options, 'defaultApiOptions'))
		{
			$this->defaultApiOptions = Wb\arrayGet($options, 'defaultApiOptions');
		}
	}

	/**
	 * Register all routes with the API layer.
	 */
	abstract public function register();

	/**
	 * A lits of standard options for a route, that should always be present.
	 *
	 * @return array
	 */
	protected function getDefaultRouteOptions()
	{
		return array(
			'version'   => $this->version,
			'auth_type' => Authorizer::AUTH_LOG_IN,
		);
	}

	/**
	 * Build a list of options for a router, merging default options and passed ones.
	 *
	 * @param   array  $routeOptions
	 *
	 * @return array
	 */
	protected function buildRouteOptions($routeOptions = array())
	{
		return Wb\arrayMerge(
			$this->getDefaultRouteOptions(),
			$routeOptions
		);
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
	 * @deprecated Use Helper::getPagination()
	 */
	protected function getPagination($request, $options, $total)
	{
		return $this->factory->getA(Helper::class)->getPagination($request, $options, $total);
	}

	/**
	 * Set this function as the "auth_callback" parameter
	 * when add a router handler to bypass authorization.
	 * DANGEROUS: only use for dev!
	 *
	 * @param   Request  $apiRequest
	 * @param   array    $authorization
	 *
	 * @return array
	 */
	public function bypassAuthorization($apiRequest, $authorization)
	{
		$authorization = array(
			'status' => System\Http::RETURN_OK
		);

		return $authorization;
	}

	/**
	 * Set this function as the "auth_callback" parameter
	 * when add a router handler to deny authorization.
	 *
	 * @param   Request  $apiRequest
	 * @param   array    $authorization
	 *
	 * @return array
	 */
	public function denyAuthorization($apiRequest, $authorization)
	{
		$authorization = array(
			'status' => System\Http::RETURN_UNAUTHORIZED
		);

		return $authorization;
	}
}
