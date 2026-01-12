UPDATE `users`

SET

`last_30_days_views`        = {{l30dvw}},
`last_30_days_downloads`    = {{l30ddl}},
`useragents`                = {{l30dua}},
`locations`                 = {{l30dip}}

WHERE `id` = {{user_id}}