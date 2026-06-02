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

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Urls extends Base\Base
{
	/**
	 * Store a crawled collected URL to the Links table.
	 *
	 * @param Data\Collected $url
	 * @param string         $lastCrawledUrl
	 * @param int            $redirectsCounter
	 *
	 * @return Urls
	 */
	public function storeToLink($url, $lastCrawledUrl, $redirectsCounter)
	{
		$link = $this->factory
			->getA(Data\Link::class)
			->set(
				'full_url',
				$url->get('full_url')
			)
			->loadPerUrl(
				$url->get('full_url')
			)->set(
				[
					'full_final_url'  => $lastCrawledUrl,
					'redirects_count' => $redirectsCounter,
					'status'          => $url->get('status')
				]
			)->timestamp('last_hit')
			->store();

		// if URL has a referrer, store that and the relationship
		$this->storeReferrers(
			$link,
			$url->get('referrers')
		);

		return $this;
	}

	/**
	 * Delete records for a given URL in the Pages.
	 *
	 * @param Data\Url $url
	 *
	 * @return Urls
	 */
	public function deleteFromPage($url)
	{
		// if that URL existed as a page or an error, we must remove it from there, it's now only a link
		$page = $this->factory
			->getA(Data\Page::class)
			->set(
				'full_url',
				$url->get('full_url')
			)->loadPerUrl(
				$url->get('full_url')
			);
		if ($page->exists())
		{
			$page->delete();
		}

		return $this;
	}

	/**
	 * Delete records for a give URL in the Pages or Error tables.
	 *
	 * @param Data\Url $url
	 *
	 * @return Urls
	 * @throws \Exception
	 */
	public function deleteFromError($url)
	{
		$error = $this->factory
			->getA(Data\Error::class)
			->set(
				'full_url',
				$url->get('full_url')
			)->loadPerUrl(
				$url->get('full_url')
			);
		if ($error->exists())
		{
			$this->factory
				->getA(Referrers::class)
				->purgeReferrersForUrl(
					$error
				);

			// safely delete now
			$error->delete();
		}

		return $this;
	}

	/**
	 * Delete records for a given URL in the Link table.
	 *
	 * @param Data\Url $url
	 *
	 * @return Urls
	 * @throws \Exception
	 */
	public function deleteFromLink($url)
	{
		$link = $this->factory
			->getA(Data\Link::class)
			->set(
				'full_url',
				$url->get('full_url')
			)->loadPerUrl(
				$url->get('full_url')
			);
		if ($link->exists())
		{
			$this->factory
				->getA(Referrers::class)
				->purgeReferrersForUrl(
					$link
				);

			// safely delete now
			$link->delete();
		}

		return $this;
	}

	/**
	 * Store the currently being collected URL to the crawl errors or links tables.
	 *
	 * @param Data\Collected $url
	 * @param int            $source
	 * @param string         $message
	 *
	 * @return Urls
	 * @throws \Exception
	 */
	public function storeToError($url, $source = Data\Url::SOURCE_UNKNOWN, $message = '')
	{
		$error = $this->factory
			->getA(Data\Error::class)
			->withData(
				$url->get()
			)->loadPerUrl(
				$url->get('full_url')
			)->timestamp('last_hit')
			->increment('hits')
			->set(
				'status',
				$url->get('status')
			)->set(
				'source',
				$source
			)->set(
				'message',
				$message
			)->store();

		// if URL has a referrer, store that and the relationship
		$this->storeReferrers(
			$error,
			$url->get('referrers')
		);

		return $this;
	}

	/**
	 * Stores a referrer reference if page does have referrer information.
	 *
	 * @param Data\Page | Data\Error | Data\Link $page
	 * @param array                              $referrers
	 *
	 * @return Urls
	 */
	public function storeReferrers($page, $referrers)
	{
		if (!empty($referrers))
		{
			foreach ($referrers as $referrer)
			{
				$this->factory
					->getA(Helper\Referrer::class)
					->store(
						$page,
						$referrer
					);
			}
		}

		return $this;
	}
}
