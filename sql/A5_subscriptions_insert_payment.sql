INSERT INTO
    `payments`

    ( `subscription_id`, `duration`, `amount`, `vat`, `when`, `success`, `auto`, `amount_nexi`, `codTrans`, `user_id`, `ip`, `ua` )

VALUES
    ( {{subscription_id}}, {{duration}}, {{amount}}, {{vat}}, {{when}}, {{success}}, 1, {{amount_nexi}}, {{codTrans}}, 0, '', '' )
