<?php
/**
 * AI Boost — RobotsPreviewField
 *
 * Custom Joomla form field that renders a "Preview /robots.txt" button in
 * the plugin params panel. Clicking it opens the live /robots.txt output
 * in a new browser tab so admins can verify the generated content without
 * leaving the plugin settings page.
 *
 * Registered via addfieldprefix="AiBoost\Plugin\System\AiBoostAeo\Field"
 * on the <fields> element in aiboost_aeo.xml.
 *
 * @package     AiBoost\Plugin\System\AiBoostAeo
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostAeo\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class RobotsPreviewField extends FormField
{
    protected $type = 'RobotsPreview';

    /**
     * Renders a button that opens /robots.txt in a new tab.
     */
    protected function getInput(): string
    {
        $url = rtrim(Uri::root(), '/') . '/robots.txt';

        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"'
            . ' target="_blank"'
            . ' rel="noopener noreferrer"'
            . ' class="btn btn-sm btn-outline-secondary">'
            . '<span class="icon-eye" aria-hidden="true"></span> '
            . Text::_('PLG_SYSTEM_AIBOOST_AEO_ROBOTS_PREVIEW_BTN_TEXT')
            . '</a>';
    }

    /**
     * No label needed — the button is self-explanatory.
     */
    protected function getLabel(): string
    {
        return '';
    }
}
