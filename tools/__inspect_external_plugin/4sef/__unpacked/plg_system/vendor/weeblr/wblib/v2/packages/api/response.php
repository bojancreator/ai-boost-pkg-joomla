<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 2.6.2.644
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Api;

use Weeblr\Wblib\Forsef\Wb;
use Weeblr\Wblib\Forsef\Base,
	Weeblr\Wblib\Forsef\System;

defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * HTTP response as enveloped json.
 *
 **/
class Response extends Base\Base
{
	/**
	 * @var string The version of the handling route.
	 */
	private $version;

	/**
	 * @var string Holds the request id.
	 */
	private $requestId;

	/**
	 * @var string Holds the response HTTP status code.
	 */
	private $status;

	/**
	 * @var array Data to be sent in the "data" field of the response.
	 */
	private $data;

	/**
	 * @var array Meta data to be sent in the response.
	 */
	private $meta;

	/**
	 * @var array Stores error descriptors.
	 */
	private $errors;

	/**
	 * @var array Stores links.
	 */
	private $links;

	/**
	 * @var array Associative array of headers to be sent with the response.
	 */
	private $headers = array();

	/**
	 * @var int A unix timestamp of last modification of the resource.
	 */
	private $lastModified;

	/**
	 * @var array The raw assembled response, before being output.
	 */
	private $response = array();

	/**
	 * @var string The response body, as enveloped json.
	 */
	private $responseBody = '';

	/**
	 * \ShlApi_Response constructor.
	 *
	 * @param   string Id of the request we are responding to.
	 * @param   string Handler api version;
	 */
	public function __construct($requestId, $version)
	{
		$this->requestId = $requestId;
		$this->version   = $version;
	}

	/**
	 * Setter for the response HTTP status code.
	 *
	 * @param   int  $status  The status code to use.
	 *
	 * @return Response
	 */
	public function withStatus($status)
	{
		$this->status = $status;

		return $this;
	}

	/**
	 * @param   array  $data  Data to use in response
	 *
	 * @return Response
	 */
	public function withData($data)
	{
		$this->data = $data;

		return $this;
	}

	/**
	 * Set arbitrary meta data about the response.
	 *
	 * @param   array  $meta  Associative array of arbitrary meta data.
	 *
	 * @return $this
	 */
	public function withMeta($meta)
	{
		$this->meta = Wb\arrayMerge(
			$this->meta,
			$meta
		);

		return $this;
	}

	/**
	 * Set links related to the response.
	 *
	 * @param   array  $links  Array of links.
	 *
	 * @return $this
	 */
	public function withLinks($links)
	{
		$this->links = Wb\arrayMerge(
			$this->links,
			$links
		);

		return $this;
	}

	/**
	 * Set the list of headers to output.
	 *
	 * @param   array  $headers  Associate array of headers name => headers value
	 *
	 * @return $this
	 */
	public function withHeaders($headers)
	{
		$this->headers = Wb\arrayMerge(
			$this->headers,
			$headers
		);

		return $this;
	}

	/**
	 * Set the date/time at which the resource was last modified
	 *
	 * @param   int  $lastModified  A unix timestamp.
	 *
	 * @return Response
	 */
	public function withLastModified($lastModified)
	{
		$this->lastModified = $lastModified;

		return $this;
	}

	/**
	 * Set the list of errors to output.
	 *
	 * @param   array  $errors  Array of errors descriptors.
	 *                          array(
	 *                          'code' => 123,
	 *                          'message' => 'Some error message'
	 *                          )
	 *
	 * @return $this
	 */
	public function withErrors($errors)
	{
		$this->errors = Wb\arrayMerge(
			$this->errors,
			$errors
		);

		return $this;
	}

	/**
	 * Output the response and exit.
	 *
	 * @param   bool  $endRequest  Whether to end the request after rendering the page
	 */
	public function render($endRequest = true)
	{
		$this->build();

		// and prepare output
		$this->responseBody = $this->envelope(
			$this->response
		);

		$this->responseBody = $this->encode(
			$this->responseBody
		);

		System\Http::render(
			$this->status,
			$this->responseBody,
			'application/json',
			$this->headers,
			$endRequest
		);
	}

	/**
	 * Return response data only, without exiting.
	 */
	public function renderData()
	{
		$this->build();

		return array(
			'status'  => $this->status,
			'data'    => $this->response,
			'headers' => $this->headers
		);
	}

	/**
	 * Merge default response values with provided values to build a full response.
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function build()
	{
		if (empty($this->status))
		{
			$this->status = System\Http::RETURN_OK;
		}

		// errors or data, but not both
		if (!empty($this->errors))
		{
			$this->data = null;
		}
		else
		{
			$this->errors = null;
		}

		$this->response =
			[
				'data'   => $this->data,
				'links'  => $this->links,
				'errors' => $this->errors,
				'meta'   => $this->meta,
			];

		// finally insert version info and nosniff
		$this->withHeaders(
			[
				'x-wbl-api-version'      => $this->version,
				'x-wbl-api-id'           => $this->requestId,
				'X-Content-Type-Options' => 'nosniff'
			]
		);

		// Return the response data, can be used directly
		// from php, without an HTTP response.
		foreach ($this->response as $key => $value)
		{
			if ('data' != $key && empty($value))
			{
				unset($this->response[$key]);
			}
		}

		if (!Wb\arrayIsEmpty($this->headers, 'cache-control'))
		{
			$this->withHeaders(
				[
					'Cache-Control' => 'no-cache, no-store'
				]
			);
		}

		if (!empty($this->lastModified))
		{
			$this->withHeaders(
				[
					'Last-Modified' => $this->getLastModified()
				]
			);
		}

		return $this->response;
	}

	/**
	 * Wraps content in an envelope for json security purpose.
	 *
	 * @param   array  $content
	 *
	 * @return string
	 */
	protected function envelope($content)
	{
		$envelopedContent = new \stdClass;
		foreach ($content as $key => $value)
		{
			$envelopedContent->{$key} = $value;
		}

		return $envelopedContent;
	}

	/**
	 * Json encode an input string, without escaping slashed, if PHP version allows.
	 *
	 * @param   string  $content
	 *
	 * @return string| null
	 */
	private function encode($content)
	{
		if (is_null($content))
		{
			return $content;
		}

		// do not use library jsonPrettyPrint as this implies JSON_NUMERIC_CHECK which
		// we do not want. Causes issue for strings such as "012345" which cannot be worked around
		$options = defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : null;
		$options = defined('JSON_UNESCAPED_SLASHES') ? JSON_UNESCAPED_SLASHES | $options : $options;

		return \json_encode(
			$content,
			$options
		);
	}

	/**
	 * Builds a date time string suitable for use in Last-modified header.
	 *
	 * @return string
	 * @throws \Exception
	 */
	private function getLastModified()
	{
		$date = new \DateTime(
			'now',
			new \DateTimeZone('UTC')
		);

		if (!empty($this->lastModified))
		{
			$date->setTimestamp(
				$this->lastModified
			);
		}

		return $date->format(' D, d M Y H:i:s e');
	}
}
