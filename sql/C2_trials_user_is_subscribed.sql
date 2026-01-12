SELECT
        `subscriptions`.`user_id`

FROM
        `subscriptions`

WHERE
        `subscriptions`.`user_id` = {{user_id}}

ORDER BY
        `subscriptions`.`id` DESC

LIMIT 1