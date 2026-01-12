SELECT
        `id`,
        `description`,
        `payment_mode`

FROM
        `subscriptions`

WHERE
        `is_active` = 1 AND
        `valid_until` < {{today}} AND
        `payment_mode` = 'bt' AND
        `bt_notified` = 0

ORDER BY
        `id` DESC

LIMIT 1