<?php

/**
 * AI Boost — PageType
 *
 * The CMS-neutral classification of the current request, produced by
 * PageResolver and carried on PageContext. A string-backed enum so a `match`
 * over it is exhaustive — adding a case forces every future consumer to
 * consider it.
 *
 * Part of the T1 "page-type / entity / indexability / canonical resolver"
 * (docs/analysis/T1-resolver-design.md). Slice S0: this type exists and is
 * resolved, but NO consumer reads it yet — zero behaviour change.
 *
 * @package     AiBoost\Lib\Page
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib\Page;

defined('_JEXEC') or defined('ABSPATH') or die;

enum PageType: string
{
    case HOMEPAGE        = 'homepage';
    case ARTICLE         = 'article';
    case CATEGORY        = 'category';
    case FEATURED        = 'featured';
    case CONTACT         = 'contact';
    case TAG             = 'tag';
    case SEARCH          = 'search';
    case MENU_OTHER      = 'menu_other';
    case COMPONENT_OTHER = 'component_other';
    case UNKNOWN         = 'unknown';
}
