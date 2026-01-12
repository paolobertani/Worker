INSERT INTO brands_per_category (brand_id,category_id)

SELECT DISTINCT brand_id, category_id

FROM documents

WHERE `status`=""

ORDER BY brand_id ASC, category_id ASC