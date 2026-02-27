<?php

/**
 * Environment Type Enum for JoomlaBoost
 *
 * @package     JoomlaBoost
 * @subpackage  Plugin.System.Enums
 * @since       4.0
 */

declare(strict_types=1);

namespace JoomlaBoost\Plugin\System\JoomlaBoost\Enums;

\defined('_JEXEC') or die;

/**
 * Represents the deployment environment type
 */
enum EnvironmentType: string
{
    case Production = 'production';
    case Staging    = 'staging';
    case Local      = 'local';

    /**
     * Detect environment from a domain string
     */
    public static function detectFromDomain(string $domain): self
    {
        $lower = strtolower($domain);

        $stagingKeywords = ['staging', 'stage', 'dev', 'test', 'localhost', '127.0.0.1', '.local'];

        foreach ($stagingKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return self::Staging;
            }
        }

        return self::Production;
    }

    /**
     * Whether search engines should be allowed to index this environment
     */
    public function allowSearchEngines(): bool
    {
        return $this === self::Production;
    }

    /**
     * Whether this is a production environment
     */
    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
