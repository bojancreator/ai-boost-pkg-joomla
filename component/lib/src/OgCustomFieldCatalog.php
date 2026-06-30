<?php
/**
 * AI Boost — OG custom-field catalogue (single source of truth)
 *
 * The 6 AI Boost per-article OpenGraph/Twitter Joomla custom fields
 * (`com_content.article`, group "AI Boost — OpenGraph"). This is the ONE
 * definition consumed by both writers (DRY — order 0017, finding G9):
 *   - package/pkg_script.php::ensureOgCustomFields()  — installer auto-create
 *   - SettingsController::repairOgFields()            — "Create / Repair" button
 *
 * `fieldparams` is returned as a PHP array; the installer passes it straight to
 * upsertField(), the controller json_encode()s it. Keep the field NAMES in sync
 * with CustomFieldReader::FIELD_NAMES (the runtime reader).
 *
 * @package     AiBoost\Lib
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

declare(strict_types=1);

namespace AiBoost\Lib;

defined('_JEXEC') or die;

final class OgCustomFieldCatalog
{
    /** The Joomla field context these fields are attached to. */
    public const CONTEXT = 'com_content.article';

    /** The Joomla custom-field group title. */
    public const GROUP_TITLE = 'AI Boost — OpenGraph';

    /**
     * The 6 field definitions, in display order.
     *
     * @return list<array{name:string,title:string,type:string,description:string,fieldparams:array<string,mixed>,ordering:int}>
     */
    public static function fields(): array
    {
        return [
            [
                'name'        => 'aiboost_og_title',
                'title'       => 'AI Boost — OG Title',
                'type'        => 'text',
                'description' => 'Override the og:title meta tag. Leave empty to use the article title.',
                'fieldparams' => [],
                'ordering'    => 1,
            ],
            [
                'name'        => 'aiboost_og_description',
                'title'       => 'AI Boost — OG Description',
                'type'        => 'textarea',
                'description' => 'Override the og:description meta tag for this article.',
                'fieldparams' => ['rows' => '3', 'cols' => ''],
                'ordering'    => 2,
            ],
            [
                'name'        => 'aiboost_og_image',
                'title'       => 'AI Boost — OG Image',
                'type'        => 'media',
                'description' => 'Override og:image. Recommended size: 1200x630 px.',
                'fieldparams' => ['directory' => '', 'preview' => 'true'],
                'ordering'    => 3,
            ],
            [
                'name'        => 'aiboost_og_type',
                'title'       => 'AI Boost — OG Type',
                'type'        => 'list',
                'description' => 'Override the og:type meta tag. Defaults to "article" for article pages.',
                'fieldparams' => ['options' => [
                    ['name' => '— default (article) —', 'value' => ''],
                    ['name' => 'Article',               'value' => 'article'],
                    ['name' => 'Website',               'value' => 'website'],
                    ['name' => 'Video',                 'value' => 'video.movie'],
                    ['name' => 'Music',                 'value' => 'music.song'],
                    ['name' => 'Product',               'value' => 'product'],
                ]],
                'ordering'    => 4,
            ],
            [
                'name'        => 'aiboost_og_video',
                'title'       => 'AI Boost — OG Video URL',
                'type'        => 'url',
                'description' => 'Optional og:video URL. Enables video preview cards on Facebook and LinkedIn.',
                'fieldparams' => [],
                'ordering'    => 5,
            ],
            [
                'name'        => 'aiboost_twitter_card',
                'title'       => 'AI Boost — Twitter Card',
                'type'        => 'list',
                'description' => 'Override the twitter:card type. Defaults to summary_large_image.',
                'fieldparams' => ['options' => [
                    ['name' => '— default (summary_large_image) —', 'value' => ''],
                    ['name' => 'Summary Large Image',               'value' => 'summary_large_image'],
                    ['name' => 'Summary',                           'value' => 'summary'],
                ]],
                'ordering'    => 6,
            ],
        ];
    }

    /**
     * Same defs with `fieldparams` pre-encoded as JSON strings — for callers
     * (e.g. SettingsController) that write the raw `#__fields.fieldparams`
     * column directly. Empty params encode as `{}` (object), not `[]`.
     *
     * @return list<array{name:string,title:string,type:string,description:string,fieldparams:string,ordering:int}>
     */
    public static function fieldsWithJsonParams(): array
    {
        return array_map(
            static function (array $f): array {
                $f['fieldparams'] = $f['fieldparams'] === []
                    ? '{}'
                    : (string) json_encode($f['fieldparams']);
                return $f;
            },
            self::fields()
        );
    }
}
