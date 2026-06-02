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

use Weeblr\Forseo\Data;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

$requestInfo = $this->factory->getThe('forseo.requestInfo');

// NB: Social profiles have been removed, see https://support.google.com/knowledgepanel/answer/7534842

return [
	'config'        => [
		'enabled'                        => true,
		'enabledSiteLinks'               => true,
		'enabledBreadcrumb'              => true,
		'enabledPerPage'                 => true,
		'enabledLocalBusiness'           => true,
		'enabledBuiltInRules'            => true,
		'enabledCleanup'                 => true,
		'imageSpec'                      => [
			'width'  => 696,
			'height' => 0,
			'pixels' => 0
		],
		'imageDetectionMethod'           => Html\Image::IMAGE_SEARCH_LARGEST,

		// Organization definition
		'organizationType'               => [Data\Sd::LOCAL_BUSINESS],
		'organizationName'               => $requestInfo->get('site_name'),
		'organizationUrl'                => $requestInfo->get('site_url'),
		'organizationTel'                => '',
		'organizationPriceRange'         => '',
		'organizationStreetAddress'      => '',
		'organizationAddressLocality'    => '',
		'organizationAddressRegion'      => '',
		'organizationPostalCode'         => '',
		'organizationAddressCountry'     => [],
		'organizationGeoLatitude'        => '',
		'organizationGeoLongitude'       => '',
		'organizationHoursType'          => Data\Sd::HOURS_TYPE_NONE,
		'organizationHoursHoursType'     => Data\Sd::HOURS_HOURS_TYPE_24,
		'hoursMon1Opens'                 => '09:00',
		'hoursMon1Closes'                => '17:00',
		'hoursMon2Opens'                 => '',
		'hoursMon2Closes'                => '',
		'hoursTue1Opens'                 => '09:00',
		'hoursTue1Closes'                => '17:00',
		'hoursTue2Opens'                 => '',
		'hoursTue2Closes'                => '',
		'hoursWed1Opens'                 => '09:00',
		'hoursWed1Closes'                => '17:00',
		'hoursWed2Opens'                 => '',
		'hoursWed2Closes'                => '',
		'hoursThu1Opens'                 => '09:00',
		'hoursThu1Closes'                => '17:00',
		'hoursThu2Opens'                 => '',
		'hoursThu2Closes'                => '',
		'hoursFri1Opens'                 => '09:00',
		'hoursFri1Closes'                => '17:00',
		'hoursFri2Opens'                 => '',
		'hoursFri2Closes'                => '',
		'hoursSat1Opens'                 => '',
		'hoursSat1Closes'                => '',
		'hoursSat2Opens'                 => '',
		'hoursSat2Closes'                => '',
		'hoursSun1Opens'                 => '',
		'hoursSun1Closes'                => '',
		'hoursSun2Opens'                 => '',
		'hoursSun2Closes'                => '',
		'organizationLogo'               => [],
		'logoSpec'                       => [
			'width'  => 112,
			'height' => 112,
			'pixels' => 0
		],
		'eventImageSpec'                 => [
			'width'  => 720,
			'height' => 0,
			'pixels' => 0
		],
		'profileImageSpec'               => [
			'width'  => 200,
			'height' => 200,
			'pixels' => 50000
		],
		// Person definition
		'personName'                     => '',
		'personUrl'                      => $requestInfo->get('site_url'),

		// FAQ spec
		'faqPageItemAllowedTags'         => '<h1><h2><h3><h4><h5><h6><br><ol><ul><li><a><p><div><b><strong><i><em>',
		'faqPageItemAllowedTagsQuestion' => '<b><strong><i><em>',

		// Maps
		'googleMapsApiKey'               => '',

		// user custom
		'organizationCustomCode'         => '',
		'personCustomCode'               => '',

		// Joomla
		'removeJoomlaBreadcrumb'         => true
	],
	'doNotStore'    => [
		'imageSpec',
		'logoSpec',
		'profileImageSpec',
		'imageDetectionMethod',
		'faqPageItemAllowedTags',
		'removeJoomlaBreadcrumb'
	],
	'enforcedTypes' => [
		'organizationName'            => System\Convert::STRING,
		'organizationUrl'             => System\Convert::STRING,
		'organizationTel'             => System\Convert::STRING,
		'organizationPriceRange'      => System\Convert::STRING,
		'organizationStreetAddress'   => System\Convert::STRING,
		'organizationAddressLocality' => System\Convert::STRING,
		'organizationAddressRegion'   => System\Convert::STRING,
		'organizationPostalCode'      => System\Convert::STRING,
		'personName'                  => System\Convert::STRING,
		'personUrl'                   => System\Convert::STRING,
		'googleMapsApiKey'            => System\Convert::STRING,
		'profileImageSpec'            => System\Convert::ARRAY,
	]
];