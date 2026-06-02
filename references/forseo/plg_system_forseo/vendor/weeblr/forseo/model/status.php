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
use Weeblr\Wblib\Forseo\Messages;
use Weeblr\Wblib\Forseo\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Status extends Base\Base
{
	/**
	 * @var array Storage for alerts gathered during the status build-up.
	 */
	private $alerts = [
		'danger'  => [],
		'warning' => [],
		'info'    => [],
	];

	/**
	 * @var Weeblr\Wblib\Db\Dbhelper Database helper instance.
	 */
	private $db;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * @var Config
	 */
	private $pagesConfig;

	/**
	 * @var Config
	 */
	private $sdConfig;

	/**
	 * @var Config
	 */
	private $sitemapsConfig;

	/**
	 * @var Messages\Manager
	 */
	private $msgManager;

	/**
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct();

		$this->db             = $this->factory->getThe('db');
		$this->logger         = $this->factory->getThe('forseo.logger');
		$this->pagesConfig    = $this->factory->getThis('forseo.config', 'pages');
		$this->sdConfig       = $this->factory->getThis('forseo.config', 'sd');
		$this->sitemapsConfig = $this->factory->getThis('forseo.config', 'sitemaps');
		$this->msgManager     = $this->factory->getThe('forseo.msgManager');
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
		return [
			'pages'          => [
				'collectionEnabled'    => $this->pagesConfig->isTruthy('collectionEnabled'),
				'collectionCompletion' => $this->getAnalysisCompletion(),
			],
			'perf'           => $this->getPerfStatus(),
			'sd'             => $this->getSdStatus(),
			'sitemaps'       => $this->getSitemapsStatus(),
			'socialnetworks' => $this->getSocialNetworksStatus(),
			'alerts'         => $this->getAlerts()
		];
	}

	/**
	 * Collect status of performance metrics collection.
	 *
	 * @return array
	 */
	private function getPerfStatus()
	{
		$config = $this->factory
			->getThis('forseo.config', 'pages');

		$passingCount = $this->db->count(
			'#__forseo_pages',
			'*',
			[
				'perf_status' => Data\Page::PERF_OK
			]
		);

		$failingCount = $this->db->count(
			'#__forseo_pages',
			'*',
			[
				'perf_status' => Data\Page::PERF_FAILING
			]
		);

		$totalMeasurements = $failingCount + $passingCount;
		$failingRatio      = $totalMeasurements <= 0
			? 0
			: $failingCount / $totalMeasurements;

		return array_merge(
			[
				'passing'   => $passingCount,
				'failing'   => $failingCount,
				'hasErrors' => $failingRatio
			],
			$config->get([])
		);
	}

	/**
	 * Collect status of Structured data implementation.
	 *
	 * @return array
	 */
	private function getSdStatus()
	{
		$config = $this->factory
			->getThis('forseo.config', 'sd');

		$activeRulesCount = $this->db->count(
			'#__forseo_rules',
			'*',
			[
				'type' => Data\Rule::TYPE_SD,
				['enabled', '!=', '0']
			]
		);

		if ($config->isTruthy('enabledBuiltInRules'))
		{
			$activeRulesCount += 1;
		}

		return array_merge(
			[
				'activeRulesCount'  => $activeRulesCount,
				'activeRulesStatus' => !empty($activeRulesCount),
				'hasErrors'         => !$this->sdHasLogoOrIsNotNeeded()
			],
			$config->get([])
		);
	}

	/**
	 * Collect status of sitemaps implementation.
	 *
	 * @return array
	 */
	private function getSitemapsStatus()
	{
		return $this->factory
			->getA(Sitemapsstatus::class)
			->status(
				[]
			);
	}

	/**
	 * Collect status of social sharing implementation.
	 *
	 * @return array
	 */
	private function getSocialNetworksStatus()
	{
		$socialNetworksConfig = $this->factory
			->getThis('forseo.config', 'socialnetworks');

		return [
			'ogpEnabled'    => $socialNetworksConfig->isTruthy('ogpEnabled'),
			'tCardsEnabled' => $socialNetworksConfig->isTruthy('tCardsEnabled')
		];
	}

	/**
	 * Find out how far we are into the site analysis process.
	 *
	 * @return array
	 */
	private function getAnalysisCompletion()
	{
		$collectedUrlsCount = $this->db->count('#__forseo_collected_urls');
		$pagesCount         = $this->db->count('#__forseo_pages');
		$total              = $collectedUrlsCount + $pagesCount;

		if (empty($collectedUrlsCount) && !empty($total))
		{
			// completed crawl
			$completion = 100;
		}
		else
		{
			$completion = empty($total)
				? 0
				: ceil(100 * $pagesCount / $total);
		}

		return [
			'pagesCount' => $pagesCount,
			'perCent'    => $completion
		];
	}

	/**
	 * Count links that triggered errors.
	 *
	 * @return int
	 */
	private function getErrorLinksCount()
	{
		return $this->db->count(
			'#__forseo_links',
			'*',
			[
				['status', '>=', 400]
			]
		);
	}

	/**
	 * Count requests that triggered errors.
	 *
	 * @return int
	 */
	private function getErrorPagesCount()
	{
		return $this->db->count(
			'#__forseo_errors'
		);
	}

	/**
	 * Count enabled analytics providers.
	 *
	 * @return int
	 */
	private function getAnalyticsProvidersCount()
	{
		return count($this->getAnalyticsProvidersRules());
	}

	private function hasUniversalAnalyticsRule()
	{
		$rules                        = $this->getAnalyticsProvidersRules();
		$universalAnalyticsRulesCount = 0;
		foreach ($rules as $rule)
		{
			$parsed = json_decode($rule, true);
			if ('universalga' === Wb\arrayGet($parsed, 'actionAnalyticsProvider', ''))
			{
				$universalAnalyticsRulesCount++;
			}
		}

		return $universalAnalyticsRulesCount > 0;
	}

	/**
	 * Load enabled analytics rules.
	 *
	 * @return array
	 */
	private function getAnalyticsProvidersRules()
	{
		static $analyticsRules;

		if (is_null($analyticsRules))
		{
			$analyticsRules = $this->db->selectColumn(
				'#__forseo_rules',
				'rule',
				[
					'type' => Data\Rule::TYPE_ANALYTICS,
					['enabled', '!=', Data\Rule::DISABLED]
				]
			);
		}

		return $analyticsRules;
	}

	/**
	 * Build-up default alerts based on arbitrary checks.
	 *
	 * @return array
	 */
	private function getAlerts()
	{
		// Errors
		if (Crawler::STATE_RUNNING !== $this->pagesConfig->get('crawlerStatus'))
		{
			$this->alerts['danger'][] = [
				'type'    => 'status',
				'text'    => 'dashboard.errorCrawler',
				'details' => [
					'dashboard.errorCrawlerDetails',
					'dashboard.contactSupport'
				]
			];
		}

		if ($this->getErrorLinksCount() > 0)
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_DANGER,
					'msg_id'        => 'dashboard.linksInError',
					'title'         => 'dashboard.linksInError',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE,
					'postpone_spec' => Messages\Message::DELAY_1W
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.linksInError');
		}

		// Prompt to add ProfilePage
		$this->msgManager->add(
			[
				'type'          => Messages\Message::TYPE_DANGER,
				'msg_id'        => 'dashboard.profilePagePrompt',
				'title'         => 'dashboard.profilePagePrompt',
				'body'          => 'dashboard.profilePagePromptDetails',
				'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
				'postpone_spec' => Messages\Message::DELAY_2W
			]
		);

		// Prompt to exclude AI robots
		$this->msgManager->add(
			[
				'type'          => Messages\Message::TYPE_DANGER,
				'msg_id'        => 'dashboard.robotOutAiBotsPrompt',
				'title'         => 'dashboard.robotOutAiBotsPrompt',
				'body'          => 'dashboard.robotOutAiBotsPromptDetails',
				'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
				'postpone_spec' => Messages\Message::DELAY_2W
			]
		);

		// Prompt to connect Search Console
		if (
			Wb\arrayIsTruthy(
				FORSEO_FEATURES_OVERRIDES,
				'integrations.google.search_console'
			)
			&&
			!$this->factory->getThis('forseo.config', 'integrations')->isGoogleSearchConsoleActive()
		) {
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_DANGER,
					'msg_id'        => 'dashboard.gscConnectPrompt',
					'title'         => 'configWizard.gscExplain',
					'body'          => 'dashboard.gscPromptDetails',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_2W
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.gscConnectPrompt');
		}

		if ($this->getErrorPagesCount() > 0)
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.pagesInError',
					'title'         => 'dashboard.pagesInError',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE,
					'postpone_spec' => Messages\Message::DELAY_1W
				]
			);
		}

		// Warnings
		$this->getExtensionsAlerts();

		if ($this->pagesConfig->isFalsy('insertAutoCanonical'))
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.warningInsertAutoCanonical',
					'title'         => 'dashboard.warningInsertAutoCanonical',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_1M
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.warningInsertAutoCanonical');
		}

		if ($this->pagesConfig->isFalsy('metaAutoDescIfMissing'))
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.warningMetaAutoDescIfMissing',
					'title'         => 'dashboard.warningMetaAutoDescIfMissing',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_1M
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.warningMetaAutoDescIfMissing');
		}

		if ($this->getAnalyticsProvidersCount() <= 0)
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.noAnalytics',
					'title'         => 'dashboard.noAnalytics',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_1M
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.noAnalytics');
		}

		if ($this->hasUniversalAnalyticsRule())
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_DANGER,
					'msg_id'        => 'dashboard.ugaDeprecation',
					'title'         => 'ruleEdit.ugaDeprecation',
					'body'          => 'dashboard.ugaDeprecationDetails',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_2W
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.ugaDeprecation');
		}

		$this->getSdAlerts();

		$this->getSitemapsAlerts();

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
	 * Build up alerts related to Structured data.
	 */
	private function getSdAlerts()
	{
		if ($this->sdConfig->isFalsy('enabled'))
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.warningSdDisabled',
					'title'         => 'dashboard.warningSdDisabled',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_1M
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.warningSdDisabled');
		}

		if (!$this->sdHasLogoOrIsNotNeeded())
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_DANGER,
					'msg_id'        => 'dashboard.warningSdMissingLogo',
					'title'         => 'dashboard.warningSdMissingLogo',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE,
					'postpone_spec' => Messages\Message::DELAY_1W
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.warningSdMissingLogo');
		}

		return $this;
	}


	/**
	 * Build up alerts related to 3rd-party extensions.
	 */
	private function getExtensionsAlerts()
	{
		$sh404sefConfig = $this->factory->getThis('forseo.config', 'sh404sef');
		if (
			(
				$sh404sefConfig->get('canImportMetaFromSh404sef') > 0
				||
				$sh404sefConfig->get('canImportAliasesFromSh404sef') > 0
			)
			&&
			$sh404sefConfig->isFalsy('importWizardCompleted')
		) {
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.canImportFromSh404sef',
					'title'         => 'importWizard.sh404sefCanImportData',
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
	}

	/**
	 * Build up alerts related to Sitemaps data.
	 */
	private function getSitemapsAlerts()
	{
		if ($this->sitemapsConfig->isFalsy('enabled'))
		{
			// sitemaps are not enabled, reduce noise by not
			// alerting at all
			return $this;
		}

		// adding to robots.txt?
		if ($this->sitemapsConfig->isFalsy('addToRobotsTxt'))
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.warningSitemapsNotAddingRobotsTxt',
					'title'         => 'dashboard.warningSitemapsNotAddingRobotsTxt',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_1M
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.warningSitemapsNotAddingRobotsTxt');
		}

		if ($this->sitemapsConfig->isFalsy('searchEnginesPingEnabled'))
		{
			$this->msgManager->add(
				[
					'type'          => Messages\Message::TYPE_WARNING,
					'msg_id'        => 'dashboard.warningSitemapsNotSubmitting',
					'title'         => 'dashboard.warningSitemapsNotSubmitting',
					'body'          => '',
					'dismiss_type'  => Messages\Message::DISMISS_TYPE_POSTPONABLE | Messages\Message::DISMISS_TYPE_DISMISSABLE,
					'postpone_spec' => Messages\Message::DELAY_1M
				]
			);
		}
		else
		{
			$this->msgManager->deleteByMsgId('dashboard.warningSitemapsNotSubmitting');
		}

		return $this;
	}

	/**
	 * Whether the SD organization has a valid logo if it needs one.
	 *
	 * @return bool
	 */
	private function sdHasLogoOrIsNotNeeded()
	{
		if (
			$this->sdConfig->isFalsy('enabled')
			||
			(
				$this->sdConfig->isFalsy('enabledLocalBusiness')
				&&
				$this->sdConfig->isFalsy('enabledBuiltInRules')
			)
		) {
			return true;
		}

		$orgLogo = $this->sdConfig->get('organizationLogo');
		return !empty($orgLogo)
			   &&
			   !Wb\arrayIsEmpty($orgLogo, 'url');
	}

	/**
	 * Strip the leading number in message type, used to sort displayed items
	 * @param string $type
	 * @return string
	 */
	private function dbTypeToDisplayType($type)
	{
		return Wb\lTrim(
			$type,
			['1_', '2_', '3_']
		);
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
