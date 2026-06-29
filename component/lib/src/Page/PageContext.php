<?php

/**
 * AI Boost — PageContext
 *
 * The single value object that answers "where am I?" for the current request:
 * page type, the primary (CMS-neutral) entity, the one homepage truth,
 * language facts, canonical URL and the single indexability verdict.
 *
 * Produced once per request by PageResolver. Part of T1 slice S0
 * (docs/analysis/T1-resolver-design.md §2.1) — it EXISTS and is resolvable,
 * but no consumer reads it yet, so the rendered site is unchanged. Consumers
 * migrate onto it one behaviour-preserving slice at a time (S2+).
 *
 * `entityKind` is the CMS-neutral projection — Joomla's `com_content.article`
 * and a future WordPress `post` both map to `entityKind='article'`. That is
 * what makes the eventual WordPress port possible.
 *
 * @package     AiBoost\Lib\Page
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Page;

defined('_JEXEC') or defined('ABSPATH') or die;

final class PageContext
{
    public function __construct(
        public readonly PageType $type,
        /** CMS-neutral entity kind: article | category | contact | tag | site | '' */
        public readonly string $entityKind,
        /** Primary entity id (0 when none). */
        public readonly int $entityId,
        /** Raw request primitives, preserved for edge cases + debugging. */
        public readonly string $option,
        public readonly string $view,
        public readonly int $rawId,
        /** The ONE homepage truth (active menu item `home=1`). */
        public readonly bool $isHomepage,
        /** Active request language tag (e.g. 'de-DE'). */
        public readonly string $language,
        /** Site default CONTENT language (com_languages `site`) — the SEO/x-default default. */
        public readonly string $siteDefaultLanguage,
        /** Global config default language (rarely the SEO-correct one; kept for back-compat). */
        public readonly string $globalDefaultLanguage,
        /** Resolved canonical URL for this page. */
        public readonly string $canonical,
        /** The SINGLE indexability verdict every emitter consults. */
        public readonly bool $indexable,
        /** Why not indexable ('' when indexable) — for Health + debugging. */
        public readonly string $noindexReason,
    ) {
    }

    /**
     * Return a copy of this context with a different canonical URL (immutable).
     *
     * T1·S5: the PageResolver builds the base context with the bare
     * scheme://host/path canonical, then — only for the canonical consumer that
     * threads in a URL map — applies a map hit by producing a new context via
     * this wither. The base stays untouched so consumers that never pass a map
     * always see the bare URL.
     */
    public function withCanonical(string $canonical): self
    {
        return new self(
            type:                  $this->type,
            entityKind:            $this->entityKind,
            entityId:              $this->entityId,
            option:                $this->option,
            view:                  $this->view,
            rawId:                 $this->rawId,
            isHomepage:            $this->isHomepage,
            language:              $this->language,
            siteDefaultLanguage:   $this->siteDefaultLanguage,
            globalDefaultLanguage: $this->globalDefaultLanguage,
            canonical:             $canonical,
            indexable:             $this->indexable,
            noindexReason:         $this->noindexReason,
        );
    }

    /** True only on a real article page with a positive id. */
    public function isArticle(): bool
    {
        return $this->type === PageType::ARTICLE && $this->entityId > 0;
    }

    public function isCategory(): bool
    {
        return $this->type === PageType::CATEGORY;
    }

    public function isContact(): bool
    {
        return $this->type === PageType::CONTACT;
    }

    public function isTag(): bool
    {
        return $this->type === PageType::TAG;
    }

    /** A single, addressable content entity (article/category/contact/tag), not a list/system page. */
    public function isContentEntity(): bool
    {
        return $this->entityId > 0 && $this->entityKind !== '' && $this->entityKind !== 'site';
    }
}
