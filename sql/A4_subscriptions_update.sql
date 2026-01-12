UPDATE
        `subscriptions`

SET
        `valid_until` = {{valid_until}},
        `last_payment_did_fail` = {{last_payment_did_fail}},
        `is_active` = {{is_active}},
        `payment_is_auto` = {{payment_is_auto}},
        `payment_request` = {{payment_request}}

WHERE
        `id` = {{id}}