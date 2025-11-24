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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\CheckboxesField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\Menus\Administrator\Model\MenutypesModel;
use Joomla\Utilities\ArrayHelper;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

FormHelper::loadFieldType('Checkboxes');
if (class_exists(CheckboxesField::class) == false) {
    class_alias(JFormFieldCheckboxes::class, CheckboxesField::class);
}

// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class SogFormFieldComponents extends CheckboxesField
{
    /**
     * @inheritdoc
     */
    protected $type = 'sog.components';

    /**
     * @inheritdoc
     */
    protected $forceMultiple = true;

    /**
     * @inheritDoc
     */
    public function setup(SimpleXMLElement $element, $value, $group = null)
    {
        if (parent::setup($element, $value, $group)) {
            if (class_exists(MenutypesModel::class) == false) {
                JLoader::import('components.com_menus.models.menutypes', JPATH_ADMINISTRATOR);
                JLoader::import('components.com_menus.helpers.menus', JPATH_ADMINISTRATOR);
                JLoader::import('components.com_menus.models.menutypes', JPATH_ADMINISTRATOR);

                class_alias(MenusModelMenutypes::class, MenutypesModel::class);
            }

            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function getInput()
    {
        $html = [];

        $class = trim('pwebcomponents checkboxes ' . $this->element['class']);

        $checkedOptions = explode(',', (string)$this->element['checked']);

        $html[] = sprintf('<fieldset id="%s" class="%s">', $this->id, $class);

        $options = $this->getOptions();

        $html[] = '<ul>';
        $i      = 0;
        foreach ($options as $option) {
            if (is_array($option) == false) {
                $i++;

                if ($this->value == false) {
                    $checked = in_array((string)$option->value, (array)$checkedOptions);
                } else {
                    $value   = !is_array($this->value) ? explode(',', $this->value) : $this->value;
                    $checked = in_array((string)$option->value, $value);
                }

                $inputAttributes = [
                    'type'  => 'checkbox',
                    'id'    => $this->id . $i,
                    'name'  => $this->name,
                    'value' => $option->value,
                ];
                if ($checked) {
                    $inputAttributes['checked'] = 'checked';
                }

                $labelAttributes = [
                    'for'   => $inputAttributes['id'],
                    'class' => 'checkbox',
                ];

                $html[] = '<li>';
                $html[] = sprintf('<label %s>', ArrayHelper::toString($labelAttributes));
                $html[] = sprintf('<input %s/>', ArrayHelper::toString($inputAttributes));
                $html[] = Text::_($option->text) . '</label>';

            } else {
                $html[] = '<li>';
                $html[] = '<ul>';

                $j = 0;
                foreach ($option as $child) {
                    $j++;

                    if ($this->value == false) {
                        $checked = in_array((string)$child->value, (array)$checkedOptions);

                    } else {
                        $value   = !is_array($this->value) ? explode(',', $this->value) : $this->value;
                        $checked = in_array((string)$child->value, $value);
                    }

                    $optionId = $this->id . $i . '-' . $j;

                    $inputAttributes = [
                        'type'  => 'checkbox',
                        'id'    => $optionId,
                        'name'  => $this->name,
                        'value' => $child->value,
                    ];
                    if ($checked) {
                        $inputAttributes['checked'] = 'checked';
                    }

                    $labelAttributes = [
                        'for'   => $optionId,
                        'class' => 'checkbox',
                    ];

                    $html[] = '<li>';
                    $html[] = sprintf('<label %s>', ArrayHelper::toString($labelAttributes));
                    $html[] = sprintf('<input %s/>', ArrayHelper::toString($inputAttributes));
                    $html[] = Text::_($child->text) . '</label>';
                    $html[] = '</li>';
                }
                $html[] = '</ul>';
            }
            $html[] = '</li>';
        }
        $html[] = '</ul>';

        // End the checkbox field output.
        $html[] = '</fieldset>';

        return implode($html);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function getOptions()
    {
        $options = [];

        try {
            $model = new MenutypesModel();
            $types = $model->getTypeOptions();

        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            $types = [];
        }

        foreach ($types as $name => $list) {
            $request   = ['option' => $name];
            $options[] = HTMLHelper::_(
                'select.option',
                base64_encode(json_encode($request)),
                Text::_($name),
                'value',
                'text',
                false
            );

            $options2 = [];
            foreach ($list as $item) {
                $options2[] = HTMLHelper::_(
                    'select.option',
                    base64_encode(json_encode($item->request)),
                    Text::_($item->title),
                    'value',
                    'text',
                    false
                );
            }
            if (count($options2)) {
                $options[] = $options2;
            }
        }

        return $options;
    }
}
