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
    <!-- Facebook debugger -->
    <h4>
        <?php
        echo HTMLHelper::_(
            'link',
            sprintf(
                'https://developers.facebook.com/tools/debug?q=%s',
                rawurlencode($tags['url']['content'] ?? Uri::getInstance()->toString())
            ),
            Text::_('PLG_PWEBOPENGRAPH_DEBUG_FB_DEBUGGER'),
            'target="_blank"'
        );
        ?>
    </h4>
<?php
$message = [];
foreach ($tags as $tag) :
    $property = $tag['property'];
    $content  = $tag['content'];

    if ($property == 'og:image') {
        $content = HTMLHelper::_(
            'link',
            $content,
            str_replace(Uri::root(), '', $content),
            [
                'target' => '_blank',
            ]
        );
    }

    $message[] = sprintf('<strong>%s</strong> - %s', $property, $content);
endforeach;
echo join('<br>', $message);
