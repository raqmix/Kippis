-- Remap products from newly created categories (external_source='local') 
-- to existing categories (external_source='foodics') by matching category names
-- and delete the newly created categories

-- Step 1: Create a temporary table to store category mappings
-- New categories have external_source='local', existing ones have external_source='foodics'
CREATE TEMPORARY TABLE IF NOT EXISTS category_remap AS
SELECT DISTINCT
    new_cat.id AS new_category_id,
    MIN(existing_cat.id) AS existing_category_id,
    JSON_UNQUOTE(JSON_EXTRACT(new_cat.name_json, '$.en')) AS category_name
FROM categories AS new_cat
INNER JOIN products AS p ON p.category_id = new_cat.id
INNER JOIN categories AS existing_cat ON 
    LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(new_cat.name_json, '$.en')))) = 
    LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(existing_cat.name_json, '$.en'))))
WHERE 
    p.external_source = 'foodics'
    AND new_cat.external_source = 'local'
    AND existing_cat.external_source = 'foodics'
    AND new_cat.id != existing_cat.id
GROUP BY new_cat.id, JSON_UNQUOTE(JSON_EXTRACT(new_cat.name_json, '$.en'));

-- Step 2: Show what will be remapped (for verification)
SELECT 
    remap.new_category_id,
    remap.existing_category_id,
    remap.category_name,
    (SELECT COUNT(*) FROM products WHERE category_id = remap.new_category_id) AS products_to_remap
FROM category_remap AS remap
ORDER BY remap.category_name, remap.new_category_id;

-- Step 3: Update products to use existing category IDs
UPDATE products AS p
INNER JOIN category_remap AS remap ON p.category_id = remap.new_category_id
SET p.category_id = remap.existing_category_id,
    p.updated_at = NOW()
WHERE p.external_source = 'foodics';

-- Step 4: Delete the duplicate categories (only if they have no products left)
-- Note: MySQL doesn't allow DELETE with subquery on same table, so we use a temporary table
CREATE TEMPORARY TABLE IF NOT EXISTS categories_to_delete AS
SELECT remap.new_category_id
FROM category_remap AS remap
WHERE NOT EXISTS (
    SELECT 1 FROM products WHERE category_id = remap.new_category_id
);

DELETE c FROM categories c
INNER JOIN categories_to_delete ctd ON c.id = ctd.new_category_id;

DROP TEMPORARY TABLE IF EXISTS categories_to_delete;

-- Step 5: Clean up temporary table
DROP TEMPORARY TABLE IF EXISTS category_remap;
