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
    (
        `md` = 0 OR
        `md_md5` != `md5` OR
        `md_page_index` < `pages_count`
    ) AND
    `brand_id` IN ({{::brand_ids}}) AND
    `lock` = "worker markdown"
LIMIT
    1
