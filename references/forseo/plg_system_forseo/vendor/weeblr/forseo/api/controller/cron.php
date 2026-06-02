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
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Api;

// no direct access
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

class Cron extends Api\Controller
{
	private $validCronTypes = ['image', 'http'];

	/**
	 * A special handler for the cron route. That route can be triggered in 2 ways:
	 *
	 * - by making an HTTP request to  /?_wblapi=/forseo/v1/cron/http
	 *
	 * - by including an image in a page:
	 *
	 * <img style="position:absolute;bottom:0;left:0;" src="/?_wblapi=/forseo/v1/cron/image/random_id.svg"/>
	 *
	 * The image can be hardcoded in the page or inserted with javascript after a small delay for instance.
	 * See src/main/server/layouts/default/forseo/cron/pixel.php
	 *
	 * It does not simply return some response but instead:
	 *
	 * - returns an empty svg with a display:none style attribute
	 * - returns that content and flush the response so that the browser is not kept waiting
	 * - triggers a hook to execute other code after response has been sent.
	 *
	 * @param Api\Request $request
	 * @param array       $options
	 *
	 * @return \Exception
	 */
	public function run($request, $options)
	{
		$type = Wb\arrayGet($options, 'type');
		$id   = Wb\arrayGet($options, 'id');
		if (!in_array($type, $this->validCronTypes))
		{
			return new \Exception('Not found.', System\Http::RETURN_NOT_FOUND);
		}

		if ('http' === $type)
		{
			$key      = Wb\arrayGet($options, 'k');
			$validKey = $this->factory
				->getThis('forseo.config', 'system')
				->get('cronKey');
			if (empty($key) || $key !== $validKey)
			{
				return new \Exception('Not found.', System\Http::RETURN_NOT_FOUND);
			}
		}

		// respond with an image
		$this->respond($type, $id)
			 ->doCron($request, $type);

		// avoid sending extraneous content, this may cause on some servers
		// the request to show a failing message in browser console:
		// error on line 1 at column 66100: Extra content at the end of the document
		while (ob_get_level())
		{
			ob_end_clean();
		}
		die();
	}

	/**
	 * Respond to a cron request based on its type:
	 *
	 * - image: returns an empty svg and flush the response
	 * - http: do nothing and let cron executor respond instead.
	 *
	 * @param string $type image | http
	 * @param string $id   Cache buster string, not used
	 *
	 * @return $this
	 */
	private function respond($type, $id)
	{
		if ('image' == $type)
		{
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="display:none!important;"></svg>';

			System\Http::render(
				System\Http::RETURN_OK,
				$svg,
				'image/svg+xml',
				[
					'X-Robots-Tag' => 'noindex'
				],
				false
			);
		}

		if ('http' == $type)
		{
			System\Http::render(
				System\Http::RETURN_NO_CONTENT,
				'',
				'text/plain',
				[],
				false
			);
		}

		return $this;
	}

	/**
	 * Triggers a hook to allow execution of additional code after response has been sent.
	 *
	 * @param Api\Request $request
	 * @param string      $type image | http
	 *
	 * @return array
	 */
	private function doCron($request, $type)
	{
		/**
		 * Execute actions ran over cron. Each job can update the request response to reflect
		 * success or otherwise.
		 *
		 *
		 * @api     forseo
		 * @package 4SEO\filter\cron
		 * @var forseo_cron
		 * @since   1.0.0
		 *
		 * @param array $data        API response data array: [[]data, int count, int total [,int status][,callable runAfterResponse]]
		 * @param array $options     Some options:
		 *                           type => image | cron
		 *
		 * @return Request
		 *
		 */
		return $this->factory
			->getThe('hook')
			->filter(
				'forseo_cron',
				[
					'data'  => [],
					'count' => 0,
					'total' => 0,
				],
				$request,
				$type
			);
	}
}
