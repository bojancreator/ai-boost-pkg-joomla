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

namespace Weeblr\Forsef\Model\Extensions;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Joomla\CMS\HTML\Registry;
use Joomla\CMS\Component\ComponentHelper;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;

use Weeblr\Wblib\Forsef\Joomla\Uri\Uri as wblUri;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sh404sef extends Base\Base
{
	/**
	 * @var Helper\Dynamicsegments
	 */
	private $dynamicSegmentsHelper;

	/**
	 * @var Keystore General purpose storage.
	 */
	private $keystore = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * Stores factory instance.
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->dynamicSegmentsHelper = $this->factory->getA(Helper\Dynamicsegments::class);
		$this->keystore              = $this->factory->getThe('forsef.keystore');

		$this->logger = $this->factory->getThe('forsef.logger');
	}

	/**
	 * Patch sh404SEF configuration with an array of key/values.
	 *
	 * @param Registry $newState
	 * @return array
	 * @throws \Exception
	 */
	public function setConfigState($newState)
	{
		$newState = Wb\arrayEnsure($newState);
		if (empty($newState))
		{
			throw new \Exception('Invalid data providing trying to set sh404SEF configuraton, ' . print_r($newState, true), System\Http::RETURN_BAD_REQUEST);
		}

		// read from #__extensions table
		$state = ComponentHelper::getParams('com_sh404sef');

		// if not found, return OK, nothing to do
		if (empty($state))
		{
			return [
				'data'  => $newState,
				'count' => 1,
				'total' => 1,
			];
		}

		// if found, apply newState
		foreach ($newState as $key => $value)
		{
			$state->set($key, $value);
		}

		// and write back to DB
		$this->platform->saveExtensionParams(
			$state,
			[
				'type'    => 'component',
				'element' => 'com_sh404sef'
			]
		);

		return [
			'data'  => $newState,
			'count' => 1,
			'total' => 1,
		];
	}

	/**
	 * Extract useful values from sh404SEF configuration and apply and/or convert them to 4SEF
	 * configuration where they apply.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function importConfiguration()
	{
		$sh404sefConfig = ComponentHelper::getParams('com_sh404sef');

		if (
			empty($sh404sefConfig)
			||
			$sh404sefConfig->count() <= 0
		)
		{
			throw new \Exception('importWizard.configNotFound', 404);
		}

		$this->routingOptions($sh404sefConfig)
			 ->paginationOptions($sh404sefConfig)
			 ->contentOptions($sh404sefConfig)
			 ->contactOptions($sh404sefConfig)
			 ->weblinksOptions($sh404sefConfig)
			 ->virtuemartOptions($sh404sefConfig);

		$filteredInstalledExtensions = $this->factory
			->getThis('forsef.config', 'extensions')
			->getFilteredInstalledExtensions();

		$nonsefHelper = $this->factory
			->getA(Helper\Nonsef::class);

		foreach ($filteredInstalledExtensions as $installedExtension)
		{
			$this->sharedOptions(
				$nonsefHelper->optionToExtension($installedExtension->element),
				$sh404sefConfig
			);
		}

		$this->saveAll();

		return [
			'data'  => [],
			'count' => 1,
			'total' => 1,
		];
	}

	/**
	 * Store to db all configuration objects that may have been modified.
	 *
	 * @return void
	 */
	private function saveAll()
	{
		$configNames = [
			'routing',
			'pagination',
			'extensions'
		];

		foreach ($configNames as $configName)
		{
			$configObject = $this->factory->getThis('forsef.config', $configName);

			/**
			 * Filter the result of importing sh404SEF configuration, before it is stored to 4SEF database.
			 * Returning an empty value will prevent that config object to be saved at all.
			 *
			 * @api     forsef
			 * @package 4SEF\filter\import
			 * @var forsef_sh404sef_import_before_store_config
			 * @since   1.0.0
			 *
			 * @param Config $configObject
			 * @param string $configName
			 *
			 * @return Config
			 *
			 */
			$configObject = $this->factory->getThe('hook')->filter(
				'forsef_sh404sef_import_before_store_config',
				$configObject,
				$configName
			);

			if (!empty($configObject))
			{
				$configObject->store();
			}
		}
	}

	/**
	 * Convert all routing-related options.
	 *
	 * @param Registry $sh404sefConfig
	 * @return $this
	 */
	private function routingOptions($sh404sefConfig)
	{
		return $this->applyOptions(
			[
				'spacer'       => $sh404sefConfig->get('replacement', ''),
				'toStrip'      => str_replace('|', '', $sh404sefConfig->get('stripthese')),
				'replacements' => $sh404sefConfig->get('shReplacements'),
				'toTrim'       => str_replace('|', '', $sh404sefConfig->get('friendlytrim')),
				'lowerCase'    => (bool)$sh404sefConfig->get('LowerCase'),
				'suffix'       => $sh404sefConfig->get('suffix', ''),
				'useMenuAlias' => (bool)$sh404sefConfig->get('useMenuAlias')
			],
			$this->factory->getThis(
				'forsef.config',
				'routing'
			)
		);
	}

	/**
	 * Convert all pagination-related options.
	 *
	 * @param Registry $sh404sefConfig
	 * @return $this
	 */
	private function paginationOptions($sh404sefConfig)
	{
		return $this->applyOptions(
			[
				'slugsPatterns'            => $this->convertPageTexts($sh404sefConfig),
				'spacer'                   => $sh404sefConfig->get('pagerep'),
				'alwaysAppendItemsPerPage' => (bool)$sh404sefConfig->get('alwaysAppendItemsPerPage'),
			],
			$this->factory->getThis(
				'forsef.config',
				'pagination'
			)
		);
	}

	/**
	 * Convert pagination strings per language.
	 *
	 * @param Registry $sh404sefConfig
	 * @return $this
	 */
	private function convertPageTexts($sh404sefConfig)
	{
		$slugsPatterns = $this->factory->getThis(
			'forsef.config',
			'paginaton'
		)->get('slugsPatterns', []);

		$languageTags = array_map(
			function ($language) {
				return $language->lang_code;
			},
			$this->platform->getFrontendLanguages()
		);

		// collect all pageTexts
		foreach ($languageTags as $languageTag)
		{
			$keyName                     = 'languages_' . $languageTag . '_pageText';
			$slug                        = $sh404sefConfig->get($keyName);
			$slugsPatterns[$languageTag] = empty($slug)
				? 'Page-%s'
				: $slug;
		}

		return $slugsPatterns;
	}

	private function applyOptions($options, $config)
	{
		foreach ($options as $key => $value)
		{
			$config->set(
				$key,
				$value
			);
		}

		return $this;
	}

	private function contentOptions($sh404sefConfig)
	{
		return $this->applyOptions(
			[
				'contentUseTitleAlias'                 => (bool)$sh404sefConfig->get('UseAlias'),
				'contentUseCategoryAlias'              => (bool)$sh404sefConfig->get('useCatAlias'),
				'contentIncludeContentCat'             => (int) $sh404sefConfig->get('includeContentCat'),
				'contentIncludeContentCatCategories'   => (int) $sh404sefConfig->get('includeContentCatCategories'),
				'contentContentCategoriesSuffix'       => $sh404sefConfig->get('contentCategoriesSuffix'),
				'contentSlugForUncategorizedContent'   => $sh404sefConfig->get('slugForUncategorizedContent'),
				'contentInsertContentTableName'        => (bool)$sh404sefConfig->get('shInsertContentTableName'),
				'contentContentTableName'              => $sh404sefConfig->get('shContentTableName'),
				'contentInsertContentBlogName'         => (bool)$sh404sefConfig->get('shInsertContentBlogName'),
				'contentContentBlogName'               => $sh404sefConfig->get('shContentBlogName'),
				//'contentMultipagesTitle'               => true, // fixed now
				'contentContentTitleInsertArticleId'   => $sh404sefConfig->get('ContentTitleInsertArticleId'),
				'contentInsertContentArticleIdCatList' => $sh404sefConfig->get('shInsertContentArticleIdCatList'),  // list of category id
				'contentInsertNumericalId'             => (bool)$sh404sefConfig->get('shInsertNumericalId'),
				'contentInsertNumericalIdCatList'      => $sh404sefConfig->get('shInsertNumericalIdCatList'),
				'contentInsertDate'                    => (bool)$sh404sefConfig->get('insertDate'),
				'contentInsertDateCatList'             => $sh404sefConfig->get('insertDateCatList')
			],
			$this->factory->getThis(
				'forsef.config',
				'extensions'
			)
		);
	}

	private function contactOptions($sh404sefConfig)
	{
		return $this->applyOptions(
			[
				'contactUseContactCatAlias'          => (bool)$sh404sefConfig->get('useContactCatAlias'),
				'contactIncludeContactCat'           => (int) $sh404sefConfig->get('includeContactCat'),
				'contactIncludeContactCatCategories' => (int) $sh404sefConfig->get('includeContactCatCategories'),
				'contactContactCategoriesSuffix'     => $sh404sefConfig->get('contactCategoriesSuffix'),
				'contactSlugForUncategorizedContact' => $sh404sefConfig->get('slugForUncategorizedContact')
			],
			$this->factory->getThis(
				'forsef.config',
				'extensions'
			)
		);
	}

	private function weblinksOptions($sh404sefConfig)
	{
		return $this->applyOptions(
			[
				'weblinksUseWeblinksCatAlias'          => (bool)$sh404sefConfig->get('useWeblinksCatAlias'),
				'weblinksIncludeWeblinksCat'           => (int) $sh404sefConfig->get('includeWeblinksCat'),
				'weblinksIncludeWeblinksCatCategories' => (int) $sh404sefConfig->get('includeWeblinksCatCategories'),
				'weblinksWeblinksCategoriesSuffix'     => $sh404sefConfig->get('weblinksCategoriesSuffix'),
				'weblinksSlugForUncategorizedWeblinks' => $sh404sefConfig->get('slugForUncategorizedWeblinks')
			],
			$this->factory->getThis(
				'forsef.config',
				'extensions'
			)
		);
	}

	private function virtuemartOptions($sh404sefConfig)
	{
		return $this->applyOptions(
			[
				'virtuemartInsertShopName'         => (bool)$sh404sefConfig->get('shVmInsertShopName'),
				'virtuemartUseMenuItems'           => (bool)$sh404sefConfig->get('vmUseMenuItems'),
				'virtuemartWhichProductDetailsCat' => $sh404sefConfig->get('vmWhichProductDetailsCat'),
			],
			$this->factory->getThis(
				'forsef.config',
				'extensions'
			)
		);
	}

	private function sharedOptions($extension, $sh404sefConfig)
	{
		return $this->applyOptions(
			$this->convertProcessMode(
				$extension,
				$sh404sefConfig
			),
			$this->factory->getThis(
				'forsef.config',
				'extensions'
			)
		);
	}

	private function convertProcessMode($extension, $sh404sefConfig)
	{
		$component = 'com_' . $extension;
		$converted = [
			$extension . 'ProcessMode'                  => Data\Config::PROCESS_NORMAL,
			$extension . 'ProcessModeJoomlaSefWithMenu' => true,
			$extension . 'Prefix'                       => ''
		];

		$manageMode = $sh404sefConfig->get($component . '___manageURL', 0);
		switch ($manageMode)
		{
			case 1: // Simple encoding. Broken for many years, use Joomla SEF instead but store to DB to avoid using the Joomla router
				$converted[$extension . 'ProcessMode'] = Data\Config::PROCESS_USE_JOOMLA;
				break;
			case 2: // Leave as non-SEF
				$converted[$extension . 'ProcessMode'] = Data\Config::PROCESS_NON_SEF;
				break;
			case 3: // Use Joomla router
				$converted[$extension . 'ProcessMode'] = Data\Config::PROCESS_BYPASS;
				break;
			default:
				$converted[$extension . 'ProcessMode'] = Data\Config::PROCESS_NORMAL;
				break;
		}

		// in normal mode, if no dedicated plugin exists, we use the extension router.php file
		// and then there's a choice of prefixing with the menu item or not
		$componentOpMode = (int)$sh404sefConfig->get($component . '___shDoNotOverrideOwnSef', 0);
		if (
			Data\Config::PROCESS_NORMAL === $converted[$extension . 'ProcessMode']
			&&
			in_array(
				$componentOpMode,
				[1, 50]
			)
		)
		{
			switch ($componentOpMode)
			{
				case 1: // use router.php without menu item -> switch to using Joomla SEF
					$converted[$extension . 'ProcessMode']                  = Data\Config::PROCESS_USE_JOOMLA;
					$converted[$extension . 'ProcessModeJoomlaSefWithMenu'] = false;
					break;
				case 50: // use router.php with menu item -> switch to using Joomla SEF
					$converted[$extension . 'ProcessMode']                  = Data\Config::PROCESS_USE_JOOMLA;
					$converted[$extension . 'ProcessModeJoomlaSefWithMenu'] = true;
					break;
				default:
					$converted[$extension . 'ProcessModeJoomlaSefWithMenu'] = false;
					break;
			}

		}

		$converted[$extension . 'Prefix'] = $sh404sefConfig->get($component . '___defaultComponentString', '');

		return $converted;
	}

	/**
	 * Extract useful values from sh404SEF confguratioin and apply and/or convert them to 4SEF
	 * configuration where they apply.
	 *
	 * @param array $options
	 * @return array
	 * @throws \Exception
	 */
	public function importUrls($options)
	{
		$from = Wb\arrayGet($options, 'from');
		if ('sh404sef' !== $from)
		{
			throw new \Exception('Invalid source name trying to import sh404SEF custom URLs, ' . print_r($from, true), System\Http::RETURN_BAD_REQUEST);
		}
		$page = Wb\arrayGetInt($options, 'page');
		if ((int)$page < 1)
		{
			throw new \Exception('Invalid page number when trying to import sh404SEF custom URLs, ' . print_r($page, true), System\Http::RETURN_BAD_REQUEST);
		}
		$perPage = Wb\arrayGetInt($options, 'per_page', 5);
		if ((int)$perPage > 20)
		{
			throw new \Exception('Invalid number of URLs per bacth when trying to import sh404SEF custom URLs, ' . print_r($perPage, true), System\Http::RETURN_BAD_REQUEST);
		}

		$importResult = $this->importBatch(
			$page,
			$perPage,
			Wb\arrayGet($options, 'custom_only', true)
		);

		// read any custom URLs
		return [
			'data'  => [
				'imported' => Wb\arrayGet($importResult, 'imported'),
				'errored'  => Wb\arrayGet($importResult, 'errored'),
			],
			'count' => Wb\arrayGet($importResult, 'imported'),
			'total' => Wb\arrayGet($importResult, 'imported')
		];
	}

	/**
	 * Import a batch of URL pairs from sh404SEF into 4SEF, converting
	 * what can be converted in the process.
	 *
	 * @param int  $page
	 * @param int  $perPage
	 * @param bool $customOnly
	 * @return array
	 * @throws \Throwable
	 */
	private function importBatch($page, $perPage, $customOnly)
	{
		$whereClause = [
			['newurl', '<>', '']
		];

		if ($customOnly)
		{
			$whereClause[] = ['dateadd', '<>', '0000-00-00'];
		}

		$sources = $this->factory
			->getThe('db')
			->selectAssocList(
				'#__sh404sef_urls',
				'*',
				$whereClause,
				[],                              // $aWhereData
				[
					'id'   => 'ASC',
					'rank' => 'ASC'
				],                               // $orderBy
				($page - 1) * $perPage,          // $offset
				$perPage                         // $lines
			);

		$imported      = 0;
		$errored       = 0;
		$offset        = 0;
		$currentOffset = 0;
		foreach ($sources as $source)
		{
			$offset        += 1;
			$currentOffset = ($page - 1) * $perPage + $offset;

			$processed = $this->keystore->get('sh404sef_import.processed', null);
			if (is_null($processed))
			{
				// no processed record in keystore, we're starting a new batch
				$processed = 0;
				$this->keystore->delete(
					'sh404sef_import.errored'
				);
			}

			if ($currentOffset <= $processed)
			{
				// we already processed this URL, we may be resuming a failed import
				continue;
			}

			$errored = $this->keystore->get('sh404sef_import.errored', 0);

			try
			{
				$this->importSef($source);
			}
			catch (\Throwable $e)
			{
				$errored += 1;
				$this->logger->custom('import', 'Error importing sh404SEF data #' . $currentOffset . "\n" . print_r($source, true));
				$this->logger->custom('import', sprintf(__METHOD__ . ", %s::%d %s\n\n%s", $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString()));
				$this->keystore->put(
					'sh404sef_import.errored',
					$errored
				);
			}

			$imported += 1;
			$this->keystore->put(
				'sh404sef_import.processed',
				$currentOffset
			);
		}

		if ($currentOffset >= $this->factory->getThis('forsef.config', 'sh404sef')->get('canImportFromSh404sef', 0))
		{
			// completed, erase progress counter
			$this->keystore->delete(
				'sh404sef_import.processed'
			);
		}

		return [
			'imported' => $imported,
			'errored'  => $errored
		];
	}

	/**
	 * Import a single source SEF source
	 *
	 * @param array $source
	 * @return void
	 * @throws \Throwable
	 */
	private function importSef($source)
	{
		$urlPair = $this->factory->getA(
			Data\Urlpair::class
		);
		$sef     = Wb\arrayGet($source, 'oldurl', '');
		$parsed  = $this->parseSourceSef($sef);

		if (empty($parsed))
		{
			throw new \Exception('Error importing sh404SEF URL ' . print_r($sef, true) . '. Could not parse dynamic segments. Check your content language configuration, content languages are probably missing.');
		}

		// remove "appended_segment", the part of URL added dynamically
		$sef = Wb\rTrim(
			$sef,
			Wb\arrayGet($parsed, 'appended_segment', '')
		);

		$nonSef = $this->platform
			->stripLangVarIfUseless(
				Wb\arrayGet($source, 'newurl'),
				false
			);

		parse_str(
			Wb\lTrim($nonSef, 'index.php?'),
			$nonSefVars
		);

		$format = Wb\arrayGet($nonSefVars, 'format');

		// must use a plugin to clean the non-sef url, ie stripFeedVars and such
		$nonSefHelper = $this->factory
			->getA(Helper\Nonsef::class);

		$normalizedNonSefVars = $nonSefHelper->buildNormalizedNonSefVars(
			$nonSefVars
		);

		if (
			!empty($format)
			&&
			Wb\arrayIsEmpty($normalizedNonSefVars, 'format')
		)
		{
			// format was stripped by plugin. Means format is generated dynamically from base url pair
			// we do not want to import that URL
			return;
		}

		$normalizedNonSef = $nonSefHelper->buildNormalizedNonSefFromVars(
			$normalizedNonSefVars);

		$urlPair->loadPerUrlPair(
			$sef,
			$normalizedNonSef
		);

		if ($urlPair->exists())
		{
			// this exact URL pair already exists
			// maybe due to feed/rss or print dynamically added segments
			// just forget about it
			return;
		}

		$urlPair->set(
			[
				'sef'        => $sef,
				'base_path'  => Wb\arrayGet($parsed, 'base_path'),
				'extra_path' => Wb\arrayGet($parsed, 'extra_path'),
				'nonsef'     => $normalizedNonSef,
				'platform'   => '',
				'custom'     => '0000-00-00' === Wb\arrayGet($source, 'dateadd', '')
					? Data\Urlpair::AUTO
					: Data\Urlpair::CUSTOM,
				'hits'       => Wb\arrayGet($source, 'cpt', 232)
			]
		);

		/**
		 * Filter the result of importing an sh404SEF URL pair, before it is stored to 4SEF database.
		 *
		 * @api     forsef
		 * @package 4SEF\filter\import
		 * @var forsef_sh404sef_import_before_store_url
		 * @since   1.0.0
		 *
		 * @param Data\Urlpair $urlPair
		 * @param array        $source
		 *
		 * @return Data\Urlpair
		 *
		 */
		$urlPair = $this->factory->getThe('hook')->filter(
			'forsef_sh404sef_import_before_store_url',
			$urlPair,
			$source
		);

		if (!empty($urlPair))
		{
			$urlPair->store();
			if (
				Data\Urlpair::DUPLICATE === $urlPair->get('duplicate')
				&&
				Data\Urlpair::CANONICAL === Wb\arrayGetInt($source, 'rank', 0)
			)
			{
				$this->makeMain(
					$urlPair
				);
			}
		}
	}

	/**
	 * Make provided URL pair the canonical one. Needed as a duplicate may have been
	 * imported first, thus been considered canonical. By making the current one canonical
	 * we comply with whatever decision was made in sh404SEF.
	 *
	 * @param Data\Urlpair $urlPair
	 * @return void
	 * @throws \Throwable
	 */
	private function makeMain($urlPair)
	{
		// search for the current main URL
		$previousMain = $this->factory
			->getA(Data\Urlpair::class)
			->loadPerCanonicalSef(
				$urlPair->get('sef')
			);

		// then call URL pair makeMain function
		$urlPair->makeMain(
			$urlPair->get(),
			[
				'previousMain' => $previousMain->getId()
			]
		);
	}

	/**
	 * Parses SEF urls into their constituents to prepare a valid
	 * 4SEF URL pair.
	 *
	 * @param string $sourceSef
	 * @return array
	 */
	private function parseSourceSef($sourceSef)
	{
		return $this->dynamicSegmentsHelper
			->parse(
				$sourceSef,
				$this->guessLanguage($sourceSef)
			);
	}

	/**
	 * Try to identify the language associated with the provided SEF URLs
	 * by matching it to installed languages SEF urls prefix.
	 *
	 * @param string $sourceSef
	 * @return mixed|string
	 */
	private function guessLanguage($sourceSef)
	{
		$guessedLanguage = '';
		foreach ($this->platform->getFrontendLanguages() as $frontendLanguage)
		{
			if (Wb\startsWith($sourceSef, $frontendLanguage->sef . '/'))
			{
				$guessedLanguage = $frontendLanguage->lang_code;
				break;
			}
		}

		return $guessedLanguage;
	}
}
