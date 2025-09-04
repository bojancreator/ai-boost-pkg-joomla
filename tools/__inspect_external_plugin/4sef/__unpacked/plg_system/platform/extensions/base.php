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

namespace Weeblr\Forsef\Platform\Extensions;

use Joomla\CMS\Uri;
use Joomla\CMS\Language;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Base as wblBase;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Base class to provide support for an extension.
 */
class Base extends wblBase\Base
{
	/**
	 * @var string Extension this plugin applies to, in com_xxxx format.
	 */
	protected $option = '';

	/**
	 * @var string Extension this plugin applies to, in xxxx format.
	 */
	protected $extension = '';

	/**
	 * @var Config
	 */
	protected $routingConfig;

	/**
	 * @var Config
	 */
	protected $extensionsConfig;

	/**
	 * @var Config
	 */
	protected $paginationConfig;

	/**
	 * @var Helper\Builder
	 */
	protected $builderHelper;

	/**
	 * @var Helper\Sef
	 */
	protected $sefHelper;

	/**
	 * @var Helper\Nonsef
	 */
	protected $nonSefHelper;

	/**
	 * @var Helper\Url
	 */
	protected $urlHelper;

	/**
	 * @var Helper\Menu
	 */
	protected $menuHelper;

	/**
	 * @var string Default language to use when none is present in incoming request.
	 */
	protected $defaultLanguage;

	/**
	 * @var string Default language to use when none is present in incoming request (as an URL code).
	 */
	protected $defaultLanguageCode;

	/**
	 * @var string Language tag for current page.
	 */
	protected $currentLanguage;

	/**
	 * @var string Language code for current page.
	 */
	protected $currentLanguageCode;

	/**
	 * @var array Holds per language fixed translation strings.
	 */
	protected $languageStrings = [];

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	protected $logger;

	/**
	 * Stores factory instance.
	 *
	 * @param string $option  Extension this applies to, in com_xxx format.
	 * @param array  $options Can inject custom factory and platform.
	 */
	public function __construct($option, $options = [])
	{
		parent::__construct($options);

		$this->option = $option;

		$this->logger              = $this->factory->getThe('forsef.logger');
		$this->routingConfig       = $this->factory->getThis('forsef.config', 'routing');
		$this->extensionsConfig    = $this->factory->getThis('forsef.config', 'extensions');
		$this->paginationConfig    = $this->factory->getThis('forsef.config', 'pagination');
		$this->builderHelper       = $this->factory->getA(Helper\Builder::class);
		$this->sefHelper           = $this->factory->getA(Helper\Sef::class);
		$this->nonSefHelper        = $this->factory->getA(Helper\Nonsef::class);
		$this->urlHelper           = $this->factory->getA(Helper\Url::class);
		$this->menuHelper          = $this->factory->getA(Helper\Menu::class);
		$this->defaultLanguage     = $this->platform->getDefaultLanguageTag();
		$this->currentLanguage     = $this->platform->getCurrentLanguageTag();
		$this->defaultLanguageCode = $this->platform
			->getLanguageUrlCode(
				$this->defaultLanguage
			);
		$this->currentLanguageCode = $this->platform
			->getLanguageUrlCode(
				$this->currentLanguage
			);

		$this->extension = $this->nonSefHelper->optionToExtension($option);

		$this->loadLanguage();
	}

	/**
	 * Give this plugin a chance to alter the parsed variables/Uri jsut before returning
	 * control to application router.
	 *
	 * @param array   $vars
	 * @param Uri\Uri $uri
	 * @return mixed
	 */
	public function postProcessParsing($vars, &$uri)
	{
		return $vars;
	}

	/**
	 * Builds the SEF URL for a non-sef using a plugin per extension, if available.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $platformUri
	 * @param Uri\Uri $originalUri
	 *
	 * @return array|null
	 * @throws \Exception
	 */
	public function build($uriToBuild, $platformUri, $originalUri)
	{
		return $this->addPrefix();
	}

	/**
	 * Inject a prefix to this extensions URLs, if any is configured for it by user.
	 *
	 * @return array
	 */
	protected function addPrefix()
	{
		$sefSegments = [];

		$prefixPropName = $this->extension . 'Prefix';

		if ($this->extensionsConfig->isTruthy($prefixPropName))
		{
			$sefSegments[] = $this->extensionsConfig->get($prefixPropName);
		}

		return $sefSegments;
	}

	/**
	 * Check if passed URI is for an extension configured to be left non-sef.
	 *
	 * @param Uri\Uri $uri
	 * @return bool
	 */
	public function shouldLeaveNonSef($uri)
	{
		return false;
	}

	/**
	 * Participate in building a normalized non-sef URL based on an incoming URI. Query vars values are URL-encoded.
	 * Stripping slugs, sorting vars and possibly other things are taken care globally. Only plugin-specific
	 * vars processing should happen here. For instance, stripping pagination variables if the plugin
	 * handles pagination dynamically.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function buildNormalizedNonSef($vars)
	{
		return array_diff_key(
			$vars,
			array_flip(
				[
					'tmpl',
					'jooa11y',
					// ours
					'wb4a'
				]
			)
		);
	}

	/**
	 * Get a chance to filter the remaining vars after the URL pair has been built.
	 * These are the vars that will stripped out from the the non-sef URL stored in the pair.
	 *
	 * @param array   $remainingVars
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $platformUri
	 * @param Uri\Uri $originalUri
	 * @return array
	 * @throws \Exception
	 *
	 * @since 1.6.2
	 */
	public function getRemainingNonSefVars($remainingVars, $uriToBuild, $platformUri, $originalUri)
	{
		return $remainingVars;
	}

	/**
	 * Build any final - dynamic - conformation. Plugin is responsible for setting the final sef
	 * in the URL pair provided, as well as removing query vars as needed from the platform URI.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      $platformUri
	 * @return void
	 * @throws \Exception
	 */
	public function buildDynamicSefSegments(&$urlPair, &$uriToBuild, &$platformUri)
	{
	}

	/**
	 * Build and append a pagination string to the URL to be built.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $uriToBuild
	 * @param Uri\Uri      $platformUri
	 * @return string
	 */
	public function buildPagination(&$urlPair, &$uriToBuild, &$platformUri)
	{
		$paginationString = '';
		if (
			!$uriToBuild->hasVar('start')
			&&
			!$uriToBuild->hasVar('limitstart')
		)
		{
			return $paginationString;
		}

		$limitstartVar = $uriToBuild->getVar(
			'start',
			$uriToBuild->getVar('limitstart')
		);

		$limitVar = $this->getPaginationLimit($uriToBuild);

		return $this->buildPaginationString(
			$uriToBuild,
			$limitstartVar,
			$limitVar,
			$uriToBuild->getVar('lang')
		);
	}

	/**
	 * Extract the limit pagination value from a URI, trying to get the default value
	 * if none set and if possible.
	 *
	 * @TODO: implement detecting default limit.
	 *
	 * @param Uri\Uri $uri
	 * @return int
	 */
	protected function getPaginationLimit($uri)
	{
		if ($uri->hasVar('limit'))
		{
			return (int)$uri->getVar('limit');
		}

		return 0;
	}

	/**
	 * Build a pagination string based on number of pages, start item and language.
	 *
	 * @param Uri\Uri $uri
	 * @param integer $limitstartVar
	 * @param integer $limitVar
	 * @param string  $languageTag
	 * @return string
	 */
	public function buildPaginationString($uri, $limitstartVar, $limitVar, $languageTag)
	{
		$paginationString = '';

		$pageNumber = 0;
		if (!empty($limitVar))
		{
			$pageNumber = (int)ceil($limitstartVar / $limitVar) + 1;
		}

		if (empty($pageNumber))
		{
			return $paginationString;
		}

		$paginationStrings = $this->paginationConfig->get('slugsPatterns', []);
		$languageTag       = empty($languageTag)
							 ||
							 !array_key_exists(
								 $languageTag,
								 $paginationStrings
							 )
			? $this->defaultLanguage
			: $languageTag;

		$paginationPattern = empty($paginationStrings[$languageTag])
			? 'Page-%s'
			: $paginationStrings[$languageTag];

		$paginationString = str_replace(
			'%s',
			$pageNumber,
			$paginationPattern
		);

		if (
			!empty($limitVar)
			&&
			$this->shouldAppendPaginationLimit($uri, $limitstartVar, $limitVar)
		)
		{
			$paginationString .= $this->paginationConfig->get('spacer') . $limitVar;
		}

		return $paginationString;
	}

	/**
	 * Whether the limit value should be included in the URL. Factors are:
	 * - configuration option
	 * - for some extensions, if limit is different from the default limit, it should be included
	 *
	 * There may be a per extension configuration option as well.
	 *
	 * @param Uri\Uri $uri
	 * @param integer $limitstartVar
	 * @param integer $limitVar
	 * @return bool
	 */
	protected function shouldAppendPaginationLimit($uri, $limitstartVar, $limitVar)
	{
		$shouldAppend    = false;
		$platformDefault = $this->getDefaultListLimit($uri);
		if ((int)$limitVar !== (int)$platformDefault)
		{
			$shouldAppend = true;
		}
		if ($this->paginationConfig->get('alwaysAppendItemsPerPage', false))
		{
			$shouldAppend = true;
		}

		return $shouldAppend;
	}

	/**
	 * Figure out the default list limit value based on the nons-ef being built.
	 *
	 * @param Uri\Uri $uri
	 * @return mixed
	 */
	protected function getDefaultListlimit($uri)
	{
		// defaults to platform limit
		return $this->platform->getConfig()->get('list_limit');
	}

	/**
	 * Loads any fixed translation strings available for this plugin extension.
	 *
	 * @return void
	 */
	private function loadLanguage()
	{
		$fileName = __DIR__ . '/language/' . $this->option . '.php';
		if (file_exists($fileName))
		{
			$this->languageStrings = include $fileName;
		}
	}

	/**
	 * Translate a string identified by a key into the specified language. Returns the key
	 * if no translation is available.
	 *
	 * @param string $language
	 * @param string $key
	 * @return string
	 */
	protected function t($language, $key)
	{
		$language = empty($language)
			? $this->currentLanguageCode
			: $language;

		if (
			empty($language)
			||
			!array_key_exists($language, $this->languageStrings)
			||
			!array_key_exists($key, $this->languageStrings[$language])
		)
		{
			return $key;
		}

		return $this->languageStrings[$language][$key];
	}

	/**
	 * Build and append a feed forma string to the URL to be built.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $platformUri
	 * @return string
	 */
	public function buildFeed($urlPair, $platformUri)
	{
		if (!$platformUri->hasVar('format'))
		{
			return '';
		}

		$format = $platformUri->getVar('format');
		if ('feed' !== $format)
		{
			return '';
		}

		return Wb\slashTrimJoin(
			'/feed',
			$platformUri->getVar('type', ''),
			'/'
		);
	}

	/**
	 * Build and append a print format string to the URL to be built.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $platformUri
	 * @return string
	 */
	public function buildPrint($urlPair, $platformUri)
	{
		if (
			!$platformUri->hasVar('print')
			||
			$this->platform->majorVersion() < 4
		)
		{
			return '';
		}

		$format = $platformUri->getVar('print');
		if (empty($format))
		{
			return '';
		}

		return Language\Text::_('Print');
	}

	/**
	 * Build and append a suffix string to the URL to be built.
	 *
	 * @param Data\Urlpair $urlPair
	 * @param Uri\Uri      $platformUri
	 * @return string
	 * @throws \Exception
	 */
	public function buildSuffix($urlPair, $platformUri)
	{
		$sef = $urlPair->get('sef');
		if (Wb\endsWith($sef, '/'))
		{
			return '';
		}

		return $this->routingConfig->get('suffix', '');
	}
}
