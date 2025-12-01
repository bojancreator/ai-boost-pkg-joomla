<?php

/**
 * Analytics Service for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Services
 * @since       Joomla 4.0, PHP 8.1+
 * @author      JoomlaBoost Team
 * @copyright   (C) 2025 JoomlaBoost. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Services;

use Joomla\CMS\Document\HtmlDocument;

/**
 * Analytics Service
 */
class AnalyticsService extends AbstractService
{
    protected function getServiceKey(): string
    {
        return 'enable_analytics';
    }

    /**
     * Inject all enabled analytics scripts
     */
    public function injectAnalytics(HtmlDocument $document): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if ($this->params->get('enable_ga4', 0)) {
            $this->injectGA4($document);
        }

        if ($this->params->get('enable_gtm', 0)) {
            $this->injectGTM($document);
        }
    }

    /**
     * Inject Google Analytics 4 (GA4)
     */
    private function injectGA4(HtmlDocument $document): void
    {
        $measurementId = $this->params->get('ga4_measurement_id', '');
        if (empty($measurementId)) {
            $this->logDebug('GA4: No measurement ID provided');
            return;
        }

        $ga4Script = "\n" .
            '<!-- Google Analytics 4 -->' . "\n" .
            '<script async src="https://www.googletagmanager.com/gtag/js?id=' . $measurementId . '"></script>' . "\n" .
            '<script>' . "\n" .
            '  window.dataLayer = window.dataLayer || [];' . "\n" .
            '  function gtag(){dataLayer.push(arguments);}' . "\n" .
            '  gtag(\'js\', new Date());' . "\n" .
            '  gtag(\'config\', \'' . $measurementId . '\');' . "\n" .
            '</script>';

        $document->addCustomTag($ga4Script);
        $this->logDebug('Added Google Analytics 4 tracking for: ' . $measurementId);
    }

    /**
     * Inject Google Tag Manager (GTM)
     */
    private function injectGTM(HtmlDocument $document): void
    {
        $containerId = $this->params->get('gtm_container_id', '');
        if (empty($containerId)) {
            $this->logDebug('GTM: No container ID provided');
            return;
        }

        $gtmHead = "\n" .
            '<!-- Google Tag Manager -->' . "\n" .
            '<script>' . "\n" .
            '(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':' . "\n" .
            'new Date().getTime(),event:\'gtm.js\'});var f=d.getElementsByTagName(s)[0],' . "\n" .
            'j=d.createElement(s),dl=l!=\'dataLayer\'?\'&l=\'+l:\'\';j.async=true;j.src=' . "\n" .
            '\'https://www.googletagmanager.com/gtm.js?id=\'+i+dl;f.parentNode.insertBefore(j,f);' . "\n" .
            '})(window,document,\'script\',\'dataLayer\',\'' . $containerId . '\');' . "\n" .
            '</script>' . "\n" .
            '<!-- End Google Tag Manager -->';

        $document->addCustomTag($gtmHead);
        $this->logDebug('Added Google Tag Manager tracking for: ' . $containerId);
    }
}
