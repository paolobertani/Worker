SELECT

`id`

FROM `documents`

WHERE

`expire` <= {{old_expire}}
AND `status` = ""
AND `lock` = ""
AND `dont_cache` = 0
AND NOT EXISTS (
    SELECT 1
    FROM `usage_per_document`
    WHERE `when` > {{ignored_from}}
    AND `usage_per_document`.`document_id` = `documents`.`id`
)