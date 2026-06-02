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

namespace Weeblr\Forseo\Platform\Components;

use Joomla\CMS\Router\Route;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 */
class Sh404sef extends Base
{
	/**
	 * @var string Component name - with leading com_ removed, eg content for com_content
	 */
	protected $component = 'sh404sef';

	/**
	 * @var string[] views we can store: none for newsfeeds
	 */
	protected $includedViews = null;

	/**
	 * @var bool Whether the plugin wants to filter raw, SEF URL. Time consuming, avoid if not needed.
	 */
	protected $filterShouldCollectUrlsFoundOnPage = false;

	/**
	 * @var Model\Config
	 */
	private $config;

	/**
	 * Add handlers for desired com_sh404sef hooks.
	 */
	public function addHooks()
	{
		parent::addHooks();

		// Only if feature is enabled, don't waste time.
		// Upon 4SEO install/update, we need to check if the sh404SEF_urls table is present. If so
		// we set a keystore value extensions.sh404sef.hasSefUrls (we'll need some for imports anyway).

		// We'll have some more keys in the keystore:
		// extensions.sh404sef.metaImport => 0:none, 1: completed, 2: in_progress, 3:in_error

		$this->config = $this->factory
			->getThis('forseo.config', 'extensions');

		if ($this->platform->isFrontend())
		{
			if ($this->config->isTruthy('sh404sefAutoRedirectToJoomla'))
			{
				$this->hook->add(
					'forseo_on_404_error',
					[
						$this,
						'action404Error'
					]
				);
			}
		}
	}

	/**
	 * Intercept 404 errors, look-up sh404SEF SEF URLs table and
	 * redirect to the matching Joomla URL if any.
	 *
	 * @param \Throwable $error
	 */
	public function action404Error($error)
	{
		if (!$this->canRedirectFromSh404sef())
		{
			return;
		}

		$requestParts = System\Route::splitQuery(
			$this->factory->getThe('forseo.pageDataCollector')
						  ->get()
						  ->get('full_url')
		);

		$requestedPath = Wb\arrayGet($requestParts, 'path', '');
		if (empty($requestedPath))
		{
			return;
		}

		// look it up in sh404SEF URLs
		try
		{
			$db      = $this->factory->getThe('db');
			$table   = '#__sh404sef_urls';
			$columns = $db->getTableColumns($table, false);
			if (empty($columns))
			{
				// sh404SEF db tables have been removed since 4SEO install.
				return;
			}

			$sh404sefRecord = $db->selectObject(
				$table,
				'*',
				[
					'oldurl' => $requestedPath,
					[
						'newurl',
						'!=',
						''
					]
				]
			);

			if (empty($sh404sefRecord))
			{
				return;
			}

			$targetUrl = $this->buildRedirectTarget(
				$sh404sefRecord->newurl,
				Wb\arrayGet($requestParts, 'query', '')
			);

			if (
				$this->platform->canRedirect(
					$this->factory->getThe('forseo.requestInfo')->get('page_url'),
					$targetUrl
				)
			) {
				$this->platform->redirectTo(
					$targetUrl,
					System\Http::RETURN_MOVED
				);
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Tests various conditions on sh404SEF presence and status to figure out
	 * wether we can try to redirect from its URLs to Joomla SEF.
	 *
	 * @return bool
	 */
	private function canRedirectFromSh404sef()
	{
		if (defined('SH404SEF_IS_RUNNING') || $this->platform->isBackend())
		{
			// don't do anything if sh404SEF is active
			return false;
		}

		if ($this->factory
			->getThe('forseo.keystore')
			->isFalsy('extensions.sh404sef.hasSefUrls')
		) {
			return false;
		}

		return true;
	}

	/**
	 * Build a fully qualified target URL from a non-sef URL read from
	 * sh404SEF URLs table. Include stitching back the request query if any.
	 *
	 * @param string $nonSefUrl      sh404SEF non-sef as read from the DB
	 * @param string $requestedQuery Query from the current request
	 * @return string
	 * @throws \Exception
	 */
	private function buildRedirectTarget($nonSefUrl, $requestedQuery = '')
	{
		// clean the non-sef URL by removing language code - if not ML site
		// and a few more conditions.
		$nonSefUrl = $this->platform
			->stripLangVarIfUseless(
				$nonSefUrl,
				false
			);

		if (!empty($requestedQuery))
		{
			$nonSefUrl = System\Route::appendQuery(
				$nonSefUrl,
				$requestedQuery
			);
		}

		return $this->platform->route(
			$nonSefUrl,
			false,
			Route::TLS_IGNORE,
			true
		);
	}
}

