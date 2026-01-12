UPDATE
        `subscriptions`

SET
        `is_active` = 0,
        `last_payment_did_fail` = IF( `last_payment_did_fail` = 1, 1, 2 )

WHERE
        `id` = {{id}}
