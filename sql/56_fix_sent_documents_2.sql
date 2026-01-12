UPDATE `sent_documents`

SET `sent_documents`.`status` = 1, `sent_documents`.`reply` = "{rd_doc_loaded}"

WHERE

`sent_documents`.`md5` = "" AND
`sent_documents`.`status` = 0 AND
`sent_documents`.`in_inbox` = 0