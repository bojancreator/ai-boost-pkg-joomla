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

use Weeblr\Forseo\Data\Requestinfo;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Tcards extends Base\Base
{
	/**
	 * @var Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo = null;

	/**
	 * @var array Holds all the structured data to be output as json Twitter Cards
	 */
	private $data = [];

	/**
	 * Loads pre-computed info about the request.
	 */
	public function __construct()
	{
		parent::__construct();

		$this->requestInfo = $this->factory->getThe('forseo.requestInfo');
	}

	/**
	 * Builds the final values for the data to inject
	 */
	public function build()
	{
		$config = $this->factory->getThis('forseo.config', 'socialnetworks');
		if ($config->isFalsy('tCardsEnabled'))
		{
			return $this;
		}

		$this->data = [
			'card'    => $config->get('tCardsType', 'summary'),
			'site'    => $config->get('tCardsSiteAccount', ''),
			'creator' => str_replace('"', '\'', $config->get('tCardsCreator', '')),
			'url'     => $this->requestInfo->get('page_url', '')
		];

		// Can have per page title and description just for OGP
		$title               = $this->requestInfo->get('page_custom_title_tcards', '');
		$this->data['title'] = empty($title)
			? str_replace('"', '\'', $this->requestInfo->getPageTitle())
			: $title;

		$description               = $this->requestInfo->get('page_custom_description_tcards', '');
		$this->data['description'] = empty($description)
			? str_replace('"', '\'', $this->requestInfo->getMetaDescription())
			: $description;

		$image = $this->requestInfo->get('page_custom_sharing_image', '');
		if (empty($image))
		{
			$image = $this->requestInfo->get('page_sharing_image', '');
		}
		if (empty($image))
		{
			$image = $config->get('defaultImage', '');
		}
		if (!empty($image))
		{
			$url                 = Wb\arrayGet($image, 'url', '');
			$this->data['image'] = $url;
		}

		// filter out empty values
		$this->data = array_filter(
			$this->data
		);

		/**
		 * Filter the data array used to build the Twitter Cards meta tags.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\features
		 * @var forseo_tcards_data
		 * @since   4.7.1
		 *
		 * @param array       $tcardsData  Computed Twitter Cards Data as a key/value array
		 * @param Requestinfo $requestInfo Instance of the current request details.
		 *
		 * @return array
		 *
		 */
		$this->data = $this->factory
			->getThe('hook')
			->filter(
				'forseo_tcards_data',
				$this->data,
				$this->requestInfo
			);


		return $this;
	}

	/**
	 * Inject structured data as OGP json.
	 */
	public function inject()
	{
		$document   = $this->platform->getDocument();
		$htmlHelper = $this->factory->getA(Html\Helper::class);
		foreach ($this->data as $property => $content)
		{
			$meta = $htmlHelper->makeTag(
				'meta',
				[
					'name'    => 'twitter:' . $property,
					'content' => $content,
					'class'   => '4SEO_tcards_tag',
				],
				'',
				[
					'escapeAttrFlags' => ENT_COMPAT
				]
			);
			$document->addCustomTag(
				$meta
			);
		}

		return $this;
	}
}