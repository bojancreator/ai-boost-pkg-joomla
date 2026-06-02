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
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Db;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Perf extends Base\Base
{
	public const PERF_DATA_TABLE = '#__forseo_perf_data';

	public const MIN_TIME_BETWEEN_DATA_POINTS = 5; // 5 minutes

	public const GC_DAYS = 28;

	/**
	 * @var System\Config Holds the page collection config object.
	 */
	private $pagesConfig = null;

	/**
	 * @var Db\Helper Database access helper.
	 */
	protected $dbHelper = null;

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

		$this->pagesConfig = $this->factory->getThis('forseo.config', 'pages');
		$this->dbHelper    = $this->factory->getThe('db');
		$this->logger      = $this->factory->getThe('forseo.logger');
	}

	/**
	 * Hard delete all performance data, individuals and aggregate.
	 *
	 * @return array
	 */
	public function reset()
	{
		$this->logger->debug(__METHOD__ . ': Resetting performance data.');

		$truncateTableList = [
			'#__forseo_perf_data',
			'#__forseo_perf_data_agg'
		];

		foreach ($truncateTableList as $table)
		{
			$this->dbHelper->truncate($table);
		}

		$this->dbHelper->update(
			'#__forseo_pages',
			[
				'perf_status' => Data\Page::PERF_NO_DATA
			]
		);

		return [];
	}

	/**
	 * Store a performance data set for a page.
	 *
	 * We check previous timestamp for same URL and device and compare it to threshold
	 * to avoid storing too much data.
	 *
	 * 300 secs threshold means < 300 data point per day per URL per device type.
	 *
	 * Throttling when we receive requests is needed in case of browser full page caching.
	 * Else we could do by only deciding to insert or not the snippet in the page.
	 *
	 * @param array $data
	 *
	 * @return $this
	 */
	public function store($data)
	{
		if ($this->factory->getThe('forseo.searchEnginesHelper')->isSearchEngineRequest())
		{
			return $this;
		}

		if ($this->pagesConfig->isFalsy('perfMeasurementEnabled'))
		{
			return $this;
		}

		// is this a known URL? disregard if not.
		if (!$this->factory->getA(Data\Page::class)->loadPerUrl($data['full_url'])->exists())
		{
			return $this;
		}

		// when was last record for that URL?
		$device = is_null($data['device'])
			? Data\Perf::UNKNOWN
			: $data['device'];
		$lastTs = $this->dbHelper
			->selectResult(
				'#__forseo_perf_data',
				['ts'],
				[
					'url'    => $data['url'],
					'device' => $device
				],
				[],
				[
					'ts' => 'desc'
				],
				0,  // start at first record
				1   // get only one record
			);

		$isDev = 'dev' === WBLIB_Forseo_OP_MODE;
		if (
			!$isDev
			&&
			!empty($lastTs)
			&&
			(($data['ts'] - $lastTs) < 1000 * 60 * $this->pagesConfig->getInt('perfProbeTimeBetweenDataPoints', self::MIN_TIME_BETWEEN_DATA_POINTS))
		) {
			// discard data point if too soon after previous
			return $this;
		}

		try
		{
			$this->factory
				->getA(Data\Perf::class)
				->set($data)
				->store();

			// NB: data are collected per device, but aggregated globally for now.
			// May provide ability to drll-down per device type in the future.
			$this->gc()
				 ->updateMetrics($data);

		}
		catch (\Throwable $e)
		{
			$this->logger->error(__METHOD__ . ' %s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $this;
	}

	/**
	 *
	 */
	private function updateMetrics($data, $device = Data\Perf::ANY)
	{
		$perfMetrics = $this->getPerfMetrics(
			$data['full_url'],
			$device
		);

		// write to aggregate table
		$this->factory->getA(Data\Perfaggregate::class)
					  ->loadPerUrl($data['full_url'])
					  ->set(
						  [
							  'full_url' => $data['full_url'],
							  'device'   => $device
						  ]
					  )->timestamp('modified_at')
					  ->set(
						  $perfMetrics
					  )->store();

		// update Pages record column for fast enough retrieval of failing pages.
		// This must be done also when a new page record is written to DB as
		// an analysis reset may cause all those perf_status to be lost.

		$perfPass = $this->pass($perfMetrics);
		$this->updatePageStatus(
			$data['full_url'],
			$perfPass
		);

		return $this;
	}

	/**
	 * Reads raw data from perf db table to build the current performance aggregate for a page.
	 *
	 * @param string $fullUrl
	 * @param int    $device
	 * @return array
	 */
	public function getPerfMetrics($fullUrl, $device = Data\Perf::ANY)
	{
		return array_merge(
			$this->getPercentileValue('lcp', $fullUrl, $device),
			$this->getPercentileValue('fid', $fullUrl, $device),
			$this->getPercentileValue('inp', $fullUrl, $device),
			$this->getPercentileValue('cls', $fullUrl, $device),
			$this->getPercentileValue('ttfb', $fullUrl, $device)
		);
	}

	/**
	 * Finds out if the given perf metrics set passes Google Core Web Vitals test.
	 *
	 * @param array $data
	 * @return int
	 */
	public function pass($data)
	{
		$metrics = ['lcp', 'fid', 'inp', 'cls'];

		$pass          = Data\Page::PERF_NO_DATA;
		$perfThreshold = $this->pagesConfig->get('perfThresholds');
		foreach ($metrics as $metricName)
		{
			$dataPointValue = Wb\arrayGet($data, $metricName, null);
			if (is_null($dataPointValue))
			{
				// No data is not a failure as CWV are assessed individually.
				// No data on one does not invalidate OK on another metric.
				continue;
			}
			$dataPointCount = Wb\arrayGet($data, $metricName . '_count', 0);
			if ($dataPointCount < Wb\arrayGet($perfThreshold, 'minValues'))
			{
				continue;
			}
			if ($dataPointValue > Wb\arrayGet($perfThreshold, [$metricName, 'good']))
			{
				$pass = Data\Page::PERF_FAILING;
			}
			else if (Data\Page::PERF_FAILING !== $pass)
			{
				// only set to OK if not previously failing, ie only from NO_DATA to OK
				$pass = Data\Page::PERF_OK;
			}
		}

		return $pass;
	}

	/**
	 * Update the Pages table record of a given Page object with the failing status.
	 *
	 * @param string $fullUrl
	 * @param int    $status
	 * @return Perf
	 */
	public function updatePageStatus($fullUrl, $status)
	{
		$this->factory->getA(Data\Page::class)
					  ->loadPerUrl($fullUrl)
					  ->set(
						  [
							  'perf_status' => $status,
						  ]
					  )->store();

		return $this;
	}

	private function getPercentileValue($column, $fullUrl, $device, $percentile = 0.75)
	{
		$value = null;

		$url = $this->dbHelper->storageSafe($fullUrl);

		$filter = $this->dbHelper->qn($column) . ' IS NOT NULL';
		$filter .= ' and ' . $this->dbHelper->qn('url') . ' = ' . $this->dbHelper->q($url);
		if (Data\Perf::ANY != $device)
		{
			$filter .= ' and ' . $this->dbHelper->qn('device') . ' = ' . (int)$device;
		}

		$total = $this->dbHelper->count(
			self::PERF_DATA_TABLE,
			'*',
			$filter
		);


		if (!empty($total))
		{
			$percentilePosition = (int)floor($percentile * $total);

			$value = $this->dbHelper
				->selectResult(
					self::PERF_DATA_TABLE,
					$column,
					$filter,
					[],
					[
						$column => 'ASC'
					],
					$percentilePosition,
					1
				);
		}

		return [
			$column            => $value,
			$column . '_count' => $total
		];
	}

	/**
	 * Purge anything older than 28 days, that is on a day
	 * that is more than 28 days from now in the past.
	 *
	 * @return $this
	 */
	private function gc()
	{
		$todayTimestamp = System\Date::toExtendedDateTime()
									 ->getTimestamp();

		$limitTimestamp = 1000 * ($todayTimestamp - $this->pagesConfig->getInt('perfMeasurementGcDays', self::GC_DAYS) * 86400);

		$this->dbHelper
			->delete(
				self::PERF_DATA_TABLE,
				[
					['ts', '<', $limitTimestamp]
				]
			);

		return $this;
	}
}
