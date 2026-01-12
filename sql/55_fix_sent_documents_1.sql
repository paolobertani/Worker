UPDATE `sent_documents`

SET `sent_documents`.`status` = 1, `sent_documents`.`reply` = "{rd_doc_loaded}"

WHERE

`sent_documents`.`status` = 0 AND
`sent_documents`.`md5` != "" AND
( SELECT count(`documents`.`id`) from `documents` where `documents`.`md5` = `sent_documents`.`md5` group by `documents`.`md5`) > 0