<?php
/**
 * @package   ShackOpenGraph
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2024 Joomlashack.com. All rights reserved
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
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
 * along with ShackOpenGraph.  If not, see <https://www.gnu.org/licenses/>.
 */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\FileLayout;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

defined('_JEXEC') or die();

/**
 * @var FileLayout $this
 * @var array      $displayData
 * @var string     $layoutOutput
 * @var string     $path
 */

extract($displayData);
/**
 * @var bool     $process
 * @var Registry $params
 * @var array[]  $tags
 * @var string[] $twitterTags
 * @var string[] $images
 * @var array[]  $debugImages
 * @var object[] $profiler
 */

/**
 * @var bool     $process
 * @var Registry $params
 * @var array    $tags
 * @var string[] $images
 */

if ($params->get('twitter_cardtags', 1)) : ?>
    <!-- Twitter Card Validator -->
    <h4>
        <?php echo HTMLHelper::_(
            'link',
            'https://cards-dev.twitter.com/validator',
            Text::_('PLG_PWEBOPENGRAPH_DEBUG_TWITTER_CARD_VALIDATOR'),
            'target="_blank"'
        );
        ?>
    </h4>
    <?php
    $message = [];
    foreach ($twitterTags as $name => $content) :
        if (strpos($name, ':image') != false) :
            $content = HTMLHelper::_(
                'link',
                $content,
                str_replace(Uri::root(), '', $content),
                [
                    'target' => '_blank',
                ]
            );
        endif;
        $message[] = sprintf('<strong>%s</strong> - %s', $name, $content);
    endforeach;
    echo join('<br>', $message);
endif;
