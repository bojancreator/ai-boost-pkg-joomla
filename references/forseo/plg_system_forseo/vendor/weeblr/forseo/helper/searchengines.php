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

namespace Weeblr\Forseo\Helper;

use Weeblr\Forseo\Model;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\Seo;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Searchengines extends Base\Base
{
	/**
	 * @var Model\Config Convenience instance of the application config config model.
	 */
	private $sitemapsConfig = null;

	/**
	 * @var
	 */
	private $seoHelper;

	/**
	 * Stores factory instance.
	 *
	 * @param array $options Can inject custom factory and platform.
	 */
	public function __construct($options = [])
	{
		parent::__construct($options);
		$this->sitemapsConfig = $this->factory->getThis('forseo.config', 'app');
		$this->seoHelper      = $this->factory->getA(
			Seo\Helper::class,
			[
				'validateIp' => $this->sitemapsConfig->get('validateSearchEnginesIp', true)
			]
		);
	}

	/**
	 * Whether current request is by a known search engine.
	 *
	 * @return bool
	 */
	public function isSearchEngineRequest()
	{
		return $this->getRequestingSearchEngine() != Seo\Searchengine::NONE;
	}

	/**
	 * Wrapper for the wbLib function to get the requesting search engines, if any.
	 *
	 * @return mixed
	 */
	public function getRequestingSearchEngine()
	{
		return $this->seoHelper
			->getRequestingSearchEngine();
	}

	/**
	 * Fetch from our server and caches for a few days the list of search engines updates
	 * we know of.
	 *
	 * @return array
	 */
	public function getEnginesUpdates()
	{
		return $this->seoHelper
			->getEnginesUpdates();
	}
}