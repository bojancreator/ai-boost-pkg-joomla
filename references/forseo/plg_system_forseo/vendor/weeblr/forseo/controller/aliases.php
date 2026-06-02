<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * 2026-01-30
 */

namespace Weeblr\Forseo\Controller;

use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Model;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Seo;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Checks for and handles runtime execution of aliases.
 */
class Aliases extends Base\Base
{
	/**
	 * @var System\Log Convenience access to app logger.
	 */
	private $logger;

	/**
	 * @var System\Config Convenience access to system config.
	 */
	private $redirectsConfig;

	/**
	 * Instantiate convenience properties to main configs and logger.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->logger          = $this->factory->getThe('forseo.logger');
		$this->redirectsConfig = $this->factory->getThis('forseo.config', 'redirects');
	}

	/**
	 * Execute the first alias available for the current request, if any.
	 *
	 */
	public function execute()
	{
		try
		{
			if ($this->redirectsConfig->isFalsy('aliasesEnabled'))
			{
				return;
			}

			$currentUrl           = $this->factory->getThe('forseo.pageHelper')->getCleanedCurrentUrl();
			$normalizedCurrentUrl = $this->platform->normalizeUrl(
				$currentUrl,
				false // $removeLeadingSlash
			);

			$alias = $this->factory->getA(Model\Aliases::class)
								   ->lookupAlias($normalizedCurrentUrl);

			if ($alias->exists())
			{
				$targetUrl = System\Route::absolutify(
					$alias->get('full_url'),
					true
				);

				// only GET requests and not ajax
				if ($this->platform->canRedirect(
					$currentUrl,
					$alias->get('full_url')
				))
				{
					$alias->timestamp('last_hit')
						  ->increment('hits')
						  ->store();

					// 301
					$this->platform->redirectTo($targetUrl);
				}

			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}
}
