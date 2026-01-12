SELECT
    `id`,
    `from`,
    `action`
FROM
    `updates_sent`
WHERE
    `when` = {{when}} AND
    `executed` = 0 AND
    ( `action` = "snd0" OR `action` = "snd1" )
