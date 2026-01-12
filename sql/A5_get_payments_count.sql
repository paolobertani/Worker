SELECT
    COUNT(*) as `count`

FROM
    `payments`

WHERE
    `subscription_id` = {{subscription_id}}