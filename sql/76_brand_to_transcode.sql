SELECT

`brands`.`id` as `brand_id`,
`brands`.`brand_extended` as `brand`

FROM `brands`

WHERE

`brands`.`pricelist_products_count` > 0 AND
`brands`.`pricelist_building_transcoder` = 1

LIMIT 1