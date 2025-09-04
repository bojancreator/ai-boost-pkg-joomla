<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Mvc;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base;
use Weeblr\Wblib\Forsef\System;

/** ensure this file is being included by a parent file */
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

include_once __DIR__ . '/wblViewHelper.php';

/**
 * Updates to a standard HTML page, which has an AMP version
 */
abstract class ViewView extends Base\Base
{
	protected $data = null;
	protected $theme = null;
	protected $layout = null;
	protected $baseLayoutPath = null;
	protected $echoOutput = true;
	protected $headers = array();
	protected $outputHeaders = false;
	protected $output = '';

	/**
	 * Constructor
	 *
	 * @param   array  $options  An array of options.
	 */
	public function __construct($options = array())
	{
		parent::__construct();

		// get some default values
		$this->theme          = Wb\arrayGet($options, 'theme', 'default');
		$this->layout         = Wb\arrayGet($options, 'layout', 'default');
		$this->baseLayoutPath = Wb\arrayGet($options, 'base_layout_path', WBLIB_Forsef_LAYOUTS_PATH);
		$this->echoOutput     = Wb\arrayGet($options, 'echo_output', $this->echoOutput);
		$this->outputHeaders  = Wb\arrayGet($options, 'output_headers', $this->outputHeaders);
	}

	/**
	 * Renders the view content, returning it in a string and
	 * optionally echoing it
	 */
	public function render()
	{
		try
		{
			$output = $this->doRender();
			if ($this->echoOutput)
			{
				echo $output;
			}

			return $output;
		}
		catch (\Throwable $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
		catch (\Exception $e)
		{
			System\Log::libraryError('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}
	}

	/**
	 * Actually render the output of the view.
	 *
	 * @return mixed
	 */
	abstract protected function doRender();

	/**
	 * Stores data required for display, sent by dispatcher/controller
	 *
	 * @param   mixed  $data
	 *
	 * @return $this
	 */
	public function setDisplayData($data)
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * Stores data required for display, sent by dispatcher/controller
	 *
	 * @param   mixed  $newData
	 *
	 * @return $this
	 */
	public function mergeDisplayData($newData)
	{
		$this->data = array_merge(
			$this->data,
			$newData
		);

		return $this;
	}

	/**
	 * Store a header value, as a key/value array
	 *
	 * @param   array  $header  key => value list of headers to output
	 *
	 * @return $this
	 */
	public function setHeader($header)
	{
		$this->headers = array_merge($this->headers, $header);

		return $this;
	}

	/**
	 * Output headers stored up until now, unless headers
	 * have already been sent
	 *
	 * @return $this
	 */
	public function outputHeaders()
	{
		if (!$this->outputHeaders)
		{
			return $this;
		}

		if (!headers_sent())
		{
			// run filter to collect headers
			/**
			 * Filter the list of HTTP headers included in a page response
			 *
			 * @api
			 *
			 * @package wbLib\filter\output
			 * @var wblib_response_headers
			 *
			 * @param   array  $headers  Name => Value indexed array of headers ready to be sent
			 *
			 * @return array
			 * @since   1.0.0
			 *
			 */
			$headers = $this->factory->getThe('hook')->filter('wblib_response_headers', $this->headers);

			// output headers
			foreach ($headers as $name => $content)
			{
				if ('status' == strtolower($name))
				{
					status_header($content);
				}
				else
				{
					header($name . ': ' . $content);
				}
			}
		}
		else
		{
			System\Log::libraryError('%s::%d %s', __METHOD__, __LINE__, 'Headers already sent!');
		}

		return $this;
	}
}