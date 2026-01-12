SELECT
        `id`,
        `description`,
        `payment_mode`

FROM
        `subscriptions`

WHERE
        `is_active` = 1 AND
        `payment_is_auto` = 0 AND
        `valid_until` < {{today}} AND
        `payment_mode` = 'cc'

ORDER BY
        `id` DESC

LIMIT 1