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
	Weeblr\Wblib\Forsef\System,
	Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * HTTP request. Breaks down and store an HTTP requests into components:
 *
 * string host
 * string path
 * array query
 * string fragment
 * array request_headers
 * string method
 *
 * A request
 */
class Request extends Base\Base
{
	/**
	 * @var array Version of this API.
	 */
	private $apiSlug = '_wblapi';

	/**
	 * @var string The version of the handling route.
	 */
	private $version;

	/**
	 * @var string Unique ID for the request (uuid v4)
	 */
	private $id;

	/**
	 * @var string The date/time the request was received.
	 */
	private $timestamp;

	/**
	 * @var array Holds all the request parsed parts.
	 */
	protected $request = array();

	/**
	 * @var Input Manages parameters obtained by parsing the route.
	 */
	protected $parameters;

	/**
	 * @var array Associative array of named parameters passed in the path.
	 */
	protected $namedParameters = array();
	/**
	 * @var string The root part of the path, to be trimmed from the full path.
	 */
	private $root;

	/**
	 * @var Route The router that was triggered by the request
	 */
	private $activeRoute;

	/**
	 * @var Response Holds the response to the current request.
	 */
	protected $response;

	/**
	 * @var string A name for the component that should have registered a route for this request.
	 */
	private $namespace;

	/**
	 * @var bool Whether this request has been made in a secure manner, ie https when over HTTP.
	 */
	private $secure = false;

	/**
	 * @var Callable|null A function to be ran after response has been sent, if $this->endRequest is false.
	 */
	protected $runAfterResponse = null;

	/**
	 * @var array Cache for already parsed current request.
	 */
	protected static $currentRequest = array();

	/**
	 * Stores provided request definition,
	 * or use current request if none provided.
	 *
	 * @param   array | null  $request
	 * @param   string        $root  Fully qualified root to be trimmed from request. Typically: /api_slug/namespace
	 * @param   string        $namespace
	 * @param   string        $version
	 *
	 *   array(
	 *     string $url
	 *     array  $data
	 *     array  $headers
	 *     string $method
	 *   )
	 */
	public function __construct($request = null, $root = '', $namespace = '', $version = '', $apiSlug = '')
	{
		parent::__construct();

		$this->namespace = $namespace;
		$this->version   = $version;
		$this->apiSlug   = empty($apiSlug) ? $this->apiSlug : $apiSlug;

		$this->id       = System\Auth::uuidv4(
			System\Auth::UUID4_NO_DASHES,
			System\Auth::UUID4_LOWERCASE
		);
		$this->response = new Response(
			$this->id,
			$this->version
		);

		/**
		 * {year}-{month}-{day}T{hour}:{minute}:{second},{microsecond}{timezone-offset}
		 * 2014-12-08T12:35:00Z
		 */
		$this->timestamp =
			System\Date::getUTCNow('Y-m-d')
			. 'T'
			. System\Date::getUTCNow('H:i:s,u')
			. 'Z';

		$this->root = trim($root ?? '', '/');

		$this->parseCurrentRequest();

		$this->parseRequest(
			$request
		);
	}

	/**
	 * Parse a request, or the current HTTP request if none provided.
	 *
	 * @param   array  $requestDef
	 *
	 * array(
	 *     string $url
	 *     array  $data
	 *     array  $headers
	 *     string $method
	 *   )
	 *
	 * @return $this
	 */
	protected function parseRequest($requestDef = null)
	{
		if (empty($requestDef))
		{
			$this->request = self::$currentRequest[$this->root];
		}
		else
		{
			$url = Wb\arrayGet($requestDef, 'url');
			$uri = System\Http::buildUri($url);

			$path = Wb\lTrim(
				trim($uri->getPath(), '/'),
				trim($this->platform->getBaseUrl(true), '/')
			);

			$path = trim($path, '/');
			$path = Wb\lTrim(
				$path,
				$this->root
			);

			$pathSegments = explode(
				'/',
				trim($path, '/')
			);

			$parsedRequest = array(
				'url'           => $uri->toString(),
				'path'          => $path,
				'path_segments' => $pathSegments,
				'query'         => new Input(
					$uri->getQuery(true)
				),
				'method'        => strtoupper(
					Wb\arrayGet(
						$requestDef,
						'method',
						'GET'
					)
				),
				'headers'       => Wb\arrayGet(
					$requestDef,
					'headers',
					array()
				),
				'secure'        => Wb\arrayGet(
					$requestDef,
					'secure',
					$this->secure
				),
				'data'          => Wb\arrayGet(
					$requestDef,
					'data',
					array()
				)
			);

			$this->request = $this->applyMethodOverrides(
				$parsedRequest
			);
		}

		return $this;
	}

	/**
	 * Check for method override header, and update the request method
	 * accordingly - only for POST requests.
	 *
	 * @param   array  $parsedRequest
	 *
	 * @return array
	 */
	private function applyMethodOverrides($parsedRequest)
	{
		$parsedRequest['original_method'] = $parsedRequest['method'];

		$currentMethod = Wb\arrayGet(
			$parsedRequest,
			'method',
			'GET'
		);

		if ('POST' != $currentMethod)
		{
			return $parsedRequest;
		}

		// look for an override header
		$overrideHeader = Wb\arrayGet(
			$parsedRequest,
			[
				'headers',
				'x-wblr-http-method-override'
			]
		);

		if (
			empty($overrideHeader)
			||
			!in_array(
				strtoupper($overrideHeader),
				[
					'PUT',
					'DELETE',
					'PATCH',
					'OPTIONS'
				]
			))
		{
			return $parsedRequest;
		}

		// valid override, update the request
		$parsedRequest['method'] = $overrideHeader;

		return $parsedRequest;
	}

	/**
	 * Get and store the current HTTP request.
	 *
	 * return $this;
	 */
	private function parseCurrentRequest()
	{
		$sig = is_null($this->root) ? '__current__http__request__' : $this->root;
		if (!isset(self::$currentRequest[$sig]))
		{
			$apiQueryVar = Wb\arrayGet($_GET, $this->apiSlug);
			if (!empty($apiQueryVar))
			{
				$queryVars = $_GET;
				unset($queryVars[$this->apiSlug]);
				if (!empty($queryVars))
				{
					$queryVars = \http_build_query($queryVars);
				}
				$queryVars = empty($queryVars) ? '' : '?' . $queryVars;
				$uri       = System\Http::buildUri('/' . $this->apiSlug . $apiQueryVar . $queryVars);
			}
			else
			{
				$uri = System\Http::buildUri();
			}

			$path = Wb\lTrim(
				trim($uri->getPath(), '/'),
				trim($this->platform->getBaseUrl(true), '/')
			);

			$path = trim($path, '/');
			$path = Wb\lTrim(
				$path,
				$this->root
			);

			$pathSegments = explode(
				'/',
				trim($path, '/')
			);

			$parsedRequest = array(
				'url'           => $uri->toString(),
				'path'          => $path,
				'path_segments' => $pathSegments,
				'query'         => new Input(
					$uri->getQuery(true)
				),
				'method'        => $this->platform->getMethod(),
				'secure'        => 'https' == $uri->getScheme(),
				'headers'       => System\Http::getAllHeaders(),
				'data'          => $this->parseBody()
			);

			self::$currentRequest[$sig] = $this->applyMethodOverrides(
				$parsedRequest
			);
		}

		return $this;
	}

	/**
	 * Parse de Json-encoded request body.
	 *
	 * @return array
	 */
	private function parseBody()
	{
		$requestBody = file_get_contents('php://input');
		$body        = json_decode(
			$requestBody,
			true
		);

		if ('' === $body)
		{
			$body = array();
		}

		return $body;
	}

	/**
	 * Builds a routed URL from an API path. An optional associative array of query vars, or a string directly,
	 * can be appended.
	 * If namespace and version are not supplied, those from the currently matched route will be used, if a route has
	 * been matched.
	 *
	 * @param   string  $path            The API request path: /aliases/12
	 * @param   array   $query           Query vars as an associative array.
	 * @param   string  $root            Optional api slug, namespace and version string (ie /_wblapi/wbredir/v1)
	 * @param   bool    $fullyQualified  Whether the full scheme+host should be prepended to the resulting URL.
	 *
	 * @return string
	 */
	public function routeLink($path = null, $query = null, $root = null, $fullyQualified = true)
	{
		$path  = empty($path) ? $this->getPath() : StringHelper::trim($path);
		$query = is_null($query) ? $this->getQuery()->getArray() : $query;
		if (!empty($query))
		{
			$query = '&' . \http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		}
		else
		{
			$query = '';
		}
		$root   = empty($root) ? $this->root : StringHelper::trim($root);
		$domain = $fullyQualified ? $this->platform->getRootUrl($pathOnly = false) : '';
		$root   = StringHelper::trim($root, '/');
		$path   = StringHelper::trim($path, '/');
		$domain = StringHelper::rtrim($domain, '/');

		return Wb\slashTrimJoin(
				$domain,
				$this->factory->getA(Helper::class)->buildBaseUrl($this->apiSlug),
				Wb\lTrim($root, $this->apiSlug . '/'),
				$path
			)
			. $query;
	}

	/**
	 * Getter for the request unique Id.
	 *
	 * @return string
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * Getter for the request namespace;
	 *
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->namespace;
	}

	/**
	 * Getter for the date/time the request was received, as a string.
	 *
	 * @return string
	 */
	public function getTimestamp()
	{
		return $this->timestamp;
	}

	/**
	 * Getter for the request method.
	 *
	 * @return string
	 */
	public function getMethod()
	{
		return Wb\arrayGet(
			$this->request,
			'method',
			'GET'
		);
	}

	/**
	 * Getter for the request original method, before any override.
	 *
	 * @return string
	 */
	public function getOriginalMethod()
	{
		return Wb\arrayGet(
			$this->request,
			'original_method',
			'GET'
		);
	}

	/**
	 * Getter for the host name.
	 *
	 * @return string
	 */
	public function getHost()
	{
		return Wb\arrayGet(
			$this->request,
			'host',
			''
		);
	}

	/**
	 * Whether the request was made over a secure connection.
	 *
	 * @return string
	 */
	public function isSecure()
	{
		return $this->secure;
	}

	/**
	 * Getter for the request path.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return trim(
			Wb\arrayGet(
				$this->request,
				'path',
				''
			),
			'/'
		);
	}

	/**
	 * Getter for the route object that was triggered, if any.
	 *
	 * @return Route
	 */
	public function getActiveRoute()
	{
		return $this->activeRoute;
	}

	/**
	 * Getter for the path segments.
	 *
	 * @return array Path segments as an array
	 */
	public function getPathSegments()
	{
		return Wb\arrayGet(
			$this->request,
			'path_segments',
			array()
		);
	}

	/**
	 * Returns the Nth segment in the requested path, starting from 1.
	 *
	 * @param   int  $position  The numerica position of the segment, starts at 1.
	 *
	 * @return string
	 * @throws \RuntimeException
	 */
	public function getPathSegment($position = 1)
	{
		$position = (int) $position;
		if ($position < 1)
		{
			throw new \RuntimeException('Invalid path segment requested');
		}
		$segments = $this->getPathSegments();
		if (empty($segments) || empty($segments[$position - 1]))
		{
			return '';
		}

		return $segments[$position - 1];
	}

	/**
	 * Getter for the query variables, as an associative array.
	 *
	 * @param   bool  $unfiltered  If true, query vars are not whitelisted. Required if called before a route is actually
	 *                             selected.
	 *
	 * @return Input
	 */
	public function getQuery($unfiltered = false)
	{
		$query = Wb\arrayGet(
			$this->request,
			'query'
		);

		// whitelist the query
		if (!$unfiltered)
		{
			$activeRoute = $this->getActiveRoute();
			if (!empty($activeRoute))
			{
				$query = $activeRoute->filterQueryVariables($query);
			}
		}

		return $query;
	}

	/**
	 * Getter for the request headers as an associative array.
	 *
	 * @return array
	 */
	public function getHeaders()
	{
		return Wb\arrayGet(
			$this->request,
			'headers',
			array()
		);
	}

	/**
	 * Getter for a single request header value.
	 *
	 * @param   string Header name, case insensitive.
	 * @param   string Default value if header not defined.
	 *
	 * @return string
	 */
	public function getHeader($name, $default = '')
	{
		return Wb\arrayGet(
			$this->request,
			array('headers', strtoupper($name)),
			$default
		);
	}

	/**
	 * Getter for the request fragment (without leading #).
	 *
	 * @return string
	 */
	public function getFragment()
	{
		return Wb\arrayGet(
			$this->request,
			'fragment',
			''
		);
	}

	/**
	 * Get the parameters object obtained from parsing the route.
	 *
	 * @return Input
	 */
	public function getParameters()
	{
		return $this->parameters;
	}

	/**
	 * Getter for the request body content, parsed into an array.
	 *
	 * @return array
	 */
	public function getBody()
	{
		return Wb\arrayGet(
			$this->request,
			'data',
			array()
		);
	}

	/**
	 * Set the route that was activated for this request.
	 *
	 * @param   Route  $route
	 *
	 * @return $this
	 */
	public function setActiveRoute($route)
	{
		$this->activeRoute = $route;

		return $this;
	}

	/**
	 * Setter for runAfterResponse, a Callable that should be ran after the response has been sent to requester.
	 *
	 * @param   Callable  $runAfterResponse
	 *
	 * @return $this
	 */
	public function setRunAfterResponse($runAfterResponse)
	{
		$this->runAfterResponse = $runAfterResponse;

		return $this;
	}

	/**
	 * Set the parameters object obtained from parsing the route.
	 *
	 * @param   Input  $parameters  An object to manage parameters in parsed route.
	 *
	 * @return $this
	 */
	public function setParameters($parameters)
	{
		$this->parameters = $parameters;

		return $this;
	}

	/**
	 * Set the HTTP status code to use in response.
	 *
	 * @param   int  $status  The HTTP status code.
	 *
	 * @return $this
	 */
	public function setResponseStatus($status)
	{
		$this->response->withStatus($status);

		return $this;
	}

	/**
	 * Set the data to use in response.
	 *
	 * @param   array  $data  Data to use in response
	 *
	 * @return $this
	 */
	public function setResponseData($data)
	{
		$this->response->withData($data);

		return $this;
	}

	/**
	 * Set the date/time at which the resource was last modified
	 *
	 * @param   int  $lastModified  A unix timestamp.
	 *
	 * @return $this
	 */
	public function setLastModified($lastModified)
	{
		$this->response->withLastModified($lastModified);

		return $this;
	}

	/**
	 * Set a list of errors to the response.
	 *
	 * @param   array  $errors  List of associative array.
	 *
	 * @return $this
	 */
	public function addResponseErrors($errors)
	{
		$this->response->withErrors($errors);

		return $this;
	}

	/**
	 * Set a list of links related to the response.
	 *
	 * @param   array  $links
	 *
	 * @return $this
	 */
	public function addResponseLinks($links)
	{
		$this->response->withLinks($links);

		return $this;
	}

	/**
	 * Add/set a list of headers to output.
	 *
	 * @param   array  $headers  Associate array of headers name => headers value
	 *
	 * @return $this
	 */
	public function addResponseHeaders($headers)
	{
		$this->response->withHeaders($headers);

		return $this;
	}

	/**
	 * Add/sets a list of meta data to be included in the response body.
	 *
	 * @param   array  $meta  Associative array of meta data, ie array('next' =>
	 *                        'https://www.weeblrpress.com/aliases?page=2&per_page=10')
	 *
	 * @return $this
	 */
	public function addResponseMeta($meta)
	{
		$this->response->withMeta($meta);

		return $this;
	}

	/**
	 * Proxy to render the response to this request.
	 */
	public function respond()
	{
		$endRequest = true;
		if (is_callable($this->runAfterResponse))
		{
			$endRequest = false;
		}

		$this->response->render(
			$endRequest
		);

		if (!$endRequest)
		{
			call_user_func($this->runAfterResponse);
			die();
		}
	}

	/**
	 * Proxy to get the response data for this request.
	 */
	public function getResponse()
	{
		return $this->response->renderData();
	}
}
