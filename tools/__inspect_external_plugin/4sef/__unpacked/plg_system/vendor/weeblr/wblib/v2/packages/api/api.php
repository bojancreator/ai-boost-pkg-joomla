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
 * Simple api system.
 *
 * GET/POST/DELETE to ?{api_slug}=/{namespace}/{vn}/{path}[?query_string]
 *
 * {path}:
 *   /items
 *   /items/{named_param}
 *   /items/{named_param}/other_items/{other_named_param}
 *
 *
 * Response: enveloped json
 *
 * response =
 * {
 *   "data":
 *     {
 *        ...
 *     },
 *    "links": {
 *      "self: "https://domain.tld/.../?_wblapi=/namespace/v1/something/2?page=1&per_page=10",
 *    }
 *    "meta": {
 *      "count": 10,
 *      "total": 62,
 *      "id": "fkjsdhfkdsjfhsdkjf"
 *   }
 * }
 *
 * Routes and handlers are specified in Handler::register().
 *
 */
class Api extends Base\Base
{
	/**
	 * @var string Stores the URL api slug.
	 */
	private $apiSlug = '_wblapi';

	/**
	 * @var array Store all registered API routes.
	 */
	private $routes = [];

	/**
	 * Api constructor.
	 *
	 * @param   array  $options
	 *                      string $apiSlug Slug specific to this api instance calls.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->apiSlug = Wb\arrayGet(
			$options,
			'apiSlug',
			$this->apiSlug
		);
	}

	/**
	 * Change this API instance slug, avoid conflicts.
	 *
	 * @param   string  $slug
	 *
	 * @return Api
	 */
	public function setSlug($slug)
	{
		$this->apiSlug = $slug;

		return $this;
	}

	/**
	 * Getter for the API slug.
	 *
	 * @return string
	 */
	public function getSlug()
	{
		return $this->apiSlug;
	}

	/**
	 * Stores a GET route definition.
	 * @TODO: dedupe: compute a signature and check it.
	 *
	 * @param   string    $namespace  Unique ID for the router supplier.
	 * @param   string    $version    API version for that route.
	 * @param   string    $route      Route starting with a /, without the version.
	 * @param   Callable  $callback
	 * @param   array     $options    A series of options as an array:
	 *                                int priority Execution priority, higher get executed first, default to 0.
	 *                                string version Version string for the supplier API.
	 *                                Callable auth_callback An authorization callback.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function get($namespace, $version, $route, $callback, $options = [])
	{
		return $this->storeRoute(
			'get',
			$namespace,
			$version,
			$route,
			$callback,
			$options
		);
	}

	/**
	 * Stores a POST route definition.
	 * @TODO: dedupe: compute a signature and check it.
	 *
	 * @param   string    $namespace  Unique ID for the router supplier.
	 * @param   string    $version    API version for that route.
	 * @param   string    $route      Route starting with a /, without the version.
	 * @param   Callable  $callback
	 * @param   array     $options    A series of options as an array:
	 *                                int priority Execution priority, higher get executed first, default to 0.
	 *                                string version Version string for the supplier API.
	 *                                Callable auth_callback An authorization callback.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function post($namespace, $version, $route, $callback, $options = [])
	{
		return $this->storeRoute(
			'post',
			$namespace,
			$version,
			$route,
			$callback,
			$options
		);
	}

	/**
	 * Stores a PUT route definition.
	 * @TODO: dedupe: compute a signature and check it.
	 *
	 * @param   string    $namespace  Unique ID for the router supplier.
	 * @param   string    $version    API version for that route.
	 * @param   string    $route      Route starting with a /, without the version.
	 * @param   Callable  $callback
	 * @param   array     $options    A series of options as an array:
	 *                                int priority Execution priority, higher get executed first, default to 0.
	 *                                string version Version string for the supplier API.
	 *                                Callable auth_callback An authorization callback.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function put($namespace, $version, $route, $callback, $options = [])
	{
		return $this->storeRoute(
			'put',
			$namespace,
			$version,
			$route,
			$callback,
			$options
		);
	}

	/**
	 * Stores a Delete route definition.
	 * @TODO: dedupe: compute a signature and check it.
	 *
	 * @param   string    $namespace  Unique ID for the router supplier.
	 * @param   string    $version    API version for that route.
	 * @param   string    $route      Route starting with a /, without the version.
	 * @param   Callable  $callback
	 * @param   array     $options    A series of options as an array:
	 *                                int priority Execution priority, higher get executed first, default to 0.
	 *                                string version Version string for the supplier API.
	 *                                Callable auth_callback An authorization callback.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function delete($namespace, $version, $route, $callback, $options = [])
	{
		return $this->storeRoute(
			'delete',
			$namespace,
			$version,
			$route,
			$callback,
			$options
		);
	}

	/**
	 * Stores a patch route definition.
	 * @TODO: dedupe: compute a signature and check it.
	 *
	 * @param   string    $namespace  Unique ID for the router supplier.
	 * @param   string    $version    API version for that route.
	 * @param   string    $route      Route starting with a /, without the version.
	 * @param   Callable  $callback
	 * @param   array     $options    A series of options as an array:
	 *                                int priority Execution priority, higher get executed first, default to 0.
	 *                                string version Version string for the supplier API.
	 *                                Callable auth_callback An authorization callback.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function patch($namespace, $version, $route, $callback, $options = [])
	{
		return $this->storeRoute(
			'patch',
			$namespace,
			$version,
			$route,
			$callback,
			$options
		);
	}

	/**
	 * Stores an OPTIONS route definition.
	 * @TODO: dedupe: compute a signature and check it.
	 *
	 * @param   string    $namespace  Unique ID for the router supplier.
	 * @param   string    $version    API version for that route.
	 * @param   string    $route      Route starting with a /, without the version.
	 * @param   Callable  $callback
	 * @param   array     $options    A series of options as an array:
	 *                                int priority Execution priority, higher get executed first, default to 0.
	 *                                string version Version string for the supplier API.
	 *                                Callable auth_callback An authorization callback.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function options($namespace, $version, $route, $callback, $options = [])
	{
		return $this->storeRoute(
			'options',
			$namespace,
			$version,
			$route,
			$callback,
			$options
		);
	}

	/**
	 * Route a public API request url.
	 *
	 * @param   string  $namespace     Unique ID for the router supplier.
	 * @param   string  $version       API version for that route.
	 * @param   string  $url
	 * @param   array   $query         Associative array of query variables.
	 * @param   string  $endPointType  Decides whethere to include index.php in built link auto | file | folder
	 * @param   string  $format        Optional format query var, default to raw
	 *
	 * return string
	 *
	 * @return string
	 */
	public function routeLink($namespace, $version, $url, $query = [], $endPointType = 'auto', $format = null)
	{
		if (System\Route::isFullyQualified($url))
		{
			return $url;
		}
		if (!Wb\startsWith($url, '/'))
		{
			return $url;
		}

		// format is set in the base URL. We prioritize the one provided in $query
		// over the last parameter to maintain B/C
		$format = Wb\arrayGet($query, 'format', $format);
		if (isset($query['format']))
		{
			unset($query['format']);
		}

		// prepend root URL, root path, api_prefix
		if (!empty($query))
		{
			$query = '&' . \http_build_query($query, '', '&', PHP_QUERY_RFC3986);
		}
		else
		{
			$query = '';
		}

		return Wb\slashTrimJoin(
				StringHelper::trim(
					$this->platform->getRootUrl(false), // $pathOnly
					'/'
				),
				$this->factory->getA(Helper::class)
					->buildBaseUrl(
						$this->apiSlug,
						true, // $bypassLanguageFilter
						$endPointType,
						$format
					),
				$namespace,
				$version,
				StringHelper::ltrim(
					$url,
					'/'
				)
			)
			. $query;
	}

	/**
	 * Execute an API request, defined with:
	 *
	 * array(
	 *     string $url
	 *     array  $data
	 *     array  $headers
	 *     string $method
	 *   )
	 *
	 * Returns an array of data.
	 *
	 * @param   array  $requestDef  Optional request def, when called from PHP.
	 *
	 * @return array
	 */
	public function execute($requestDef)
	{
		$requestDef = array_merge(
			$requestDef,
			[
				'secure' => true
			]
		);

		return $this->processRequest(
			$requestDef,
			false
		);
	}

	/**
	 * Proxy for platform router hook-up.
	 */
	public function handleRequest()
	{
		$this->processRequest();
	}

	/**
	 * Handle a request. Either the current HTTP request, or a passed request definition.
	 *
	 * array(
	 *     string $url
	 *     array  $data
	 *     array  $headers
	 *     string $method
	 *   )
	 *
	 * Either render and Returns an array of data.
	 *
	 * @param   array  $requestDef  Optional request def, when called from PHP.
	 * @param   bool   $respond     If true, an HTTP response is output and processing ended.
	 *
	 * @return void|array
	 */
	public function processRequest($requestDef = null, $respond = true)
	{
		try
		{
			// is it an API request?
			if (!$this->isApiRequest($requestDef))
			{
				// not an api request
				return;
			}

			// bail early: must start with /{api_slug}/{namespace}
			$namespace = $this->getNamespaceIfValid(
				$requestDef
			);
			if (empty($namespace))
			{
				// maybe an API request but we don't know this namespace
				// Maybe another instance of wbLib can process it.
				return;
			}

			// which version is targeted?
			$version = $this->getVersionIfValid(
				$requestDef,
				$namespace
			);
			if (empty($version))
			{
				// api request, but not current version
				return $this->outputResponse(
					$respond,
					System\Http::RETURN_BAD_REQUEST,
					array(
						'Unsupported API version'
					)
				);
			}

			$processedRequest = $this->dispatchRequest(
				new Request(
					$requestDef,
					$this->apiSlug . '/' . $namespace . '/' . $version,
					$namespace,
					$version,
					$this->apiSlug
				),
				$namespace
			);

			if (!empty($processedRequest) && $processedRequest instanceof Request)
			{
				if ($respond)
				{
					$processedRequest->respond();
				}
				else
				{
					return $processedRequest->getResponse();
				}
			}

			return $this->outputResponse(
				$respond,
				System\Http::RETURN_NOT_FOUND,
				[
					'No route matched.'
				],
				[
					'status' => 'error',
					'errors' => [
						'message' => 'No route matched.',
					]
				]
			);
		}
		catch (\Throwable $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $this->outputResponse(
				$respond,
				System\Http::RETURN_INTERNAL_ERROR,
				array(
					'Internal error. Please try again later or contact this site administrator for assistance.'
				)
			);
		}
		catch (\Exception $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $this->outputResponse(
				$respond,
				System\Http::RETURN_INTERNAL_ERROR,
				array(
					'Internal error. Please try again later or contact this site administrator for assistance.'
				)
			);
		}
	}

	/**
	 * Dispatch an API request to a registered handler, if any. Returns the processed request.
	 *
	 * @param   Request  $request    The request inforrmation.
	 * @param   string   $namespace  Specific namespace of the request.
	 *
	 * @return Request
	 */
	protected function dispatchRequest($request, $namespace)
	{
		$processedRequest = null;
		foreach ($this->routes[$namespace] as $priority => $routes)
		{
			foreach ($routes as $route)
			{
				// returns a processed request if match,
				// null otherwise.
				$processedRequest = $route->processRequest(
					$request
				);
				if (
					!empty($processedRequest)
					&&
					$processedRequest instanceof Request
				)
				{
					return $processedRequest;
				}
			}
		}

		return $processedRequest;
	}

	/**
	 * Stores a route definition.
	 *
	 * @param   string    $method     The method this route can be used with.
	 * @param   string    $namespace  Unique ID for the router supplier.
	 * @param   string    $version    API version for that route.
	 * @param   string    $route      Route starting with a /, without the version.
	 * @param   Callable  $callback
	 * @param   array     $options    A series of options as an array:
	 *                                int priority Execution priority, higher get executed first, default to 0.
	 *                                string version Version string for the supplier API.
	 *                                Callable auth_callback An authorization callback.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function storeRoute($method, $namespace, $version, $route, $callback, $options)
	{
		// store
		$priority = Wb\arrayGet($options, 'priority', 0);
		Wb\arrayKeyInit($this->routes, $namespace, []);
		Wb\arrayKeyInit($this->routes[$namespace], $priority, []);

		try
		{
			// create route and store it
			$this->routes[$namespace][$priority][] =
				new Route(
					array_merge(
						[
							'method'    => $method,
							'namespace' => $namespace,
							'version'   => $version,
							'route'     => $route,
							'callback'  => $callback
						],
						$options
					)
				);

			// resort routes for this namespaces
			krsort(
				$this->routes[$namespace]
			);
		}
		catch (\Throwable $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
		catch (\Exception $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $this;
	}

	/**
	 * Checks whether this is an API request, based on first path segment.
	 *
	 * @param   array  $request  Optional request def, when called from PHP.
	 *
	 * @return bool Whether request is for this API.
	 * @throws \Exception
	 */
	private function isApiRequest($request = null)
	{
		// is it an API request?
		$request = new Request(
			$request,
			null, // $root
			'',
			'',
			$this->apiSlug
		);

		return $request->getPathSegment() == $this->apiSlug;
	}

	/**
	 * Extract the namespace from the request, and find whether there are some
	 * candidate routes for it.
	 *
	 * @param   array  $request  Optional request def, when called from PHP.
	 *
	 * @return string The request namespace.
	 * @throws \Exception
	 */
	private function getNamespaceIfValid($request = null)
	{
		$request   = new Request(
			$request,
			$this->apiSlug,
			'',
			'',
			$this->apiSlug
		);
		$namespace = $request->getPathSegment();

		if (
			empty($namespace)
			||
			!array_key_exists(
				$namespace,
				$this->routes
			)
		)
		{
			return '';
		};

		return $namespace;
	}

	/**
	 * Check whether the request is for an API version that can be handled.
	 *
	 * @param   array   $requestDef  Optional request def, when called from PHP.
	 * @param   string  $namespace   The namespace that was identified in a previous step.
	 *
	 * @return string|bool False if invalid version, the version otherwise.
	 * @throws \Exception
	 */
	private function getVersionIfValid($requestDef, $namespace)
	{
		$request = new Request(
			$requestDef,
			$this->apiSlug . '/' . $namespace,
			'',
			'',
			$this->apiSlug
		);

		$requestedVersion = $request->getPathSegment();
		if (empty($requestedVersion))
		{
			return false;
		}

		if (empty($this->routes[$namespace]))
		{
			return false;
		}

		// get all registered route for this namespace
		// and see if there's a handler registered for this version
		foreach ($this->routes[$namespace] as $priority => $routeDefs)
		{
			foreach ($routeDefs as $routeDef)
			{
				if ($requestedVersion == $routeDef->version)
				{
					return $requestedVersion;
				}
			}
		}

		return false;
	}

	/**
	 * Used only to output a direct response, ie in case of error for instance.
	 *
	 * @param   bool   $respond  If true, response should be ouput, else returned.
	 * @param   int    $status   The HTTP status to use.
	 * @param   array  $errors   Array of errors descriptors.
	 * @param   array  $data     Data required to build the desired response
	 * @param   array  $meta     Arbitrary meta data about the response.
	 * @param   array  $links    List of links.
	 *
	 * @return array|void
	 */
	private function outputResponse($respond, $status, $errors = null, $data = null, $meta = null, $links = null)
	{
		if ($respond)
		{
			$request = new Request(
				$request = null,
				$root = null,
				'',
				'',
				$this->apiSlug
			);
			$request
				->setResponseStatus($status)
				->addResponseErrors($errors)
				->setResponseData($data)
				->addResponseMeta($meta)
				->addResponseLinks($links)
				->respond();
		}
		else
		{
			return array(
				'status' => $status,
				'data'   => $data,
				'error'  => $errors,
				'meta'   => $meta
			);
		}
	}

}
