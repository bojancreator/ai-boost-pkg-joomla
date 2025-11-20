-- SQL Query to check JoomlaBoost plugin configuration
-- Execute this in phpMyAdmin or Joomla's Database section

-- 1. Check if plugin exists and is enabled
SELECT
    extension_id,
    name,
    enabled,
    params
FROM #__extensions
WHERE type = 'plugin'
  AND folder = 'system'
  AND element = 'joomlaboost';

-- 2. Extract specific config values (MySQL 5.7.8+)
SELECT
    extension_id,
    enabled,
    JSON_EXTRACT(params, '$.org_name') AS org_name,
    JSON_EXTRACT(params, '$.org_logo') AS org_logo,
    JSON_EXTRACT(params, '$.og_site_name') AS og_site_name,
    JSON_EXTRACT(params, '$.og_image') AS og_image,
    JSON_EXTRACT(params, '$.schema_enabled') AS schema_enabled,
    JSON_EXTRACT(params, '$.enable_caching') AS enable_caching
FROM #__extensions
WHERE type = 'plugin'
  AND folder = 'system'
  AND element = 'joomlaboost';

-- 3. If MySQL version doesn't support JSON_EXTRACT, just view raw params:
SELECT params FROM #__extensions WHERE element = 'joomlaboost' AND folder = 'system';
