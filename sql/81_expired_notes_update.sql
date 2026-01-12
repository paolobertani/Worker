UPDATE
    `notes_per_document_per_group`,
    `documents`
SET
    `expire_notified`=1
WHERE
    `expire_notified`=0 AND
    TRIM(`notes`) != "" AND
    `documents`.`expire` < LEFT(CURDATE(),7) AND
    `documents`.`id` = `notes_per_document_per_group`.`document_id`