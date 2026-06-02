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
use Weeblr\Wblib\Forseo\Route;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Sd extends Base\Base
{
	/**
	 * @var string The base string used to build internal schema ids, typically the site root URL.
	 */
	private $baseId = '';

	/**
	 * @var array Temporarily hold being built structured data.
	 */
	private $graph = [];

	/**
	 * @var Data\Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo;

	/**
	 * @var Data\Page Instance of current computed data about the page.
	 */
	private $pageData;

	/**
	 * @var array SD identities (author, publisher) and similar data built.
	 */
	private $identities = [];

	/**
	 * @var array Some identities may depend on others. Storing dependencies here.
	 */
	private $identitiesDeps = [];

	/**
	 * @var array SD identities actually used when building the SD, need to be injected into the SD json output.
	 */
	private $identitiesUsed = [];

	/**
	 * @var array SD identities already renderd in the page.
	 */
	private $identitiesRendered = [];

	/**
	 * @var System\Config Holds the page collection config object.
	 */
	private $config = null;

	/**
	 * @var System\Config Holds the system config object.
	 */
	private $systemConfig = null;

	/**
	 * @var System\Config Holds the application static config object.
	 */
	private $appConfig = null;

	/**
	 * @var Db\Helper Database access helper.
	 */
	protected $dbHelper = null;

	/**
	 * @var System\Logger Convenience logger instance.
	 */
	private $logger = null;

	/**
	 * Assign a unique uuidV1. Better V1 than v4 as they are indexed
	 * in DB.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);

		$this->config       = $this->factory->getThis('forseo.config', 'sd');
		$this->systemConfig = $this->factory->getThis('forseo.config', 'system');
		$this->appConfig    = $this->factory->getThis('forseo.config', 'app');
		$this->dbHelper     = $this->factory->getThe('db');
		$this->logger       = $this->factory->getThe('forseo.logger');

		$this->pageData    = Wb\arrayGet($options, 'pageData');
		$this->requestInfo = Wb\arrayGet($options, 'requestInfo');
	}

	/**
	 * Build all structured data pertaining to the current page, caching
	 * them in the process. Caching is required if Joomla content caching is enabled
	 * (not full page caching) as in this case onContentPrepare is not fired
	 * and we don't get a chance to get the content data required to compute SD.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function build()
	{
		$this->graph = [];

		$this->baseId = $this->requestInfo->get('site_url');
		if (empty($this->baseId))
		{
			return $this->graph;
		}

		if (!$this->platform->getConfig()->get('caching'))
		{
			// we only cache if joomla caching is enabled
			$this->doBuild();

			return [
				'@context' => 'http://schema.org',
				'@graph'   => $this->graph
			];
		}

		$cache = $this->platform->getCache(
			'output',
			array(
				'defaultgroup' => '4seo_sd_build',
				'lifetime'     => 0,
				'caching'      => 1,
			)
		);

		$cacheId = $this->requestInfo->get('page_url');
		if ($cache->contains($cacheId))
		{
			return $cache->get($cacheId);
		}

		$this->doBuild();

		if (empty($this->graph))
		{
			$built = $this->graph;
		}
		else
		{
			$built = [
				'@context' => 'http://schema.org',
				'@graph'   => $this->graph
			];

			$cache->store(
				$built,
				$cacheId
			);
		}

		return $built;
	}

	/**
	 * Actually build SD graph in case of a cache miss in build().
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function doBuild()
	{
		$rules = [];

		if ($this->config->isTruthy('enabledPerPage'))
		{
			$rules = $this->factory
				->getThe('forseo.rulesController')
				->getRulesPerType(Data\Rule::TYPE_SD);
		}

		if ($this->config->isTruthy('enabledLocalBusiness'))
		{
			$rules = $this->addLocalBusinessRule(
				$rules
			);
		}

		$rules = $this->addPluginsRules(
			$rules
		);

		if ($this->config->isTruthy('enabledBuiltInRules'))
		{
			$rules = $this->addBuiltInRules(
				$rules
			);
		}

		$this->identitiesRendered = [];

		$this->buildIdentities()
			 ->buildDataPerType($rules)
			 ->renderIdentities($this->identitiesUsed);
	}

	/**
	 * Add required rule to build the LocalBusiness structured data
	 * if on home page and if enabled in global config.
	 *
	 * @return array
	 */
	private function addLocalBusinessRule($rules)
	{
		if (!$this->platform->isHomePage())
		{
			// Local Business only on home page
			return $rules;
		}

		$businessType = $this->config->get('organizationType');
		if (empty($businessType) || empty($businessType[0]))
		{
			return $rules;
		}

		$this->identitiesUsed = array_merge(
			$this->identitiesUsed,
			['defaultBusiness']
		);

		return $rules;
	}

	/**
	 * Give plugins and 3rd-party a chance to add their own SD rules.
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function addPluginsRules($rules)
	{
		/**
		 * Filter the list of structured data rules.
		 *
		 * @api     forseo
		 * @package 4SEO\sd
		 * @var forseo_sd_rules
		 * @since   1.3.0
		 *
		 * @param array            $rules
		 * @param Data\Requestinfo $requestInfo
		 * @param Data\Page        $pageData
		 * @param string           $baseId
		 *
		 * @return void
		 *
		 */
		return $this->factory
			->getThe('hook')
			->filter(
				'forseo_sd_rules',
				$rules,
				$this->requestInfo,
				$this->pageData,
				$this->baseId
			);
	}

	/**
	 * Add default built-in rules as last rules in set. Any custom
	 * rules triggered before them will thus prevent them from running,
	 * however this allows having a default behavior without
	 * requiring user to create any rule.
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function addBuiltInRules($rules)
	{
		// Video Object
		$rule     = $this->factory->getA(Data\Rule::class);
		$ruleData = [
			'actionSdType'             => [Data\Sd::VIDEO_OBJECT],
			'actionSdThumbnailUrlAuto' => true,
			'actionSdContentUrlAuto'   => true,
		];

		$rule->set(
			[
				'rule'   => $ruleData,
				'source' => Data\Rule::SOURCE_BUILT_IN
			]
		);

		$rules[] = $rule;

		return $rules;
	}

	/**
	 * @TODO Move to SD config object?
	 *
	 * @return $this
	 * @throws \Exception
	 */
	private function buildIdentities()
	{
		$this->identities['defaultAuthor'] = [
			'@id'   => $this->baseId . '#defaultAuthor',
			'@type' => Data\Sd::PERSON, // Person | Organization
			'name'  => $this->config->get('personName')
		];
		$authorUrl                         = $this->config->get('personUrl');
		if (!empty($authorUrl))
		{
			$this->identities['defaultAuthor']['url'] = $authorUrl;
		}

		$customSdDataJson = $this->config->get('personCustomCode');
		if (!empty($customSdDataJson))
		{
			$customSdData = json_decode(
				$customSdDataJson,
				true
			);
			if (is_array($customSdData))
			{
				$this->identities['defaultAuthor'] = array_merge(
					$this->identities['defaultAuthor'],
					$customSdData
				);
			}
		}

		$this->identities['defaultOwner'] = [
			'@id'   => $this->baseId . '#defaultOwner',
			'@type' => Data\Sd::ORGANIZATION, // Person | Organization
			'name'  => $this->config->get('organizationName')
		];

		$country            = $this->config->get('organizationAddressCountry', []);
		$defaultAddressDef  = [
			'addressLocality' => $this->config->get('organizationAddressLocality', ''),
			'addressRegion'   => $this->config->get('organizationAddressRegion', ''),
			'postalCode'      => $this->config->get('organizationPostalCode', ''),
			'streetAddress'   => $this->config->get('organizationStreetAddress', ''),
			'addressCountry'  => Wb\arrayGet($country, 0, '')
		];
		$defaultAddressText = empty($defaultAddressDef)
			? null
			: implode($defaultAddressDef);
		if (empty($defaultAddressText))
		{
			$this->identities['defaultAddress'] = null;
		}
		else
		{
			$this->identities['defaultAddress'] = array_merge(
				[
					'@id'   => $this->baseId . '#defaultAddress',
					'@type' => Data\Sd::POSTAL_ADRESS,
				],
				$defaultAddressDef
			);
		}

		$defaultGeoDef = [
			'latitude'  => $this->config->get('organizationGeoLatitude', ''),
			'longitude' => $this->config->get('organizationGeoLongitude', '')
		];
		if (
			empty($defaultGeoDef)
			||
			Wb\arrayIsEmpty($defaultGeoDef, 'latitude')
			||
			Wb\arrayIsEmpty($defaultGeoDef, 'longitude')
		) {
			$defaultGeoDef = null;
			// try get geo from address?
			// Disabled, generates too much traffic on our GMaps account
			// Coordinates can only be retrieved from the admin UI.
//			if (!empty($this->identities['defaultAddress']))
//			{
//				$geoDef = $this->factory
//					->getA(Geo::class)
//					->findCoordinates(
//						$this->identities['defaultAddress'],
//						true
//					);
//
//				if (
//					Wb\arrayIsTruthy($geoDef, 'latitude')
//					&&
//					Wb\arrayIsTruthy($geoDef, 'longitude')
//				)
//				{
//					$defaultGeoDef = $geoDef;
//				}
//			}
		}

		if (!empty($defaultGeoDef))
		{
			$this->identities['defaultGeo'] = array_merge(
				[
					'@id'   => $this->baseId . '#defaultGeo',
					'@type' => Data\Sd::GEO_COORDINATES,
				],
				$defaultGeoDef
			);
		}

		$openingHoursType      = $this->config->get('organizationHoursType', Data\Sd::HOURS_TYPE_NONE);
		$openingHoursHoursType = $this->config->get('organizationHoursHoursType', Data\Sd::HOURS_HOURS_TYPE_24);
		$defaultHoursDef       = [
			'hoursType' => $openingHoursType,
		];
		switch ($openingHoursType)
		{
			case Data\Sd::HOURS_TYPE_ALWAYS:
				$spec = [array_merge(
							 [
								 'dayOfWeek' => Data\Sd::DAYS_OF_WEEK,
							 ],
							 $this->hoursFromHoursType($openingHoursHoursType)
						 )];
				break;
			case Data\Sd::HOURS_TYPE_WEEKDAYS:
				$spec = [array_merge(
							 [
								 'dayOfWeek' => Data\Sd::WEEKDAYS,
							 ],
							 $this->hoursFromHoursType($openingHoursHoursType)
						 )];
				break;
			case Data\Sd::HOURS_TYPE_CUSTOM:
				$spec = $this->hoursFromCustomHours();
				break;
			default:
				$spec = [];
				break;
		}

		$defaultHoursDef = array_merge(
			$defaultHoursDef,
			[
				'spec' => $spec
			]
		);

		if (!empty($defaultHoursDef) && 'none' !== Wb\arrayGet($defaultHoursDef, 'hoursType'))
		{
			$defaultHours = [];
			$hoursSpec    = Wb\arrayGet($defaultHoursDef, 'spec', []);
			foreach ($hoursSpec as $daySpec)
			{
				$defaultHours[] = array_merge(
					[
						'@type' => Data\Sd::OPENING_HOURS_SPECIFICATION,
					],
					$daySpec
				);
			}
		}

		if (
			!empty($this->identities['defaultAddress'])
			||
			!empty($this->identities['defaultGeo'])
			||
			!empty($defaultHours)
		) {
			$this->identities['defaultPlace'] = [
				'@id'   => $this->baseId . '#defaultPlace',
				'@type' => Data\Sd::PLACE,
			];

			if (!empty($this->identities['defaultAddress']))
			{
				$this->identities['defaultPlace']['address'] = [
					'@id' => $this->baseId . '#defaultAddress'
				];
			}
			$this->identitiesDeps['defaultPlace'][] = 'defaultAddress';

			if (!empty($this->identities['defaultGeo']))
			{
				$this->identities['defaultPlace']['geo'] = [
					'@id' => $this->baseId . '#defaultGeo'
				];
			}
			$this->identitiesDeps['defaultPlace'][] = 'defaultGeo';

			if (!empty($defaultHours))
			{
				$this->identities['defaultPlace']['openingHoursSpecification'] = $defaultHours;
			}
		}

		$publisherSameAs = [];

		$defaultLogo                     = $this->config->get('organizationLogo');
		$this->identities['defaultLogo'] = [
			'@id'    => $this->baseId . '#defaultLogo',
			'@type'  => Data\Sd::IMAGE_OBJECT,
			'url'    => Wb\arrayGet($defaultLogo, 'url', ''),
			'width'  => Wb\arrayGet($defaultLogo, 'width', ''),
			'height' => Wb\arrayGet($defaultLogo, 'height', ''),
		];

		$this->identities['defaultPublisher']       = [
			'@id'   => $this->baseId . '#defaultPublisher',
			'@type' => Data\Sd::ORGANIZATION,
			'url'   => $this->requestInfo->get('site_url'),
			'logo'  => [
				'@id' => $this->baseId . '#defaultLogo',
			],
			'name'  => $this->config->get('organizationName', ''),
		];
		$this->identitiesDeps['defaultPublisher'][] = 'defaultLogo';
		if (!empty($publisherSameAs))
		{
			$this->identities['defaultPublisher']['sameAs'] = $publisherSameAs;
		}
		if (!empty($this->identities['defaultPlace']))
		{
			$this->identities['defaultPublisher']['location'] = [
				'@id' => $this->baseId . '#defaultPlace'
			];
			$this->identitiesDeps['defaultPublisher'][]       = 'defaultPlace';
		}

		$businessType = $this->config->get('organizationType');
		if (!empty($businessType))
		{
			$businessType = array_pop($businessType);
		}
		if (!empty($businessType))
		{
			$this->identities['defaultBusiness'] = [
				'@id'       => $this->baseId . '#defaultBusiness',
				'@type'     => $businessType,
				'name'      => $this->config->get(
					'organizationName',
					$this->requestInfo->get('site_name')
				),
				'url'       => $this->config->get(
					'organizationUrl',
					$this->requestInfo->get('site_url')
				),
				'telephone' => System\Strings::wrapJsonValue($this->config->get(
					'organizationTel'
				)
				),
				'address'   => [
					'@id' => $this->baseId . '#defaultAddress'
				],
				'geo'       => [
					'@id' => $this->baseId . '#defaultGeo'
				],
				'image'     => [
					'@id' => $this->baseId . '#defaultLogo'
				]
			];
			$priceRange                          = $this->config->get('organizationPriceRange');
			if (!empty($priceRange))
			{
				$this->identities['defaultBusiness']['priceRange'] = $priceRange;
			}
			if (!empty($defaultHours))
			{
				$this->identities['defaultBusiness']['openingHoursSpecification'] = $defaultHours;
			}
			$this->identitiesDeps['defaultBusiness'][] = 'defaultAddress';
			$this->identitiesDeps['defaultBusiness'][] = 'defaultGeo';
			$this->identitiesDeps['defaultBusiness'][] = 'defaultLogo';

			$customSdDataJson = $this->config->get('organizationCustomCode');
			if (!empty($customSdDataJson))
			{
				$customSdData = json_decode(
					$customSdDataJson,
					true
				);
				if (is_array($customSdData))
				{
					$this->identities['defaultBusiness'] = array_merge(
						$this->identities['defaultBusiness'],
						$customSdData
					);
				}
			}
		}

		return $this;
	}

	/**
	 * Builds a array of openingHours specification
	 * to be used in SD output.
	 *
	 * @return array
	 */
	private function hoursFromCustomHours()
	{
		$spec = [];

		foreach (Data\Sd::DAYS_OF_WEEK_SHORT as $key => $dayOfWeek)
		{
			$opens  = $this->config->get('hours' . ucfirst($dayOfWeek) . '1Opens');
			$closes = $this->config->get('hours' . ucfirst($dayOfWeek) . '1Closes');
			if (!empty($opens) && !empty($closes))
			{
				$spec[] = [
					'dayOfWeek' => 'http://schema.org/' . ucfirst(Data\Sd::DAYS_OF_WEEK[$key]),
					'opens'     => $opens,
					'closes'    => $closes
				];
			}
			$opens  = $this->config->get('hours' . ucfirst($dayOfWeek) . '2Opens');
			$closes = $this->config->get('hours' . ucfirst($dayOfWeek) . '2Closes');
			if (!empty($opens) && !empty($closes))
			{
				$spec[] = [
					'dayOfWeek' => 'http://schema.org/' . ucfirst(Data\Sd::DAYS_OF_WEEK[$key]),
					'opens'     => $opens,
					'closes'    => $closes
				];
			}
		}

		return $spec;
	}

	/**
	 * Builds an array with opens and close hours specification
	 * to use in SD structure.
	 *
	 * @param int $hoursType
	 * @return array
	 */
	private function hoursFromHoursType($hoursType)
	{
		switch ($hoursType)
		{
			case Data\Sd::HOURS_HOURS_TYPE_24:
				$hours = [
					'opens'  => Data\Sd::HOURS_ALL_DAY_OPENS,
					'closes' => Data\Sd::HOURS_ALL_DAY_CLOSES
				];
				break;
			case Data\Sd::HOURS_HOURS_TYPE_9_5:
				$hours = [
					'opens'  => Data\Sd::HOURS_9_TO_5_OPENS,
					'closes' => Data\Sd::HOURS_9_TO_5_CLOSES
				];
				break;
			case Data\Sd::HOURS_HOURS_TYPE_8_6:
				$hours = [
					'opens'  => Data\Sd::HOURS_8_TO_6_OPENS,
					'closes' => Data\Sd::HOURS_8_TO_6_CLOSES
				];
				break;
			default:
				$hours = [];
				break;
		}
		return $hours;
	}

	/**
	 * Instantiate an adapter based on the type of SD rules being processed
	 * and use it to render the schema data for that rule.
	 *
	 * NB: We prevent:
	 *
	 * - multiple top-level SD types on the same page.
	 * - multiple rules for the same SD type to execute. The first executed
	 * rule wins and prevent subsequent ones to run (for the same type).
	 *
	 * @param array $rules
	 * @return $this
	 * @throws \Exception
	 */
	private function buildDataPerType($rules)
	{
		$executedRulesPerType = [];
		foreach ($rules as $rule)
		{
			$ruleDefinition = $rule->getRule();
			$ruleType       = Wb\arrayGet($ruleDefinition, 'actionSdType');
			$ruleType       = is_array($ruleType)
				? Wb\arrayGet($ruleType, 0)
				: $ruleType;

			if (
				// this rule is page level
				in_array($ruleType, Data\Sd::PAGE_LEVEL_TYPES)
				&&
				// a page level rule has already been executed
				array_intersect(
					Data\Sd::PAGE_LEVEL_TYPES,
					$executedRulesPerType
				)
			) {
				continue;
			}

			$className = Sd::class . '\\' . ucfirst(strtolower($ruleType));
			$renderer  = $this->factory->getA(
				$className,
				[
					'baseId'      => $this->baseId,
					'pageData'    => $this->pageData,
					'requestInfo' => $this->requestInfo,
				]
			);

			if (empty($renderer))
			{
				$this->logger->error(__METHOD__ . ', unable to create SD renderer for rule with type ' . $ruleType . ":\n" . print_r($ruleDefinition, true));
				return $this;
			}

			$rendered = $renderer->addSdSpecFromRule($ruleType, $ruleDefinition)
								 ->get();

			if (!empty($rendered))
			{
				$sdData = Wb\arrayGet($rendered, 'sdData');
				if (!empty($sdData))
				{
					$this->graph[]          = $sdData;
					$executedRulesPerType[] = $ruleType;
				}

				$this->identitiesUsed = array_merge(
					$this->identitiesUsed,
					Wb\arrayGet($rendered, 'identitiesUsed', [])
				);

				$this->identities = array_merge(
					$this->identities,
					Wb\arrayGet($rendered, 'identitiesCreated', [])
				);

				if (Data\Rule::SOURCE_BUILT_IN !== $rule->get('source'))
				{
					$rule->timestamp('last_hit')
						 ->increment('hits')
						 ->store();
				}

				$this->factory
					->getThe('forseo.logger')
					->debug(
						'Triggered %s rule for request %s, rule: %s',
						'structured data',
						$this->requestInfo->get('page_url'),
						$rule->getId()
					);
			}
		}

		return $this;
	}

	/**
	 * Render all identities used on the page, including dependencies.
	 *
	 * @param array $identitiesUsed
	 * @return $this
	 */
	private function renderIdentities($identitiesUsed)
	{
		foreach ($identitiesUsed as $identityUsed)
		{
			if (in_array($identityUsed, $this->identitiesRendered))
			{
				continue;
			}
			$identity = Wb\arrayGet($this->identities, $identityUsed);
			if (!empty($identity))
			{
				$this->graph[]              = $identity;
				$this->identitiesRendered[] = $identityUsed;
				if (!empty($this->identitiesDeps[$identityUsed]))
				{
					$this->renderIdentities($this->identitiesDeps[$identityUsed]);
				}
			}
		}

		return $this;
	}
}

