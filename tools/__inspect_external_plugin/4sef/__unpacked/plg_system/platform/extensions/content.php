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

use Weeblr\Forsef\Data;
use Weeblr\Forsef\Helper;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\System;
use Weeblr\Wblib\Forsef\Factory;
use Weeblr\Wblib\Forsef\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Content extends Base
{
	// When extracting sub-pages titles from multipage article,
	// they must still fit within the extra_path db column, which is 50 chars.
	// We trim to 40 chars, to leave some room for potential html suffix.
	const TITLE_TRIM_LENGTH_FOR_PAGINATION = 40;

	/**
	 * @var array Memoize articles read from database.
	 */
	static private $articlesCache = [];

	/**
	 * @var Helper\Platform
	 */
	private $platformHelper;

	/**
	 * Stores factory instance.
	 *
	 * @param string $option  Extension this applies to, in com_xxx format.
	 * @param array  $options Can inject custom factory and platform.
	 */
	public function __construct($option, $options = [])
	{
		parent::__construct($option, $options);

		$this->platformHelper = $this->factory
			->getA(Helper\Platform::class);
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
		$view = $uri->getVar('view');
		if (
			'article' === $view
			&&
			!empty($limitstartVar)
		)
		{
			// multipage article
			return false;
		}

		return parent::shouldAppendPaginationLimit($uri, $limitstartVar, $limitVar);
	}

	/**
	 * Figure out the default list limit value based on the nons-ef being built.
	 *
	 * @param Uri\Uri $uri
	 * @return mixed
	 */
	protected function getDefaultListlimit($uri)
	{
		return $this->platformHelper
			->getDefaultListLimit(
				$uri->getQuery(true)
			);
	}

	/**
	 * Builds the SEF URL for a non-sef.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @param Uri\Uri $platformUri
	 * @param Uri\Uri $originalUri
	 *
	 * @return \array
	 * @throws \Exception
	 */
	public function build($uriToBuild, $platformUri, $originalUri)
	{
		$sefSegments = parent::build($uriToBuild, $platformUri, $originalUri);

		$view      = $uriToBuild->getVar('view');
		$layout    = $uriToBuild->getVar('layout');
		$id        = $uriToBuild->getVar('id');
		$catid     = $uriToBuild->getVar('catid');
		$Itemid    = $uriToBuild->getVar('Itemid');
		$a_id      = $uriToBuild->getVar('a_id');
		$lang      = $uriToBuild->getVar('lang');
		$filterTag = $uriToBuild->getVar('filter_tag');

		if (
			!empty($id)
			&&
			'article' === $view
			&&
			$this->extensionsConfig->isTruthy('contentInsertNumericalId')
			&&
			$this->extensionsConfig->isTruthy('contentInsertNumericalIdCatList')
		)
		{
			try
			{
				$contentElement = $this->loadArticle($id);
				if (!empty($contentElement)
					&&
					in_array(
						$contentElement->catid,
						$this->extensionsConfig->get('contentInsertNumericalIdCatList', [])
					)
				)
				{
					// we use the raw created date - which is UTC - for B/C reason. The values won't refletc the
					// the website timezone but that does not really matters here, it's just an id.
					$bits          = explode(' ', $contentElement->created);
					$sefSegments[] = str_replace('-', '', $bits[0]) . $contentElement->id;
				}

			}
			catch (\Exception $e)
			{
				$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			}
		}

		if (
			!empty($id)
			&&
			'article' === $view
			&&
			$this->extensionsConfig->isTruthy('contentInsertDate')
			&&
			$this->extensionsConfig->isTruthy('contentInsertDateCatList')
		)
		{
			try
			{
				$contentElement = $this->loadArticle($id);
				if (!empty($contentElement)
					&&
					in_array(
						$contentElement->catid,
						$this->extensionsConfig->get('contentInsertDateCatList', [])
					)
				)
				{
					// We convert the created time - which is UTC - to display a date in website timezone.
					// This a/ is required for B/C and b/ makes more sense.
					$created = new \DateTime($contentElement->created . ' UTC');
					$created->setTimezone(
						new \DateTimeZone(
							System\Date::getTimezoneName()
						)
					);
					$sefSegments[] = $created->format('Y');
					$sefSegments[] = $created->format('m');
					$sefSegments[] = $created->format('d');
				}
			}
			catch (\Exception $e)
			{
				$this->logger->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			}
		}

		if (
			$layout === 'edit'
			&&
			empty($a_id)
		)
		{
			// submit new article
			// must differentiate view=form urls from task=article.edit or article add, otherwise joomla loops
			$sefSegments[] = 'f';
			if (
				empty($catid)
				&&
				!empty($Itemid)
			)
			{
				// maybe a specific category
				$menu     = $this->platform->getMenu('site');
				$menuItem = $menu->getItem($Itemid);
				if (!empty($menuItem))
				{
					$catid = $menuItem->getParams()->get('catid');
				}
			}
			if (!empty($catid))
			{
				$sefSegments[] = $catid;
			}
			$sefSegments[] = $this->t($lang, 'CREATE_NEW');
		}

		if (empty($layout) || (!empty($layout) && $layout != 'edit'))
		{
			$contentSefSegments = $this->builderHelper
				->getContentSlugs(
					$view,
					$id,
					$layout,
					[
						'Itemid' => $Itemid,
						// @TODO: manage language here?
						'shLang' => null
					]
				);

			$sefSegments = array_merge(
				$sefSegments,
				$contentSefSegments
			);

			// We know these 3 will always be used
			$platformUri->delVar('view');
			$platformUri->delVar('id');
			$platformUri->delVar('catid');

			// and in some cases, Itemid will prevent us from recognizing the page
			$platformUri->delVar('Itemid');

			// sometimes J4+ adds an format var to the URL for no reason
			$format = $uriToBuild->getVar('format');
			if ('html' === $format)
			{
				$platformUri->delVar('format');
			}
		}

		if (!empty($filterTag))
		{
			// leave filter_tag as query var
			$platformUri->setVar('filter_tag', $filterTag);
		}

		return $sefSegments;
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
		return $this->nonSefHelper->stripFeedVars(
			parent::buildNormalizedNonSef(
				$vars
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
		$platformUri->delVar('showall');

		return array_diff_key(
			$remainingVars,
			array_flip(
				[
					'showall',
				]
			)
		);
	}

	/**
	 * Check if passed URI is for an extension configured to be left non-sef.
	 *
	 * @param Uri\Uri $uriToBuild
	 * @return bool
	 */
	public function shouldLeaveNonSef($uriToBuild)
	{
		$view   = $uriToBuild->getVar('view');
		$task   = $uriToBuild->getVar('task');
		$layout = $uriToBuild->getVar('layout');
		$a_id   = $uriToBuild->getVar('a_id');
		switch ($view)
		{
			case 'archivecategory':
			case 'archivesection':
			case 'archive':
				return true;
			case 'form':
				return 'edit' !== $layout
					   ||
					   !empty($a_id);
			default:
				// editing content
				if (isset($a_id) && empty($view))
				{
					// front end editing
					return true;
				}
				else if ($task === 'article.edit' || $task === 'article.add')
				{
					return true;
				}
				else if ($layout === 'edit')
				{
					return true;
				}
		}

		return false;
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
		if (
			empty($uriToBuild->getVar('id'))
			||
			'article' !== $uriToBuild->getVar('view')
			||
			(
				!$uriToBuild->hasVar('start')
				&&
				!$uriToBuild->hasVar('limitstart')
				&&
				!$uriToBuild->hasVar('showall')
			)
		)
		{
			return parent::buildPagination(
				$urlPair,
				$uriToBuild,
				$platformUri
			);
		}

		if ($uriToBuild->hasVar('showall'))
		{
			$segment = $this->sefHelper->conformSegment(
				$this->extensionsConfig->get('showallSlug')
			);

			$platformUri->delVar('start');
			$platformUri->delVar('limitstart');
			$platformUri->delVar('showall');

			return '/' . $segment;
		}

		$contentElement = $this->loadArticle($uriToBuild->getVar('id'));
		$limitstartVar  = $uriToBuild->getVar(
			'start',
			$uriToBuild->getVar('limitstart')
		);

		if ($limitstartVar <= 0)
		{
			return '';
		}

		// Title set for this sub-page, use it
		if (!empty($contentElement->pagesDef[$limitstartVar - 1]))
		{
			$segment = $this->sefHelper->conformSegment(
				$contentElement->pagesDef[$limitstartVar - 1]
			);

			return '/' . $segment;
		}

		// not title provided, use a page number
		$limitVar = count($contentElement->pagesDef) + 1;

		return '/' . $this->buildPaginationString(
				$uriToBuild,
				$limitstartVar,
				$limitVar,
				$uriToBuild->getVar('lang')
			);
	}

	/**
	 * Load from db and memoize basic data about an article.
	 *
	 * @param int $id
	 * @return mixed
	 */
	private function loadArticle($id)
	{
		if (!isset(self::$articlesCache[$id]))
		{
			$fieldsToRead = $this->extensionsConfig->isTruthy('contentMultipagesTitle')
				? [
					'id',
					'catid',
					'created',
					'fulltext',
					'introtext'
				]
				: [
					'id',
					'catid',
					'created'
				];

			$articleRawData = $this->factory
				->getThe('db')
				->selectObject(
					'#__content',
					$fieldsToRead,
					[
						'id' => $id
					]
				);

			self::$articlesCache[$id] = false;
			if (!empty($articleRawData))
			{
				$articleObject          = new \stdClass;
				$articleObject->id      = $articleRawData->id;
				$articleObject->catid   = $articleRawData->catid;
				$articleObject->created = $articleRawData->created;
				if ($this->extensionsConfig->isTruthy('contentMultipagesTitle'))
				{
					$contentText                = $articleRawData->introtext . $articleRawData->fulltext;
					$articleObject->isMultipage = Wb\contains(
						$contentText,
						'class="system-pagebreak'
					);
					$articleObject->pagesDef    = [];

					if ($articleObject->isMultipage)
					{
						$pattern = '#<hr([^>]*)class=(?:\"|\')system-pagebreak(?:\"|\')([^>]*)\/>#iU';
						preg_match_all(
							$pattern,
							$contentText,
							$matches,
							PREG_SET_ORDER
						);
						$matches = Wb\arrayEnsure($matches);
						foreach ($matches as $match)
						{
							$match = array_values(
								array_filter($match)
							);
							if (
								empty($match)
								||
								count($match) < 2
							)
							{
								continue;
							}

							array_shift($match);
							$allAttributes = StringHelper::trim(
								implode(' ', $match)
							);
							$args          = System\Strings::parseAttributes($allAttributes);

							$title = Wb\arrayGet(
								$args,
								'title'
							);
							$title = empty($title)
								? Wb\arrayGet(
									$args,
									'alt'
								)
								: $title;

							$title = StringHelper::substr(
								$title,
								0,
								self::TITLE_TRIM_LENGTH_FOR_PAGINATION
							);

							if (!empty($title))
							{
								$articleObject->pagesDef[] = $title;
							}
						}
					}
				}

				self::$articlesCache[$id] = $articleObject;
			}
		}

		return self::$articlesCache[$id];
	}
}

