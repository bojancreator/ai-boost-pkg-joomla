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

use Weeblr\Forseo\Model;

use Weeblr\Wblib\Forseo\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Customfields extends Api\Controller
{
	/**
	 * Builds up an array of data for use in API response.
	 *
	 * @param array $options
	 *
	 * @return array|\Throwable
	 */
	public function get($request, $options)
	{
		try
		{
			$fields = $this->factory
				->getA(Model\Customfields::class)
				->getFieldsDef($options);

			return [
				'data'  => $fields,
				'count' => count($fields),
				'total' => count($fields),
			];
		}
		catch (\Throwable $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s', $e->getFile(), $e->getLine(), $e->getMessage());

			return new \Exception('Full error message has been stored to the 4SEO log file on the server', 500);
		}
	}
}
