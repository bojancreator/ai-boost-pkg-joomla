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

namespace Weeblr\Forseo\Model\Sd;

use Weeblr\Forseo\Data;
use Weeblr\Forseo\Helper;

use Weeblr\Wblib\Forseo\Wb;
use Weeblr\Wblib\Forseo\System;
use Weeblr\Wblib\Forseo\Html;
use Weeblr\Wblib\Forseo\Joomla\StringHelper\StringHelper;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_EXEC') || die;

/**
 * Base class for Structured Data objects.
 *
 * @package Weeblr\Forseo\Data\Sd
 */
class Faqpage extends Base
{
	/**
	 * @var string Schema type.
	 */
	protected $type = Data\Sd::FAQ_PAGE;

	/**
	 * Builds a data array suited to building Structured data from the
	 * rule definition used in the client.
	 *
	 * @param string $actualRuleType
	 * @param array  $rule
	 * @return $this
	 * @throws \Exception
	 */
	public function addSdSpecFromRule($actualRuleType, $rule)
	{
		parent::addSdSpecFromRule($actualRuleType, $rule);

		if (empty($actualRuleType))
		{
			return $this;
		}

		// Some SD types may have "sub-types"
		$ruleType = $actualRuleType;

		$fields = [
			'inLanguage' => [
				'type'      => Data\Sd::TEXT,
				'valueType' => Data\Sd::FIELD_CUSTOM,
				'value'     => $this->requestInfo->get('page_language')
			]
		];

		foreach ($rule as $key => $value)
		{
			if (!Wb\startsWith($key, 'actionSd'))
			{
				continue;
			}
			$itemKey   = Wb\lTrim($key, 'actionSd');
			$lcItemKey = Wb\lcFirst($itemKey);
			switch ($lcItemKey)
			{
				case 'url':
				case 'headline':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => Wb\arrayGet($rule, $key)
					];
					break;
				case 'author':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
					];
					$authorFromRule     = $this->helper->authorFromRule(
						$rule,
						Wb\arrayGet($fields, [$lcItemKey, 'valueType'], Data\Sd::FIELD_AUTO)
					);
					if (!empty($authorFromRule))
					{
						$fields[$lcItemKey]['value'] = $authorFromRule;
					}
					break;
				case 'datePublished':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => Wb\join(' ', Wb\arrayGet($rule, 'actionSdDatePublished'), Wb\arrayGet($rule, 'actionSdTimePublished'))
					];
					break;
				case 'dateModified':
					$fields[$lcItemKey] = [
						'type'      => $this->sdFieldType($lcItemKey),
						'valueType' => $this->sdIsAutomaticField($rule, $itemKey),
						'value'     => Wb\join(Wb\arrayGet($rule, 'actionSdDateModified'), Wb\arrayGet($rule, 'actionSdTimeModified'))
					];
					break;

				// specific to FAQ
				case 'faqMode':
					$qas = [];
					switch ($value)
					{
						case Data\Sd::DETECT_NONE:
							// only custom Q/A
							$qaSpecs = Wb\arrayGet($rule, 'actionSdFaqMainEntity', []);
							foreach ($qaSpecs as $qaSpec)
							{
								$qas[] = [
									'@type'     => $this->sdFieldType('question'),
									'valueType' => Data\Sd::FIELD_CUSTOM,
									'value'     => [
										'@type'          => $this->sdFieldType('question'),
										'name'           => Wb\arrayGet($qaSpec, 'q', ''),
										'acceptedAnswer' => [
											'@type' => Data\Sd::ANSWER,
											'text'  => Wb\arrayGet($qaSpec, 'a', '')
										],
									]
								];
							}

							break;
					}
					if (!empty($qas))
					{
						$fields['mainEntity'] = $qas;
					}
					break;
			}
		}

		// gather everyone
		$this->spec = [
			'rule'                     => $rule,
			'type'                     => $ruleType,
			'actualType'               => $actualRuleType,
			'useItemAuthor'            => true,
			'useDefaultImageIfMissing' => true,
			'fields'                   => $fields
		];

		return $this;
	}

	/**
	 * Fill-in fallback values that may not have provided by plugin.
	 *
	 * @return $this|Faqpage
	 * @throws \Exception
	 */
	protected function addMissingFields()
	{
		parent::addMissingFields();

		if (empty($this->sdData['mainEntity']))
		{
			$this->sdData['mainEntity'] = [];
		}
		$mode         = Wb\arrayGet($this->spec, ['rule', 'actionSdFaqMode']);
		$sdData       = [];
		$detectedFaqs = $this->detectFaqInContent([$mode]);
		foreach ($detectedFaqs as $detectedFaq)
		{
			$sdData[] = [
				'@type'          => Data\Sd::QUESTION,
				'name'           => Wb\arrayGet($detectedFaq, 'q'),
				'acceptedAnswer' => [
					'@type' => Data\Sd::ANSWER,
					'text'  => Wb\arrayGet($detectedFaq, 'a')
				],
			];
		}

		if (!empty($sdData))
		{
			$this->sdData['mainEntity'] = array_merge(
				$this->sdData['mainEntity'],
				$sdData
			);
		}


		if (
			!empty($sdData)
			&&
			empty($this->sdData['mainEntityOfPage'])
		) {
			$url                              = Wb\arrayGet(
				$this->sdData,
				'url',
				$this->requestInfo->get('page_url')
			);
			$this->sdData['mainEntityOfPage'] = [
				'@type' => 'WebPage',
				'url'   => $url
			];
		}

		return $this;
	}

	/**
	 * Whether enough structured data has been provided for required fields.
	 *
	 * @return bool
	 */
	protected function hasRequiredFields()
	{
		$hasRequiredFields = parent::hasRequiredFields();
		return $hasRequiredFields
			   &&
			   !empty($this->sdData['mainEntity']);
	}

	/**
	 * Extract FAQ stuctured data from page content, based on specified mode:
	 * - shortcodes
	 * - CSS selectors
	 *
	 * @param array $modes
	 * @return array
	 */
	protected function detectFaqInContent($modes)
	{
		if (empty($modes))
		{
			return [];
		}

		$sdData      = [];
		$pageContent = $this->platform->getDocumentContent();

		if (in_array(Data\Sd::DETECT_CSS, $modes))
		{
			$sdData = $this->extractFromCSS($sdData, $pageContent);
		}

		if (in_array(Data\Sd::DETECT_CODES, $modes))
		{
			$sdData = $this->extractFromShortCodes($sdData, $pageContent);
		}

		return $sdData;
	}

	/**
	 * Extract an array of question/answers marked with specific CSS classes in content.
	 *
	 * @param array  $sdData
	 * @param string $content
	 * @return array
	 */
	private function extractFromCSS($sdData, $content)
	{
		$questionSelectors = trim(
			Wb\arrayGet($this->spec, ['rule', 'actionSdFaqQCss'], '')
		);
		$questionSelectors = rtrim($questionSelectors, '.');
		$answerSelectors   = trim(
			Wb\arrayGet($this->spec, ['rule', 'actionSdFaqACss'], '')
		);
		$answerSelectors   = rtrim($answerSelectors, '.');

		if (
			empty($questionSelectors)
			||
			empty($answerSelectors)
		) {
			return [];
		}

		// build a DOM object
		$dom = Html\Extract::domFromContent($content);
		if (empty($dom))
		{
			$this->factory->getThe('forseo.logger')->error(__METHOD__ . ': error turning page content into a DOM object. URL ' . $this->factory->getThe('forseo.pageHelper')->getCleanedCurrentUrl());

			return [];
		}

		try
		{
			$xPather   = new \DOMXPath($dom);
			$xpathSpec = Html\Extract::cssSelectorToXPath($questionSelectors);
			$questions = $xPather->query($xpathSpec);
			if (empty($questions) || empty($questions->length))
			{
				return [];
			}

			$xpathSpec = Html\Extract::cssSelectorToXPath($answerSelectors);
			$answers   = $xPather->query($xpathSpec);
			if (empty($answers) || empty($answers->length) || $answers->length !== $questions->length)
			{
				$this->factory->getThe('forseo.logger')
							  ->error(__METHOD__ . ': error extracting Q/A on FAqPage, no question, answer or not same number of questions and answers. URL: ' . $this->factory->getThe('forseo.pageHelper')->getCleanedCurrentUrl());
				return [];
			}

			for ($nodeIndex = 0; $nodeIndex < $questions->length; $nodeIndex++)
			{
				$q = $this->processQAFragment(
					$questions[$nodeIndex],
					$this->config->get('faqPageItemAllowedTagsQuestion')
				);
				$a = $this->processQAFragment(
					$answers[$nodeIndex],
					$this->config->get('faqPageItemAllowedTags')
				);
				if (empty($q) || empty($a))
				{
					continue;
				}
				$sdData[] = [
					'q' => $q,
					'a' => $a
				];
			}
		}
		catch (\Exception $e)
		{
			$this->factory->getThe('forseo.logger')->error('%s::%d %s - %s', $e->getFile(), $e->getLine(), $e->getMessage(), $e->getTraceAsString());
		}

		return $sdData;
	}

	/**
	 * Process a single question or answer DOMNode extracted from the page content
	 * to be used by Google as part of FaqPage.
	 *
	 * @param \DOMNode $framentNode
	 * @param array    $allowedTags
	 * @return string
	 */
	private function processQAFragment($framentNode, $allowedTags)
	{
		$html = $framentNode->ownerDocument->saveHTML($framentNode);
		$html = strip_tags(
			$html,
			$allowedTags
		);

		// empty p
		$html = preg_replace('~<p>\s*</p>~', '', $html);
		// useless spaces
		$html = preg_replace('~\s\s+~', '', $html);
		$html = StringHelper::trim($html);

		$dom = Html\Extract::domFromContent($html);

		$xpather   = new \DOMXPath($dom);
		$attrNodes = $xpather->query('//@*');
		foreach ($attrNodes as $attrNode)
		{
			if ($attrNode->nodeName === 'href')
			{
				$attrNode->parentNode
					->setAttribute(
						'href',
						System\Route::absolutify(
							$attrNode->nodeValue,
							true
						)
					);
			}
			else
			{
				$attrNode->parentNode
					->removeAttribute($attrNode->nodeName);
			}
		}


		$html = '';
		$body = $dom->getElementsByTagName('body')->item(0);
		foreach ($body->childNodes as $n)
		{
			$html .= $dom->saveHTML($n);
		}

		return $html;
	}

	/**
	 * Extract an array of question/answers marked with {4seo_faq_xxx_yyy} shortcodes in the page content.
	 *
	 * @param array  $sdData
	 * @param string $content
	 * @return array
	 */
	private function extractFromShortCodes($sdData, $content)
	{
		$pattern = '~{4seo_faq_question_start}(.+){4seo_faq_question_end}.*{4seo_faq_answer_start}(.+){4seo_faq_answer_end}~iuUs';
		$found   = preg_match_all(
			$pattern,
			$content,
			$faqs,
			PREG_SET_ORDER
		);
		if ($found > 0)
		{
			foreach ($faqs as $faq)
			{
				$q = StringHelper::trim(Wb\arrayGet($faq, 1));
				$a = StringHelper::trim(Wb\arrayGet($faq, 2));
				if (!empty($q) && !empty($a))
				{
					$sdData[] = [
						'q' => $q,
						'a' => $a
					];
				}
			}
		}

		return $sdData;
	}
}
