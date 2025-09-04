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

namespace Weeblr\Forsef\Model\Admin;

use Weeblr\Forsef\Data;

use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\Messages;
use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Db;
use Weeblr\Wblib\Forsef\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Status extends Base\Base
{
	/**
	 * How many requests to include in top requests list.
	 */
	const TOP_REQUESTS_COUNT = 10;

	/**
	 * Caching time in minutes for dashboard stats retrieval.
	 */
	const STATS_DISPLAY_CACHE_TIME = 5;

	/**
	 * @var array Storage for alerts gathered during the status build-up.
	 */
	private $alerts = [
		'danger'  => [],
		'warning' => [],
		'info'    => [],
	];

	/**
	 * @var Db\Dbhelper Database helper instance.
	 */
	private $db;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var Config
	 */
	private $routingConfig;

	/**
	 * @var Messages\Manager
	 */
	private $msgManager;

	/**
	 * @var bool If set, any cached data is cleaned to return fresh values.
	 */
	private $forceRefresh = false;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->db            = $this->factory->getThe('db');
		$this->logger        = $this->factory->getThe('forsef.logger');
		$this->routingConfig = $this->factory->getThis('forsef.config', 'routing');
		$this->msgManager    = $this->factory->getThe('forsef.msgManager');
	}

	/**
	 * Reports on current crawlser status.
	 *
	 * @param array $options API request options
	 *                       'type'
	 * @return array
	 * @throws \Exception
	 */
	public function status($options)
	{
		$this->forceRefresh = Wb\arrayGet($options, 'force_refresh', false);

		return [
			'autoUrlsCount'      => $this->getUrlsCount('auto'),
			'customUrlsCount'    => $this->getUrlsCount('custom'),
			'duplicateUrlsCount' => $this->getUrlsCount('duplicate'),
			'topRequests'        => $this->getTopRequest(),
			'statsDailies'       => $this->getStatsDailies(),
			'alerts'             => $this->getAlerts()
		];
	}

	/**
	 * Count URLs pairs in the database.
	 *
	 * @return int
	 */
	private function getUrlsCount($urlType = null)
	{
		switch ($urlType)
		{
			case 'auto':
				$whereClause = [
					'custom' => Data\Urlpair::AUTO,
					'duplicate' => Data\Urlpair::CANONICAL
				];
				break;
			case 'custom':
				$whereClause = [
					'custom' => Data\Urlpair::CUSTOM,
					'duplicate' => Data\Urlpair::CANONICAL
				];
				break;
			case 'duplicate':
				$whereClause = [
					'duplicate' => Data\Urlpair::DUPLICATE
				];
				break;
			default:
				$whereClause = [];
		}

		return $this->db->count(
			'#__forsef_urls',
			'*',
			$whereClause
		);
	}

	/**
	 * Select top requests from the list of SEF URLs.
	 *
	 * @return int
	 */
	private function getTopRequest()
	{
		$cache = $this->platform->getCache(
			'callback',
			array(
				'defaultgroup' => '4sef_top_requests',
				'lifetime'     => self::STATS_DISPLAY_CACHE_TIME,
				'caching'      => 1,
			)
		);

		if ($this->forceRefresh)
		{
			$cache->clean();
		}

		return $cache->get(
			[
				$this,
				'doGetTopRequest'
			]
		);
	}

	/**
	 * Select top requests from the list of SEF URLs.
	 *
	 * @return int
	 */
	public function doGetTopRequest()
	{
		return $this->db->selectAssocList(
			'#__forsef_urls',
			[
				'sef',
				'hits'
			],
			[
				['hits', '>', 0]
			],
			[],                        // whereData
			[
				'hits' => 'DESC'
			],
			0,                         // offset
			self::TOP_REQUESTS_COUNT   // limit
		);
	}

	/**
	 * Read requests stats for the last few days.
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getStatsDailies()
	{
		$cache = $this->platform->getCache(
			'callback',
			array(
				'defaultgroup' => '4sef_stats_dailies',
				'lifetime'     => self::STATS_DISPLAY_CACHE_TIME,
				'caching'      => 1,
			)
		);

		if ($this->forceRefresh)
		{
			$cache->clean();
		}

		return $cache->get(
			[
				$this,
				'doGetStatsDailies'
			]
		);
	}

	/**
	 * Read requests stats for the last few days.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function doGetStatsDailies()
	{
		$daysToDisplay = $this->factory->getThis('forsef.config', 'stats')
									   ->get('displayDays');

		$dayObject = $this->factory->getA(
			System\Datetimeobject::class,
			'now'
		)->sub(
			new \DateInterval(
				'P' . $daysToDisplay . 'D'
			)
		);

		$rawData = $this->db->selectAssocList(
			'#__forsef_stats_dailies',
			[
				'period_start',
				'hits',
				'hits_bots',
				'hits_se'
			],
			[
				['period_start', '>', $dayObject->toMysql()]
			],
			[],            // whereData
			[],            // orderBy
			0,             // offset
			0,             // lines
			'period_start' // key
		);

		$data = [];
		for ($day = 0; $day < $daysToDisplay; $day++)
		{
			$thisDayDate = $dayObject->add(
				new \DateInterval(
					'P1D'
				)
			)->format('Y-m-d');

			if (!empty($rawData[$thisDayDate . ' 00:00:00']))
			{
				$data[] = [
					'period_start' => $thisDayDate,
					'hits'         => (int)$rawData[$thisDayDate . ' 00:00:00']['hits']
				];;
			}
			else
			{
				$data[] = [
					'period_start' => $thisDayDate,
					'hits'         => 0
				];
			}
		}

		return $data;
	}

	/**
	 * Build-up default alerts based on arbitrary checks.
	 *
	 * @return array
	 */
	private function getAlerts()
	{
		// Errors

		// Warnings
		$sh404sefConfig = $this->factory->getThis('forsef.config', 'sh404sef');
		if (
			$sh404sefConfig->get('canImportFromSh404sef') >= 0
			&&
			$sh404sefConfig->isFalsy('importWizardCompleted')
		)
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.canImportFromSh404sef',
					'title'         => 'importWizard.promptImportUrlsTitle',
					'body'          => 'dashboard.canImportFromSh404sef',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_1M
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.canImportFromSh404sef');
		}

		// Infos
		$this->getGlobalMessagesAlerts();

		return $this->alerts;
	}

	/**
	 * Build up alerts from global messaging manager. Includes postponable alerts.
	 */
	private function getGlobalMessagesAlerts()
	{
		$messages = $this->msgManager->get();
		foreach ($messages as $message)
		{
			$this->addAlert(
				[
					'type'        => $this->dbTypeToDisplayType(
						Wb\arrayGet($message, 'type', '')
					),
					'text'        => Wb\arrayGet($message, 'title', ''),
					'details'     => Wb\arrayGet($message, 'body', ''),
					'dismissType' => Wb\arrayGet($message, 'dismiss_type', 0),
					'id'          => Wb\arrayGet($message, 'id', 0)
				],
				preg_replace(
					'~^[0-9]_(.*)~',
					'$1',
					Wb\arrayGet($message, 'type', '')
				)
			);
		}
	}

	/**
	 * Strip the leading number in message type, used to sort displayed items
	 * @param string $type
	 * @return string
	 */
	private function dbTypeToDisplayType($type)
	{
		switch ($type)
		{
			case Messages\Message::TYPE_DANGER:
				return 'danger';
			case Messages\Message::TYPE_WARNING:
				return 'warning';
			case Messages\Message::TYPE_INFO:
				return 'ok';
		}
		return '';
	}

	/**
	 * Store an alert to be rendered.
	 *
	 * @param array  $alert
	 * @param string $alertType
	 */
	private function addAlert($alert, $alertType)
	{
		$this->alerts[$alertType][] = $alert;
	}
}
