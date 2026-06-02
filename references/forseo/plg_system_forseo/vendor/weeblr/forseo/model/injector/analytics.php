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

namespace Weeblr\Forseo\Model\Injector;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Mvc;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Analytics extends Base\Base
{
	/**
	 * @var Data\Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo = null;


	/**
	 * @var Helper/Analytics convenience instance of an analytics helper.
	 */
	private $helper;

	/**
	 * @var Stores list of known analytics providers from config.
	 */
	private $providers;

	/**
	 * @var Stores list of analytics providers that requires cookies consent.
	 */
	private $providersRequiringConsent;

	/**
	 * @var Complete analytics configuration.
	 */
	private $config;

	/**
	 * @var array Holds all the structured data to be output as json-ld
	 */
	private $data = [];

	public function __construct()
	{
		parent::__construct();

		$this->requestInfo = $this->factory->getThe('forseo.requestInfo');

		$this->helper                    = $this->factory->getA(Helper\Analytics::class);
		$this->config                    = $this->factory->getThis(
			'forseo.config',
			'analytics'
		);
		$this->providers                 = $this->config->get('providers');
		$this->providersRequiringConsent = $this->config->get('providersRequiringConsent');
	}

	/**
	 * Builds the final values for the data to inject.
	 *
	 * @params array Active Analytics rules for this request.
	 */
	public function build(array $rules)
	{
		if (
			empty($rules)
			||
			$this->factory->getThis('forseo.config', 'analytics')->isFalsy('enabled')
		) {
			return $this;
		}

		$requestInfo = $this->factory->getThe('forseo.requestInfo');

		foreach ($rules as $rule)
		{
			$ruleDetails       = $rule->getRule();
			$ruleDetails['id'] = $rule->getId();

			// customize the URL for errors
			$pageStatus = $requestInfo->get('page_status');
			if (System\Http::isError($pageStatus))
			{
				$ruleDetails['custom_url'] = '/__' . $pageStatus . '__';
			}

			$this->data[] = $ruleDetails;
		}

		/**
		 * Filter the raw data used when outputting analytics snippet, for each configured
		 * provider on the current page.
		 *
		 * @api     forseo
		 * @var forseo_analytics_snippets
		 * @package 4SEO\filter\analytics
		 * @since   1.0.0
		 *
		 * @param array $data Raw data to be used in snippets, per provider.
		 *
		 * @return array
		 *
		 */
		$this->data = $this->factory
			->getThe('hook')
			->filter(
				'forseo_analytics_snippets',
				$this->data
			);

		return $this;
	}

	/**
	 * Inject analytics snippets by rendering Layouts.
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	public function inject(string $body)
	{
		if (empty($this->data))
		{
			return $body;
		}

		$rulesHelper = $this->factory->getA(Helper\Rules::class);

		// reverse rules order so that snippets are injected in the right order
		$this->data = array_reverse($this->data);

		foreach ($this->data as $rule)
		{
			$provider = Wb\arrayGet(
				$rule,
				'actionAnalyticsProvider',
				''
			);

			if (!$this->canInjectProvider($provider))
			{
				$this->factory->getThe('forseo.logger')->debug(
					Wb\join(
						', ',
						__METHOD__,
						'Cannot inject analytics tracking for provider ' . $provider . ', cookies not accepted by user.'
					)
				);

				continue;
			}

			$locations = $this->config->get(
				'actionAnalytics' . ucfirst($provider) . 'Location',
				Data\Rule::RAW_CONTENT_LOCATION_HEAD_BOTTOM
			);
			$locations = Wb\arrayEnsure($locations);

			foreach ($locations as $location)
			{
				$toInject = Mvc\LayoutHelper::render(
					'forseo.analytics.' . $provider . '_' . strtolower($location),
					array_merge(
						$rule,
						[
							'page_language' => str_replace(
								'-',
								'_',
								$this->requestInfo->get('page_language')
							)
						]
					),
					FORSEO_LAYOUTS_PATH,
					'default'
				);

				$toInject = StringHelper::trim($toInject);
				if (empty($toInject))
				{
					continue 2;
				}

				$body = $rulesHelper->injectRawContent(
					Wb\arrayGet($rule, 'id'),
					$body,
					$toInject,
					$location,
					$wrap = false
				);
			}
		}

		return $body;
	}

	/**
	 * Check if an analytics provider exists, requires cookies consent
	 * and if so, if cookies were accepted.
	 *
	 * @param string $provider
	 * @return bool
	 */
	private function canInjectProvider($provider)
	{
		if (
		!in_array(
			$provider,
			$this->providers
		)
		) {
			// unknown provider
			return false;
		}

		if (
			$this->config->isTruthy('enableConsentCheck')
			&&
			in_array(
				$provider,
				$this->providersRequiringConsent
			)
			&&
			!$this->helper->userAcceptedCookies($provider)
		) {
			return false;
		}

		return true;
	}
}