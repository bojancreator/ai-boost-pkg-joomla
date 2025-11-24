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
?>
    <h4><?php echo Text::_('PLG_PWEBOPENGRAPH_DEBUG_OUTPUT'); ?></h4>
<?php

if ($process) :
    echo '<br>' . $this->setLayoutId('debug.facebook')->render($displayData) . '<br>';
    echo '<br>' . $this->setLayoutId('debug.x')->render($displayData) . '<br>';

    if ($debugImages) :
        $message = [Text::_('PLG_PWEBOPENGRAPH_DEBUG_REJECTED')];

        foreach ($debugImages as $image) :
            $message[] = sprintf(
                '%s - %s',
                HTMLHelper::_(
                    'link',
                    $image['image'],
                    str_replace(Uri::root(), '', $image['image']),
                    [
                        'target' => '_blank',
                    ]
                ),
                $image['reason']
            );
        endforeach;
        echo join('<br>', $message);
    endif;

else :
    echo Text::_('PLG_PWEBOPENGRAPH_DEBUG_SKIP_' . $params->get('filter_type', 'exclude'));
endif;

if ($profiler) : ?>
    <br>
    <h4><?php echo Text::_('PLG_PWEBOPENGRAPH_PROFILER'); ?></h4>
    <?php
    $start = 0;
    $last  = 0;
    foreach ($profiler as $entry) :
        $start = $start ?: $entry->timestamp;

        echo sprintf(
            '%s[%s]: %.5f/%.5f - %s<br>',
            $entry->method,
            $entry->line,
            $last ? ($entry->timestamp - $last) : 0,
            $entry->timestamp - $start,
            $entry->message
        );

        $last = $entry->timestamp;
    endforeach;
endif;
