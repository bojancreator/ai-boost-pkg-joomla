<?php
/**
 * @package   admintools
 * @copyright Copyright (c)2010-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\AdminTools\Feature;

defined('_JEXEC') || die;

use Akeeba\Plugin\System\AdminTools\Utility\Cache;

class BadWordsFiltering extends Base
{
	/**
	 * Is this feature enabled?
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		if (!$this->app->isClient('site'))
		{
			return false;
		}

		if ($this->skipFiltering)
		{
			return false;
		}

		return ($this->wafParams->getValue('antispam', 0) == 1);
	}

	/**
	 * The simplest anti-spam solution imaginable. Just blocks a request if a prohibited word is found.
	 */
	public function onAfterRoute(): void
	{
		$badwords = Cache::getCache('badwords');

		if (empty($badwords))
		{
			return;
		}

		$hashes = ['get', 'post'];

		foreach ($hashes as $hash)
		{
			$input   = $this->input->$hash;
			$allVars = $input->getArray();

			if (empty($allVars))
			{
				continue;
			}

			foreach ($badwords as $word)
			{
				$regex = '#\b' . $word . '\b#iu';

				if ($this->recursiveRegExMatch($regex, $allVars, true, [$this, 'preconditionValues']))
				{
					$extraInfo = "Hash      : $hash\n";
					$extraInfo .= "Variables :\n";
					$extraInfo .= print_r($allVars, true);
					$extraInfo .= "\n";
					$this->exceptionsHandler->blockRequest('antispam', null, $extraInfo);
				}
			}
		}
	}

	/**
	 * Normalise input variables for this feature.
	 *
	 * This method decodes any URL-encoded values and any HTML entities back to plain UTF-8.
	 *
	 * @param   mixed  $value  The input value, which can be of any type.
	 *
	 * @return  mixed  The processed value.
	 * @since   7.8.2
	 */
	public function preconditionValues($value)
	{
		if (is_array($value))
		{
			return array_map([$this, 'preconditionValues'], $value);
		}

		if (!is_string($value))
		{
			return $value;
		}

		if (is_numeric($value))
		{
			return $value;
		}

		return html_entity_decode(urldecode($value), ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
	}
}
