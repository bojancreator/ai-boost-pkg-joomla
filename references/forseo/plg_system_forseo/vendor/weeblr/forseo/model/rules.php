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
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Route;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;
use Weeblr\Wblib\Forseo\Joomla\Uri;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Rules extends Db\Dataobjectsortedlist
{
	/**
	 * @var array Holds all rules definitions as read from database or updated by code or API.
	 */
	protected static $rules = null;

	/**
	 * @var array Convenience array of the defaults values for a rule.
	 */
	protected $ruleDefaults = [];

	/**
	 * Store information about managed data.
	 *
	 * @param string $dataObjectClass
	 */
	public function __construct($dataObjectClass = null)
	{
		parent::__construct(Data\Rule::class);

		$this->defaultItemsPerPage = $this->factory
			->getThis(
				'forseo.config',
				'system'
			)->getInt(
				'defaultItemsPerPage',
				10
			);

		$this->ruleDefaults = $this->factory
			->getA(Data\Rule::class)
			->ruleDefaults();
	}

	/**
	 * Loads all current rules from the database, whether enabled or not.
	 * Cache them for the duration of the request.
	 * Called from the onAfterInitialize event.
	 */
	public function loadRules()
	{
		if (!is_null(self::$rules))
		{
			return $this;
		}

		// only run on front end
		if (!$this->platform->isFrontend())
		{
			return $this;
		}

		// Load rules that are:
		// - enabled, even if conditionallly
		// - not excluded by date
		$now      = System\Date::getUTCNow();
		$rawRules = $this->dbHelper
			->selectAssocList(
				'#__forseo_rules',
				'*',
				// amways enabled
				$this->dbHelper->quoteName('enabled') . ' = ?'
				// or enabled with conditions and...
				. ' or ('
				. $this->dbHelper->quoteName('enabled') . ' = ?'
				. ' and ('
				// enabled_after is either nulll or < now
				. $this->dbHelper->quoteName('enabled_after') . ' is null'
				. ' or '
				. $this->dbHelper->quoteName('enabled_after') . ' < ?'
				. ')'

				// or enabled_until is either nulll or > now
				. ' and ('
				. $this->dbHelper->quoteName('enabled_until') . ' is null'
				. ' or '
				. $this->dbHelper->quoteName('enabled_until') . ' > ?'
				. ')'
				. ')'
				,
				[
					Data\Rule::ENABLED,
					Data\Rule::ENABLED_WITH_CONDITIONS,
					$now,
					$now,
				],
				[
					'type'     => 'asc',
					'ordering' => 'asc',
				],
				null,
				null,
				'id'
			);

		if (empty($rawRules))
		{
			self::$rules = [];

			return $this;
		}

		$sortedRules = $rawRules;

		// built-in rules added at the start of the static::$rules array that holds all rules
		$this->loadBuiltinRules();

		// Then add user rules from the database
		foreach ($sortedRules as $sortedRawRule)
		{
			$ruleType = Wb\arrayGet($sortedRawRule, 'type');
			$rule     = $this->factory->getA(Data\Rule::class);
			$rule->withData(
				$sortedRawRule
			);

			Wb\arrayKeyInit(
				static::$rules,
				$ruleType,
				[]
			);
			static::$rules[$ruleType][] = $rule;
		}

		return $this;
	}

	/**
	 * There are a few built-in rules (Blocking Wordpress-centered bots for instance).
	 * Read them from config and build a set a rules object.
	 *
	 * Those are not user-editable for now (except through a filter).
	 */
	protected function loadBuiltinRules()
	{
		// WAF rules
		/**
		 * Filter the list of default, built-in WAF block rules URL specifications.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\rules
		 * @var forseo_default_block_rules
		 * @since   1.0.0
		 *
		 * @param array $wafRulesSpecs An array of URL specifications, such as /{*} or /blog/{*}.
		 *
		 * @return array
		 */
		$wafRulesSpecs = $this->factory->getThe('hook')->filter(
			'forseo_default_block_rules',
			$this->factory
				->getThis('forseo.config', 'app')
				->get('wafBlockedUrlsDefault', [])
		);

		foreach ($wafRulesSpecs as $wafRulesSpec)
		{
			$ruleObject  = $this->factory->getA(Data\Rule::class);
			$ruleSpecDef = [
				'type'    => Data\Rule::TYPE_WAF,
				'source'  => Data\Rule::SOURCE_BUILT_IN,
				'enabled' => Data\Rule::ENABLED_WITH_CONDITIONS,
				'title'   => 'Built-in WAF rule',
				'valid'   => 1,
			];

			$rule = array_merge(
				$ruleObject->getRule(),
				$ruleSpecDef,
				[
					'urlSpec' => $wafRulesSpec,
				]
			);

			$ruleObject->set(
				[
					'type'    => Data\Rule::TYPE_WAF,
					'source'  => Data\Rule::SOURCE_BUILT_IN,
					'enabled' => Data\Rule::ENABLED_WITH_CONDITIONS,
					'title'   => 'Built-in WAF rule',
					'valid'   => 1,
					'rule'    => $rule
				]
			);

			Wb\arrayKeyInit(
				static::$rules,
				Data\Rule::TYPE_WAF,
				[]
			);

			static::$rules[Data\Rule::TYPE_WAF][] = $ruleObject;
		}
	}

	/**
	 * @onAfterRoute Remove all rules that don't match the user groups specification
	 */
	public function filterByUser()
	{
		if (empty(static::$rules))
		{
			return $this;
		}

		$user   = $this->platform->getUser();
		$groups = array_map(
			function ($group)
			{
				return (int)$group;
			},
			$user->groups
		);

		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($groups)
					{
						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							return true;
						}
						$ruleSpec         = $rule->getRule();
						$allowedGroups    = Wb\arrayGet(
							$ruleSpec,
							'includedUsersGroups',
							[]
						);
						$disallowedGroups = Wb\arrayGet(
							$ruleSpec,
							'excludedUsersGroups',
							[]
						);

						if (
							empty($allowedGroups)
							&&
							empty($disallowedGroups))
						{
							// no user groups specification, let go
							return true;
						}
						if (
							!empty($allowedGroups)
							&&
							empty(array_intersect(
								$groups,
								$allowedGroups
							))
						) {
							return false;
						}

						if (
							!empty($disallowedGroups)
							&&
							!empty(array_intersect(
								$groups,
								$disallowedGroups
							))
						) {
							return false;
						}

						return true;
					}
				)
			);
		}

		return $this;
	}

	/**
	 * @onAfterRoute Remove all rules that don't match the IP address
	 */
	public function filterByIpAddress()
	{
		if (empty(static::$rules))
		{
			return $this;
		}

		$ip = $this->platform->getIp();
		$ip = StringHelper::strtolower($ip);

		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($ip)
					{
						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							return true;
						}
						$ruleSpec   = $rule->getRule();
						$allowedIps = trim(Wb\arrayGet(
							$ruleSpec,
							'includedIps',
							''
						));
						$allowedIps = StringHelper::strtolower($allowedIps);
						$allowedIps = System\Strings::stringToCleanedArray(
							$allowedIps,
							"\n"
						);

						$disallowedIps = trim(Wb\arrayGet(
							$ruleSpec,
							'excludedIps',
							''
						));
						$disallowedIps = StringHelper::strtolower($disallowedIps);
						$disallowedIps = System\Strings::stringToCleanedArray(
							$disallowedIps,
							"\n"
						);

						if (
							empty($allowedIps)
							&&
							empty($disallowedIps))
						{
							// no user groups specification, let go
							return true;
						}

						if (
							!empty($allowedIps)
							&&
							!$this->findIpMatchInList($ip, $allowedIps)
						) {
							return false;
						}

						if (
							!empty($disallowedIps)
							&&
							$this->findIpMatchInList($ip, $disallowedIps)
						) {
							return false;
						}

						return true;
					}
				)
			);
		}

		return $this;
	}

	/**
	 * Finds a match in a list of IP addresses, allowing for {*} and {?} wildcard
	 * characters.
	 *
	 * @param string $ip
	 * @param array  $ipSpecs
	 * @return bool
	 */
	private function findIpMatchInList($ip, $ipSpecs)
	{
		if (
			!empty($ip)
			&&
			!empty($ipSpecs)
		) {
			foreach ($ipSpecs as $ipSpec)
			{
				if (System\Route::matchUrlRule($ipSpec, $ip))
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @onAfterRoute Remove all rules that don't match the site home address specification
	 */
	public function filterByHomeAddress()
	{
		if (empty(static::$rules))
		{
			return $this;
		}

		$requestHomeAddress = $this->platform->getRootUrl(false /* $pathOnly */);
		$requestHomeAddress = StringHelper::strtolower($requestHomeAddress);

		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($requestHomeAddress)
					{
						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							return true;
						}
						$ruleSpec             = $rule->getRule();
						$allowedHomeAddresses = trim(Wb\arrayGet(
							$ruleSpec,
							'includedHomeAddresses',
							''
						));
						$allowedHomeAddresses = StringHelper::strtolower($allowedHomeAddresses);
						$allowedHomeAddresses = System\Strings::stringToCleanedArray(
							$allowedHomeAddresses,
							"\n"
						);

						$disallowedHomeAddresses = trim(Wb\arrayGet(
							$ruleSpec,
							'excludedHomeAddresses',
							''
						));
						$disallowedHomeAddresses = StringHelper::strtolower($disallowedHomeAddresses);
						$disallowedHomeAddresses = System\Strings::stringToCleanedArray(
							$disallowedHomeAddresses,
							"\n"
						);

						if (
							empty($allowedHomeAddresses)
							&&
							empty($disallowedHomeAddresses))
						{
							// no user groups specification, let go
							return true;
						}

						if (
							!empty($allowedHomeAddresses)
							&&
							!$this->findHomeAddressMatchInList($requestHomeAddress, $allowedHomeAddresses)
						) {
							return false;
						}

						if (
							!empty($disallowedHomeAddresses)
							&&
							$this->findHomeAddressMatchInList($requestHomeAddress, $disallowedHomeAddresses)
						) {
							return false;
						}

						return true;
					}
				)
			);
		}

		return $this;
	}


	// should I just use the included/excluded URL field for that? instead of adding new set of fields.
	// the change would be only to allow for entering a fully qualified URL in the list
	// the rest of the comparison would likely not change much
	// That code is tricky, will require much testing
	/**
	 * Finds a match in a list of websites home addresses, allowing for {*} and {?} wildcard
	 * characters.
	 *
	 * - scheme must match
	 * - home address differs from domain in that it may include a path, in the case of sites located
	 * in  a subfolder.
	 *
	 * @param string $searchedHomeAdress
	 * @param array  $homeAdresses
	 * @return bool
	 */
	private function findHomeAddressMatchInList($searchedHomeAdress, $homeAdresses)
	{
		if (
			!empty($searchedHomeAdress)
			&&
			!empty($homeAdresses)
		) {
			if (in_array($searchedHomeAdress, $homeAdresses))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @onAfterRoute Remove all rules that don't match the URL specification
	 */
	public function filterByUrl()
	{
		if (empty(static::$rules))
		{
			return $this;
		}

		$uri = $this->factory->getA(
			Uri\Uri::class,
			$this->factory->getThe('forseo.pageHelper')
						  ->getCleanedCurrentUrl()
		);

		$requestedpath = rawurldecode(
			System\Route::makeRootRelative(
				$uri->getPath()
			)
		);
		$requestedpath = preg_replace('/\s/u', '%20', $requestedpath);

		$queryString = $uri->toString(
			['query']
		);

		$requestedPathAndQuery = System\Route::makeRootRelative(
			$requestedpath . $queryString
		);

		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($requestedpath, $requestedPathAndQuery, $queryString)
					{
						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							// set to "on all pages"
							return true;
						}

						$ruleSpec             = $rule->getRule();
						$disregardQueryString = Wb\arrayGet(
							$ruleSpec,
							'disregardQuery',
							true
						);

						$usableUrl = $disregardQueryString
							? $requestedpath
							: $requestedPathAndQuery;

						$disregardCase = Wb\arrayGet(
							$ruleSpec,
							'disregardCase',
							true
						);

						if ($disregardCase)
						{
							$usableUrl = StringHelper::strtolower($usableUrl);
						}

						// inclusion specification
						$shouldRun = false;
						$urlSpec   = Wb\arrayGet(
							$ruleSpec,
							'urlSpec',
							''
						);

						$urlSpec = trim($urlSpec);

						if (
							empty($urlSpec)
							||
							'/{*}' === $urlSpec)
						{
							// no URL specification to match, keep the rule
							$shouldRun = true;
						}

						// by-pass checking each individual inclusive URL specification
						// if the specification is a full wildcard
						if (!$shouldRun)
						{
							// URLs specification can be an array (4.0+)
							$urlSpecs = System\Strings::stringToCleanedArray(
								$urlSpec,
								"\n"
							);

							foreach ($urlSpecs as $singleUrlSpec)
							{
								if (empty($singleUrlSpec))
								{
									continue;
								}

								$safeUrlSpec = str_replace(
									['{*}', '{?}'],
									['__WB_WILD_STAR__', '__WB_WILD_QUESTION__'],
									$singleUrlSpec
								);

								$urlSpecBits = explode(
									'?',
									$safeUrlSpec,
									2
								);

								$singleUrlSpec = rawurldecode($urlSpecBits[0]);
								$singleUrlSpec = preg_replace('/\s/u', '%20', $singleUrlSpec);
								if (!empty($urlSpecBits[1]))
								{
									$singleUrlSpec .= '?' . $urlSpecBits[1];
								}

								$singleUrlSpec = str_replace(
									['__WB_WILD_STAR__', '__WB_WILD_QUESTION__'],
									['{*}', '{?}'],
									$singleUrlSpec
								);

								// is it a regular expression?
								if (Wb\startsWith($singleUrlSpec, '~'))
								{
									if (preg_match(
										$singleUrlSpec,
										$usableUrl
									))
									{
										$shouldRun = true;
										break;
									};
								}

								if ($disregardCase)
								{
									$singleUrlSpec = StringHelper::strtolower($singleUrlSpec);
								}

								if (
									System\Route::matchUrlRule(
										$singleUrlSpec,
										$usableUrl
									)
								) {
									$shouldRun = true;
									break;
								};
							}
						}

						if (!$shouldRun)
						{
							// inclusive URL spec says rule should not run
							// no need to check exclusion specs
							return false;
						}

						// exclusion specification
						$urlNegSpec = Wb\arrayGet(
							$ruleSpec,
							'urlNegSpec',
							''
						);

						$urlNegSpec = trim($urlNegSpec);
						if ('/{*}' === $urlNegSpec)
						{
							// user excluded all URLs (?)
							return false;
						}

						// URLs specification can be an array (4.0+)
						$urlNegSpecs = System\Strings::stringToCleanedArray(
							$urlNegSpec,
							"\n"
						);

						$shouldRun = true;
						foreach ($urlNegSpecs as $singleUrlSpec)
						{
							$safeUrlSpec = str_replace(
								['{*}', '{?}'],
								['__WB_WILD_STAR__', '__WB_WILD_QUESTION__'],
								$singleUrlSpec
							);

							$urlSpecBits = explode(
								'?',
								$safeUrlSpec,
								2
							);

							$singleUrlSpec = rawurldecode($urlSpecBits[0]);
							$singleUrlSpec = preg_replace('/\s/u', '%20', $singleUrlSpec);

							if (!empty($urlSpecBits[1]))
							{
								$singleUrlSpec .= '?' . $urlSpecBits[1];
							}

							$singleUrlSpec = str_replace(
								['__WB_WILD_STAR__', '__WB_WILD_QUESTION__'],
								['{*}', '{?}'],
								$singleUrlSpec
							);

							// is it a regular expression?
							if (Wb\startsWith($singleUrlSpec, '~'))
							{
								if (preg_match(
									$singleUrlSpec,
									$usableUrl
								))
								{
									$shouldRun = false;
									break;
								};
							}

							if ($disregardCase)
							{
								$singleUrlSpec = StringHelper::strtolower($singleUrlSpec);
							}

							if (
								System\Route::matchUrlRule(
									$singleUrlSpec,
									$usableUrl
								)
							) {
								$shouldRun = false;
								break;
							};
						}

						return $shouldRun;
					}
				)
			);
		}

		return $this;
	}

	/**
	 * Ran at onAfterRoute: Remove all rules that don't match the language specification.
	 */
	public function filterByLanguage()
	{
		if (empty(static::$rules))
		{
			return $this;
		}

		$currentRequestLanguage = $this->platform->getCurrentLanguageTag();
		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($currentRequestLanguage)
					{
						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							return true;
						}
						$ruleSpec                = $rule->getRule();
						$allowedContentLanguages = Wb\arrayGet(
							$ruleSpec,
							'includedLanguages',
							[]
						);
						if (empty($allowedContentLanguages))
						{
							return true;
						}

						return in_array(
							$currentRequestLanguage,
							$allowedContentLanguages
						);
					}
				)
			);
		}

		return $this;
	}

	/**
	 * Ran at onAfterRoute: Remove all rules that don't match the extension specification.
	 */
	public function filterByContentType()
	{
		if (empty(static::$rules))
		{
			return $this;
		}

		$currentRequestExtension = $this->platform->getCurrentContentType();
		$view                    = $this->platform->getHttpInput()->get('view', '');
		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($currentRequestExtension, $view)
					{

						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							return true;
						}

						$ruleSpec = $rule->getRule();

						$allowedContentTypes = Wb\arrayGet(
							$ruleSpec,
							'includedExtensions',
							[]
						);

						$allowedViews = Wb\arrayGet(
							$ruleSpec,
							'viewSpec',
							''
						);
						$allowedViews = System\Strings::stringToCleanedArray(
							trim($allowedViews),
							"\n"
						);

						$disallowedViews = Wb\arrayGet(
							$ruleSpec,
							'viewNegSpec',
							''
						);
						$disallowedViews = System\Strings::stringToCleanedArray(
							trim($disallowedViews),
							"\n"
						);

						if (
							empty($allowedContentTypes)
							&&
							empty($allowedViews)
							&&
							empty($disallowedViews)
						) {
							// no restriction, can run
							return true;
						}

						if (
							!empty($allowedContentTypes)
							&&
							(
								empty($currentRequestExtension)
								||
								!in_array(
									$currentRequestExtension,
									$allowedContentTypes
								)
							)
						) {
							// not the right extension, cannot run
							return false;
						}

						$shouldRun = true;
						if (
							!empty($allowedViews)
							&&
							(
								empty($view)
								||
								!in_array(
									$view,
									$allowedViews
								)
							)
						) {
							// restriction on views, and view is not on the list, cannot run
							$shouldRun = false;
						}

						if (
							!empty($disallowedViews)
							&&
							!empty($view)
							&&
							in_array(
								$view,
								$disallowedViews
							)
						) {
							// restriction on view, and have invalid view, cannot run
							$shouldRun = false;
						}

						return $shouldRun;
					}
				)
			);
		}

		return $this;
	}

	/**
	 * Ran at onAfterRoute: Remove all rules that don't match the categories specification
	 */
	public function filterByCategories()
	{
		if (empty(static::$rules))
		{
			return $this;
		}

		/**
		 * Filter the content category for current request belongs to.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\content
		 * @var forseo_current_request_category
		 * @since   1.0.0
		 *
		 * @param object $category The category object as already established by wbLib.
		 *
		 * @return object
		 */
		$currentRequestCategory = $this->factory->getThe('hook')->filter(
			'forseo_current_request_category',
			$this->platform->getCurrentRequestCategory()
		);

		$currentRequestCategoryPath = empty($currentRequestCategory)
			? ''
			: $currentRequestCategory->extension . '.' . $currentRequestCategory->path;

		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($currentRequestCategoryPath)
					{
						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							return true;
						}
						$ruleSpec             = $rule->getRule();
						$allowedCategories    = Wb\arrayGet(
							$ruleSpec,
							'includedCategories',
							[]
						);
						$disallowedCategories = Wb\arrayGet(
							$ruleSpec,
							'excludedCategories',
							[]
						);

						if (
							empty($allowedCategories)
							&&
							empty($disallowedCategories))
						{
							// no category specification, let go
							return true;
						}

						if (
							!empty($allowedCategories)
							&&
							(
								empty($currentRequestCategoryPath)
								||
								!in_array(
									$currentRequestCategoryPath,
									$allowedCategories
								)
							)
						) {
							return false;
						}

						if (
							!empty($disallowedCategories)
							&&
							empty($currentRequestCategoryPath)
						) {
							return true;
						}

						if (
							!empty($disallowedCategories)
							&&
							in_array(
								$currentRequestCategoryPath,
								$disallowedCategories
							)
						) {
							return false;
						}

						return true;
					}
				)
			);
		}

		return $this;
	}

	/**
	 * Ran at onAfterRoute. Filters out any custom field-based rule that do not apply to the
	 * current component.
	 *
	 * @return Rules
	 */
	public function filterByCustomFieldByExtension()
	{
		$option = $this->platform->getHttpInput()->getCmd('option');

		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($option)
					{
						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							return true;
						}
						$ruleSpec      = $rule->getRule();
						$customFieldId = Wb\arrayGet(
							$ruleSpec,
							'customFieldId',
							[]
						);

						// id is stored as an array
						$customFieldId = Wb\arrayGet(
							$customFieldId,
							0
						);

						if (empty($customFieldId))
						{
							// no condition on custom fields, don't change the execution status
							return true;
						}

						$fieldContext = $this->platform->getFieldContextFromId($customFieldId);

						return Wb\startsWith(
							$fieldContext,
							$option . '.'
						);
					}
				)
			);
		}

		return $this;
	}

	/**
	 * Ran at onPrepareContent: Remove all rules that don't match the custom fields specification.
	 *
	 * [
	 * 'modified' => false,
	 * 'context'  => $context,
	 * 'content'  => $row,
	 * 'params'   => $params,
	 * 'page'     => $page
	 * ]
	 *
	 * @param array $contentData
	 * @return Rules
	 */
	public function filterByCustomField($contentData)
	{
		if (empty($contentData))
		{
			return $this;
		}

		$contentDataContext = Wb\arrayGet($contentData, 'context');
		if (
			!Wb\startsWith(
				$contentDataContext,
				'com_'
			))
		{
			// do away with all module preparation calls
			return $this;
		}

		$customFieldsHelper = $this->factory->getA(Helper\Customfields::class);

		foreach (static::$rules as $ruleType => $rules)
		{
			static::$rules[$ruleType] = array_values(
				array_filter(
					static::$rules[$ruleType],
					function ($rule) use ($contentData, $contentDataContext, $customFieldsHelper)
					{
						if ($rule->get('enabled') == Data\Rule::ENABLED)
						{
							return true;
						}
						$ruleSpec      = $rule->getRule();
						$customFieldId = Wb\arrayGet(
							$ruleSpec,
							'customFieldId',
							[]
						);

						// id is stored as an array
						$customFieldId = Wb\arrayGet(
							$customFieldId,
							0
						);

						if (empty($customFieldId))
						{
							// no condition on custom fields, this rule should be executed
							// as any other condition has been already checked at onAfterRoute
							return true;
						}

						$fieldContext = $this->platform->getFieldContextFromId($customFieldId);
						if (
							empty($fieldContext)
							||
							$fieldContext !== $contentDataContext
						) {
							// this content type does not offer this custom field context
							// but that CF is required, so don't run the rule
							return false;
						}

						// get the custom fields values for the current page content
						$fields = $this->platform->getCustomFieldsForContent(
							$contentDataContext,
							Wb\arrayGet($contentData, 'content'),
							true, // $prepareValue
							null  // $valuesToOverride
						);

						foreach ($fields as $field)
						{
							if ($customFieldId !== $field->id)
							{
								continue;
							}

							// get the field value and compare it to the rule
							return $customFieldsHelper->pass(
								$field,
								Wb\arrayGet($ruleSpec, 'cfOperator'),
								Wb\arrayGet($ruleSpec, 'cfValue'),
								Wb\arrayGet($ruleSpec, 'cfValue2')
							);
						}

						// if the desired custom field is not defined for this content item
						// reject the rule.
						return false;
					}
				)
			);
		}

		return $this;
	}

	/**
	 * Getter for the rules loaded from the db, possibly filtered based on
	 * conditions for the current request in each rule.
	 *
	 * @param int $ruleType
	 *
	 * @return array
	 */
	public function getRulesSpecs($ruleType = null)
	{
		if (empty($ruleType))
		{
			return static::$rules;
		}
		else
		{
			return Wb\arrayGet(
				static::$rules,
				$ruleType,
				[]
			);
		}
	}

	/**
	 * Create a record from raw data received from client.
	 *
	 * @param array $data
	 *
	 * @return array|\Exception
	 * @throws \Throwable
	 */
	public function create($data)
	{
		$this->factory->getThe('forseo.logger')->debug('Rule creation with raw data ' . print_r($data, true));
		$preProcessed = [
			'type'           => Wb\arrayGet($data, 'type'),
			'source'         => Wb\arrayGet($data, 'source', Data\Rule::SOURCE_USER),
			'title'          => Wb\arrayGet($data, 'title'),
			'rule'           => Wb\arrayGet($data, 'rule'),
			'enabled'        => Wb\arrayGet($data, 'enabled'),
			'enabled_after'  => Wb\arrayGet($data, 'enabled_after'),
			'enabled_until'  => Wb\arrayGet($data, 'enabled_until'),
			'ordering'       => Wb\arrayGet($data, 'ordering', 0),
			'orderAfter'     => Wb\arrayGet($data, 'orderAfter', 0),
			'orderTarget'    => Wb\arrayGet($data, 'orderTarget', 0),
			'orderDirection' => Wb\arrayGet($data, 'orderDirection', 'after'),
		];

		return $this->store($preProcessed);
	}

	/**
	 * Normalize a URL specification (source of a redirect). Basically ensures
	 * it starts with a /.
	 *
	 * @param string $url
	 * @return string
	 */
	public function normalizeUrlSpec($url)
	{
		return '/' . Wb\lTrim($url, '/');
	}

	/**
	 * Normalize the target of a redirect or a canonical. Can be:
	 * - starts with http/https
	 * - start with a /
	 *
	 * @param string $url
	 * @return string
	 */
	public function normalizeTarget($url)
	{
		return System\Route::makeRootRelative(
			$url,
			false,  // $removeLeadingSlash
			null,   // $currentUrl
			false   // $isAsset
		);
	}

	/**
	 * Delete imported rules, identified by a source ID.
	 *
	 * @param int $sourceId
	 * @return array|\Exception
	 */
	public function deleteImportedRules($sourceId)
	{
		try
		{
			$this->dbHelper->delete(
				'#__forseo_rules',
				[
					'source' => (int)$sourceId
				]
			);
		}
		catch (\Exception $e)
		{
			return new \Exception($e->getMessage(), System\Http::RETURN_INTERNAL_ERROR);
		}

		return [
			'data'  => null,
			'count' => 0,
			'total' => 0
		];
	}

	/**
	 * Hook to post-process list of data read.
	 *
	 * @param mixed $data
	 * @param array $options
	 *
	 * @return mixed
	 */
	protected function afterGetList($data, array $options)
	{
		$data = parent::afterGetList(
			$data,
			$options
		);

		// Make sure we always return rule as an object, not a stringified version
		$data['data'] = array_map(
			function ($item) use ($options)
			{
				$format = Wb\arrayGet($options, 'format');
				if ('short' === $format)
				{
					return array_intersect_key(
						$item,
						array_flip(
							[
								'id',
								'title'
							]
						)
					);
				}

				if (!is_array($item['rule']))
				{
					$item['rule'] = json_decode(
						Wb\arrayGet(
							$item,
							'rule',
							''
						),
						true
					);
				}

				// in case of fields addition
				$item['rule'] = array_merge(
					$this->ruleDefaults,
					$item['rule']
				);

				return $item;
			},
			$data['data']
		);

		return $data;
	}

	/**
	 * Method to extend the default where clause.
	 *
	 * @param array $options
	 * @param array $clause
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	protected function extendWhereClause($options, $clause)
	{
		$typeClause = $this->buildTypeWhereClause($options);
		if (!empty($typeClause))
		{
			$clause[] = $typeClause;
		}

		return $clause;
	}

	/**
	 * Build a where clause taking into account desired
	 * type values.
	 *
	 * @param Array $options
	 *
	 * @return string
	 */
	protected function buildTypeWhereClause($options)
	{
		$rulesType = Wb\arrayGet(
			$options,
			'rulesType',
			'others'
		);

		$type = Wb\arrayGet(
			$options,
			'type',
			'all'
		);

		// if rulesType is structured data, replacer, redirect, error_page or analytics, use it directly
		// else use the type query
		switch ($rulesType)
		{
			case 'others':
			case Data\Rule::TYPE_REDIRECT:
			case Data\Rule::TYPE_ERROR_PAGE:
			case Data\Rule::TYPE_REPLACER:
			case Data\Rule::TYPE_ANALYTICS:
			case Data\Rule::TYPE_SD:
				break;
			default:
				$rulesType = Wb\arrayGet(
					$options,
					'type',
					'others'
				);
				break;
		}

		$clause = '';
		switch ($rulesType)
		{
			case Data\Rule::TYPE_REDIRECT:
			case Data\Rule::TYPE_ERROR_PAGE:
			case Data\Rule::TYPE_REPLACER:
			case Data\Rule::TYPE_ANALYTICS:
			case Data\Rule::TYPE_SD:
				$clause = $this->dbHelper->quoteName('type')
						  . ' = ' . $this->dbHelper->quote($rulesType);
				break;
			case 'others':
				$clause = $this->dbHelper->quoteName('type')
						  . ' != ' . $this->dbHelper->quote(Data\Rule::TYPE_NONE)
						  . ' and '
						  . $this->dbHelper->quoteName('type')
						  . ' != ' . $this->dbHelper->quote(Data\Rule::TYPE_REDIRECT)
						  . ' and '
						  . $this->dbHelper->quoteName('type')
						  . ' != ' . $this->dbHelper->quote(Data\Rule::TYPE_ERROR_PAGE)
						  . ' and '
						  . $this->dbHelper->quoteName('type')
						  . ' != ' . $this->dbHelper->quote(Data\Rule::TYPE_REPLACER)
						  . ' and '
						  . $this->dbHelper->quoteName('type')
						  . ' != ' . $this->dbHelper->quote(Data\Rule::TYPE_ANALYTICS)
						  . ' and '
						  . $this->dbHelper->quoteName('type')
						  . ' != ' . $this->dbHelper->quote(Data\Rule::TYPE_SD);
				break;
		}

		switch ($type)
		{
			case Data\Rule::TYPE_CANONICAL:
			case Data\Rule::TYPE_META:
			case Data\Rule::TYPE_WAF:
			case Data\Rule::TYPE_RAW_CONTENT:
			case Data\Rule::TYPE_ROBOTS:
			case Data\Rule::TYPE_SOCIAL:
			case Data\Rule::TYPE_SITEMAP:
				$clause = $this->dbHelper->quoteName('type')
						  . ' = ' . $this->dbHelper->quote($type);
				break;
		}

		return $clause;
	}
}
