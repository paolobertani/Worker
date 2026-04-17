UPDATE
    `documents`
SET
    `lock` = {{lock}},
    `md_md5` = {{md_md5}},
    `md_page_index` = {{md_page_index}}
WHERE
    `id` = {{id}}