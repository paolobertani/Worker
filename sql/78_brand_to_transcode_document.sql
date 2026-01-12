SELECT

`documents`.`id` AS `document_id`,
`documents`.`pages_count`,
`documents`.`description`

FROM `documents`

WHERE `documents`.`public_id` = {{public_id}}