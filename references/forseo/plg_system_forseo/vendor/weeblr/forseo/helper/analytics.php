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

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forseo\Joomla\Registry;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Analytics extends Base\Base
{
	/**
	 * Whether 4SEO memorize cookie acceptance itself or relies on 3rd parties
	 * to call its API.
	 */
	public const SET_4SEO_COOKIE = false;

	/**
	 * Pattern to follow to create our cookie to track cookies acceptance.
	 */
	public const COOKIE_PATTERN = '4seo_cookies_consent_';

	/**
	 * @var string Expanded name of cookie to track cookies acceptance.
	 */
	private $cookieName;

	/**
	 * @var array Convenience list of known providers
	 */
	private $providers = [];

	/**
	 * @var array Convenience list of providers for which cookies have been accepted.
	 */
	private static $providersAccepted = [];

	/**
	 * @var Convenience instance of platform cookies manager. Can set and read cookies content.
	 */
	private $cookiesManager;

	/**
	 * @var Complete analytics configuration.
	 */
	private $config;

	/**
	 * Analytics constructor.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->config = $this->factory->getThis(
			'forseo.config',
			'analytics'
		);

		$this->providers = $this->config->get('providers');

		if ($this->config->isFalsy('enableConsentCheck'))
		{
			return;
		}

		if (self::SET_4SEO_COOKIE)
		{
			$this->cookiesManager = $this->platform->getCookiesManager();
			$this->cookieName     = self::COOKIE_PATTERN . md5($this->platform->getRootUrl(false));
			$this->readConfig();
		}
	}

	/**
	 * Search for an HTTP only cookie listing providers for which
	 * user has consented to cookies.
	 */
	private function readConfig()
	{
		$consents = $this->cookiesManager->getString(
			$this->cookieName
		);
		$consents = json_decode($consents);
		if (empty($consents))
		{
			self::$providersAccepted = [];
		}

		$consents = Wb\arrayEnsure($consents);
		foreach ($consents as $consent)
		{
			if (!in_array($consent, $this->providers))
			{
				continue;
			}

			self::$providersAccepted[] = $consent;
		}
	}

	/**
	 * Whether we've been informed that user accepted cookies for this provider.
	 *
	 * @param string $provider
	 * @return bool
	 */
	public function userAcceptedCookies($provider)
	{
		if ($this->config->isFalsy('enableConsentCheck'))
		{
			return true;
		}

		return
			!empty($provider)
			&&
			in_array(
				$provider,
				self::$providersAccepted
			);
	}

	/**
	 * One or more providers have consented to cookies.
	 *
	 * @param array $providers
	 */
	public function analyticsCookiesAccepted($providers = [])
	{
		foreach ($providers as $provider)
		{
			if (!in_array($provider, $this->providers))
			{
				continue;
			}
			if (!in_array($provider, self::$providersAccepted))
			{
				self::$providersAccepted[] = $provider;
			}
		}

		$this->setCookie();
	}

	/**
	 * Cookies consent has been removed for one or more providers.
	 *
	 * @param array $providers
	 */
	public function analyticsCookiesRejected($providers = [])
	{
		foreach ($providers as $provider)
		{
			if (!in_array($provider, $this->providers))
			{
				continue;
			}

			if (in_array($provider, self::$providersAccepted))
			{
				self::$providersAccepted = array_diff(
					self::$providersAccepted,
					[$provider]
				);
			}

		}

		$this->setCookie();
	}

	/**
	 * Sets a cookie holding a json_encoded array of providers
	 * for which we have cookie consent.
	 */
	private function setCookie()
	{
		if (!self::SET_4SEO_COOKIE)
		{
			return;
		}

		$this->cookiesManager
			->set(
				$this->cookieName,
				json_encode(
					self::$providersAccepted
				),
				$expire = 0,
				$path = '',
				$domain = '',
				$secure = false,
				$httpOnly = true
			);
	}
}