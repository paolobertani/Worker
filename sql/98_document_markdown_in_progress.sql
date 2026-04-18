SELECT
    `id`
FROM
    `documents`
WHERE
    `pdf` = 1 AND
    `primary` = 1 AND
    `type` = "L" AND
    `expire` = "UNDEFINED" AND
    `status` = "" AND
    `worker_cmd` = "" AND
    `md5` != "" AND
    `pages_count` > 0 AND
    `md_md5` != `md5` AND
    `brand_id` IN ({{::brand_ids}}) AND
    `lock` = "worker markdown"
LIMIT
    1
