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

use Alledia\Installer\AbstractScript;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Version;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die();

require_once __DIR__ . '/library/Installer/include.php';

// phpcs:enable PSR1.Files.SideEffects
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace

class PlgsystempwebopengraphInstallerScript extends AbstractScript
{
    /**
     * @inheritDoc
     */
    protected function initProperties(InstallerAdapter $parent): void
    {
        parent::initProperties($parent);

        if ($this->cancelInstallation !== true) {
            Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_fields/tables');
        }
    }

    /**
     * @inheritDoc
     */
    protected function customPreFlight(string $type, InstallerAdapter $parent): bool
    {
        $this->removeUpdateSite();

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function customUpdate(InstallerAdapter $parent): bool
    {
        $this->removeOldLanguageFiles();

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function customPostFlight(string $type, InstallerAdapter $parent): void
    {
        $this->checkGraphFields();
    }

    /**
     * Remove all references to pre-Joomlashack update sites
     *
     * @retun void
     */
    protected function removeUpdateSite()
    {
        $db = $this->dbo;

        $query = $db->getQuery(true)
            ->select('se.update_site_id')
            ->from('#__extensions AS extension')
            ->innerJoin('#__update_sites_extensions AS se ON se.extension_id = extension.extension_id')
            ->innerJoin('#__update_sites AS site ON site.update_site_id = se.update_site_id')
            ->where([
                'extension.type = ' . $db->quote('plugin'),
                'extension.element = ' . $db->quote('pwebopengraph'),
                'extension.folder = ' . $db->quote('system'),
                'site.location like ' . $db->quote('%perfect-web.co%'),
            ]);

        if ($updateSites = $db->setQuery($query)->loadColumn()) {
            $updateSites = join(',', array_filter(array_map('intval', $updateSites)));

            $query = $db->getQuery(true)
                ->delete('#__update_sites')
                ->where(sprintf('update_site_id IN (%s)', $updateSites));

            $db->setQuery($query)->execute();

            $query->clear('delete')->delete('#__updates');
            $db->setQuery($query)->execute();
        }
    }

    /**
     * Removes old language files from site core
     * As they are now saved locally
     */
    protected function removeOldLanguageFiles()
    {
        $files = Folder::files(JPATH_ADMINISTRATOR . '/language', '\.plg_system_pwebopengraph\.', true, true);
        foreach ($files as $file) {
            File::delete($file);
        }
    }

    /**
     * Verify the custom article fields we use
     */
    public function checkGraphFields()
    {
        $groupTable = $this->getFieldsTable('Group');

        $articleFields = [
            $this->findGraphField('facebook_image'),
            $this->findGraphField('twitter_image'),
        ];

        $groupIds = array_unique(
            array_map(
                function (Table $table) {
                    return $table->get('group_id');
                },
                $articleFields
            )
        );
        if (count($groupIds) == 1 && $groupIds[0]) {
            // Try to load the referenced field group
            $groupTable->load(['id' => $groupIds[0]]);
        }

        $now    = Date::getInstance()->toSql();
        $userId = Factory::getUser()->get('id');

        // Verify field group
        if (empty($groupTable->get('id'))) {
            // Create a new field group
            $groupTable->bind([
                'context'     => 'com_content.article',
                'title'       => Text::_('PLG_PWEBOPENGRAPH_INSTALL_FIELD_GROUP_TITLE'),
                'note'        => Text::_('PLG_PWEBOPENGRAPH_INSTALL_FIELD_NOTE'),
                'description' => Text::_('PLG_PWEBOPENGRAPH_INSTALL_FIELD_GROUP_DESCRIPTION'),
                'state'       => 1,
                'access'      => 1,
                'language'    => '*',
                'params'      => ['display_readonly' => '1'],
                'created'     => $now,
                'created_by'  => $userId,
                'modified'    => $now,
                'modified_by' => $userId,
            ]);
            if (!$groupTable->store()) {
                $this->sendMessage(
                    Text::sprintf(
                        'PLG_PWEBOPENGRAPH_INSTALL_FIELD_ERROR_GROUP',
                        join('<br>', $groupTable->getErrors())
                    ),
                    'warning'
                );
                return;
            }
        }

        foreach ($articleFields as $articleField) {
            if (empty($articleField->get('id'))) {
                $articleField->bind([
                    'note'            => Text::_('PLG_PWEBOPENGRAPH_INSTALL_FIELD_NOTE'),
                    'description'     => '',
                    'created_time'    => $now,
                    'created_user_id' => $userId,
                ]);
            }
            $articleField->bind([
                'group_id'      => $groupTable->get('id'),
                'modified_time' => $now,
                'modified_by'   => $userId,
            ]);
            if (!$articleField->store()) {
                $this->sendMessage(
                    Text::sprintf(
                        'PLG_PWEBOPENGRAPH_INSTALL_FIELD_ERROR_FIELD',
                        $articleField->get('name'),
                        join('<br>', $articleField->getErrors())
                    ),
                    'warning'
                );
            }
        }
    }

    /**
     * @param string $name
     *
     * @return Table
     */
    protected function findGraphField(string $name): Table
    {
        $this->sendDebugMessage(__METHOD__);

        $fieldTable = $this->getFieldsTable('Field');

        $fieldTable->load([
            'context' => 'com_content.article',
            'type'    => 'media',
            'name'    => str_replace('_', '-', $name),
        ]);

        if (empty($fieldTable->get('id'))) {
            // Field doesn't exist, initialize defaults
            $title = ucwords(str_replace('_', ' ', $name));
            $name  = str_replace('_', '-', $name);

            $fieldTable->bind([
                'title'       => $title,
                'name'        => $name,
                'label'       => $title,
                'type'        => 'media',
                'state'       => 1,
                'context'     => 'com_content.article',
                'params'      => [
                    'display' => 0,
                ],
                'fieldparams' => ['hint' => ''],
                'language'    => '*',
            ]);
        }

        return $fieldTable;
    }

    /**
     * @param string $name
     *
     * @return Table
     */
    protected function getFieldsTable(string $name): Table
    {
        if (Version::MAJOR_VERSION < 4) {
            Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_fields/tables');
            $table = Table::getInstance($name, 'FieldsTable');

        } else {
            $table = $this->app->bootComponent('com_fields')
                ->getMVCFactory()->createTable($name, 'Administrator');
        }

        return $table;
    }
}
