SELECT

`id`,
`worker_cmd`, `cover_width`, `cover_height`, `pdf`, `cache_size`, `pdf_size`, `pdfff_size`, `status`, `expire`,
`slow_pages`, `slow_milliseconds`, `has_slow_pages`, `idrolab_status`, `pdf_modified`, `pdf_created`, `has_outlines`, `pages_count`,
`md5`, `cachev2_md5`, `cachev2_pages`, `cached_pages_count`, `pagescolor_md5`, `qr_md5`, `qr_count`, `covers_md5`, `meta_md5`

FROM

`documents`

WHERE

`lock` = "" AND `status` = "" AND `pdf` = 1 AND `md5` = `cachev2_md5` AND `dont_cache` = 0 AND `pages_count` = `cachev2_pages` AND `pagescolor_md5` != `md5` AND `pagescolor_md5` != '<FAILED>'

ORDER BY `id` DESC

LIMIT 1
