UPDATE
    `updates_sent`
SET
    `executed` = 1,
    `when_sent` = {{when_sent}},
    `elapsed_time` = {{elapsed_time}},
    `recipients_count` = {{recipients_count}}
WHERE
    `id` = {{id}}