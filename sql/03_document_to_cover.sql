SELECT

`id`,
`worker_cmd`, `cover_width`, `cover_height`, `pdf`, `cache_size`, `pdf_size`, `pdfff_size`, `status`, `expire`,
`slow_pages`, `slow_milliseconds`, `has_slow_pages`, `idrolab_status`, `pdf_modified`, `pdf_created`, `has_outlines`, `pages_count`,
`md5`, `cachev2_md5`, `cachev2_pages`, `pagescolor_md5`, `qr_md5`, `qr_count`, `covers_md5`, `meta_md5`

FROM

`documents`

WHERE

`lock` = "" AND `status` = "" AND `pdf` = 1 AND `covers_md5` != `md5`

ORDER BY `id` DESC

LIMIT 1