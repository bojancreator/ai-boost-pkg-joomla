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

use Weeblr\Forseo\Helper;
use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\Base;
use Weeblr\Wblib\Forseo\System;


// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Customfields extends Base\Base
{
	/**
	 * Finds a list of custom fields definition set up on the site. Possibly restrict
	 * the list to a context, either as an exact match or a partial one.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	public function getFieldsDef($options)
	{
		try
		{
			$context    = Wb\arrayGet($options, 'context', '');
			$exactMatch = (bool)Wb\arrayGetInt($options, 'exact_match');

			$extractContext = !empty($context)
							  &&
							  $exactMatch
				? $context  // if exact match, only read CF for that context
				: '';  // else read them all

			$fields = $this->platform->getCustomFieldsDefs($extractContext);

			if (
				!empty($fields)
				&&
				!empty($context)
				&&
				!$exactMatch)
			{
				// if not exact match, we read ALL CF, regardless of context
				// and we now filter out those which don't match the (partial) filter passed
				$fields = array_filter(
					$fields,
					function ($field) use ($context)
					{
						return Wb\startsWith(
							$field->context,
							$context
						);
					}
				);
			}

			// now gather suitable custom field details
			$formattedFields = [];
			foreach ($fields as $field)
			{
				$formattedFields[] = [
					'id'          => $field->id,
					'context'     => $field->context,
					'name'        => $field->name,
					'title'       => $field->title . ' - ' . $this->formatContext($field->context),
					'state'       => $field->state,
					'language'    => $field->language,
					'type'        => $field->type,
					'group_id'    => $field->group_id,
					'group_title' => $this->buildGroupTitle($field),
					'group_state' => $field->group_state,
				];
			}

			usort(
				$formattedFields,
				function ($a, $b)
				{
					if ($a['context'] === $b['context'])
					{
						return $a['title'] <=> $b['title'];
					}
					return $a['context'] <=> $b['context'];
				}
			);

			return $formattedFields;
		}
		catch (\Exception $e)
		{
			// error while fetching, wait before retry for standard caching period
			return null;
		}
	}

	/**
	 * Build a displayable title for a custom field, starting with the field
	 * context, possibly appending the field group name, if any.
	 *
	 * @param Object $field
	 * @return string
	 */
	private function buildGroupTitle($field)
	{
		$groupTitle = $this->formatContext($field->context);

		if (!empty($field->group_title))
		{
			$groupTitle .= ' | ' . $field->group_title;
		}

		if ($field->language !== '*')
		{
			$groupTitle .= ' (' . $field->language . ')';
		}

		return $groupTitle;
	}

	/**
	 * Builds a formatted title based on a custom field context string.
	 *
	 * @param string $context
	 * @return string
	 */
	private function formatContext($context, $short = false)
	{
		$context = Wb\lTrim($context, 'com_');

		$title = System\Strings::stringToCleanedArray(
			$context,
			'.',
			System\Strings::UCFIRST
		);

		$title = array_unique($title);

		if (empty($title))
		{
			return ucfirst($context);
		}

		return $short
			? $title[0]
			: implode(' - ', array_unique($title));
	}
}
