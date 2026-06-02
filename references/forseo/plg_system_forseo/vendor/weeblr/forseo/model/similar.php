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
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Similar extends Base\Base
{
	/**
	 * Max number of URLs returned when asked for similar pages
	 */
	public const MAX_SIMILAR = 4;

	/**
	 * Max number of candidate pages pulled from database with before final ranking
	 */
	public const MAX_EXAMINED = 200;

	/**
	 * Max number of requested URL bits to use when searching the database.
	 */
	public const MAX_DB_SEARCH_BITS = 12;

	/**
	 * We discard common extensions from pages to search for similar pages.
	 */
	public const TRIMMABLE_EXTENSIONS = [
		'.',
		'.html',
		'.htm',
		'.php',
		'.json',
		'.raw',
		'.pdf',
		'.exe',
		'.doc',
		'.docx',
		'.xls',
		'.xlsx'
	];

	public const MIN_SEGMENT_LENGTH = 4;

	/**
	 * @var Db\Dbhelper Database helper instance.
	 */
	private $dbHelper;

	private $cleanedUrl = '';
	private $segments   = [];
	private $candidates = [];
	private $ranked     = [];
	private $similar    = [];

	/**
	 * Finds a list of this site pages that are similar to the URL provided
	 *
	 * @param string $requestedUrl
	 * @return array
	 */
	public function find($requestedUrl)
	{
		$this->cleanedUrl = Wb\rTrim(
			StringHelper::strtolower($requestedUrl),
			self::TRIMMABLE_EXTENSIONS
		);

		$this->dbHelper = $this->factory->getThe('db');

		$this->buildUrlSegments()
			 ->searchSimilarPages()
			 ->rankCandidates()
			 ->loadSimilarPagesTitles();

		return $this->similar;
	}

	/**
	 * Read identified similar pages page titles from database to later
	 * display them more efficiently than just the URL.
	 *
	 * @return $this
	 */
	private function loadSimilarPagesTitles()
	{
		if (empty($this->ranked))
		{
			return $this;
		}

		// search page title from meta
		$metaDatas = $this->dbHelper->selectAssocList(
			'#__forseo_custom_meta',
			[
				'data',
				'url'
			],
			$this->dbHelper->qn('url') . ' in (' . $this->dbHelper->arrayToQuotedList(array_keys($this->ranked)) . ')',
			[],
			[],
			0,
			0,
			'url'
		);

		$titles = [];
		foreach ($metaDatas as $url => $metaData)
		{
			$data  = json_decode($metaData['data'], true);
			$title = Wb\arrayGet($data, ['custom', 'title']);
			if (empty($title))
			{
				$title = Wb\arrayGet($data, ['platform', 'title']);
			}
			if (empty($title))
			{
				$title = $this->ranked[$url]['full_url'];
			}
			$titles[$url] = $title;
		}

		// final massaging
		$this->similar = [];
		$rootUrl       = $this->platform->getBaseUrl() . $this->platform->getUrlRewritingPrefix();
		$rootUrl       = empty($rootUrl)
			? '/'
			: $rootUrl;
		foreach ($this->ranked as $url => $record)
		{
			if (empty($titles[$url]))
			{
				continue;
			}

			$absUrl                 = Wb\slashTrimJoin(
				$rootUrl,
				$this->candidates[$url]['full_url']
			);
			$this->similar[$absUrl] = $titles[$url];
		}

		return $this;
	}

	/**
	 * Rank candidate similar URLs based on their similarity percentage. Also trim the list
	 * to a small number.
	 *
	 * Tested with multiple methods, Smith Waterman Gotoh, Jaro Winkler, mixed with some heuristics
	 * In the end, for similar URLs, similar_text yields the best results, fast.
	 *
	 * @return $this
	 */
	private function rankCandidates()
	{
		if (empty($this->candidates))
		{
			return $this;
		}

		$ranked = [];
		foreach ($this->candidates as $url => $candidate)
		{
			similar_text(
				$this->cleanedUrl,
				$candidate['full_url'],
				$percent
			);
			$ranked[$url] = $percent;
		}

		arsort($ranked);

		$hooks = $this->factory->getThe('hook');

		/**
		 * Filter the list of ranked similar pages candidates to be suggested on error pages.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\error
		 * @var forseo_error_page_ranked_candidates
		 *
		 * @param array  $ranked       An ordered list of similar pages URLs.
		 * @param string $requestedUrl The URL requested which caused a 404.
		 *
		 * @return array
		 *
		 * @since   1.5.0
		 */
		$ranked = $hooks->filter(
			'forseo_error_page_ranked_candidates',
			$ranked,
			$this->cleanedUrl
		);

		// keep only the top MAX_SIMILAR
		$this->ranked = array_slice(
			$ranked,
			0,
			/**
			 * Filter the max number of similar pages candidates to be suggested on error pages.
			 *
			 * @api     forseo
			 * @package 4SEO\filter\frontend\error
			 * @var forseo_error_page_max_similar_pages
			 *
			 * @param int $maxSimilar
			 *
			 * @return int
			 *
			 * @since   1.5.0
			 */
			$hooks->filter(
				'forseo_error_page_max_similar_pages',
				(int)self::MAX_SIMILAR
			)
		);

		return $this;
	}

	/**
	 * Search the Pages table for pages that have a URL similar to the currently
	 * requested 404. This large number of candidates will be later ranked and trimmed
	 * to a short list.
	 *
	 * @return $this
	 */
	private function searchSimilarPages()
	{
		if (empty($this->segments))
		{
			return $this;
		}

		$query = 'select ' . $this->dbHelper->qn('full_url') . ', ' . $this->dbHelper->qn('url')
				 . ' from ' . $this->dbHelper->qn('#__forseo_pages')
				 . ' where ' . $this->dbHelper->qn('url') . ' != ""'
				 . ' and ' . $this->dbHelper->qn('status') . ' = 0';

		$likeQuery = [];
		foreach ($this->segments as $segment)
		{
			$likeQuery[] = $this->dbHelper->qn('url') . ' like ' . $this->escapeLike('%' . $segment . '%');
		}
		if (!empty($likeQuery))
		{
			$query .= ' and ('
					  . implode(' or ', $likeQuery)
					  . ')';
		}

		$query .= Wb\join(
			'',
			' and (',
			'(',
			$this->dbHelper->qn('canonical_mode') . ' = ' . Data\Page::AUTO,
			' and ',
			$this->dbHelper->qn('canonical_auto') . ' = ' . Data\Page::CANONICAL,
			')',
			' or ',
			'(',
			$this->dbHelper->qn('canonical_mode') . ' = ' . Data\Page::USER,
			' and ',
			$this->dbHelper->qn('canonical_user') . ' = ' . Data\Page::CANONICAL,
			')',
			')'
		);

		$query .= ' and ' . $this->dbHelper->qn('url') . ' not like ' . $this->escapeLike('%?start=%');

		$query .= ' limit ' . self::MAX_EXAMINED;

		$this->candidates = $this->dbHelper
			->setQueryAnd($query)
			->loadAssocList('url');

		return $this;
	}

	/**
	 * Split current 404 request in words, to be used later to search the database.
	 *
	 * @return $this
	 */
	private function buildUrlSegments()
	{
		if (empty($this->cleanedUrl))
		{
			return $this;
		}

		$bits     = explode('/', $this->cleanedUrl);
		$segments = [];
		foreach ($bits as $bit)
		{
			$bit     = str_replace(['-', '_', '.'], ' ', $bit);
			$subBits = explode(' ', $bit);
			foreach ($subBits as $subBit)
			{
				if (StringHelper::strlen($subBit) >= self::MIN_SEGMENT_LENGTH)
				{
					$segments[] = $subBit;
				}
			}
		}

		$this->segments = array_slice(
			$segments,
			0,
			self::MAX_DB_SEARCH_BITS
		);

		return $this;
	}

	/**
	 * Escape a MYSQL LIKE clause value, escaping underscores.
	 *
	 * @param string $string
	 * @return string
	 */
	private function escapeLike($string)
	{
		return str_replace('_', '\_', $this->dbHelper->q($string));
	}
}
