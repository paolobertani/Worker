SELECT
    `id`,
    `brand_id`,
    `description`,
    `md5`,
    `md_md5`,
    `md_page_index`,
    `pages_count`,
    `lock`
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
    (
        `lock` = "" OR
        `lock` = "worker markdown"
    )
ORDER BY
    CASE WHEN `lock` = "worker markdown" THEN 0 ELSE 1 END ASC,
    `brand_id` ASC,
    `id` ASC
LIMIT
    1
