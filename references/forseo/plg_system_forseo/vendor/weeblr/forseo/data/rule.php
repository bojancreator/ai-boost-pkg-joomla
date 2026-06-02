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

namespace Weeblr\Forseo\Data;

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Db;
use Weeblr\Wblib\Forseo\System;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Class Rule
 * @package Weeblr\Forseo\Data
 *
 * Rule are stored in the rule column, as json.
 * Rule definition format is:
 *
 * [
 *          'type' => RULE::TYPE_*
 *          'activation' => [
 *              'urlSpec' => '/blog/{*}' string URL spec with either wildcards strings or a full regexp starting with ~
 *              'urlNegSpec' => '/blog/{*}' string URL spec for pages that should be discarded with either wildcards strings or a full regexp starting with ~
 *              'includedLanguages' => [] // list of languages the rule is for. Empty means all all languages
 *              'includedExtensions' => ['content', 'contact'] // list of components the rule is for
 *              'viewSpec' => ''  // spec of views that should be used
 *              'viewNegSpec' => ''  // spec of views that should NOT be used
 *              'includedCategories' => [  // list of categories per component the rule is for
 *                  'content' => [],
 *                  'contact' => []
 *              ]
 *              'excludedCategories' => [  // list of categories per component the rule is NOT for
 *                  'content' => [],
 *                  'contact' => []
 *              ],
 *              'enableAfter' => '2020-10-09 11:14:32',
 *              'enableUntil' => '2020-10-09 11:14:32',
 *          ],
 *          // replacer
 *          'action' => [  // defines action(s) to perform, varies based on type
 *              'location' => 'any' | 'content' | 'modules' | 'head' | 'body',
 *              'type' => 'text' | 'link',
 *              'source' => '' // string specification with wildchards strings or full regexp starting with ~
 *              'target' => '' // string specification with wildchards strings or full regexp starting with ~
 *          ]
 *
 *          // redirect
 *          'action' => [  // defines action(s) to perform, varies based on type
 *              'type' => 301 | 302 | 303 | internal,
 *              'target' => '' // string specification with wildchards strings or full regexp starting with ~
 *          ]
 *          // canonical
 *          'action' => [  // defines action(s) to perform, varies based on type
 *              'target' => '' // string specification with wildchards strings or full regexp starting with ~
 *          ]
 *          // WAF
 *          'action' => [  // defines action(s) to perform, varies based on type
 *              'type' => 403 | 404,
 *          ]
 *          // Raw content
 *          'action' => [  // defines action(s) to perform, varies based on type
 *              headTop => ''  // raw text to be inserted
 *              headBottom => ''
 *              bodyTop => ''
 *              bodyBottom => ''
 *          ]
 *          // Analytics
 *          'action' => [  // defines action(s) to perform, varies based on type
 *              provider => ''  // analytics provider id universalga|globalga|gtm|matomo|fbpixel|clarity|bing|(fathom: removed)
 *              options =>{}
 *          ]
 * ]
 *
 */
class Rule extends Db\Dataobject
{
	public const TYPE_NONE     = 0;
	public const TYPE_REPLACER = 5;
	public const TYPE_REDIRECT = 10;
	// META is a generalization of canonical.
	// For easier B/C, we keep the same numerical value
	public const TYPE_CANONICAL        = 12;
	public const TYPE_META             = 12;
	public const TYPE_INTERNAL_REWRITE = 14;
	public const TYPE_WAF              = 20;
	public const TYPE_RAW_CONTENT      = 30;
	public const TYPE_ANALYTICS        = 40;
	public const TYPE_SD               = 50;

	public const TYPE_ROBOTS  = 100; // future
	public const TYPE_SOCIAL  = 110; // future
	public const TYPE_SITEMAP = 120;

	public const TYPE_ERROR_PAGE = 140;

	public const SOURCE_USER            = 0;
	public const SOURCE_BUILT_IN        = 1;
	public const SOURCE_IMPORT_SH404SEF = 100;

	public const DISABLED                = 0;
	public const ENABLED                 = 1;
	public const ENABLED_WITH_CONDITIONS = 2;

	public const WAF_TYPE_404 = 404;
	public const WAF_TYPE_403 = 403;
	public const WAF_TYPE_503 = 503;


	/**
	 * Possible values for how to update the canonical URL
	 */
	public const CANONICAL_TYPE_DO_NOT_CHANGE = 'donotchange';
	public const CANONICAL_TYPE_VALUE         = 'value';
	public const CANONICAL_TYPE_CF            = 'cf';

	/**
	 * List of possible locations for injecting raw content.
	 */
	public const RAW_CONTENT_LOCATION_HEAD_TOP    = 'HeadTop';
	public const RAW_CONTENT_LOCATION_HEAD_BOTTOM = 'HeadBottom';
	public const RAW_CONTENT_LOCATION_BODY_TOP    = 'BodyTop';
	public const RAW_CONTENT_LOCATION_BODY_BOTTOM = 'BodyBottom';
	public const RAW_CONTENT_LOCATIONS            = [
		self::RAW_CONTENT_LOCATION_HEAD_TOP,
		self::RAW_CONTENT_LOCATION_HEAD_BOTTOM,
		self::RAW_CONTENT_LOCATION_BODY_TOP,
		self::RAW_CONTENT_LOCATION_BODY_BOTTOM,
	];

	/**
	 * Possible redirects types
	 */
	public const REDIRECT_TYPE_301      = 301;
	public const REDIRECT_TYPE_302      = 302;
	public const REDIRECT_TYPE_303      = 303;
	public const REDIRECT_TYPE_TO_SEF   = 'sef';
	public const REDIRECT_TYPE_INTERNAL = 'internal';


	/**
	 * List of possible locations for running replacers
	 */
	public const REPLACE_TYPE_TEXT      = 'text';
	public const REPLACE_TYPE_LINK      = 'link';
	public const REPLACE_WHERE_ANYWHERE = 'any';
	public const REPLACE_WHERE_CONTENT  = 'content';
	public const REPLACE_WHERE_MODULES  = 'modules';
	public const REPLACE_WHERE_HEAD     = 'head';
	public const REPLACE_WHERE_BODY     = 'body';
	public const REPLACE_WHERE_GLOBAL   = [
		self::REPLACE_WHERE_ANYWHERE,
		self::REPLACE_WHERE_HEAD,
		self::REPLACE_WHERE_BODY
	];

	public const REPLACE_WHERE_METADATA              = 'metadata';
	public const REPLACE_WHERE_PAGE_TITLE            = 'page_title';
	public const REPLACE_WHERE_PAGE_DESCRIPTION      = 'page_description';
	public const REPLACE_WHERE_OGP_TITLE             = 'ogp_title';
	public const REPLACE_WHERE_OGP_DESCRIPTION       = 'ogp_description';
	public const REPLACE_WHERE_TCARDS_TITLE          = 'tcards_title';
	public const REPLACE_WHERE_TCARDS_DESCRIPTION    = 'tcards_description';
	public const REPLACE_WHERE_SUB_LOCATION_METADATA = [
		self::REPLACE_WHERE_PAGE_TITLE,
		self::REPLACE_WHERE_PAGE_DESCRIPTION,
		self::REPLACE_WHERE_OGP_TITLE,
		self::REPLACE_WHERE_OGP_DESCRIPTION,
		self::REPLACE_WHERE_TCARDS_TITLE,
		self::REPLACE_WHERE_TCARDS_DESCRIPTION,
	];

	// All types of robots meta/header
	public const ROBOTS_INDEX        = 0;
	public const ROBOTS_NOINDEX      = 1;
	public const ROBOTS_FOLLOW       = 2;
	public const ROBOTS_NOFOLLOW     = 4;
	public const ROBOTS_NOIMAGEINDEX = 8;
	public const ROBOTS_NOARCHIVE    = 16;
	public const ROBOTS_NOCACHE      = 32;
	public const ROBOTS_NOSNIPPET    = 64;

	// Sitemaps exclusions
	public const SITEMAP_EXCLUDED = true;
	public const SITEMAP_INCLUDED = false;

	/**
	 * @var string Database table associated with this instance.
	 */
	protected $table = '#__forseo_rules';

	/**
	 * @var array Holds data read/written to db. Also a specification of the columns.
	 */
	protected $defaults = [
		'id'            => 0,
		'type'          => Rule::TYPE_NONE,
		'source'        => Rule::SOURCE_USER,
		'title'         => '', // auto generate if empty, "Redirect rule #12"
		'rule'          => '{}',
		'last_hit'      => null,
		'hits'          => 0,
		'enabled'       => self::ENABLED,
		'enabled_after' => null,
		'enabled_until' => null,
		'ordering'      => 0,
		'valid'         => 1,
	];

	/**
	 * @var array List of types that should be enforced if present for properties.
	 */
	protected $dataTypes = [
		'id'       => System\Convert::INT,
		'type'     => System\Convert::INT,
		'hits'     => System\Convert::INT,
		'enabled'  => System\Convert::INT,
		'ordering' => System\Convert::INT,
		'valid'    => System\Convert::INT
	];

	/**
	 * @var array Default values for the rule field in this object data structure. Merged with incoming data when setting the rule value through set() method.
	 */
	protected $ruleDefaults = [
		'id'                                   => 0,
		'ordering'                             => 0,
		'orderAfter'                           => -1,
		'orderTarget'                          => -1,
		'orderDirection'                       => 'after',
		'notes'                                => '',
		'type'                                 => 0,
		'title'                                => '',
		// when
		'enabled'                              => self::ENABLED_WITH_CONDITIONS,
		'urlSpec'                              => '/{*}',
		'urlNegSpec'                           => '',
		'disregardQuery'                       => true,
		'disregardCase'                        => true,
		'includedLanguages'                    => [],
		'includedExtensions'                   => [],
		'viewSpec'                             => '',
		'viewNegSpec'                          => '',
		'includedCategories'                   => [],
		'excludedCategories'                   => [],
		'includedUsersGroups'                  => [],
		'excludedUsersGroups'                  => [],
		'includedIps'                          => '',
		'excludedIps'                          => '',
		'includedHomeAddresses'                => '',
		'excludedHomeAddresses'                => '',
		'enableAfter'                          => null,
		'enableUntil'                          => null,
		'customFieldId'                        => [],
		'cfOperator'                           => '=',
		'cfValue'                              => '',
		'cfValue2'                             => '',
		// what' =>  all
		'actionReappendQuery'                  => false,
		// what' =>  redirect
		'actionRedirectType'                   => System\Http::RETURN_MOVED,
		'actionRedirectTarget'                 => '/',
		// what' =>  replacer
		'actionReplacerLocation'               => self::REPLACE_WHERE_CONTENT, //'any' | 'content' | 'modules' | 'head' | 'body'
		'actionReplacerSubLocation'            => self::REPLACE_WHERE_PAGE_TITLE,
		'actionReplacerProtectLinks'           => true,
		'actionReplacerCaseSensitive'          => false,
		'actionReplacerWholeWordsOnly'         => false,
		'actionReplacerProtectHnTags'          => true,
		'actionReplacerType'                   => self::REPLACE_TYPE_TEXT, // text | link
		'actionReplacerTargetBlank'            => false,
		'actionReplacerNoFollow'               => false,
		'actionReplacerSource'                 => '',
		'actionReplacerTarget'                 => '',
		'actionReplacerMaxReplacements'        => 99999,
		// what' =>  canonical
		'actionCanonicalTarget'                => '/{*}',
		'actionCanonicalTargetSource'          => self::CANONICAL_TYPE_DO_NOT_CHANGE,
		'actionCanonicalTargetCfId'            => [],
		// what' =>  waf
		'actionWafType'                        => System\Http::RETURN_NOT_FOUND,
		// what' =>  rawContent,
		'actionRawContentHeadTop'              => '',
		'actionRawContentHeadBottom'           => '',
		'actionRawContentBodyTop'              => '',
		'actionRawContentBodyBottom'           => '',
		// what => analytics
		'actionAnalyticsProvider'              => '',
		// Universal GA
		'actionAnalyticsUniversalgaId'         => '',
		'actionAnalyticsUniversalgaCustom'     => '',
		'actionAnalyticsGaAnonymize'           => true,
		'actionAnalyticsGaDisplayFeatures'     => false,
		'actionAnalyticsGaAdFeatures'          => true, // can only disable from code
		'actionAnalyticsGaLinkAttribution'     => false,
		'actionAnalyticsGaCustomDomain'        => 'auto',
		'actionAnalyticsGaCustomUrl'           => '',
		'actionAnalyticsGaCookieDomain'        => '',
		'actionAnalyticsGaOptions'             => [],
		// Global site
		'actionAnalyticsGlobalgaId'            => '',
		'actionAnalyticsGlobalgaCustom'        => '',
		// Google Tag manager
		'actionAnalyticsGtmId'                 => '',
		'actionAnalyticsGtmDatalayer'          => '',
		// Facebook Pixel
		'actionAnalyticsFbpixelId'             => '',
		'actionAnalyticsFbpixelCustom'         => '',
		// Matomo
		'actionAnalyticsMatomoId'              => '',
		'actionAnalyticsMatomoEndpoint'        => 'https://matomo.org/',
		'actionAnalyticsMatomoTrackWithoutJs'  => true,
		'actionAnalyticsMatomoCustom'          => '',
		// Clarity
		'actionAnalyticsClarityId'             => '',
		// Cloudflare web
		'actionAnalyticsCloudflareId'          => '',
		// Fathom
		'actionAnalyticsFathomId'              => '',
		'actionAnalyticsFathomCustom'          => '',
		// what => sd
		'actionSdType'                         => '',
		'actionSdUrlAuto'                      => true,
		'actionSdUrl'                          => '',
		'actionSdUrlCfId'                      => [],
		'actionSdHeadlineAuto'                 => true,
		'actionSdHeadline'                     => '',
		'actionSdHeadlineCfId'                 => [],
		'actionSdDescriptionAuto'              => true,
		'actionSdDescription'                  => '',
		'actionSdDescriptionCfId'              => [],
		'actionSdInLanguageAuto'               => true,
		'actionSdInLanguage'                   => '',
		'actionSdAuthorAuto'                   => true,
		'actionSdAuthor'                       => '',
		'actionSdAuthorCfId'                   => [],
		'actionSdAuthorUrl'                    => '',
		'actionSdAuthorUrlCfId'                => [],
		'actionSdImageAuto'                    => true,
		'actionSdImageUrl'                     => '',
		'actionSdImageUrlCfId'                 => [],
		'actionSdImageAlt'                     => '',
		'actionSdImageWidth'                   => 0,
		'actionSdImageHeight'                  => 0,
		'actionSdImagePixels'                  => 0,
		'actionSdPublisherAuto'                => true,
		'actionSdPublisher'                    => '',
		'actionSdPublisherCfId'                => [],
		'actionSdPublisherLogoAuto'            => true,
		'actionSdPublisherLogoUrl'             => '',
		'actionSdPublisherLogoAlt'             => '',
		'actionSdPublisherLogoWidth'           => '',
		'actionSdPublisherLogoHeight'          => '',
		'actionSdPublisherLogoPixels'          => '',
		'actionSdDatePublishedAuto'            => true,
		'actionSdDatePublished'                => '',
		'actionSdDatePublishedCfId'            => [],
		'actionSdTimePublished'                => '',
		'actionSdDateModifiedAuto'             => true,
		'actionSdDateModified'                 => '',
		'actionSdDateModifiedCfId'             => [],
		'actionSdTimeModified'                 => '',
		'actionSdAggregateRatingAuto'          => '',
		'actionSdRatingValue'                  => 0,
		'actionSdRatingCount'                  => 0,
		'actionSdWorstRating'                  => 0,
		'actionSdBestRating'                   => 0,
		'actionSdReviewAuto'                   => [],
		'actionSdCustom'                       => '',
		'actionSdCustomCfId'                   => '',
		// VideoObject
		'actionSdNameAuto'                     => true,
		'actionSdName'                         => '',
		'actionSdThumbnailUrlAuto'             => true,
		'actionSdThumbnailUrl'                 => '',
		'actionSdDateUploadedAuto'             => true,
		'actionSdDateUploaded'                 => '',
		'actionSdTimeUploaded'                 => '',
		'actionSdContentUrlAuto'               => true,
		'actionSdContentUrl'                   => '',
		// Course
		'actionSdCourseOfferCategoryCfId'      => [],
		'actionSdCourseOfferCategory'          => Sd::COURSE_OFFER_CATEGORY_FREE,
		'actionSdProviderAuto'                 => true,
		'actionSdProvider'                     => '',
		'actionSdCourseModeCfId'               => 0,
		'actionSdCourseMode'                   => Sd::COURSE_MODE_ONLINE,
		'actionSdWorkloadCfId'                 => 0,
		'actionSdWorkload'                     => '',
		'actionSdRepeatCountCfId'              => 0,
		'actionSdRepeatCount'                  => 0,
		'actionSdRepeatFrequencyCfId'          => 0,
		'actionSdRepeatFrequency'              => Sd::COURSE_FREQUENCY_DAILY,
		'actionSdDurationCfId'                 => 0,
		'actionSdDuration'                     => '',
		'actionSdInstructorNameCfId'           => 0,
		'actionSdInstructorName'               => '',
		'actionSdInstructorDescriptionCfId'    => 0,
		'actionSdInstructorDescription'        => '',
		// ProfilePage
		'actionSdProfileEntityType'            => Sd::PERSON,
		'actionSdProfileAltName'               => '',
		'actionSdProfileAltNameCfId'           => [],
		'actionSdProfileImage2Url'             => '',
		'actionSdProfileImage2UrlCfId'         => [],
		'actionSdProfileImage2Alt'             => '',
		'actionSdProfileImage2Width'           => 0,
		'actionSdProfileImage2Height'          => 0,
		'actionSdProfileImage2Pixels'          => 0,
		'actionSdProfileImage3Url'             => '',
		'actionSdProfileImage3UrlCfId'         => [],
		'actionSdProfileImage3Alt'             => '',
		'actionSdProfileImage3Width'           => 0,
		'actionSdProfileImage3Height'          => 0,
		'actionSdProfileImage3Pixels'          => 0,
		'actionSdSocialProfiles'               => '',
		'actionSdSocialProfilesCfId'           => 0,
		// Event
		'actionSdLocationNameAuto'             => true,
		'actionSdLocationName'                 => '',
		'actionSdLocationAuto'                 => true,
		'actionSdLocation'                     => '',
		'actionSdLocationCfId'                 => 0,
		'actionSdLocationAddress'              => '',
		'actionSdLocationAddressCfId'          => 0,
		'actionSdDateStartedAuto'              => true,
		'actionSdDateStarted'                  => '',
		'actionSdDateStartedCfId'              => 0,
		'actionSdTimeStarted'                  => '',
		'actionSdDateEndedAuto'                => true,
		'actionSdDateEnded'                    => '',
		'actionSdDateEndedCfId'                => 0,
		'actionSdTimeEnded'                    => '',
		'actionSdOffersAuto'                   => true,
		'actionSdPerformerAuto'                => true,
		'actionSdPerformer'                    => '',
		'actionSdPerformerType'                => Sd::PERSON,
		'actionSdOrganizerAuto'                => true,
		'actionSdOrganizer'                    => '',
		'actionSdEventAttendanceModeAuto'      => true,
		'actionSdEventAttendanceMode'          => Sd::ONLINE_EVENT_ATTENDANCE_MODE,
		'actionSdEventStatusAuto'              => true,
		'actionSdEventStatus'                  => [Sd::EVENT_STATUS_SCHEDULED],
		'actionSdOfferPriceAuto'               => true,
		'actionSdOfferPrice'                   => 0.0,
		'actionSdOfferPriceCurrencyAuto'       => true,
		'actionSdOfferPriceCurrency'           => 'USD',
		'actionSdOfferDateValidFromAuto'       => true,
		'actionSdOfferDateValidFrom'           => '',
		'actionSdOfferTimeValidFrom'           => '',
		'actionSdOfferAvailabilityAuto'        => true,
		'actionSdOfferAvailability'            => [Sd::OFFERS_IN_STOCK],
		'actionSdOfferUrlAuto'                 => true,
		'actionSdOfferUrl'                     => '',
		// Movie
		'actionSdNameCfId'                     => [],
		'actionSdMovieDirector'                => '',
		'actionSdMovieDirectorCfId'            => [],

		// Product
		'actionSdBrandAuto'                    => true,
		'actionSdBrand'                        => null,
		'actionSdSkuAuto'                      => true,
		'actionSdSku'                          => '',
		'actionSdProductGlobalIdTypeAuto'      => true,
		'actionSdProductGlobalIdType'          => '',
		'actionSdProductGlobalId'              => '',
		'actionSdOfferDatePriceValidUntilAuto' => true,
		'actionSdOfferDatePriceValidUntil'     => '',
		'actionSdOfferTimePriceValidUntil'     => '',
		'actionSdOfferItemConditionAuto'       => true,
		'actionSdOfferItemCondition'           => [Sd::ITEM_NEW_CONDITION],
		'actionSdOfferItemConditionCustom'     => '',
		// Recipe
		'actionSdRecipeCategoryAuto'           => true,
		'actionSdRecipeCategory'               => '',
		'actionSdRecipeCuisineAuto'            => true,
		'actionSdRecipeCuisine'                => '',
		'actionSdKeywordsAuto'                 => true,
		'actionSdKeywords'                     => '',
		'actionSdRecipeIngredientAuto'         => true,
		'actionSdRecipeIngredient'             => '',
		'actionSdRecipeInstructionsAuto'       => true,
		'actionSdRecipeInstructions'           => '',
		'actionSdRecipeYieldAuto'              => true,
		'actionSdRecipeYield'                  => '',
		'actionSdPrepTimeAuto'                 => true,
		'actionSdPrepTime'                     => 0,
		'actionSdCookTimeAuto'                 => true,
		'actionSdCookTime'                     => 0,
		'actionSdTotalTimeAuto'                => true,
		'actionSdTotalTime'                    => 0,
		'actionSdCaloriesAuto'                 => true,
		'actionSdCalories'                     => 0,
		// FaqPage
		'actionSdFaqMode'                      => Sd::DETECT_CODES,
		'actionSdFaqQCss'                      => [],
		'actionSdFaqACss'                      => [],
		'actionSdFaqMainEntity'                => [],
		// What => sitemap
		'actionSmExclude'                      => self::SITEMAP_INCLUDED,
		'actionSmExcludeAge'                   => 0, // number of days
		'actionSmExcludeArchived'              => self::SITEMAP_EXCLUDED,
		// What => error page
		'actionErrorTitle'                     => '',
		'actionErrorContent'                   => '',
		'actionErrorSuggest'                   => true,
		'actionErrorSuggestTitle'              => '',
		'actionErrorNoSuggestText'             => '',
		'actionErrorShowDetails'               => true,
		'actionErrorRandomImage'               => true,
		'actionErrorCode'                      => 404, // 401 | 403 | 404 | 500 | 0
		'actionErrorMenu'                      => [],
		// What => meta
		'actionMetaTitleSpec'                  => '{page_title}',
		'actionMetaDescSpec'                   => '{page_description}',
		'actionMetaDescIfEmpty'                => false,
		'actionMetaDescSuppress'               => false,
		'actionMetaRobots'                     => '',
		'actionMetaRobotsCustom'               => '',
		'actionMetaOgpForceDefaultImage'       => false
	];

	/**
	 * @var array List of max length per column.
	 */
	protected $autotrimSpec = [
		'notes' => 1500,
		'title' => 190,
	];

	/**
	 * @var string[] List of columns and their id which can be searched.
	 */
	protected $searchableColumns = [
		'title' => 'title',
		'id'    => 'id',
	];

	/**
	 * @var string[] List of columns and their id which can be ordered by.
	 */
	protected $orderableColumns = [
		'title',
		'hits'
	];

	/**
	 * @var array List of data key that should be ignored when storing to the DB.
	 */
	protected $dbIgnore = [
		'orderAfter',
		'orderTarget',
		'orderDirection',
		'actionReplacerProtectLinks'
	];

	/**
	 * Shortcut to get the actual rule specification.
	 *
	 * @return array
	 */
	public function getRule()
	{
		$decodedRule = json_decode(
			$this->data['rule'],
			true
		);
		$decodedRule = empty($decodedRule)
			? []
			: $decodedRule;

		$rule = array_merge(
			$this->ruleDefaults,
			$decodedRule
		);

		$this->updateCanonicalUseCfFlag($rule);

		return $rule;
	}

	/**
	 * Convert actionCanonicalTargetUseCf prop, pre-4.5.0.
	 *
	 * @param array $rule
	 * @return void
	 */
	private function updateCanonicalUseCfFlag(&$rule)
	{
		if (
			!empty($rule['id'])
			&&
			Wb\arrayIsset($rule, 'actionCanonicalTargetUseCf')
		) {
			$rule['actionCanonicalTargetSource'] = !empty($rule['actionCanonicalTargetUseCf'])
				? self::CANONICAL_TYPE_CF
				: self::CANONICAL_TYPE_VALUE;
		}

		$rule['actionCanonicalTargetUseCf'] = 'processed';
	}

	/**
	 * Update the copy stored in the "rule" field when a key/pair stored
	 * at top level is modified.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	protected function updateRuleField($key, $value)
	{
		$rule               = json_decode(
			$this->data['rule'],
			true
		);
		$rule               = empty($rule)
			? []
			: $rule;
		$rule[$key]         = $value;
		$this->data['rule'] = json_encode($rule);
	}

	/**
	 * Optionally encode a value before it's stored in the data object.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function encodeValue($key, $value)
	{
		switch ($key)
		{
			case 'enabled':
			case 'title':
			case 'type':
				$this->updateRuleField(
					$key,
					$value
				);
				break;
			case 'rule':
				// merge with default data so that new fields are added after updates/ changes in data structure
				$value = array_merge(
					$this->ruleDefaults,
					$value
				);

				unset($value['id']);
				unset($value['ordering']);
				unset($value['orderAfter']);
				unset($value['orderTarget']);
				unset($value['orderDirection']);
				// update top level columns from "rule" column data
				$this->data['enabled_after'] = Wb\arrayGet($value, 'enableAfter');
				$this->data['enabled_until'] = Wb\arrayGet($value, 'enableUntil');

				// apply autotrim
				foreach ($this->autotrimSpec as $property => $maxLength)
				{
					$value[$property] = $this->autotrim(
						$property,
						$value[$property]
					);
				}

				// Convert actionCanonicalTargetUseCf prop, pre-4.5.0
				$this->updateCanonicalUseCfFlag($value);

				// encode to json for storage
				$value = json_encode(
					array_merge(
						$this->ruleDefaults,
						$value
					)
				);
				break;
			case 'enabled_after':
			case 'enabled_until':
				$value          = empty($value)
					? null
					: $value;
				$updatedKeyName = 'enabled_after' == $key
					? 'enableAfter'
					: 'enableUntil';
				$this->updateRuleField(
					$updatedKeyName,
					$value
				);
				break;
		}

		return parent::encodeValue($key, $value);
	}

	/**
	 * Optionally decode a value before it's returned from the data object.
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	public function decodeValue($key, $value)
	{
		switch ($key)
		{
			case 'rule':
				$decodedValue = json_decode($value, true);
				$decodedValue = empty($decodedValue)
					? []
					: $decodedValue;
				$value        = array_merge(
					$this->ruleDefaults,
					$decodedValue
				);

				$this->updateCanonicalUseCfFlag($value);

				// ensure user groups are int: Joomla 3 returns int-as-strings
				$value['includedUsersGroups'] = array_map(
					'intval',
					Wb\arrayEnsure($value['includedUsersGroups'])
				);
				$value['excludedUsersGroups'] = array_map(
					'intval',
					Wb\arrayEnsure($value['excludedUsersGroups'])
				);

				break;
		}

		return parent::decodeValue($key, $value);
	}

	/**
	 * Get the array of default values for a rule.
	 *
	 * @return array
	 */
	public function ruleDefaults()
	{
		return $this->ruleDefaults;
	}
}
