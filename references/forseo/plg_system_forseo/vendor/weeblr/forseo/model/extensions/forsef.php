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

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Forsef extends Base\Base
{
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

		$this->logger = $this->factory->getThe('forseo.logger');
	}

	/**
	 * Hook handler ran when a 4SEF URL pair is manually customized.
	 *
	 * @param array  $data
	 * @param string $originalBasePath
	 * @param array  $customizedSefs
	 * @param string $originalSef
	 * @param string $extraPathLeadingSlash
	 * @param bool   $customizeDuplicates
	 *
	 * @return void
	 * @throws \Throwable
	 */
	public function onUrlCustomized($data, $originalBasePath, $customizedSefs, $originalSef, $extraPathLeadingSlash, $customizeDuplicates)
	{
		$this->logger->debug(__METHOD__ . ', 4SEO URL customized handler, from ' . $originalBasePath . " to \n" . print_r($data, true) . "\n" . print_r($customizedSefs, true));

		// prepare
		$newBasePath = Wb\arrayGet(
			$data,
			'base_path'
		);

		if ($newBasePath === $originalBasePath)
		{
			$this->logger->debug(__METHOD__ . ', 4SEO URL customized handler: original SEF and customized one are the same, cannot add a redirect for ' . print_r($originalBasePath, true));

			return;
		}

		try
		{
			// should change the main URL
			$targetSef = Wb\arrayGet(
				$data,
				'sef',
				''
			);
			$this->updateRecords(
				$originalSef,
				$targetSef,
				true
			);

			// and all other customized variants
			if ($customizeDuplicates)
			{
				foreach ($customizedSefs as $customizedSef)
				{
					$newSef = $newBasePath
							  . $extraPathLeadingSlash
							  . StringHelper::substr($customizedSef, StringHelper::strlen($originalBasePath));

					if ($newSef === $targetSef)
					{
						continue;
					}
					$this->updateRecords(
						$customizedSef,
						$newSef,
						false
					);
				}
			}
		}
		catch (\Throwable $e)
		{
			$this->logger->error(__METHOD__ . ' %s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Performs all db updates after a 4SEF URL pair is manually customized.
	 *
	 * @param string $sourceSef
	 * @param string $targetSef
	 * @param bool   $cleanBefore
	 * @return void
	 * @throws \Throwable
	 */
	private function updateRecords($sourceSef, $targetSef, $cleanBefore)
	{
		$dbHelper = $this->factory->getThe('db');

		try
		{
			$indexableSource    = $dbHelper->storageSafe($sourceSef);
			$indexableTargetSef = $dbHelper->storageSafe($targetSef);

			$this->logger->debug(__METHOD__ . ', 4SEO URL customized handler: updating records for ' . $indexableSource . ' to ' . $indexableTargetSef . ' cleanBefore=' . $cleanBefore);

			$dbHelper->db()->transactionStart();

			// update canonical includes
			$targetExists = $dbHelper->count(
					'#__forseo_canonical_includes',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;

			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_canonical_includes',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_canonical_includes',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_canonical_includes',
					[
						'url' => $indexableTargetSef,
					],
					[
						'url' => $indexableSource
					]
				);
			}

			// update collected URLs
			// NB: we do not attempt to update all the referrers links. This would require to run a full text search
			// of the referrers column to identify rows that have the modified URLs as a referrer, and then update
			// each of them. This can lead to gigantic volumes of dbHelper writes. Only way out is to reset analysis and re-crawl.

			$targetExists = $dbHelper->count(
					'#__forseo_collected_urls',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;

			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_collected_urls',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_collected_urls',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_collected_urls',
					[
						'url'      => $indexableTargetSef,
						'full_url' => $targetSef
					],
					[
						'url' => $indexableSource
					]
				);
			}

			// update custom meta
			$targetExists = $dbHelper->count(
					'#__forseo_custom_meta',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;

			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_custom_meta',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_custom_meta',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_custom_meta',
					[
						'url' => $indexableTargetSef,
					],
					[
						'url' => $indexableSource
					]
				);
			}

			// update excluded URLs
			$targetExists = $dbHelper->count(
					'#__forseo_excluded_urls',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;


			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_excluded_urls',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_excluded_urls',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_excluded_urls',
					[
						'url'      => $indexableTargetSef,
						'full_url' => $targetSef
					],
					[
						'url' => $indexableSource
					]
				);
			}

			// update images
			$targetExists = $dbHelper->count(
					'#__forseo_images',
					'*',
					[
						'page_url' => $indexableTargetSef,
					]
				) > 0;


			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_images',
					[
						'page_url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_images',
					[
						'page_url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_images',
					[
						'page_url'      => $indexableTargetSef,
						'page_full_url' => $targetSef
					],
					[
						'page_url' => $indexableSource,
					]
				);
			}

			// update links: not updated as we do not know if these links (which are links
			// which triggers a redirect) are the result of a SEF URL action.
			// That's true of pages one could say, but more likely with links.

			// update pages
			$targetExists = $dbHelper->count(
					'#__forseo_pages',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;

			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_pages',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_pages',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_pages',
					[
						'url'      => $indexableTargetSef,
						'full_url' => $targetSef
					],
					[
						'url' => $indexableSource
					]
				);
			}

			// update perf_data
			$targetExists = $dbHelper->count(
					'#__forseo_perf_data',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;

			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_perf_data',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_perf_data',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_perf_data',
					[
						'url'      => $indexableTargetSef,
						'full_url' => $targetSef
					],
					[
						'url' => $indexableSource
					]
				);
			}

			// update perf_data_agg
			$targetExists = $dbHelper->count(
					'#__forseo_perf_data_agg',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;

			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_perf_data_agg',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_perf_data_agg',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_perf_data_agg',
					[
						'url'      => $indexableTargetSef,
						'full_url' => $targetSef
					],
					[
						'url' => $indexableSource
					]
				);
			}

			// update referrers
			$targetExists = $dbHelper->count(
					'#__forseo_referrers',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;

			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_referrers',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_referrers',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_referrers',
					[
						'url'      => $indexableTargetSef,
						'full_url' => $targetSef
					],
					[
						'url' => $indexableSource
					]
				);
			}

			// update sitemaps_includes
			$targetExists = $dbHelper->count(
					'#__forseo_referrers',
					'*',
					[
						'url' => $indexableTargetSef,
					]
				) > 0;

			if ($targetExists)
			{
				$dbHelper->delete(
					'#__forseo_sitemaps_includes',
					[
						'url' => $indexableSource,
					]
				);
			}
			else
			{
				$dbHelper->delete(
					'#__forseo_sitemaps_includes',
					[
						'url' => $indexableTargetSef,
					]
				);
				$dbHelper->update(
					'#__forseo_sitemaps_includes',
					[
						'url' => $indexableTargetSef,
					],
					[
						'url' => $indexableSource
					]
				);
			}

			$dbHelper->db()->transactionCommit();

		}
		catch (\Throwable $e)
		{
			$dbHelper->db()->transactionRollback();
			$this->logger->error(__METHOD__ . ' %s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			throw $e;
		}
	}
}
