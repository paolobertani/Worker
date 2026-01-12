SELECT
    `id`,
    `from`
FROM
    `updates_sent`
WHERE
    `when` = {{when}} AND
    `action` = "notice" AND
    `executed` = 0