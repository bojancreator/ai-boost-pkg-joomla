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

namespace Weeblr\Forseo\Model;

use Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Aliases extends Db\Dataobjectlist
{
	/**
	 * @var string Name of the ordering column if none specified.
	 */
	protected $defaultOrderBy = 'alias';

	/**
	 * @var array List of columns that can be used to order lists.
	 */
	protected $orderableColumns = [
		'alias'
	];

	/**
	 * Store information about managed data.
	 *
	 * @param string $dataObjectClass
	 */
	public function __construct($dataObjectClass = null)
	{
		$this->dataObjectClass = Data\Alias::class;

		parent::__construct();

		$this->defaultItemsPerPage = $this->factory
			->getThis(
				'forseo.config',
				'system'
			)->getInt(
				'defaultItemsPerPage',
				10
			);
	}

	/**
	 * Take end-of-line chars separated list of aliases for a URL and update database records
	 * accordingly.
	 *
	 * @param string $rawAliasesRecord
	 * @return array
	 * @throws \Throwable
	 */
	public function saveFromInput($rawAliasesRecord)
	{
		if (empty($rawAliasesRecord))
		{
			throw new \Exception('No data provided to save aliases', System\Http::RETURN_BAD_REQUEST);
		}

		$url = StringHelper::trim(
			Wb\arrayGet($rawAliasesRecord, 'url', '')
		);

		$rawAliases = Wb\arrayGet($rawAliasesRecord, 'aliases', '');
		$aliases    = System\Strings::stringToCleanedArray(
			$rawAliases,
			"\n"
		);

		try
		{
			$indexableUrl = $this->dbHelper->storageSafe($url);

			$this->dbHelper->db()->transactionStart();

			// read all current aliases for this URL
			$existingAliases = $this->dbHelper->selectColumn(
				'#__forseo_pages_aliases',
				'full_alias',
				[
					'url' => $indexableUrl,
				]
			);

			// delete all aliases that are not in the new list
			$toDeleteAliases = array_diff(
				$existingAliases,
				$aliases
			);
			if (!empty($toDeleteAliases))
			{
				$toDeleteAliases = array_map(
					function ($alias)
					{
						return $this->dbHelper->storageSafe($alias);
					},
					$toDeleteAliases
				);

				$this->dbHelper->deleteIn(
					'#__forseo_pages_aliases',
					'alias',
					$toDeleteAliases
				);
			}

			// add all aliases that are not in the current list
			$toAddAliases = array_diff(
				$aliases,
				$existingAliases
			);

			$additionWarnings = [];

			if (!empty($toAddAliases))
			{
				foreach ($toAddAliases as $alias)
				{

					$otherUrlWithSameAlias = $this->dbHelper->selectResult(
						'#__forseo_pages_aliases',
						'full_url',
						[
							'alias' => $this->dbHelper->storageSafe($alias),
							['url', '!=', $indexableUrl],
						]
					);

					if (!empty($otherUrlWithSameAlias))
					{
						$additionWarnings[$alias] = $otherUrlWithSameAlias;
						continue;
					}

					$this->factory->getA(
						Data\Alias::class
					)->set(
						[
							'full_url'   => $url,
							'full_alias' => $alias
						]
					)->store();
				}
			}

			$this->dbHelper->db()->transactionCommit();

			if (!empty($additionWarnings))
			{
				$list = [];
				foreach ($additionWarnings as $alias => $otherUrl)
				{
					$list[] = '<strong>' . $alias . '</strong> (/' . $otherUrl . ')';
				}
				return [
					'status' => System\Http::RETURN_BAD_REQUEST,
					'data'   => [
						'message'        => 'aliases.someAliasesAlreadyUsed',
						'details'        => 'aliases.someAliasesAlreadyUsedDetails',
						'failingAliases' => [
							'<ul><li>' . implode('</li><li>', $list) . '</li></ul>'
						],
					],
				];
			}
		}
		catch (\Throwable $e)
		{
			$this->dbHelper->db()->transactionRollback();
			$this->factory->getThe('forseo.logger')->error(__METHOD__ . ' %s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			throw $e;
		}
	}

	/**
	 * Lookup the provided string in the aliases db table and return the corresponding
	 * full target URL if found.
	 *
	 * @param string $alias
	 * @return Data\Alias
	 */
	public function lookupAlias($alias)
	{
		$aliasData = $this->dbHelper->selectAssoc(
			'#__forseo_pages_aliases',
			'*',
			[
				'alias'   => $this->dbHelper->storageSafe($alias),
				'enabled' => Data\Url::ENABLED
			]
		);

		$alias = $this->factory->getA(
			Data\Alias::class
		);

		if (!empty($aliasData))
		{
			$alias->set(
				$aliasData
			);
		}

		return $alias;
	}
}
