DELETE FROM `sessions`

WHERE

`expires` < UNIX_TIMESTAMP()