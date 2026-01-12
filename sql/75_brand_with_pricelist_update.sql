UPDATE

`brands`

SET

`brands`.`pricelist_products_count` = {{count}},
`brands`.`pricelist_received` = 0

WHERE

`brands`.`id` = {{brand_id}}