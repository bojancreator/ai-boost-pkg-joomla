<?php
/**
 * @package   ShackOpenGraph
 * @author    Piotr Moćko
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2018 Perfect Web sp. z o.o., All rights reserved.
 * @copyright 2019-2024 Joomlashack. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of ShackOpenGraph.
 *
 * ShackOpenGraph is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * ShackOpenGraph is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ShackOpenGraph.  If not, see <http://www.gnu.org/licenses/>.
 */

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class PlgsystemPWebOpenGraph extends CMSPlugin
{
    /**
     * @var CMSApplication
     */
    protected $app = null;

    /**
     * @var bool
     */
    protected $isHome = false;

    /**
     * @var int
     */
    protected $article_id;

    /**
     * @var string
     */
    protected $imageDefault = null;

    /**
     * @var bool
     */
    protected $process = null;

    /**
     * @var string[]
     */
    protected $images = null;

    /**
     * @var int
     */
    protected $minimumScore = -1;

    /**
     * @var array
     */
    protected $tags = [];

    /**
     * @var array
     */
    protected $twitterTags = [];

    /**
     * @var bool
     */
    protected $enabled = null;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var array
     */
    protected $debugImages = [];

    /**
     * @var array
     */
    protected static $profilerEntries = [];

    /**
     * @var array[]
     */
    protected $regExps = [
        'img'   => [
            '/<img\s+[^<>]*?src\s*=\s*[\\"\']?([^\\"\']+\.(png|jpg|jpeg|gif))[\\"\']?/i', // images with extension
            '/<img\s+[^<>]*?src\s*=\s*[\\"\']?([^\\"\']+)[\\"\']?/i', // images without extension
        ],
        'img_a' => [
            '/<(?:img\s+[^<>]*?src|a\s+[^<>]*href)\s*=\s*[\\"\']?([^\\"\']+\.(png|jpg|jpeg|gif))[\\"\']?/i',
            '/(?:<img\s+[^<>]*?src\s*=\s*[\\"\']?([^\\"\']+)[\\"\']?|<a\s+[^<>]*href\s*=\s*[\\"\']?([^\\"\']+\.(png|jpg|jpeg|gif))[\\"\']?)/i',
        ],
    ];

    /**
     * @inheritDoc
     */
    public function __construct($subject, array $config = [])
    {
        parent::__construct($subject, $config);

        $this->minimumScore = (int)$this->params->get('min_score', 3);

        if ($imageDefault = $this->params->get('image_default')) {
            $this->imageDefault = $this->normalizeImageUrl($imageDefault);
        }

        // Debug init
        $this->debug = ((int)$this->params->get('debug') || $this->app->input->getInt('debug'));
        if ($this->debug) {
            $this->loadLanguage();
        }

        $this->profiler('INIT');
    }

    /**
     * @return void
     * @throws Exception
     */
    public function onBeforeRender()
    {
        if ($this->isEnabled() == false) {
            return;
        }

        $this->profiler('Begin');

        // Filter components
        $exclude    = $this->params->get('filter_type', 'exclude') == 'exclude';
        $components = (array)$this->params->get('filter_components', []);

        $request = [];
        foreach ($components as $component) {
            $request = json_decode(base64_decode($component));
            $found   = true;

            // check request variables
            foreach ($request as $key => $value) {
                if ($this->app->input->getCmd($key) != $value) {
                    $found = false;
                    break;
                }
            }

            if ($found) {
                // found matching request
                if ($exclude) {
                    // stop processing if exclude found component
                    $this->process = false;
                    break;

                } else {
                    // stop searching and continue processing
                    $this->process = true;
                    break;
                }
            }
        }

        if ($this->process === null && $exclude) {
            $this->process = true;
        }

        if ($this->process) {
            /** @var HtmlDocument $doc */
            $doc = Factory::getDocument();

            $this->tags        = $this->getOpenGraphTags();
            $this->twitterTags = $this->getTwitterTags();

            if ($this->isHome && $request) {
                $this->profiler('Checking Home Page');
                // is home page
                if ($menu = $this->app->getMenu()) {
                    $activeMenu = $menu->getActive();

                    foreach ($request as $key => $value) {
                        if (
                            isset($activeMenu->query[$key]) == false
                            || $this->app->input->getCmd($key) != $activeMenu->query[$key]
                        ) {
                            $this->isHome = false;
                            break;
                        }
                    }
                }
            }

            if (
                ($this->isHome && $this->params->get('image_home', 1))
                || $this->params->get('image_component', 1) == false
            ) {
                // Add default image on home page and on other pages if search in component is disabled
                if ($this->imageDefault) {
                    $this->tags['image']                = [
                        'property' => 'og:image',
                        'content'  => $this->imageDefault,
                    ];
                    $this->twitterTags['twitter:image'] = $this->imageDefault;
                }

            } else {
                // Get component buffer
                $buffer = $doc->getBuffer('component');

                $images = $this->findFacebookImage() ?: $this->findImages($buffer);
                foreach ($images as $imageUrl) {
                    $this->tags[] = ['property' => 'og:image', 'content' => $imageUrl];
                }

                if ($twitterImage = $this->findTwitterImage() ?: $this->findImages($buffer)) {
                    $this->twitterTags['twitter:image'] = reset($twitterImage);
                }
            }

            // Add OG tags to document head
            foreach ($this->tags as $tag) {
                $doc->addCustomTag(
                    sprintf('<meta property="%s" content="%s"/>', $tag['property'], $tag['content'])
                );
            }

            // Twitter Card Tags
            if ($this->params->get('twitter_cardtags', 1)) {
                foreach ($this->twitterTags as $name => $content) {
                    $doc->setMetaData($name, $content);
                }
            }
        }

        $this->profiler('End');

        if ($this->debug) {
            $this->displayDebug();
        }
    }

    /**
     * Check if accessing an article page
     *
     * @return bool
     */
    protected function isArticlePage(): bool
    {
        $option           = $this->app->input->get('option');
        $view             = $this->app->input->get('view');
        $this->article_id = $this->app->input->getInt('id');

        if ($option == 'com_content' && $view == 'article' && $this->article_id) {
            return true;
        }

        return false;
    }

    /**
     * Handle Graph Image Field
     *
     * @param ?string $field
     *
     * @return string[]
     */
    protected function getArticleImage(?string $field): array
    {
        $field = $field ?: 'image_intro';

        $this->profiler('FIND ' . $field);

        switch ($field) {
            case 'image_fulltext':
            case 'image_intro':
                $db    = Factory::getDbo();
                $query = $db->getQuery(true)
                    ->select('images')
                    ->from('#__content')
                    ->where('id = ' . $db->quote($this->article_id));

                $images = new Registry($db->setQuery($query)->loadResult());

                $image = $this->getMediaFieldImage($images->get($field));

                if ($image) {
                    return [Uri::base() . $image];
                }
                break;

            case 'twitter_image':
            case 'facebook_image':
                $db      = Factory::getDbo();
                $fieldId = $this->findFieldID($field);

                if ($fieldId) {
                    $query = $db->getQuery(true)
                        ->select('value')
                        ->from('#__fields_values')
                        ->where([
                            'item_id = ' . $db->quote($this->article_id),
                            'field_id = ' . $fieldId,
                        ]);

                    $image = $this->getMediaFieldImage($db->setQuery($query)->loadResult());
                    if ($image) {
                        return [Uri::base() . $image];
                    }
                }

                break;
        }

        return [];
    }

    /**
     * @param string $name
     *
     * @return int
     */
    protected function findFieldID(string $name): int
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__fields')
            ->where([
                'context = ' . $db->quote('com_content.article'),
                'type = ' . $db->quote('media'),
                'name = ' . $db->quote(str_replace('_', '-', $name)),
            ]);

        return (int)$db->setQuery($query)->loadResult();
    }

    /**
     * Get the image url from a custom field value
     *
     * @param ?string $fieldValue
     *
     * @return ?string
     */
    protected function getMediaFieldImage(?string $fieldValue): ?string
    {
        if ($fieldValue) {
            if (Version::MAJOR_VERSION >= 4) {
                $image  = json_decode($fieldValue);
                $image  = $image->imagefile ?? $fieldValue;
                $parsed = parse_url($image);
                $image  = $parsed['path'] ?? null;
            }
        }

        return $image ?? $fieldValue;
    }

    /**
     * @return string[]
     */
    protected function findFacebookImage(): array
    {
        if ($this->isArticlePage()) {
            return $this->getArticleImage($this->params->get('content_facebook_field'));
        }

        return [];
    }

    /**
     * @return string[]
     */
    protected function findTwitterImage(): array
    {
        if ($this->isArticlePage()) {
            return $this->getArticleImage($this->params->get('content_twitter_field'));
        }

        return [];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function onAfterRender()
    {
        if ($this->process && $this->params->get('xmlns', 1)) {
            $this->app->setBody(
                preg_replace(
                    '/<html /i',
                    '<html prefix="og: https://ogp.me/ns#" ',
                    $this->app->getBody()
                )
            );
        }
    }

    /**
     * @return bool
     */
    protected function isEnabled(): bool
    {
        if ($this->enabled === null) {
            $this->enabled = $this->app->isClient('site') && (Factory::getDocument()->getType() == 'html');

            if ($this->enabled) {
                if ($menu = $this->app->getMenu()) {
                    if ($activeMenu = $menu->getActive()) {
                        $languageCode = $this->app->getLanguageFilter() ? Factory::getLanguage()->getTag() : null;
                        if ($defaultMenu = $menu->getDefault($languageCode)) {
                            $this->isHome = $activeMenu->id == $defaultMenu->id;
                        }
                    }
                }
            }
        }

        return $this->enabled;
    }

    /**
     * @return array[]strings[]
     */
    protected function getOpenGraphTags(): array
    {
        $openGraphTags = [];

        if ($this->params->get('fb_appid')) {
            $openGraphTags['appid'] = ['property' => 'fb:app_id', 'content' => $this->params->get('fb_appid')];
        }
        if ($this->params->get('fb_admins')) {
            $openGraphTags['admins'] = ['property' => 'fb:admins', 'content' => $this->params->get('fb_admins')];
        }

        if ($this->params->get('website_details', 1)) {
            /** @var HtmlDocument $doc */
            $doc   = Factory::getDocument();
            $title = $doc->getTitle();

            $openGraphTags['title']     = [
                'property' => 'og:title',
                'content'  => $title ? htmlentities($title, ENT_QUOTES, 'UTF-8') : $this->app->get('sitename'),
            ];
            $openGraphTags['type']      = [
                'property' => 'og:type',
                'content'  => $this->isHome ? 'website' : 'article',
            ];
            $openGraphTags['url']       = [
                'property' => 'og:url',
                'content'  => htmlentities(Uri::getInstance()->toString()),
            ];
            $openGraphTags['site_name'] = ['property' => 'og:site_name', 'content' => $this->app->get('sitename')];

            // Description
            if ($description = $doc->getMetaData('description')) {
                $openGraphTags['description'] = [
                    'property' => 'og:description',
                    'content'  => htmlentities($description, ENT_QUOTES, 'UTF-8'),
                ];
            }
        }

        if ($this->params->get('fb_locale_enable')) {
            $primaryLocale = str_replace('-', '_', $this->params->get('fb_locale_primary'));

            if ($this->params->get('fb_locale_alternate_select', 1)) {
                $alternateLocales = (array)$this->params->get('fb_locale_alternate');

            } else {
                $alternateLocales = array_keys(LanguageHelper::getInstalledLanguages(0));
            }

            if ($primaryLocale) {
                $openGraphTags['locale'] = ['property' => 'og:locale', 'content' => $primaryLocale];

                $alternateLocales = array_filter(array_unique($alternateLocales));
                foreach ($alternateLocales as $alternateLocale) {
                    $alternateLocale = str_replace('-', '_', $alternateLocale);
                    if ($alternateLocale != $primaryLocale) {
                        $openGraphTags['altlocale.' . $alternateLocale] = [
                            'property' => 'og:locale:alternate',
                            'content'  => $alternateLocale,
                        ];
                    }
                }
            }

        }

        return $openGraphTags;
    }

    /**
     * @return string[]
     */
    protected function getTwitterTags(): array
    {
        $twitterTags = [];

        if ($this->params->get('twitter_cardtags', 1)) {
            $twitterTags['twitter:card'] = $this->params->get('twitter_card_type_single_image', 'summary');

            if ($this->params->get('twitter_username')) {
                $twitterTags['twitter:site'] = $this->params->get('twitter_username');
            }

            if ($this->params->get('twitter_creator')) {
                $twitterTags['twitter:creator'] = $this->params->get('twitter_creator');
            }

            if ($this->params->get('twitter_website_details', 1)) {
                /** @var HtmlDocument $doc */
                $doc   = Factory::getDocument();
                $title = $doc->getTitle();

                $twitterTags['twitter:url']   = Uri::getInstance()->toString();
                $twitterTags['twitter:title'] = $title
                    ? htmlentities($title, ENT_QUOTES, 'UTF-8')
                    : $this->app->get('sitename');

                // Description
                if ($description = $doc->getMetaData('description')) {
                    $twitterTags['twitter:description'] = htmlentities($description, ENT_QUOTES, 'UTF-8');
                }
            }
        }

        return $twitterTags;
    }

    /**
     * @param string $text
     *
     * @return string[]
     */
    protected function findImages(string $text): array
    {
        if ($this->images === null) {
            // Select regular expression
            $regType   = $this->params->get('image_link', 0) ? 'img_a' : 'img';
            $extension = $this->params->get('image_ext', 1) ? 0 : 1;
            $regExp    = $this->regExps[$regType][$extension];

            $this->images = [];

            $imageLimit = $this->params->get('images_limit', 2);
            if ($imageLimit == 1) {
                $this->profiler('Begin Find First Image');
                preg_match($regExp, $text, $images);

            } else {
                $this->profiler('Begin Find All Images');
                preg_match_all($regExp, $text, $images);
            }

            $images = array_slice(array_unique((array)$images[1]), 0, 10);
            foreach ($images as $image) {
                if ($imageUrl = $this->normalizeImageUrl($image)) {
                    $this->profiler('Image: ' . $imageUrl);
                    $this->images[] = $imageUrl;
                }
            }

            if (empty($this->images)) {
                $this->addDefaultImage();
            }
        }

        return $this->images;
    }

    /**
     * return ?string
     */
    protected function addDefaultImage(): ?string
    {
        if ($imageDefault = $this->params->get('image_default')) {
            if ($imageDefault = $this->normalizeImageUrl($imageDefault)) {
                $this->profiler('Using Default Image: ' . $imageDefault);

                return $imageDefault;
            }
        }

        return null;
    }

    /**
     * Score:
     *  0 - smaller than 50x50
     *  1 - aspect ratio bigger than 3:1
     *  2 - smaller than 200x200
     *  3 - smaller than 280x150
     *  4 - correct
     *
     * @param string $path
     * @param bool   $is_url
     *
     * @return bool
     */
    protected function checkImage(string $path, ?bool $is_url = false): bool
    {
        if ($this->minimumScore == -1) {
            return true;
        }

        $info = @getimagesize($is_url ? $path : JPATH_ROOT . '/' . $path);
        if ($info == false) {
            return true;
        }

        if ($info[0] < 50 || $info[1] < 50) {
            $score = 0;

        } elseif ($this->minimumScore == 1) {
            $score = 1;

        } elseif ($this->minimumScore == 4) {
            if ($info[0] < 280 || $info[1] < 150) {
                if ($info[0] / $info[1] > 3) {
                    $score = 1;
                } else {
                    $score = 3;
                }
            } else {
                $score = 0;
            }

        } elseif ($info[0] < 200 || $info[1] < 200) {
            if ($info[0] / $info[1] > 3) {
                $score = 1;
            } else {
                $score = 2;
            }

        } elseif ($info[0] / $info[1] > 3) {
            $score = 1;

        } else {
            $score = 4;
        }

        if ($this->debug && $this->minimumScore > $score) {
            $this->debugImages[] = [
                'image'  => $path,
                'reason' => Text::_('PLG_PWEBOPENGRAPH_DEBUG_REJECT_REASON_' . $score),
            ];
        }

        return $this->minimumScore <= $score;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    protected function encodeURIComponent(string $str): string
    {
        $str = preg_replace('/\s/u', '%20', $str);

        return htmlspecialchars(htmlspecialchars_decode($str));
    }

    /**
     * @param string $imageUrl
     *
     * @return ?string
     */
    protected function normalizeImageUrl(string $imageUrl): ?string
    {
        if (is_callable([HTMLHelper::class, 'cleanImageURL'])) {
            $imageUrl = HTMLHelper::cleanImageURL($imageUrl)->url;
        }

        if (preg_match('#^https?://#i', $imageUrl)) {
            if ($this->checkImage($imageUrl, true)) {
                return $this->encodeURIComponent($imageUrl);
            }

        } else {
            $imageUrl = ltrim($imageUrl, '/');
            if ($this->checkImage($imageUrl)) {
                return Uri::root() . $this->encodeURIComponent($imageUrl);
            }
        }

        return null;
    }

    /**
     * @param string $message
     *
     * @return void
     */
    protected function profiler(string $message)
    {
        if ($this->debug) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $line   = empty($trace[0]['line']) ? '{LINE}' : $trace[0]['line'];
            $method = array_filter(
                [
                    $trace[1]['class'] ?? null,
                    $trace[1]['function'] ?? null,
                ]
            );

            static::$profilerEntries[] = (object)[
                'timestamp' => microtime(true),
                'method'    => join('::', $method),
                'line'      => $line,
                'message'   => $message,
            ];
        }
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function displayDebug()
    {
        $data = [
            'process'     => $this->process,
            'params'      => $this->params,
            'tags'        => $this->tags,
            'twitterTags' => $this->twitterTags,
            'images'      => $this->images,
            'debugImages' => $this->debugImages,
            'profiler'    => static::$profilerEntries,
        ];

        $this->app->enqueueMessage(LayoutHelper::render('debug.default', $data, __DIR__ . '/tmpl'));
    }
}
