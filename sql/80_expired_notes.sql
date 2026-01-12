SELECT
    `notes_per_document_per_group`.`id`,
    `notes_per_document_per_group`.`user_id`,
    `notes_per_document_per_group`.`notes`,
    `documents`.`description`,
    `documents`.`public_id`,
    `documents`.`brand_id`,
    `documents`.`category_id`,
    `brands`.`brand`,
    `documents`.`expire`,
    `users`.`firstname`,
    `users`.`email`,
    TRIM( CONCAT( `users`.`firstname`, " ", `users`.`surname` ) ) AS `fullname`
FROM
    `notes_per_document_per_group`,
    `documents`,
    `brands`,
    `users`
WHERE
    `expire_notified`=0 AND
    TRIM(`notes`) != "" AND
    `documents`.`expire` < LEFT(CURDATE(),7) AND
    `documents`.`id` = `notes_per_document_per_group`.`document_id` AND
    `brands`.`id` = `documents`.`brand_id` AND
    `users`.`id` = `notes_per_document_per_group`.`user_id`
