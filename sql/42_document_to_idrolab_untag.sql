SELECT

`documents`.`id`, `brand_id`, `brand`, `documents`.`description`,
`worker_cmd`, `cover_width`, `cover_height`, `pdf`, `cache_size`, `pdf_size`, `pdfff_size`, `status`, `expire`,
`slow_pages`, `slow_milliseconds`, `has_slow_pages`, `idrolab_status`, `pdf_modified`, `pdf_created`, `has_outlines`, `pages_count`,
`md5`, `cachev2_md5`, `cachev2_pages`, `pagescolor_md5`, `qr_md5`, `qr_count`, `covers_md5`, `meta_md5`

FROM

`documents`, `brands`

WHERE

`documents`.`brand_id` = `brands`.`id` AND

`lock` = "" AND `status` = "" AND `pdf` = 1 AND `idrolab_status` = 1 AND `brands`.`idrolab_productlist` = 0

ORDER BY `id` DESC

LIMIT 1