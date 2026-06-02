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

namespace Weeblr\Forseo\Model\Extensions;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;
use Weeblr\Forseo\Model;

use Joomla\CMS\HTML\Registry;
use Joomla\CMS\Component\ComponentHelper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sh404sef extends Base\Base
{
	public const ALIASES = 'aliases';

	public const ALIAS_REDIRECT         = 0;
	public const ALIAS_CANONICAL        = 1;
	public const ALIAS_INTERNAL_REWRITE = 2;

	/**
	 * Home page identifier used by sh404SEF
	 */
	public const HOME_PAGE_ID = 'index.php?3de69ea13a27d1ead96ff5d7b47efae3';

	/**
	 * @var Helper\Meta Convenience metadata helper
	 */
	private $metaHelper;

	/**
	 * @var array Array describing specification of OpenGraph images
	 */
	private $ogpImageSpec;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * Instantiate a page object to store current page request data.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->logger       = $this->factory->getThe('forseo.logger');
		$this->metaHelper   = $this->factory->getA(Helper\Meta::class);
		$this->ogpImageSpec = $this->factory->getThis('forseo.config', 'app')
											->get('imageDetectionRequireSizeOgp');
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
	 * Extract useful values from sh404SEF tables and convert them to 4SEF records.
	 *
	 * @param string $type
	 * @param array  $options
	 * @return array
	 * @throws \Exception
	 * @throws \Throwable
	 */
	public function import($type, $options)
	{
		if (!in_array($type, [Data\Meta::ID, self::ALIASES]))
		{
			throw new \Exception('Invalid data type trying to import from sh404SEF, ' . print_r($type, true), System\Http::RETURN_BAD_REQUEST);
		}

		$from = Wb\arrayGet($options, 'from');
		if ('sh404sef' !== $from)
		{
			throw new \Exception('Invalid source name trying to import sh404SEF ' . $type . ' data, ' . print_r($from, true), System\Http::RETURN_BAD_REQUEST);
		}
		$page = Wb\arrayGetInt($options, 'page');
		if ((int)$page < 1)
		{
			throw new \Exception('Invalid page number when trying to import sh404SEF ' . $type . ' data, ' . print_r($page, true), System\Http::RETURN_BAD_REQUEST);
		}
		$perPage = Wb\arrayGetInt($options, 'per_page', 5);
		if ((int)$perPage > 20)
		{
			throw new \Exception('Invalid number of URLs per bacth when trying to import sh404SEF ' . $type . ' data, ' . print_r($perPage, true), System\Http::RETURN_BAD_REQUEST);
		}

		$imported = $this->importBatch(
			$type,
			$page,
			$perPage
		);

		// read any custom URLs
		return [
			'data'  => [],
			'count' => $imported,
			'total' => $imported,
		];
	}

	/**
	 * Import a batch of aliases from sh404SEF into 4SEO, converting
	 * what can be converted in the process.
	 *
	 * @param string $type
	 * @param int    $page
	 * @param int    $perPage
	 *
	 * @return int
	 * @throws \Throwable
	 */
	private function importBatch($type, $page, $perPage)
	{
		$this->logger->custom('import', 'Importing ' . $type . ' batch, page: ' . $page . ', per page: ' . $perPage);
		$sources = $this->factory
			->getThe('db')
			->selectAssocList(
				'#__sh404sef_' . $type,
				'*',
				[],                               // $whereClause
				[],                               // $aWhereData
				[],                               // $orderBy
				($page - 1) * $perPage,           // $offset
				$perPage                          // $lines
			);

		$toImport = count($sources);

		$this->logger->custom('import', 'Found ' . $toImport . ' items to import');

		foreach ($sources as $source)
		{
			try
			{
				Data\Meta::ID === $type
					? $this->importMetaRecords($source)
					: $this->importAliasRecords($source);
			}
			catch (\Throwable $e)
			{
				$this->logger->custom('import', 'Error importing sh404SEF data #' . "\n" . print_r($source, true));
				$this->logger->custom('import', sprintf(__METHOD__ . ', %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString()));
			}
		}

		return $toImport;
	}

	/**
	 * Import a single source meta data source
	 *
	 * @param array $source
	 * @return void
	 * @throws \Throwable
	 */
	private function importMetaRecords($source)
	{
		$this->logger->custom('import', 'Ready to import meta source record: ' . print_r($source, true));
		$metaRecord = $this->factory->getA(Data\Meta::class);

		$routedTarget = $this->routeNonSef(
			Wb\arrayGet($source, 'newurl')
		);

		if (is_null($routedTarget))
		{
			$this->logger->custom('import', 'Invalid non-sef URL found in record importing meta data from sh404SEF, skipping.');
			return;
		}

		$nonSef = Wb\arrayGet($routedTarget, 'nonsef');
		$sef    = Wb\arrayGet($routedTarget, 'sef');

		$nonSefQueryString = Wb\lTrim(
			$nonSef,
			'index.php?'
		);

		parse_str(
			$nonSefQueryString,
			$nonSefQueryVars
		);

		$metaRecord->loadPerColumn(
			'url',
			$sef
		)->set(
			'url',
			$sef
		);

		$metaData = $metaRecord->getMeta();

		$columns = [
			'title'       => 'metatitle',
			'description' => 'metadesc',
			'canonical'   => 'canonical',
			'robots'      => 'robots',
		];

		$modified = false;
		foreach ($columns as $forseoKey => $sh404sefKey)
		{
			$value = Wb\arrayGet($source, $sh404sefKey, '');
			if (!empty($value))
			{
				$modified                              = true;
				$metaData['custom'][$forseoKey]        = $value;
				$metaData['use' . ucfirst($forseoKey)] = Data\Meta::CUSTOM;
			}
		}

		$imageUrl = Wb\arrayGet($source, 'og_image', '');
		if (!empty($imageUrl))
		{
			$imageUrl = System\Route::absolutify(
				$imageUrl,
				true, // force domain
				$this->factory->getThis('forseo.config', 'pages')->get('canonicalRootUrl'),
				true  // isAsset
			);

			$image = $this->metaHelper->validateImageFromContent(
				$imageUrl,
				$this->ogpImageSpec
			);

			if (!empty($image))
			{
				$modified                            = true;
				$metaData['custom']['image']         = $image;
				$metaData['custom']['sharing_image'] = $image;
				$metaData['useImage']                = Data\Meta::CUSTOM;
			}
		}

		if ($modified)
		{
			$metaRecord->set(
				'data',
				$metaData
			)->timestamp('crawled_at');

			if (!$metaRecord->exists())
			{
				// if it does not exists, we must provide a content_id ourselves
				$page      = $this->factory->getA(Data\Page::class)->set(
					[
						'full_url'     => $sef,
						'non_sef_vars' => $nonSefQueryVars,
						'input_vars'   => $nonSefQueryVars,
						'query'        => [],
						'lang'         => $this->platform->getLanguageTagFromUrlCode(
							Wb\arrayGet($nonSefQueryVars, 'lang', $this->platform->getDefaultLanguageTag())
						),
						'page'         => Wb\arrayGet($nonSefQueryVars, 'Itemid', ''),
						'extension'    => strtolower(
							Wb\lTrim(
								Wb\arrayGetInt($nonSefQueryVars, 'option', ''),
								'com_'
							)
						),
						'view'         => Wb\arrayGet($nonSefQueryVars, 'view', ''),
						'layout'       => Wb\arrayGet($nonSefQueryVars, 'layout', ''),
						'item_id'      => Wb\arrayGet($nonSefQueryVars, 'id', ''),
						'content_lang' => $this->platform->getLanguageTagFromUrlCode(
							Wb\arrayGet($nonSefQueryVars, 'lang', $this->platform->getDefaultLanguageTag())
						),
					]
				);
				$contentId = $this->factory
					->getThe('forseo.pageHelper')
					->contentId($page);

				$metaRecord->set(
					'content_id',
					$contentId
				);
			}

			/**
			 * Filter the result of importing an sh404SEF meta object, before it is stored to 4SEO database.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\import
			 * @var forseo_sh404sef_import_before_store_meta
			 * @since   2.0.0
			 *
			 * @param Data\Meta $meta
			 * @param array     $source
			 *
			 * @return Data\Meta
			 *
			 */
			$metaRecord = $this->factory->getThe('hook')->filter(
				'forseo_sh404sef_import_before_store_meta',
				$metaRecord,
				$source
			);

			if (!empty($metaRecord))
			{
				$metaRecord->store();
			}
		}
	}

	/**
	 * Import a single source meta data source
	 *
	 * @param array $source
	 * @return void
	 * @throws \Throwable
	 */
	private function importAliasRecords($source)
	{
		$this->logger->custom('import', 'Ready to import alias source record: ' . print_r($source, true));
		$aliasType = Wb\arrayGetInt($source, 'target_type');
		switch ($aliasType)
		{
			case self::ALIAS_REDIRECT:
				$ruleType = Data\Rule::TYPE_REDIRECT;
				break;
			case self::ALIAS_CANONICAL:
				$ruleType = Data\Rule::TYPE_META;
				break;
			case self::ALIAS_INTERNAL_REWRITE:
				$ruleType = Data\Rule::TYPE_INTERNAL_REWRITE;
				break;
		}

		if (empty($ruleType))
		{
			$this->logger->custom('import', 'Cannot import alias source record, invalid type: ' . print_r($source, true));
			return;
		}

		$rulesModel = $this->factory->getA(Model\Rules::class);

		$ruleSpecDef = [
			'type'    => $ruleType,
			'source'  => Data\Rule::SOURCE_IMPORT_SH404SEF,
			'enabled' => Data\Rule::ENABLED_WITH_CONDITIONS,
			'title'   => Wb\arrayGet($source, 'alias') . ' (imported)',
			'valid'   => 1,
		];

		$routedTarget = $this->routeTarget(
			Wb\arrayGet($source, 'newurl')
		);

		if (is_null($routedTarget))
		{
			$this->logger->custom('import', 'Invalid target sef or non-sef URL found in record, skipping.');

			return;
		}

		$alias     = Wb\arrayGet($source, 'alias');
		$sefTarget = Wb\arrayGet($routedTarget, 'sef');
		$rule      = array_merge(
			$ruleSpecDef,
			[
				'urlSpec' => $rulesModel->normalizeUrlSpec(
					$alias
				)
			]
		);

		if (self::ALIAS_CANONICAL === $aliasType)
		{
			$rule['actionCanonicalTarget'] = $rulesModel->normalizeTarget(
				$sefTarget
			);
		}
		else
		{
			$rule['actionRedirectType']   = 301;
			$rule['actionRedirectTarget'] = $rulesModel->normalizeTarget(
				$sefTarget
			);

			// if the source URL is a non-SEF URL, we cannot disregard the query string
			// as this would result in any URL with a query string to be redirected to home
			// In most cases, these old redirects are going to be for specific non-sef URLs
			$rule['disregardQuery'] = !Wb\startsWith(
				$alias,
				'index.php?'
			);
		}

		$aliasRuleSpec = [
			'type'    => $ruleType,
			'source'  => Data\Rule::SOURCE_IMPORT_SH404SEF,
			'enabled' => Data\Rule::ENABLED_WITH_CONDITIONS,
			'title'   => Wb\arrayGet($source, 'alias') . ' (imported)',
			'valid'   => 1,
			'rule'    => $rule
		];


		/**
		 * Filter the result of importing an sh404SEF alias object, before it is stored to 4SEO database.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\import
		 * @var forseo_sh404sef_import_before_store_alias
		 * @since   2.0.0
		 *
		 * @param array $alias
		 * @param array $source
		 *
		 * @return array
		 *
		 */
		$aliasRuleSpec = $this->factory->getThe('hook')->filter(
			'forseo_sh404sef_import_before_store_alias',
			$aliasRuleSpec,
			$source
		);

		if (!empty($aliasRuleSpec))
		{
			$rulesModel->create(
				$aliasRuleSpec
			);
		}
	}

	/**
	 * If imported target URL is non-sef, make it SEF using the frontend router.
	 * Returns an array with both SEF and possibly modified non-sef.
	 *
	 * @param string $target
	 * @return array
	 */
	private function routeTarget($target)
	{
		if (Wb\startsWith($target, 'index.php?option=com_'))
		{
			return $this->routeNonSef($target);
		}

		return [
			'sef'    => $target,
			'nonsef' => null
		];
	}

	/**
	 * Process an incoming non-SEF URL into a SEF. Returns both the SEF and the
	 * possibly modified non-sef stored.
	 *
	 * Returns null if non-sef was found to be invalid.
	 *
	 * @param string $nonSef
	 * @return array|null
	 */
	private function routeNonSef($nonSef)
	{
		if (self::HOME_PAGE_ID !== $nonSef)
		{

			$nonSef = $this->platform->stripLangVarIfUseless(
				$nonSef,
				false // $checkCurrentLanguage
			);

			if (empty($nonSef))
			{
				$this->logger->custom('import', 'Invalid non-sef URL found in record, skipping.');

				return null;
			}
		}

		$sef = self::HOME_PAGE_ID === $nonSef
			? ''
			: $this->platform->relativeRoute($nonSef);

		$this->logger->custom('import', 'sh404SEF meta import computed SEF URL: ' . $sef);

		return [
			'sef'    => $sef,
			'nonsef' => $nonSef
		];
	}
}
