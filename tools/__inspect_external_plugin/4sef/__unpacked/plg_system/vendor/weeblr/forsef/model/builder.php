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

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Router\Router as JoomlaRouter;
use Joomla\CMS\Uri;
use Joomla\CMS\Factory as JoomlaFactory;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;
use Weeblr\Forsef\Platform;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Builder extends Base\Base
{
	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger;

	/**
	 * @var Config
	 */
	private $appConfig;

	/**
	 * @var Routingconfig
	 */
	private $routingConfig;

	/**
	 * @var Extensionsconfig
	 */
	private $extensionsConfig;

	/**
	 * @var Uri\Uri
	 */
	private $originalUri;

	/**
	 * @var Uri\Uri
	 */
	private $uriToBuild;

	/**
	 * @var Uri\Uri
	 */
	private $urlObject;

	/**
	 * @var bool Flag to decide if a new SEF should be built.
	 */
	private $sefUrlFound = false;

	/**
	 * @var Helper\Url
	 */
	private $urlHelper;

	/**
	 * @var Helper\Nonsef
	 */
	private $nonSefHelper;

	/**
	 * @var Helper\Sef
	 */
	private $sefHelper;

	/**
	 * @var Helper\Extensions
	 */
	private $extensionsHelper;

	/**
	 * @var Helper\Plugins
	 */
	private $pluginsHelper;

	/**
	 * @var Helper\Menu
	 */
	private $menuHelper;

	/* @var Urlbuilder */
	protected $urlBuilder;

	/**
	 * @var int Platform original option for suffix addition.
	 */
	private $platformSuffixOption;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->logger           = $this->factory->getThe('forsef.logger');
		$this->appConfig        = $this->factory->getThis('forsef.config', 'app');
		$this->routingConfig    = $this->factory->getThis('forsef.config', 'routing');
		$this->extensionsConfig = $this->factory->getThis('forsef.config', 'extensions');
		$this->urlHelper        = $this->factory->getA(Helper\Url::class);
		$this->nonSefHelper     = $this->factory->getA(Helper\Nonsef::class);
		$this->sefHelper        = $this->factory->getA(Helper\Sef::class);
		$this->extensionsHelper = $this->factory->getA(Helper\Extensions::class);
		$this->pluginsHelper    = $this->factory->getA(Helper\Plugins::class);
		$this->menuHelper       = $this->factory->getA(Helper\Menu::class);
		$this->urlBuilder       = $this->factory->getA(Urlbuilder::class);

		$this->enableBuildContext();
	}

	/**
	 * Enable the build context allowing proper interaction with the platform.
	 *
	 * @return Builder
	 */
	private function enableBuildContext()
	{
		if ($this->factory->getThis('forsef.config', 'legacy')->isTruthy('legacyMode'))
		{
			$this->factory->getThe('forsef.legacyLayer')->enableBuildContext();
		}

		$this->platformSuffixOption = $this->platform
			->getConfig()
			->get('sef_suffix', 0);

		return $this;
	}

	/**
	 * Used at Router::PROCESS_BEFORE to store the initial URI, before any Joomla processing.
	 *
	 * @param JoomlaRouter $router
	 * @param Uri\Uri      $uri
	 *
	 * @return Builder
	 */
	public function storeBuildRequest($router, $uri)
	{
		$this->originalUri = clone($uri);
		$this->uriToBuild  = clone($uri);
		$this->urlObject   = null;

		// if set to use Joomla router
		$shouldBypass = $this->extensionsHelper->shouldBypass($this->uriToBuild);
		if ($shouldBypass)
		{
			// empty the uri to build, that'll just leave the native
			// router to do its stuff.
			$this->uriToBuild = null;

			$this->setSuffixOption(
				$this->platformSuffixOption
			);
		}

		$this->setSuffixOption(
			$shouldBypass
				? $this->platformSuffixOption
				: 0
		);

		if (
			empty($uri->getQuery(true))
			&&
			(
				empty($uri->getPath())
				||
				'index.php' === $uri->getPath()
			)
		)
		{
			// special case of URL to route being just 'index.php'
			$this->uriToBuild = null;
		}

		return $this;
	}

	/**
	 * @return Uri\Uri Current URI build request in original form.
	 */
	public function getOriginalUri()
	{
		return $this->originalUri;
	}

	private function setSuffixOption($option)
	{
		$this->platform->getConfig()
					   ->set('sef_suffix', $option);

		JoomlaFactory::getApplication()->set('sef_suffix', $option);
	}

	/**
	 * Used at Router::PROCESS_DURING to try and find an existing SEF.
	 *
	 * @param JoomlaRouter $router
	 * @param Uri\Uri      $platformUri
	 *
	 * @return Builder
	 * @throws \Exception
	 */
	public function searchSef(&$router, &$platformUri)
	{
		if (empty($this->uriToBuild))
		{
			return $this;
		}

		$this->sefUrlFound = false;
		$this->uriToBuild  = $this->nonSefHelper->normalizeUri(
			$this->uriToBuild
		);

		if ($this->shouldNotRoute($this->uriToBuild))
		{
			$this->sefUrlFound = true;
			$this->makeUriStayNonSef($platformUri);

			return $this;
		}

		// possibly update the Itemid in the non-sef URL as Joomla router may have changed
		// it since we stored the request at searchSef().
		$platformItemid = $platformUri->getVar('Itemid');
		$toBuildItemid  = $this->uriToBuild->getVar('Itemid');
		if (
			!empty($platformItemid)
			&&
			$platformItemid != $toBuildItemid
		)
		{
			$this->uriToBuild->setVar('Itemid', $platformItemid);
		}

		// Now move on
		$nonSefUrl = $this->nonSefHelper->buildNormalizedNonSef(
			$this->uriToBuild
				->getQuery(true)
		);

		$urlPair = $this->getUrlObject()
						->loadPerNonSef(
							$nonSefUrl
						);

		if ($urlPair->exists())
		{
			$this->sefUrlFound = true;

			$this->conformBuiltUri(
				$this->uriToBuild,
				$platformUri,
				$urlPair
			)->injectPrefix(
				$urlPair
			)->fixMultilingualHomeLink(
				$urlPair
			)->useBuiltUri(
				$platformUri,
				$urlPair
			);
		}

		return $this;
	}

	/**
	 * Used at Router::PROCESS_AFTER to build the SEF if it has not been found yet.
	 * Joomla has already built its sef, so we can save it along ours.
	 *
	 * @param JoomlaRouter $platformRouter
	 * @param Uri\Uri      $platformUri
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function buildSef(&$platformRouter, &$platformUri)
	{
		if (
			empty($this->uriToBuild)
			||
			$this->sefUrlFound
		)
		{
			return;
		}

		if ($this->shouldNotRoute($this->uriToBuild))
		{
			$this->sefUrlFound = true;
			$this->makeUriStayNonSef($platformUri);

			return;
		}

		/** @var Platform\Extensions\Base $urlBuilderPlugin */
		$urlBuilderPlugin = $this->pluginsHelper
			->getPluginFromUri(
				$this->uriToBuild
			);

		if (
			$this->shouldLeaveNonSef(
				$urlBuilderPlugin,
				$this->uriToBuild
			)
		)
		{
			$this->sefUrlFound = true;
			$this->makeUriStayNonSef($platformUri);

			return;
		}

		// This is a new URL pair, figure out the SEF
		$urlPair = $this->urlBuilder->build(
			$this->uriToBuild,
			$urlBuilderPlugin,
			$platformRouter,
			$platformUri,
			$this->originalUri
		);

		if (!empty($urlPair))
		{
			// store sef as the 'base_path' before adding any extra: pagination, feed, print options
			$urlPair->set(
				'base_path',
				$urlPair->get('sef')
			);

			$customizedSef = $this->searchCustomizedSef($platformUri);

			$isNewUrlPair = true;
			if (!is_null($customizedSef))
			{
				$urlPair->set(
					'sef',
					$customizedSef
				);
				$isNewUrlPair = false;
			}

			if (in_array(
				$this->extensionsHelper->getProcessModeFromUri($this->uriToBuild),
				[
					Data\Config::PROCESS_NORMAL,
					Data\Config::PROCESS_USE_JOOMLA
				]
			))
			{
				$this->attachPagination(
					$urlPair,
					$this->uriToBuild,
					$platformUri
				);

				$this->attachSuffix(
					$urlPair,
					$this->uriToBuild,
					$platformUri
				);
			}

			/**
			 * Filter the built URL pair (sef + nonSef) object just before it's stored to the database. Pagination and suffix has been attached
			 * but dynamic variables are not present as they are not stored to DB. This currently includes:
			 *
			 * - /feed/rss and /feed/atom suffixes
			 * - print suffix
			 *
			 * @api     forsef
			 * @package 4SEF\filter\build
			 * @var forsef_before_store_url
			 * @since   1.0.0
			 *
			 * @param Data\Urlpair $urlPair
			 * @param Uri\Uri      $uriToBuild
			 * @param Uri\Uri      $platformUri
			 *
			 * @return void
			 *
			 */
			$urlPair = $this->factory
				->getThe('hook')
				->filter(
					'forsef_before_store_url',
					$urlPair,
					$this->uriToBuild,
					$platformUri
				);

			if ($isNewUrlPair)
			{
				$urlPair->store();
			}

			$this->conformBuiltUri(
				$this->uriToBuild,
				$platformUri,
				$urlPair
			)->injectPrefix(
				$urlPair
			)->useBuiltUri(
				$platformUri,
				$urlPair
			);
		}
	}

	/**
	 * Search the database for a customized SEF for a given non-sef URL.
	 *
	 * @param Uri\Uri $platformUri
	 * @return string | void
	 * @throws \Exception
	 */
	private function searchCustomizedSef($platformUri)
	{
		$nonsef            = array_diff_key(
			$this->uriToBuild->getQuery(true),
			$platformUri->getQuery(true)
		);
		$customizedVersion = $this->factory
			->getA(Data\Urlpair::class)
			->loadPerNonSef(
				$this->nonSefHelper->buildNormalizedNonSef(
					$nonsef
				)
			);

		return $customizedVersion->exists()
			? $customizedVersion->get('base_path')
			: null;
	}

	/**
	 * Make the platform use the URI we just built.
	 *
	 * @param Uri\Uri      $platformUri
	 * @param Data\Urlpair $urlPair
	 * @return $this
	 * @throws \Exception
	 * @return $this
	 */
	private function useBuiltUri(&$platformUri, $urlPair)
	{
		$this->makeUriUseSefAndNonSef(
			$platformUri,
			$urlPair->get('sef'),
			$urlPair->get('nonsef')
		);

		return $this;
	}

	/**
	 * Apply final conformance adjustment to the built URI before returning it
	 * to the platform. Append feed, print or similar suffixes.
	 *
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      $platformUri
	 * @param Data\Urlpair $urlPair
	 * @return $this
	 * @throws \Exception
	 */
	private function conformBuiltUri(&$uriToBuild, &$platformUri, $urlPair)
	{
		$this->conformFeed(
			$urlPair,
			$uriToBuild,
			$platformUri
		);

		$this->conformPrint(
			$urlPair,
			$uriToBuild,
			$platformUri
		);

		$this->pluginConform(
			$urlPair,
			$uriToBuild,
			$platformUri
		);

		// final touch, apply to dynamically generated segments
		$sef = $this->sefHelper->conform(
			$urlPair->get('sef')
		);

		$urlPair->set(
			'sef',
			$sef
		);

		return $this;
	}

	/**
	 * Possibly dynamically change the home page link for Multilingual sites.
	 * If language code is suppressed for default language, this may prevent
	 * the language switcher to switch back to default language.
	 * So we add the language code anyway if:
	 * - site is ML
	 * - default lang code is suppressed
	 * - current language is not default
	 * - URL is for the home page (ie empty)
	 *
	 * @param Data\Urlpair $urlPair
	 * @return Builder
	 * @throws \Exception
	 */
	private function fixMultilingualHomeLink($urlPair)
	{
		static $shouldFix;
		static $defaultLanguageCode;

		if (is_null($shouldFix))
		{
			$defaultLanguageCode = $this->platform->getLanguageUrlCode(
				$this->platform->getDefaultLanguageTag()
			);
			$currentLangCode     = $this->platform->getLanguageUrlCode(
				$this->platform->getCurrentLanguageTag()
			);

			$shouldFix = $this->platform->isMultilingual()
						 &&
						 !$this->platform->shouldInsertLangCodeInDefaultLanguage()
						 &&
						 $defaultLanguageCode !== $currentLangCode;
		}

		$sef = $urlPair->get('sef', '');
		if (
			$shouldFix
			&&
			empty($sef)
		)
		{
			$urlPair->set(
				'sef',
				$defaultLanguageCode . '/'
			);
		}

		return $this;
	}

	/**
	 * Optionally inject rewrite prefix.
	 *
	 * @param Data\Urlpair $urlPair
	 * @return Builder
	 */
	private function injectPrefix($urlPair)
	{
		$prefix = $this->platform->getUrlRewritingPrefix();
		if (empty($prefix))
		{
			return $this;
		}

		$sef = $urlPair->get('sef', '');
		if (
			!empty($sef)
			&&
			'index.php' !== $sef
		)
		{
			$urlPair->set(
				'sef',
				Wb\slashTrimJoin(
					'index.php',
					$sef
				)
			);
		}

		return $this;
	}

	/**
	 * Build and append a pagination string to the URL pair to be used.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      $platformUri
	 * @return Builder
	 * @throws \Exception
	 */
	private function attachPagination(&$urlPair, &$uriToBuild, &$platformUri)
	{
		$paginationString = $this->pluginsHelper
			->getPluginFromUri(
				$uriToBuild
			)->buildPagination(
				$urlPair,
				$uriToBuild,
				$platformUri
			);

		if (!empty($paginationString))
		{
			if ($this->routingConfig->isTruthy('lowerCase', true))
			{
				$paginationString = StringHelper::strtolower($paginationString);
			}

			$urlPair->set(
				[
					'sef'        => Wb\slashTrimJoin(
						$urlPair->get('sef'),
						$paginationString
					),
					'extra_path' => $paginationString
				]
			);

			$platformUri->delVar('start');
			$platformUri->delVar('limitstart');
			$platformUri->delVar('limit');
			$platformUri->delVar('showall');
		}

		return $this;
	}

	/**
	 * Build and append a suffix string to the URL pair to be used. No suffix if URL ends with /.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      $platformUri
	 * @return Builder
	 * @throws \Exception
	 */
	private function attachSuffix(&$urlPair, &$uriToBuild, &$platformUri)
	{
		$sef = $urlPair->get('sef');
		if (empty($sef))
		{
			return $this;
		}

		if ($this->extensionsHelper->shouldUseJoomlaSef($this->uriToBuild))
		{
			$suffixString = Wb\endsWith($sef, '/')
				? ''
				: $this->routingConfig->get('suffix', '');
		}
		else
		{
			$suffixString = $this->pluginsHelper
				->getPluginFromUri(
					$uriToBuild
				)->buildSuffix(
					$urlPair,
					$uriToBuild
				);
		}

		if (!empty($suffixString))
		{
			$urlPair->set(
				[
					'sef'        => $sef . $suffixString,
					'extra_path' => $urlPair->get('extra_path') . $suffixString
				]
			);
		}

		return $this;
	}

	/**
	 * Build and append a string to the URL pair to be used to represent a rss or other type of feeds.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      $platformUri
	 * @return Builder
	 * @throws \Exception
	 */
	private function conformFeed(&$urlPair, &$uriToBuild, &$platformUri)
	{
		/**
		 * Filters whether RSS feeds URLS should be built in a safer
		 * way, with format and type passed as query vars instead of
		 * part of the path.
		 * This may be required in some cases, for instance when manually
		 * customizing a category URL to append a prefix to it.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\build
		 * @var forsef_feeds_safe_mode
		 * @since   4.6.0
		 *
		 * @param bool $safeMode
		 *
		 * @return bool
		 *
		 */
		$safeMode = $this->factory->getThe('hook')->filter(
			'forsef_feeds_safe_mode',
			$this->routingConfig->isTruthy('feedsSafeMode', false)
		);

		$feedString = $this->pluginsHelper
			->getPluginFromUri(
				$uriToBuild
			)->buildFeed(
				$urlPair,
				$uriToBuild
			);

		if (!empty($feedString))
		{
			if (!$safeMode)
			{
				$urlPair->set(
					'sef',
					Wb\slashTrimJoin(
						$urlPair->get('base_path'),
						$feedString
					)
				);
				$platformUri->delVar('format');
				$platformUri->delVar('type');
			}
			$platformUri->delVar('limitstart');
			$platformUri->delVar('limit');
		}

		return $this;
	}

	/**
	 * Build and append a suffix string to the URL pair to be used. No suffix if URL ends with /.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      platformUri
	 * @return Builder
	 * @throws \Exception
	 */
	private function conformPrint(&$urlPair, &$uriToBuild, &$platformUri)
	{
		if ($this->platform->majorVersion() > 3)
		{
			return $this;
		}

		$printString = $this->pluginsHelper
			->getPluginFromUri(
				$uriToBuild
			)->buildPrint(
				$urlPair,
				$uriToBuild
			);

		if (!empty($printString))
		{
			$urlPair->set(
				'sef',
				Wb\slashTrimJoin(
					$urlPair->get('sef'),
					$printString
				)
			);

			$platformUri->delVar('print');
		}

		return $this;
	}

	/**
	 * Ask plugins for any final - dynamic - conformation. Plugin is responsible for setting the final sef
	 * in the URL pair provided, as well as removing query vars as needed from the platform URI.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      $platformUri
	 * @return Builder
	 * @throws \Exception
	 */
	private function pluginConform(&$urlPair, &$uriToBuild, &$platformUri)
	{
		$this->pluginsHelper
			->getPluginFromUri(
				$uriToBuild
			)->buildDynamicSefSegments(
				$urlPair,
				$uriToBuild,
				$platformUri
			);

		return $this;
	}

	/**
	 * Create an URL data object from a fully configured URI object and store it to db.
	 *
	 * @param Uri\Uri $uri
	 * @param string  $platformSef
	 * @return void
	 */
	private function storeUrlPair($uri, $platformSef)
	{
		$this->factory
			->getA(Data\Urlpair::class)
			->set(
				[
					'sef'      => $uri->getPath(),
					'nonsef'   => $uri->getQuery(true),
					'platform' => $platformSef
				]
			)->store();
	}

	/**
	 * Check if passed URI is for an extension configured to be left non-sef.
	 *
	 * @param Platform\Extensions\Base $builderPlugin
	 * @param Uri\Uri                  $uri
	 * @return bool
	 */
	private function shouldLeaveNonSef($builderPlugin, $uri)
	{
		/**
		 * Filters whether the URL being built should be left non-sef.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\build
		 * @var forsef_should_leave_non_sef
		 * @since   1.0.6
		 *
		 * @param bool $shouldLeaveNonSef
		 * @param Uri  $uri
		 *
		 * @return bool
		 *
		 */
		return $this->factory->getThe('hook')->filter(
			'forsef_should_leave_non_sef',
			$this->extensionsHelper->shouldLeaveNonSef($this->uriToBuild)
			||
			$builderPlugin->shouldLeaveNonSef($uri),
			$uri
		);
	}

	/**
	 * Whether the passed URI matches one of the conditions where 4SEF should not
	 * search DB for a stored SEF. For instance component has been set to Leave as non-SEF
	 * Note that the other such case, Do not store (ie = leave the Joomla router build the URL)
	 * has already been taken care of in storeBuildRequest() as we simply
	 * bypass the entire process by not storing the URI to build in $this->uriToBuild.
	 *
	 *
	 * @param Uri\Uri $uri
	 * @return bool
	 */
	private function shouldNotRoute($uri)
	{
		$extension = $this->nonSefHelper
			->optionToExtension(
				$uri->getVar('option')
			);

		return in_array(
				   $extension,
				   $this->extensionsConfig->get('nonRoutable', [])
			   )
			   ||
			   $this->extensionsHelper->shouldLeaveNonSef($this->uriToBuild);
	}

	/**
	 * Apply the passed nonSEF/SEF pair to the URI provided,  making the platform use the SEF URL.
	 *
	 * @param Uri\Uri $uri
	 * @param string  $sef
	 * @param string  $nonSef
	 * @return $this
	 */
	private function makeUriUseSefAndNonSef(&$uri, $sef, $nonSef)
	{
		$uri->setpath(
			$sef
		);

		$nonSefQueryString = Wb\lTrim(
			$nonSef,
			'index.php?'
		);

		parse_str(
			$nonSefQueryString,
			$nonSefQueryVars
		);

		$uriQueryVars = $uri->getQuery(true);

		if (
			// URI has an Itemid
			Wb\arrayIsTruthy($uriQueryVars, 'Itemid')
			&&
			// but nonSef does not
			Wb\arrayIsFalsy($nonSefQueryVars, 'Itemid')
		)
		{
			// keep the Itemid in the URI
			$nonSefQueryVars['Itemid'] = $uriQueryVars['Itemid'];
		}

		$uri->setQuery(
			array_diff_key(
				$uriQueryVars,
				$nonSefQueryVars
			)
		);

		return $this;
	}

	/**
	 * Format the URI so that it stays non-sef, without the platform router
	 * modifying it downstream. Attempts to detect the home page link and if detected
	 * returns without modifying the URI object.
	 *
	 * @param Uri\Uri $uri
	 *
	 * @return $this
	 */
	private function makeUriStayNonSef(&$uri)
	{
		if ($this->platform->isHomepageFromVars(
			$uri->getQuery(true)
		))
		{
			// special home page case
			return $this;
		}

		$uri->setPath(
			implode(
				'?',
				[
					'index.php',
					rawurldecode(
						http_build_query(
							$this->nonSefHelper->stripSlugs(
								$this->uriToBuild->getQuery(true)
							),
							'',
							'&',
							PHP_QUERY_RFC3986
						)
					)
				]
			)
		);

		$uri->setQuery('');

		return $this;
	}

	/**
	 * Builds a unique url data object.
	 *
	 * @return Data\Urlpair
	 */
	private function getUrlObject()
	{
		if (is_null($this->urlObject))
		{
			$this->urlObject = $this->factory
				->getA(Data\Urlpair::class);
		}

		return $this->urlObject;
	}
}
