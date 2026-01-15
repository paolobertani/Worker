SELECT
        `id`,
        `description`,
        `is_active`,
        `valid_until`,
        `valid_until_day`,
        `payment_is_auto`,
        `amount`,
        `duration`,
        `vat`,
        `num_contratto`,
        `pan`,
        `pan_expire`,
        `user_id`,
        `group_id`

FROM
        `subscriptions`

WHERE
        `payment_mode` = 'cc' AND
        `is_active` = 1 AND
        `payment_is_auto` = 1 AND
        `last_payment_did_fail` = 0 AND
        `valid_until` < {{today}}

ORDER BY
        `id` DESC

LIMIT 1