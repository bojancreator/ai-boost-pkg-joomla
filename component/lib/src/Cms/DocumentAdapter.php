<?php
/**
 * AI Boost — DocumentAdapter interface
 *
 * Abstracts the host CMS's page-document object (the thing that owns
 * <head> metadata, custom tags, title, description). On Joomla this
 * wraps Factory::getDocument(); on WordPress it will be a thin shim
 * over wp_head / wp_title / add_action('wp_head', …).
 *
 * Most current lib/ services rewrite the rendered HTML body directly
 * (HeadBlockBuilder / BodyBlockBuilder do byte-safe substring splices
 * around </head> and </body>) precisely because they need to win against
 * template-injected tags. This adapter exists for the smaller set of
 * call sites that read or set document-level metadata directly, and as
 * the WP-port hook point for plugins that prefer the native Document
 * API over body rewriting.
 *
 * @package     AiBoost\Lib\Cms
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Cms;

defined('_JEXEC') or defined('ABSPATH') or die;

interface DocumentAdapter
{
    /** Current page title, or '' if unavailable. */
    public function getTitle(): string;

    /** Overwrite the page title. */
    public function setTitle(string $title): void;

    /**
     * Read a head metadata value by name (e.g. 'description', 'robots').
     * Returns '' when absent.
     */
    public function getMetaData(string $name): string;

    /**
     * Write a head metadata value. $httpEquiv mirrors Joomla's flag for
     * http-equiv vs name= attribute.
     */
    public function setMetaData(string $name, string $content, bool $httpEquiv = false): void;

    /**
     * Append a raw <head> tag (e.g. JSON-LD <script>). WP impl will hook
     * the same payload onto wp_head with priority 99 so it lands inside
     * the consolidated AI Boost block.
     */
    public function addCustomTag(string $html): void;
}
