SELECT

`id`,
`in_inbox`, `pdfff`, `status`, `reply`

FROM

`sent_documents`

WHERE

`in_inbox` = 1 AND `status` = 0 AND `pdfff` = 0

ORDER BY `id` DESC

LIMIT 1