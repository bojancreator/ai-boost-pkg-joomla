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

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

use Weeblr\Forseo\Data;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Links extends Db\Dataobjectlist
{
	/**
	 * @var string Name of the ordering column if none specified. Prefix with - for DESC ordering.
	 */
	protected $defaultOrderBy = '-hits';

	/**
	 * Store information about managed data.
	 *
	 * @param string $dataObjectClass
	 */
	public function __construct($dataObjectClass = null)
	{
		$this->dataObjectClass = Data\Link::class;

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
	 * Method to extend the default where clause.
	 *
	 * @param array $options
	 * @param array $clause
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function extendWhereClause($options, $clause)
	{
		$statusClause = $this->buildStatusWhereClause($options);
		if (!empty($statusClause))
		{
			$clause[] = $statusClause;
		}

		$targetClause = $this->buildTargetWhereClause($options);
		if (!empty($targetClause))
		{
			$clause[] = $targetClause;
		}

		$typeClause = $this->buildTypeWhereClause($options);
		if (!empty($typeClause))
		{
			$clause[] = $typeClause;
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * status values.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildStatusWhereClause($options)
	{
		$status = Wb\arrayGet(
			$options,
			'status',
			'all'
		);

		$clause = '';
		switch ($status)
		{
			case '404':
				$clause = $this->dbHelper->quoteName('status')
						  . ' = ' . System\Http::RETURN_NOT_FOUND;
				break;
			case '500':
				$clause = $this->dbHelper->quoteName('status')
						  . ' = ' . System\Http::RETURN_INTERNAL_ERROR;
				break;
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * links target values.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildTargetWhereClause($options)
	{
		$target = Wb\arrayGet(
			$options,
			'target',
			'all'
		);

		$clause = '';
		if ('all' == $target)
		{
			return $clause;
		}

		switch ($target)
		{
			case Data\Url::TARGET_INTERNAL:
			case Data\Url::TARGET_EXTERNAL:
				$clause = $this->dbHelper->quoteName('target')
						  . ' = ' . (int)$target;
				break;
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * links type values.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildTypeWhereClause($options)
	{
		$type = Wb\arrayGet(
			$options,
			'type',
			'all'
		);

		$clause = '';
		switch ($type)
		{
			case 'errors':
				$clause = '('
						  . $this->dbHelper->quoteName('status')
						  . ' >= ' . System\Http::RETURN_BAD_REQUEST
						  . ' or'
						  . $this->dbHelper->quoteName('status')
						  . ' = ' . System\Http::RETURN_ZERO
						  . ')';
				break;
			case 'redirects':
				$clause = $this->dbHelper->quoteName('redirects_count')
						  . ' > 0 ';
				break;
		}

		return $clause;
	}

	/**
	 * Override handles special case where when searching for home page
	 * URL (ie /), we actually need to search for an empty URL.
	 *
	 * @param string      $term
	 * @param null|string $column
	 * @return mixed
	 */
	protected function rewriteSearchTerm($term, $column = null)
	{
		if (
			'/' === $term
			&&
			in_array(
				$column,
				[
					'url',
					'final_url'
				]
			)
		) {
			return '';
		}

		return $term;
	}
}
