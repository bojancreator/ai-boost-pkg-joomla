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

// phpcs:disable PSR1.Files.SideEffects
use Joomla\CMS\Form\Field\SpacerField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Version;

defined('_JEXEC') or die();

if (class_exists(SpacerField::class) == false) {
    // Joomla 3 classes
    FormHelper::loadFieldClass('Spacer');

    class_alias(JFormFieldSpacer::class, SpacerField::class);
}
// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class SogFormFieldButton extends SpacerField
{
    public function setup(\SimpleXMLElement $element, $value, $group = null)
    {
        if ($element['class'] == false) {
            $element['class'] = 'nowrap text-nowrap';
        }

        return parent::setup($element, $value, $group);
    }

    /**
     * @inheritDoc
     */
    protected function getLabel()
    {
        $this->setText();

        return parent::getLabel();
    }

    protected function setText(): void
    {
        $link  = trim($this->element['link']);
        $label = (string)$this->element['label'];
        $icon  = Version::MAJOR_VERSION < 4
            ? '<span class="icon-out-2"></span>'
            : '';

        $label = HTMLHelper::_(
            'link',
            $link,
            $icon . Text::_($label),
            [
                'target' => '_blank',
                'class'  => 'btn btn-info btn-sm',
            ]
        );

        $this->element['label'] = $label;
    }
}
