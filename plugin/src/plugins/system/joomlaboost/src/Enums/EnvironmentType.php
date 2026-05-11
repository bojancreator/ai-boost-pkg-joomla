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

    /**
     * Get a human-readable label for this environment
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Production => 'Production',
            self::Staging    => 'Staging',
            self::Local      => 'Local',
        };
    }

    /**
     * Get robots.txt rules for this environment
     *
     * @return array<int, string>
     */
    public function getRobotsRules(): array
    {
        if (!$this->isProduction()) {
            return [
                '# AI Boost for Joomla - Robots.txt - ' . strtoupper($this->value) . ' ENVIRONMENT',
                '',
                'User-agent: *',
                'Disallow: /',
            ];
        }

        return [
            '# AI Boost for Joomla - Robots.txt - PRODUCTION ENVIRONMENT',
            '',
            'User-agent: *',
            'Allow: /',
            '',
            '# Disallow admin and system folders',
            'Disallow: /administrator/',
            'Disallow: /api/',
            'Disallow: /bin/',
            'Disallow: /cache/',
            'Disallow: /cli/',
            'Disallow: /components/',
            'Disallow: /includes/',
            'Disallow: /installation/',
            'Disallow: /language/',
            'Disallow: /layouts/',
            'Disallow: /libraries/',
            'Disallow: /logs/',
            'Disallow: /modules/',
            'Disallow: /plugins/',
            'Disallow: /tmp/',
            '',
            '# Allow public assets',
            'Allow: /templates/',
            'Allow: /media/',
            'Allow: /images/',
            '',
            '# -------------------------------------------------------',
            '# AI Crawlers — GEO (Generative Engine Optimization)',
            '# Explicitly allow AI systems to index and cite this site.',
            '# -------------------------------------------------------',
            '',
            '# OpenAI (ChatGPT, GPT-4o search)',
            'User-agent: GPTBot',
            'Allow: /',
            '',
            '# Anthropic (Claude)',
            'User-agent: ClaudeBot',
            'Allow: /',
            '',
            '# Perplexity AI',
            'User-agent: PerplexityBot',
            'Allow: /',
            '',
            '# Google AI (Gemini, AI Overviews)',
            'User-agent: Google-Extended',
            'Allow: /',
            '',
            '# Cohere AI',
            'User-agent: cohere-ai',
            'Allow: /',
            '',
            '# Meta AI (Llama)',
            'User-agent: FacebookBot',
            'Allow: /',
            '',
            '# Amazon Alexa',
            'User-agent: Amazonbot',
            'Allow: /',
            '',
            '# Apple Applebot (Siri, Spotlight)',
            'User-agent: Applebot',
            'Allow: /',
            '',
            '# Common Crawl (used for AI training datasets)',
            'User-agent: CCBot',
            'Allow: /',
        ];
    }
}
