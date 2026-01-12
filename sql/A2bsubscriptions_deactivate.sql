UPDATE
        `subscriptions`

SET
        `is_active` = 0,
        `bt_notified` = 1

WHERE
        `id` = {{id}}
