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

class Sd extends Base\Base
{
	/**
	 * Primary Types
	 */
	public const ARTICLE      = 'Article';
	public const NEWS_ARTICLE = 'NewsArticle';
	public const BLOG_POSTING = 'BlogPosting';
	public const COURSE       = 'Course';
	public const EVENT        = 'Event';
	public const PRODUCT      = 'Product';
	public const RECIPE       = 'Recipe';
	public const VIDEO_OBJECT = 'VideoObject';

	// 1.3
	public const FAQ_PAGE = 'FaqPage';
	public const QUESTION = 'Question';
	public const ANSWER   = 'Answer';

	// 6.0
	public const PROFILE_PAGE = 'ProfilePage';
	public const MOVIE        = 'Movie';

	// Page-level types
	public const PAGE_LEVEL_TYPES = [
		self::ARTICLE,
		self::NEWS_ARTICLE,
		self::BLOG_POSTING,
		self::COURSE,
		self::EVENT,
		self::PRODUCT,
		self::RECIPE,
		self::FAQ_PAGE,
		self::MOVIE,
		self::PROFILE_PAGE
	];

	public const REQUIRED_FIELDS_PER_TYPE = [
		self::ARTICLE      => [
			'author',
			'datePublished',
			'headline',
			'image',
			'publisher'
		],
		self::NEWS_ARTICLE => [
			'author',
			'datePublished',
			'headline',
			'image',
			'publisher'
		],
		self::BLOG_POSTING => [
			'author',
			'datePublished',
			'headline',
			'image',
			'publisher'
		],
		self::COURSE       => [
			'description',
			'name',
			'provider',
			'offers',
			'hasCourseInstance'
		],
		self::FAQ_PAGE     => [
			'mainEntity'
		],
		self::MOVIE        => [
			'name',
			'image'
		],
		self::EVENT        => [
			'location',
			'name',
			'startDate'
		],
		self::PROFILE_PAGE => [
			'mainEntity',
		],
		// Product required field list is dynamic.
		self::PRODUCT      => [
			Sd::class,
			'hasRequiredFieldsProduct'
		],
		self::RECIPE       => [
			Sd::class,
			'hasRequiredFieldsRecipe'
		],
		self::VIDEO_OBJECT => [
			Sd::class,
			'hasRequiredFieldsVideoObject'
		]
	];

	// Local business is global, only on home page
	// or maybe in the future on other specific pages
	// such as contact.
	public const LOCAL_BUSINESS = 'LocalBusiness';

	// Future: Job posting
	public const JOB_POSTING = 'JobPosting';

	// Future: Fact Check
	public const CLAIM        = 'Claim';
	public const CLAIM_REVIEW = 'ClaimReview';

	// Future: How_To
	public const HOW_TO           = 'HowTo';
	public const HOW_TO_SECTION   = 'HowToSection';
	public const HOW_TO_STEP      = 'HowToStep';
	public const HOW_TO_SUPPLY    = 'HowToSupply';
	public const HOW_TO_TOOL      = 'HowToTool';
	public const HOW_TO_DIRECTION = 'HowToDirection';
	public const HOW_TO_TIP       = 'HowToTip';

	/**
	 * Secondary types
	 */
	public const DATE_TIME        = 'DateTime';
	public const URL              = 'URL';
	public const IMAGE_OBJECT     = 'ImageObject';
	public const PERSON           = 'Person';
	public const ORGANIZATION     = 'Organization';
	public const CONTACT_POINT    = 'ContactPoint';
	public const PLACE            = 'Place';
	public const VIRTUAL_LOCATION = 'VirtualLocation';
	public const OFFER            = 'Offer';
	public const RATING           = 'Rating';
	public const AGGREGATE_RATING = 'AggregateRating';
	public const BREADCRUMB       = 'BreadcrumbList';

	// Loc
	public const POSTAL_ADRESS   = 'PostalAddress';
	public const GEO_COORDINATES = 'GeoCoordinates';

	// Hours & Time
	public const OPENING_HOURS_SPECIFICATION = 'OpeningHoursSpecification';
	public const DURATION                    = 'Duration';

	// Products && Offers
	public const QUANTITATIVE_VALUE = 'QuantitativeValue';
	public const REVIEW             = 'Review';

	public const OFFERS_DISCONTINUED         = 'http://schema.org/Discontinued';
	public const OFFERS_IN_STOCK             = 'http://schema.org/InStock';
	public const OFFERS_IN_STORE_ONLY        = 'http://schema.org/InStoreOnly';
	public const OFFERS_LIMITED_AVAILABILITY = 'http://schema.org/LimitedAvailability';
	public const OFFERS_ONLINE_ONLY          = 'http://schema.org/OnlineOnly';
	public const OFFERS_OUT_OF_STOCK         = 'http://schema.org/OutOfStock';
	public const OFFERS_PRE_ORDER            = 'http://schema.org/PreOrder';
	public const OFFERS_PRE_SALE             = 'http://schema.org/PreSale';
	public const OFFERS_SOLD_OUT             = 'http://schema.org/SoldOut';

	public const OFFER_ITEM_CONDITION       = 'http://schema.org/OfferItemCondition';
	public const ITEM_NEW_CONDITION         = 'http://schema.org/NewCondition';
	public const ITEM_USED_CONDITION        = 'http://schema.org/UsedCondition';
	public const ITEM_DAMAGED_CONDITION     = 'http://schema.org/DamagedCondition';
	public const ITEM_REFURBISHED_CONDITION = 'http://schema.org/RefurbishedCondition';

	// Course
	public const COURSE_OFFER_CATEGORY_FREE  = 'Free';
	public const COURSE_OFFER_PARTIALLY_FREE = 'Partially Free';
	public const COURSE_OFFER_CATEGORY_PAID  = 'Paid';
	public const COURSE_OFFER_SUBSCRIPTION   = 'Subscription';
	public const COURSE_MODE_ONLINE          = 'Online';
	public const COURSE_MODE_ONSITE          = 'Onsite';
	public const COURSE_MODE_BLENDED         = 'Blended';
	public const COURSE_FREQUENCY_DAILY      = 'Daily';
	public const COURSE_FREQUENCY_WEEKLY     = 'Weekly';
	public const COURSE_FREQUENCY_MONTHLY    = 'Monthly';
	public const COURSE_FREQUENCY_YEARLY     = 'Yearly';
	public const COURSE_INSTANCE             = 'http://schema.org/CourseInstance';
	public const SCHEDULE                    = 'http://schema.org/Schedule';


	// Events
	public const PERFORMING_GROUP                  = 'http://schema.org/PerformingGroup';
	public const DANCE_GROUP                       = 'http://schema.org/DanceGroup';
	public const MUSIC_GROUP                       = 'http://schema.org/MusicGroup';
	public const THEATER_GROUP                     = 'http://schema.org/TheaterGroup';
	public const EVENT_STATUS_TYPE                 = 'EventStatusType';
	public const EVENT_STATUS_CANCELLED            = 'http://schema.org/EventCancelled';
	public const EVENT_MOVED_ONLINE                = 'http://schema.org/EventMovedOnline';
	public const EVENT_STATUS_POSTPONED            = 'http://schema.org/EventPostponed';
	public const EVENT_STATUS_RESCHEDULED          = 'http://schema.org/EventRescheduled';
	public const EVENT_STATUS_SCHEDULED            = 'http://schema.org/EventScheduled';
	public const EVENT_ATTENDANCE_MODE_ENUMERATION = 'EventAttendanceModeEnumeration';
	public const ONLINE_EVENT_ATTENDANCE_MODE      = 'http://schema.org/OnlineEventAttendanceMode';
	public const OFFLINE_EVENT_ATTENDANCE_MODE     = 'http://schema.org/OfflineEventAttendanceMode';
	public const MIXED_EVENT_ATTENDANCE_MODE       = 'http://schema.org/MixedEventAttendanceMode';

	// Recipe
	public const NUTRITION_INFORMATION = 'http://schema.org/NutritionInformation';

	/**
	 * Generic types
	 */
	public const TEXT = 'Text';

	/**
	 * Fields type: auto is computed by us, custom has been filled in by user
	 */
	public const FIELD_AUTO   = true;
	public const FIELD_CUSTOM = false;

	/**
	 * Types of opening hours specifications
	 */
	public const HOURS_TYPE_NONE      = 0;
	public const HOURS_TYPE_WEEKDAYS  = 1;
	public const HOURS_TYPE_ALWAYS    = 2;
	public const HOURS_TYPE_CUSTOM    = 3;
	public const HOURS_HOURS_TYPE_24  = 0;
	public const HOURS_HOURS_TYPE_9_5 = 1;
	public const HOURS_HOURS_TYPE_8_6 = 2;
	/**
	 * Opening hours Specification constants
	 */
	public const HOURS_ALL_DAY_OPENS  = '00:00';
	public const HOURS_ALL_DAY_CLOSES = '23:59';
	public const HOURS_9_TO_5_OPENS   = '09:00';
	public const HOURS_9_TO_5_CLOSES  = '17:00';
	public const HOURS_8_TO_6_OPENS   = '08:00';
	public const HOURS_8_TO_6_CLOSES  = '18:00';

	public const DAYS_OF_WEEK_SHORT = [
		'mon',
		'tue',
		'wed',
		'thu',
		'fri',
		'sat',
		'sun'
	];

	public const DAYS_OF_WEEK = [
		'monday',
		'tuesday',
		'wednesday',
		'thursday',
		'friday',
		'saturday',
		'sunday'
	];
	public const WEEKDAYS     = [
		'monday',
		'tuesday',
		'wednesday',
		'thursday',
		'friday',
	];

	/**
	 * FAQ operation mode: no detection, detect based on CSS selectors, detect 4SEO shortcodes
	 */
	public const DETECT_NONE  = 0;
	public const DETECT_CSS   = 1;
	public const DETECT_CODES = 2;

	/**
	 * Various specifications and requirements.
	 */
	public const ARTICLE_MIN_IMAGE_WIDTH  = 696;
	public const ARTICLE_MIN_IMAGE_PIXELS = 300000;

	/**
	 * Data types that can accept reviews.
	 * https://developers.google.com/search/docs/data-types/review-snippet
	 */
	public const REVIEWABLE_TYPES = [
		self::COURSE,
		self::EVENT,
		self::HOW_TO,
		self::LOCAL_BUSINESS,
		self::MOVIE,
		self::PRODUCT,
		self::RECIPE,
		self::ORGANIZATION
	];

	public const PRODUCT_GLOBAL_IDENTIFIER_TYPES = [
		'gtin',
		'gtin8',
		//		'gtin12',
		'gtin13',
		'gtin14',
		'mnp',
		//		'nsn',
		'isbn'
	];

	// Ref: http://schema.org/docs/full.html#LocalBusiness
	public const LOCAL_BUSINESS_TYPES = [
		'AnimalShelter'               => 'AnimalShelter',
		'AutomotiveBusiness'          => [
			'AutoBodyShop',
			'AutoDealer',
			'AutoRental',
			'AutoRepair',
			'AutoWash',
			'GasStation',
			'MotorcycleDealer',
			'MotorcycleRepair'
		],
		'ChildCare'                   => 'ChildCare',
		'DryCleaningOrLaundry'        => 'DryCleaningOrLaundry',
		'EmergencyService'            => [
			'FireStation',
			'Hospital',
			'PoliceStation'
		],
		'EmploymentAgency'            => 'EmploymentAgency',
		'EntertainmentBusiness'       => [
			'AdultEntertainment',
			'AmusementPark',
			'ArtGallery',
			'Casino',
			'ComedyClub',
			'MovieTheater',
			'NightClub'
		],
		'FinancialService'            => [
			'AccountingService',
			'AutomatedTeller',
			'BankOrCreditUnion',
			'InsuranceAgency'
		],
		'FoodEstablishment'           => [
			'Bakery',
			'BarOrPub',
			'Brewery',
			'CafeOrCoffeeShop',
			'Distillery',
			'FastFoodRestaurant',
			'IceCreamShop',
			'Restaurant',
			'Winery'
		],
		'GovernmentOffice'            => [
			'PostOffice'
		],
		'HealthAndBeautyBusiness'     => [
			'BeautySalon',
			'DaySpa',
			'HairSalon',
			'HealthClub',
			'NailSalon',
			'TattooParlor'
		],
		'HomeAndConstructionBusiness' => [
			'Electrician',
			'GeneralContractor',
			'HVACBusiness',
			'HousePainter',
			'Locksmith',
			'MovingCompany',
			'Plumber',
			'RoofingContractor'
		],
		'InternetCafe'                => 'InternetCafe',
		'LegalService'                => [
			'Attorney',
			'Notary'
		],
		'Library'                     => 'Library',
		'LocalBusiness'               => 'LocalBusiness',
		'LodgingBusiness'             => [
			'BedAndBreakfast',
			'Campground',
			'Hostel',
			'Hotel',
			'Motel'
		],
		'MedicalBusiness'             => [
			'Dentist',
			'Optician',
			'Pharmacy',
			'Physician'
		],
		'ProfessionalService'         => 'ProfessionalService',
		'RadioStation'                => 'RadioStation',
		'RealEstateAgent'             => 'RealEstateAgent',
		'RecyclingCenter'             => 'RecyclingCenter',
		'SelfStorage'                 => 'SelfStorage',
		'ShoppingCenter'              => 'ShoppingCenter',
		'SportsActivityLocation'      => [
			'BowlingAlley',
			'ExerciseGym',
			'GolfCourse',
			'PublicSwimmingPool',
			'SkiResort',
			'SportsClub',
			'StadiumOrArena',
			'TennisComplex'
		],
		'Store'                       => [
			'AutoPartsStore',
			'BikeStore',
			'BookStore',
			'ClothingStore',
			'ComputerStore',
			'ConvenienceStore',
			'DepartmentStore',
			'ElectronicsStore',
			'Florist',
			'FurnitureStore',
			'GardenStore',
			'GroceryStore',
			'HardwareStore',
			'HobbyShop',
			'HomeGoodsStore',
			'JewelryStore',
			'LiquorStore',
			'MensClothingStore',
			'MobilePhoneStore',
			'MovieRentalStore',
			'MusicStore',
			'OfficeEquipmentStore',
			'OutletStore',
			'PawnShop',
			'PetStore',
			'ShoeStore',
			'SportingGoodsStore',
			'TireShop',
			'ToyStore',
			'WholesaleStore'
		],
		'TelevisionStation'           => 'TelevisionStation',
		'TouristInformationCenter'    => 'TouristInformationCenter',
		'TravelAgency'                => 'TravelAgency'
	];


	// NB: When using this, remember to include LocalBusiness (defined above)
	// Not included to avoid repetition.
	public const ORGANIZATION_TYPES = [
		'Airline',
		'Consortium',
		'Corporation',
		'EducationalOrganization' => [
			'CollegeOrUniversity',
			'ElementarySchool',
			'HighSchool',
			'MiddleSchool',
			'Preschool',
			'School'
		],
		'FundingScheme',
		'GovernmentOrganization',
		'LibrarySystem',
		'MedicalOrganization'     => [
			'Dentist',
			'DiagnosticLab',
			'Hospital',
			'MedicalClinic',
			'Pharmacy',
			'Physician',
			'VeterinaryCare',
		],
		'NGO',
		'NewsMediaOrganization',
		'PerformingGroup'         => [
			'PerformingGroup',
			'DanceGroup',
			'MusicGroup',
			'TheaterGroup'
		],
		'Project'                 => [
			'FundingAgency',
			'ResearchProject'
		],
		'SportsOrganization'      => [
			'SportsTeam'
		],
		'WorkersUnion'
	];

	public const SD_FIELDS_DEF = [
		'fields' => [
			'url'                => [
				'type' => self::URL
			],
			'headline'           => [
				'type' => self::TEXT
			],
			'description'        => [
				'type' => self::TEXT
			],
			'author'             => [
				'type' => self::TEXT
			],
			'publisher'          => [
				'type' => self::TEXT
			],
			'image'              => [
				'type' => self::IMAGE_OBJECT
			],
			'datePublished'      => [
				'type' => self::DATE_TIME
			],
			'dateModified'       => [
				'type' => self::DATE_TIME
			],
			'aggregateRating'    => [
				'type' => self::AGGREGATE_RATING
			],
			'review'             => [
				'type' => self::REVIEW
			],
			// VideoObject
			'name'               => [
				'type' => self::TEXT
			],
			'thumbnailUrl'       => [
				'type' => self::TEXT
			],
			'contentUrl'         => [
				'type' => self::TEXT
			],
			'uploadDate'         => [
				'type' => self::DATE_TIME
			],
			// LocalBusiness
			'telephone'          => [
				'type' => self::TEXT
			],
			// Course
			'provider'           => [
				'type' => self::PERSON
			],
			// FaqPage
			'question'           => [
				'type' => self::QUESTION
			],
			'answer'             => [
				'type' => self::ANSWER
			],
			// Product
			'offerItemCondition' => [
				'type' => self::OFFER_ITEM_CONDITION
			],
			// Event
			'startDate'          => [
				'type' => self::DATE_TIME
			],
			'endDate'            => [
				'type' => self::DATE_TIME
			],
			'offerValidFrom'     => [
				'type' => self::DATE_TIME
			]
		]
	];

	/**
	 * @var array Holds SD definition.
	 */
	public static $sdFieldsSpec;

	/**
	 * Checks the SD built record for a VideoObject against
	 * required fields requirements.
	 * Most types have a static list of required fields,
	 * VideoObject require a dynamic definition.
	 *
	 * @param array $sdData
	 * @return bool
	 */
	public static function hasRequiredFieldsVideoObject($sdData)
	{
		return !empty($sdData['description'])
			   &&
			   !empty($sdData['name'])
			   &&
			   !empty($sdData['thumbnailUrl'])
			   &&
			   !empty($sdData['uploadDate'])
			   &&
			   (
				   !empty($sdData['contentUrl'])
				   ||
				   !empty($sdData['embedUrl'])
				   ||
				   !empty($sdData['offers'])
			   );
	}

	/**
	 * Checks the SD built record for a Product against
	 * required fields requirements.
	 * Most types have a static list of required fields,
	 * Products require a dynamic definition.
	 *
	 * @param array $sdData
	 * @return bool
	 */
	public static function hasRequiredFieldsProduct($sdData)
	{
		return !empty($sdData['name'])
			   &&
			   (
				   !empty($sdData['review'])
				   ||
				   !empty($sdData['aggregateRating'])
				   ||
				   !empty($sdData['offers'])
			   );
	}

	/**
	 * Checks the SD built record for a Recipe against
	 * required fields requirements.
	 * Most types have a static list of required fields,
	 * Recipe require a dynamic definition.
	 *
	 * @param array $sdData
	 * @return bool
	 */
	public static function hasRequiredFieldsRecipe($sdData)
	{
		if (
			empty($sdData['name'])
			||
			empty($sdData['image'])
		) {
			return false;
		}

		return true;
	}
}
