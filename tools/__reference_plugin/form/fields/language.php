<?php
/**
 * @package   ShackOpenGraph
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2021-2024 Joomlashack. All rights reserved
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

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\LanguageHelper;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

if (class_exists(ListField::class) == false) {
    // Joomla 3 classes
    FormHelper::loadFieldClass('List');

    class_alias(JFormFieldList::class, ListField::class);
}

// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class SogFormFieldLanguage extends ListField
{
    /**
     * @inheritDoc
     */
    protected function getOptions()
    {
        $languages   = LanguageHelper::createLanguageList(null, JPATH_SITE);
        $languages[] = (array)HTMLHelper::_('select.option', 'en-US', 'English (United States)');

        usort($languages, function ($a, $b) {
            return $a['value'] == $b['value']
                ? 0
                : ($a['value'] < $b['value'] ? -1 : 1);
        });

        return array_merge(parent::getOptions(), $languages);
    }
}
