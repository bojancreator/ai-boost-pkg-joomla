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

namespace Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Referrer extends Base\Base
{
	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * Store a logger for convenience.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->logger = $this->factory->getThe('forseo.logger');
	}

	/**
	 * Stores referrer information for a an Error or a Link.
	 *
	 * @param   Data\Link|Data\Error  $page
	 * @param   string                $referrerFullUrl
	 *
	 * @throws \Exception
	 */
	public function store($page, $referrerFullUrl)
	{
		// referrer can be empty - that would be the home page
		// but cannot be null - that would be when recording an incoming 404 for instance.
		if (is_null($referrerFullUrl))
		{
			$this->logger->debug(static::class . ' No referrer to store for url ' . $page->get('url'));

			return;
		}

		$this->logger->debug(static::class . ' Storing referrer ' . $referrerFullUrl . ' for page/error ' . $page->get('url'));

		// search any referrer for the provided URL
		$referrer = $this->factory
			->getA(Data\Referrer::class)
			->loadPerUrl(
				$referrerFullUrl
			);

		// can do better than that
		switch (true)
		{
			case $page instanceof Data\Page:
				$referrerXRef = $this->factory
					->getA(Data\Referrerspages::class);
				break;
			case $page instanceof Data\Error:
				$referrerXRef = $this->factory
					->getA(Data\Referrerserrors::class);
				break;
			case $page instanceof Data\Link:
				$referrerXRef = $this->factory
					->getA(Data\Referrerslinks::class);
				break;
			default:
				$this->logger->error(static::class . ' Storing referrer, unknown  page type for referrer' . $referrerFullUrl . ' for url ' . $page->get('url'));

				return;
		}

		if ($referrer->exists())
		{
			// referrer already exists, search for Xref
			$this->logger->debug(static::class . ' Referrer ' . $referrerFullUrl . ' already exists with ID:' . $referrer->getId());
			$referrerXRef->loadWhere(
				[
					'referrer_id' => $referrer->getId(),
					'referree_id' => $page->getid()
				]
			);
		}
		else
		{
			$this->logger->debug(static::class . ' Referrer ' . $referrerFullUrl . ' does not exist, adding it ');
			// referrer does not exist, add referrer and Xref
			$referrer->set('full_url', $referrerFullUrl)
				->store();
		}

		if (!$referrer->exists())
		{
			$this->logger->error(static::class . ' Unable to create and store referrer record. Referrer URL: ' . $referrerFullUrl . ' for url ' . $page->get('url'));

			return;
		}

		if (!$referrerXRef->exists())
		{
			// store X-ref
			$this->logger->debug(static::class . ' Storing referrer XREF, Referrer URL: ' . $referrerFullUrl . ' for url ' . $page->get('url') . ', page ID: ' . $page->getId());
			$referrerXRef->set('referrer_id', $referrer->getId())
				->set('referree_id', $page->getId())
				->store();
		}
		else
		{
			$this->logger->debug(static::class . ' Referrer XRef already exists. Referrer URL: ' . $referrerFullUrl . ' for url ' . $page->get('url'));
		}
	}
}