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

namespace Weeblr\Forsef\Helper;

use Joomla\CMS\Categories\Categories;

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Model;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Slugs extends Base\Base
{
	/**
	 * @var Model\Config
	 */
	protected $routingConfig;

	/**
	 * @var Db\Helper
	 */
	protected $db;

	/**
	 * @var array Memoize articles defs
	 */
	private static $articles = [];

	/**
	 * @var array Memoize categories defs
	 */
	private static $categories = [];

	/**
	 * @var array Memoize "uncategorized" category details per extension
	 */
	private static $uncategorizedCat = [];

	/**
	 * Store a logger and config for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->db            = $this->factory->getThe('db');
		$this->routingConfig = $this->factory->getThis('forsef.config', 'routing');
	}

	/**
	 * Read an article details from database.
	 *
	 * @param int $id
	 * @return \stdClass
	 * @throws \Exception
	 */
	public function getArticle($id)
	{
		// sanitize input
		$id = (int)$id;
		if (empty($id))
		{
			throw new \Exception('Invalid article id passed to ' . __METHOD__ . ' in ' . __CLASS__, 500);
		}

		// already cached ?
		if (empty(self::$articles[$id]))
		{
			// read details about the article
			$article = $this->db->selectObject(
				'#__content',
				['id', 'title', 'alias', 'catid', 'language'],
				['id' => $id]
			);

			// if not found, that's bad
			if (empty($article))
			{
				throw new \Exception('Non existing article id (' . $id . ') passed to ' . __METHOD__ . ' in ' . __CLASS__, 500);
			}

			// store our cached record
			self::$articles[$id][$article->language] = $article;
		}

		return self::$articles[$id];
	}

	/**
	 * Builds the slugs array for an article, based on routing config.
	 *
	 * @param int    $id
	 * @param bool   $useAlias
	 * @param bool   $insertId
	 * @param bool   $insertIdCatList
	 * @param string $requestedLanguage
	 * @param string $separator
	 * @return string
	 * @throws \Exception
	 */
	public function getArticleSlug($id, $useAlias, $insertId, $insertIdCatList, $requestedLanguage = '*', $separator = '')
	{
		$rawArticle = $this->getArticle($id);

		// select language
		$language = $requestedLanguage;
		if (empty($rawArticle[$language]))
		{
			$language = '*';
		}
		// still no luck, use whatever is available
		if (empty($rawArticle[$language]))
		{
			$languages = array_keys($rawArticle);
			$language  = array_shift($languages);
		}

		// must insert id ?
		$insertId = $this->shouldInsertArticleId(
			$insertId,
			$insertIdCatList,
			$rawArticle[$language]->catid
		);

		$slug = '';
		if (!empty($insertId))
		{
			$separator = empty($separator)
				? $this->routingConfig->get('spacer')
				: $separator;
			if ($insertId === 1)
			{
				// prepend to title
				$slug = $id . $separator;
			}
		}

		$slug .= $useAlias
			? $rawArticle[$language]->alias
			: $rawArticle[$language]->title;

		if ($insertId === 2)
		{
			$slug .= $separator . $id;
		}

		return $slug;
	}

	/**
	 * Whether a specific category is set to include article id in slug.
	 *
	 * @param bool  $insertId
	 * @param array $insertIdCatList
	 * @param int   $catId
	 * @return false
	 */
	protected function shouldInsertArticleId($insertId, $insertIdCatList, $catId)
	{
		if (empty($insertId) || empty($catId))
		{
			return false;
		}

		return
			(
				!empty($insertIdCatList)
				&&
				empty($insertIdCatList[0])
			)
			||
			in_array($catId, $insertIdCatList)
				? $insertId
				: false;
	}

	/**
	 * Get an object describing a category, for the
	 * purpose of 4SEF usage, for a Joomla! category
	 *
	 * @param string  $extension extension for which category is searched
	 * @param integer $id        id of requested category
	 *
	 * @return mixed
	 * @throws \Exception if invalid id
	 */
	public function getCategory($extension, $id)
	{
		// sanitize input
		$extension = strtolower($extension);
		if (
			empty($extension)
			||
			!Wb\startsWith($extension, 'com_')
		)
		{
			throw new \Exception('Invalid extension (' . $extension . ') passed to ' . __METHOD__ . ' in ' . __CLASS__, 500);
		}

		$id = (int)$id;
		if (empty($id))
		{
			throw new \Exception('Invalid category id passed to ' . __METHOD__ . ' in ' . __CLASS__, 500);
		}

		// check if cached, create if not
		if (
			empty(self::$categories[$extension])
			||
			empty(self::$categories[$extension][$id])
		)
		{
			// get the Joomla! built category node
			$options = [
				'access'    => false,
				'published' => 0
			];

			$categories = Categories::getInstance(str_replace('com_', '', $extension), $options);

			// and ask for the category Joomla! object
			$node = $categories->get($id);

			// no data? error
			if (empty($node))
			{
				throw new \Exception('Non existing category id (' . $id . ') passed to ' . __METHOD__ . ' in ' . __CLASS__, 500);
			}

			// we have an object, build our record
			$cat            = new \stdClass();
			$cat->id        = $node->id;
			$cat->extension = $node->extension;
			$cat->title     = $node->title;
			$cat->alias     = $node->alias;
			$cat->language  = $node->language;
			$cat->params    = $node->params;
			$cat->metadesc  = $node->metadesc;
			$cat->metakey   = $node->metakey;
			$cat->metadata  = $node->metadata;
			$cat->pathArray = $this->buildCategoryRawNodePathArray(
				$node
			);

			self::$categories[$extension][$id][$node->language] = $cat;
		}

		// do we now have the category? if not, throw Exception
		if (
			empty(self::$categories[$extension])
			||
			empty(self::$categories[$extension][$id])
		)
		{
			throw new \Exception('Non existing category id (' . $id . ') passed to ' . __METHOD__ . ' in ' . __CLASS__, 500);
		}

		return self::$categories[$extension][$id];
	}

	/**
	 * Builds the slugs array for a category, based on routing config.
	 *
	 * @param string $extension
	 * @param int    $id
	 * @param int    $whichCat
	 * @param bool   $useAlias
	 * @param bool   $insertId
	 * @param string $requestedLanguage
	 * @param string $separator
	 * @return array
	 * @throws \Exception
	 */
	private function getCategoryPathArray($extension, $id, $whichCat, $useAlias, $insertId, $requestedLanguage = '*', $separator = '')
	{
		// get full category data
		$rawCat = $this->getCategory($extension, $id);

		// select language
		$language = $requestedLanguage;
		if (empty($rawCat[$language]))
		{
			$language = '*';
		}

		// still no luck, use whatever is available
		if (empty($rawCat[$language]))
		{
			$languages = array_keys($rawCat);
			$language  = array_shift($languages);
		}

		// break reference
		if (!empty($rawCat[$language]))
		{
			$copyCat = clone($rawCat[$language]);
		}
		else
		{
			throw new \Exception(
				'Language (' . $requestedLanguage . ') not found in categories list, ' . __METHOD__ . ' in '
				. __CLASS__, 500
			);
		}

		// only keep appropriate parts, according to request
		switch ($whichCat)
		{
			case Data\Config::CAT_ALL_NESTED:
				$pathArray = $copyCat->pathArray;
				break;
			case Data\Config::CAT_NONE:
				$pathArray = array();
				break;
			case Data\Config::CAT_FIRST:
				$pathArray = array(array_shift($copyCat->pathArray));
				break;
			case Data\Config::CAT_LAST:
				$pathArray = array(array_pop($copyCat->pathArray));
				break;
			case Data\Config::CAT_FIRST_TWO:
				$pathArray = $copyCat->pathArray;
				while (count($pathArray) > 2)
				{
					array_pop($pathArray);
				}
				break;
			case Data\Config::CAT_LAST_TWO:
				$pathArray = $copyCat->pathArray;
				while (count($pathArray) > 2)
				{
					array_shift($pathArray);
				}
				break;
			default:
				throw new \Exception(
					'Invalid configuration option (' . print_r($id) . ') passed to ' . __METHOD__ . ' in ' . __CLASS__,
					500
				);;
				break;
		}
		// build slug, according to request
		foreach ($pathArray as $key => $value)
		{
			$pathArray[$key]->slug = $useAlias ? $pathArray[$key]->alias : $pathArray[$key]->title;
			if ($insertId)
			{
				$separator             = empty($separator) ? $this->routingConfig->get('spacer') : $separator;
				$pathArray[$key]->slug = $pathArray[$key]->id . $separator . $pathArray[$key]->slug;
			}
		}

		// return formatted Path
		return empty($pathArray) ? array() : $pathArray;
	}

	/**
	 * Builds the slugs array for a category, based on routing config.
	 *
	 * @param string $extension
	 * @param int    $id
	 * @param int    $whichCat
	 * @param bool   $useAlias
	 * @param bool   $insertId
	 * @param string $requestedLanguage
	 * @param string $separator
	 * @return array
	 * @throws \Exception
	 */
	public function getCategorySlugArray($extension, $id, $whichCat, $useAlias, $insertId, $uncategorizedPath = '', $requestedLanguage = '*',
										 $separator = '')
	{
		// special case for the "uncategorised" category
		$unCat = $this->getUncategorizedCat($extension);
		if (
			!empty($unCat)
			&& $id == $unCat->id
		)
		{
			$slug = $useAlias
				? $unCat->title
				: $unCat->alias;
			return empty($uncategorizedPath)
				? [$slug]
				: [$uncategorizedPath, $slug];
		}

		// regular category, build the path to the cat
		$separator = empty($separator)
			? $this->routingConfig->get('spacer')
			: $separator;

		$pathArray = $this->getCategoryPathArray(
			$extension,
			$id,
			$whichCat,
			$useAlias,
			$insertId,
			$requestedLanguage,
			$separator
		);

		$slugArray = [];
		foreach ($pathArray as $catObject)
		{
			$slugArray[] = $catObject->slug;
		}

		return $slugArray;
	}

	/**
	 *
	 * Get details of the "Uncategorized" category for a given extension,
	 * storing the result in a cache variable
	 *
	 * @param string $extension full name of extension, ie "com_content"
	 *
	 * @return array
	 */
	public function getUncategorizedCat($extension = 'com_content')
	{
		// if not already in cache
		if (!isset(self::$uncategorizedCat[$extension]))
		{

			try
			{
				self::$uncategorizedCat[$extension] = $this->db->selectObject(
					'#__categories',
					'*',
					'parent_id > 0 and extension = ? and path = ? and level = ?',
					[
						$extension,
						'uncategorised',
						1
					]
				);
			}
			catch (\Exception $e)
			{

				self::$uncategorizedCat[$extension] = null;
			}
		}

		return self::$uncategorizedCat[$extension];
	}

	/**
	 * Build an array holding the various path items
	 * for a given category, as described by a CategoryNode object
	 * complying with general SEF url generation parameters
	 *
	 * @param CategoryNode object $node the category node
	 *
	 * @return array
	 */
	private function buildCategoryRawNodePathArray($node)
	{
		// holds result
		$pathArray = array();

		// iterate over node parent cats
		$safer = 0;
		do
		{
			$tmp         = new \stdClass();
			$tmp->id     = $node->id;
			$tmp->title  = $node->title;
			$tmp->alias  = $node->alias;
			$tmp->slug   = '';
			$pathArray[] = $tmp;
			$node        = $node->getParent();
			$isRoot      = empty($node) || $node->id == 'root';
			$safer++;
		} while (!$isRoot && $safer < 20);

		// get first things first
		return array_reverse($pathArray);
	}

}
