UPDATE `brands`

SET

`brands`.`pricelist_building_transcoder` = 0

WHERE `brands`.`id` = {{brand_id}}