<?php
/**
 * AI Boost — JoomlaApplicationAdapter
 *
 * Joomla implementation of ApplicationAdapter. Wraps CMSApplication
 * (passed in) for body manipulation, and falls back to Factory /
 * Uri::getInstance for host detection when no application was injected.
 *
 * @package     AiBoost\Lib\Cms\Joomla
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms\Joomla;

defined('_JEXEC') or die;

use AiBoost\Lib\Cms\ApplicationAdapter;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

final class JoomlaApplicationAdapter implements ApplicationAdapter
{
    private ?CMSApplication $app;

    public function __construct(?CMSApplication $app = null)
    {
        $this->app = $app;
    }

    private function app(): ?CMSApplication
    {
        if ($this->app !== null) {
            return $this->app;
        }
        try {
            $resolved = Factory::getApplication();
            return $resolved instanceof CMSApplication ? $resolved : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isSite(): bool
    {
        $app = $this->app();
        if ($app === null) {
            return false;
        }
        try {
            return $app->isClient('site');
        } catch (\Throwable) {
            return false;
        }
    }

    public function getBody(): string
    {
        $app = $this->app();
        return $app !== null ? (string) $app->getBody() : '';
    }

    public function setBody(string $body): void
    {
        $app = $this->app();
        if ($app !== null) {
            $app->setBody($body);
        }
    }

    public function getHost(): string
    {
        try {
            return (string) Uri::getInstance()->getHost();
        } catch (\Throwable) {
            return '';
        }
    }
}
