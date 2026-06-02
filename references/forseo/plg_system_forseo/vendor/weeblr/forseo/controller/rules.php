<?php
/**
 * Project: 4SEO
 *
 * @package                 4SEO
 * @copyright               Copyright Weeblr llc - 2020 - 2026
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 6.10.1.2660
 *
 * 2026-01-30
 */

namespace Weeblr\Forseo\Controller;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Data\Requestinfo;
use Weeblr\Forseo\Model;
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Mvc;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Render a story page
 */
class Rules extends Base\Base
{
	/**
	 * @var Model\Rules Convenience model instance.
	 */
	protected $model;

	/**
	 * @var Helper\Page
	 */
	protected $pageHelper;

	/**
	 * @var Helper\Rules
	 */
	protected $rulesHelper;

	/**
	 * @var Helper\Customfields
	 */
	protected $customFieldsHelper;

	/**
	 * @var Model\Injector\Variables
	 */
	protected $variablesExpander;

	/**
	 * @var Model\Injector\Meta
	 */
	protected $metaInjector;

	/**
	 * @var array
	 */
	protected $executedByType = [];

	/**
	 * @var Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo = null;

	/**
	 * @var bool Whether {4seo_* tags should be removed from conte after rule execution.
	 */
	private $cleanTagsAfterMetaRuleExec = false;

	/**
	 * @var array Stores replacements count made on the page as user can limit that optionally.
	 */
	protected $replacementsCounters = [];

	public function __construct()
	{
		parent::__construct();

		$this->model = $this->factory->getA(
			Model\Rules::class
		);

		$this->pageHelper         = $this->factory->getThe('forseo.pageHelper');
		$this->rulesHelper        = $this->factory->getA(Helper\Rules::class);
		$this->customFieldsHelper = $this->factory->getA(Helper\Customfields::class);
		$this->requestInfo        = $this->factory->getThe('forseo.requestInfo');
		$this->executedByType     = [
			'canonical'    => false,
			'modifyTitle'  => false,
			'modifyDesc'   => false,
			'modifyRobots' => false,
			'sharing'      => false,
		];
	}

	/**
	 * Ran at onAfterRoute, filters out those rules that should not be
	 * triggered on the current request.
	 */
	public function filterExecutableRules()
	{
		$this->model
			->filterByUser()
			->filterByIpAddress()
			->filterByHomeAddress()
			->filterByUrl()
			->filterByLanguage()
			->filterByContentType()
			->filterByCategories()
			->filterByCustomFieldByExtension();

		return $this;
	}

	/**
	 * Ran at onContentPrepare, removes rules based on existence of custom fields.
	 * Custom fields cannot be checked before onContentPrepare as we need to know the
	 * page content to find their value.
	 *
	 * There's a case to run this at onAfterDispatch, as not all extensions will trigger
	 * onContentPrepare, and may have their own custom fields implementation.
	 *
	 * @param array $contentData
	 * @return array
	 */
	public function filterExecutableRulesByCustomField($contentData = [])
	{
		if (empty($contentData))
		{
			return $contentData;
		}

		$this->model->filterByCustomField(
			$contentData
		);

		return $contentData;
	}

	/**
	 * Get all applicable rules of a give type.
	 *
	 * @return array
	 */
	public function getRulesPerType($type)
	{
		try
		{
			$rules = $this->model->getRulesSpecs($type);
			return empty($rules)
				? []
				: $rules;
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('Rules controller model getRules %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return [];
	}

	/**
	 * Execute all redirects rules that are left after filtering by conditions.
	 * We try executing all triggered rules instead of just the 1st one as some
	 * rules may fail after being expanded, may result in infinite loops, etc
	 *
	 * @param array $contentData
	 * @return array
	 */
	public function executeRedirects($contentData = [])
	{
		try
		{
			if ($this->factory->getThis('forseo.config', 'redirects')
							  ->isFalsy('enabled'))
			{
				return $contentData;
			}

			$rules = $this->model->getRulesSpecs(Data\Rule::TYPE_REDIRECT);
			if (empty($rules))
			{
				return $contentData;
			}

			$currentUrl = $this->pageHelper->getCleanedCurrentUrl();
			foreach ($rules as $applicableRule)
			{
				// Possibly add a filter before execution?
				$ruleSpec = $applicableRule->getRule();
				if (
					empty($contentData)
					&&
					Wb\ArrayIsTruthy($ruleSpec, 'customFieldId')
				) {
					// No data item for that page yet, but there's a custom field specification
					// we must wait until onContentPrepare to check if the rule applies
					continue;
				}

				$redirectType = Wb\arrayGet(
					$ruleSpec,
					'actionRedirectType'
				);

				$targetUrl = $this->rulesHelper->buildTarget(
					$ruleSpec,
					$currentUrl,
					'actionRedirectTarget'
				);

				if ($this->platform->canRedirect(
					$currentUrl,
					$targetUrl
				))
				{
					if (Data\Rule::REDIRECT_TYPE_TO_SEF === $redirectType)
					{
						$redirectType = Data\Rule::REDIRECT_TYPE_301;
					}

					$this->logRuleExecution('redirect', $applicableRule, $currentUrl, $ruleSpec)
						->platform->redirectTo(
							$targetUrl,
							$redirectType
						);
				}
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('Rules controller model executeRedirects %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $contentData;
	}

	/**
	 * Check all metadata rules that are left after filtering by conditions,
	 * and execute them, only one per subtype.
	 *
	 * @return Rules
	 */
	public function executeMeta()
	{
		if ($this->factory->getThis('forseo.config', 'rules')
						  ->isFalsy('enabled'))
		{
			return $this;
		}

		// currently only handling meta in content
		if (!$this->platform->isHtmlPage())
		{
			return $this;
		}

		/**
		 * Filter whether {4seo_*} tags should be removed from the result of the
		 * execution of metadata rules. If left to false, the default, this allows using user-defined tags
		 * in meta rules output, which can then be replaced with a replacer rule.
		 * If set to true, this ensures no such tags is left in the final result.
		 *
		 * @api     forseo
		 * @var forseo_filter_clean_tags_after_meta_rule_exec
		 * @package 4SEO\filter\content
		 * @since   4.4.0
		 *
		 * @param bool $cleanTagsAfterMetaRuleExec
		 *
		 * @return bool
		 *
		 */
		$this->cleanTagsAfterMetaRuleExec = $this->factory
			->getThe('hook')
			->filter(
				'forseo_filter_clean_tags_after_meta_rule_exec',
				$this->cleanTagsAfterMetaRuleExec
			);

		$rules = $this->model->getRulesSpecs(Data\Rule::TYPE_META);
		if (empty($rules))
		{
			return $this;
		}

		$this->variablesExpander = $this->factory->getThe('forseo.variablesExpander');
		$this->metaInjector      = $this->factory->getA(Model\Injector\Meta::class);
		$currentUrl              = $this->pageHelper->getCleanedCurrentUrl();
		foreach ($rules as $applicableRule)
		{
			try
			{
				foreach ($this->executedByType as $type => $executed)
				{
					if ($executed)
					{
						// Only execute the first rule per type
						continue;
					}

					$methodName = 'executeByType' . ucfirst($type);
					if (method_exists($this, $methodName))
					{
						$this->{$methodName}($applicableRule, $currentUrl);
					}
				}
			}
			catch (\Throwable $e)
			{
				$this->factory->getThe('forseo.logger')->error('Rules controller executeMeta %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
			}
		}

		return $this;
	}

	/**
	 * Replaces tags in a page title specification and inject that new title
	 *
	 * @param $applicableRule
	 * @param $currentUrl
	 * @return void
	 * @throws \Exception
	 */
	protected function executeByTypeModifyTitle($applicableRule, $currentUrl)
	{
		$ruleSpec  = $applicableRule->getRule();
		$titleSpec = Wb\arrayGet($ruleSpec, 'actionMetaTitleSpec', '');
		if ('{page_title}' === $titleSpec)
		{
			// no specification given, we're not modifying the title
			return;
		}

		$titleSpec = str_replace(
			'{page_title}',
			'{4seo_page_title}',
			$titleSpec
		);

		$expansionResult = $this->variablesExpander->expand(
			$titleSpec,
			$expansionCount
		);

		if (empty($expansionResult))
		{
			// no expansion, no title
			return;
		}

		$updatedTitle = StringHelper::trim(
			$this->cleanVariableTags($expansionResult[0])
		);

		$this->factory->getThe('forseo.requestInfo')->set(
			'page_title',
			$updatedTitle
		);

		$this->executedByType['modifyTitle'] = true;
		$this->logRuleExecution('meta:title', $applicableRule, $currentUrl, $ruleSpec)
			->factory
			->getThe('forseo.logger')
			->debug('Updating page title %s to request %s, rule: %s', $updatedTitle, $currentUrl, print_r($ruleSpec, true));
	}

	/**
	 * Remove {4seo_*} tags from a string, if the cleanTagsAfterMetaRuleExec flag is set.
	 *
	 * @param string $content
	 * @return string
	 */
	private function cleanVariableTags($content)
	{
		if (!$this->cleanTagsAfterMetaRuleExec)
		{
			return $content;
		}

		return $this->variablesExpander->cleanVariablesTags($content);
	}

	/**
	 * Replaces tags in a page description meta tag specification and inject that new description
	 *
	 * @param $applicableRule
	 * @param $currentUrl
	 * @return void
	 * @throws \Exception
	 */
	protected function executeByTypeModifyDesc($applicableRule, $currentUrl)
	{
		$ruleSpec    = $applicableRule->getRule();
		$requestInfo = $this->factory->getThe('forseo.requestInfo');

		if (Wb\arrayIsTruthy($ruleSpec, 'actionMetaDescSuppress', false))
		{
			// we set the flag for removing the description but still
			// try and apply the other description modificaction
			// instructions as the description could also be used
			// for OGP or schema tags
			$requestInfo->set(
				'page_suppress_meta_description',
				true
			);
		}

		if (
			Wb\arrayIsTruthy($ruleSpec, 'actionMetaDescIfEmpty', false)
			&&
			(
				empty($requestInfo->get('page_description'))
				||
				(
					$requestInfo->get('page_description') == $requestInfo->get('page_custom_description')
				)
			)
		) {
			return;
		}

		$descriptionSpec = Wb\arrayGet($ruleSpec, 'actionMetaDescSpec', '');
		if ('{page_description}' === $descriptionSpec)
		{
			// no specification given, we're not modifying the desc
			return;
		}

		$descriptionSpec = str_replace(
			'{page_description}',
			'{4seo_page_description}',
			$descriptionSpec
		);

		$expansionResult = $this->variablesExpander->expand(
			$descriptionSpec,
			$expansionCount
		);

		if (empty($expansionResult))
		{
			// no expansion, no title
			return;
		}

		$updatedDescription = StringHelper::trim(
			$this->cleanVariableTags($expansionResult[0])
		);

		$this->factory->getThe('forseo.requestInfo')->set(
			'page_description',
			$updatedDescription
		);

		$this->executedByType['modifyDesc'] = true;
		$this->logRuleExecution('meta:description', $applicableRule, $currentUrl, $ruleSpec)
			->factory
			->getThe('forseo.logger')
			->debug('Updating description %s to request %s, rule: %s', $updatedDescription, $currentUrl, print_r($ruleSpec, true));
	}

	/**
	 * @throws \Exception
	 */
	protected function executeByTypeModifyRobots($applicableRule, $currentUrl)
	{
		$ruleSpec = $applicableRule->getRule();

		$robotsValue = Wb\arrayGet($ruleSpec, 'actionMetaRobots', '');
		if (empty($robotsValue))
		{
			return;
		}

		if ('__custom__' === $robotsValue)
		{
			$robotsValue = StringHelper::trim(
				Wb\arrayGet($ruleSpec, 'actionMetaRobotsCustom', '')
			);
		}

		if (!empty($robotsValue))
		{
			$this->factory->getA(Helper\Meta::class)
						  ->injectRobots(
							  $robotsValue,
							  '4SEO_meta_rule_' . $applicableRule->getId()
						  );

			$this->factory->getThe('forseo.requestInfo')->set(
				'page_robots',
				$robotsValue
			);

			$this->executedByType['modifyRobots'] = true;
			$this->logRuleExecution('meta:robots', $applicableRule, $currentUrl, $ruleSpec)
				->factory
				->getThe('forseo.logger')
				->debug('Adding robots %s to request %s, rule: %s', $robotsValue, $currentUrl, print_r($ruleSpec, true));
		}
	}

	/**
	 * Execute a canonical rule if it applies to the current page.
	 *
	 * @param $applicableRule
	 * @param $currentUrl
	 * @return void
	 * @throws \Exception
	 */
	protected function executeByTypeCanonical($applicableRule, $currentUrl)
	{
		$ruleSpec = $applicableRule->getRule();
		if (Data\Rule::CANONICAL_TYPE_DO_NOT_CHANGE === Wb\arrayGet($ruleSpec, 'actionCanonicalTargetSource', Data\Rule::CANONICAL_TYPE_DO_NOT_CHANGE))
		{
			// no canonical to add
			return;
		}

		$useCf = Wb\arrayGet($ruleSpec, 'actionCanonicalTargetUseCf', 'processed');
		if ('processed' !== $useCf)
		{
			$customFieldId                     = Wb\arrayGet($ruleSpec, ['actionCanonicalTargetCfId', 0], 0);
			$ruleSpec['actionCanonicalTarget'] = $this->customFieldsHelper->getFieldValueById(
				$customFieldId
			);
		}

		$targetUrl = $this->rulesHelper->buildTarget(
			$ruleSpec,
			$currentUrl,
			'actionCanonicalTarget'
		);

		$targetUrl = StringHelper::trim($targetUrl);
		if (empty($targetUrl))
		{
			return;
		}

		$this->executedByType['canonical'] = true;
		$this->factory->getA(Helper\Meta::class)
					  ->injectCanonical(
						  $targetUrl,
						  '4SEO_canonical_rule_' . $applicableRule->getId()
					  );

		$this->factory->getThe('forseo.requestInfo')->set(
			'page_canonical',
			$targetUrl
		);

		$this->logRuleExecution('meta:canonical', $applicableRule, $currentUrl, $ruleSpec)
			->factory
			->getThe('forseo.logger')
			->debug('Adding canonical %s to request %s, rule: %s', $targetUrl, $currentUrl, print_r($ruleSpec, true));
	}

	/**
	 * Modify OGP/TCards data on the fly.
	 *
	 * @param $applicableRule
	 * @param $currentUrl
	 * @return void
	 * @throws \Exception
	 */
	protected function executeByTypeSharing($applicableRule, $currentUrl)
	{
		$ruleSpec = $applicableRule->getRule();
		if (Wb\arrayIsFalsy($ruleSpec, 'actionMetaOgpForceDefaultImage'))
		{
			return;
		}

		$defaultSharingImage = $this->factory
			->getThis('forseo.config', 'socialnetworks')
			->get('defaultImage');
		if (empty($defaultSharingImage))
		{
			// No default image set, can't do
			return;
		}

		$this->factory->getThe('forseo.requestInfo')->set(
			'page_sharing_image',
			$defaultSharingImage
		);

		$this->executedByType['sharing'] = true;
		$this->logRuleExecution('meta:sharing', $applicableRule, $currentUrl, $ruleSpec)
			->factory
			->getThe('forseo.logger')
			->debug('Updating sharing image with default sharing image, rule: %s', print_r($ruleSpec, true));
	}

	/**
	 * Execute all Waf rules that are left after filtering by conditions.
	 *
	 * @param array $contentData
	 * @return array
	 */
	public function executeWaf($contentData = [])
	{
		try
		{
			if ($this->factory->getThis('forseo.config', 'rules')
							  ->isFalsy('enabled'))
			{
				return $contentData;
			}

			$rules = $this->model->getRulesSpecs(Data\Rule::TYPE_WAF);
			if (empty($rules))
			{
				return $contentData;
			}

			$currentUrl = $this->pageHelper->getCleanedCurrentUrl();
			foreach ($rules as $applicableRule)
			{
				$ruleSpec = $applicableRule->getRule();
				if (
					empty($contentData)
					&&
					Wb\ArrayIsTruthy($ruleSpec, 'customFieldId')
				) {
					// No data item for that page yet, but there's a custom field specification
					// we must wait until onContentPrepare to check if the rule applies
					continue;
				}

				$actionType = Wb\arrayGet($ruleSpec, 'actionWafType');
				switch ($actionType)
				{
					case Data\Rule::WAF_TYPE_404:
					case Data\Rule::WAF_TYPE_403:
						$this->logRuleExecution($actionType, $applicableRule, $currentUrl, $ruleSpec);
						System\Http::abort(
							$actionType
						);
						break;
					case Data\Rule::WAF_TYPE_503:
						$this->logRuleExecution($actionType, $applicableRule, $currentUrl, $ruleSpec)
							->platform
							->enableOfflineMode();
						break;
					default:
						// invalid, try next rule
						continue 2;
				}

				return $contentData;
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('Rules controller executeWaf %s::%d %s %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $contentData;
	}

	/**
	 * Possibly inject some raw content inside HTML response.
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	public function injectRawContent(string $body)
	{
		if ($this->factory->getThis('forseo.config', 'rules')
						  ->isFalsy('enabled'))
		{
			return $body;
		}

		if (!$this->platform->isHtmlPage())
		{
			return $body;
		}

		$originalBody = $body;

		try
		{
			$rules = $this->model->getRulesSpecs(Data\Rule::TYPE_RAW_CONTENT);
			if (empty($rules))
			{
				return $body;
			}

			$rulesHelper = $this->factory->getA(Helper\Rules::class);

			foreach (array_reverse($rules) as $applicableRule)
			{
				$ruleSpec = $applicableRule->getRule();
				foreach (Data\Rule::RAW_CONTENT_LOCATIONS as $location)
				{
					$toInject = StringHelper::trim(
						Wb\arrayGet(
							$ruleSpec,
							'actionRawContent' . $location
						)
					);

					if (!empty($toInject))
					{
						$this->logRuleExecution('rawcontent', $applicableRule, $this->pageHelper->getCleanedCurrentUrl(), $ruleSpec);
						$body = $rulesHelper->injectRawContent(
							$applicableRule->get('id'),
							$body,
							$toInject,
							$location
						);
					}
				}
			}

			return $body;
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $originalBody;
		}
	}

	/**
	 * Run content replacers set for onContentPrepare events, ie Main content and modules.
	 *
	 * [
	 * 'modified' => false,
	 * 'context'  => $context,
	 * 'content'  => $row,
	 * 'params'   => $params,
	 * 'page'     => $page
	 * ]
	 *
	 * 'modified' must be set to true for any change to be taken into consideration.
	 *
	 * @param array $contentData
	 *
	 * @return array
	 */
	public function runContentReplacers($contentData)
	{
		if (!$this->shouldRunReplacers('content'))
		{
			return $contentData;
		}

		$contentObject = Wb\arrayGet($contentData, 'content');
		if (empty($contentObject || empty($contentObject->text)))
		{
			return $contentData;
		}

		$context = Wb\arrayGet($contentData, 'context');

		// If finder indexer, use item context instead
		if ($context == 'com_finder.indexer' && !empty($contentObject->context))
		{
			$context = $contentObject->context;
		}
		elseif ($context == 'com_finder.indexer')
		{
			// Don't run this plugin when the content is being indexed and we have no real context
			return $contentData;
		}

		try
		{
			$rules = $this->model->getRulesSpecs(Data\Rule::TYPE_REPLACER);
			if (empty($rules))
			{
				return $contentData;
			}

			$rulesHelper = $this->factory->getA(Helper\Rules::class);
			foreach ($rules as $applicableRule)
			{
				$ruleSpec               = $applicableRule->getRule();
				$actionReplacerLocation = Wb\arrayGet($ruleSpec, 'actionReplacerLocation');
				$replacementType        = Wb\arrayGet($ruleSpec, 'actionReplacerType', Data\Rule::REPLACE_TYPE_TEXT);
				$protectLinks           = Data\Rule::REPLACE_TYPE_LINK === $replacementType
					// if we replace with a link, always protect links, and their anchors - see t9845
					? true
					: Wb\arrayGet($ruleSpec, 'actionReplacerProtectLinks', true);
				$maxReplacementsInRule  = Wb\arrayGet($ruleSpec, 'actionReplacerMaxReplacements', 99999);
				$maxReplacements        = $maxReplacementsInRule - $this->getAlreadyMadeReplacementsCount($applicableRule->getId());
				if ($maxReplacements < 1)
				{
					continue;
				}

				switch ($actionReplacerLocation)
				{
					case Data\Rule::REPLACE_WHERE_CONTENT:
						if (Wb\startsWith($context, 'mod_'))
						{
							continue 2;
						}
						break;
					case Data\Rule::REPLACE_WHERE_MODULES:
						if (!Wb\startsWith($context, 'mod_custom'))
						{
							continue 2;
						}
						break;
					default:
						continue 2;
				}

				$searchFor = Wb\arrayGet($ruleSpec, 'actionReplacerSource');
				if (empty($searchFor))
				{
					continue;
				}

				$isRegExp        = Wb\startsWith($searchFor, '~');
				$isCaseSensitive = Wb\arrayGet($ruleSpec, 'actionReplacerCaseSensitive', false);
				$wholeWordsOnly  = Wb\arrayGet($ruleSpec, 'actionReplacerWholeWordsOnly', false);
				$protectHnTags   = Wb\arrayGet($ruleSpec, 'actionReplacerProtectHnTags', false);

				$replaceWith = $rulesHelper->buildReplacementTarget(
					$ruleSpec,
					$isRegExp,
					$applicableRule
				);

				$counter   = 0;
				$processed = System\Strings::pregInBuffer(
					$contentObject->text,
					$searchFor,
					$replaceWith,
					$counter,
					[
						'replacementCounter' => $counter,
						'isRegExp'           => $isRegExp,
						'isCaseSensitive'    => $isCaseSensitive,
						'wholeWordsOnly'     => $wholeWordsOnly,
						'protectLinks'       => $protectLinks,
						'protectHtmlTags'    => Data\Rule::REPLACE_TYPE_LINK === $replacementType,
						'protectHnTags'      => $protectHnTags,
						'maxReplacements'    => $maxReplacements
					]
				);

				$this->recordReplacements(
					$applicableRule->getId(),
					$counter
				);


				$contentData['modified'] = true;
				$contentObject->text     = $processed;
				$this->logRuleExecution('replacer', $applicableRule, $this->pageHelper->getCleanedCurrentUrl(), $ruleSpec);
			}
		}
		catch
		(\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

		}

		return $contentData;
	}

	/**
	 * Add a number of replacements made by a rule to the replacement counters value.
	 * Creates the counter if it does not exist.
	 * @param int $ruleId
	 * @param int $replacementsCount
	 * @return $this
	 */
	private function recordReplacements($ruleId, $replacementsCount)
	{
		Wb\arrayKeyInit(
			$this->replacementsCounters,
			$ruleId,
			0
		);

		$this->replacementsCounters[$ruleId] += $replacementsCount;

		return $this;
	}

	/**
	 * Get the number of replacements already made for the specified rule.
	 *
	 * @param int $ruleId
	 * @return int
	 */
	private function getAlreadyMadeReplacementsCount($ruleId)
	{
		return Wb\arrayGet(
			$this->replacementsCounters,
			$ruleId,
			0
		);
	}

	/**
	 * Possibly replace some content globally:
	 *  - head rules
	 *  - body rules
	 *  - anywhere rules
	 *
	 * Other locations (content, modules) must be triggered at onContentPrepare.
	 *
	 * NB: Replace what can be a regexp if starting with a tilde. We should use the exact same specification
	 * as the redirects: ie {*} and {?}, with same expansion rules in the target, but that's not implemented yet.
	 *
	 * Additional settings: replace all, replace first, replace last, replace all but first
	 *
	 * @param string $body
	 *
	 * @return string
	 */
	public function runGlobalReplacers(string $body)
	{
		$originalBody = $body;

		try
		{
			if (!$this->shouldRunReplacers('anywhere'))
			{
				return $body;
			}

			$rules = $this->model->getRulesSpecs(Data\Rule::TYPE_REPLACER);
			if (empty($rules))
			{
				return $body;
			}

			$rulesHelper = $this->factory->getA(Helper\Rules::class);
			foreach ($rules as $applicableRule)
			{
				$ruleSpec               = $applicableRule->getRule();
				$actionReplacerLocation = Wb\arrayGet($ruleSpec, 'actionReplacerLocation');
				if (!in_array($actionReplacerLocation, Data\Rule::REPLACE_WHERE_GLOBAL))
				{
					// content and modules replacements are done on onContentPrepare
					continue;
				}
				$replacementType       = Wb\arrayGet($ruleSpec, 'actionReplacerType', Data\Rule::REPLACE_TYPE_TEXT);
				$protectLinks          =
					Data\Rule::REPLACE_TYPE_LINK === $replacementType
					||
					Data\Rule::REPLACE_WHERE_METADATA === $actionReplacerLocation
						? true
						: Wb\arrayGet($ruleSpec, 'actionReplacerProtectLinks', true);
				$maxReplacementsInRule = Wb\arrayGet($ruleSpec, 'actionReplacerMaxReplacements', 99999);
				$maxReplacements       = $maxReplacementsInRule - $this->getAlreadyMadeReplacementsCount($applicableRule->getId());
				if ($maxReplacements < 1)
				{
					continue;
				}

				$searchFor = Wb\arrayGet($ruleSpec, 'actionReplacerSource');
				if (empty($searchFor))
				{
					continue;
				}

				$isRegExp        = Wb\startsWith($searchFor, '~');
				$isCaseSensitive = Wb\arrayGet($ruleSpec, 'actionReplacerCaseSensitive', false);
				$wholeWordsOnly  = Wb\arrayGet($ruleSpec, 'actionReplacerWholeWordsOnly', false);
				$protectHnTags   = Wb\arrayGet($ruleSpec, 'actionReplacerProtectHnTags', false);

				$replaceWith = $rulesHelper->buildReplacementTarget(
					$ruleSpec,
					$isRegExp,
					$applicableRule
				);

				switch ($actionReplacerLocation)
				{
					case Data\Rule::REPLACE_WHERE_HEAD:
						$applyTo = System\Strings::getTagInBuffer(
							$body,
							'head'
						);
						break;
					case Data\Rule::REPLACE_WHERE_BODY:
						$applyTo = System\Strings::getTagInBuffer(
							$body,
							'body'
						);
						break;
					case Data\Rule::REPLACE_WHERE_ANYWHERE:
						$applyTo = $body;
						break;
				}

				if (empty($applyTo))
				{
					continue;
				}

				$counter   = 0;
				$processed = System\Strings::pregInBuffer(
					$applyTo,
					$searchFor,
					$replaceWith,
					$counter,
					[
						'isRegExp'        => $isRegExp,
						'isCaseSensitive' => $isCaseSensitive,
						'wholeWordsOnly'  => $wholeWordsOnly,
						'protectLinks'    => $protectLinks,
						'protectHnTags'   => $protectHnTags,
						'maxReplacements' => $maxReplacements
					]
				);

				$this->recordReplacements(
					$applicableRule->getId(),
					$counter
				);

				switch ($actionReplacerLocation)
				{
					case Data\Rule::REPLACE_WHERE_ANYWHERE:
						$body = $processed;
						break;
					case Data\Rule::REPLACE_WHERE_HEAD:
					case Data\Rule::REPLACE_WHERE_BODY:
						$body = str_replace(
							$applyTo,
							$processed,
							$body
						);
						break;

				}

				$this->logRuleExecution('replacer', $applicableRule, $this->pageHelper->getCleanedCurrentUrl(), $ruleSpec);
			}

			return $body;
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());

			return $originalBody;
		}
	}

	/**
	 * Run meta replacement rules just before meta data are injected
	 * in the page by the platform.
	 *
	 * @return Rules
	 */
	public function runMetaReplacers()
	{
		try
		{
			if (!$this->shouldRunReplacers('metadata'))
			{
				return;
			}

			// currently only handling meta in content
			if (!$this->platform->isHtmlPage())
			{
				return;
			}

			$rules = $this->model->getRulesSpecs(Data\Rule::TYPE_REPLACER);
			if (empty($rules))
			{
				return;
			}

			$rulesHelper = $this->factory->getA(Helper\Rules::class);
			foreach ($rules as $applicableRule)
			{
				$ruleSpec               = $applicableRule->getRule();
				$actionReplacerLocation = Wb\arrayGet($ruleSpec, 'actionReplacerLocation');
				if (Data\Rule::REPLACE_WHERE_METADATA !== $actionReplacerLocation)
				{
					continue;
				}
				$actionReplacerSubLocation = Wb\arrayGet($ruleSpec, 'actionReplacerSubLocation');
				if (!in_array($actionReplacerSubLocation, Data\Rule::REPLACE_WHERE_SUB_LOCATION_METADATA))
				{
					continue;
				}

				$maxReplacementsInRule = Wb\arrayGet($ruleSpec, 'actionReplacerMaxReplacements', 99999);
				$maxReplacements       = $maxReplacementsInRule - $this->getAlreadyMadeReplacementsCount($applicableRule->getId());
				if ($maxReplacements < 1)
				{
					continue;
				}

				$searchFor = Wb\arrayGet($ruleSpec, 'actionReplacerSource');
				if (empty($searchFor))
				{
					continue;
				}

				$isRegExp        = Wb\startsWith($searchFor, '~');
				$isCaseSensitive = Wb\arrayGet($ruleSpec, 'actionReplacerCaseSensitive', false);
				$wholeWordsOnly  = Wb\arrayGet($ruleSpec, 'actionReplacerWholeWordsOnly', false);

				$replaceWith = $rulesHelper->buildReplacementTarget(
					$ruleSpec,
					$isRegExp,
					$applicableRule
				);

				switch ($actionReplacerSubLocation)
				{
					case Data\Rule::REPLACE_WHERE_PAGE_TITLE:
						// Can have per page title and description just for OGP
						$applyTo = $this->requestInfo->getPageTitle();
						break;
					case Data\Rule::REPLACE_WHERE_PAGE_DESCRIPTION:
						$applyTo = $this->requestInfo->getMetaDescription();
						break;
					case Data\Rule::REPLACE_WHERE_OGP_TITLE:
						$title   = $this->requestInfo->get('page_custom_title_ogp', '');
						$applyTo = empty($title)
							? $this->requestInfo->getPageTitle()
							: $title;
						break;
					case Data\Rule::REPLACE_WHERE_OGP_DESCRIPTION:
						$description = $this->requestInfo->get('page_custom_description_ogp', '');
						$applyTo     = empty($description)
							? $this->requestInfo->getMetaDescription()
							: $description;
						break;
					case Data\Rule::REPLACE_WHERE_TCARDS_TITLE:
						$title   = $this->requestInfo->get('page_custom_title_tcards', '');
						$applyTo = empty($title)
							? $this->requestInfo->getPageTitle()
							: $title;
						break;
					case Data\Rule::REPLACE_WHERE_TCARDS_DESCRIPTION:
						$description = $this->requestInfo->get('page_custom_description_tcards', '');
						$applyTo     = empty($description)
							? $this->requestInfo->getMetaDescription()
							: $description;
						break;
				}
				if (empty($applyTo))
				{
					continue;
				}

				$counter   = 0;
				$processed = System\Strings::pregInBuffer(
					$applyTo,
					$searchFor,
					$replaceWith,
					$counter,
					[
						'isRegExp'        => $isRegExp,
						'isCaseSensitive' => $isCaseSensitive,
						'wholeWordsOnly'  => $wholeWordsOnly,
						'maxReplacements' => $maxReplacements
					]
				);

				$this->recordReplacements(
					$applicableRule->getId(),
					$counter
				);

				switch ($actionReplacerSubLocation)
				{
					case Data\Rule::REPLACE_WHERE_PAGE_TITLE:
						// Can have per page title and description just for OGP
						$this->requestInfo->set('page_title', $processed);
						break;
					case Data\Rule::REPLACE_WHERE_PAGE_DESCRIPTION:
						$this->requestInfo->set('page_description', $processed);
						break;
					case Data\Rule::REPLACE_WHERE_OGP_TITLE:
						$this->requestInfo->set('page_custom_title_ogp', $processed);
						break;
					case Data\Rule::REPLACE_WHERE_OGP_DESCRIPTION:
						$this->requestInfo->set('page_custom_description_ogp', $processed);
						break;
					case Data\Rule::REPLACE_WHERE_TCARDS_TITLE:
						$this->requestInfo->set('page_custom_title_tcards', $processed);
						break;
					case Data\Rule::REPLACE_WHERE_TCARDS_DESCRIPTION:
						$this->requestInfo->set('page_custom_description_tcards', $processed);
						break;
				}

				$this->logRuleExecution('replacer', $applicableRule, $this->pageHelper->getCleanedCurrentUrl(), $ruleSpec);
			}
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Checks various conditions to run the 4SEO replacers
	 *
	 * @param string $location content | component | anywhere | modules
	 *
	 * @return bool
	 */
	private function shouldRunReplacers(string $location)
	{
		if ($this->factory->getThis('forseo.config', 'replacer')
						  ->isFalsy('enabled'))
		{
			return false;
		}

		$shouldRunReplacers = true;
		$input              = $this->platform->getHttpInput();

		// in edit layout (or in admin)
		if ($this->platform->isFrontendEditPage())
		{
			$shouldRunReplacers = false;
		}

		/**
		 * Filter whether the content replacers should run on this page.
		 *
		 *
		 * @api     forseo
		 * @var forseo_filter_run_replacers
		 * @package 4SEO\filter\content
		 * @since   1.0.0
		 *
		 * @param bool   $shouldRunReplacers
		 * @param string $location content | component | anywhere | modules
		 * @param Input  $input
		 *
		 * @return bool
		 *
		 */
		$shouldRunReplacers = $this->factory
			->getThe('hook')
			->filter(
				'forseo_filter_run_replacers',
				$shouldRunReplacers,
				$location,
				$input
			);

		return $shouldRunReplacers;
	}

	/**
	 * Log and timestamp execution of a rule.
	 *
	 * @param string    $actionType
	 * @param Data\Rule $rule
	 * @param string    $currentUrl
	 * @param array     $ruleSpec
	 * @return Rules
	 * @throws \Exception
	 */
	private function logRuleExecution(string $actionType, Data\Rule $rule, string $currentUrl, array $ruleSpec)
	{
		if (Data\Rule::SOURCE_BUILT_IN !== $rule->get('source'))
		{
			$rule->timestamp('last_hit')
				 ->increment('hits')
				 ->store();
			$this->factory
				->getThe('forseo.logger')
				->debug(
					'Triggered %s rule for request %s, rule: %s',
					$actionType,
					$currentUrl,
					$rule->getId()
				);
		}

		return $this;
	}
}
