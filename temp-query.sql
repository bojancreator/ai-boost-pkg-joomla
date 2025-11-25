SELECT 
    a.id AS article_id,
    a.title AS article_title,
    f.name AS field_name,
    fv.value AS field_value
FROM 
    jos_content a
    CROSS JOIN jos_fields f
    LEFT JOIN jos_fields_values fv ON fv.field_id = f.id AND fv.item_id = a.id
WHERE 
    f.name IN ('custom_og_image', 'custom_og_title', 'custom_og_description')
    AND a.id IN (214, 216)
ORDER BY 
    a.id, f.name;
