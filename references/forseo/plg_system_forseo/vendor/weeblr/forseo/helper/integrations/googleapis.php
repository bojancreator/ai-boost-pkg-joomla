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

namespace Weeblr\Forseo\Helper\Integrations;

use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Factory;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Googleapis extends Base\Base
{
	/**
	 * Default time between 2 attempts at fetching new information.
	 */
	public const CACHE_TIME = 60;

	/**
	 * @var Cache Cache from platform.
	 */
	private $cache;

	/**
	 * Store a logger and config for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->cache = $this->platform->getCache(
			'callback',
			array(
				'defaultgroup' => '4seo_googleapis',
				'lifetime'     => self::CACHE_TIME,
				'caching'      => 1,
			)
		);
	}

	/**
	 * Handler for the forseo_onBeforeCompileHeadComplete hook. Search for a
	 * stored site verification token and inject it if found.
	 * @return void
	 */
	public function injectVerificationToken()
	{
		if (!$this->platform->isHtmlPage())
		{
			return;
		}

		$token = $this->factory
			->getThe('forseo.keystore')
			->get(
				Data\Services::STORE_PREFIX_SITE_VERIFICATION . 'token'
			);

		if (!empty($token))
		{
			$this->platform->addCustomTag(
				$token
			);
		}
	}

	/**
	 * Execute actions on a service being disconnected (through Oauth).
	 *
	 * @param string $service
	 * @return void
	 */
	public function onServiceDisconnected($service)
	{
		if ('google.search_console' === $service)
		{
			$this->factory->getThis('forseo.config', 'integrations')
						  ->set('gscProperty', [])
						  ->set('gscConfigStep', 'notStarted')
						  ->store();
		}
	}
}
