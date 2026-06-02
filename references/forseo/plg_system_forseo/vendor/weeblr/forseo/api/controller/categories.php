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

namespace Weeblr\Forseo\Api\Controller;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Categories extends Api\Controller
{
	/**
	 * @var null|array Holds list of installed components.
	 */
	private $categories = null;

	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function get($request, $options)
	{
		$this->loadCategories();
		$categories = $this->filterCategories($options);
		$total      = count($categories);

		return [
			'data'  => $this->formatCategories($categories, $options),
			'count' => $total,
			'total' => $total,
		];
	}

	/**
	 * Filter categories, from cache.
	 *
	 * @param array $categories Filtered list of categories
	 * @param array $options    Filtering options.
	 *
	 * @return Categories
	 */
	private function formatCategories($categories, $options = [])
	{
		$format = Wb\arrayGet(
			$options,
			'format',
			''
		);

		if ('select' === $format)
		{
			$formatted = [];
			foreach ($categories as $category)
			{
				$formattedCat         = new \stdClass();
				$formattedCat->group  = $this->formatExtension($category->extension);
				$formattedCat->option = $category->extension . '.' . $category->path;
				$formattedCat->name   = $this->formatCategoryTitle($category);
				$formatted[]          = $formattedCat;
			}
		}
		else
		{
			$formatted = $categories;
		}

		return $formatted;
	}

	/**
	 * Filter categories, from cache.
	 *
	 * @param array $options Filtering options.
	 *
	 * @return array|null
	 */
	private function filterCategories($options = [])
	{
		$extension = Wb\arrayGet(
			$options,
			'extension',
			''
		);
		$language  = StringHelper::strtolower(Wb\arrayGet(
				$options,
				'language',
				''
			)
		);

		$search = StringHelper::strtolower(
			Wb\arrayGet(
				$options,
				'search',
				''
			)
		);

		if (
			empty($extension)
			&&
			empty($language)
			&&
			empty($search)
		) {
			return $this->categories;
		}


		return array_filter(
			$this->categories,
			function ($category) use ($extension, $language, $search)
			{
				if (!empty($extension) && $extension != $category->extension)
				{
					return false;
				}
				if (
					!empty($language)
					&&
					$language != StringHelper::strtolower($category->language)
				) {
					return false;
				}

				if (
					!empty($search)
					&&
					StringHelper::strpos(
						StringHelper::strtolower(
							$category->title
						),
						$search
					) === false
				) {
					return false;
				}

				return true;
			}
		);
	}

	/**
	 * Reads categories from db, cache them.
	 *
	 * @return Categories
	 */
	private function loadCategories()
	{
		if (is_null($this->categories))
		{
			$this->categories = $this->platform->getCategories();
		}

		/**
		 * Filter the list of categories on the site, for user display in the admin.
		 *
		 * @api     forseo
		 * @package 4SEO\filter\admin
		 * @var forseo_categories_list
		 * @since   1.0.0
		 *
		 * @param array $categories List of objects each describing a category.
		 *
		 * @return array
		 *
		 */
		$this->categories = $this->factory
			->getThe('hook')
			->filter(
				'forseo_categories_list',
				$this->categories
			);

		return $this;
	}

	private function formatCategoryTitle($category)
	{
		$repeat = $category->level >= 1 ? $category->level - 1 : 0;
		$title  = str_repeat('- ', $repeat) . $category->title;

		$title .= ' - ' . $this->formatExtension($category->extension);

		if ($category->language !== '*')
		{
			$title .= ' - ' . $category->language;
		}

		return $title;
	}

	private function formatExtension($extension)
	{
		return StringHelper::ucfirst(
			Wb\lTrim(
				$extension,
				'com_'
			)
		);
	}
}
