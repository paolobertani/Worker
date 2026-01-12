UPDATE

`documents`

SET

`lock` = '',

`worker_cmd`        = {{worker_cmd}},
`cover_width`       = {{cover_width}},
`cover_height`      = {{cover_height}},
`pdf`               = {{pdf}},
`cache_size`        = {{cache_size}},
`pdf_size`          = {{pdf_size}},
`pdfff_size`        = {{pdfff_size}},
`slow_pages`        = {{slow_pages}},
`slow_milliseconds` = {{slow_milliseconds}},
`has_slow_pages`    = {{has_slow_pages}},
`idrolab_status`    = {{idrolab_status}},
`pdf_modified`      = {{pdf_modified}},
`pdf_created`       = {{pdf_created}},
`has_outlines`      = {{has_outlines}},
`pages_count`       = {{pages_count}},
`cachev2_md5`       = {{cachev2_md5}},
`cachev2_pages`     = {{cachev2_pages}},
`covers_md5`        = {{covers_md5}},
`pagescolor_md5`    = {{pagescolor_md5}},
`qr_md5`            = {{qr_md5}},
`qr_count`          = {{qr_count}},
`meta_md5`          = {{meta_md5}}

WHERE

`id` = {{id}}