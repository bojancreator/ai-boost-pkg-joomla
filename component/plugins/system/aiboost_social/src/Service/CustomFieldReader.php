<?php
/**
 * AI Boost — CustomFieldReader (Pro-gated)
 *
 * Reads AI Boost–specific Joomla custom field values for a given article.
 * Lives in the FREE `aiboost_social` plugin but is a Pro-only feature: the
 * build strips this class from the Free package (FREE_EXCLUDE), and the
 * decorator only instantiates it when PluginRegistry::isProActive() — so the
 * per-article OG overrides (incl. the Falang translation overlay) are unlocked
 * by Pro activation, never present on a Free install. (Relocated here during
 * the "Pro replaces Free" collapse; the old aiboost_social_pro home is retired.)
 *
 * Looks for custom fields named exactly:
 *   aiboost_og_title        → overrides og:title for this article
 *   aiboost_og_description  → overrides og:description for this article
 *   aiboost_og_image        → overrides og:image for this article (absolute URL)
 *   aiboost_og_type         → overrides og:type (article, website, video.movie, music.song, product)
 *   aiboost_og_video        → adds og:video URL (for video content preview cards)
 *   aiboost_twitter_card    → overrides twitter:card type (summary, summary_large_image)
 *
 * Falang bridge:
 *   If the Falang extension is installed (#__falang_content table exists),
 *   translated values for the current active language are used instead of the
 *   default field values.  Falls back to default field values if no translation
 *   is found for the current language.
 *
 * @package     AiBoost\Plugin\System\AiBoostSocial
 * @copyright   (C) 2025 AI Boost (aiboostnow.com). All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace AiBoost\Plugin\System\AiBoostSocial\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

class CustomFieldReader
{
    /** Target custom field names this plugin reads. */
    private const FIELD_NAMES = [
        'aiboost_og_title',
        'aiboost_og_description',
        'aiboost_og_image',
        'aiboost_og_type',
        'aiboost_og_video',
        'aiboost_twitter_card',
    ];

    public function __construct(
        private readonly DatabaseInterface $db,
    ) {}

    /**
     * Read AI Boost custom field values for an article.
     *
     * @return array{og_title:string, og_description:string, og_image:string, og_type:string, og_video:string, twitter_card:string}
     */
    public function read(int $articleId, string $langTag = 'en-GB'): array
    {
        $result = [
            'og_title'       => '',
            'og_description' => '',
            'og_image'       => '',
            'og_type'        => '',
            'og_video'       => '',
            'twitter_card'   => '',
        ];

        if ($articleId <= 0) {
            return $result;
        }

        try {
            $db = $this->db;

            $query = $db->getQuery(true)
                ->select([
                    $db->quoteName('f.name'),
                    $db->quoteName('fv.value'),
                    $db->quoteName('f.id', 'field_id'),
                ])
                ->from($db->quoteName('#__fields', 'f'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__fields_values', 'fv')
                    . ' ON ' . $db->quoteName('fv.field_id') . ' = ' . $db->quoteName('f.id')
                    . ' AND ' . $db->quoteName('fv.item_id') . ' = ' . $db->quote((string) $articleId)
                )
                ->where($db->quoteName('f.context') . ' = ' . $db->quote('com_content.article'))
                ->where($db->quoteName('f.name') . ' IN ('
                    . implode(',', array_map([$db, 'quote'], self::FIELD_NAMES))
                    . ')')
                ->where($db->quoteName('f.state') . ' = 1');

            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: [];

            $fieldIdMap = [];
            foreach ($rows as $row) {
                $fieldIdMap[(int) $row->field_id] = (string) $row->name;
                $this->mapRowToResult($result, (string) $row->name, (string) ($row->value ?? ''));
            }

            if (!empty($fieldIdMap) && $this->falangTableExists($db)) {
                $this->overlayFalangTranslations($db, $articleId, $langTag, $fieldIdMap, $result);
            }
        } catch (\Throwable $e) {
            // Silent fallback — never break the page for OG tags
        }

        return $result;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function mapRowToResult(array &$result, string $name, string $value): void
    {
        if ($value === '') {
            return;
        }
        match ($name) {
            'aiboost_og_title'       => $result['og_title']       = $value,
            'aiboost_og_description' => $result['og_description']  = $value,
            'aiboost_og_image'       => $result['og_image']        = $value,
            'aiboost_og_type'        => $result['og_type']         = $value,
            'aiboost_og_video'       => $result['og_video']        = $value,
            'aiboost_twitter_card'   => $result['twitter_card']    = $value,
            default                  => null,
        };
    }

    private function falangTableExists(DatabaseInterface $db): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        try {
            $prefix = $db->getPrefix();
            $tables = $db->getTableList();
            $exists = in_array($prefix . 'falang_content', $tables, true);
        } catch (\Throwable $e) {
            $exists = false;
        }
        return $exists;
    }

    /**
     * @param  array<int,string> $fieldIdMap  field_id → field_name
     */
    private function overlayFalangTranslations(
        DatabaseInterface $db,
        int $articleId,
        string $langTag,
        array $fieldIdMap,
        array &$result
    ): void {
        try {
            $langQuery = $db->getQuery(true)
                ->select($db->quoteName('lang_id'))
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('lang_code') . ' = ' . $db->quote($langTag))
                ->where($db->quoteName('published') . ' = 1');
            $db->setQuery($langQuery, 0, 1);
            $falangLangId = (int) ($db->loadResult() ?? 0);

            if ($falangLangId === 0) {
                return;
            }

            $fieldIds = array_keys($fieldIdMap);
            $query    = $db->getQuery(true)
                ->select([
                    $db->quoteName('fv.field_id'),
                    $db->quoteName('fc.value', 'translated_value'),
                ])
                ->from($db->quoteName('#__fields_values', 'fv'))
                ->join(
                    'INNER',
                    $db->quoteName('#__falang_content', 'fc')
                    . ' ON ' . $db->quoteName('fc.reference_id') . ' = ' . $db->quoteName('fv.id')
                    . ' AND ' . $db->quoteName('fc.reference_table') . ' = ' . $db->quote('fields_values')
                    . ' AND ' . $db->quoteName('fc.reference_field') . ' = ' . $db->quote('value')
                    . ' AND ' . $db->quoteName('fc.language_id') . ' = ' . $falangLangId
                    . ' AND ' . $db->quoteName('fc.published') . ' = 1'
                )
                ->where($db->quoteName('fv.item_id') . ' = ' . $db->quote((string) $articleId))
                ->where($db->quoteName('fv.field_id') . ' IN ('
                    . implode(',', array_map('intval', $fieldIds)) . ')');

            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: [];

            foreach ($rows as $row) {
                $fieldId    = (int) $row->field_id;
                $translated = (string) ($row->translated_value ?? '');
                if ($translated === '' || !isset($fieldIdMap[$fieldId])) {
                    continue;
                }
                $this->mapRowToResult($result, $fieldIdMap[$fieldId], $translated);
            }
        } catch (\Throwable $e) {
            // Silent fallback
        }
    }
}
