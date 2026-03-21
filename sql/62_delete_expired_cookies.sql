DELETE FROM `cookies`

WHERE

`when` < DATE_FORMAT( ( NOW() - INTERVAL 12 MONTH ), "%Y-%m-%d %H:%i:%s" )
