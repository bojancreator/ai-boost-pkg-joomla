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
 */

namespace Weeblr\Wblib\Forsef\Api;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base,
	Weeblr\Wblib\Forsef\System;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Parse and manage the named parameters of an API request.
 *
 */
class Route extends Base\Base
{
	protected $namespace;
	protected $method;
	protected $priority;
	protected $version;
	protected $route;
	protected $callback;
	protected $auth_callback;
	protected $auth_type;
	protected $authorizations;
	protected $query_vars_whitelist;
	protected $reg_exp;
	protected $params_list;

	protected $authorizer;

	/**
	 * @var string Characters allowed in an API request path.
	 */
	protected $allowedChars = '-+a-zA-Z0-9_.';

	/**
	 * Api\Route constructor. Stores and sanitizes a route definition.
	 *
	 * @param   array  $def
	 *
	 * @throws \Exception
	 */
	public function __construct($def)
	{
		parent::__construct();

		// prepare
		$route = Wb\arrayGet($def, 'route', '');
		if (!is_string($route))
		{
			throw new \RuntimeException('Invalid route registered: ' . print_r($route, true));
		}

		$route = trim($route, '/');
		if (empty($route))
		{
			throw new \RuntimeException('Invalid route registered.');
		}
		$hasParams = (bool) preg_match_all(
			'~{\??([' . $this->allowedChars . ']+)}~',
			$route,
			$matches
		);
		if (empty($hasParams))
		{
			$paramsList = array();
			$regExp     = '';
		}
		else
		{
			$paramsList = $matches[1];
			$regExp     = preg_replace(
				'~{[' . $this->allowedChars . ']+}~',
				'([' . $this->allowedChars . ']+)',
				$route
			);
			$regExp     = preg_replace(
				'~{\?[' . $this->allowedChars . ']+}~',
				'(?:/)?([' . $this->allowedChars . ']*)?',
				$regExp
			);

			$regExp = '~^' . $regExp . '$~u';
		}

		// which method this route applies to?
		$this->method    = strtoupper(
			Wb\arrayGet($def, 'method', 'GET')
		);
		$this->namespace = Wb\arrayGet($def, 'namespace', '');
		$this->priority  = Wb\arrayGet($def, 'priority', 0);
		$this->version   = Wb\arrayGet($def, 'version', 'v1');
		$this->route     = $route;

		// what to execute
		$this->callback = Wb\arrayGet($def, 'callback', null);

		// authentication/authorization
		$this->auth_type      = Wb\arrayGet($def, 'auth_type', Authorizer::AUTH_LOG_IN);
		$this->auth_callback  = Wb\arrayGet($def, 'auth_callback', null);
		$this->authorizations = Wb\arrayGet($def, 'authorizations', array());
		// must have authorizations definition, error out
		if (
			Authorizer::AUTH_PUBLIC != $this->auth_type
			&&
			empty($this->authorizations)
			&&
			empty($this->auth_callback)
		)
		{
			throw new \Exception(
				sprintf(
					'API route %s not public, but no authorizations specified.',
					$this->namespace . ':' . $this->method . ':' . $this->route
				)
			);
		}
		// whitelist for query vars in incoming request.
		$this->query_vars_whitelist = Wb\arrayGet($def, 'query_vars_whitelist', array());

		// computed reg_exp based on passed route
		$this->reg_exp = $regExp;

		// computed parameters name list, based on passed route
		$this->params_list = $paramsList;

		// authorizer will decide to allow requests
		$this->authorizer = new Authorizer(
			$this->auth_type,
			$this->authorizations,
			$this->auth_callback
		);
	}

	/**
	 * Match a path against a route, then parse any parameters.
	 *
	 * /aliases
	 * /aliases/{id}
	 * /aliases/{id}/{alias}
	 * /aliases/{id}/alias/{alias_id}
	 *
	 * @param   string  $method  HTTP method used.
	 * @param   string  $path    Path to match.
	 *
	 * @return array|bool
	 */
	public function match($method, $path)
	{
		// check method
		if (empty($method) || $method !== $this->method)
		{
			return false;
		}

		// direct match if not a parameterized route.
		if (empty($this->params_list))
		{
			if ($this->route == $path)
			{
				return array(
					'callback'   => $this->callback,
					'parameters' => array()
				);
			}
			else
			{
				return false;
			}
		}

		// parse request if some params.
		$matched = preg_match_all(
			$this->reg_exp,
			$path,
			$matches,
			PREG_SET_ORDER
		);

		if (!empty($matched))
		{
			array_shift($matches[0]);

			return array(
				'callback'   => $this->callback,
				'parameters' => $matches[0]
			);
		}

		return false;
	}

	/**
	 * Fully process a request if it matches this route definition.
	 *
	 * @param   Request  $request
	 *
	 * @return mixed|void
	 */
	public function processRequest($request)
	{
		$path        = $request->getPath();
		$parsedRoute = $this->match(
			$request->getMethod(),
			$path
		);
		if (false === $parsedRoute)
		{
			return;
		}

		// we have a candidate, check auth
		$authorization = $this->authorizer->authorize(
			$request
		);

		$status = Wb\arrayGet($authorization, 'status', System\Http::RETURN_UNAUTHORIZED);
		if (System\Http::RETURN_OK != $status)
		{
			$processedRequest = $request
				->setResponseStatus($status)
				->addResponseErrors(
					Wb\arrayGet(
						$authorization,
						'errors',
						array()
					)
				);

			return $processedRequest;
		}

		// ready to process, attach request input to request object
		$request->setParameters(
			new Input(
				$this->params_list,
				$parsedRoute['parameters']
			)
		);

		// store this route in the request itself, will be useful later.
		$request->setActiveRoute(
			$this
		);

		// Prevent fatal errors on ajax calls where some system plugin can assume the type is html
		// and try to inject assets manager or other htm-only stuff.
		$this->platform->setResponseType('json');

		// callback can be directly callable, or the name of a Controller descendant.
		// If a Controller descendant, the method is meant to be called from the Helper::handle() method
		// If not, just call it directly.
		if (is_array($this->callback))
		{
			$instanceOrClass = Wb\arrayGet($this->callback, 0);
			if (is_string($instanceOrClass) || (is_object($instanceOrClass) && is_callable($this->callback)))
			{
				$methodName = Wb\arrayGet($this->callback, 1);
				$request    = $this->factory->getA('Weeblr\Wblib\Forsef\Api\Helper')->handle($instanceOrClass, $methodName, $request);
			}
			else
			{
				$request = $request->setResponseStatus(System\Http::RETURN_NOT_FOUND);
			}
		}
		// a simple function or function name.
		else if (\is_callable($this->callback))
		{
			// call the actual code handling this route.
			$request = \call_user_func_array(
				$this->callback,
				array($request)
			);
		}
		else
		{
			$request = $request->setResponseStatus(System\Http::RETURN_NOT_FOUND);
		}

		return $request;
	}

	/**
	 * Filter the query variables against the whitelist.
	 *
	 * @param   Input  $queryVars
	 *
	 * @return Input
	 */
	public function filterQueryVariables($queryVars)
	{
		// we need a whitelist
		if (empty($this->query_vars_whitelist))
		{
			$queryVars = array();
		}
		else
		{

			$queryVars = array_intersect_key(
				$queryVars->getArray(),
				array_flip(
					$this->query_vars_whitelist
				)
			);
		}

		return new Input(
			$queryVars
		);
	}

	/**
	 * Magic getter.
	 *
	 * @param   string  $name
	 *
	 * @return mixed
	 */
	public function __get($name)
	{
		if (method_exists($this, $name))
		{
			return $this->{$name}();
		}
		elseif (property_exists($this, $name))
		{
			// Getter/Setter not defined so return property if it exists
			return $this->{$name};
		}

		return null;
	}

}
