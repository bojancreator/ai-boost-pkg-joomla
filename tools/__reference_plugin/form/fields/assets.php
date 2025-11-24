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

use Joomla\CMS\Form\Field\HiddenField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

if (class_exists(HiddenField::class) == false) {
    // Joomla 3 classes
    FormHelper::loadFieldClass('Hidden');

    class_alias(JFormFieldHidden::class, HiddenField::class);
}

class SogFormFieldAssets extends HiddenField
{
    /**
     * @inheritDoc
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        HTMLHelper::_('jquery.framework');

        Text::script('PLG_PWEBOPENGRAPH_SITEMAP_BUTTON');

        HTMLHelper::_('script', 'plg_system_pwebopengraph/admin.js', ['relative' => true]);

        return false;
    }
}
