-- Provera svih instaliranih i aktivnih ekstenzija
SELECT
    e.extension_id,
    e.name,
    e.type,
    e.element,
    e.enabled,
    e.manifest_cache
FROM
    #__extensions e
WHERE
    e.enabled = 1
    AND e.type IN ('plugin', 'component', 'module')
ORDER BY
    e.type, e.name;

-- Provera nedavno instaliranih ekstenzija
SELECT
    e.extension_id,
    e.name,
    e.type,
    e.element,
    e.enabled
FROM
    #__extensions e
ORDER BY
    e.extension_id DESC
LIMIT 20;
