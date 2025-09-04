<?php
/**
 * Project: 4SEF
 *
 * @package          4SEF
 * @copyright        Copyright Weeblr llc - 2022 -2025
 * @author           Yannick Gaultier - Weeblr llc
 * @license          GNU General Public License version 3; see LICENSE.md
 * @version          2.6.2.644
 * @date        2025-06-02
 */

namespace Weeblr\Forsef\Model;

use Joomla\CMS\Uri\Uri;

use Weeblr\Forsef\Controller;
use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Parser extends Base\Base
{
	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var Config
	 */
	private $appConfig;

	/**
	 * @var Config
	 */
	private $routingConfig;

	/**
	 * @var Uri
	 */
	private $originalUri;

	/**
	 * @var Uri
	 */
	private $uriToParse;

	/**
	 * @var Helper\Nonsef
	 */
	private $nonSefHelper;

	/**
	 * @var Helper\Extensions
	 */
	private $extensionsHelper;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->logger           = $this->factory->getThe('forsef.logger');
		$this->appConfig        = $this->factory->getThis('forsef.config', 'app');
		$this->routingConfig    = $this->factory->getThis('forsef.config', 'routing');
		$this->nonSefHelper     = $this->factory->getA(Helper\Nonsef::class);
		$this->extensionsHelper = $this->factory->getA(Helper\Extensions::class);
		$this->originalUri      = clone Uri::getInstance();
		$this->originalUri->setPath(
			rawurldecode(
				$this->originalUri->getPath()
			)
		);
	}

	/**
	 * Parse the format of the request
	 *
	 * @param SiteRouter  &$router Router object
	 * @param Uri         &$uri    URI object to process
	 *
	 * @return  void
	 *
	 * @since   4.0.0
	 */
	public function restoreTrailingSlash(&$router, &$uri)
	{
		$originalPath = Wb\lTrim(
			$this->originalUri->getPath(),
			[
				$this->platform->getBaseUrl(),
				$this->platform->getUrlRewritingPrefix(),
				'/'
			]
		);

		$currentPath = $uri->getPath();
		if (
			Wb\endsWith($originalPath, '/')
			&&
			!Wb\endsWith($currentPath, '/')

		)
		{
			$currentPath .= '/';
			$uri->setPath(
				$currentPath
			);
		}
	}

	/**
	 * PROCESS_DURING parse rule for Joomla router.
	 *
	 * @param $router
	 * @param $uri
	 * @return array
	 * @throws \Exception
	 */
	public function parseUri(&$router, &$uri)
	{
		try
		{
			$this->uriToParse = clone($uri);
			$originalPath     = Wb\lTrim(
				$this->originalUri->getPath(),
				[
					$this->platform->getBaseUrl(),
					$this->platform->getUrlRewritingPrefix(),
					'/'
				]
			);

			/**
			 * Filter the path 4SEF will try to parse, obtained from the original current URI object.
			 *
			 * @api     forsef
			 * @package 4SEF\filter\parse
			 * @var forsef_request_path_to_parse
			 * @since   1.0.0
			 *
			 * @param string $originalPath
			 *
			 * @return string
			 *
			 */
			$originalPath = $this->factory
				->getThe('hook')
				->filter(
					'forsef_request_path_to_parse',
					$originalPath
				);

			$currentPath = $this->uriToParse->getPath();

			if ($this->isNonSefRequest($this->uriToParse))
			{
				return [];
			}

			// at this stage, the language filter may have already parsed the URL
			// It would have set the lang variable and removed any language URL prefix corresponding to that language variable.
			// meaning that whatever language we have stored in the non-sef should be used but should not override
			// the lang var set by the language filter.
			// In any case we must use the original path to look it up
			// in the SEF URLs database table, as we store full URLs, including any language code.
			$requestedSef = '/' === $originalPath
				? ''
				: $originalPath;

			// it can also be one of the home pages, in which case we let the platform handle it.
			if (
				'index.php' === $requestedSef
				||
				$this->platform->isAnyHomepagePath($requestedSef)
			)
			{
				$this->storeHit(
					$this->factory
						->getA(Data\Urlpair::class)
						->loadPerCanonicalSef(
							$requestedSef
						)
				);

				return [];
			}

			// NB: we must parse dynamic vars (feed, suffix,...) here instead of relying
			// on parsing done withing forsef.request_info because we do not know
			// if this is the initial request parsing or just somebody (core or extension)
			// parsing a random URL.
			$parsedDynamicValues = $this->factory
				->getA(Helper\Dynamicsegments::class)
				->parse(
					$requestedSef,
					$this->uriToParse->getVar('lang')
				);

			$urlPair = $this->factory
				->getA(Data\Urlpair::class)
				->set(
					[
						'base_path'  => Wb\arrayGet($parsedDynamicValues, 'base_path', ''),
						'extra_path' => Wb\arrayGet($parsedDynamicValues, 'extra_path')
					]
				);

			// remove "appended_segment", the part of URL added dynamically
			$requestedSef = Wb\rTrim(
				$requestedSef,
				Wb\arrayGet($parsedDynamicValues, 'appended_segment', '')
			);

			$urlPair->loadPerCanonicalSef(
				$requestedSef
			);

			if (
				$urlPair->exists()
				&&
				$requestedSef !== $urlPair->get('sef')
			)
			{
				// only way this could happen is if path case differ
				$this->redirectForCase(
					$requestedSef,
					$urlPair->get('sef')
				);
			}

			if (!$urlPair->exists())
			{
				$this->redirectForTrailingSlash(
					$requestedSef,
					$urlPair
				);
			}

			// Execute any customized URL redirect. Must happen after requestInfo->parseDynamicSegments() has been
			// called and dynamic URL segments have been collected and analyzed. This is done early on in
			// our router parseRule method, so we're good here. We also want to do that before handing over
			// control back to the Joomla router if some extensions are set to PROCESS_BYPASS as otherwise
			// the router may decide it sees a valid URL and does not trigger a 404 (the other place where
			// we could check these redirects).
			if (!$urlPair->exists())
			{
				$this->factory
					->getA(Controller\Redirect::class)
					->execute();
			}

			if (!$urlPair->exists())
			{
				if (
					!empty($requestedSef)
					&&
					!$this->extensionsHelper->shouldUsePlatformRouter()
					&&
					$this->routingConfig->isTruthy('strict'))
				{
					throw new \Exception($this->platform->t('JERROR_PAGE_NOT_FOUND'), 404);
				}
				else
				{
					return [];
				}
			}

			$nonSef = $this->platform
				->stripLangVarIfUseless(
					$urlPair->get('nonsef'),
					false
				);

			parse_str(
				Wb\lTrim($nonSef, 'index.php?'),
				$vars
			);

			$vars = array_merge(
				empty($vars)
					? []
					: $vars,
				Wb\arrayGet($parsedDynamicValues, 'vars', [])
			);

			$extension = $this->nonSefHelper->optionToExtension(
				Wb\arrayGet($vars, 'option')
			);

			if (
				!empty($extension)
				&&
				Data\Config::PROCESS_BYPASS === $this->routingConfig->get(
					$extension . 'ProcessMode'
				)
			)
			{
				// found a non-sef/SEF pair in 4SEF data that would work,
				// but it is for an extension set to use the Joomla router
				// so we cannot use it.
				return [];
			}

			if (
				empty($requestedSef)
				&&
				empty($parsedDynamicValues)
			)
			{
				// home page, let platform router handle that.
				$this->postProcessParsing(
					$vars,
					$uri
				)->storeHit(
					$urlPair
				);

				return [];
			}

			if (!empty($vars))
			{
				$uri->setPath('');
				$uri->setQuery(
					array_merge(
						$uri->getQuery(true),
						$this->cleanParsedVars(
							$vars
						)
					)
				);

				$this->postProcessParsing(
					$vars,
					$uri
				)->storeHit(
					$urlPair
				);
			}

			if (
				empty($vars)
				&&
				$this->routingConfig->isTruthy('strict')
			)
			{
				throw new \Exception($this->platform->t('JERROR_PAGE_NOT_FOUND'), 404);
			}
		}
		catch (\Throwable $e)
		{
			if (System\Http::RETURN_NOT_FOUND === $e->getCode())
			{
				// pass-thru 404s resulting from parsing.
				throw $e;
			}

			$eMessage = $e->getMessage();
			if (!Wb\contains($eMessage, '4SEF: no option value set in URI'))
			{
				$this->logger->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $eMessage, $e->getTraceAsString());
			}
		}

		return empty($vars)
			? []
			: $vars;
	}

	/**
	 * Compares the incoming requested path to the already selected URL pair path.
	 * If they differ, it can only be from the case, so we redirect from the requested
	 * to the main, canonical one.
	 *
	 * @param string $requestedSef
	 * @param string $storedSef
	 * @return void
	 */
	private function redirectForCase($requestedSef, $storedSef)
	{
		if ($requestedSef === $storedSef)
		{
			return;
		}

		/**
		 * Filter whether 4SEF should redirect a request for a URL with a different letter case to
		 * the main one created and recorded in the SEF URLs list.
		 *
		 * For instance, if /blog/some-PAGE is requested, and /blog/some-page is
		 * the proper URL, 4SEF will redirect visits to /blog/some-PAGE to /blog/some-page
		 *
		 * @api     forsef
		 * @package 4SEF\filter\parse
		 * @var forsef_redirect_to_correct_case
		 * @since   1.0.0
		 *
		 * @param bool   $redirectToCorrectCaseEnabled
		 * @param string $requestedSef
		 * @param string $storedSef
		 *
		 * @return bool
		 *
		 */
		$redirectToCorrectCaseEnabled = $this->factory
			->getThe('hook')
			->filter(
				'forsef_redirect_to_correct_case',
				true,
				$requestedSef,
				$storedSef
			);

		if (!$redirectToCorrectCaseEnabled)
		{
			return;
		}

		$this->redirectFromPathToPath(
			$requestedSef,
			$storedSef,
			$this->uriToParse->getQuery()
		);

	}

	/**
	 * Compares the incoming requested path to the already selected URL pair path.
	 * If they differ, it can only be from the case, so we redirect from the requested
	 * to the main, canonical one.
	 *
	 * @param string       $requestedSef
	 * @param Data\Urlpair $urlPair
	 * @return void
	 * @throws \Exception
	 */
	private function redirectForTrailingSlash($requestedSef, $urlPair)
	{
		/**
		 * Filter whether 4SEF should redirect a request for a URL non-existing URL
		 * to the same URL with or without a trailing slash if there exists one.
		 *
		 * For instance, if /blog/some-page is requested, and does not exists but /blog/some-page/ does exist,
		 * 4SEF will redirect visits to /blog/some-page to /blog/some-page/
		 *
		 * @api     forsef
		 * @package 4SEF\filter\parse
		 * @var forsef_redirect_to_correct_trailing_slash
		 * @since   1.0.0
		 *
		 * @param bool   $redirectToCorrectSlashEnabled
		 * @param string $requestedSef
		 *
		 * @return bool
		 *
		 */
		$redirectToCorrectSlashEnabled = $this->factory
			->getThe('hook')
			->filter(
				'forsef_redirect_to_correct_trailing_slash',
				true,
				$requestedSef
			);

		if (!$redirectToCorrectSlashEnabled)
		{
			return;
		}

		if (
			$this->routingConfig->isTruthy('suffix')
			&&
			Wb\endsWith(
				$requestedSef,
				$this->routingConfig->get('suffix')
			)
		)
		{
			return;
		}

		$reSlashTrailedSef = Wb\endsWith($requestedSef, '/')
			? Wb\rTrim($requestedSef, '/')
			: $requestedSef . '/';

		if ($reSlashTrailedSef === $requestedSef)
		{
			return;
		}

		$urlPair->loadPerCanonicalSef($reSlashTrailedSef);
		if (!$urlPair->exists())
		{
			return;
		}

		$this->redirectFromPathToPath(
			$requestedSef,
			$reSlashTrailedSef,
			$this->uriToParse->getQuery()
		);

	}

	/**
	 * Perform a redirect from a path to another path with appropriate sanity checks
	 * and re-appending any query string.
	 *
	 * @param string $source
	 * @param string $target
	 * @param string $query
	 * @return void
	 */
	private function redirectFromPathToPath($source, $target, $query = '')
	{
		$source = System\Route::absolutify(
			$source,
			true
		);

		$target = System\Route::absolutify(
			$target,
			true
		);

		if ($this->platform->canRedirect(
			$target,
			$source
		))
		{
			// re-append query...
			if(!empty($query)) {
				$query = preg_replace('~[?&]+format=html~', '', $query);
			}

			$target = empty($query)
				? $target
				: $target . '?' . $query;

			// ...before redirecting
			$this->platform->redirectTo($target);
		}
	}

	/**
	 * Increase the hit counter for a given URL pair, after it was parsed.
	 *
	 * @param Data\Urlpair $urlPair
	 * @return void
	 */
	private function storeHit($urlPair)
	{
		if (!$urlPair->exists())
		{
			return;
		}

		if ($this->factory->getThis('forsef.config', 'stats')->isTruthy('enabledPerUrl'))
		{
			$urlPair->increment(
				'hits'
			)->timestamp(
				'last_hit'
			)->store();
		}

		if ($this->factory->getThis('forsef.config', 'stats')->isTruthy('enabledPerDay'))
		{
			$this->factory
				->getA(Data\Statsdailies::class)
				->storeHit();
		}
	}

	/**
	 * Whether the URI passed looks like a non-sef url.
	 *
	 * @param Uri $uri
	 * @return bool
	 */
	private function isNonSefRequest($uri)
	{
		return empty($uri->getPath())
			   &&
			   !empty($uri->getQuery());
	}

	/**
	 * Apply a few clean up rules to the parsed array of variables, to counter
	 * Joomla or 3rd-party extensions odd behaviors.
	 *
	 * @param array $vars
	 * @return array
	 */
	private function cleanParsedVars($vars)
	{
		// unset the Itemid var if it's empty: some extensions (or user-created custom SEF URLs)
		// have "&Itemid=&id=..., which currently caused the Itemid to be set to nothing
		// instead of Joomla default behavior
		if (
			empty($vars['Itemid'])
			&&
			isset($vars['Itemid'])
		)
		{
			unset($vars['Itemid']);
		}

		return $vars;
	}

	/**
	 * Execute additional actions after parsing to counter Joomla or 3rd-party extensions odd behaviors.
	 *
	 * @param array   $vars
	 * @param Uri\Uri $uri
	 *
	 * @return $this
	 */
	private function postProcessParsing($vars, $uri)
	{
		$extensionPlugin = $this->factory->getA(Helper\Plugins::class)
										 ->getPlugin(Wb\arrayGet($vars, 'option'));

		$vars = $extensionPlugin->postProcessParsing(
			$vars,
			$uri
		);

		/**
		 * Filter parsed variables for the incoming request - or any subsequent call to
		 * Joomla router parsing function.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\parse
		 * @var forsef_parsed_vars
		 * @since   1.0.0
		 *
		 * @param array   $vars
		 * @param Uri\Uri $uri
		 *
		 * @return array
		 *
		 */
		$vars = $this->factory
			->getThe('hook')
			->filter(
				'forsef_parsed_vars',
				$vars,
				$uri
			);

		if (!empty($vars['Itemid']))
		{
			// when J! will try parse this as RAW route, for some reason it tries
			// to get the Itemid from the request, but set it to null if none is
			// found, so we have to fake having one
			$this->platform->getHttpInput()
						   ->set(
							   'Itemid',
							   (int)$vars['Itemid']
						   );
		}

		return $this;
	}
}
