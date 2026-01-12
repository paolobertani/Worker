SELECT

DISTINCT(`user_id`)             AS `user_id`,
SUM( IF( `action` = 1, 1, 0 ) ) AS `views`,
SUM( IF( `action` = 2, 1, 0 ) ) AS `downloads`,
COUNT( DISTINCT( uasha1 ) )     AS `uacnt`,
COUNT( DISTINCT( ip ) )         AS `ipcnt`

FROM events_small

GROUP BY user_id