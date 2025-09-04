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

use Joomla\CMS\Router\Router as JoomlaRouter;
use Joomla\CMS\Uri;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;
use Weeblr\Forsef\Platform;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Urlbuilder extends Base\Base
{
	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger;

	/**
	 * @var Config
	 */
	protected $extensionsConfig;

	/**
	 * @var Uri\Uri
	 */
	private $uriToBuild;

	/**
	 * @var Uri\Uri
	 */
	private $urlObject;

	/**
	 * @var bool Flag for sh404SEF backward compat legacy mode.
	 */
	private $legacyMode = false;

	/**
	 * @var Helper\Url
	 */
	private $urlHelper;

	/**
	 * @var Helper\Nonsef
	 */
	private $nonSefHelper;

	/**
	 * @var Helper\Extensions
	 */
	private $extensionsHelper;

	/**
	 * @var Helper\Menu
	 */
	private $menuHelper;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->logger           = $this->factory->getThe('forsef.logger');
		$this->extensionsConfig = $this->factory->getThis('forsef.config', 'extensions');
		$this->urlHelper        = $this->factory->getA(Helper\Url::class);
		$this->nonSefHelper     = $this->factory->getA(Helper\Nonsef::class);
		$this->extensionsHelper = $this->factory->getA(Helper\Extensions::class);
		$this->menuHelper       = $this->factory->getA(Helper\Menu::class);
	}

	/**
	 * Used at Router::PROCESS_AFTER to build the SEF if it has not been found yet.
	 * Joomla has already built its sef, so we can save it along ours.
	 *
	 * @param Uri\Uri                  $uriToBuild
	 * @param Platform\Extensions\Base $plugin
	 * @param JoomlaRouter             $platformRouter
	 * @param Uri\Uri                  $platformUri
	 * @param Uri\Uri                  $originalUri
	 *
	 * @return Data\Urlpair|null
	 */
	public function build($uriToBuild, $plugin, $platformRouter, $platformUri, $originalUri)
	{
		try
		{
			$urlPair     = $this->factory->getA(Data\Urlpair::class);
			$platformSef = $this->urlHelper->getSefFromUri($platformUri);
			$platformSef = Wb\lTrim(
				$platformSef,
				[
					'index.php/',
					'index.php'
				]
			);

			$platformQuery     = $platformUri->getQuery(true);
			$isAnyHomePagePath = $this->platform->isAnyHomepagePath($platformSef);
			if (
				empty($platformQuery)
				&&
				$isAnyHomePagePath
			)
			{
				$sef = $platformSef;
			}
			else if (
				// still use home page SEF if only query vars are format and type
				2 === count(array_intersect_key(
					$platformQuery,
					array_flip([
							'format',
							'type'
						]
					)
				))
				&&
				$isAnyHomePagePath
			)
			{
				$sef = $platformSef;
			}
			else if ($this->extensionsHelper->shouldUseJoomlaSef($uriToBuild))
			{
				$sef = $platformSef;
				if (!$this->extensionsHelper->shouldUseJoomlaSefWithMenuItem($uriToBuild))
				{
					$menuItemId = $uriToBuild->getVar('Itemid');
					if (!empty($menuItemId))
					{
						$sef = $this->menuHelper->stripMenuItem(
							$sef,
							$menuItemId
						);
					}
				}

				// if using platform SEF, we must also use the same non-sef variables
				$uriToBuild->setQuery(
					array_diff_key(
						$uriToBuild->getQuery(true),
						$platformUri->getQuery(true)
					)
				);
			}
			else
			{
				$sef = $this->buildSefUrl(
					$uriToBuild,
					$plugin,
					$platformUri,
					$originalUri
				);
			}

			$remainingVars = $plugin->getRemainingNonSefVars(
				$platformUri->getQuery(true),
				$uriToBuild,
				$platformUri,
				$originalUri
			);

			/**
			 * Filter the list of non-SEF vars that should be re-appended to the built URL.
			 *
			 * @api     forsef
			 * @package 4SEF\filter\build
			 * @var forsef_non_sef_vars_to_reappend
			 * @since   1.2.4
			 *
			 * @param array   $platformQuery
			 * @param URI\Uri $uriToBuild
			 * @param URI\Uri $platformUri
			 *
			 * @return string
			 *
			 */
			$remainingVars = $this->factory
				->getThe('hook')
				->filter(
					'forsef_non_sef_vars_to_reappend',
					$remainingVars,
					$uriToBuild,
					$platformUri
				);

			$remainingNonSef = $this->nonSefHelper->buildNormalizedNonSefFromVars(
				array_diff_key(
					$uriToBuild->getQuery(true),
					$remainingVars // any non-sef variable that was left over by the platform
				)
			);

			$urlPair->set(
				[
					'sef'      => is_null($sef)
						? $platformSef
						: $sef,
					'nonsef'   => $remainingNonSef,
					'platform' => $platformSef
				]
			);
		}
		catch (\Throwable $e)
		{
			$eMessage = $e->getMessage();
			if (!Wb\contains($eMessage, '4SEF: no option value set in URI'))
			{
				$this->logger->error(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $eMessage, $e->getTraceAsString());
			}
			$urlPair = null;
		}

		return $urlPair;
	}

	/**
	 * Use plugins to build the 4SEF SEF URL based on URI object.
	 *
	 * @param Uri\Uri                  $uriToBuild
	 * @param Platform\Extensions\Base $plugin
	 * @param Uri\Uri                  $platformUri
	 * @param Uri\Uri                  $originalUri
	 *
	 * @return string | null
	 * @throws \Exception
	 */
	private function buildSefUrl($uriToBuild, $plugin, $platformUri, $originalUri)
	{
		if (empty($plugin))
		{
			return null;
		}

		$queryVars = $uriToBuild->getQuery(true);
		$option    = Wb\arrayGet($queryVars, 'option');
		if (empty($option))
		{
			throw new \Exception('4SEF: no option value set in URI when building SEF.');
		}

		$customizedBaseSef = $this->findCustomizedBasedSef($queryVars);
		if (!is_null($customizedBaseSef))
		{
			return $customizedBaseSef;
		}

		$sefSegments = $plugin->build(
			$uriToBuild,
			$platformUri,
			$originalUri
		);

		$sefSegments = $this->maybeAddLanguageCode(
			$sefSegments,
			$uriToBuild
		);

		if (
			empty($sefSegments)
			||
			!is_array($sefSegments)
		)
		{
			return '';
		}

		$sefHelper = $this->factory->getA(Helper\Sef::class);
		$filtered  = array_filter(
			array_map(
				[
					$sefHelper,
					'conformSegment'
				],
				$sefSegments
			)
		);

		if (empty($filtered))
		{
			return '';
		}

		return Wb\slashTrimJoin(
			$filtered
		);
	}

	/**
	 * Possibly prepend the language code to the build URL and add it to
	 * the query vars.
	 *
	 * @param array   $segments
	 * @param Uri\Uri $uriToBuild
	 * @return array
	 */
	private function maybeAddLanguageCode($segments, $uriToBuild)
	{
		$sef = Wb\slashTrimJoin($segments);

		if ($this->platform->isAnyHomepagePath($sef))
		{
			// do not prepend language code on home page in any language, it's already there.
			return $segments;
		}

		$languageSpec = $this->nonSefHelper->getLanguageFromUri($uriToBuild);

		if ($languageSpec['tag'] !== $this->platform->getDefaultLanguageTag())
		{
			$uriToBuild->setVar(
				'lang',
				$languageSpec['sef']
			);
		}
		else
		{
			$uriToBuild->delVar(
				'lang'
			);
		}

		if ($this->platform->shouldAddLangCodeToSef($languageSpec['tag']))
		{
			array_unshift(
				$segments,
				$languageSpec['sef']
			);
		}

		return $segments;
	}

	/**
	 * Search for an existing urlPair resulting from the customization of a previous URL and
	 * with same non-sef as requested, except for pagination, format and suffix.
	 *
	 * Eg: /blog was customized into /custom-blog.
	 * If we now build the SEF URL for page 2 of that blog,we want it to be /custom-blog/page-2 and not /blog/page-2.
	 * The latter was happening in sh404SEF.
	 *
	 * @param array $query
	 * @return null|string
	 */
	private function findCustomizedBasedSef($queryVars)
	{
		// hack: Virtuemart pagination is not dynamic, so SEF does include pagination
		// we must not strip pagination query vars when looking for a customized base SEF
		$option          = Wb\arrayGet($queryVars, 'option');
		$stripPagination = 'com_virtuemart' !== $option;

		$urlPair = $this->factory
			->getA(Data\Urlpair::class)
			->loadPerBaseNonSef(
				$queryVars,
				$stripPagination
			);

		return $urlPair->exists()
			? $urlPair->get('base_path')
			: null;
	}
}
