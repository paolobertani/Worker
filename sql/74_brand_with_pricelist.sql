SELECT

`brands`.`id`,
`brands`.`brand`,
`brands`.`pricelist_received`,
`brands`.`pricelist_description`,
`brands`.`pricelist_uploaded`,
`brands`.`pricelist_uploader`,
`brands`.`pricelist_products_count`

FROM `brands`

WHERE

`brands`.`pricelist_received` = 1

LIMIT 1