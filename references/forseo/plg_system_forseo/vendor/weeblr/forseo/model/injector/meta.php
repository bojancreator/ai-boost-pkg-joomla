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

use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Html;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Meta extends Base\Base
{
	/**
	 * @var Requestinfo Convenience instance of the current request details.
	 */
	private $requestInfo = null;

	public function __construct()
	{
		parent::__construct();

		$this->requestInfo = $this->factory
			->getThe('forseo.requestInfo');
	}

	/**
	 * Inject the page title.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function title()
	{
		$title = $this->requestInfo->getPageTitle();
		if (!empty($title))
		{
			$this->platform->setTitle($title);
		}

		return $this;
	}

	/**
	 * Inject the page description, or the auto-generated one if none present.
	 *
	 * @return $this
	 * @throws \Exception
	 */
	public function description()
	{
		$suppressDescriptionFlag = $this->requestInfo
			->get('page_suppress_meta_description', false);
		$customDescription       = $this->requestInfo
			->get('page_custom_description');

		if (
			$suppressDescriptionFlag
			&&
			empty($customDescription)
		) {
			$this->platform->setDescription('');
		}
		else
		{
			$desc = $this->requestInfo->getMetaDescription();
			if (!empty($desc))
			{
				$this->platform->setDescription(
					$desc
				);
			}
		}

		return $this;
	}

	/**
	 * Inject the 4SEO generator meta.
	 *
	 * @return $this
	 */
	public function generator()
	{
		/**
		 * Filter whether the 4SEO generator meta should be disabled.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\frontend\features
		 * @var forseo_disable_generator_tag
		 * @since   4.8.0
		 *
		 * @param bool $disableGeneratorTag
		 *
		 * @return bool
		 *
		 */
		$disableGeneratorTag = $this->factory
			->getThe('hook')
			->filter(
				'forseo_disable_generator_tag',
				false
			);

		if (!$disableGeneratorTag)
		{
			return $this;
		}

		$meta = $this->factory
			->getA(Html\Helper::class)
			->makeTag(
				'meta',
				[
					'name'    => 'generator',
					'content' => 'SEO optimization by 4SEO',
					'class'   => '4SEO_generator_tag'
				],
				''
			);

		$this->platform->addCustomTag(
			$meta
		);

		return $this;
	}

}