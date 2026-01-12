SELECT

`id`,
`release`, `expire`

FROM `documents`

WHERE
    `release` <= {{old_release}}
    AND `expire` = "UNDEFINED"
    AND `status` = ""
    AND `lock` = ""
    AND NOT EXISTS (
        SELECT 1
        FROM `usage_per_document`
        WHERE `when` > {{ignored_from}}
        AND `usage_per_document`.`document_id` = `documents`.`id`
    )