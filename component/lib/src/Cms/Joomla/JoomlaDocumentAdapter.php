<?php
/**
 * AI Boost — JoomlaDocumentAdapter
 *
 * Joomla implementation of DocumentAdapter. Wraps Factory::getDocument()
 * with the same defensive try/catch envelope used throughout lib/.
 *
 * @package     AiBoost\Lib\Cms\Joomla
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Joomla;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\DocumentAdapter;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;

final class JoomlaDocumentAdapter implements DocumentAdapter
{
    private function doc(): ?HtmlDocument
    {
        try {
            $d = Factory::getDocument();
            return $d instanceof HtmlDocument ? $d : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getTitle(): string
    {
        $d = $this->doc();
        return $d !== null ? (string) $d->getTitle() : '';
    }

    public function setTitle(string $title): void
    {
        $d = $this->doc();
        if ($d !== null) {
            $d->setTitle($title);
        }
    }

    public function getMetaData(string $name): string
    {
        $d = $this->doc();
        if ($d === null) {
            return '';
        }
        try {
            return (string) $d->getMetaData($name);
        } catch (\Throwable) {
            return '';
        }
    }

    public function setMetaData(string $name, string $content, bool $httpEquiv = false): void
    {
        $d = $this->doc();
        if ($d === null) {
            return;
        }
        try {
            $d->setMetaData($name, $content, $httpEquiv ? 'http-equiv' : 'name');
        } catch (\Throwable) {
            // legacy signature
            $d->setMetaData($name, $content);
        }
    }

    public function addCustomTag(string $html): void
    {
        $d = $this->doc();
        if ($d !== null && method_exists($d, 'addCustomTag')) {
            $d->addCustomTag($html);
        }
    }
}
