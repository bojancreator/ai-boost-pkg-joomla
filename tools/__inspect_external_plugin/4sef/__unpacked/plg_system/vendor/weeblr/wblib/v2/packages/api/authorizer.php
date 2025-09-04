<?php
/**
 * Project:                 4SEF
 *
 * @author                  Yannick Gaultier - Weeblr llc
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @package                 4SEF
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
 * Decide to authorize the processing of the provided request by the provided route.
 *
 * Workflow:
 *
 * If HTTPS, allow logged-in user authorization AND/OR token authorization.
 * If HTTP, only allow logged-in user authorization.
 *
 * routeDef can have an authorization callback in the callback_auth field.
 * This is called with:
 *   $routeDef[](apiRequest, $namespace, $routeDef, currentAuthorization)
 *
 * and must return an array:
 *
 * array (
 *   'status' => 200/401/403,
 *   'errors' => array(
 *     array(
 *       'code' => 12345,
 *       'message' => 'First error message'
 *     ),
 *     array(
 *       'code' => 3456,
 *       'message' => 'Second error message'
 *     )
 *   )
 * )
 *
 * The 'errors' field is optional.
 *
 * @TODO: implement token access over HTTPS.
 *
 * Token access = API to generate token and store them, revoke them, renew them.
 * A token must be associated with permissions rights, ie fairly complicated stuff.
 * \ShlApi_Token::create(
 *   $namespace,
 *   $authorizations_granted = array(
 *    array(
 *     'asset'  => 'xxxx,
 *     'action' => 'yyyy'
 *    ),
 *    array(
 *     'asset'  => 'xxxx_2,
 *     'action' => 'yyyy_2'
 *    )
 *  )
 * );
 *
 * Providing this token in a request is equivalent to being logged-in and having
 * the described privileges.
 *
 * Token does not seem urgent. Can be left to the implementer I think. Tokens can be managed
 * directly by the client app.
 *
 */
class Authorizer extends Base\Base
{
	const NOT_AUTHORIZED = [
		'status' => System\Http::RETURN_UNAUTHORIZED,
		'errors' => array(
			'code'    => System\Http::RETURN_UNAUTHORIZED,
			'message' => 'Invalid authorization token or credentials.'
		)
	];

	/**
	 * All access granted, no authorization required.
	 */
	const AUTH_PUBLIC = 'public';

	/**
	 * Authentication through cookie-based user authentication, platform-dependant.
	 */
	const AUTH_LOG_IN = 'login';

	/**
	 * Authentication based on token passed in request. To be defined, not implemented, falls back to AUTH_LOG_IN.
	 */
	const AUTH_TOKEN = 'token';

	/**
	 * @var string Authentication type: log-in, token or public.
	 */
	private $type;

	/**
	 * @var Authorizations required to be granted access.
	 */
	private $authorizations;

	/**
	 * @var Callable Optional callback provided at creation time to filter the built-in authorization check.
	 */
	private $authCallback;

	/**
	 * \ShlApi_Authorizer constructor. Stores type, authorizations list and optional callback.
	 *
	 * @param   string    $type            Authentication type: log-in, token or public.
	 * @param   array     $authorizations  Authorizations required to be granted access. Platform-dependant.
	 * @param   Callable  $authCallback    Optional callback provided at creation time to filter the built-in authorization
	 *                                     check.
	 */
	public function __construct($type, $authorizations, $authCallback = null)
	{
		parent::__construct();

		// @TODO implement token auth. For now, only login
		if (self::AUTH_TOKEN == $type)
		{
			$type = self::AUTH_LOG_IN;
		}

		$this->type           = $type;
		$this->authorizations = $authorizations;
		$this->authCallback   = $authCallback;
	}

	/**
	 * Getter for the authentication type.
	 *
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * Authorize a request against authorization rules passed
	 * at this object creation.
	 *
	 * @param   Request  $apiRequest
	 *
	 * @return array
	 */
	public function authorize($apiRequest)
	{
		switch ($this->type)
		{
			case self::AUTH_PUBLIC:
				return array(
					'status' => System\Http::RETURN_OK
				);
			case self::AUTH_LOG_IN:
			case self::AUTH_TOKEN:

				// can call auth method.
				$methodName    = 'authorize' . ucfirst($this->type);
				$authorization = $this->{$methodName}(
					$apiRequest
				);
				break;
			default:
				System\Log::libraryError(
					'API authorization method unknown: %s.',
					$this->type
				);

				return array(
					'status' => System\Http::RETURN_INTERNAL_ERROR
				);
		}

		return $authorization;
	}

	/**
	 * Decide to authorize the processing of the provided request against this object
	 * authorizations set, authentication being based on user login.
	 *
	 * @param   Request  $apiRequest
	 *
	 * @return int
	 */
	public function authorizeLogin($apiRequest)
	{
		$authorization = array(
			'status' => System\Http::RETURN_OK
		);

		// if state-changing method, require CRSF token
		if (
			'GET' != $apiRequest->getMethod()
			&&
			!$this->platform->checkCSRFToken(
				$apiRequest->getOriginalMethod()
			)
		)
		{
			return array(
				'status' => System\Http::RETURN_UNAUTHORIZED
			);
		}

		// if no auth requirements, we default to authorizing
		// else default to not authorizing as at least one of the
		// auth requirements must be satisfied.
		// (if ALL auth requirements must be met, this requires
		// a custom authorize which applies an AND operator
		// instead of the default OR we use below.
		$authorized = is_array($this->authorizations) && empty($this->authorizations);
		// we have some permissions requirements.
		foreach ($this->authorizations as $requiredPermission)
		{
			$authorized =
				$authorized
				||
				$this->platform->authorize(
					Wb\arrayGet($requiredPermission, 'action'),
					Wb\arrayGet($requiredPermission, 'asset')
				);
		}

		if (!$authorized)
		{
			$authorization = array(
				'status' => System\Http::RETURN_UNAUTHORIZED,
				'errors' => array(
					'code'    => System\Http::RETURN_UNAUTHORIZED,
					'message' => 'Invalid authorization token or credentials.'
				)
			);
		}

		if (is_callable($this->authCallback))
		{
			$authorization = call_user_func_array(
				$this->authCallback,
				array(
					$apiRequest,
					$authorization
				)
			);
		}

		return $authorization;
	}

	/**
	 * Authorize a request based on a passed token.
	 *
	 * Not implemented.
	 *
	 * @param   Request  $apiRequest
	 *
	 * @return array
	 */
	public function authorizeToken($apiRequest)
	{
		$authorization = array(
			'status' => System\Http::RETURN_FORBIDDEN
		);

		// if not secure, only log-in auth allowed (cookies)
		if (!$apiRequest->isSecure())
		{
			$this->type = self::AUTH_LOG_IN;
			System\Log::info(
				'wblib',
				'API route %s allows token, but not when requested in an insecure manner. Downgraded to AUTH_LOG_IN.',
				$this->namespace . ':' . $this->method . ':' . $this->route
			);

			return $this->authorizeLogin(
				$apiRequest
			);
		}

		return $authorization;
	}
}
